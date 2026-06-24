@extends('layouts.app')
@section('title', 'Tambah Supir — Greenfields')

@section('content')
<div class="p-6 max-w-xl mx-auto">
    <div class="flex items-center gap-2 text-sm text-gray-400 mb-6">
        <a href="{{ route('master.drivers.index') }}" class="hover:text-green-500">Master Supir</a>
        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
        </svg>
        <span class="text-gray-700 font-semibold">Tambah Supir</span>
    </div>

    <div class="card p-6">
        <h2 class="text-lg font-extrabold text-gray-900 mb-5">Tambah Supir Baru</h2>

        <form action="{{ route('master.drivers.store') }}" method="POST">
            @csrf
            <div class="grid grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">Kode Supir</label>
                    <div class="relative">
                        <input type="text" value="{{ $nextDriverCode }}" readonly tabindex="-1"
                               class="w-full pl-4 pr-10 py-2.5 text-sm font-semibold border border-gray-200 rounded-xl bg-gray-50 text-gray-600 cursor-not-allowed select-none">
                        <svg class="absolute right-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-12v3H8V7a4 4 0 118 0z"/>
                        </svg>
                    </div>
                    <p class="text-xs text-gray-400 mt-1">Otomatis mengikuti kode terakhir — tidak bisa diubah manual.</p>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">Status <span class="text-red-400">*</span></label>
                    <select name="status" class="w-full px-4 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-none focus:border-green-500 bg-white">
                        <option value="available" {{ old('status') === 'available' ? 'selected' : '' }}>Available</option>
                        <option value="on_duty"   {{ old('status') === 'on_duty'   ? 'selected' : '' }}>On Duty</option>
                        <option value="off_duty"  {{ old('status') === 'off_duty'  ? 'selected' : '' }}>Off Duty</option>
                        <option value="inactive"  {{ old('status') === 'inactive'  ? 'selected' : '' }}>Inactive</option>
                    </select>
                </div>
            </div>

            <div class="mb-4">
                <label class="block text-sm font-semibold text-gray-700 mb-1.5">Nama Lengkap <span class="text-red-400">*</span></label>
                <input type="text" name="full_name" value="{{ old('full_name') }}" placeholder="Ahmad Fauzi"
                       class="w-full px-4 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-none focus:border-green-500 focus:ring-2 focus:ring-green-500/20 @error('full_name') border-red-400 @enderror">
                @error('full_name') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="grid grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">No. HP</label>
                    <input type="text" name="phone" value="{{ old('phone') }}" placeholder="081234567895"
                           class="w-full px-4 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-none focus:border-green-500 focus:ring-2 focus:ring-green-500/20">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">No. SIM</label>
                    <input type="text" name="license_number" value="{{ old('license_number') }}" placeholder="SIM-A-001239"
                           class="w-full px-4 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-none focus:border-green-500 focus:ring-2 focus:ring-green-500/20">
                </div>
            </div>

            <div class="mb-4">
                <label class="block text-sm font-semibold text-gray-700 mb-1.5">Masa Berlaku SIM</label>
                <input type="date" name="license_expiry" value="{{ old('license_expiry') }}"
                       class="w-full px-4 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-none focus:border-green-500 focus:ring-2 focus:ring-green-500/20">
            </div>

            <div class="mb-4">
                <label class="block text-sm font-semibold text-gray-700 mb-1.5">Alamat</label>
                <textarea name="address" rows="2" placeholder="Jl. ..."
                          class="w-full px-4 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-none focus:border-green-500 focus:ring-2 focus:ring-green-500/20 resize-none">{{ old('address') }}</textarea>
            </div>

            <div class="mb-6">
                <label class="block text-sm font-semibold text-gray-700 mb-1.5">Catatan</label>
                <textarea name="notes" rows="2" class="w-full px-4 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-none focus:border-green-500 focus:ring-2 focus:ring-green-500/20 resize-none">{{ old('notes') }}</textarea>
            </div>

            <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-100">
                <a href="{{ route('master.drivers.index') }}" class="px-5 py-2.5 text-sm font-semibold text-gray-600 border border-gray-200 rounded-xl hover:bg-gray-50 transition-all">Batal</a>
                <button type="submit" class="px-6 py-2.5 bg-green-500 hover:bg-green-600 text-white text-sm font-bold rounded-xl transition-all shadow-md hover:shadow-green-200">Simpan Supir</button>
            </div>
        </form>
    </div>
</div>
@endsection