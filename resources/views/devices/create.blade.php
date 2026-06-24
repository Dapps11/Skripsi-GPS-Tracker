@extends('layouts.app')
@section('title', 'Tambah Device — Greenfields')

@section('content')
<div class="p-6 max-w-2xl mx-auto">

    <div class="flex items-center gap-2 text-sm text-gray-400 mb-6">
        <a href="{{ route('devices.index') }}" class="hover:text-green-500">Devices</a>
        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
        </svg>
        <span class="text-gray-700 font-semibold">Tambah Device</span>
    </div>

    <div class="card p-6">
        <h2 class="text-lg font-extrabold text-gray-900 mb-1">Tambah Device Baru</h2>
        <p class="text-sm text-gray-400 mb-6">Device tracker yang dipasang di kendaraan.</p>

        <form action="{{ route('devices.store') }}" method="POST">
            @csrf

            <div class="mb-4">
                <label class="block text-sm font-semibold text-gray-700 mb-1.5">
                    Device ID
                </label>
                <div class="relative">
                    <input type="text" value="{{ $nextDeviceId }}" readonly tabindex="-1"
                           class="w-full pl-4 pr-10 py-2.5 text-sm font-semibold border border-gray-200 rounded-xl bg-gray-50 text-gray-600 cursor-not-allowed select-none">
                    <svg class="absolute right-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-12v3H8V7a4 4 0 118 0z"/>
                    </svg>
                </div>
                <p class="text-xs text-gray-400 mt-1">Otomatis mengikuti ID terakhir di database — tidak bisa diubah manual.</p>
            </div>

            <div class="grid grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">Kendaraan</label>
                    <select name="vehicle_id"
                            class="w-full px-4 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-none focus:border-green-500 bg-white">
                        <option value="">-- Pilih Kendaraan --</option>
                        @foreach($vehicles as $v)
                        <option value="{{ $v->id }}" {{ old('vehicle_id') == $v->id ? 'selected' : '' }}>
                            {{ $v->name }} ({{ $v->license_plate }})
                        </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">Driver</label>
                    <select name="driver_id"
                            class="w-full px-4 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-none focus:border-green-500 bg-white">
                        <option value="">-- Pilih Driver --</option>
                        @foreach($drivers as $d)
                        <option value="{{ $d->id }}" {{ old('driver_id') == $d->id ? 'selected' : '' }}>
                            {{ $d->full_name }}
                        </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">IMEI</label>
                    <input type="text" name="imei" value="{{ old('imei') }}"
                           placeholder="358000000000000"
                           class="w-full px-4 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-none focus:border-green-500 focus:ring-2 focus:ring-green-500/20">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">No. SIM</label>
                    <input type="text" name="phone_number" value="{{ old('phone_number') }}"
                           placeholder="+6281234567890"
                           class="w-full px-4 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-none focus:border-green-500 focus:ring-2 focus:ring-green-500/20">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">Firmware Version</label>
                    <input type="text" name="firmware_version" value="{{ old('firmware_version') }}"
                           placeholder="v1.0.0"
                           class="w-full px-4 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-none focus:border-green-500 focus:ring-2 focus:ring-green-500/20">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">Network Operator</label>
                    <input type="text" name="network_operator" value="{{ old('network_operator') }}"
                           placeholder="Telkomsel, Indosat..."
                           class="w-full px-4 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-none focus:border-green-500 focus:ring-2 focus:ring-green-500/20">
                </div>
            </div>

            <div class="mb-6">
                <label class="block text-sm font-semibold text-gray-700 mb-1.5">Catatan</label>
                <textarea name="notes" rows="2"
                          class="w-full px-4 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-none focus:border-green-500 focus:ring-2 focus:ring-green-500/20 resize-none">{{ old('notes') }}</textarea>
            </div>

            {{-- Info: device_type otomatis tracker --}}
            <div class="mb-6 p-3 bg-blue-50 border border-blue-200 rounded-xl text-xs text-blue-700">
                ℹ️ Device type otomatis ditetapkan sebagai <strong>Tracker</strong> (GPS + IoT combined).
            </div>

            <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-100">
                <a href="{{ route('devices.index') }}"
                   class="px-5 py-2.5 text-sm font-semibold text-gray-600 border border-gray-200 rounded-xl hover:bg-gray-50">
                    Batal
                </a>
                <button type="submit"
                        class="px-6 py-2.5 bg-green-500 hover:bg-green-600 text-white text-sm font-bold rounded-xl shadow-md">
                    Simpan Device
                </button>
            </div>
        </form>
    </div>
</div>
@endsection