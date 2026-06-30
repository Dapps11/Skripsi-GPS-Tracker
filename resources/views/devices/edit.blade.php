@extends('layouts.app')
@section('title', 'Edit Device — Greenfields')

@section('content')
<div class="p-6 max-w-2xl mx-auto">

    <div class="flex items-center gap-2 text-sm text-gray-400 mb-6">
        <a href="{{ route('devices.index') }}" class="hover:text-green-500 transition-colors">Device</a>
        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
        </svg>
        <span class="text-gray-700 font-semibold">Edit: {{ $device->device_id }}</span>
    </div>

    <div class="card p-6">
        <h2 class="text-lg font-extrabold text-gray-900 mb-1">Edit Device</h2>
        <p class="text-sm text-gray-400 mb-6">Update konfigurasi device <strong>{{ $device->device_id }}</strong>.</p>

        <form action="{{ route('devices.update', $device) }}" method="POST">
            @csrf @method('PUT')

            <div class="mb-6">
                <div class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-3 pb-2 border-b border-gray-100">Device Information</div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1.5">Device ID <span class="text-red-400">*</span></label>
                        <input type="text" name="device_id" value="{{ old('device_id', $device->device_id) }}"
                               class="w-full px-4 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-none focus:border-green-500 focus:ring-2 focus:ring-green-500/20 @error('device_id') border-red-400 @enderror">
                        @error('device_id') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1.5">Device Type <span class="text-red-400">*</span></label>
                        <select name="device_type" class="w-full px-4 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-none focus:border-green-500 bg-white">
                            <option value="sim7600"  {{ old('device_type', $device->device_type) === 'sim7600'  ? 'selected' : '' }}>SIM7600 (GPS Tracker)</option>
                            <option value="raspberry" {{ old('device_type', $device->device_type) === 'raspberry' ? 'selected' : '' }}>Raspberry Pi 5</option>
                            <option value="combined" {{ old('device_type', $device->device_type) === 'combined' ? 'selected' : '' }}>Combined</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="mb-6">
                <div class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-3 pb-2 border-b border-gray-100">Assignment</div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1.5">Vehicle</label>
                        <div class="relative">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-base pointer-events-none"></span>
                            <select name="vehicle_id" class="w-full pl-9 pr-8 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-none focus:border-green-500 bg-white appearance-none">
                                <option value="">— Unassigned —</option>
                                @foreach($vehicles as $v)
                                <option value="{{ $v->id }}" {{ old('vehicle_id', $device->vehicle_id) == $v->id ? 'selected' : '' }}>
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
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-base pointer-events-none"></span>
                            <select name="driver_id" class="w-full pl-9 pr-8 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-none focus:border-green-500 bg-white appearance-none">
                                <option value="">— Unassigned —</option>
                                @foreach($drivers as $d)
                                <option value="{{ $d->id }}" {{ old('driver_id', $device->driver_id) == $d->id ? 'selected' : '' }}>
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

            <div class="mb-6">
                <div class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-3 pb-2 border-b border-gray-100">Info Teknis</div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1.5">IMEI</label>
                        <input type="text" name="imei" value="{{ old('imei', $device->imei) }}"
                               class="w-full px-4 py-2.5 text-sm font-mono border border-gray-200 rounded-xl focus:outline-none focus:border-green-500 focus:ring-2 focus:ring-green-500/20">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1.5">ICCID</label>
                        <input type="text" name="iccid" value="{{ old('iccid', $device->iccid) }}"
                               class="w-full px-4 py-2.5 text-sm font-mono border border-gray-200 rounded-xl focus:outline-none focus:border-green-500 focus:ring-2 focus:ring-green-500/20">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1.5">APN</label>
                        <input type="text" name="apn" value="{{ old('apn', $device->apn) }}"
                               class="w-full px-4 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-none focus:border-green-500 focus:ring-2 focus:ring-green-500/20">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1.5">Network Operator</label>
                        <input type="text" name="network_operator" value="{{ old('network_operator', $device->network_operator) }}"
                               class="w-full px-4 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-none focus:border-green-500 focus:ring-2 focus:ring-green-500/20">
                    </div>
                </div>
            </div>

            <div class="mb-6">
                <label class="block text-sm font-semibold text-gray-700 mb-1.5">Catatan</label>
                <textarea name="notes" rows="2" class="w-full px-4 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-none focus:border-green-500 focus:ring-2 focus:ring-green-500/20 resize-none">{{ old('notes', $device->notes) }}</textarea>
            </div>

            <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-100">
                <a href="{{ route('devices.index') }}" class="px-5 py-2.5 text-sm font-semibold text-gray-600 border border-gray-200 rounded-xl hover:bg-gray-50 transition-all">
                    Cancel
                </a>
                <button type="submit" class="px-6 py-2.5 bg-green-500 hover:bg-green-600 text-white text-sm font-bold rounded-xl transition-all shadow-md hover:shadow-green-200">
                    Save Changes
                </button>
            </div>
        </form>
    </div>
</div>
@endsection