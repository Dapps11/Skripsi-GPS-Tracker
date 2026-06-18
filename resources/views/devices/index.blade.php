@extends('layouts.app')
@section('title', 'Device Management — Greenfields')

@section('content')
<div class="p-6">

    <div class="flex items-start justify-between mb-6">
        <div>
            <h1 class="text-2xl font-extrabold text-gray-900">Fleet Asset Inventory</h1>
            <p class="text-sm text-gray-400 mt-1">Real-time monitoring and configuration for SIM7600 & OpenMV units.</p>
        </div>
        <a href="{{ route('devices.create') }}"
           class="flex items-center gap-2 px-4 py-2.5 bg-green-500 hover:bg-green-600 text-white text-sm font-bold rounded-xl transition-all shadow-md hover:shadow-lg hover:shadow-green-200">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
            </svg>
            Tambah Device
        </a>
    </div>

    {{-- Summary Cards --}}
    <div class="grid grid-cols-4 gap-4 mb-6">
        <div class="card p-5">
            <div class="flex items-center justify-between mb-2">
                <span class="text-sm text-gray-500">Total Device</span>
                <div class="w-9 h-9 bg-blue-50 rounded-lg flex items-center justify-center">
                    <svg class="w-5 h-5 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 3H5a2 2 0 00-2 2v4m6-6h10a2 2 0 012 2v4M9 3v18m0 0h10a2 2 0 002-2V9M9 21H5a2 2 0 01-2-2V9m0 0h18"/>
                    </svg>
                </div>
            </div>
            <div class="text-3xl font-extrabold text-gray-900">{{ $summary->total_devices ?? 0 }}</div>
        </div>
        <div class="card p-5">
            <div class="flex items-center justify-between mb-2">
                <span class="text-sm text-gray-500">Online</span>
                <div class="w-9 h-9 bg-green-50 rounded-lg flex items-center justify-center">
                    <svg class="w-5 h-5 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                    </svg>
                </div>
            </div>
            <div class="text-3xl font-extrabold text-gray-900">{{ $summary->online ?? 0 }}</div>
        </div>
        <div class="card p-5">
            <div class="flex items-center justify-between mb-2">
                <span class="text-sm text-gray-500">Idle</span>
                <div class="w-9 h-9 bg-orange-50 rounded-lg flex items-center justify-center">
                    <svg class="w-5 h-5 text-orange-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
            </div>
            <div class="text-3xl font-extrabold text-gray-900">{{ $summary->idle ?? 0 }}</div>
        </div>
        <div class="card p-5">
            <div class="flex items-center justify-between mb-2">
                <span class="text-sm text-gray-500">Offline</span>
                <div class="w-9 h-9 bg-red-50 rounded-lg flex items-center justify-center">
                    <svg class="w-5 h-5 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
                    </svg>
                </div>
            </div>
            <div class="text-3xl font-extrabold text-gray-900">{{ $summary->offline ?? 0 }}</div>
        </div>
    </div>

    {{-- Table --}}
    <div class="card overflow-hidden">
        <table class="w-full">
            <thead>
                <tr class="border-b border-gray-100">
                    <th class="text-left text-xs font-bold text-gray-400 uppercase tracking-wider px-5 py-4">Device ID</th>
                    <th class="text-left text-xs font-bold text-gray-400 uppercase tracking-wider px-5 py-4">Truck Assigned</th>
                    <th class="text-left text-xs font-bold text-gray-400 uppercase tracking-wider px-5 py-4">Type</th>
                    <th class="text-left text-xs font-bold text-gray-400 uppercase tracking-wider px-5 py-4">Status</th>
                    <th class="text-left text-xs font-bold text-gray-400 uppercase tracking-wider px-5 py-4">Last Heartbeat</th>
                    <th class="text-right text-xs font-bold text-gray-400 uppercase tracking-wider px-5 py-4">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @forelse($devices as $device)
                <tr class="hover:bg-gray-50/70 transition-colors">
                    <td class="px-5 py-4">
                        <div class="font-bold text-gray-900 text-sm">{{ $device->device_id }}</div>
                        @if($device->imei)
                        <div class="text-xs text-gray-400 font-mono">{{ $device->imei }}</div>
                        @endif
                    </td>
                    <td class="px-5 py-4 text-sm text-gray-700">
                        @if($device->vehicle)
                            {{ $device->vehicle->name }}
                            @if($device->driver)
                            <span class="text-gray-400">({{ $device->driver->full_name }})</span>
                            @endif
                        @else
                            <span class="text-gray-300 italic text-xs">Unassigned</span>
                        @endif
                    </td>
                    <td class="px-5 py-4">
                        <span class="px-2 py-0.5 bg-gray-100 text-gray-600 rounded-full text-xs font-semibold uppercase">
                            {{ $device->device_type }}
                        </span>
                    </td>
                    <td class="px-5 py-4">
                        @if($device->status === 'online')
                            <span class="badge-online">
                                <span style="width:6px;height:6px;background:#22c55e;border-radius:50%;animation:pulse 2s infinite"></span>
                                Online
                            </span>
                        @elseif($device->status === 'idle')
                            <span class="badge-idle">
                                <span style="width:6px;height:6px;background:#f97316;border-radius:50%"></span>
                                Idle
                            </span>
                        @else
                            <span class="badge-offline">
                                <span style="width:6px;height:6px;background:#ef4444;border-radius:50%"></span>
                                Offline
                            </span>
                        @endif
                    </td>
                    <td class="px-5 py-4 text-sm text-gray-500">
                        {{ $device->last_heartbeat ? $device->last_heartbeat->diffForHumans() : '—' }}
                    </td>
                    <td class="px-5 py-4">
                        <div class="flex items-center justify-end gap-1">
                            <a href="{{ route('devices.edit', $device) }}"
                               class="w-8 h-8 flex items-center justify-center text-gray-400 hover:text-green-600 hover:bg-green-50 rounded-lg transition-all">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                </svg>
                            </a>
                            <form action="{{ route('devices.destroy', $device) }}" method="POST"
                                  onsubmit="return confirm('Hapus device {{ $device->device_id }}?')">
                                @csrf @method('DELETE')
                                <button type="submit"
                                        class="w-8 h-8 flex items-center justify-center text-gray-400 hover:text-red-500 hover:bg-red-50 rounded-lg transition-all">
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
                    <td colspan="6" class="px-5 py-12 text-center">
                        <div class="text-gray-400 text-sm mb-2">Belum ada device terdaftar.</div>
                        <a href="{{ route('devices.create') }}" class="text-green-500 font-semibold text-sm hover:text-green-700">+ Tambah Device Baru</a>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
        <div class="px-5 py-3 border-t border-gray-100 flex items-center justify-between">
            <span class="text-xs text-gray-400">
                Showing {{ $devices->firstItem() }}–{{ $devices->lastItem() }} of {{ $devices->total() }} devices
            </span>
            {{ $devices->links() }}
        </div>
    </div>
</div>
@endsection