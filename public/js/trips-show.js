// ── Config dari window.__tripshow ────────────────────────────────
const MAP_TYPE  = window.__tripshow?.mapType  ?? 'osm';
const GMAPS_KEY = window.__tripshow?.gmapsKey ?? '';
const gpsPoints = window.__tripshow?.gpsPoints    ?? [];
const gpsPointsRaw = window.__tripshow?.gpsPointsRaw ?? [];
const trip      = window.__tripshow?.trip ?? null;
const stopEvents= window.__tripshow?.stopEvents ?? [];


// Helper sample points
function samplePoints(pts, max) {
    if (pts.length <= max) return pts;
    const r = [pts[0]], step = (pts.length - 2) / (max - 2);
    for (let i = 1; i < max - 1; i++) r.push(pts[Math.round(i * step)]);
    r.push(pts[pts.length - 1]);
    return r;
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

    // ── Rute biru (OSRM) ─────────────────────────────────────────
    if (trip.origin_lat && trip.dest_lat) {
        try {
            const url  = `https://router.project-osrm.org/route/v1/driving/${trip.origin_lng},${trip.origin_lat};${trip.dest_lng},${trip.dest_lat}?overview=full&geometries=geojson`;
            const res  = await fetch(url, { signal: AbortSignal.timeout(8000) });
            const data = await res.json();

            if (data.code === 'Ok' && data.routes.length) {
                const coords = data.routes[0].geometry.coordinates.map(c => [c[1], c[0]]);
                // Shadow biru
                L.polyline(coords, {
                    color:'#818cf8', weight:10, opacity:.2,
                    lineCap:'round', lineJoin:'round'
                }).addTo(map);
                // Garis biru utama
                L.polyline(coords, {
                    color:'#4f46e5', weight:5, opacity:.88,
                    lineCap:'round', lineJoin:'round'
                }).addTo(map);
            }
        } catch(e) {
            // Fallback garis lurus biru putus-putus
            L.polyline(
                [[+trip.origin_lat, +trip.origin_lng], [+trip.dest_lat, +trip.dest_lng]],
                { color:'#4f46e5', weight:4, opacity:.6, dashArray:'10,7' }
            ).addTo(map);
        }
    }

    // ── Track orange (GPS history) ────────────────────────────────
    if (hasGps) {
        const coords = gpsPoints.map(p => [+p.latitude, +p.longitude]);

        // Shadow orange
        L.polyline(coords, {
            color:'#fb923c', weight:10, opacity:.18,
            lineCap:'round', lineJoin:'round'
        }).addTo(map);
        // Garis orange utama
        L.polyline(coords, {
            color:'#f97316', weight:4.5, opacity:.9,
            lineCap:'round', lineJoin:'round'
        }).addTo(map);
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

            // Bubble chat permanen (seperti chat bubble)
            stopMarker.bindTooltip(
                `<div style="font-weight:700;color:#dc2626;">⏱️ Stop ${stop.duration_label}</div>
                 <div style="font-size:10px;color:#6b7280;">${stop.started_at} — ${stop.ended_at}</div>`,
                {
                    permanent: true,
                    direction: 'top',
                    offset: [0, -10],
                    className: 'stop-bubble-tooltip'
                }
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

    if (window.google && window.google.maps) { createGHistory(); return; }
    if (document.getElementById('gmaps-sdk')) return;

    const s   = document.createElement('script');
    s.id      = 'gmaps-sdk';
    s.src     = `https://maps.googleapis.com/maps/api/js?key=${GMAPS_KEY}&callback=createGHistory&loading=async`;
    s.async   = true;
    s.defer   = true;
    document.head.appendChild(s);
}

window.createGHistory = function () {
    const hasGps = gpsPoints && gpsPoints.length >= 2;
    const center = hasGps
        ? { lat: +gpsPoints[0].latitude, lng: +gpsPoints[0].longitude }
        : { lat: +trip.origin_lat,        lng: +trip.origin_lng };

    const gMap = new google.maps.Map(document.getElementById('history-gmap'), {
        center, zoom: 13, mapTypeId: 'roadmap',
        mapTypeControl: false, fullscreenControl: false, streetViewControl: false,
    });

    // ── Rute biru via Google Directions ──────────────────────────
    if (trip.origin_lat && trip.dest_lat) {
        const svc = new google.maps.DirectionsService();
        const rdr = new google.maps.DirectionsRenderer({
            map: gMap,
            suppressMarkers: true,
            preserveViewport: true,
            polylineOptions: {
                strokeColor:   '#4f46e5',
                strokeWeight:  5,
                strokeOpacity: .88,
                zIndex: 1,
            },
        });
        svc.route({
            origin:      { lat: +trip.origin_lat, lng: +trip.origin_lng },
            destination: { lat: +trip.dest_lat,   lng: +trip.dest_lng },
            travelMode:  google.maps.TravelMode.DRIVING,
        }, (result, status) => {
            if (status === 'OK') rdr.setDirections(result);
        });
    }

    // ── Track orange (GPS history) ────────────────────────────────
    if (hasGps) {
        const coords = gpsPoints.map(p => ({ lat: +p.latitude, lng: +p.longitude }));

        // Shadow orange
        new google.maps.Polyline({
            path: coords, map: gMap,
            strokeColor: '#fb923c', strokeOpacity: .2, strokeWeight: 10,
            zIndex: 2,
        });
        // Garis orange utama — zIndex lebih tinggi supaya selalu di atas garis biru,
        // termasuk setelah DirectionsRenderer selesai render secara async
        new google.maps.Polyline({
            path: coords, map: gMap,
            strokeColor: '#f97316', strokeOpacity: .9, strokeWeight: 4.5,
            zIndex: 3,
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

    // ── Custom permanent bubble overlay (tidak ada tombol close) ───
    class StopBubbleOverlay extends google.maps.OverlayView {
        constructor(position, html) {
            super();
            this.position = position;
            this.html     = html;
            this.div      = null;
        }
        onAdd() {
            this.div = document.createElement('div');
            this.div.style.position = 'absolute';
            this.div.style.transform = 'translate(-50%, -100%)';
            this.div.style.background = 'white';
            this.div.style.border = '1.5px solid #dc2626';
            this.div.style.borderRadius = '10px';
            this.div.style.padding = '6px 10px';
            this.div.style.boxShadow = '0 2px 8px rgba(220,38,38,.25)';
            this.div.style.fontSize = '11px';
            this.div.style.lineHeight = '1.4';
            this.div.style.whiteSpace = 'nowrap';
            this.div.style.pointerEvents = 'none'; // tidak menghalangi interaksi peta
            this.div.innerHTML = this.html;
            this.getPanes().floatPane.appendChild(this.div);
        }
        draw() {
            const proj = this.getProjection();
            if (!proj || !this.div) return;
            const point = proj.fromLatLngToDivPixel(this.position);
            if (point) {
                this.div.style.left = point.x + 'px';
                this.div.style.top  = (point.y - 14) + 'px';
            }
        }
        onRemove() {
            if (this.div) {
                this.div.parentNode.removeChild(this.div);
                this.div = null;
            }
        }
    }

    // ── Stop markers (titik berhenti lama) ────────────────────────
    if (stopEvents && stopEvents.length) {
        stopEvents.forEach(stop => {
            new google.maps.Marker({
                position: { lat: stop.lat, lng: stop.lng },
                map: gMap,
                icon: {
                    path: google.maps.SymbolPath.CIRCLE,
                    scale: 8, fillColor: '#dc2626', fillOpacity: 1,
                    strokeColor: 'white', strokeWeight: 3,
                },
                title: `Stop ${stop.duration_label} (${stop.started_at} - ${stop.ended_at})`,
                zIndex: 999,
            });

            // Bubble chat permanen — custom overlay, tidak ada tombol close
            const bubbleHtml = `
                <div style="font-weight:700;color:#dc2626;">⏱️ Stop ${stop.duration_label}</div>
                <div style="font-size:10px;color:#6b7280;margin-top:2px;">${stop.started_at} — ${stop.ended_at}</div>
            `;
            const overlay = new StopBubbleOverlay(
                new google.maps.LatLng(stop.lat, stop.lng),
                bubbleHtml
            );
            overlay.setMap(gMap);
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
} else {
    initOSMHistory();
}
</script>