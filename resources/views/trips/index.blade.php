@extends('layouts.app')
@section('title', 'Trip Management — Greenfields')

@section('content')
<div class="p-4 md:p-6">

    <div class="flex items-start justify-between mb-4 md:mb-6 gap-3">
        <div>
            <h1 class="text-xl md:text-2xl font-extrabold text-gray-900 page-title">Trip Management</h1>
            <p class="text-xs md:text-sm text-gray-400 mt-1">Kelola rute perjalanan kendaraan.</p>
        </div>
        <a href="{{ route('trips.create') }}"
           class="flex items-center gap-2 px-3 md:px-4 py-2 md:py-2.5 bg-green-500 hover:bg-green-600 text-white text-sm font-bold rounded-xl transition-all shadow-md flex-shrink-0">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
            </svg>
            <span class="hidden sm:inline">Tambah Trip</span>
            <span class="sm:hidden">Tambah</span>
        </a>
    </div>

    {{-- Summary --}}
    <div class="grid grid-cols-3 gap-3 md:gap-4 mb-4 md:mb-6 summary-grid">
        @foreach([
            ['Planned',     $summary['planned'],     'bg-blue-50',  'text-blue-500',  'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z'],
            ['In Progress', $summary['in_progress'], 'bg-green-50', 'text-green-500', 'M13 10V3L4 14h7v7l9-11h-7z'],
            ['Completed',   $summary['completed'],   'bg-gray-50',  'text-gray-500',  'M5 13l4 4L19 7'],
        ] as [$label, $count, $bg, $color, $path])
        <div class="card p-3 md:p-5">
            <div class="flex items-center justify-between mb-1 md:mb-2">
                <span class="text-xs md:text-sm text-gray-500">{{ $label }}</span>
                <div class="w-7 h-7 md:w-9 md:h-9 {{ $bg }} rounded-lg flex items-center justify-center">
                    <svg class="w-4 h-4 md:w-5 md:h-5 {{ $color }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="{{ $path }}"/>
                    </svg>
                </div>
            </div>
            <div class="text-2xl md:text-3xl font-extrabold text-gray-900">{{ $count }}</div>
        </div>
        @endforeach
    </div>

    {{-- Table --}}
    <div class="card overflow-hidden">
        <div class="table-responsive">
            <table class="w-full" style="min-width:640px;">
                <thead>
                    <tr class="border-b border-gray-100">
                        <th class="text-left text-xs font-bold text-gray-400 uppercase tracking-wider px-4 py-3">Trip</th>
                        <th class="text-left text-xs font-bold text-gray-400 uppercase tracking-wider px-4 py-3">Kendaraan</th>
                        <th class="text-left text-xs font-bold text-gray-400 uppercase tracking-wider px-4 py-3">Rute</th>
                        <th class="text-left text-xs font-bold text-gray-400 uppercase tracking-wider px-4 py-3 hidden md:table-cell">Berangkat</th>
                        <th class="text-left text-xs font-bold text-gray-400 uppercase tracking-wider px-4 py-3">Status</th>
                        <th class="text-right text-xs font-bold text-gray-400 uppercase tracking-wider px-4 py-3">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @forelse($trips as $trip)
                    <tr class="hover:bg-gray-50/70 transition-colors">
                        <td class="px-4 py-3">
                            <div class="font-bold text-gray-900 text-xs font-mono">{{ $trip->trip_code }}</div>
                            @if($trip->driver)
                            <div class="text-xs text-gray-400 mt-0.5"> {{ $trip->driver->full_name }}</div>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <div class="text-sm font-semibold text-gray-900">{{ $trip->vehicle->name ?? '—' }}</div>
                            <div class="text-xs text-gray-400">{{ $trip->vehicle->license_plate ?? '' }}</div>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-start gap-2">
                                <div class="flex flex-col items-center mt-1 flex-shrink-0">
                                    <div style="width:8px;height:8px;background:#22c55e;border-radius:50%;border:1.5px solid white;box-shadow:0 0 0 1.5px #22c55e60;flex-shrink:0;"></div>
                                    <div style="width:1.5px;height:10px;background:#e5e7eb;margin:2px 0;"></div>
                                    <div style="width:8px;height:8px;background:#ef4444;border-radius:50%;border:1.5px solid white;box-shadow:0 0 0 1.5px #ef444460;flex-shrink:0;"></div>
                                </div>
                                <div>
                                    <div class="text-xs font-semibold text-gray-900 leading-tight">{{ Str::limit($trip->origin_name, 22) }}</div>
                                    <div class="text-xs text-gray-400 mt-1.5 leading-tight">{{ Str::limit($trip->dest_name, 22) }}</div>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-xs text-gray-600 hidden md:table-cell">
                            {{ $trip->departed_at ? \Carbon\Carbon::parse($trip->departed_at)->format('d/m/Y H:i') : '—' }}
                        </td>
                        <td class="px-4 py-3">
                            @php
                                $stMap = [
                                    'planned'     => ['bg-blue-100 text-blue-700',   'Planned'],
                                    'in_progress' => ['bg-green-100 text-green-700', 'In Progress'],
                                    'completed'   => ['bg-gray-100 text-gray-600',   'Completed'],
                                    'cancelled'   => ['bg-red-100 text-red-600',     'Cancelled'],
                                ];
                                [$cls, $lbl] = $stMap[$trip->status] ?? ['bg-gray-100 text-gray-600', $trip->status];
                            @endphp
                            <span class="px-2 py-0.5 rounded-full text-xs font-semibold {{ $cls }}">{{ $lbl }}</span>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-1">
                                @if($trip->status === 'in_progress')
                                <a href="{{ route('livemap.show', $trip->vehicle_id) }}"
                                   class="w-7 h-7 flex items-center justify-center text-gray-400 hover:text-green-600 hover:bg-green-50 rounded-lg transition-all" title="Live Map">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/>
                                    </svg>
                                </a>
                                @endif

                                @if($trip->status === 'planned')
                                <form action="{{ route('trips.start', $trip) }}" method="POST">
                                    @csrf
                                    <button type="submit" class="w-7 h-7 flex items-center justify-center text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-all" title="Mulai Trip">
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                        </svg>
                                    </button>
                                </form>
                                @endif

                                @if($trip->status === 'in_progress')
                                <form action="{{ route('trips.complete', $trip) }}" method="POST" onsubmit="return confirm('Tandai trip ini selesai?')">
                                    @csrf
                                    <button type="submit" class="w-7 h-7 flex items-center justify-center text-gray-400 hover:text-green-600 hover:bg-green-50 rounded-lg transition-all" title="Selesai">
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                        </svg>
                                    </button>
                                </form>
                                @endif

                                @if($trip->status === 'planned')
                                <a href="{{ route('trips.edit', $trip) }}"
                                   class="w-7 h-7 flex items-center justify-center text-gray-400 hover:text-green-600 hover:bg-green-50 rounded-lg transition-all" title="Edit">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                    </svg>
                                </a>
                                @endif

                                <a href="{{ route('trips.show', $trip) }}"
                                   class="w-7 h-7 flex items-center justify-center text-gray-400 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg transition-all" title="Detail">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0zM2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                    </svg>
                                </a>

                                @if($trip->status !== 'in_progress')
                                <form action="{{ route('trips.destroy', $trip) }}" method="POST" onsubmit="return confirm('Hapus trip {{ $trip->trip_code }}?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="w-7 h-7 flex items-center justify-center text-gray-400 hover:text-red-500 hover:bg-red-50 rounded-lg transition-all">
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
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
                            Belum ada trip. <a href="{{ route('trips.create') }}" class="text-green-500 font-semibold">Buat sekarang →</a>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-4 py-3 border-t border-gray-100">
            {{ $trips->links() }}
        </div>
    </div>
</div>
@endsection