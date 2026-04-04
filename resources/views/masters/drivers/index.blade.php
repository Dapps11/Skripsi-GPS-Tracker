@extends('layouts.app')
@section('title', 'Master Supir — Greenfields')

@section('content')
<div class="p-6">
    <div class="flex items-start justify-between mb-6">
        <div>
            <h1 class="text-2xl font-extrabold text-gray-900">Master Supir</h1>
            <p class="text-sm text-gray-400 mt-1">Kelola data supir — sumber dropdown form Add Device.</p>
        </div>
        <a href="{{ route('master.drivers.create') }}"
           class="flex items-center gap-2 px-4 py-2.5 bg-green-500 hover:bg-green-600 text-white text-sm font-bold rounded-xl transition-all shadow-md hover:shadow-green-200">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
            </svg>
            Tambah Supir
        </a>
    </div>

    <div class="card overflow-hidden">
        <table class="w-full">
            <thead>
                <tr class="border-b border-gray-100">
                    <th class="text-left text-xs font-bold text-gray-400 uppercase tracking-wider px-5 py-4">Kode</th>
                    <th class="text-left text-xs font-bold text-gray-400 uppercase tracking-wider px-5 py-4">Nama Lengkap</th>
                    <th class="text-left text-xs font-bold text-gray-400 uppercase tracking-wider px-5 py-4">No. HP</th>
                    <th class="text-left text-xs font-bold text-gray-400 uppercase tracking-wider px-5 py-4">No. SIM</th>
                    <th class="text-left text-xs font-bold text-gray-400 uppercase tracking-wider px-5 py-4">Exp. SIM</th>
                    <th class="text-left text-xs font-bold text-gray-400 uppercase tracking-wider px-5 py-4">Status</th>
                    <th class="text-right text-xs font-bold text-gray-400 uppercase tracking-wider px-5 py-4">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @forelse($drivers as $driver)
                <tr class="hover:bg-gray-50/70 transition-colors">
                    <td class="px-5 py-4 text-sm font-mono font-semibold text-gray-600">{{ $driver->driver_code }}</td>
                    <td class="px-5 py-4">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center text-sm font-bold text-green-700 flex-shrink-0">
                                {{ strtoupper(substr($driver->full_name, 0, 1)) }}
                            </div>
                            <span class="text-sm font-semibold text-gray-900">{{ $driver->full_name }}</span>
                        </div>
                    </td>
                    <td class="px-5 py-4 text-sm text-gray-500">{{ $driver->phone ?? '—' }}</td>
                    <td class="px-5 py-4 text-sm font-mono text-gray-500">{{ $driver->license_number ?? '—' }}</td>
                    <td class="px-5 py-4 text-sm">
                        @if($driver->license_expiry)
                            @php $exp = \Carbon\Carbon::parse($driver->license_expiry); @endphp
                            <span class="{{ $exp->isPast() ? 'text-red-500 font-semibold' : ($exp->diffInDays(now()) < 60 ? 'text-orange-500' : 'text-gray-500') }}">
                                {{ $exp->format('d/m/Y') }}
                            </span>
                        @else
                            <span class="text-gray-300">—</span>
                        @endif
                    </td>
                    <td class="px-5 py-4">
                        @php
                            $cls = match($driver->status) {
                                'available' => 'background:#dcfce7;color:#15803d',
                                'on_duty'   => 'background:#dbeafe;color:#1d4ed8',
                                'off_duty'  => 'background:#f3f4f6;color:#6b7280',
                                'inactive'  => 'background:#fee2e2;color:#b91c1c',
                                default     => 'background:#f3f4f6;color:#6b7280',
                            };
                            $lbl = match($driver->status) {
                                'available' => 'Available', 'on_duty' => 'On Duty',
                                'off_duty'  => 'Off Duty',  'inactive' => 'Inactive',
                                default     => $driver->status,
                            };
                        @endphp
                        <span style="padding:3px 10px;border-radius:9999px;font-size:11px;font-weight:600;{{ $cls }}">{{ $lbl }}</span>
                    </td>
                    <td class="px-5 py-4">
                        <div class="flex items-center justify-end gap-1">
                            <a href="{{ route('master.drivers.edit', $driver) }}"
                               class="w-8 h-8 flex items-center justify-center text-gray-400 hover:text-green-600 hover:bg-green-50 rounded-lg transition-all">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                </svg>
                            </a>
                            <form action="{{ route('master.drivers.destroy', $driver) }}" method="POST"
                                  onsubmit="return confirm('Hapus supir {{ $driver->full_name }}?')">
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
                        Belum ada data supir.
                        <a href="{{ route('master.drivers.create') }}" class="text-green-500 font-semibold">Tambah sekarang →</a>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
        <div class="px-5 py-3 border-t border-gray-100">{{ $drivers->links() }}</div>
    </div>
</div>
@endsection