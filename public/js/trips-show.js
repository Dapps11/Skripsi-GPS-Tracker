// ── Config dari window.__tripshow ────────────────────────────────
const MAP_TYPE  = window.__tripshow?.mapType  ?? 'gmaps';
const GMAPS_KEY = window.__tripshow?.gmapsKey ?? '';
const gpsPoints = window.__tripshow?.gpsPoints    ?? [];
const gpsPointsRaw = window.__tripshow?.gpsPointsRaw ?? [];
const gpsSegments = window.__tripshow?.gpsSegments ?? [];
const signalGaps   = window.__tripshow?.signalGaps  ?? [];
const trip      = window.__tripshow?.trip ?? null;
const stopEvents= window.__tripshow?.stopEvents ?? [];
let routeDeviations = [];
let intendedRoutePolyline = null;

// Helper sample points
function samplePoints(pts, max) {
    if (pts.length <= max) return pts;
    const r = [pts[0]], step = (pts.length - 2) / (max - 2);
    for (let i = 1; i < max - 1; i++) r.push(pts[Math.round(i * step)]);
    r.push(pts[pts.length - 1]);
    return r;
}

// Helper get strategic waypoints (menghindari muter-muter/noise GPS)
// Mengambil sejumlah kecil titik (misal 2-3) yang memiliki kecepatan tinggi
// sehingga dipastikan berada di jalan utama, bukan di area parkir/rest area.
function getStrategicWaypoints(pts, count = 3) {
    if (pts.length < 10) return [];
    
    // Filter titik dengan kecepatan memadai (>15 km/h)
    const valid = pts.filter(p => (parseFloat(p.speed_kmh) || 0) > 15);
    
    if (valid.length < count) {
        // Fallback jika tidak ada data kecepatan
        return samplePoints(pts, count + 2).slice(1, -1);
    }
    
    // Ambil sampel secara merata dari titik berkecepatan tinggi
    const step = valid.length / (count + 1);
    const result = [];
    for (let i = 1; i <= count; i++) {
        result.push(valid[Math.floor(i * step)]);
    }
    return result;
}

// ── Haversine distance (km) ───────────────────────────────────────
function haversineKm(lat1, lng1, lat2, lng2) {
    const R    = 6371;
    const dLat = (lat2 - lat1) * Math.PI / 180;
    const dLng = (lng2 - lng1) * Math.PI / 180;
    const a    = Math.sin(dLat / 2) ** 2
               + Math.cos(lat1 * Math.PI / 180)
               * Math.cos(lat2 * Math.PI / 180)
               * Math.sin(dLng / 2) ** 2;
    return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
}

// ── Build deviation path overlays dari GPS raw + deviation windows ───────────────
//
// Setiap deviation entry memiliki start_ts_utc dan end_ts_utc (ISO 8601 UTC).
// Fungsi ini memetakan titik-titik GPS yang timestampnya masuk jendela waktu
// tersebut, sehingga bisa digambar sebagai overlay merah di atas track oranye.
//
function buildDeviationPaths(rawPoints, deviations) {
    if (!rawPoints?.length || !deviations?.length) return [];

    return deviations
        .filter(dev => dev.start_ts_utc && dev.end_ts_utc)
        .map(dev => {
            const tStart = new Date(dev.start_ts_utc).getTime() - 2000; // toleransi ±2 detik
            const tEnd   = new Date(dev.end_ts_utc).getTime()   + 2000;

            // Filter GPS points yang masuk jendela waktu deviasi ini
            const pts = rawPoints.filter(pt => {
                // gps_timestamp dari PHP biasanya UTC ISO string
                const raw = pt.gps_timestamp;
                const ms  = new Date(typeof raw === 'string' && !raw.endsWith('Z') ? raw + 'Z' : raw).getTime();
                return ms >= tStart && ms <= tEnd;
            });

            return { ...dev, gpsPoints: pts };
        })
        .filter(d => d.gpsPoints.length >= 2);
}

// ════════════════════════════════════════════════════════════════
// OSM HISTORY MAP
// ════════════════════════════════════════════════════════════════
async function initOSMHistory() {
    const el = document.getElementById('history-map');
    if (!el) return;

    const hasGps = gpsPoints && gpsPoints.length >= 2;
    const center = hasGps
        ? [+gpsPoints[0].latitude, +gpsPoints[0].longitude]
        : [+trip.origin_lat, +trip.origin_lng];

    const map = L.map('history-map').setView(center, 13);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors', maxZoom: 19
    }).addTo(map);

    // ── Rute biru (Intended Route dari Backend) ──────────────────────
    if (intendedRoutePolyline && intendedRoutePolyline.length > 0) {
        const rLatLng = intendedRoutePolyline.map(c => [c.lat, c.lng]);
        
        // Shadow biru
        L.polyline(rLatLng, {
            color:'#818cf8', weight:10, opacity:.2,
            lineCap:'round', lineJoin:'round'
        }).addTo(map);
        
        // Garis biru utama
        L.polyline(rLatLng, {
            color:'#4f46e5', weight:5, opacity:.88,
            lineCap:'round', lineJoin:'round'
        }).addTo(map);
    } else if (trip.origin_lat && trip.dest_lat) {
        // Fallback garis lurus biru putus-putus
        L.polyline(
            [[+trip.origin_lat, +trip.origin_lng], [+trip.dest_lat, +trip.dest_lng]],
            { color:'#4f46e5', weight:4, opacity:.6, dashArray:'10,7' }
        ).addTo(map);
    }

    // ── Rute hijau (Haversine straight-line) ─────────────────────────
    if (trip.origin_lat && trip.dest_lat) {
        const oLat = +trip.origin_lat, oLng = +trip.origin_lng;
        const dLat = +trip.dest_lat,   dLng = +trip.dest_lng;
        const distKm = haversineKm(oLat, oLng, dLat, dLng).toFixed(2);

        // Shadow hijau
        L.polyline([[oLat, oLng], [dLat, dLng]], {
            color: '#86efac', weight: 10, opacity: 0.25,
            lineCap: 'round', lineJoin: 'round'
        }).addTo(map);
        // Garis hijau utama (putus-putus untuk membedakan dari routing)
        L.polyline([[oLat, oLng], [dLat, dLng]], {
            color: '#16a34a', weight: 3.5, opacity: 0.9,
            dashArray: '12, 7', lineCap: 'round', lineJoin: 'round'
        }).addTo(map)
         .bindPopup(
            `<div style="font-weight:700;color:#16a34a;font-size:12px;">📏 Jarak Haversine (Garis Lurus)</div>
             <div style="font-size:11px;color:#6b7280;margin-top:4px;">Jarak lurus: <b>${distKm} km</b></div>
             <div style="font-size:10px;color:#9ca3af;margin-top:2px;">Tanpa memperhitungkan jalan / rute</div>`
        );
    }

    // ── Track orange (GPS history) — dipecah per segmen biar gak ada
    //    garis lurus "meloncat" pas sinyal sempat putus ──────────────
    if (gpsSegments && gpsSegments.length) {
        gpsSegments.forEach(segment => {
            if (segment.length < 2) return;
            const coords = segment.map(p => [+p.latitude, +p.longitude]);

            L.polyline(coords, {
                color:'#fb923c', weight:10, opacity:.18,
                lineCap:'round', lineJoin:'round'
            }).addTo(map);
            L.polyline(coords, {
                color:'#f97316', weight:4.5, opacity:.9,
                lineCap:'round', lineJoin:'round'
            }).addTo(map);
        });
    } else if (hasGps) {
        // Fallback: trip lama sebelum fitur segmentasi ini ada
        const coords = gpsPoints.map(p => [+p.latitude, +p.longitude]);
        L.polyline(coords, { color:'#fb923c', weight:10, opacity:.18, lineCap:'round', lineJoin:'round' }).addTo(map);
        L.polyline(coords, { color:'#f97316', weight:4.5, opacity:.9, lineCap:'round', lineJoin:'round' }).addTo(map);
    }

    // ── Celah sinyal — garis ungu putus + bubble permanen ──────────
    if (signalGaps && signalGaps.length && gpsSegments && gpsSegments.length >= 2) {
        const gapDotIcon = L.divIcon({
            html: `<div style="width:14px;height:14px;background:#7c3aed;border-radius:50%;border:2px solid white;box-shadow:0 0 0 3px #7c3aed55;"></div>`,
            iconSize: [14, 14], iconAnchor: [7, 7], className: ''
        });
        signalGaps.forEach((gap, i) => {
            const nextSeg = gpsSegments[i + 1];
            if (!nextSeg || !nextSeg.length) return;

            const startPt  = [+gap.lat, +gap.lng];
            const endPt    = [+nextSeg[0].latitude, +nextSeg[0].longitude];
            const midPt    = [(startPt[0] + endPt[0]) / 2, (startPt[1] + endPt[1]) / 2];

            const mins     = Math.floor(gap.duration_sec / 60);
            const secs     = gap.duration_sec % 60;
            const durLabel = mins > 0 ? `${mins}m ${secs}d` : `${secs}d`;
            const tStart   = new Date(gap.start_at).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
            const tEnd     = new Date(gap.end_at).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });

            // Garis ungu putus-putus antara titik akhir sebelum gap dan titik awal sesudah gap
            L.polyline([startPt, endPt], {
                color: '#7c3aed', weight: 3, opacity: 0.85,
                dashArray: '8, 6', lineCap: 'round', lineJoin: 'round'
            }).addTo(map);

            L.marker(midPt, { icon: gapDotIcon, zIndexOffset: 400 })
             .addTo(map)
             .bindPopup(
                 `<div style="font-weight:700;color:#7c3aed;">📡 Sinyal GPS/Internet Hilang</div>
                  <div style="font-size:10px;color:#6b7280;margin-top:4px;">${tStart} — ${tEnd} (${durLabel})</div>`
             );
        });
    }

    // ── Marker waypoint ───────────────────────────────────────────
    const mkWP = (color, size = 16) => L.divIcon({
        html: `<div style="width:${size}px;height:${size}px;background:${color};border-radius:50%;border:3px solid white;box-shadow:0 0 0 3px ${color}55;"></div>`,
        iconSize: [size,size], iconAnchor: [size/2,size/2], className: ''
    });

    // Start marker
    L.marker([+trip.origin_lat, +trip.origin_lng], { icon: mkWP('#22c55e', 18), zIndexOffset: 500 })
     .addTo(map)
     .bindTooltip(`<b>🟢 ${trip.origin_name}</b>`, { permanent: false, direction: 'top' });

    // End marker
    L.marker([+trip.dest_lat, +trip.dest_lng], { icon: mkWP('#ef4444', 18), zIndexOffset: 500 })
     .addTo(map)
     .bindTooltip(`<b>🔴 ${trip.dest_name}</b>`, { permanent: false, direction: 'bottom' });

    // ── Stop markers (titik berhenti lama) ────────────────────────
    if (stopEvents && stopEvents.length) {
        stopEvents.forEach(stop => {
            const stopIcon = L.divIcon({
                html: `<div style="
                            width:16px;height:16px;background:#dc2626;border-radius:50%;
                            border:3px solid white;box-shadow:0 0 0 4px #dc262640;
                            display:flex;align-items:center;justify-content:center;
                        ">
                            <div style="width:5px;height:5px;background:white;border-radius:50%;"></div>
                        </div>`,
                iconSize: [16, 16], iconAnchor: [8, 8], className: ''
            });

            const stopMarker = L.marker([stop.lat, stop.lng], {
                icon: stopIcon,
                zIndexOffset: 600
            }).addTo(map);

            stopMarker.bindPopup(
                `<div style="font-weight:700;color:#dc2626;">⏱️ Stop ${stop.duration_label}</div>
                 <div style="font-size:10px;color:#6b7280;margin-top:4px;">${stop.started_at} — ${stop.ended_at}</div>`
            );
        });
    }

    // ── Route deviation — overlay merah di segmen keluar jalur ───────
    if (routeDeviations && routeDeviations.length) {
        // Overlay jalur merah pada segmen GPS yang keluar jalur
        const devPaths = buildDeviationPaths(gpsPointsRaw, routeDeviations);
        devPaths.forEach(dp => {
            const coords = dp.gpsPoints.map(p => [+p.latitude, +p.longitude]);
            // Glow merah
            L.polyline(coords, {
                color: '#dc2626', weight: 12, opacity: 0.22,
                lineCap: 'round', lineJoin: 'round'
            }).addTo(map);
            // Garis merah putus-putus utama
            L.polyline(coords, {
                color: '#dc2626', weight: 4, opacity: 0.9,
                dashArray: '8, 5', lineCap: 'round', lineJoin: 'round'
            }).addTo(map)
             .bindPopup(
                `<div style="font-weight:700;color:#dc2626;font-size:12px;">🚧 Segmen Keluar Jalur</div>
                 <div style="font-size:11px;color:#6b7280;margin-top:4px;">${dp.started_at} — ${dp.ended_at} (${dp.duration_label})</div>
                 <div style="font-size:11px;color:#dc2626;margin-top:2px;">Menyimpang maks. <b>${dp.max_distance_m}m</b> dari jalur</div>`
            );
        });

        // Marker amber di titik tengah setiap event (identifikasi cepat)
        const devIcon = L.divIcon({
            html: `<div style="width:14px;height:14px;background:#d97706;border-radius:50%;border:2px solid white;box-shadow:0 0 0 3px #d9770640;"></div>`,
            iconSize: [14, 14], iconAnchor: [7, 7], className: ''
        });
        routeDeviations.forEach(dev => {
            L.marker([dev.lat, dev.lng], { icon: devIcon, zIndexOffset: 550 })
             .addTo(map)
             .bindPopup(
                 `<div style="font-weight:700;color:#d97706;">🚧 Keluar Jalur (Adaptif)</div>
                  <div style="font-size:10px;color:#6b7280;margin-top:4px;">${dev.started_at} — ${dev.ended_at} (${dev.duration_label})</div>
                  <div style="font-size:10px;color:#d97706;margin-top:2px;">Menyimpang ${dev.max_distance_m}m dari jalur</div>`
             );
        });
    }

    // Fit bounds ke semua elemen
    const allCoords = [];
    if (gpsPoints && gpsPoints.length) {
        gpsPoints.forEach(p => allCoords.push([+p.latitude, +p.longitude]));
    } else {
        allCoords.push([+trip.origin_lat, +trip.origin_lng]);
        allCoords.push([+trip.dest_lat,   +trip.dest_lng]);
    }
    if (allCoords.length) map.fitBounds(allCoords, { padding: [30, 30] });
}

// ════════════════════════════════════════════════════════════════
// GOOGLE MAPS HISTORY
// ════════════════════════════════════════════════════════════════
function initGMapsHistory() {
    document.getElementById('history-map').style.display  = 'none';
    document.getElementById('history-gmap').style.display = 'block';

    if (!GMAPS_KEY) { initOSMHistory(); return; }

    if (window.google && window.google.maps) { googleMapsLoaded(); return; }
    if (document.getElementById('gmaps-sdk')) return;

    const s   = document.createElement('script');
    s.id      = 'gmaps-sdk';
    s.src     = `https://maps.googleapis.com/maps/api/js?key=${GMAPS_KEY}&libraries=geometry&callback=googleMapsLoaded&loading=async`;
    s.async   = true;
    s.defer   = true;
    document.head.appendChild(s);
}

window.googleMapsLoaded = async function() {
    await calculateDeviationsAndRoute();
    if (MAP_TYPE === 'gmaps') {
        createGHistory();
    } else {
        initOSMHistory(); // OSM fallback tapi tetap punya routeDeviations
    }
}

// Menghitung jarak (meter) dari titik GPS ke polyline menggunakan
// Google Maps Geometry Library (computeDistanceBetween + computeHeading + interpolate).
// Fungsi ini hanya boleh dipanggil setelah Google Maps selesai dimuat.
function pointToPolylineDistJS(ptLat, ptLng, polyline) {
    const geo   = google.maps.geometry.spherical;
    const ptLL  = new google.maps.LatLng(ptLat, ptLng);
    let minDist = Infinity;
    const count = polyline.length;

    if (count === 0) return 0;
    if (count === 1) {
        return geo.computeDistanceBetween(ptLL, new google.maps.LatLng(polyline[0].lat, polyline[0].lng));
    }

    for (let i = 0; i < count - 1; i++) {
        const aLL = new google.maps.LatLng(polyline[i].lat,   polyline[i].lng);
        const bLL = new google.maps.LatLng(polyline[i+1].lat, polyline[i+1].lng);

        const dAB = geo.computeDistanceBetween(aLL, bLL); // meter
        if (dAB === 0) {
            minDist = Math.min(minDist, geo.computeDistanceBetween(ptLL, aLL));
            continue;
        }

        // Heading A→B dan A→P (derajat)
        const headingAB = geo.computeHeading(aLL, bLL);
        const headingAP = geo.computeHeading(aLL, ptLL);
        const dAP       = geo.computeDistanceBetween(aLL, ptLL); // meter

        // Komponen along-track: seberapa jauh proyeksi P di sepanjang A–B
        const angDiffRad = (headingAP - headingAB) * Math.PI / 180;
        const dAT        = dAP * Math.cos(angDiffRad);

        let closestPoint;
        if (dAT <= 0) {
            closestPoint = aLL;            // proyeksi di belakang A
        } else if (dAT >= dAB) {
            closestPoint = bLL;            // proyeksi di depan B
        } else {
            // Interpolasi titik terdekat di segmen A–B (Google Maps spherical interpolate)
            closestPoint = geo.interpolate(aLL, bLL, dAT / dAB);
        }

        const dist = geo.computeDistanceBetween(ptLL, closestPoint);
        if (dist < minDist) minDist = dist;
    }

    return minDist;
}

async function calculateDeviationsAndRoute() {
    if (!gpsPoints || gpsPoints.length < 2) {
        document.getElementById('dev-info-box').innerText = "Data GPS tidak memadai";
        document.getElementById('dev-count').innerText = "0";
        return;
    }
    if (!trip || !trip.origin_lat || !trip.dest_lat) {
        document.getElementById('dev-info-box').innerText = "Titik awal/tujuan tidak ada";
        document.getElementById('dev-count').innerText = "0";
        return;
    }
    
    document.getElementById('dev-info-box').innerText = "Meminta rute...";
    
    const oLat = parseFloat(trip.origin_lat);
    const oLng = parseFloat(trip.origin_lng);
    const dLat = parseFloat(trip.dest_lat);
    const dLng = parseFloat(trip.dest_lng);
    
    const ds = new google.maps.DirectionsService();
    try {
        const result = await ds.route({
            origin: new google.maps.LatLng(oLat, oLng),
            destination: new google.maps.LatLng(dLat, dLng),
            travelMode: google.maps.TravelMode.DRIVING,
            provideRouteAlternatives: true
        });
        
        if (!result.routes || result.routes.length === 0) {
            document.getElementById('dev-info-box').innerText = "Rute tidak ditemukan";
            document.getElementById('dev-count').innerText = "0";
            return;
        }
        
        const alternatives = result.routes.map(r => r.overview_path.map(p => ({ lat: p.lat(), lng: p.lng() })));
        
        document.getElementById('dev-info-box').innerText = "Mencocokkan...";
        
        let earlyPoints = [];
        const searchLimit = Math.min(Math.floor(gpsPoints.length * 0.5), 200);
        for(let i = 0; i < searchLimit; i++) {
            if (parseFloat(gpsPoints[i].speed_kmh) > 10) {
                earlyPoints.push(gpsPoints[i]);
                if (earlyPoints.length >= 50) break;
            }
        }
        if (earlyPoints.length === 0) {
            earlyPoints = gpsPoints.slice(0, Math.min(20, gpsPoints.length));
        }
        
        let bestRoute = alternatives[0];
        let bestScore = Infinity;
        
        for (const route of alternatives) {
            let sumDist = 0;
            for (const pt of earlyPoints) {
                sumDist += pointToPolylineDistJS(parseFloat(pt.latitude), parseFloat(pt.longitude), route);
            }
            const avgDist = sumDist / earlyPoints.length;
            if (avgDist < bestScore) {
                bestScore = avgDist;
                bestRoute = route;
            }
        }
        
        intendedRoutePolyline = bestRoute;
        
        document.getElementById('dev-info-box').innerText = "Menghitung deviasi...";
        
        const maxDistanceMeters = 150;
        const minDurationMinutes = 2;
        let rawDeviations = [];
        let total = gpsPoints.length;
        let i = 0;
        
        while (i < total) {
            let pt = gpsPoints[i];
            let dist = pointToPolylineDistJS(parseFloat(pt.latitude), parseFloat(pt.longitude), bestRoute);
            
            if (dist > maxDistanceMeters) {
                let startIdx = i;
                let maxDist = dist;
                let j = i;
                
                while (j + 1 < total) {
                    let nextPt = gpsPoints[j + 1];
                    let nextDist = pointToPolylineDistJS(parseFloat(nextPt.latitude), parseFloat(nextPt.longitude), bestRoute);
                    if (nextDist > maxDistanceMeters) {
                        maxDist = Math.max(maxDist, nextDist);
                        j++;
                    } else {
                        break;
                    }
                }
                
                let startTimeStr = gpsPoints[startIdx].gps_timestamp;
                if (startTimeStr && !startTimeStr.endsWith('Z')) startTimeStr += 'Z';
                let endTimeStr = gpsPoints[j].gps_timestamp;
                if (endTimeStr && !endTimeStr.endsWith('Z')) endTimeStr += 'Z';
                
                let startTime = new Date(startTimeStr);
                let endTime = new Date(endTimeStr);
                let durMin = (endTime - startTime) / 60000;
                
                if (durMin >= minDurationMinutes) {
                    let midIdx = startIdx + Math.floor((j - startIdx) / 2);
                    let midPt = gpsPoints[midIdx];
                    
                    let pad = (n) => n.toString().padStart(2, '0');
                    let formatTime = (d) => {
                        let tz = new Date(d.toLocaleString('en-US', { timeZone: 'Asia/Jakarta' }));
                        return `${pad(tz.getHours())}:${pad(tz.getMinutes())}:${pad(tz.getSeconds())}`;
                    };
                    
                    // Hitung jarak tempuh saat off-route (sum haversine antar GPS points dalam window)
                    let offRouteKm = 0;
                    for (let k = startIdx; k < j; k++) {
                        const pA = gpsPoints[k];
                        const pB = gpsPoints[k + 1];
                        offRouteKm += haversineKm(
                            parseFloat(pA.latitude), parseFloat(pA.longitude),
                            parseFloat(pB.latitude), parseFloat(pB.longitude)
                        );
                    }

                    rawDeviations.push({
                        lat: parseFloat(midPt.latitude),
                        lng: parseFloat(midPt.longitude),
                        max_distance_m: Math.round(maxDist),
                        off_route_km: offRouteKm,
                        started_at: formatTime(startTime),
                        ended_at: formatTime(endTime),
                        start_ts_utc: startTime.toISOString(),
                        end_ts_utc: endTime.toISOString(),
                        duration_sec: (endTime - startTime) / 1000,
                        duration_label: durMin >= 60 ? Math.floor(durMin / 60) + 'j ' + Math.floor(durMin % 60) + 'm' : Math.floor(durMin) + ' menit'
                    });
                }
                i = j + 1;
            } else {
                i++;
            }
        }
        
        routeDeviations = rawDeviations;
        
        document.getElementById('dev-count').innerText = routeDeviations.length;
        if (routeDeviations.length === 0) {
            document.getElementById('dev-info-box').innerHTML = "Konsisten di jalur yang dipilih";
        } else {
            let mDev      = Math.max(...routeDeviations.map(d => d.max_distance_m));
            let totalOffKm = routeDeviations.reduce((s, d) => s + (d.off_route_km || 0), 0);
            let offLabel   = totalOffKm >= 1
                ? totalOffKm.toFixed(1) + ' km'
                : Math.round(totalOffKm * 1000) + ' m';
            document.getElementById('dev-info-box').innerHTML =
                `Maks. <span style="font-weight:700;">${mDev}m</span> dari jalur` +
                `<br><span style="color:#dc2626;">± ${offLabel} ditempuh di luar jalur</span>`;
            
            document.getElementById('legend-dev-item').style.display = 'flex';
            document.getElementById('legend-dev-text').innerText = `Keluar Jalur (${routeDeviations.length}×)`;
        }
        
    } catch(e) {
        console.error("Google Directions error:", e);
        document.getElementById('dev-info-box').innerText = "Gagal memuat rute.";
        document.getElementById('dev-count').innerText = "0";
    }
}

window.createGHistory = async function () {
    const hasGps = gpsPoints && gpsPoints.length >= 2;
    const center = hasGps
        ? { lat: +gpsPoints[0].latitude, lng: +gpsPoints[0].longitude }
        : { lat: +trip.origin_lat,        lng: +trip.origin_lng };

    const gMap = new google.maps.Map(document.getElementById('history-gmap'), {
        center, zoom: 13, mapTypeId: 'roadmap',
        mapTypeControl: false, fullscreenControl: false, streetViewControl: false,
    });

    // ── Rute biru (Intended Route dari Backend) ───────────
    if (intendedRoutePolyline && intendedRoutePolyline.length > 0) {
        const path = intendedRoutePolyline.map(c => ({ lat: +c.lat, lng: +c.lng }));
        
        // Shadow biru
        new google.maps.Polyline({
            path, map: gMap,
            strokeColor: '#818cf8', strokeOpacity: 0.2, strokeWeight: 10,
            zIndex: 1,
        });
        
        // Garis biru utama
        new google.maps.Polyline({
            path, map: gMap,
            strokeColor: '#4f46e5', strokeOpacity: 0.88, strokeWeight: 5,
            zIndex: 2,
        });
    } else if (trip.origin_lat && trip.dest_lat) {
        // Fallback lurus putus-putus
        new google.maps.Polyline({
            path: [
                { lat: +trip.origin_lat, lng: +trip.origin_lng },
                { lat: +trip.dest_lat,   lng: +trip.dest_lng }
            ],
            map: gMap,
            strokeColor: '#4f46e5', strokeOpacity: 0.6, strokeWeight: 4,
            icons: [{ icon: { path: 'M 0,-1 0,1', strokeOpacity: 0.6, scale: 2 }, offset: '0', repeat: '15px' }],
            zIndex: 1,
        });
    }

    // ── Rute hijau (Haversine straight-line) ─────────────────────
    if (trip.origin_lat && trip.dest_lat) {
        const oLat = +trip.origin_lat, oLng = +trip.origin_lng;
        const dLat = +trip.dest_lat,   dLng = +trip.dest_lng;
        const distKm = haversineKm(oLat, oLng, dLat, dLng).toFixed(2);

        // Shadow hijau
        new google.maps.Polyline({
            path: [{ lat: oLat, lng: oLng }, { lat: dLat, lng: dLng }],
            map: gMap,
            strokeColor: '#86efac', strokeOpacity: 0.3, strokeWeight: 10,
            zIndex: 1,
        });

        // Garis putus-putus hijau utama
        const haversineLine = new google.maps.Polyline({
            path: [{ lat: oLat, lng: oLng }, { lat: dLat, lng: dLng }],
            map: gMap,
            strokeColor: '#16a34a', strokeOpacity: 0,
            strokeWeight: 3.5,
            icons: [{
                icon: {
                    path: 'M 0,-1 0,1',
                    strokeOpacity: 0.95,
                    strokeColor: '#16a34a',
                    strokeWeight: 3.5,
                    scale: 4,
                },
                offset: '0',
                repeat: '18px',
            }],
            zIndex: 2,
        });

        // InfoWindow saat klik garis Haversine
        const haverInfoWin = new google.maps.InfoWindow();
        const midLat = (oLat + dLat) / 2;
        const midLng = (oLng + dLng) / 2;
        haversineLine.addListener('click', (e) => {
            haverInfoWin.setContent(
                `<div style="font-weight:700;color:#16a34a;font-size:12px;">📏 Jarak Haversine (Garis Lurus)</div>
                 <div style="font-size:11px;color:#6b7280;margin-top:4px;">Jarak lurus: <b>${distKm} km</b></div>
                 <div style="font-size:10px;color:#9ca3af;margin-top:2px;">Tanpa memperhitungkan jalan / rute</div>`
            );
            haverInfoWin.setPosition(e.latLng);
            haverInfoWin.open(gMap);
        });
    }

    // ── Track orange (GPS history) — dipecah per segmen biar gak ada
    //    garis lurus "meloncat" pas sinyal sempat putus ──────────────
    if (gpsSegments && gpsSegments.length) {
        gpsSegments.forEach(segment => {
            if (segment.length < 2) return;
            const coords = segment.map(p => ({ lat: +p.latitude, lng: +p.longitude }));

            new google.maps.Polyline({
                path: coords, map: gMap,
                strokeColor: '#fb923c', strokeOpacity: .2, strokeWeight: 10,
                zIndex: 2,
            });
            new google.maps.Polyline({
                path: coords, map: gMap,
                strokeColor: '#f97316', strokeOpacity: .9, strokeWeight: 4.5,
                zIndex: 3,
            });
        });
    } else if (hasGps) {
        // Fallback: trip lama sebelum fitur segmentasi ini ada
        const coords = gpsPoints.map(p => ({ lat: +p.latitude, lng: +p.longitude }));
        new google.maps.Polyline({ path: coords, map: gMap, strokeColor: '#fb923c', strokeOpacity: .2, strokeWeight: 10, zIndex: 2 });
        new google.maps.Polyline({ path: coords, map: gMap, strokeColor: '#f97316', strokeOpacity: .9, strokeWeight: 4.5, zIndex: 3 });
    }

    // ── Celah sinyal — garis ungu putus-putus + titik di tengah (klik untuk info) ──
    const gapInfoWindow = new google.maps.InfoWindow();
    if (signalGaps && signalGaps.length && gpsSegments && gpsSegments.length >= 2) {
        signalGaps.forEach((gap, i) => {
            const nextSeg = gpsSegments[i + 1];
            if (!nextSeg || !nextSeg.length) return;

            const startPt  = { lat: +gap.lat, lng: +gap.lng };
            const endPt    = { lat: +nextSeg[0].latitude, lng: +nextSeg[0].longitude };
            const midPt    = { lat: (startPt.lat + endPt.lat) / 2, lng: (startPt.lng + endPt.lng) / 2 };
            const mins     = Math.floor(gap.duration_sec / 60);
            const secs     = gap.duration_sec % 60;
            const durLabel = mins > 0 ? `${mins}m ${secs}d` : `${secs}d`;
            const tStart   = new Date(gap.start_at).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
            const tEnd     = new Date(gap.end_at).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });

            // Garis ungu putus-putus
            new google.maps.Polyline({
                path: [startPt, endPt], map: gMap,
                strokeColor: '#7c3aed', strokeOpacity: 0, strokeWeight: 3,
                icons: [{ icon: { path: 'M 0,-1 0,1', strokeOpacity: 1, strokeColor: '#7c3aed', strokeWeight: 3, scale: 4 }, offset: '0', repeat: '14px' }],
                zIndex: 4,
            });

            // Titik ungu di tengah — klik untuk buka info
            const gapMarker = new google.maps.Marker({
                position: midPt, map: gMap,
                icon: { path: google.maps.SymbolPath.CIRCLE, scale: 7, fillColor: '#7c3aed', fillOpacity: 1, strokeColor: 'white', strokeWeight: 2 },
                zIndex: 997,
            });
            gapMarker.addListener('click', () => {
                gapInfoWindow.setContent(
                    `<div style="font-weight:700;color:#7c3aed;font-size:12px;">📡 Sinyal GPS/Internet Hilang</div>
                     <div style="font-size:11px;color:#6b7280;margin-top:4px;">${tStart} — ${tEnd} (${durLabel})</div>`
                );
                gapInfoWindow.open(gMap, gapMarker);
            });
        });
    }

    // ── Route deviation — overlay merah + marker amber ───────────────────────────
    if (routeDeviations && routeDeviations.length) {
        // Gambar overlay jalur merah di segmen GPS yang keluar jalur
        const devPaths = buildDeviationPaths(gpsPointsRaw, routeDeviations);
        devPaths.forEach(dp => {
            const coords = dp.gpsPoints.map(p => ({ lat: +p.latitude, lng: +p.longitude }));

            // Glow merah (halo)
            new google.maps.Polyline({
                path: coords, map: gMap,
                strokeColor: '#dc2626', strokeOpacity: 0.22, strokeWeight: 12,
                zIndex: 4,
            });

            // Garis merah putus-putus utama
            const devLine = new google.maps.Polyline({
                path: coords, map: gMap,
                strokeColor: '#dc2626', strokeOpacity: 0,
                strokeWeight: 4,
                icons: [{
                    icon: {
                        path: 'M 0,-1 0,1',
                        strokeOpacity: 0.95,
                        strokeColor: '#dc2626',
                        strokeWeight: 4,
                        scale: 3,
                    },
                    offset: '0',
                    repeat: '12px',
                }],
                zIndex: 5,
            });

            // Klik garis merah → InfoWindow detail
            const devLineInfo = new google.maps.InfoWindow();
            devLine.addListener('click', (e) => {
                devLineInfo.setContent(
                    `<div style="font-weight:700;color:#dc2626;font-size:12px;">🚧 Segmen Keluar Jalur</div>
                     <div style="font-size:11px;color:#6b7280;margin-top:4px;">${dp.started_at} — ${dp.ended_at} (${dp.duration_label})</div>
                     <div style="font-size:11px;color:#dc2626;margin-top:2px;">Menyimpang maks. <b>${dp.max_distance_m}m</b> dari jalur</div>`
                );
                devLineInfo.setPosition(e.latLng);
                devLineInfo.open(gMap);
            });
        });

        // Marker amber di titik tengah event deviasi
        const devInfoWin = new google.maps.InfoWindow();
        routeDeviations.forEach(dev => {
            const devMk = new google.maps.Marker({
                position: { lat: dev.lat, lng: dev.lng },
                map: gMap,
                icon: {
                    path: google.maps.SymbolPath.CIRCLE,
                    scale: 7, fillColor: '#d97706', fillOpacity: 1,
                    strokeColor: 'white', strokeWeight: 2,
                },
                zIndex: 950,
            });
            devMk.addListener('click', () => {
                devInfoWin.setContent(
                    `<div style="font-weight:700;color:#d97706;font-size:12px;">🚧 Keluar Jalur (Adaptif)</div>
                     <div style="font-size:11px;color:#6b7280;margin-top:4px;">${dev.started_at} — ${dev.ended_at} (${dev.duration_label})</div>
                     <div style="font-size:11px;color:#d97706;margin-top:2px;">Menyimpang ${dev.max_distance_m}m dari jalur yang ditempuh</div>`
                );
                devInfoWin.open(gMap, devMk);
            });
        });
    }

    // ── Marker waypoint ───────────────────────────────────────────
    new google.maps.Marker({
        position: { lat: +trip.origin_lat, lng: +trip.origin_lng },
        map: gMap,
        icon: {
            path: google.maps.SymbolPath.CIRCLE,
            scale: 10, fillColor: '#22c55e', fillOpacity: 1,
            strokeColor: 'white', strokeWeight: 3,
        },
        title: 'Start: ' + trip.origin_name,
        zIndex: 998,
    });

    new google.maps.Marker({
        position: { lat: +trip.dest_lat, lng: +trip.dest_lng },
        map: gMap,
        icon: {
            path: google.maps.SymbolPath.CIRCLE,
            scale: 10, fillColor: '#ef4444', fillOpacity: 1,
            strokeColor: 'white', strokeWeight: 3,
        },
        title: 'End: ' + trip.dest_name,
        zIndex: 998,
    });

    // ── Stop markers (titik berhenti lama) — klik untuk buka info ─
    const stopInfoWindow = new google.maps.InfoWindow();
    if (stopEvents && stopEvents.length) {
        stopEvents.forEach(stop => {
            const stopMarker = new google.maps.Marker({
                position: { lat: stop.lat, lng: stop.lng },
                map: gMap,
                icon: {
                    path: google.maps.SymbolPath.CIRCLE,
                    scale: 8, fillColor: '#dc2626', fillOpacity: 1,
                    strokeColor: 'white', strokeWeight: 3,
                },
                zIndex: 999,
            });
            stopMarker.addListener('click', () => {
                stopInfoWindow.setContent(
                    `<div style="font-weight:700;color:#dc2626;font-size:12px;">⏱️ Stop ${stop.duration_label}</div>
                     <div style="font-size:11px;color:#6b7280;margin-top:4px;">${stop.started_at} — ${stop.ended_at}</div>`
                );
                stopInfoWindow.open(gMap, stopMarker);
            });
        });
    }

    // ── Route deviation markers (amber) ──────────────────────────
    if (routeDeviations && routeDeviations.length) {
        const devInfoWin = new google.maps.InfoWindow();
        routeDeviations.forEach(dev => {
            const devMk = new google.maps.Marker({
                position: { lat: dev.lat, lng: dev.lng },
                map: gMap,
                icon: {
                    path: google.maps.SymbolPath.CIRCLE,
                    scale: 8, fillColor: '#d97706', fillOpacity: 1,
                    strokeColor: 'white', strokeWeight: 3,
                },
                zIndex: 950,
            });
            devMk.addListener('click', () => {
                devInfoWin.setContent(
                    `<div style="font-weight:700;color:#d97706;font-size:12px;">🚧 Keluar Jalur (Adaptif)</div>
                     <div style="font-size:11px;color:#6b7280;margin-top:4px;">${dev.started_at} — ${dev.ended_at} (${dev.duration_label})</div>
                     <div style="font-size:11px;color:#d97706;margin-top:2px;">Menyimpang ${dev.max_distance_m}m dari jalur yang ditempuh</div>`
                );
                devInfoWin.open(gMap, devMk);
            });
        });
    }

    // Fit bounds
    const bounds = new google.maps.LatLngBounds();
    if (hasGps) {
        gpsPoints.forEach(p => bounds.extend({ lat: +p.latitude, lng: +p.longitude }));
    } else {
        bounds.extend({ lat: +trip.origin_lat, lng: +trip.origin_lng });
        bounds.extend({ lat: +trip.dest_lat,   lng: +trip.dest_lng });
    }
    gMap.fitBounds(bounds, 30);
};

// ── Fetch ETA API untuk trips/show ───────────────────────────────
async function fetchShowAPIeta() {
    if (!trip.origin_lat || !trip.dest_lat) return;

    const oLat = +trip.origin_lat, oLng = +trip.origin_lng;
    const dLat = +trip.dest_lat,   dLng = +trip.dest_lng;

    if (MAP_TYPE === 'gmaps' && GMAPS_KEY && window.google) {
        const svc = new google.maps.DirectionsService();
        svc.route({
            origin:      { lat: oLat, lng: oLng },
            destination: { lat: dLat, lng: dLng },
            travelMode:  google.maps.TravelMode.DRIVING,
            // Traffic real-time
            drivingOptions: {
                departureTime: new Date(),
                trafficModel:  google.maps.TrafficModel.BEST_GUESS,
            },
        }, (result, status) => {
            if (status === 'OK' && result.routes.length) {
                const leg  = result.routes[0].legs[0];
                // Pakai traffic duration jika ada
                const dur  = leg.duration_in_traffic ?? leg.duration;
                const eta  = Math.round(dur.value / 60);
                const dist = (leg.distance.value / 1000).toFixed(1);
                updateETADisplay(eta, dist);
            }
        });
    } else {
        // OSRM tidak support traffic — gunakan saja tanpa traffic
        try {
            const url  = `https://router.project-osrm.org/route/v1/driving/${oLng},${oLat};${dLng},${dLat}?overview=false`;
            const res  = await fetch(url, { signal: AbortSignal.timeout(8000) });
            const data = await res.json();
            if (data.code === 'Ok' && data.routes.length) {
                const eta  = Math.round(data.routes[0].duration / 60);
                const dist = (data.routes[0].distance / 1000).toFixed(1);
                updateETADisplay(eta, dist);
            }
        } catch(e) { console.warn('OSRM ETA:', e.message); }
    }
}

function updateETADisplay(eta, dist) {
    const valEl  = document.getElementById('eta-api-value');
    const distEl = document.getElementById('eta-api-dist');
    if (valEl)  valEl.textContent  = eta  !== null ? eta  : '—';
    if (distEl) distEl.textContent = dist !== null ? dist : '—';
}

// Panggil setelah map siap
if (MAP_TYPE === 'gmaps' && GMAPS_KEY) {
    // Google Maps sudah di-init, fetch setelah SDK ready
    const origReady = window.createGHistory;
    window.createGHistory = function() {
        origReady();
        fetchShowAPIeta();
    };
} else {
    // OSM — fetch langsung
    fetchShowAPIeta();
}

// ════════════════════════════════════════════════════════════════
// INIT
// ════════════════════════════════════════════════════════════════
if (MAP_TYPE === 'gmaps' && GMAPS_KEY) {
    initGMapsHistory();
} else if (GMAPS_KEY) {
    // Tetap load Google Maps untuk kalkulasi logic routing, baru render OSM
    const s   = document.createElement('script');
    s.id      = 'gmaps-sdk';
    s.src     = `https://maps.googleapis.com/maps/api/js?key=${GMAPS_KEY}&libraries=geometry&callback=googleMapsLoaded&loading=async`;
    s.async   = true;
    s.defer   = true;
    document.head.appendChild(s);
} else {
    initOSMHistory();
}