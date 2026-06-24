@extends('layouts.app')
@section('title', 'Tambah Trip — Greenfields')

@push('styles')
<style>
    #origin-map, #dest-map {
        height: 280px;
        border-radius: 12px;
        border: 1.5px solid #e5e7eb;
        cursor: crosshair;
        z-index: 0;
    }
    @media (min-width: 768px) {
        #origin-map, #dest-map {
            height: 460px;
        }
    }
    .coord-badge {
        display: inline-flex; align-items: center; gap: 6px;
        padding: 5px 10px; background: #f9fafb;
        border: 1px solid #e5e7eb; border-radius: 8px;
        font-size: 11px; font-family: monospace; color: #6b7280;
    }
    .coord-badge.set { background: #f0fdf4; border-color: #86efac; color: #15803d; }
    .search-result-item {
        padding: 10px 14px; font-size: 12px; cursor: pointer;
        border-bottom: 1px solid #f3f4f6; color: #374151;
        transition: background .1s;
    }
    .search-result-item:hover { background: #f9fafb; }
    .search-result-item:last-child { border-bottom: none; }
    .eta-badge {
        display: inline-flex; align-items: center; gap: 6px;
        padding: 6px 12px; background: #eff6ff;
        border: 1px solid #bfdbfe; border-radius: 10px;
        font-size: 12px; font-weight: 700; color: #1d4ed8;
    }
</style>
@endpush

@section('content')
<div class="p-6 max-w-6xl mx-auto">

    <div class="flex items-center gap-2 text-sm text-gray-400 mb-6">
        <a href="{{ route('trips.index') }}" class="hover:text-green-500">Trip Management</a>
        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
        </svg>
        <span class="text-gray-700 font-semibold">Tambah Trip</span>
    </div>

    <div class="card p-6">
        <h2 class="text-lg font-extrabold text-gray-900 mb-1">Buat Trip Baru</h2>
        <p class="text-sm text-gray-400 mb-6">
            Tentukan kendaraan, titik awal, dan titik tujuan. Waktu berangkat & ETA otomatis dihitung sistem.
        </p>

        <form action="{{ route('trips.store') }}" method="POST" id="trip-form">
            @csrf

            {{-- Hidden fields — diisi otomatis --}}
            <input type="hidden" name="departed_at"          id="h-departed-at">
            <input type="hidden" name="estimated_arrival_at" id="h-eta">
            <input type="hidden" name="origin_lat"           id="h-origin-lat">
            <input type="hidden" name="origin_lng"           id="h-origin-lng">
            <input type="hidden" name="origin_address"       id="h-origin-address">
            <input type="hidden" name="dest_lat"             id="h-dest-lat">
            <input type="hidden" name="dest_lng"             id="h-dest-lng">
            <input type="hidden" name="dest_address"         id="h-dest-address">

            {{-- ── SECTION 1: Kendaraan ── --}}
            <div class="mb-6">
                <div class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-3 pb-2 border-b border-gray-100">
                    Kendaraan & Supir
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1.5">
                            Kendaraan <span class="text-red-400">*</span>
                        </label>
                        <div class="relative">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-base pointer-events-none">🚛</span>
                            <select name="vehicle_id" id="vehicle-select" required
                                    class="w-full pl-9 pr-8 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-none focus:border-green-500 focus:ring-2 focus:ring-green-500/20 bg-white appearance-none"
                                    onchange="onVehicleChange(this.value)">
                                <option value="">Pilih kendaraan...</option>
                                @foreach($vehicles as $v)
                                <option value="{{ $v->id }}" {{ old('vehicle_id') == $v->id ? 'selected' : '' }}>
                                    {{ $v->name }} — {{ $v->license_plate }}
                                </option>
                                @endforeach
                            </select>
                            <svg class="absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </div>
                        @error('vehicle_id') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1.5">Supir</label>
                        <div class="relative">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-base pointer-events-none">👤</span>
                            <select name="driver_id" id="driver-select"
                                    class="w-full pl-9 pr-8 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-none focus:border-green-500 focus:ring-2 focus:ring-green-500/20 bg-white appearance-none">
                                <option value="">Pilih supir...</option>
                                @foreach($drivers as $d)
                                <option value="{{ $d->id }}" {{ old('driver_id') == $d->id ? 'selected' : '' }}>
                                    {{ $d->full_name }} ({{ $d->driver_code }})
                                </option>
                                @endforeach
                            </select>
                            <svg class="absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </div>
                    </div>
                </div>

                {{-- Info device & status waktu berangkat --}}
                <div id="device-info" class="mt-3 p-3 bg-blue-50 border border-blue-200 rounded-xl hidden">
                    <div class="flex items-start gap-2">
                        <svg class="w-4 h-4 text-blue-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <div class="text-xs text-blue-700 flex-1">
                            <span id="device-info-text"></span>
                        </div>
                    </div>
                </div>

                {{-- Waktu berangkat — otomatis dari device --}}
                <div class="mt-3 p-3 bg-gray-50 border border-gray-200 rounded-xl">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <svg class="w-4 h-4 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <span class="text-xs font-semibold text-gray-600">Waktu Berangkat</span>
                        </div>
                        <span class="text-xs text-gray-400 italic" id="departed-at-display">
                            Otomatis — saat kendaraan mulai bergerak
                        </span>
                    </div>
                    <div class="mt-1 text-[10px] text-gray-400">
                        Sistem akan mencatat waktu berangkat otomatis dari data GPS device saat kendaraan pertama kali bergerak (speed &gt; 2 km/h).
                    </div>
                </div>
            </div>

            {{-- ── SECTION 2: Origin ── --}}
            <div class="mb-6">
                <div class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-3 pb-2 border-b border-gray-100">
                    🟢 Titik Awal (Origin)
                </div>

                {{-- Nama tempat — input manual --}}
                <div class="mb-3">
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">
                        Nama Tempat <span class="text-red-400">*</span>
                    </label>
                    <input type="text" name="origin_name" id="origin-name"
                           value="{{ old('origin_name') }}"
                           placeholder="Contoh: Gudang Utama Greenfields"
                           class="w-full px-4 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-none focus:border-green-500 focus:ring-2 focus:ring-green-500/20">
                    @error('origin_name') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                </div>

                {{-- Search di peta --}}
                <div class="mb-3">
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">
                        Cari di Peta
                        <span class="text-xs font-normal text-gray-400 ml-1">— ketik untuk cari otomatis</span>
                    </label>
                    <div class="relative">
                        <div class="relative">
                            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                            </svg>
                            <input type="text" id="origin-search"
                                   placeholder="Ketik nama tempat, contoh: Universitas Brawijaya..."
                                   class="w-full pl-10 pr-4 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-none focus:border-green-500 focus:ring-2 focus:ring-green-500/20">
                        </div>
                        {{-- Dropdown hasil pencarian --}}
                        <div id="origin-results"
                             class="absolute z-50 w-full bg-white border border-gray-200 rounded-xl shadow-lg hidden overflow-hidden"
                             style="top: calc(100% + 4px);">
                        </div>
                    </div>
                </div>

                {{-- Peta klik --}}
                <div class="mb-3">
                    <div id="origin-map"></div>
                    <p class="text-xs text-gray-400 mt-1">💡 Bisa juga klik langsung di peta untuk pin lokasi</p>
                </div>

                {{-- Koordinat & Alamat — otomatis --}}
                <div class="p-3 bg-gray-50 border border-gray-200 rounded-xl" id="origin-coord-box">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs font-semibold text-gray-500">Koordinat & Alamat</span>
                        <span id="origin-coord-badge" class="coord-badge">Belum dipilih</span>
                    </div>
                    <div id="origin-address-display" class="text-xs text-gray-400 italic">
                        Pilih lokasi di peta atau cari menggunakan search di atas.
                    </div>
                </div>
                @error('origin_lat') <p class="text-xs text-red-500 mt-1">Koordinat asal wajib diisi.</p> @enderror
            </div>

            {{-- ── SECTION 3: Destination ── --}}
            <div class="mb-6">
                <div class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-3 pb-2 border-b border-gray-100">
                    🔴 Titik Tujuan (Destination)
                </div>

                <div class="mb-3">
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">
                        Nama Tempat <span class="text-red-400">*</span>
                    </label>
                    <input type="text" name="dest_name" id="dest-name"
                           value="{{ old('dest_name') }}"
                           placeholder="Contoh: Gudang Sapi Perah Ngajum"
                           class="w-full px-4 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-none focus:border-green-500 focus:ring-2 focus:ring-green-500/20">
                    @error('dest_name') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                </div>

                <div class="mb-3">
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">
                        Cari di Peta
                        <span class="text-xs font-normal text-gray-400 ml-1">— ketik untuk cari otomatis</span>
                    </label>
                    <div class="relative">
                        <div class="relative">
                            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                            </svg>
                            <input type="text" id="dest-search"
                                   placeholder="Ketik nama tempat, contoh: Gudang Sapi Perah..."
                                   class="w-full pl-10 pr-4 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-none focus:border-green-500 focus:ring-2 focus:ring-green-500/20">
                        </div>
                        <div id="dest-results"
                             class="absolute z-50 w-full bg-white border border-gray-200 rounded-xl shadow-lg hidden overflow-hidden"
                             style="top: calc(100% + 4px);">
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <div id="dest-map"></div>
                    <p class="text-xs text-gray-400 mt-1">💡 Bisa juga klik langsung di peta untuk pin lokasi</p>
                </div>

                <div class="p-3 bg-gray-50 border border-gray-200 rounded-xl" id="dest-coord-box">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs font-semibold text-gray-500">Koordinat & Alamat</span>
                        <span id="dest-coord-badge" class="coord-badge">Belum dipilih</span>
                    </div>
                    <div id="dest-address-display" class="text-xs text-gray-400 italic">
                        Pilih lokasi di peta atau cari menggunakan search di atas.
                    </div>
                </div>
                @error('dest_lat') <p class="text-xs text-red-500 mt-1">Koordinat tujuan wajib diisi.</p> @enderror
            </div>

            {{-- ── ETA Preview (Haversine) ── --}}
            <div id="eta-preview" class="mb-6 p-4 bg-indigo-50 border border-indigo-200 rounded-xl hidden">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/>
                        </svg>
                        <span class="text-sm font-semibold text-indigo-800">Estimasi Perjalanan (Haversine)</span>
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="eta-badge" id="eta-distance">— km</span>
                        <span class="eta-badge" id="eta-duration">— menit</span>
                    </div>
                </div>
                <div class="mt-2 text-xs text-indigo-600">
                    <!-- Berdasarkan jarak garis lurus × 1.3 (faktor jalan) ÷ kecepatan rata-rata 40 km/h.
                    ETA akan diset otomatis saat trip dibuat. -->
                </div>
            </div>

            {{-- Notes --}}
            <div class="mb-6">
                <label class="block text-sm font-semibold text-gray-700 mb-1.5">Catatan</label>
                <textarea name="notes" rows="2"
                          placeholder="Catatan tambahan (opsional)..."
                          class="w-full px-4 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-none focus:border-green-500 focus:ring-2 focus:ring-green-500/20 resize-none">{{ old('notes') }}</textarea>
            </div>

            <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-100">
                <a href="{{ route('trips.index') }}"
                   class="px-5 py-2.5 text-sm font-semibold text-gray-600 border border-gray-200 rounded-xl hover:bg-gray-50 transition-all">
                    Batal
                </a>
                <button type="submit" id="submit-btn"
                        class="px-6 py-2.5 bg-green-500 hover:bg-green-600 text-white text-sm font-bold rounded-xl transition-all shadow-md hover:shadow-green-200">
                    Simpan Trip
                </button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
// ── Data server → trips-create.js ────────────────────────────────
window.__tripcreate = {
    gmapsKey: "{{ $googleMapsKey ?? '' }}",
    mapType:  "{{ $mapType ?? 'osm' }}",
};
</script>
<script src="{{ asset('js/trips-create.js') }}"></script>
@endpush