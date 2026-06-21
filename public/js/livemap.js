// ── Config — diisi dari window.__livemap (set di blade) ──────────
const GMAPS_KEY    = window.__livemap?.gmapsKey    ?? '';
const MAP_TYPE     = window.__livemap?.mapType     ?? 'osm';
const allVehicles  = window.__livemap?.allVehicles ?? [];
const gpsPoints    = window.__livemap?.gpsPoints   ?? [];
const activeTrip   = window.__livemap?.activeTrip  ?? null;
const STATUS_COLOR = { moving:'#22c55e', idle:'#f97316', offline:'#ef4444', online:'#22c55e' };


// ════════════════════════════════════════════════════════════════
// UI HELPERS
// ════════════════════════════════════════════════════════════════
let isMinimized = false;
function minimizePanel() {
    const p = document.getElementById('detail-panel');
    if (!p || isMinimized) return;
    isMinimized = true;
    p.classList.add('minimized');
}
function expandPanel() {
    const p = document.getElementById('detail-panel');
    if (!p || !isMinimized) return;
    isMinimized = false;
    p.classList.remove('minimized');
}

function showToast(msg) {
    const el = document.getElementById('toast');
    const tx = document.getElementById('toast-msg');
    if (!el) return;
    tx.textContent = msg;
    el.style.opacity = '1';
    el.style.transform = 'translateX(-50%) translateY(0)';
    el.style.pointerEvents = 'auto';
    setTimeout(() => {
        el.style.opacity = '0';
        el.style.transform = 'translateX(-50%) translateY(8px)';
        el.style.pointerEvents = 'none';
    }, 3000);
}

// ════════════════════════════════════════════════════════════════
// SHARED HELPERS
// ════════════════════════════════════════════════════════════════
function samplePoints(pts, max) {
    if (pts.length <= max) return pts;
    const r = [pts[0]], step = (pts.length - 2) / (max - 2);
    for (let i = 1; i < max - 1; i++) r.push(pts[Math.round(i * step)]);
    r.push(pts[pts.length - 1]);
    return r;
}

function haversineJS(lat1, lng1, lat2, lng2) {
    const R    = 6371;
    const dLat = (lat2 - lat1) * Math.PI / 180;
    const dLng = (lng2 - lng1) * Math.PI / 180;
    const a    = Math.sin(dLat/2)**2
               + Math.cos(lat1*Math.PI/180) * Math.cos(lat2*Math.PI/180)
               * Math.sin(dLng/2)**2;
    return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
}

function calcRealtimeETA(curLat, curLng, destLat, destLng, currentSpeed) {
    const dist     = haversineJS(curLat, curLng, destLat, destLng);
    const rf       = dist < 3 ? 1.6 : (dist < 10 ? 1.4 : 1.25);
    const distRoad = dist * rf;
    const speed    = currentSpeed > 5
        ? currentSpeed
        : (distRoad < 5 ? 25 : (distRoad < 15 ? 35 : 50));
    const delay    = distRoad < 5 ? 5 : (distRoad < 15 ? 4 : 3);
    return Math.round((distRoad / speed) * 60 + delay);
}

// ════════════════════════════════════════════════════════════════
// OPENSTREETMAP
// ════════════════════════════════════════════════════════════════
let osmMap         = null;
let osmMarkers     = {};
let osmTrackLayers = [];
let osmRouteMain   = null;
let osmRouteShadow = null;

function initOSM() {
    document.getElementById('live-map').style.display  = 'block';
    document.getElementById('live-gmap').style.display = 'none';

    osmMap = L.map('live-map', { zoomControl:false }).setView([-7.965, 112.60], 13);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution:'© OpenStreetMap contributors', maxZoom:19
    }).addTo(osmMap);
    L.control.zoom({ position:'topleft' }).addTo(osmMap);

    const mkTruck = (status, isActive = false) => {
        const c = STATUS_COLOR[status] || '#6b7280';
        const s = isActive ? 48 : 40;
        return L.divIcon({
            html: `<div style="width:${s}px;height:${s}px;background:${c};border-radius:50%;border:3px solid white;box-shadow:0 0 0 ${isActive?`5px ${c}35,`:''} 0 3px 12px rgba(0,0,0,.25);display:flex;align-items:center;justify-content:center;font-size:${isActive?22:18}px;transition:all .4s;">🚛</div>`,
            iconSize:[s,s], iconAnchor:[s/2,s/2], className:''
        });
    };

    const mkWP = (color, size = 42) => L.divIcon({
        html: `<div style="width:${size}px;height:${size}px;background:${color};border-radius:50%;border:4px solid white;box-shadow:0 0 0 3px ${color}55,0 4px 14px rgba(0,0,0,.25);"></div>`,
        iconSize:[size,size], iconAnchor:[size/2,size/2], className:''
    });

    allVehicles.forEach(v => {
        if (!v.latitude || !v.longitude) return;
        const isActive = activeTrip && activeTrip.vehicle_id == v.vehicle_id;
        const displaySpeed = (v.vehicle_status === 'offline' || v.vehicle_status === 'idle') ? 0 : Math.round(v.speed_kmh || 0);

        const popupHtml = (speed, status) => {
            const spd = (status === 'offline') ? 0 : Math.round(speed || 0);
            return `<div style="font-family:'Plus Jakarta Sans',sans-serif;font-size:12px;min-width:160px;">
                <div style="font-weight:800;font-size:13px;">${v.vehicle_name}</div>
                <div style="color:#9ca3af;font-size:10px;margin-bottom:6px;">${v.license_plate}</div>
                <div style="font-size:11px;line-height:1.8;">👤 ${v.driver_name||'—'}<br>⚡ <span class="popup-speed-${v.vehicle_id}">${spd}</span> km/h</div>
                <a href="/live-map/${v.vehicle_id}" style="display:block;margin-top:8px;background:#22c55e;color:white;text-align:center;padding:6px;border-radius:8px;font-size:11px;font-weight:700;text-decoration:none;">Lihat Detail →</a>
            </div>`;
        };

        const popup = L.popup({ closeButton: true, autoClose: false })
            .setContent(popupHtml(v.speed_kmh, v.vehicle_status));

        const m = L.marker([+v.latitude, +v.longitude], { icon: mkTruck(v.vehicle_status, isActive) })
            .addTo(osmMap)
            .bindPopup(popup);
        m.on('click', () => { /* popup handles navigation */ });
        osmMarkers[v.vehicle_id] = { marker: m, mkIcon: mkTruck, popupFn: popupHtml };
    });

    if (activeTrip && activeTrip.origin_lat) {
        L.marker([+activeTrip.origin_lat, +activeTrip.origin_lng], { icon: mkWP('#22c55e', 42), zIndexOffset:500 })
         .addTo(osmMap)
         .bindTooltip(`<b style="font-size:12px;">🟢 ${activeTrip.origin_name}</b>`, { permanent:true, direction:'top', offset:[0,-26] });
        L.marker([+activeTrip.dest_lat, +activeTrip.dest_lng], { icon: mkWP('#ef4444', 42), zIndexOffset:500 })
         .addTo(osmMap)
         .bindTooltip(`<b style="font-size:12px;">🔴 ${activeTrip.dest_name}</b>`, { permanent:true, direction:'bottom', offset:[0,26] });
    }

    (async () => {
        if (activeTrip && activeTrip.origin_lat) {
            const rc = await drawOSMRoute(+activeTrip.origin_lat, +activeTrip.origin_lng, +activeTrip.dest_lat, +activeTrip.dest_lng);
            if (gpsPoints.length >= 2) drawOSMTrack(gpsPoints);
            if (rc && rc.length) osmMap.fitBounds(rc, { padding:[80, 420] });
        } else {
            const coords = allVehicles.filter(v => v.latitude && v.longitude).map(v => [+v.latitude, +v.longitude]);
            if (coords.length) osmMap.fitBounds(coords, { padding:[60, 340] });
        }
    })();

    if (window.__livemap && window.__livemap.hasTrip) {
    const leg = L.control({ position:'bottomleft' });
    leg.onAdd = () => {
        const d = L.DomUtil.create('div');
        d.style.cssText = 'background:white;padding:10px 14px;border-radius:12px;box-shadow:0 2px 12px rgba(0,0,0,.12);font-size:11px;font-family:Plus Jakarta Sans,sans-serif;line-height:2;';
        d.innerHTML = `
            <div style="font-weight:700;color:#374151;margin-bottom:4px;">Keterangan</div>
            <div style="display:flex;align-items:center;gap:6px;"><div style="width:28px;height:4px;background:#4f46e5;border-radius:2px;"></div><span style="color:#4f46e5;font-weight:600;">Rute Jalan</span></div>
            <div style="display:flex;align-items:center;gap:6px;"><div style="width:28px;height:4px;background:#f97316;border-radius:2px;"></div><span style="color:#f97316;font-weight:600;">Riwayat GPS</span></div>
            <div style="display:flex;align-items:center;gap:6px;"><div style="width:14px;height:14px;background:#22c55e;border-radius:50%;border:2px solid white;"></div><span>Titik Awal</span></div>
            <div style="display:flex;align-items:center;gap:6px;"><div style="width:14px;height:14px;background:#ef4444;border-radius:50%;border:2px solid white;"></div><span>Titik Tujuan</span></div>`;
        return d;
    };
    leg.addTo(osmMap);
    }
}

function drawOSMTrack(points) {
    if (!points || points.length < 2) return;
    osmTrackLayers.forEach(l => osmMap.removeLayer(l));
    osmTrackLayers = [];
    const coords = points.map(p => [+p.latitude, +p.longitude]);
    osmTrackLayers.push(
        L.polyline(coords, { color:'#fb923c', weight:10, opacity:.18, lineCap:'round', lineJoin:'round' }).addTo(osmMap),
        L.polyline(coords, { color:'#f97316', weight:4.5, opacity:.9,  lineCap:'round', lineJoin:'round' }).addTo(osmMap)
    );
}

async function drawOSMRoute(oLat, oLng, dLat, dLng) {
    try {
        const res  = await fetch(`https://router.project-osrm.org/route/v1/driving/${oLng},${oLat};${dLng},${dLat}?overview=full&geometries=geojson`, { signal: AbortSignal.timeout(8000) });
        const data = await res.json();
        if (data.code !== 'Ok' || !data.routes.length) throw new Error('no route');
        const coords = data.routes[0].geometry.coordinates.map(c => [c[1], c[0]]);
        if (osmRouteShadow) osmMap.removeLayer(osmRouteShadow);
        if (osmRouteMain)   osmMap.removeLayer(osmRouteMain);
        osmRouteShadow = L.polyline(coords, { color:'#818cf8', weight:10, opacity:.2,  lineCap:'round', lineJoin:'round' }).addTo(osmMap);
        osmRouteMain   = L.polyline(coords, { color:'#4f46e5', weight:5,  opacity:.88, lineCap:'round', lineJoin:'round' }).addTo(osmMap);
        return coords;
    } catch(e) {
        if (!activeTrip) return null;
        const coords = [[+activeTrip.origin_lat, +activeTrip.origin_lng], [+activeTrip.dest_lat, +activeTrip.dest_lng]];
        osmRouteMain = L.polyline(coords, { color:'#4f46e5', weight:4, opacity:.6, dashArray:'10,7' }).addTo(osmMap);
        return coords;
    }
}

// ════════════════════════════════════════════════════════════════
// GOOGLE MAPS
// ════════════════════════════════════════════════════════════════
let gMap               = null;
let gMapReady          = false;
let gVMarkers          = {};
let gTrackLines        = [];
let gDirectionsRenderers = [];
let gTraffic           = null;

function initGMaps() {
    document.getElementById('live-map').style.display  = 'none';
    document.getElementById('live-gmap').style.display = 'block';

    if (!GMAPS_KEY) return;
    if (window.google && window.google.maps) { onLiveGmapReady(); return; }
    if (document.getElementById('gmaps-sdk')) return;

    const s   = document.createElement('script');
    s.id      = 'gmaps-sdk';
    s.src     = `https://maps.googleapis.com/maps/api/js?key=${GMAPS_KEY}&libraries=geometry&callback=onLiveGmapReady&loading=async`;
    s.async   = true;
    s.defer   = true;
    document.head.appendChild(s);
}

const mkGIcon = (status, isActive = false) => {
    const c    = STATUS_COLOR[status] || '#6b7280';
    const size = isActive ? 60 : 52;
    const svg  = `<svg xmlns="http://www.w3.org/2000/svg" width="${size}" height="${size}" viewBox="0 0 ${size} ${size}">
        <circle cx="${size/2}" cy="${size/2}" r="${size/2}" fill="${c}" fill-opacity="0.22"/>
        <circle cx="${size/2}" cy="${size/2}" r="${size/2-5}" fill="${c}" stroke="white" stroke-width="3.5"/>
        <text x="${size/2}" y="${size/2+8}" text-anchor="middle" font-size="${isActive?24:20}" font-family="Apple Color Emoji,Segoe UI Emoji,Noto Color Emoji,sans-serif">🚛</text>
    </svg>`;
    return {
        url:        'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(svg),
        scaledSize: new google.maps.Size(size, size),
        anchor:     new google.maps.Point(size/2, size/2),
    };
};

window.onLiveGmapReady = function () {
    gMapReady = true;

    const center = activeTrip
        ? { lat: +activeTrip.origin_lat, lng: +activeTrip.origin_lng }
        : { lat: -7.965, lng: 112.60 };

    gMap = new google.maps.Map(document.getElementById('live-gmap'), {
        center, zoom:13, mapTypeId:'roadmap',
        mapTypeControl:false, fullscreenControl:false, streetViewControl:false,
        zoomControlOptions: { position: google.maps.ControlPosition.LEFT_TOP },
    });

    gTraffic = new google.maps.TrafficLayer();
    gTraffic.setMap(gMap);

    allVehicles.forEach(v => {
        if (!v.latitude || !v.longitude) return;
        const isActive = activeTrip && activeTrip.vehicle_id == v.vehicle_id;

        const infoHtml = (speed, status) => {
            const spd = (status === 'offline') ? 0 : Math.round(speed || 0);
            return `<div style="font-family:'Plus Jakarta Sans',sans-serif;font-size:12px;min-width:160px;padding:4px;">
                <div style="font-weight:800;font-size:13px;">${v.vehicle_name}</div>
                <div style="color:#9ca3af;font-size:10px;margin-bottom:6px;">${v.license_plate}</div>
                <div style="font-size:11px;line-height:1.8;">👤 ${v.driver_name||'—'}<br>⚡ ${spd} km/h</div>
                <a href="/live-map/${v.vehicle_id}" style="display:block;margin-top:8px;background:#22c55e;color:white;text-align:center;padding:6px;border-radius:8px;font-size:11px;font-weight:700;text-decoration:none;">Lihat Detail →</a>
            </div>`;
        };

        const marker = new google.maps.Marker({
            position:  { lat: +v.latitude, lng: +v.longitude },
            map:       gMap,
            icon:      mkGIcon(v.vehicle_status, isActive),
            title:     v.vehicle_name,
            optimized: false,
            zIndex:    isActive ? 999 : 10,
        });
        const info = new google.maps.InfoWindow({
            content: infoHtml(v.speed_kmh, v.vehicle_status)
        });
        marker.addListener('click', () => info.open(gMap, marker));
        gVMarkers[v.vehicle_id] = { marker, isActive, info, infoHtml };
    });

    if (activeTrip && activeTrip.origin_lat) {
        new google.maps.Marker({
            position: { lat: +activeTrip.origin_lat, lng: +activeTrip.origin_lng },
            map: gMap,
            icon: { path: google.maps.SymbolPath.CIRCLE, scale:14, fillColor:'#22c55e', fillOpacity:1, strokeColor:'white', strokeWeight:4 },
            title: activeTrip.origin_name, zIndex:998,
        });
        new google.maps.Marker({
            position: { lat: +activeTrip.dest_lat, lng: +activeTrip.dest_lng },
            map: gMap,
            icon: { path: google.maps.SymbolPath.CIRCLE, scale:14, fillColor:'#ef4444', fillOpacity:1, strokeColor:'white', strokeWeight:4 },
            title: activeTrip.dest_name, zIndex:998,
        });
    }

    if (activeTrip) {
        drawGoogleRoute();
        if (gpsPoints.length >= 2) drawGoogleTrack(gpsPoints);
        if (activeTrip.origin_lat) {
            const bounds = new google.maps.LatLngBounds();
            bounds.extend({ lat: +activeTrip.origin_lat, lng: +activeTrip.origin_lng });
            bounds.extend({ lat: +activeTrip.dest_lat,   lng: +activeTrip.dest_lng });
            gpsPoints.forEach(p => bounds.extend({ lat: +p.latitude, lng: +p.longitude }));
            gMap.fitBounds(bounds, 80);
        }
    } else {
        const vp = allVehicles.filter(v => v.latitude && v.longitude);
        if (vp.length) {
            const b = new google.maps.LatLngBounds();
            vp.forEach(v => b.extend({ lat: +v.latitude, lng: +v.longitude }));
            gMap.fitBounds(b, 60);
        }
    }
};

function drawGoogleRoute() {
    if (!gMap || !activeTrip) return;
    const svc = new google.maps.DirectionsService();
    const rdr = new google.maps.DirectionsRenderer({
        map: gMap, suppressMarkers: true,
        polylineOptions: { strokeColor:'#4f46e5', strokeWeight:5, strokeOpacity:.88 },
    });
    svc.route({
        origin:      { lat: +activeTrip.origin_lat, lng: +activeTrip.origin_lng },
        destination: { lat: +activeTrip.dest_lat,   lng: +activeTrip.dest_lng },
        travelMode:  google.maps.TravelMode.DRIVING,
    }, (result, status) => { if (status === 'OK') rdr.setDirections(result); });
}

async function drawGoogleTrack(points) {
    if (!gMap || !window.google || !points || points.length < 2) return;
    gTrackLines.forEach(p => p.setMap(null));
    gTrackLines = [];
    gDirectionsRenderers.forEach(r => r.setMap(null));
    gDirectionsRenderers = [];

    const CHUNK_SIZE = 100;
    const allSnapped = [];
    try {
        const chunks = [];
        for (let i = 0; i < points.length; i += CHUNK_SIZE - 1) {
            chunks.push(points.slice(i, i + CHUNK_SIZE));
        }
        for (const chunk of chunks) {
            const pathParam = chunk.map(p => `${+p.latitude},${+p.longitude}`).join('|');
            const url       = `https://roads.googleapis.com/v1/snapToRoads?path=${encodeURIComponent(pathParam)}&interpolate=true&key=${GMAPS_KEY}`;
            const res       = await fetch(url);
            const data      = await res.json();
            if (data.error) throw new Error(data.error.message);
            if (data.snappedPoints?.length > 0) {
                const startIdx = allSnapped.length > 0 ? 1 : 0;
                data.snappedPoints.slice(startIdx).forEach(sp => {
                    allSnapped.push({ lat: sp.location.latitude, lng: sp.location.longitude });
                });
            }
        }
        if (allSnapped.length < 2) throw new Error('No snapped points');
        gTrackLines.push(
            new google.maps.Polyline({ path:allSnapped, map:gMap, strokeColor:'#fb923c', strokeOpacity:.2,  strokeWeight:10, zIndex:1 }),
            new google.maps.Polyline({ path:allSnapped, map:gMap, strokeColor:'#f97316', strokeOpacity:.9, strokeWeight:4.5, zIndex:2 })
        );
    } catch(e) {
        console.warn('Roads API gagal, fallback:', e.message);
        drawGoogleTrackViaDirections(points);
    }
}

function drawGoogleTrackViaDirections(points) {
    if (!gMap || !window.google || !points || points.length < 2) return;
    const sampled     = samplePoints(points, 25);
    const origin      = { lat: +sampled[0].latitude,                lng: +sampled[0].longitude };
    const destination = { lat: +sampled[sampled.length-1].latitude, lng: +sampled[sampled.length-1].longitude };
    const waypoints   = sampled.slice(1, -1).map(p => ({
        location: new google.maps.LatLng(+p.latitude, +p.longitude),
        stopover: true,
    }));
    const svc = new google.maps.DirectionsService();
    svc.route({
        origin, destination, waypoints,
        travelMode: google.maps.TravelMode.DRIVING,
        optimizeWaypoints: false,
    }, (result, status) => {
        if (status === 'OK') {
            const path = [];
            result.routes[0].legs.forEach(leg => {
                leg.steps.forEach(step => {
                    google.maps.geometry.encoding.decodePath(step.polyline.points).forEach(p => path.push(p));
                });
            });
            gTrackLines.push(
                new google.maps.Polyline({ path, map:gMap, strokeColor:'#fb923c', strokeOpacity:.2,  strokeWeight:10, zIndex:1 }),
                new google.maps.Polyline({ path, map:gMap, strokeColor:'#f97316', strokeOpacity:.9, strokeWeight:4.5, zIndex:2 })
            );
        } else {
            const coords = points.map(p => ({ lat:+p.latitude, lng:+p.longitude }));
            gTrackLines.push(
                new google.maps.Polyline({ path:coords, map:gMap, strokeColor:'#fb923c', strokeOpacity:.2, strokeWeight:10, zIndex:1 }),
                new google.maps.Polyline({ path:coords, map:gMap, strokeColor:'#f97316', strokeOpacity:.9, strokeWeight:4.5, zIndex:2 })
            );
        }
    });
}

// ════════════════════════════════════════════════════════════════
// ETA — API OSRM / Google
// ════════════════════════════════════════════════════════════════
async function fetchAPIeta(lat1, lng1, lat2, lng2) {
    if (MAP_TYPE === 'gmaps' && window.google) return fetchGoogleETA(lat1, lng1, lat2, lng2);
    return fetchOSRMeta(lat1, lng1, lat2, lng2);
}

async function fetchOSRMeta(lat1, lng1, lat2, lng2) {
    try {
        const url  = `https://router.project-osrm.org/route/v1/driving/${lng1},${lat1};${lng2},${lat2}?overview=false`;
        const res  = await fetch(url, { signal: AbortSignal.timeout(5000) });
        const data = await res.json();
        if (data.code !== 'Ok' || !data.routes.length) return null;
        return Math.round(data.routes[0].duration / 60);
    } catch(e) { return null; }
}

function fetchGoogleETA(lat1, lng1, lat2, lng2) {
    return new Promise(resolve => {
        if (!window.google?.maps) { resolve(null); return; }

        new google.maps.DirectionsService().route({
            origin:        { lat: lat1, lng: lng1 },
            destination:   { lat: lat2, lng: lng2 },
            travelMode:    google.maps.TravelMode.DRIVING,
            // ── Traffic real-time ──────────────────────────────
            drivingOptions: {
                departureTime: new Date(), // waktu sekarang → aktifkan traffic
                trafficModel:  google.maps.TrafficModel.BEST_GUESS,
                // BEST_GUESS   → estimasi terbaik (default Google Maps)
                // PESSIMISTIC  → kondisi terburuk (macet lebih)
                // OPTIMISTIC   → kondisi terbaik (lancar)
            },
        }, (result, status) => {
            if (status !== 'OK' || !result.routes.length) {
                resolve(null);
                return;
            }

            const leg = result.routes[0].legs[0];

            // Pakai duration_in_traffic jika tersedia (ada traffic data)
            // fallback ke duration biasa jika tidak ada
            const durationSeconds = leg.duration_in_traffic
                ? leg.duration_in_traffic.value
                : leg.duration.value;

            resolve(Math.round(durationSeconds / 60));
        });
    });
}

// ── ETA awal (origin → dest) — dipanggil SEKALI ──────────────────
let etaInitFetched = false;

async function fetchInitialAPIeta() {
    if (!activeTrip || etaInitFetched) return;
    etaInitFetched = true;

    // Haversine awal (origin → dest, untuk referensi)
    if (activeTrip.origin_lat && activeTrip.dest_lat) {
        const dist     = haversineJS(+activeTrip.origin_lat, +activeTrip.origin_lng, +activeTrip.dest_lat, +activeTrip.dest_lng);
        const rf       = dist < 3 ? 1.6 : (dist < 10 ? 1.4 : 1.25);
        const distRoad = dist * rf;
        const speed    = distRoad < 5 ? 25 : (distRoad < 15 ? 35 : 50);
        const delay    = distRoad < 5 ? 5  : (distRoad < 15 ? 4  : 3);
        const etaHav   = Math.round((distRoad / speed) * 60 + delay);
        const havEl    = document.getElementById('eta-init-haversine');
        if (havEl) havEl.textContent = etaHav;
    }

    // API awal
    const etaAPI = await fetchAPIeta(+activeTrip.origin_lat, +activeTrip.origin_lng, +activeTrip.dest_lat, +activeTrip.dest_lng);
    const apiEl  = document.getElementById('eta-init-api');
    if (apiEl) apiEl.textContent = etaAPI !== null ? etaAPI : '—';
}

// ── ETA real-time dari API — dipanggil tiap 30 detik ─────────────
async function pollAPIeta() {
    if (!activeTrip) return;
    try {
        const data = await fetch(`/api/internal/trip/${activeTrip.vehicle_id}`)
                           .then(r => r.json());

        if (!data?.trip?.current_lat) return;

        const lat     = +data.trip.current_lat;
        const lng     = +data.trip.current_lng;
        const destLat = +activeTrip.dest_lat;
        const destLng = +activeTrip.dest_lng;

        // Hitung ETA haversine real-time dari posisi saat ini
        const etaHavRT = calcRealtimeETA(lat, lng, destLat, destLng, data.trip.current_speed_kmh ?? 0);
        const rtHavEl  = document.getElementById('eta-rt-haversine');
        if (rtHavEl) rtHavEl.textContent = etaHavRT;

        // Update live-speed dan live-dist juga dari polling (fallback jika WS tidak konek)
        const spEl = document.getElementById('live-speed');
        if (spEl) spEl.textContent = Math.round(data.trip.current_speed_kmh ?? 0);

        const distKm  = haversineJS(lat, lng, destLat, destLng);
        const distM   = Math.round(distKm * 1000);
        const distEl  = document.getElementById('live-dist');
        if (distEl) {
            distEl.textContent = distM >= 1000 ? distKm.toFixed(1) : distM;
            distEl.nextElementSibling.textContent = distM >= 1000 ? ' km' : ' m';
        }

        // Gunakan realtime_google dari server jika tersedia
        if (data.eta?.realtime_google !== null && data.eta?.realtime_google !== undefined) {
            const el = document.getElementById('eta-rt-api');
            if (el) el.textContent = data.eta.realtime_google;
            return;
        }

        // Fallback: fetch ETA API dari client
        const etaAPI = await fetchAPIeta(lat, lng, destLat, destLng);
        const el     = document.getElementById('eta-rt-api');
        if (el) el.textContent = etaAPI !== null ? etaAPI : '—';

    } catch(e) { console.warn('pollAPIeta:', e.message); }
}

// ════════════════════════════════════════════════════════════════
// GPS TRACK UPDATE dari server
// ════════════════════════════════════════════════════════════════
let lastTrackLen = gpsPoints.length; // satu deklarasi saja

async function updateTrackFromServer() {
    if (!activeTrip) return;
    try {
        const data = await (await fetch(`/api/internal/trip/${activeTrip.vehicle_id}`)).json();
        if (data.gps_track && data.gps_track.length > lastTrackLen) {
            lastTrackLen = data.gps_track.length;
            if (MAP_TYPE === 'osm')                drawOSMTrack(data.gps_track);
            if (MAP_TYPE === 'gmaps' && gMapReady) drawGoogleTrack(data.gps_track);
        }
    } catch(e) {}
}

// ════════════════════════════════════════════════════════════════
// WEBSOCKET HANDLERS
// (dipanggil dari global Echo listener di layouts/app.blade.php)
// ════════════════════════════════════════════════════════════════
window.updateLivemapMarker = function(data) {
    const lat = +data.latitude, lng = +data.longitude;
    const vid = String(data.vehicle_id);

    if (osmMarkers[vid]) {
        osmMarkers[vid].marker.setLatLng([lat, lng]);
        const mkFn = osmMarkers[vid].mkIcon;
        if (mkFn) osmMarkers[vid].marker.setIcon(
            mkFn(data.vehicle_status, activeTrip && String(activeTrip.vehicle_id) === vid)
        );
        // Update popup speed real-time (jika popup sedang terbuka)
        const popupFn = osmMarkers[vid].popupFn;
        if (popupFn) {
            const popup = osmMarkers[vid].marker.getPopup();
            if (popup && popup.isOpen()) {
                popup.setContent(popupFn(data.speed_kmh, data.vehicle_status));
            }
        }
    }
    if (gVMarkers[vid] && gMapReady) {
        gVMarkers[vid].marker.setPosition({ lat, lng });
        gVMarkers[vid].marker.setIcon(
            mkGIcon(data.vehicle_status, activeTrip && String(activeTrip.vehicle_id) === vid)
        );
        // Update InfoWindow speed real-time (jika sedang terbuka)
        const infoHtmlFn = gVMarkers[vid].infoHtml;
        if (infoHtmlFn && gVMarkers[vid].info) {
            gVMarkers[vid].info.setContent(infoHtmlFn(data.speed_kmh, data.vehicle_status));
        }
    }
};

window.updateLivemapPanel = function(data) {
    if (!activeTrip || String(activeTrip.vehicle_id) !== String(data.vehicle_id)) return;

    // Speed
    const spEl = document.getElementById('live-speed');
    if (spEl) spEl.textContent = Math.round(data.speed_kmh || 0);

    // Sisa jarak
    if (data.latitude && data.longitude) {
        const lat     = +data.latitude, lng = +data.longitude;
        const destLat = +activeTrip.dest_lat, destLng = +activeTrip.dest_lng;
        const distKm  = haversineJS(lat, lng, destLat, destLng);
        const distM   = Math.round(distKm * 1000);
        const distEl  = document.getElementById('live-dist');
        if (distEl) {
            distEl.textContent = distM >= 1000 ? distKm.toFixed(1) : distM;
            distEl.nextElementSibling.textContent = distM >= 1000 ? ' km' : ' m';
        }

        // ETA real-time haversine
        const etaRTel = document.getElementById('eta-rt-haversine');
        if (etaRTel) etaRTel.textContent = calcRealtimeETA(lat, lng, destLat, destLng, data.speed_kmh || 0);
    }

    // Update GPS track
    updateTrackFromServer();
};

window.handleTripUpdate = function(data) {
    if (!activeTrip || String(activeTrip.vehicle_id) !== String(data.vehicle_id)) return;
    if (data.status === 'completed') {
        if (apiEtaTimer) clearInterval(apiEtaTimer);
        if (fallbackTimer) clearInterval(fallbackTimer);
        setTimeout(() => { window.location.href = `/trips/${data.trip_id}`; }, 2000);
    }
};

// ════════════════════════════════════════════════════════════════
// TIMERS — satu tempat, tidak duplikat
// ════════════════════════════════════════════════════════════════
let apiEtaTimer   = null;
let fallbackTimer = null;

// ════════════════════════════════════════════════════════════════
// INIT — dipanggil SEKALI di paling bawah
// ════════════════════════════════════════════════════════════════
if (MAP_TYPE === 'gmaps') {
    initGMaps();
} else {
    initOSM();
}

if (activeTrip) {
    // ETA awal — sekali
    fetchInitialAPIeta();

    // ETA real-time API — tiap 30 detik
    pollAPIeta();
    apiEtaTimer = setInterval(pollAPIeta, 30000);

    // Fallback polling tiap 10 detik — HANYA jika WebSocket tidak konek
    let wsConnected = false;
    if (typeof window.Echo !== 'undefined') {
        window.Echo.connector.pusher.connection.bind('connected',    () => { wsConnected = true; });
        window.Echo.connector.pusher.connection.bind('disconnected', () => { wsConnected = false; });
    }
    fallbackTimer = setInterval(() => {
        if (!wsConnected) updateTrackFromServer();
    }, 10000);
}

window.addEventListener('beforeunload', () => {
    if (apiEtaTimer)   clearInterval(apiEtaTimer);
    if (fallbackTimer) clearInterval(fallbackTimer);
});