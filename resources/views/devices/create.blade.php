@extends('layouts.app')
@section('title', 'Add Device — Greenfields')

@section('content')
<div class="p-6 max-w-2xl mx-auto">

    <div class="flex items-center gap-2 text-sm text-gray-400 mb-6">
        <a href="{{ route('devices.index') }}" class="hover:text-green-500 transition-colors">Device</a>
        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
        </svg>
        <span class="text-gray-700 font-semibold">Add New Device</span>
    </div>

    <div class="card p-6">
        <h2 class="text-lg font-extrabold text-gray-900 mb-1">Add New Device</h2>
        <p class="text-sm text-gray-400 mb-6">Register IoT device baru ke dalam sistem fleet tracking.</p>

        <form action="{{ route('devices.store') }}" method="POST">
            @csrf

            {{-- Section 1: Device Info --}}
            <div class="mb-6">
                <div class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-3 pb-2 border-b border-gray-100">
                    Device Information
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1.5">
                            Device ID <span class="text-red-400">*</span>
                        </label>
                        <input type="text" name="device_id" value="{{ old('device_id') }}"
                               placeholder="e.g. TRACKER-005"
                               class="w-full px-4 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-none focus:border-green-500 focus:ring-2 focus:ring-green-500/20 transition-all @error('device_id') border-red-400 bg-red-50 @enderror">
                        @error('device_id')
                        <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1.5">
                            Device Type <span class="text-red-400">*</span>
                        </label>
                        <select name="device_type"
                                class="w-full px-4 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-none focus:border-green-500 focus:ring-2 focus:ring-green-500/20 bg-white appearance-none transition-all">
                            <option value="">Pilih tipe...</option>
                            <option value="sim7600"  {{ old('device_type') === 'sim7600'  ? 'selected' : '' }}>SIM7600 (GPS Tracker)</option>
                            <option value="openmv"   {{ old('device_type') === 'openmv'   ? 'selected' : '' }}>OpenMV (Kamera Kantuk)</option>
                            <option value="combined" {{ old('device_type') === 'combined' ? 'selected' : '' }}>Combined</option>
                        </select>
                        @error('device_type')
                        <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </div>

            {{-- Section 2: Assignment --}}
            <div class="mb-6">
                <div class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-3 pb-2 border-b border-gray-100">
                    Assignment (dari Master Data)
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1.5">Vehicle</label>
                        <div class="relative">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-base pointer-events-none">🚛</span>
                            <select name="vehicle_id"
                                    class="w-full pl-9 pr-8 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-none focus:border-green-500 focus:ring-2 focus:ring-green-500/20 bg-white appearance-none transition-all">
                                <option value="">Select a vehicle...</option>
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
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1.5">Driver</label>
                        <div class="relative">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-base pointer-events-none">👤</span>
                            <select name="driver_id"
                                    class="w-full pl-9 pr-8 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-none focus:border-green-500 focus:ring-2 focus:ring-green-500/20 bg-white appearance-none transition-all">
                                <option value="">Select a driver...</option>
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
            </div>

            {{-- Section 3: Technical (collapsible) --}}
            <div class="mb-6" x-data="{ open: false }">
                <button type="button" @click="open = !open"
                        class="flex items-center gap-2 text-xs font-bold text-gray-400 uppercase tracking-wider mb-3 hover:text-green-600 transition-colors">
                    <svg class="w-3 h-3 transition-transform duration-200" :class="open ? 'rotate-90' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                    </svg>
                    Info Teknis — Opsional
                </button>
                <div x-show="open" x-cloak class="grid grid-cols-2 gap-4 pt-1">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1.5">IMEI</label>
                        <input type="text" name="imei" value="{{ old('imei') }}" placeholder="352093081836005"
                               class="w-full px-4 py-2.5 text-sm font-mono border border-gray-200 rounded-xl focus:outline-none focus:border-green-500 focus:ring-2 focus:ring-green-500/20">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1.5">ICCID</label>
                        <input type="text" name="iccid" value="{{ old('iccid') }}" placeholder="8962034001234567894F"
                               class="w-full px-4 py-2.5 text-sm font-mono border border-gray-200 rounded-xl focus:outline-none focus:border-green-500 focus:ring-2 focus:ring-green-500/20">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1.5">APN</label>
                        <input type="text" name="apn" value="{{ old('apn') }}" placeholder="telkomsel"
                               class="w-full px-4 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-none focus:border-green-500 focus:ring-2 focus:ring-green-500/20">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1.5">Network Operator</label>
                        <input type="text" name="network_operator" value="{{ old('network_operator') }}" placeholder="Telkomsel"
                               class="w-full px-4 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-none focus:border-green-500 focus:ring-2 focus:ring-green-500/20">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1.5">Firmware Version</label>
                        <input type="text" name="firmware_version" value="{{ old('firmware_version') }}" placeholder="v1.0.0"
                               class="w-full px-4 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-none focus:border-green-500 focus:ring-2 focus:ring-green-500/20">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1.5">AI Model Version (OpenMV)</label>
                        <input type="text" name="ai_model_version" value="{{ old('ai_model_version') }}" placeholder="drowsy-v2.1"
                               class="w-full px-4 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-none focus:border-green-500 focus:ring-2 focus:ring-green-500/20">
                    </div>
                </div>
            </div>

            {{-- Notes --}}
            <div class="mb-6">
                <label class="block text-sm font-semibold text-gray-700 mb-1.5">Catatan</label>
                <textarea name="notes" rows="2" placeholder="Catatan tambahan (opsional)..."
                          class="w-full px-4 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-none focus:border-green-500 focus:ring-2 focus:ring-green-500/20 resize-none transition-all">{{ old('notes') }}</textarea>
            </div>

            {{-- Buttons --}}
            <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-100">
                <a href="{{ route('devices.index') }}"
                   class="px-5 py-2.5 text-sm font-semibold text-gray-600 border border-gray-200 rounded-xl hover:bg-gray-50 transition-all">
                    Cancel
                </a>
                <button type="submit"
                        class="px-6 py-2.5 bg-green-500 hover:bg-green-600 text-white text-sm font-bold rounded-xl transition-all shadow-md hover:shadow-green-200">
                    Register Device
                </button>
            </div>
        </form>
    </div>
</div>
@endsection