@extends('layouts.app')
@section('title', 'Edit Kendaraan — Greenfields')

@section('content')
<div class="p-6 max-w-xl mx-auto">
    <div class="flex items-center gap-2 text-sm text-gray-400 mb-6">
        <a href="{{ route('master.vehicles.index') }}" class="hover:text-green-500">Master Kendaraan</a>
        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
        </svg>
        <span class="text-gray-700 font-semibold">Edit: {{ $vehicle->name }}</span>
    </div>

    <div class="card p-6">
        <h2 class="text-lg font-extrabold text-gray-900 mb-5">Edit Data Kendaraan</h2>

        <form action="{{ route('master.vehicles.update', $vehicle) }}" method="POST">
            @csrf @method('PUT')

            <div class="grid grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">Kode Kendaraan <span class="text-red-400">*</span></label>
                    <input type="text" name="vehicle_code" value="{{ old('vehicle_code', $vehicle->vehicle_code) }}"
                           class="w-full px-4 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-none focus:border-green-500 focus:ring-2 focus:ring-green-500/20">
                    @error('vehicle_code') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">Plat Nomor <span class="text-red-400">*</span></label>
                    <input type="text" name="license_plate" value="{{ old('license_plate', $vehicle->license_plate) }}"
                           class="w-full px-4 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-none focus:border-green-500 focus:ring-2 focus:ring-green-500/20">
                    @error('license_plate') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="mb-4">
                <label class="block text-sm font-semibold text-gray-700 mb-1.5">Nama Kendaraan <span class="text-red-400">*</span></label>
                <input type="text" name="name" value="{{ old('name', $vehicle->name) }}"
                       class="w-full px-4 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-none focus:border-green-500 focus:ring-2 focus:ring-green-500/20">
                @error('name') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="grid grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">Tipe</label>
                    <input type="text" name="vehicle_type" value="{{ old('vehicle_type', $vehicle->vehicle_type) }}"
                           class="w-full px-4 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-none focus:border-green-500 focus:ring-2 focus:ring-green-500/20">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">Status</label>
                    <select name="status" class="w-full px-4 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-none focus:border-green-500 bg-white">
                        @foreach(['idle' => 'Idle', 'moving' => 'Moving', 'offline' => 'Offline'] as $val => $lbl)
                        <option value="{{ $val }}" {{ old('status', $vehicle->status) === $val ? 'selected' : '' }}>{{ $lbl }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            {{-- Brand, Model, Year --}}
            <div class="grid grid-cols-3 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">Brand</label>
                    <input type="text" name="brand" value="{{ old('brand', $vehicle->brand ?? '') }}"
                        placeholder="Mitsubishi, Isuzu..."
                        class="w-full px-4 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-none focus:border-green-500 focus:ring-2 focus:ring-green-500/20">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">Model</label>
                    <input type="text" name="model" value="{{ old('model', $vehicle->model ?? '') }}"
                        placeholder="Canter, Elf..."
                        class="w-full px-4 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-none focus:border-green-500 focus:ring-2 focus:ring-green-500/20">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">Tahun</label>
                    <input type="number" name="year" value="{{ old('year', $vehicle->year ?? '') }}"
                        placeholder="2022" min="2000" max="2030"
                        class="w-full px-4 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-none focus:border-green-500 focus:ring-2 focus:ring-green-500/20">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4 mb-6">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">Warna</label>
                    <input type="text" name="color" value="{{ old('color', $vehicle->color) }}"
                           class="w-full px-4 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-none focus:border-green-500 focus:ring-2 focus:ring-green-500/20">
                </div>
                <!-- <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">Kapasitas (Liter)</label>
                    <input type="number" name="capacity_liters" value="{{ old('capacity_liters', $vehicle->capacity_liters) }}"
                           class="w-full px-4 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-none focus:border-green-500 focus:ring-2 focus:ring-green-500/20">
                </div> -->
            </div>

            <div class="mb-6">
                <label class="block text-sm font-semibold text-gray-700 mb-1.5">Catatan</label>
                <textarea name="notes" rows="2" class="w-full px-4 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-none focus:border-green-500 focus:ring-2 focus:ring-green-500/20 resize-none">{{ old('notes', $vehicle->notes) }}</textarea>
            </div>

            <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-100">
                <a href="{{ route('master.vehicles.index') }}" class="px-5 py-2.5 text-sm font-semibold text-gray-600 border border-gray-200 rounded-xl hover:bg-gray-50 transition-all">Batal</a>
                <button type="submit" class="px-6 py-2.5 bg-green-500 hover:bg-green-600 text-white text-sm font-bold rounded-xl transition-all shadow-md hover:shadow-green-200">Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>
@endsection