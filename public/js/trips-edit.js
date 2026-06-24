// ── Config dari window.__tripedit ───────────────────────────────
const EDIT_MAP_TYPE  = window.__tripedit?.mapType  ?? 'osm';
const EDIT_GMAPS_KEY = window.__tripedit?.gmapsKey ?? '';
const editState      = window.__tripedit?.state    ?? { origin:{lat:0,lng:0}, dest:{lat:0,lng:0} };



// ── Reverse geocode via Nominatim ────────────────────────────────
async function editReverseGeocode(lat, lng) {
    try {
        const r = await fetch(`https://nominatim.openstreetmap.org/reverse?lat=${lat}&lon=${lng}&format=json`, { headers: {'Accept-Language':'id'} });
        const d = await r.json();
        return d.display_name || '';
    } catch { return ''; }
}

// ── Update state + hidden inputs + badge + address display ───────
async function editSetCoord(type, lat, lng, addressOverride = null) {
    editState[type].lat = lat;
    editState[type].lng = lng;

    document.getElementById(`edit-${type}-lat`).value = lat;
    document.getElementById(`edit-${type}-lng`).value = lng;

    const badge = document.getElementById(`edit-${type}-badge`);
    badge.textContent = `${lat.toFixed(5)}, ${lng.toFixed(5)}`;
    badge.classList.add('set');

    const address = addressOverride !== null ? addressOverride : await editReverseGeocode(lat, lng);
    document.getElementById(`edit-${type}-address`).value = address;
    const addrEl = document.getElementById(`edit-${type}-addr-display`);
    addrEl.textContent = address || '—';

    editUpdateMarker(type, lat, lng);
}

// ── Live search dengan debounce 400ms ────────────────────────────
let _editTimers = {};

function editLiveSearch(type) {
    clearTimeout(_editTimers[type]);
    const q = document.getElementById(`edit-${type}-search`).value.trim();
    const resEl = document.getElementById(`edit-${type}-results`);
    if (q.length < 2) { resEl.classList.add('hidden'); return; }

    _editTimers[type] = setTimeout(() => {
        if (EDIT_MAP_TYPE === 'gmaps' && window.google) {
            editGoogleLiveSearch(type, q, resEl);
        } else {
            editNominatimLiveSearch(type, q, resEl);
        }
    }, 400);
}

async function editNominatimLiveSearch(type, q, resEl) {
    resEl.innerHTML = '<div class="search-result-item" style="color:#9ca3af;">🔍 Mencari...</div>';
    resEl.classList.remove('hidden');
    try {
        const r = await fetch(
            `https://nominatim.openstreetmap.org/search?q=${encodeURIComponent(q)}&format=json&limit=6&countrycodes=id`,
            { headers: { 'Accept-Language': 'id' } }
        );
        const data = await r.json();
        resEl.innerHTML = '';
        if (!data.length) { resEl.innerHTML = '<div class="search-result-item" style="color:#9ca3af;">Tidak ditemukan.</div>'; return; }
        data.forEach(place => {
            const div = document.createElement('div');
            div.className = 'search-result-item';
            const parts = place.display_name.split(',');
            const shortName = parts.slice(0, 2).join(',').trim();
            div.innerHTML = `<div style="font-weight:600;color:#111827;">${shortName}</div>
                             <div style="color:#9ca3af;font-size:10px;">${place.display_name}</div>`;
            div.onclick = async () => {
                const lat = parseFloat(place.lat), lng = parseFloat(place.lon);
                await editSetCoord(type, lat, lng, place.display_name);
                const nameEl = document.getElementById(`edit-${type}-name`);
                if (nameEl && !nameEl.value.trim()) nameEl.value = shortName;
                document.getElementById(`edit-${type}-search`).value = shortName;
                resEl.classList.add('hidden');
            };
            resEl.appendChild(div);
        });
    } catch { resEl.innerHTML = '<div class="search-result-item" style="color:#ef4444;">Gagal mencari.</div>'; }
}

function editGoogleLiveSearch(type, q, resEl) {
    resEl.innerHTML = '<div class="search-result-item" style="color:#9ca3af;">🔍 Mencari...</div>';
    resEl.classList.remove('hidden');
    const svc = new google.maps.places.PlacesServicesService(document.createElement('div'));
    svc.textSearch({ query: q, region: 'id' }, async (results, status) => {
        resEl.innerHTML = '';
        if (status !== google.maps.places.PlacesServicesServiceStatus.OK || !results.length) {
            resEl.innerHTML = '<div class="search-result-item" style="color:#9ca3af;">Tidak ditemukan.</div>'; return;
        }
        results.slice(0, 6).forEach(place => {
            const div = document.createElement('div');
            div.className = 'search-result-item';
            const addr = place.formatted_address || place.vicinity || '';
            div.innerHTML = `<div style="font-weight:600;color:#111827;">${place.name}</div>
                             <div style="color:#9ca3af;font-size:10px;">${addr}</div>`;
            div.onclick = async () => {
                const lat = place.geometry.location.lat(), lng = place.geometry.location.lng();
                await editSetCoord(type, lat, lng, `${place.name}, ${addr}`);
                const nameEl = document.getElementById(`edit-${type}-name`);
                if (nameEl && !nameEl.value.trim()) nameEl.value = place.name;
                document.getElementById(`edit-${type}-search`).value = place.name;
                resEl.classList.add('hidden');
            };
            resEl.appendChild(div);
        });
    });
}

// Attach live search ke input
document.getElementById('edit-origin-search').addEventListener('input', () => editLiveSearch('origin'));
document.getElementById('edit-dest-search').addEventListener('input',   () => editLiveSearch('dest'));

// Klik luar → tutup dropdown
document.addEventListener('click', e => {
    ['origin','dest'].forEach(type => {
        const inp = document.getElementById(`edit-${type}-search`);
        const res = document.getElementById(`edit-${type}-results`);
        if (inp && res && !inp.contains(e.target) && !res.contains(e.target)) res.classList.add('hidden');
    });
});

// ════════════════════════════════════════════════════════════════
// MAP — OSM
// ════════════════════════════════════════════════════════════════
let _oMap, _dMap, _oMrk, _dMrk;

function initEditOSMMaps() {
    const mkIcon = color => L.divIcon({
        html: `<div style="width:18px;height:18px;background:${color};border-radius:50%;border:3px solid white;box-shadow:0 0 0 3px ${color}55;"></div>`,
        iconSize:[18,18], iconAnchor:[9,9], className:''
    });

    _oMap = L.map('edit-origin-map').setView([editState.origin.lat, editState.origin.lng], 15);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom:19 }).addTo(_oMap);
    _oMrk = L.marker([editState.origin.lat, editState.origin.lng], { icon:mkIcon('#22c55e'), draggable:true }).addTo(_oMap);
    _oMrk.on('dragend', e => { const {lat,lng}=e.target.getLatLng(); editSetCoord('origin',lat,lng); });
    _oMap.on('click', e => { _oMrk.setLatLng([e.latlng.lat,e.latlng.lng]); editSetCoord('origin',e.latlng.lat,e.latlng.lng); });

    _dMap = L.map('edit-dest-map').setView([editState.dest.lat, editState.dest.lng], 15);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom:19 }).addTo(_dMap);
    _dMrk = L.marker([editState.dest.lat, editState.dest.lng], { icon:mkIcon('#ef4444'), draggable:true }).addTo(_dMap);
    _dMrk.on('dragend', e => { const {lat,lng}=e.target.getLatLng(); editSetCoord('dest',lat,lng); });
    _dMap.on('click', e => { _dMrk.setLatLng([e.latlng.lat,e.latlng.lng]); editSetCoord('dest',e.latlng.lat,e.latlng.lng); });
}

function editUpdateMarker(type, lat, lng) {
    if (EDIT_MAP_TYPE === 'gmaps' && window.google) {
        if (type==='origin' && _oMrk) { _oMrk.setPosition({lat,lng}); _oMap.setCenter({lat,lng}); }
        else if (_dMrk)               { _dMrk.setPosition({lat,lng}); _dMap.setCenter({lat,lng}); }
    } else {
        if (type==='origin' && _oMrk) { _oMrk.setLatLng([lat,lng]); _oMap.setView([lat,lng]); }
        else if (_dMrk)               { _dMrk.setLatLng([lat,lng]); _dMap.setView([lat,lng]); }
    }
}

// ════════════════════════════════════════════════════════════════
// MAP — Google Maps
// ════════════════════════════════════════════════════════════════
function initEditGMaps() {
    if (!EDIT_GMAPS_KEY) { initEditOSMMaps(); return; }
    if (window.google && window.google.maps) { createEditGMaps(); return; }
    if (document.getElementById('gmaps-sdk')) return;
    const s = document.createElement('script');
    s.id = 'gmaps-sdk';
    s.src = `https://maps.googleapis.com/maps/api/js?key=${EDIT_GMAPS_KEY}&libraries=places&callback=onEditGmapReady&loading=async`;
    s.async = true; s.defer = true;
    s.onerror = () => initEditOSMMaps();
    document.head.appendChild(s);
}

window.onEditGmapReady = function() { createEditGMaps(); };

function createEditGMaps() {
    const opts = { mapTypeId:'roadmap', mapTypeControl:false, fullscreenControl:false, streetViewControl:false };
    const mkIcon = color => ({ path:google.maps.SymbolPath.CIRCLE, scale:11, fillColor:color, fillOpacity:1, strokeColor:'white', strokeWeight:3 });

    _oMap = new google.maps.Map(document.getElementById('edit-origin-map'), { ...opts, center:{lat:editState.origin.lat,lng:editState.origin.lng}, zoom:15 });
    _oMrk = new google.maps.Marker({ position:{lat:editState.origin.lat,lng:editState.origin.lng}, map:_oMap, icon:mkIcon('#22c55e'), draggable:true, zIndex:10 });
    _oMrk.addListener('dragend', e => editSetCoord('origin', e.latLng.lat(), e.latLng.lng()));
    _oMap.addListener('click', e => { const l=e.latLng; _oMrk.setPosition(l); editSetCoord('origin',l.lat(),l.lng()); });

    _dMap = new google.maps.Map(document.getElementById('edit-dest-map'), { ...opts, center:{lat:editState.dest.lat,lng:editState.dest.lng}, zoom:15 });
    _dMrk = new google.maps.Marker({ position:{lat:editState.dest.lat,lng:editState.dest.lng}, map:_dMap, icon:mkIcon('#ef4444'), draggable:true, zIndex:10 });
    _dMrk.addListener('dragend', e => editSetCoord('dest', e.latLng.lat(), e.latLng.lng()));
    _dMap.addListener('click', e => { const l=e.latLng; _dMrk.setPosition(l); editSetCoord('dest',l.lat(),l.lng()); });
}

// ── INIT ─────────────────────────────────────────────────────────
if (EDIT_MAP_TYPE === 'gmaps' && EDIT_GMAPS_KEY) initEditGMaps();
else initEditOSMMaps();