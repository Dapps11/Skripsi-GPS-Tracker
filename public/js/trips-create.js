// ── Config dari window.__tripcreate ─────────────────────────────
const GMAPS_KEY = window.__tripcreate?.gmapsKey ?? '';
const MAP_TYPE  = window.__tripcreate?.mapType  ?? 'osm';


// ════════════════════════════════════════════════════════════════
// STATE
// ════════════════════════════════════════════════════════════════
const state = {
    origin: { lat: null, lng: null, address: '' },
    dest:   { lat: null, lng: null, address: '' },
};

// ════════════════════════════════════════════════════════════════
// HAVERSINE & ETA
// ════════════════════════════════════════════════════════════════
function haversine(lat1, lng1, lat2, lng2) {
    const R = 6371; // km
    const dLat = (lat2 - lat1) * Math.PI / 180;
    const dLng = (lng2 - lng1) * Math.PI / 180;

    const a = Math.sin(dLat / 2) ** 2 +
        Math.cos(lat1 * Math.PI / 180) *
        Math.cos(lat2 * Math.PI / 180) *
        Math.sin(dLng / 2) ** 2;

    return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
}

function calcETA() {
    // validasi lebih aman
    if (!state.origin?.lat || !state.dest?.lat) return;

    const lat1 = state.origin.lat;
    const lng1 = state.origin.lng;
    const lat2 = state.dest.lat;
    const lng2 = state.dest.lng;

    // 1. jarak garis lurus
    const distStraight = haversine(lat1, lng1, lat2, lng2);

    // 2. road factor dinamis
    let roadFactor = 1.3;
    if (distStraight < 3) roadFactor = 1.6;        // area padat
    else if (distStraight < 10) roadFactor = 1.4;  // kota
    else roadFactor = 1.25;                        // antar kota

    const distRoad = distStraight * roadFactor;

    // 3. kecepatan adaptif (km/jam)
    let speed = 40;
    if (distRoad < 5) speed = 25;        // padat
    else if (distRoad < 15) speed = 35;  // sedang
    else speed = 50;                     // lancar

    // 4. delay tambahan (lampu merah, macet ringan)
    let delay = 3; // default
    if (distRoad < 5) delay = 5;
    else if (distRoad < 15) delay = 4;

    // 5. hitung durasi (menit)
    const durationM = Math.round((distRoad / speed) * 60 + delay);

    // 6. tampilkan
    document.getElementById('eta-preview').classList.remove('hidden');
    document.getElementById('eta-distance').textContent = `${distRoad.toFixed(1)} km`;
    document.getElementById('eta-duration').textContent = `${durationM} menit`;

    // 7. hitung ETA
    const eta = new Date(Date.now() + durationM * 60 * 1000);
    document.getElementById('h-eta').value = eta.toISOString().slice(0, 16);
}

// ════════════════════════════════════════════════════════════════
// REVERSE GEOCODE (Nominatim — untuk klik peta di semua mode)
// ════════════════════════════════════════════════════════════════
async function reverseGeocode(lat, lng) {
    try {
        const res  = await fetch(
            `https://nominatim.openstreetmap.org/reverse?lat=${lat}&lon=${lng}&format=json`,
            { headers: { 'Accept-Language': 'id' } }
        );
        const data = await res.json();
        return data.display_name || '';
    } catch(e) { return ''; }
}

// ════════════════════════════════════════════════════════════════
// SET KOORDINAT — update semua field
// ════════════════════════════════════════════════════════════════
async function setCoord(type, lat, lng, addressOverride = null) {
    state[type].lat = lat;
    state[type].lng = lng;

    const address = addressOverride !== null ? addressOverride : await reverseGeocode(lat, lng);
    state[type].address = address;

    document.getElementById(`h-${type}-lat`).value     = lat;
    document.getElementById(`h-${type}-lng`).value     = lng;
    document.getElementById(`h-${type}-address`).value = address;

    // Update badge
    const badge = document.getElementById(`${type}-coord-badge`);
    badge.textContent = `${lat.toFixed(5)}, ${lng.toFixed(5)}`;
    badge.classList.add('set');

    // Update alamat display
    const addrEl = document.getElementById(`${type}-address-display`);
    addrEl.innerHTML = address
        ? `<span style="color:#374151;">${address}</span>`
        : `<span style="color:#9ca3af;font-style:italic;">Alamat tidak tersedia</span>`;

    calcETA();
    updateMarker(type, lat, lng);
}

// ════════════════════════════════════════════════════════════════
// MAP INSTANCES
// ════════════════════════════════════════════════════════════════
let osmOriginMap = null, osmDestMap   = null;
let osmOriginMrk = null, osmDestMrk  = null;
let gOriginMap   = null, gDestMap    = null;
let gOriginMrk   = null, gDestMrk   = null;
let gmapsReady   = false; // flag SDK sudah load

function updateMarker(type, lat, lng) {
    if (MAP_TYPE === 'gmaps' && gmapsReady) updateGMarker(type, lat, lng);
    else                                    updateOSMMarker(type, lat, lng);
}

// ── OSM Maps ─────────────────────────────────────────────────────
function initOSMMaps() {
    osmOriginMap = L.map('origin-map', { zoomControl: true }).setView([-7.965, 112.60], 12);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom:19 }).addTo(osmOriginMap);
    osmOriginMap.on('click', async e => await setCoord('origin', e.latlng.lat, e.latlng.lng));

    osmDestMap = L.map('dest-map', { zoomControl: true }).setView([-7.965, 112.60], 12);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom:19 }).addTo(osmDestMap);
    osmDestMap.on('click', async e => await setCoord('dest', e.latlng.lat, e.latlng.lng));
}

function updateOSMMarker(type, lat, lng) {
    const map   = type === 'origin' ? osmOriginMap : osmDestMap;
    const color = type === 'origin' ? '#22c55e'    : '#ef4444';
    if (!map) return;

    if (type === 'origin') { if (osmOriginMrk) map.removeLayer(osmOriginMrk); }
    else                   { if (osmDestMrk)   map.removeLayer(osmDestMrk);   }

    const mrk = L.marker([lat, lng], {
        icon: L.divIcon({
            html: `<div style="width:18px;height:18px;background:${color};border-radius:50%;border:3px solid white;box-shadow:0 0 0 3px ${color}55;"></div>`,
            iconSize:[18,18], iconAnchor:[9,9], className:''
        })
    }).addTo(map);
    map.setView([lat, lng], 15);

    if (type === 'origin') osmOriginMrk = mrk;
    else                   osmDestMrk   = mrk;
}

// ── Google Maps ───────────────────────────────────────────────────
function initGMaps() {
    if (!GMAPS_KEY) {
        // Fallback ke OSM jika tidak ada key
        initOSMMaps();
        return;
    }

    if (window.google && window.google.maps) {
        createGMaps();
        return;
    }
    if (document.getElementById('gmaps-sdk')) return;

    const s   = document.createElement('script');
    s.id      = 'gmaps-sdk';
    // WAJIB: sertakan places library
    s.src     = `https://maps.googleapis.com/maps/api/js?key=${GMAPS_KEY}&libraries=places&callback=onTripGmapReady&loading=async`;
    s.async   = true;
    s.defer   = true;
    s.onerror = () => {
        console.warn('Google Maps gagal load, fallback ke OSM');
        document.getElementById('origin-map').style.display = 'block';
        document.getElementById('dest-map').style.display   = 'block';
        initOSMMaps();
    };
    document.head.appendChild(s);
}

window.onTripGmapReady = function () {
    gmapsReady = true;
    createGMaps();
};

function createGMaps() {
    const opts = {
        zoom:               12,
        center:             { lat: -7.965, lng: 112.60 },
        mapTypeId:          'roadmap',
        mapTypeControl:     false,
        fullscreenControl:  false,
        streetViewControl:  false,
    };

    gOriginMap = new google.maps.Map(document.getElementById('origin-map'), opts);
    gDestMap   = new google.maps.Map(document.getElementById('dest-map'),   opts);

    // Klik peta Google
    gOriginMap.addListener('click', async e => {
        const lat = e.latLng.lat(), lng = e.latLng.lng();
        await setCoord('origin', lat, lng);
    });
    gDestMap.addListener('click', async e => {
        const lat = e.latLng.lat(), lng = e.latLng.lng();
        await setCoord('dest', lat, lng);
    });
}

function updateGMarker(type, lat, lng) {
    const map   = type === 'origin' ? gOriginMap : gDestMap;
    const color = type === 'origin' ? '#22c55e'  : '#ef4444';
    if (!map || !window.google) return;

    if (type === 'origin') { if (gOriginMrk) gOriginMrk.setMap(null); }
    else                   { if (gDestMrk)   gDestMrk.setMap(null);   }

    const mrk = new google.maps.Marker({
        position: { lat, lng },
        map,
        icon: {
            path:         google.maps.SymbolPath.CIRCLE,
            scale:        11,
            fillColor:    color,
            fillOpacity:  1,
            strokeColor:  'white',
            strokeWeight: 3,
        },
        zIndex: 10,
    });
    map.setCenter({ lat, lng });
    map.setZoom(15);

    if (type === 'origin') gOriginMrk = mrk;
    else                   gDestMrk   = mrk;
}

// ════════════════════════════════════════════════════════════════
// GOOGLE PLACES AUTOCOMPLETE
// ════════════════════════════════════════════════════════════════
function setupGoogleAutocomplete(type) {
    // Ganti input search jadi Google Autocomplete
    const inputEl = document.getElementById(`${type}-search`);
    if (!inputEl || !window.google) return;

    const autocomplete = new google.maps.places.Autocomplete(inputEl, {
        componentRestrictions: { country: 'id' },
        fields: ['geometry', 'formatted_address', 'name'],
    });

    // Bind autocomplete ke map agar prioritas area yang ditampilkan
    const map = type === 'origin' ? gOriginMap : gDestMap;
    if (map) autocomplete.bindTo('bounds', map);

    autocomplete.addListener('place_changed', async () => {
        const place = autocomplete.getPlace();

        if (!place.geometry || !place.geometry.location) {
            // Jika user enter tanpa pilih suggestion → fallback ke text search
            doTextSearch(type, inputEl.value);
            return;
        }

        const lat      = place.geometry.location.lat();
        const lng      = place.geometry.location.lng();
        const fullAddr = place.formatted_address || place.name || '';

        await setCoord(type, lat, lng, fullAddr);

        // Auto isi nama tempat jika kosong
        const nameInput = document.getElementById(`${type}-name`);
        if (nameInput && !nameInput.value.trim()) {
            nameInput.value = place.name || fullAddr.split(',')[0];
        }
    });
}

// ── Google Text Search (fallback jika autocomplete tidak dipilih) ─
function doTextSearch(type, query) {
    if (!query.trim() || !window.google) return;

    const map     = type === 'origin' ? gOriginMap : gDestMap;
    const svc     = new google.maps.places.PlacesService(map);
    const results = document.getElementById(`${type}-results`);

    results.innerHTML = `<div class="search-result-item" style="color:#9ca3af;">🔍 Mencari...</div>`;
    results.classList.remove('hidden');

    svc.textSearch({ query, region: 'id' }, async (data, status) => {
        results.innerHTML = '';

        if (status !== google.maps.places.PlacesServiceStatus.OK || !data.length) {
            results.innerHTML = `<div class="search-result-item" style="color:#9ca3af;">Tidak ditemukan.</div>`;
            return;
        }

        data.slice(0, 6).forEach(place => {
            const div       = document.createElement('div');
            div.className   = 'search-result-item';
            const shortName = place.name;
            const fullAddr  = place.formatted_address || place.vicinity || '';

            div.innerHTML = `
                <div style="font-weight:600;color:#111827;margin-bottom:2px;">${shortName}</div>
                <div style="color:#9ca3af;font-size:10px;">${fullAddr}</div>`;

            div.onclick = async () => {
                const lat = place.geometry.location.lat();
                const lng = place.geometry.location.lng();
                await setCoord(type, lat, lng, `${shortName}, ${fullAddr}`);

                const nameInput = document.getElementById(`${type}-name`);
                if (nameInput && !nameInput.value.trim()) nameInput.value = shortName;

                document.getElementById(`${type}-search`).value = shortName;
                results.classList.add('hidden');
            };
            results.appendChild(div);
        });
    });
}

// ════════════════════════════════════════════════════════════════
// LIVE SEARCH — debounce 400ms, ketik langsung muncul hasil
// ════════════════════════════════════════════════════════════════
let _searchTimers = {};

function liveSearch(type) {
    clearTimeout(_searchTimers[type]);
    const q = document.getElementById(`${type}-search`).value.trim();
    const resEl = document.getElementById(`${type}-results`);

    if (q.length < 2) { resEl.classList.add('hidden'); return; }

    _searchTimers[type] = setTimeout(() => {
        if (MAP_TYPE === 'gmaps' && gmapsReady && window.google) {
            doGoogleLiveSearch(type, q, resEl);
        } else {
            doNominatimLiveSearch(type, q, resEl);
        }
    }, 400);
}

async function doNominatimLiveSearch(type, q, resEl) {
    resEl.innerHTML = '<div class="search-result-item" style="color:#9ca3af;">🔍 Mencari...</div>';
    resEl.classList.remove('hidden');
    try {
        const r = await fetch(
            `https://nominatim.openstreetmap.org/search?q=${encodeURIComponent(q)}&format=json&limit=6&countrycodes=id`,
            { headers: { 'Accept-Language': 'id' } }
        );
        const data = await r.json();
        resEl.innerHTML = '';
        if (!data.length) {
            resEl.innerHTML = '<div class="search-result-item" style="color:#9ca3af;">Tidak ditemukan.</div>';
            return;
        }
        data.forEach(place => {
            const div = document.createElement('div');
            div.className = 'search-result-item';
            const parts = place.display_name.split(',');
            const shortName = parts.slice(0, 2).join(',').trim();
            div.innerHTML = `<div style="font-weight:600;color:#111827;margin-bottom:2px;">${shortName}</div>
                             <div style="color:#9ca3af;font-size:10px;">${place.display_name}</div>`;
            div.onclick = async () => {
                const lat = parseFloat(place.lat), lng = parseFloat(place.lon);
                await setCoord(type, lat, lng, place.display_name);
                const nameEl = document.getElementById(`${type}-name`);
                if (nameEl && !nameEl.value.trim()) nameEl.value = shortName;
                document.getElementById(`${type}-search`).value = shortName;
                resEl.classList.add('hidden');
            };
            resEl.appendChild(div);
        });
    } catch {
        resEl.innerHTML = '<div class="search-result-item" style="color:#ef4444;">Gagal mencari.</div>';
    }
}

function doGoogleLiveSearch(type, q, resEl) {
    resEl.innerHTML = '<div class="search-result-item" style="color:#9ca3af;">🔍 Mencari...</div>';
    resEl.classList.remove('hidden');
    const svc = new google.maps.places.PlacesService(document.createElement('div'));
    svc.textSearch({ query: q, region: 'id' }, async (results, status) => {
        resEl.innerHTML = '';
        if (status !== google.maps.places.PlacesServiceStatus.OK || !results.length) {
            resEl.innerHTML = '<div class="search-result-item" style="color:#9ca3af;">Tidak ditemukan.</div>';
            return;
        }
        results.slice(0, 6).forEach(place => {
            const div = document.createElement('div');
            div.className = 'search-result-item';
            const addr = place.formatted_address || place.vicinity || '';
            div.innerHTML = `<div style="font-weight:600;color:#111827;margin-bottom:2px;">${place.name}</div>
                             <div style="color:#9ca3af;font-size:10px;">${addr}</div>`;
            div.onclick = async () => {
                const lat = place.geometry.location.lat(), lng = place.geometry.location.lng();
                await setCoord(type, lat, lng, `${place.name}, ${addr}`);
                const nameEl = document.getElementById(`${type}-name`);
                if (nameEl && !nameEl.value.trim()) nameEl.value = place.name;
                document.getElementById(`${type}-search`).value = place.name;
                resEl.classList.add('hidden');
            };
            resEl.appendChild(div);
        });
    });
}

// Attach live search ke input
document.getElementById('origin-search').addEventListener('input', () => liveSearch('origin'));
document.getElementById('dest-search').addEventListener('input',   () => liveSearch('dest'));

// Klik luar dropdown → tutup
document.addEventListener('click', e => {
    ['origin', 'dest'].forEach(type => {
        const inp = document.getElementById(`${type}-search`);
        const res = document.getElementById(`${type}-results`);
        if (inp && res && !inp.contains(e.target) && !res.contains(e.target)) {
            res.classList.add('hidden');
        }
    });
});

// ════════════════════════════════════════════════════════════════
// INFO DEVICE saat pilih kendaraan
// ════════════════════════════════════════════════════════════════
async function onVehicleChange(vehicleId) {
    const infoBox  = document.getElementById('device-info');
    const infoText = document.getElementById('device-info-text');
    if (!vehicleId) { infoBox.classList.add('hidden'); return; }

    try {
        const res  = await fetch(`/api/internal/vehicle-device/${vehicleId}`);
        const data = await res.json();

        if (data.device) {
            const stColor = { online:'#15803d', idle:'#c2410c', offline:'#b91c1c' }[data.device.status] || '#6b7280';
            let msg = `Device: <strong>${data.device.device_id}</strong> (${data.device.device_type})`;
            msg += ` · <span style="color:${stColor};font-weight:700;">${(data.device.status||'').toUpperCase()}</span>`;
            if (data.driver) {
                msg += ` · Supir: <strong>${data.driver.full_name}</strong>`;
                const ds = document.getElementById('driver-select');
                if (ds) ds.value = data.driver.id;
            }
            infoText.innerHTML = msg;
            infoBox.className  = 'mt-3 p-3 bg-blue-50 border border-blue-200 rounded-xl';
        } else {
            infoText.innerHTML = '⚠️ Kendaraan ini belum memiliki device IoT terpasang.';
            infoBox.className  = 'mt-3 p-3 bg-yellow-50 border border-yellow-200 rounded-xl';
        }
        infoBox.classList.remove('hidden');
    } catch(e) { infoBox.classList.add('hidden'); }
}

// ════════════════════════════════════════════════════════════════
// FORM SUBMIT VALIDATION
// ════════════════════════════════════════════════════════════════
document.getElementById('trip-form').addEventListener('submit', function(e) {
    if (!state.origin.lat || !state.dest.lat) {
        e.preventDefault();
        alert('Pilih titik awal dan titik tujuan di peta terlebih dahulu!');
        return;
    }
    document.getElementById('h-departed-at').value = '';
});

// ════════════════════════════════════════════════════════════════
// INIT — berdasarkan MAP_TYPE dari session
// ════════════════════════════════════════════════════════════════
if (MAP_TYPE === 'gmaps' && GMAPS_KEY) {
    initGMaps(); // Load Google Maps SDK → onTripGmapReady → createGMaps → setupGoogleAutocomplete
} else {
    initOSMMaps();
}