@extends('layouts.app')
@section('title', 'Tambah Kendaraan — Greenfields')

@section('content')
<div class="p-6 max-w-xl mx-auto">
    <div class="flex items-center gap-2 text-sm text-gray-400 mb-6">
        <a href="{{ route('master.vehicles.index') }}" class="hover:text-green-500">Master Kendaraan</a>
        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
        </svg>
        <span class="text-gray-700 font-semibold">Tambah Kendaraan</span>
    </div>

    <div class="card p-6">
        <h2 class="text-lg font-extrabold text-gray-900 mb-5">Tambah Kendaraan Baru</h2>

        <form action="{{ route('master.vehicles.store') }}" method="POST">
            @csrf

            <div class="grid grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">Kode Kendaraan</label>
                    <div style="position:relative;">
                        <input type="text" value="{{ $nextVehicleCode }}" readonly tabindex="-1"
                               class="w-full py-2.5 text-sm font-semibold border border-gray-200 rounded-xl bg-gray-50 text-gray-600 cursor-not-allowed select-none"
                               style="padding-left:1rem;padding-right:2.5rem;">
                        <svg style="position:absolute;right:12px;top:50%;transform:translateY(-50%);width:16px;height:16px;color:#9ca3af;flex-shrink:0;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-12v3H8V7a4 4 0 118 0z"/>
                        </svg>
                    </div>
                    <p class="text-xs text-gray-400 mt-1">Otomatis mengikuti kode terakhir — tidak bisa diubah manual.</p>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">Plat Nomor <span class="text-red-400">*</span></label>
                    <input type="text" name="license_plate" value="{{ old('license_plate') }}" placeholder="N-1240-GF"
                           class="w-full px-4 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-none focus:border-green-500 focus:ring-2 focus:ring-green-500/20 @error('license_plate') border-red-400 @enderror">
                    @error('license_plate') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="mb-4">
                <label class="block text-sm font-semibold text-gray-700 mb-1.5">Nama Kendaraan <span class="text-red-400">*</span></label>
                <input type="text" name="name" value="{{ old('name') }}" placeholder="Truck Susu GF-06"
                       class="w-full px-4 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-none focus:border-green-500 focus:ring-2 focus:ring-green-500/20 @error('name') border-red-400 @enderror">
                @error('name') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="mb-4">
                <label class="block text-sm font-semibold text-gray-700 mb-1.5">Tipe Kendaraan <span class="text-red-400">*</span></label>
                <input type="text" name="vehicle_type" value="{{ old('vehicle_type') }}" placeholder="Truk Susu, Mobil Dinas..."
                       class="w-full px-4 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-none focus:border-green-500 focus:ring-2 focus:ring-green-500/20">
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
                    <input type="text" name="color" value="{{ old('color') }}" placeholder="Putih"
                           class="w-full px-4 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-none focus:border-green-500 focus:ring-2 focus:ring-green-500/20">
                </div>
                <!-- <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">Kapasitas (Liter)</label>
                    <input type="number" name="capacity_liters" value="{{ old('capacity_liters') }}" placeholder="5000"
                           class="w-full px-4 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-none focus:border-green-500 focus:ring-2 focus:ring-green-500/20">
                </div> -->
            </div>

            <div class="mb-6">
                <label class="block text-sm font-semibold text-gray-700 mb-1.5">Catatan</label>
                <textarea name="notes" rows="2" class="w-full px-4 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-none focus:border-green-500 focus:ring-2 focus:ring-green-500/20 resize-none">{{ old('notes') }}</textarea>
            </div>

            <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-100">
                <a href="{{ route('master.vehicles.index') }}" class="px-5 py-2.5 text-sm font-semibold text-gray-600 border border-gray-200 rounded-xl hover:bg-gray-50 transition-all">Batal</a>
                <button type="submit" class="px-6 py-2.5 bg-green-500 hover:bg-green-600 text-white text-sm font-bold rounded-xl transition-all shadow-md hover:shadow-green-200">Simpan Kendaraan</button>
            </div>
        </form>
    </div>
</div>
@endsection