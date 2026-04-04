@extends('layouts.app')
@section('title', 'Master Kendaraan — Greenfields')

@section('content')
<div class="p-6">
    <div class="flex items-start justify-between mb-6">
        <div>
            <h1 class="text-2xl font-extrabold text-gray-900">Master Kendaraan</h1>
            <p class="text-sm text-gray-400 mt-1">Kelola data kendaraan — sumber dropdown form Add Device.</p>
        </div>
        <a href="{{ route('master.vehicles.create') }}"
           class="flex items-center gap-2 px-4 py-2.5 bg-green-500 hover:bg-green-600 text-white text-sm font-bold rounded-xl transition-all shadow-md hover:shadow-green-200">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
            </svg>
            Tambah Kendaraan
        </a>
    </div>

    <div class="card overflow-hidden">
        <table class="w-full">
            <thead>
                <tr class="border-b border-gray-100">
                    <th class="text-left text-xs font-bold text-gray-400 uppercase tracking-wider px-5 py-4">Kode</th>
                    <th class="text-left text-xs font-bold text-gray-400 uppercase tracking-wider px-5 py-4">Nama Kendaraan</th>
                    <th class="text-left text-xs font-bold text-gray-400 uppercase tracking-wider px-5 py-4">Plat Nomor</th>
                    <th class="text-left text-xs font-bold text-gray-400 uppercase tracking-wider px-5 py-4">Tipe</th>
                    <th class="text-left text-xs font-bold text-gray-400 uppercase tracking-wider px-5 py-4">Kapasitas</th>
                    <th class="text-left text-xs font-bold text-gray-400 uppercase tracking-wider px-5 py-4">Status</th>
                    <th class="text-right text-xs font-bold text-gray-400 uppercase tracking-wider px-5 py-4">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @forelse($vehicles as $vehicle)
                <tr class="hover:bg-gray-50/70 transition-colors">
                    <td class="px-5 py-4 text-sm font-mono font-semibold text-gray-600">{{ $vehicle->vehicle_code }}</td>
                    <td class="px-5 py-4">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 bg-green-50 rounded-lg flex items-center justify-center text-base flex-shrink-0">🚛</div>
                            <div>
                                <div class="text-sm font-semibold text-gray-900">{{ $vehicle->name }}</div>
                                @if($vehicle->brand)
                                <div class="text-xs text-gray-400">{{ $vehicle->brand }} {{ $vehicle->model }} {{ $vehicle->year }}</div>
                                @endif
                            </div>
                        </div>
                    </td>
                    <td class="px-5 py-4">
                        <span style="padding:4px 10px;background:#111827;color:white;border-radius:8px;font-size:12px;font-weight:700;letter-spacing:0.05em">
                            {{ $vehicle->license_plate }}
                        </span>
                    </td>
                    <td class="px-5 py-4 text-sm text-gray-500">{{ $vehicle->vehicle_type }}</td>
                    <td class="px-5 py-4 text-sm text-gray-500">
                        {{ $vehicle->capacity_liters ? number_format($vehicle->capacity_liters) . ' L' : '—' }}
                    </td>
                    <td class="px-5 py-4">
                        @if($vehicle->status === 'moving')
                            <span class="badge-moving"><span style="width:6px;height:6px;background:#22c55e;border-radius:50%;animation:pulse 2s infinite"></span>Moving</span>
                        @elseif($vehicle->status === 'idle')
                            <span class="badge-idle"><span style="width:6px;height:6px;background:#f97316;border-radius:50%"></span>Idle</span>
                        @else
                            <span class="badge-offline"><span style="width:6px;height:6px;background:#ef4444;border-radius:50%"></span>Offline</span>
                        @endif
                    </td>
                    <td class="px-5 py-4">
                        <div class="flex items-center justify-end gap-1">
                            <a href="{{ route('master.vehicles.edit', $vehicle) }}"
                               class="w-8 h-8 flex items-center justify-center text-gray-400 hover:text-green-600 hover:bg-green-50 rounded-lg transition-all">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                </svg>
                            </a>
                            <form action="{{ route('master.vehicles.destroy', $vehicle) }}" method="POST"
                                  onsubmit="return confirm('Hapus kendaraan {{ $vehicle->name }}?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="w-8 h-8 flex items-center justify-center text-gray-400 hover:text-red-500 hover:bg-red-50 rounded-lg transition-all">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                    </svg>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="px-5 py-12 text-center text-sm text-gray-400">
                        Belum ada data kendaraan.
                        <a href="{{ route('master.vehicles.create') }}" class="text-green-500 font-semibold">Tambah sekarang →</a>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
        <div class="px-5 py-3 border-t border-gray-100">{{ $vehicles->links() }}</div>
    </div>
</div>
@endsection