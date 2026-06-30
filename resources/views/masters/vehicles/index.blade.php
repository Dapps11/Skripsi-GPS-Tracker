@extends('layouts.app')
@section('title', 'Master Kendaraan — Greenfields')

@section('content')
<div class="p-4 md:p-6">
    <div class="flex items-start justify-between mb-4 md:mb-6 gap-3">
        <div>
            <h1 class="text-xl md:text-2xl font-extrabold text-gray-900">Master Kendaraan</h1>
            <p class="text-xs md:text-sm text-gray-400 mt-1">Kelola data kendaraan.</p>
        </div>
        <a href="{{ route('master.vehicles.create') }}"
           class="flex items-center gap-2 px-3 md:px-4 py-2 md:py-2.5 bg-green-500 hover:bg-green-600 text-white text-sm font-bold rounded-xl transition-all shadow-md flex-shrink-0">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
            </svg>
            <span class="hidden sm:inline">Tambah Kendaraan</span>
            <span class="sm:hidden">Tambah</span>
        </a>
    </div>

    <div class="card overflow-hidden">
        <div class="table-responsive">
            <table class="w-full" style="min-width:560px;">
                <thead>
                    <tr class="border-b border-gray-100">
                        <th class="text-left text-xs font-bold text-gray-400 uppercase tracking-wider px-4 py-3">Kendaraan</th>
                        <th class="text-left text-xs font-bold text-gray-400 uppercase tracking-wider px-4 py-3 hidden sm:table-cell">Plat</th>
                        <th class="text-left text-xs font-bold text-gray-400 uppercase tracking-wider px-4 py-3 hidden md:table-cell">Tipe</th>
                        <th class="text-left text-xs font-bold text-gray-400 uppercase tracking-wider px-4 py-3">Status</th>
                        <th class="text-right text-xs font-bold text-gray-400 uppercase tracking-wider px-4 py-3">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @forelse($vehicles as $vehicle)
                    <tr class="hover:bg-gray-50/70 transition-colors">
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-2.5">
                                <div class="w-8 h-8 bg-green-50 rounded-lg flex items-center justify-center text-base flex-shrink-0">
                                    🚛
                                </div>
                                <div>
                                    <div class="text-sm font-semibold text-gray-900">{{ $vehicle->name }}</div>
                                    <div class="text-xs text-gray-400 font-mono">{{ $vehicle->vehicle_code }}</div>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-3 hidden sm:table-cell">
                            <span style="padding:3px 8px;background:#111827;color:white;border-radius:6px;font-size:11px;font-weight:700;letter-spacing:.05em">
                                {{ $vehicle->license_plate }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-500 hidden md:table-cell">{{ $vehicle->vehicle_type }}</td>
                        <td class="px-4 py-3">
                            @if($vehicle->status === 'moving')
                                <span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-green-100 text-green-700">Moving</span>
                            @elseif($vehicle->status === 'idle')
                                <span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-orange-100 text-orange-700">Idle</span>
                            @else
                                <span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-red-100 text-red-700">Offline</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-1">
                                <a href="{{ route('master.vehicles.history', $vehicle) }}"
                                   title="Riwayat Harian"
                                   class="w-7 h-7 flex items-center justify-center text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-all">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/>
                                    </svg>
                                </a>
                                <a href="{{ route('master.vehicles.edit', $vehicle) }}"
                                   class="w-7 h-7 flex items-center justify-center text-gray-400 hover:text-green-600 hover:bg-green-50 rounded-lg transition-all">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                    </svg>
                                </a>
                                <form action="{{ route('master.vehicles.destroy', $vehicle) }}" method="POST"
                                      onsubmit="return confirm('Hapus kendaraan {{ $vehicle->name }}?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="w-7 h-7 flex items-center justify-center text-gray-400 hover:text-red-500 hover:bg-red-50 rounded-lg transition-all">
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                        </svg>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="px-5 py-12 text-center text-sm text-gray-400">
                            Belum ada kendaraan. <a href="{{ route('master.vehicles.create') }}" class="text-green-500 font-semibold">Tambah sekarang →</a>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-4 py-3 border-t border-gray-100">{{ $vehicles->links() }}</div>
    </div>
</div>
@endsection