@extends('layouts.app')
@section('title', 'Edit Trip — ' . $trip->trip_code)

@push('styles')
<style>
    .form-input {
        width: 100%; padding: 10px 14px; font-size: 14px;
        border: 1.5px solid #e5e7eb; border-radius: 12px;
        outline: none; transition: border-color .15s, box-shadow .15s;
        background: white;
    }
    .form-input:focus { border-color:#22c55e; box-shadow:0 0 0 3px rgba(34,197,94,.15); }
    .form-input:disabled { background:#f9fafb; color:#9ca3af; cursor:not-allowed; }
    .form-label { display:block; font-size:13px; font-weight:600; color:#374151; margin-bottom:6px; }
    .form-group { margin-bottom:20px; }
    .section-title {
        font-size:10px; font-weight:700; color:#9ca3af;
        text-transform:uppercase; letter-spacing:.08em;
        padding-bottom:8px; border-bottom:1px solid #f1f5f9; margin-bottom:16px;
    }
    #edit-origin-map, #edit-dest-map {
        height:240px; border-radius:12px; border:1.5px solid #e5e7eb; cursor:crosshair; z-index:0;
    }
    .coord-badge {
        display:inline-flex; align-items:center; gap:6px;
        padding:4px 9px; background:#f9fafb; border:1px solid #e5e7eb;
        border-radius:8px; font-size:10px; font-family:monospace; color:#6b7280;
    }
    .coord-badge.set { background:#f0fdf4; border-color:#86efac; color:#15803d; }
    .search-result-item {
        padding:9px 14px; font-size:12px; cursor:pointer;
        border-bottom:1px solid #f3f4f6; color:#374151; transition:background .1s;
    }
    .search-result-item:hover { background:#f9fafb; }
    .search-result-item:last-child { border-bottom:none; }
    .locked-banner {
        display:flex; align-items:center; gap:10px;
        padding:14px 16px; background:#fff7ed; border:1.5px solid #fed7aa;
        border-radius:12px; margin-bottom:20px;
    }
</style>
@endpush

@section('content')
<div class="p-6 max-w-6xl mx-auto">

    {{-- Breadcrumb --}}
    <div class="flex items-center gap-2 text-sm text-gray-400 mb-6">
        <a href="{{ route('trips.index') }}" class="hover:text-green-500 transition-colors">Trip Management</a>
        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
        </svg>
        <a href="{{ route('trips.show', $trip) }}" class="hover:text-green-500 transition-colors font-mono text-xs">{{ $trip->trip_code }}</a>
        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
        </svg>
        <span class="text-gray-700 font-semibold">Edit</span>
    </div>

    @if($errors->any())
    <div class="mb-4 p-4 bg-red-50 border border-red-200 rounded-xl text-sm text-red-700">
        <div class="font-bold mb-1">Ada kesalahan input:</div>
        <ul class="list-disc list-inside space-y-0.5">
            @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
        </ul>
    </div>
    @endif

    <div class="card p-6">

        {{-- Header --}}
        <div class="flex items-center justify-between mb-6">
            <div>
                <h2 class="text-lg font-extrabold text-gray-900">Edit Trip</h2>
                <p class="text-xs text-gray-400 mt-0.5 font-mono">{{ $trip->trip_code }}</p>
            </div>
            @php
                $stMap = [
                    'planned'     => ['bg-blue-100 text-blue-700',   'Planned'],
                    'in_progress' => ['bg-green-100 text-green-700', 'In Progress'],
                    'completed'   => ['bg-gray-100 text-gray-600',   'Completed'],
                    'cancelled'   => ['bg-red-100 text-red-600',     'Cancelled'],
                ];
                [$stCls, $stLbl] = $stMap[$trip->status] ?? ['bg-gray-100 text-gray-600', $trip->status];
                $isLocked = $trip->status !== 'planned';
            @endphp
            <span class="px-3 py-1 rounded-full text-xs font-bold {{ $stCls }}">{{ $stLbl }}</span>
        </div>

        {{-- Lock banner jika bukan planned --}}
        @if($isLocked)
        <div class="locked-banner">
            <svg class="w-5 h-5 text-orange-500 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
            </svg>
            <div>
                <div class="text-sm font-bold text-orange-800">Trip tidak bisa diedit</div>
                <div class="text-xs text-orange-600 mt-0.5">
                    Trip dengan status <strong>{{ $stLbl }}</strong> sudah tidak bisa diubah. Edit hanya diizinkan saat status masih <strong>Planned</strong>.
                </div>
            </div>
            <a href="{{ route('trips.index') }}"
               class="ml-auto px-4 py-2 bg-orange-500 hover:bg-orange-600 text-white text-xs font-bold rounded-xl transition-all flex-shrink-0">
                ← Trip Management
            </a>
        </div>

        {{-- Read-only view saat locked --}}
        <div class="space-y-4 opacity-60 pointer-events-none select-none">
            <div class="section-title">Kendaraan & Supir</div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div><div class="form-label">Kendaraan</div>
                    <div class="form-input">{{ $trip->vehicle->name ?? '—' }} — {{ $trip->vehicle->license_plate ?? '' }}</div></div>
                <div><div class="form-label">Supir</div>
                    <div class="form-input">{{ $trip->driver->full_name ?? '— Tanpa supir —' }}</div></div>
            </div>
            <div class="section-title">Rute</div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div><div class="form-label">Asal</div><div class="form-input">{{ $trip->origin_name }}</div></div>
                <div><div class="form-label">Tujuan</div><div class="form-input">{{ $trip->dest_name }}</div></div>
            </div>
            @if($trip->notes)
            <div><div class="form-label">Catatan</div><div class="form-input">{{ $trip->notes }}</div></div>
            @endif
        </div>

        @else
        {{-- ══ FORM — hanya tampil jika planned ══ --}}
        <form action="{{ route('trips.update', $trip) }}" method="POST">
            @csrf
            @method('PUT')

            {{-- ── Kendaraan & Supir ── --}}
            <div class="section-title">Kendaraan & Supir</div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="form-group">
                    <label class="form-label">Kendaraan <span class="text-red-400">*</span></label>
                    <select name="vehicle_id" class="form-input appearance-none">
                        @foreach($vehicles as $v)
                        <option value="{{ $v->id }}" {{ old('vehicle_id', $trip->vehicle_id) == $v->id ? 'selected' : '' }}>
                            {{ $v->name }} — {{ $v->license_plate }}
                        </option>
                        @endforeach
                    </select>
                    @error('vehicle_id')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                </div>
                <div class="form-group">
                    <label class="form-label">Supir</label>
                    <select name="driver_id" class="form-input appearance-none">
                        <option value="">— Tanpa supir —</option>
                        @foreach($drivers as $d)
                        <option value="{{ $d->id }}" {{ old('driver_id', $trip->driver_id) == $d->id ? 'selected' : '' }}>
                            {{ $d->full_name }} ({{ $d->driver_code }})
                        </option>
                        @endforeach
                    </select>
                </div>
            </div>

            {{-- Hidden fields wajib dikirim (status tetap planned, waktu tidak diubah user) --}}
            <input type="hidden" name="status"       value="planned">
            <input type="hidden" name="departed_at"  value="{{ $trip->departed_at ?? '' }}">
            <input type="hidden" name="origin_lat"   id="edit-origin-lat" value="{{ old('origin_lat', $trip->origin_lat) }}">
            <input type="hidden" name="origin_lng"   id="edit-origin-lng" value="{{ old('origin_lng', $trip->origin_lng) }}">
            <input type="hidden" name="dest_lat"     id="edit-dest-lat"   value="{{ old('dest_lat',   $trip->dest_lat) }}">
            <input type="hidden" name="dest_lng"     id="edit-dest-lng"   value="{{ old('dest_lng',   $trip->dest_lng) }}">
            <input type="hidden" name="origin_address" id="edit-origin-address" value="{{ old('origin_address', $trip->origin_address) }}">
            <input type="hidden" name="dest_address"   id="edit-dest-address"   value="{{ old('dest_address',   $trip->dest_address) }}">

            {{-- ── Rute ── --}}
            <div class="section-title">Rute Perjalanan</div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="form-group">
                    <label class="form-label">Nama Asal <span class="text-red-400">*</span></label>
                    <input type="text" name="origin_name" id="edit-origin-name" class="form-input"
                           value="{{ old('origin_name', $trip->origin_name) }}" required>
                    @error('origin_name')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                </div>
                <div class="form-group">
                    <label class="form-label">Nama Tujuan <span class="text-red-400">*</span></label>
                    <input type="text" name="dest_name" id="edit-dest-name" class="form-input"
                           value="{{ old('dest_name', $trip->dest_name) }}" required>
                    @error('dest_name')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                </div>
            </div>

            {{-- ── Peta + Search Alamat (side by side) ── --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">

                {{-- ORIGIN --}}
                <div>
                    <div class="text-xs font-bold text-green-700 uppercase tracking-wider mb-2">🟢 Titik Asal</div>

                    {{-- Search --}}
                    <div class="relative mb-2">
                        <div class="relative">
                            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                            </svg>
                            <input type="text" id="edit-origin-search" placeholder="Ketik untuk cari alamat asal..."
                                   class="form-input pl-9 text-sm py-2">
                        </div>
                        <div id="edit-origin-results"
                             class="absolute z-50 w-full bg-white border border-gray-200 rounded-xl shadow-lg hidden overflow-hidden" style="top:calc(100% + 4px)"></div>
                    </div>

                    {{-- Peta --}}
                    <div id="edit-origin-map" class="mb-2"></div>
                    <p class="text-xs text-gray-400 mb-1">💡 Klik peta atau drag marker untuk pindah pin</p>

                    {{-- Alamat display --}}
                    <div class="p-2.5 bg-gray-50 border border-gray-200 rounded-xl">
                        <div class="flex items-center justify-between mb-1">
                            <span class="text-xs font-semibold text-gray-500">Alamat</span>
                            <span id="edit-origin-badge" class="coord-badge set">
                                {{ number_format($trip->origin_lat, 5) }}, {{ number_format($trip->origin_lng, 5) }}
                            </span>
                        </div>
                        <div id="edit-origin-addr-display" class="text-xs text-gray-600">
                            {{ $trip->origin_address ?: '—' }}
                        </div>
                    </div>
                </div>

                {{-- DESTINATION --}}
                <div>
                    <div class="text-xs font-bold text-red-600 uppercase tracking-wider mb-2">🔴 Titik Tujuan</div>

                    {{-- Search --}}
                    <div class="relative mb-2">
                        <div class="relative">
                            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                            </svg>
                            <input type="text" id="edit-dest-search" placeholder="Ketik untuk cari alamat tujuan..."
                                   class="form-input pl-9 text-sm py-2">
                        </div>
                        <div id="edit-dest-results"
                             class="absolute z-50 w-full bg-white border border-gray-200 rounded-xl shadow-lg hidden overflow-hidden" style="top:calc(100% + 4px)"></div>
                    </div>

                    {{-- Peta --}}
                    <div id="edit-dest-map" class="mb-2"></div>
                    <p class="text-xs text-gray-400 mb-1">💡 Klik peta atau drag marker untuk pindah pin</p>

                    {{-- Alamat display --}}
                    <div class="p-2.5 bg-gray-50 border border-gray-200 rounded-xl">
                        <div class="flex items-center justify-between mb-1">
                            <span class="text-xs font-semibold text-gray-500">Alamat</span>
                            <span id="edit-dest-badge" class="coord-badge set">
                                {{ number_format($trip->dest_lat, 5) }}, {{ number_format($trip->dest_lng, 5) }}
                            </span>
                        </div>
                        <div id="edit-dest-addr-display" class="text-xs text-gray-600">
                            {{ $trip->dest_address ?: '—' }}
                        </div>
                    </div>
                </div>
            </div>

            {{-- ── Catatan ── --}}
            <div class="form-group">
                <label class="form-label">Catatan</label>
                <textarea name="notes" rows="2" class="form-input resize-none"
                          placeholder="Catatan tambahan (opsional)...">{{ old('notes', $trip->notes) }}</textarea>
            </div>

            {{-- Actions --}}
            <div class="flex items-center justify-between pt-4 border-t border-gray-100">
                <a href="{{ route('trips.index') }}"
                   class="px-4 py-2.5 text-sm font-semibold text-gray-600 border border-gray-200 rounded-xl hover:bg-gray-50 transition-all">
                    ← Kembali
                </a>
                <button type="submit"
                        class="px-6 py-2.5 bg-green-500 hover:bg-green-600 text-white text-sm font-bold rounded-xl transition-all shadow-md hover:shadow-green-200">
                    💾 Simpan Perubahan
                </button>
            </div>
        </form>
        @endif

    </div>
</div>
@endsection

@push('scripts')
@if($trip->status === 'planned')
<script>
const EDIT_MAP_TYPE  = "{{ $mapType ?? 'osm' }}";
const EDIT_GMAPS_KEY = "{{ $googleMapsKey ?? '' }}";

const editState = {
    origin: { lat: {{ (float) $trip->origin_lat }}, lng: {{ (float) $trip->origin_lng }} },
    dest:   { lat: {{ (float) $trip->dest_lat }},   lng: {{ (float) $trip->dest_lng }}   },
};

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
    const svc = new google.maps.places.PlacesService(document.createElement('div'));
    svc.textSearch({ query: q, region: 'id' }, async (results, status) => {
        resEl.innerHTML = '';
        if (status !== google.maps.places.PlacesServiceStatus.OK || !results.length) {
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
</script>
@endif
@endpush