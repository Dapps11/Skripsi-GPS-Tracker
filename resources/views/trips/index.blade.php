@extends('layouts.app')
@section('title', 'Trip Management — Greenfields')

@section('content')
<div class="p-6">

    <div class="flex items-start justify-between mb-6">
        <div>
            <h1 class="text-2xl font-extrabold text-gray-900">Trip Management</h1>
            <p class="text-sm text-gray-400 mt-1">Kelola rute perjalanan kendaraan — origin & destination.</p>
        </div>
        <a href="{{ route('trips.create') }}"
           class="flex items-center gap-2 px-4 py-2.5 bg-green-500 hover:bg-green-600 text-white text-sm font-bold rounded-xl transition-all shadow-md hover:shadow-green-200">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
            </svg>
            Tambah Trip
        </a>
    </div>

    {{-- Summary --}}
    <div class="grid grid-cols-3 gap-4 mb-6">
        <div class="card p-5">
            <div class="flex items-center justify-between mb-2">
                <span class="text-sm text-gray-500">Planned</span>
                <div class="w-9 h-9 bg-blue-50 rounded-lg flex items-center justify-content-center justify-center">
                    <svg class="w-5 h-5 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                </div>
            </div>
            <div class="text-3xl font-extrabold text-gray-900">{{ $summary['planned'] }}</div>
        </div>
        <div class="card p-5">
            <div class="flex items-center justify-between mb-2">
                <span class="text-sm text-gray-500">In Progress</span>
                <div class="w-9 h-9 bg-green-50 rounded-lg flex items-center justify-center">
                    <svg class="w-5 h-5 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                    </svg>
                </div>
            </div>
            <div class="text-3xl font-extrabold text-gray-900">{{ $summary['in_progress'] }}</div>
        </div>
        <div class="card p-5">
            <div class="flex items-center justify-between mb-2">
                <span class="text-sm text-gray-500">Completed</span>
                <div class="w-9 h-9 bg-gray-50 rounded-lg flex items-center justify-center">
                    <svg class="w-5 h-5 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                    </svg>
                </div>
            </div>
            <div class="text-3xl font-extrabold text-gray-900">{{ $summary['completed'] }}</div>
        </div>
    </div>

    {{-- Table --}}
    <div class="card overflow-hidden">
        <table class="w-full">
            <thead>
                <tr class="border-b border-gray-100">
                    <th class="text-left text-xs font-bold text-gray-400 uppercase tracking-wider px-5 py-4">Trip Code</th>
                    <th class="text-left text-xs font-bold text-gray-400 uppercase tracking-wider px-5 py-4">Kendaraan</th>
                    <th class="text-left text-xs font-bold text-gray-400 uppercase tracking-wider px-5 py-4">Rute</th>
                    <th class="text-left text-xs font-bold text-gray-400 uppercase tracking-wider px-5 py-4">Berangkat</th>
                    <th class="text-left text-xs font-bold text-gray-400 uppercase tracking-wider px-5 py-4">Status</th>
                    <th class="text-right text-xs font-bold text-gray-400 uppercase tracking-wider px-5 py-4">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @forelse($trips as $trip)
                <tr class="hover:bg-gray-50/70 transition-colors">
                    <td class="px-5 py-4">
                        <div class="font-bold text-gray-900 text-sm font-mono">{{ $trip->trip_code }}</div>
                        @if($trip->driver)
                        <div class="text-xs text-gray-400 mt-0.5">👤 {{ $trip->driver->full_name }}</div>
                        @endif
                    </td>
                    <td class="px-5 py-4">
                        <div class="flex items-center gap-2">
                            <span class="text-base">🚛</span>
                            <div>
                                <div class="text-sm font-semibold text-gray-900">{{ $trip->vehicle->name ?? '—' }}</div>
                                <div class="text-xs text-gray-400">{{ $trip->vehicle->license_plate ?? '' }}</div>
                            </div>
                        </div>
                    </td>
                    <td class="px-5 py-4">
                        <div class="flex items-start gap-2 max-w-xs">
                            <div class="flex flex-col items-center mt-1 flex-shrink-0">
                                <div class="w-2 h-2 bg-green-500 rounded-full"></div>
                                <div class="w-px h-4 bg-gray-200 my-0.5"></div>
                                <div class="w-2 h-2 bg-red-500 rounded-full"></div>
                            </div>
                            <div>
                                <div class="text-xs font-semibold text-gray-900 leading-tight">{{ $trip->origin_name }}</div>
                                <div class="text-xs text-gray-400 mt-1 leading-tight">{{ $trip->dest_name }}</div>
                            </div>
                        </div>
                    </td>
                    <td class="px-5 py-4 text-sm text-gray-600">
                        {{ $trip->departed_at ? \Carbon\Carbon::parse($trip->departed_at)->format('d/m/Y H:i') : '—' }}
                    </td>
                    <td class="px-5 py-4">
                        @php
                            $stMap = [
                                'planned'     => ['bg-blue-100 text-blue-700',   'Planned'],
                                'in_progress' => ['bg-green-100 text-green-700', 'In Progress'],
                                'completed'   => ['bg-gray-100 text-gray-600',   'Completed'],
                                'cancelled'   => ['bg-red-100 text-red-600',     'Cancelled'],
                            ];
                            [$cls, $lbl] = $stMap[$trip->status] ?? ['bg-gray-100 text-gray-600', $trip->status];
                        @endphp
                        <span class="px-2.5 py-1 rounded-full text-xs font-semibold {{ $cls }}">{{ $lbl }}</span>
                    </td>
                    <td class="px-5 py-4">
                        <div class="flex items-center justify-end gap-1">

                            {{-- Live Map --}}
                            @if($trip->status === 'in_progress')
                            <a href="{{ route('livemap.show', $trip->vehicle_id) }}"
                               class="w-8 h-8 flex items-center justify-center text-gray-400 hover:text-green-600 hover:bg-green-50 rounded-lg transition-all"
                               title="Live Map">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/>
                                </svg>
                            </a>
                            @endif

                            {{-- Start (planned → in_progress) --}}
                            @if($trip->status === 'planned')
                            <form action="{{ route('trips.start', $trip) }}" method="POST">
                                @csrf
                                <button type="submit"
                                        class="w-8 h-8 flex items-center justify-center text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-all"
                                        title="Mulai Trip">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                </button>
                            </form>
                            @endif

                            {{-- Complete (in_progress → completed) --}}
                            @if($trip->status === 'in_progress')
                            <form action="{{ route('trips.complete', $trip) }}" method="POST"
                                  onsubmit="return confirm('Tandai trip ini selesai?')">
                                @csrf
                                <button type="submit"
                                        class="w-8 h-8 flex items-center justify-center text-gray-400 hover:text-green-600 hover:bg-green-50 rounded-lg transition-all"
                                        title="Tandai Selesai">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                </button>
                            </form>
                            @endif

                            {{-- Edit --}}
                            <a href="{{ route('trips.edit', $trip) }}"
                               class="w-8 h-8 flex items-center justify-center text-gray-400 hover:text-green-600 hover:bg-green-50 rounded-lg transition-all">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                </svg>
                            </a>

                            {{-- Di kolom aksi tabel trips/index.blade.php, tambahkan tombol detail --}}
                            <a href="{{ route('trips.show', $trip) }}"
                            class="w-8 h-8 flex items-center justify-center text-gray-400 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg transition-all"
                            title="Lihat Detail & History">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0zM2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                </svg>
                            </a>

                            {{-- Delete --}}
                            @if($trip->status !== 'in_progress')
                            <form action="{{ route('trips.destroy', $trip) }}" method="POST"
                                  onsubmit="return confirm('Hapus trip {{ $trip->trip_code }}?')">
                                @csrf @method('DELETE')
                                <button type="submit"
                                        class="w-8 h-8 flex items-center justify-center text-gray-400 hover:text-red-500 hover:bg-red-50 rounded-lg transition-all">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                    </svg>
                                </button>
                            </form>
                            @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="px-5 py-12 text-center text-sm text-gray-400">
                        Belum ada trip.
                        <a href="{{ route('trips.create') }}" class="text-green-500 font-semibold">Buat sekarang →</a>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
        <div class="px-5 py-3 border-t border-gray-100">
            {{ $trips->links() }}
        </div>
    </div>
</div>
@endsection