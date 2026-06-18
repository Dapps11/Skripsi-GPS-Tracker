    @extends('layouts.app')
@section('title', 'Edit Trip — ' . $trip->trip_code)

@push('styles')
<style>
    .form-input {
        width: 100%;
        padding: 10px 14px;
        font-size: 14px;
        border: 1.5px solid #e5e7eb;
        border-radius: 12px;
        outline: none;
        transition: border-color .15s, box-shadow .15s;
        background: white;
    }
    .form-input:focus {
        border-color: #22c55e;
        box-shadow: 0 0 0 3px rgba(34,197,94,.15);
    }
    .form-label {
        display: block;
        font-size: 13px;
        font-weight: 600;
        color: #374151;
        margin-bottom: 6px;
    }
    .form-group { margin-bottom: 20px; }
    .section-title {
        font-size: 10px;
        font-weight: 700;
        color: #9ca3af;
        text-transform: uppercase;
        letter-spacing: .08em;
        padding-bottom: 8px;
        border-bottom: 1px solid #f1f5f9;
        margin-bottom: 16px;
    }
</style>
@endpush

@section('content')
<div class="p-6 max-w-2xl mx-auto">

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
            @foreach($errors->all() as $error)
            <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    <div class="card p-6">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h2 class="text-lg font-extrabold text-gray-900">Edit Trip</h2>
                <p class="text-xs text-gray-400 mt-0.5 font-mono">{{ $trip->trip_code }}</p>
            </div>
            @php
                $stMap = [
                    'planned'     => 'bg-blue-100 text-blue-700',
                    'in_progress' => 'bg-green-100 text-green-700',
                    'completed'   => 'bg-gray-100 text-gray-600',
                    'cancelled'   => 'bg-red-100 text-red-600',
                ];
            @endphp
            <span class="px-3 py-1 rounded-full text-xs font-bold {{ $stMap[$trip->status] ?? 'bg-gray-100 text-gray-600' }}">
                {{ ucfirst(str_replace('_', ' ', $trip->status)) }}
            </span>
        </div>

        <form action="{{ route('trips.update', $trip) }}" method="POST">
            @csrf
            @method('PUT')

            {{-- ── Kendaraan & Supir ── --}}
            <div class="section-title">Kendaraan & Supir</div>
            <div class="grid grid-cols-2 gap-4">
                <div class="form-group">
                    <label class="form-label">Kendaraan <span class="text-red-400">*</span></label>
                    <select name="vehicle_id" class="form-input appearance-none"
                            {{ $trip->status === 'in_progress' ? 'disabled' : '' }}>
                        @foreach($vehicles as $v)
                        <option value="{{ $v->id }}" {{ old('vehicle_id', $trip->vehicle_id) == $v->id ? 'selected' : '' }}>
                            {{ $v->name }} — {{ $v->license_plate }}
                        </option>
                        @endforeach
                    </select>
                    @if($trip->status === 'in_progress')
                    {{-- Hidden input karena disabled field tidak terkirim --}}
                    <input type="hidden" name="vehicle_id" value="{{ $trip->vehicle_id }}">
                    <p class="text-xs text-amber-600 mt-1">⚠️ Kendaraan tidak bisa diubah saat trip sedang berjalan.</p>
                    @endif
                    @error('vehicle_id') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
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

            {{-- ── Rute ── --}}
            <div class="section-title">Rute Perjalanan</div>

            <div class="grid grid-cols-2 gap-4">
                <div class="form-group">
                    <label class="form-label">Nama Asal <span class="text-red-400">*</span></label>
                    <input type="text" name="origin_name" class="form-input"
                           value="{{ old('origin_name', $trip->origin_name) }}" required>
                    @error('origin_name') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                </div>
                <div class="form-group">
                    <label class="form-label">Nama Tujuan <span class="text-red-400">*</span></label>
                    <input type="text" name="dest_name" class="form-input"
                           value="{{ old('dest_name', $trip->dest_name) }}" required>
                    @error('dest_name') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div class="form-group">
                    <label class="form-label">Alamat Asal</label>
                    <input type="text" name="origin_address" class="form-input"
                           value="{{ old('origin_address', $trip->origin_address) }}">
                </div>
                <div class="form-group">
                    <label class="form-label">Alamat Tujuan</label>
                    <input type="text" name="dest_address" class="form-input"
                           value="{{ old('dest_address', $trip->dest_address) }}">
                </div>
            </div>

            {{-- Koordinat --}}
            <div class="grid grid-cols-2 gap-4">
                <div class="p-3 bg-gray-50 rounded-xl border border-gray-200">
                    <div class="text-xs font-bold text-gray-500 mb-2">🟢 Koordinat Asal</div>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="text-xs text-gray-400 mb-1 block">Latitude</label>
                            <input type="number" name="origin_lat" step="any" class="form-input text-xs"
                                   value="{{ old('origin_lat', $trip->origin_lat) }}" required>
                        </div>
                        <div>
                            <label class="text-xs text-gray-400 mb-1 block">Longitude</label>
                            <input type="number" name="origin_lng" step="any" class="form-input text-xs"
                                   value="{{ old('origin_lng', $trip->origin_lng) }}" required>
                        </div>
                    </div>
                </div>
                <div class="p-3 bg-gray-50 rounded-xl border border-gray-200">
                    <div class="text-xs font-bold text-gray-500 mb-2">🔴 Koordinat Tujuan</div>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="text-xs text-gray-400 mb-1 block">Latitude</label>
                            <input type="number" name="dest_lat" step="any" class="form-input text-xs"
                                   value="{{ old('dest_lat', $trip->dest_lat) }}" required>
                        </div>
                        <div>
                            <label class="text-xs text-gray-400 mb-1 block">Longitude</label>
                            <input type="number" name="dest_lng" step="any" class="form-input text-xs"
                                   value="{{ old('dest_lng', $trip->dest_lng) }}" required>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ── Waktu & Status ── --}}
            <div class="section-title mt-6">Waktu & Status</div>
            <div class="grid grid-cols-2 gap-4">
                <div class="form-group">
                    <label class="form-label">Waktu Berangkat <span class="text-red-400">*</span></label>
                    <input type="datetime-local" name="departed_at" class="form-input"
                           value="{{ old('departed_at', $trip->departed_at ? \Carbon\Carbon::parse($trip->departed_at)->format('Y-m-d\TH:i') : '') }}"
                           required>
                    @error('departed_at') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                </div>
                <div class="form-group">
                    <label class="form-label">Estimasi Tiba</label>
                    <input type="datetime-local" name="estimated_arrival_at" class="form-input"
                           value="{{ old('estimated_arrival_at', $trip->estimated_arrival_at ? \Carbon\Carbon::parse($trip->estimated_arrival_at)->format('Y-m-d\TH:i') : '') }}">
                    @error('estimated_arrival_at') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Status <span class="text-red-400">*</span></label>
                <select name="status" class="form-input appearance-none">
                    <option value="planned"     {{ old('status', $trip->status) === 'planned'     ? 'selected' : '' }}>Planned</option>
                    <option value="in_progress" {{ old('status', $trip->status) === 'in_progress' ? 'selected' : '' }}>In Progress</option>
                    <option value="completed"   {{ old('status', $trip->status) === 'completed'   ? 'selected' : '' }}>Completed</option>
                    <option value="cancelled"   {{ old('status', $trip->status) === 'cancelled'   ? 'selected' : '' }}>Cancelled</option>
                </select>
                @error('status') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="form-group">
                <label class="form-label">Catatan</label>
                <textarea name="notes" rows="3" class="form-input resize-none"
                          placeholder="Catatan tambahan...">{{ old('notes', $trip->notes) }}</textarea>
            </div>

            {{-- Actions --}}
            <div class="flex items-center justify-between pt-4 border-t border-gray-100">
                <a href="{{ route('trips.show', $trip) }}"
                   class="px-4 py-2.5 text-sm font-semibold text-gray-600 border border-gray-200 rounded-xl hover:bg-gray-50 transition-all">
                    ← Kembali
                </a>
                <div class="flex items-center gap-2">
                    <a href="{{ route('trips.show', $trip) }}"
                       class="px-4 py-2.5 text-sm font-semibold text-gray-600 hover:text-gray-800 transition-colors">
                        Batal
                    </a>
                    <button type="submit"
                            class="px-6 py-2.5 bg-green-500 hover:bg-green-600 text-white text-sm font-bold rounded-xl transition-all shadow-md hover:shadow-green-200">
                        💾 Simpan Perubahan
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection