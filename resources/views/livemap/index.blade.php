@extends('layouts.app')
@section('title', 'Live Map — Greenfields')

@push('styles')
<style>
    #live-map  { width:100%; height:100%; }
    #live-gmap { width:100%; height:100%; position:absolute; inset:0; display:none; }
    .map-wrap  { position:relative; flex:1; overflow:hidden; }

    .detail-panel {
        position:absolute; top:16px; right:16px;
        width:400px; max-height: 60vh;
        background:white; border-radius:1.25rem;
        box-shadow:0 12px 40px rgba(0,0,0,.15),0 2px 8px rgba(0,0,0,.08);
        border:1px solid #f1f5f9; z-index:1000;
        transition:width .35s cubic-bezier(.4,0,.2,1),
                   max-height .35s cubic-bezier(.4,0,.2,1),
                   border-radius .35s cubic-bezier(.4,0,.2,1);
        display:flex; flex-direction:column; overflow:hidden;
    }
    .detail-panel.minimized {
        width:60px !important; max-height:60px !important;
        border-radius:1rem !important; cursor:pointer;
    }
    .detail-panel.minimized .panel-content { opacity:0; pointer-events:none; }
    .detail-panel.minimized .mini-card     { opacity:1; pointer-events:auto; }
    .panel-content {
        opacity:1; transition:opacity .15s ease;
        display:flex; flex-direction:column; overflow:hidden; flex:1;
    }
    .panel-body { overflow-y:auto; flex:1; padding:1rem 1.25rem; }
    .mini-card {
        opacity:0; position:absolute; inset:0;
        display:flex; align-items:center; justify-content:center;
        transition:opacity .2s ease .15s; pointer-events:none; z-index:2;
    }
    .irow {
        display:flex; align-items:center; justify-content:space-between;
        padding:12px 0; border-bottom:1px solid #f3f4f6;
    }
    .irow:last-child { border-bottom:none; }
</style>
@endpush

@section('content')
<div class="map-wrap" style="height:calc(100vh - 3.5rem);">

    <div id="live-map" style="width:100%;height:100%;"></div>
    <div id="live-gmap"></div>

    {{-- ── PANEL: ADA TRIP AKTIF ── --}}
    @if(isset($trip) && $trip)
    @php
        $etaMin  = 0;
        if ($trip->estimated_arrival_at) {
            $diff   = now()->diffInMinutes(\Carbon\Carbon::parse($trip->estimated_arrival_at), false);
            $etaMin = $diff > 0 ? $diff : 0;
        }
        $ds      = $latestDriverStatus->driver_status ?? 'normal';
        $dsStyle = match($ds) {
            'warning' => 'background:#ffedd5;color:#c2410c',
            'danger'  => 'background:#fee2e2;color:#b91c1c',
            default   => 'background:#dcfce7;color:#15803d',
        };
    @endphp

    <div class="detail-panel" id="detail-panel">

        <div class="mini-card" id="mini-card" onclick="expandPanel()">
            <div style="width:44px;height:44px;background:#22c55e;border-radius:14px;display:flex;align-items:center;justify-content:center;box-shadow:0 4px 12px rgba(34,197,94,.4);">
                <svg style="width:24px;height:24px;" fill="none" viewBox="0 0 24 24" stroke="white" stroke-width="2">
                    <path d="M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10l2 .001M13 16H9m4 0h4.5M13 16V9.5l3.5 1.5 2 3.5V16H17"/>
                </svg>
            </div>
        </div>

        <div class="panel-content" id="panel-content">

            {{-- Header --}}
            <div style="padding:1rem 1.25rem .875rem;border-bottom:1px solid #f3f4f6;flex-shrink:0;">
                <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:8px;">
                    <div style="min-width:0;flex:1;">
                        <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px;flex-wrap:wrap;">
                            <span style="padding:2px 10px;background:#dcfce7;color:#15803d;font-size:10px;font-weight:700;border-radius:9999px;text-transform:uppercase;letter-spacing:.05em;">Active</span>
                            <span style="font-size:15px;font-weight:800;color:#111827;">{{ $trip->vehicle_name }}</span>
                        </div>
                        <div style="display:flex;align-items:center;gap:5px;color:#9ca3af;font-size:12px;">
                            <svg style="width:13px;height:13px;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                            </svg>
                            {{ $trip->driver_name }} (Driver)
                        </div>
                    </div>
                    <div style="display:flex;align-items:center;gap:4px;flex-shrink:0;">
                        <button type="button"
                                style="width:30px;height:30px;display:flex;align-items:center;justify-content:center;border:none;background:transparent;border-radius:8px;cursor:pointer;color:#9ca3af;"
                                onmouseover="this.style.background='#f3f4f6';this.style.color='#374151'"
                                onmouseout="this.style.background='transparent';this.style.color='#9ca3af'"
                                onclick="event.stopPropagation();minimizePanel();"
                                title="Minimize">
                            <svg style="width:16px;height:16px;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                            </svg>
                        </button>
                        <a href="{{ route('livemap.index') }}"
                           style="width:30px;height:30px;display:flex;align-items:center;justify-content:center;border-radius:8px;color:#9ca3af;text-decoration:none;"
                           onmouseover="this.style.background='#fee2e2';this.style.color='#ef4444'"
                           onmouseout="this.style.background='transparent';this.style.color='#9ca3af'">
                            <svg style="width:16px;height:16px;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </a>
                    </div>
                </div>
            </div>

            <div class="panel-body">

                {{-- Route --}}
                <div style="background:#f9fafb;border-radius:14px;padding:14px;margin-bottom:16px;">
                    <div style="display:flex;gap:12px;">
                        <div style="display:flex;flex-direction:column;align-items:center;padding-top:3px;flex-shrink:0;">
                            <div style="width:11px;height:11px;background:#22c55e;border-radius:50%;border:2.5px solid white;box-shadow:0 0 0 2px #22c55e60;"></div>
                            <div style="width:2px;flex:1;background:#e5e7eb;margin:5px 0;min-height:32px;"></div>
                            <div style="width:11px;height:11px;background:#ef4444;border-radius:50%;border:2.5px solid white;box-shadow:0 0 0 2px #ef444460;"></div>
                        </div>
                        <div style="flex:1;display:flex;flex-direction:column;gap:14px;">
                            <div>
                                <div style="font-size:9px;font-weight:700;color:#22c55e;text-transform:uppercase;letter-spacing:.08em;margin-bottom:2px;">Start Point</div>
                                <div style="font-size:13px;font-weight:700;color:#111827;line-height:1.3;">{{ $trip->origin_name }}</div>
                                <div style="font-size:11px;color:#9ca3af;margin-top:1px;">Departed: {{ \Carbon\Carbon::parse($trip->departed_at)->setTimezone('Asia/Jakarta')->format('H:i') }} WIB</div>
                            </div>
                            <div>
                                <div style="font-size:9px;font-weight:700;color:#ef4444;text-transform:uppercase;letter-spacing:.08em;margin-bottom:2px;">Destination</div>
                                <div style="font-size:13px;font-weight:700;color:#111827;line-height:1.3;">{{ $trip->dest_name }}</div>
                                <div style="font-size:11px;color:#9ca3af;margin-top:1px;">Est. Arrival: {{ \Carbon\Carbon::parse($trip->estimated_arrival_at)->setTimezone('Asia/Jakarta')->format('H:i') }} WIB</div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Vehicle Info --}}
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:16px;">
                    <div style="background:#f9fafb;border-radius:12px;padding:12px;">
                        <div style="font-size:9px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:.08em;margin-bottom:4px;">Vehicle Type</div>
                        <div style="font-size:13px;font-weight:700;color:#111827;">{{ $trip->vehicle_type }}</div>
                    </div>
                    <div style="background:#f9fafb;border-radius:12px;padding:12px;">
                        <div style="font-size:9px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:.08em;margin-bottom:4px;">License Plate</div>
                        <div style="font-size:13px;font-weight:700;color:#111827;letter-spacing:.05em;">{{ $trip->license_plate }}</div>
                    </div>
                </div>

                {{-- IoT Status --}}
                <div style="margin-bottom:16px;">
                    <div style="display:flex;align-items:center;gap:6px;margin-bottom:10px;">
                        <div style="width:7px;height:7px;background:#22c55e;border-radius:50%;animation:pulse 2s infinite;"></div>
                        <span style="font-size:10px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:.08em;">Real-Time IoT Status</span>
                    </div>

                    {{-- Speed --}}
                    <div class="irow">
                        <div style="display:flex;align-items:center;gap:10px;">
                            <div style="width:36px;height:36px;background:#eff6ff;border-radius:10px;display:flex;align-items:center;justify-content:center;">
                                <svg style="width:18px;height:18px;" fill="none" viewBox="0 0 24 24" stroke="#3b82f6" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                                </svg>
                            </div>
                            <span style="font-size:13px;color:#374151;">Kecepatan Sekarang</span>
                        </div>
                        <span style="font-size:14px;font-weight:800;color:#111827;">
                            <span id="live-speed">{{ (int) round($trip->current_speed_kmh ?? 0) }}</span>
                            <span style="font-size:12px;font-weight:400;color:#9ca3af;"> km/h</span>
                        </span>
                    </div>

                    {{-- Jarak ke tujuan --}}
                    <div class="irow">
                        <div style="display:flex;align-items:center;gap:10px;">
                            <div style="width:36px;height:36px;background:#f0fdf4;border-radius:10px;display:flex;align-items:center;justify-content:center;">
                                <svg style="width:18px;height:18px;" fill="none" viewBox="0 0 24 24" stroke="#22c55e" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                                </svg>
                            </div>
                            <span style="font-size:13px;color:#374151;">Sisa Jarak</span>
                        </div>
                        <span style="font-size:14px;font-weight:800;color:#111827;">
                            <span id="live-dist">—</span>
                            <span style="font-size:12px;font-weight:400;color:#9ca3af;"> m</span>
                        </span>
                    </div>

                    {{-- Separator ETA --}}
                    <div style="font-size:9px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:.08em;padding:10px 0 6px;">
                        Estimasi Waktu Tiba (ETA)
                    </div>

                    {{-- ETA Real-time Haversine --}}
                    <div class="irow">
                        <div style="display:flex;align-items:center;gap:10px;">
                            <div style="width:36px;height:36px;background:#fff7ed;border-radius:10px;display:flex;align-items:center;justify-content:center;">
                                <svg style="width:18px;height:18px;" fill="none" viewBox="0 0 24 24" stroke="#f97316" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            </div>
                            <div>
                                <span style="font-size:13px;color:#374151;">Real-time (Haversine)</span>
                                <div style="font-size:9px;color:#9ca3af;">Dari posisi sekarang</div>
                            </div>
                        </div>
                        <span style="font-size:14px;font-weight:800;color:#f97316;">
                            <span id="eta-rt-haversine">—</span>
                            <span style="font-size:12px;font-weight:400;color:#9ca3af;"> mnt</span>
                        </span>
                    </div>

                    {{-- ETA Real-time API --}}
                    <div class="irow">
                        <div style="display:flex;align-items:center;gap:10px;">
                            <div style="width:36px;height:36px;background:#faf5ff;border-radius:10px;display:flex;align-items:center;justify-content:center;">
                                <svg style="width:18px;height:18px;" fill="none" viewBox="0 0 24 24" stroke="#9333ea" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            </div>
                            <div>
                                <span style="font-size:13px;color:#374151;">Real-time ({{ ($mapType === 'gmaps' || !empty($gmapsKey)) ? 'Google' : 'OSRM' }})</span>
                                <div style="font-size:9px;color:#9ca3af;">Via routing API</div>
                            </div>
                        </div>
                        <span style="font-size:14px;font-weight:800;color:#9333ea;">
                            <span id="eta-rt-api">—</span>
                            <span style="font-size:12px;font-weight:400;color:#9ca3af;"> mnt</span>
                        </span>
                    </div>

                    {{-- ETA Awal Haversine --}}
                    <div class="irow">
                        <div style="display:flex;align-items:center;gap:10px;">
                            <div style="width:36px;height:36px;background:#f0fdf4;border-radius:10px;display:flex;align-items:center;justify-content:center;">
                                <svg style="width:18px;height:18px;" fill="none" viewBox="0 0 24 24" stroke="#16a34a" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                </svg>
                            </div>
                            <div>
                                <span style="font-size:13px;color:#374151;">Awal (Haversine)</span>
                                <div style="font-size:9px;color:#9ca3af;">Dari titik keberangkatan</div>
                            </div>
                        </div>
                        <span style="font-size:14px;font-weight:800;color:#16a34a;">
                            <span id="eta-init-haversine">{{ $etaMin }}</span>
                            <span style="font-size:12px;font-weight:400;color:#9ca3af;"> mnt</span>
                        </span>
                    </div>

                    {{-- ETA Awal API --}}
                    <div class="irow">
                        <div style="display:flex;align-items:center;gap:10px;">
                            <div style="width:36px;height:36px;background:#eef2ff;border-radius:10px;display:flex;align-items:center;justify-content:center;">
                                <svg style="width:18px;height:18px;" fill="none" viewBox="0 0 24 24" stroke="#6366f1" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                </svg>
                            </div>
                            <div>
                                <span style="font-size:13px;color:#374151;">Awal ({{ ($mapType === 'gmaps' || !empty($gmapsKey)) ? 'Google' : 'OSRM' }})</span>
                                <div style="font-size:9px;color:#9ca3af;">Dari titik keberangkatan</div>
                            </div>
                        </div>
                        <span style="font-size:14px;font-weight:800;color:#6366f1;">
                            <span id="eta-init-api">—</span>
                            <span style="font-size:12px;font-weight:400;color:#9ca3af;"> mnt</span>
                        </span>
                    </div>

                    {{-- Driver Status --}}
                    <div class="irow">
                        <div style="display:flex;align-items:center;gap:10px;">
                            <div style="width:36px;height:36px;background:#f0fdf4;border-radius:10px;display:flex;align-items:center;justify-content:center;">
                                <svg style="width:18px;height:18px;" fill="none" viewBox="0 0 24 24" stroke="#22c55e" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                                </svg>
                            </div>
                            <span style="font-size:13px;color:#374151;">Driver Status</span>
                        </div>
                        <span id="ds-badge" style="padding:4px 12px;border-radius:9999px;font-size:11px;font-weight:700;{{ $dsStyle }}">
                            {{ strtoupper($ds) }}
                        </span>
                    </div>
                </div>

                @if($ds !== 'normal')
                <div style="padding:12px;background:#fff7ed;border:1px solid #fed7aa;border-radius:12px;display:flex;gap:10px;margin-bottom:16px;">
                    <svg style="width:16px;height:16px;flex-shrink:0;margin-top:1px;" fill="none" viewBox="0 0 24 24" stroke="#f97316" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                    <span style="font-size:12px;color:#c2410c;line-height:1.5;">System detected drowsy behavior. Monitoring closely.</span>
                </div>
                @endif

                {{-- Buttons --}}
                <div style="display:flex;flex-direction:column;gap:8px;">
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
                        {{-- Contact Driver via WhatsApp --}}
                        @if($trip->driver_phone ?? null)
                        @php
                            $waPhone = preg_replace('/[^0-9]/', '', $trip->driver_phone ?? '');
                            if (str_starts_with($waPhone, '0')) $waPhone = '62' . substr($waPhone, 1);
                        @endphp
                        <a href="https://api.whatsapp.com/send?phone={{ $waPhone }}&text={{ urlencode('Halo ' . ($trip->driver_name ?? 'Supir') . ', ini pemberitahuan dari sistem fleet tracking.') }}"
                           target="_blank" rel="noopener"
                           style="display:flex;align-items:center;justify-content:center;gap:7px;padding:11px 8px;background:#dcfce7;border:1.5px solid #86efac;border-radius:12px;font-size:12px;font-weight:600;color:#15803d;text-decoration:none;"
                           onmouseover="this.style.background='#bbf7d0'"
                           onmouseout="this.style.background='#dcfce7'">
                            <svg style="width:15px;height:15px;" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                            </svg>
                            WhatsApp
                        </a>
                        @else
                        <div style="display:flex;align-items:center;justify-content:center;gap:7px;padding:11px 8px;background:#f9fafb;border:1.5px solid #e5e7eb;border-radius:12px;font-size:12px;font-weight:600;color:#9ca3af;cursor:not-allowed;"
                             title="No. HP supir tidak tersedia">
                            <svg style="width:15px;height:15px;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                            </svg>
                            No HP —
                        </div>
                        @endif

                        {{-- View Telemetry → trip detail --}}
                        <a href="{{ route('trips.show', $trip->id ?? 0) }}"
                           style="display:flex;align-items:center;justify-content:center;gap:7px;padding:11px 8px;background:#22c55e;border:none;border-radius:12px;font-size:12px;font-weight:700;color:white;text-decoration:none;"
                           onmouseover="this.style.background='#16a34a'"
                           onmouseout="this.style.background='#22c55e'">
                            <svg style="width:15px;height:15px;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                            </svg>
                            Detail Trip
                        </a>
                    </div>
                </div>

            </div>
        </div>
    </div>

    @elseif(isset($vehicle) && $vehicle && $vehicle->status === 'moving' && !isset($trip))
    {{-- ── PANEL: VEHICLE DIPILIH — BERGERAK TANPA TRIP AKTIF ── --}}
    <div class="detail-panel" id="detail-panel" style="width:320px;">
        <div class="mini-card" id="mini-card" onclick="expandPanel()">
            <div style="width:44px;height:44px;background:#3b82f6;border-radius:14px;display:flex;align-items:center;justify-content:center;box-shadow:0 4px 12px rgba(59,130,246,.4);">
                <svg style="width:22px;height:22px;" fill="none" viewBox="0 0 24 24" stroke="white" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                </svg>
            </div>
        </div>
        <div class="panel-content" id="panel-content">
            {{-- Header --}}
            <div style="padding:1rem 1.25rem .875rem;border-bottom:1px solid #f3f4f6;flex-shrink:0;">
                <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:8px;">
                    <div style="min-width:0;flex:1;">
                        <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px;flex-wrap:wrap;">
                            <span style="padding:2px 10px;font-size:10px;font-weight:700;border-radius:9999px;text-transform:uppercase;letter-spacing:.05em;background:#dcfce7;color:#15803d">
                                MOVING
                            </span>
                            <span style="font-size:15px;font-weight:800;color:#111827;">{{ $vehicle->name }}</span>
                        </div>
                        <div style="font-size:11px;color:#9ca3af;">{{ $vehicle->license_plate }} · Tidak ada trip aktif</div>
                    </div>
                    <div style="display:flex;gap:4px;flex-shrink:0;">
                        <button onclick="event.stopPropagation();minimizePanel();"
                                style="width:30px;height:30px;display:flex;align-items:center;justify-content:center;border:none;background:transparent;border-radius:8px;cursor:pointer;color:#9ca3af;"
                                onmouseover="this.style.background='#f3f4f6'" onmouseout="this.style.background='transparent'">
                            <svg style="width:16px;height:16px;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                            </svg>
                        </button>
                        <a href="{{ route('livemap.index') }}"
                           style="width:30px;height:30px;display:flex;align-items:center;justify-content:center;border-radius:8px;color:#9ca3af;text-decoration:none;"
                           onmouseover="this.style.background='#fee2e2';this.style.color='#ef4444'"
                           onmouseout="this.style.background='transparent';this.style.color='#9ca3af'">
                            <svg style="width:16px;height:16px;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </a>
                    </div>
                </div>
            </div>

            <div class="panel-body">
                <div style="margin-bottom:16px;">
                    <div style="display:flex;align-items:center;gap:6px;margin-bottom:10px;">
                        <div style="width:7px;height:7px;background:#22c55e;border-radius:50%;animation:pulse 2s infinite;"></div>
                        <span style="font-size:10px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:.08em;">Real-Time IoT Status</span>
                    </div>

                    {{-- Speed --}}
                    <div class="irow">
                        <div style="display:flex;align-items:center;gap:10px;">
                            <div style="width:36px;height:36px;background:#eff6ff;border-radius:10px;display:flex;align-items:center;justify-content:center;">
                                <svg style="width:18px;height:18px;" fill="none" viewBox="0 0 24 24" stroke="#3b82f6" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                                </svg>
                            </div>
                            <span style="font-size:13px;color:#374151;">Kecepatan Sekarang</span>
                        </div>
                        <span style="font-size:14px;font-weight:800;color:#111827;">
                            <span id="live-speed-moving">{{ (int) round($vehicle->speed_kmh ?? ($gpsPoints->last()->speed_kmh ?? 0)) }}</span>
                            <span style="font-size:12px;font-weight:400;color:#9ca3af;"> km/h</span>
                        </span>
                    </div>

                    {{-- Driver Status --}}
                    @php
                        $ds = 'normal';
                        if (isset($latestDriverStatus)) {
                            if ($latestDriverStatus->is_alarm) $ds = 'danger';
                            elseif ($latestDriverStatus->event_type === 'drowsy') $ds = 'warning';
                        }
                        $dsStyle = $ds === 'normal' ? 'background:#dcfce7;color:#15803d' : ($ds === 'warning' ? 'background:#fef08a;color:#a16207' : 'background:#fee2e2;color:#b91c1c');
                    @endphp
                    <div class="irow">
                        <div style="display:flex;align-items:center;gap:10px;">
                            <div style="width:36px;height:36px;background:#f0fdf4;border-radius:10px;display:flex;align-items:center;justify-content:center;">
                                <svg style="width:18px;height:18px;" fill="none" viewBox="0 0 24 24" stroke="#22c55e" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                                </svg>
                            </div>
                            <span style="font-size:13px;color:#374151;">Driver Status</span>
                        </div>
                        <span id="ds-badge-moving" style="padding:4px 12px;border-radius:9999px;font-size:11px;font-weight:700;{{ $dsStyle }}">
                            {{ strtoupper($ds) }}
                        </span>
                    </div>
                </div>

                @if($ds !== 'normal')
                <div style="padding:12px;background:#fff7ed;border:1px solid #fed7aa;border-radius:12px;display:flex;gap:10px;margin-bottom:16px;">
                    <svg style="width:16px;height:16px;flex-shrink:0;margin-top:1px;" fill="none" viewBox="0 0 24 24" stroke="#f97316" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                    <span style="font-size:12px;color:#c2410c;line-height:1.5;">System detected drowsy behavior. Monitoring closely.</span>
                </div>
                @endif

                <div style="display:flex;flex-direction:column;gap:8px;">
                    <a href="{{ route('trips.create', ['vehicle_id' => $vehicle->id]) }}"
                       style="display:flex;align-items:center;justify-content:center;gap:6px;padding:11px 8px;background:#22c55e;color:white;border-radius:12px;font-size:12px;font-weight:700;text-decoration:none;"
                       onmouseover="this.style.background='#16a34a'" onmouseout="this.style.background='#22c55e'">
                        <svg style="width:15px;height:15px;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                        </svg>
                        Buat Trip Baru
                    </a>
                </div>
            </div>
        </div>
    </div>

    @elseif(isset($vehicle) && $vehicle && isset($lastTrip) && $lastTrip)
    {{-- ── PANEL: VEHICLE DIPILIH — ADA TRIP TERAKHIR ── --}}
    <div class="detail-panel" id="detail-panel" style="width:320px;">
        <div class="mini-card" id="mini-card" onclick="expandPanel()">
            <div style="width:44px;height:44px;background:#6b7280;border-radius:14px;display:flex;align-items:center;justify-content:center;box-shadow:0 4px 12px rgba(107,114,128,.4);">
                <svg style="width:22px;height:22px;" fill="none" viewBox="0 0 24 24" stroke="white" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10l2 .001M13 16H9m4 0h4.5M13 16V9.5l3.5 1.5 2 3.5V16H17"/>
                </svg>
            </div>
        </div>
        <div class="panel-content" id="panel-content">
            {{-- Header --}}
            <div style="padding:1rem 1.25rem .875rem;border-bottom:1px solid #f3f4f6;flex-shrink:0;">
                <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:8px;">
                    <div style="min-width:0;flex:1;">
                        <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px;flex-wrap:wrap;">
                            @php $vs = $vehicle->status ?? 'offline'; @endphp
                            <span style="padding:2px 10px;font-size:10px;font-weight:700;border-radius:9999px;text-transform:uppercase;letter-spacing:.05em;
                                {{ $vs==='moving'?'background:#dcfce7;color:#15803d':($vs==='idle'?'background:#ffedd5;color:#c2410c':'background:#f3f4f6;color:#6b7280') }}">
                                {{ ucfirst($vs) }}
                            </span>
                            <span style="font-size:15px;font-weight:800;color:#111827;">{{ $vehicle->name }}</span>
                        </div>
                        <div style="font-size:11px;color:#9ca3af;">{{ $vehicle->license_plate }} · Tidak ada trip aktif</div>
                    </div>
                    <div style="display:flex;gap:4px;flex-shrink:0;">
                        <button onclick="event.stopPropagation();minimizePanel();"
                                style="width:30px;height:30px;display:flex;align-items:center;justify-content:center;border:none;background:transparent;border-radius:8px;cursor:pointer;color:#9ca3af;"
                                onmouseover="this.style.background='#f3f4f6'" onmouseout="this.style.background='transparent'">
                            <svg style="width:16px;height:16px;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                            </svg>
                        </button>
                        <a href="{{ route('livemap.index') }}"
                           style="width:30px;height:30px;display:flex;align-items:center;justify-content:center;border-radius:8px;color:#9ca3af;text-decoration:none;"
                           onmouseover="this.style.background='#fee2e2';this.style.color='#ef4444'"
                           onmouseout="this.style.background='transparent';this.style.color='#9ca3af'">
                            <svg style="width:16px;height:16px;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </a>
                    </div>
                </div>
            </div>

            <div class="panel-body">
                {{-- Divider label --}}
                <div style="display:flex;align-items:center;gap:8px;margin-bottom:14px;">
                    <div style="flex:1;height:1px;background:#f1f5f9;"></div>
                    <span style="font-size:10px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:.08em;white-space:nowrap;">Trip Terakhir</span>
                    <div style="flex:1;height:1px;background:#f1f5f9;"></div>
                </div>

                {{-- Trip code & status --}}
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;">
                    <div>
                        <div style="font-size:11px;font-weight:700;font-family:monospace;color:#6b7280;">{{ $lastTrip->trip_code }}</div>
                        @if($lastTrip->driver_name ?? null)
                        <div style="font-size:11px;color:#9ca3af;margin-top:2px;"> {{ $lastTrip->driver_name }}</div>
                        @endif
                    </div>
                    @php $ltLbl = $lastTrip->status === 'completed' ? 'Completed' : 'Cancelled'; @endphp
                    <span style="padding:3px 10px;border-radius:9999px;font-size:10px;font-weight:700;
                        {{ $lastTrip->status==='completed'?'background:#f3f4f6;color:#6b7280':'background:#fee2e2;color:#b91c1c' }}">
                        {{ $ltLbl }}
                    </span>
                </div>

                {{-- Rute --}}
                <div style="background:#f9fafb;border-radius:14px;padding:14px;margin-bottom:14px;">
                    <div style="display:flex;gap:12px;">
                        <div style="display:flex;flex-direction:column;align-items:center;padding-top:3px;flex-shrink:0;">
                            <div style="width:10px;height:10px;background:#22c55e;border-radius:50%;border:2px solid white;box-shadow:0 0 0 2px #22c55e60;"></div>
                            <div style="width:2px;flex:1;background:#e5e7eb;margin:4px 0;min-height:28px;"></div>
                            <div style="width:10px;height:10px;background:#ef4444;border-radius:50%;border:2px solid white;box-shadow:0 0 0 2px #ef444460;"></div>
                        </div>
                        <div style="flex:1;display:flex;flex-direction:column;gap:12px;min-width:0;">
                            <div>
                                <div style="font-size:9px;font-weight:700;color:#22c55e;text-transform:uppercase;letter-spacing:.08em;margin-bottom:2px;">Asal</div>
                                <div style="font-size:12px;font-weight:700;color:#111827;line-height:1.3;">{{ $lastTrip->origin_name }}</div>
                                @if($lastTrip->departed_at)
                                <div style="font-size:10px;color:#9ca3af;margin-top:1px;">
                                    {{ \Carbon\Carbon::parse($lastTrip->departed_at)->setTimezone('Asia/Jakarta')->format('d/m/Y H:i') }} WIB
                                </div>
                                @endif
                            </div>
                            <div>
                                <div style="font-size:9px;font-weight:700;color:#ef4444;text-transform:uppercase;letter-spacing:.08em;margin-bottom:2px;">Tujuan</div>
                                <div style="font-size:12px;font-weight:700;color:#111827;line-height:1.3;">{{ $lastTrip->dest_name }}</div>
                                @if($lastTrip->arrived_at)
                                <div style="font-size:10px;color:#9ca3af;margin-top:1px;">
                                    Tiba: {{ \Carbon\Carbon::parse($lastTrip->arrived_at)->setTimezone('Asia/Jakarta')->format('d/m/Y H:i') }} WIB
                                </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Statistik --}}
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:14px;">
                    <div style="background:#f9fafb;border-radius:12px;padding:12px;">
                        <div style="font-size:9px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:.08em;margin-bottom:4px;">Jarak</div>
                        <div style="font-size:18px;font-weight:800;color:#111827;">
                            @if($lastTrip->total_distance_km)
                                {{ number_format($lastTrip->total_distance_km, 1) }}<span style="font-size:11px;font-weight:400;color:#9ca3af;"> km</span>
                            @else
                                <span style="font-size:13px;color:#9ca3af;">—</span>
                            @endif
                        </div>
                    </div>
                    <div style="background:#f9fafb;border-radius:12px;padding:12px;">
                        <div style="font-size:9px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:.08em;margin-bottom:4px;">Durasi</div>
                        <div style="font-size:18px;font-weight:800;color:#111827;">
                            @if($lastTrip->departed_at && $lastTrip->arrived_at)
                                @php
                                    $dur = (int) \Carbon\Carbon::parse($lastTrip->departed_at)->diffInMinutes($lastTrip->arrived_at);
                                    $dj = intdiv($dur, 60); $dm = $dur % 60;
                                @endphp
                                @if($dj > 0)
                                    {{ $dj }}<span style="font-size:11px;font-weight:400;color:#9ca3af;">j</span> {{ $dm }}<span style="font-size:11px;font-weight:400;color:#9ca3af;">m</span>
                                @else
                                    {{ $dm }}<span style="font-size:11px;font-weight:400;color:#9ca3af;"> mnt</span>
                                @endif
                            @else
                                <span style="font-size:13px;color:#9ca3af;">—</span>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Tombol aksi --}}
                <div style="display:flex;flex-direction:column;gap:8px;">
                    <a href="{{ route('trips.show', $lastTrip->id) }}"
                       style="display:flex;align-items:center;justify-content:center;gap:8px;padding:12px;background:#f9fafb;border:1.5px solid #e5e7eb;border-radius:12px;font-size:13px;font-weight:700;color:#374151;text-decoration:none;"
                       onmouseover="this.style.background='#f3f4f6'" onmouseout="this.style.background='#f9fafb'">
                        <svg style="width:16px;height:16px;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0zM2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                        </svg>
                        Lihat Detail Trip
                    </a>
                    @if($lastTrip->driver_phone ?? null)
                    @php
                        $ltWaPhone = preg_replace('/[^0-9]/', '', $lastTrip->driver_phone ?? '');
                        if (str_starts_with($ltWaPhone, '0')) $ltWaPhone = '62' . substr($ltWaPhone, 1);
                    @endphp
                    <a href="https://api.whatsapp.com/send?phone={{ $ltWaPhone }}&text={{ urlencode('Halo ' . ($lastTrip->driver_name ?? 'Supir') . ', ini pemberitahuan dari sistem fleet tracking.') }}"
                       target="_blank" rel="noopener"
                       style="display:flex;align-items:center;justify-content:center;gap:7px;padding:11px 8px;background:#dcfce7;border:1.5px solid #86efac;border-radius:12px;font-size:12px;font-weight:600;color:#15803d;text-decoration:none;"
                       onmouseover="this.style.background='#bbf7d0'" onmouseout="this.style.background='#dcfce7'">
                        <svg style="width:15px;height:15px;" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                        </svg>
                        WhatsApp
                    </a>
                    @endif
                    <a href="{{ route('trips.create') }}"
                       style="display:flex;align-items:center;justify-content:center;gap:8px;padding:11px 8px;background:#22c55e;border:none;border-radius:12px;font-size:12px;font-weight:700;color:white;text-decoration:none;"
                       onmouseover="this.style.background='#16a34a'" onmouseout="this.style.background='#22c55e'">
                        <svg style="width:15px;height:15px;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                        </svg>
                        Buat Trip Baru
                    </a>
                </div>
            </div>
        </div>
    </div>


    @elseif(isset($vehicle) && $vehicle)
    {{-- ── PANEL: VEHICLE DIPILIH, BELUM PERNAH ADA TRIP ── --}}
    <div class="detail-panel" id="detail-panel" style="width:320px;">
        <div class="mini-card" id="mini-card" onclick="expandPanel()">
            <div style="width:44px;height:44px;background:#9ca3af;border-radius:14px;display:flex;align-items:center;justify-content:center;">
                <svg style="width:22px;height:22px;" fill="none" viewBox="0 0 24 24" stroke="white" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10l2 .001M13 16H9m4 0h4.5M13 16V9.5l3.5 1.5 2 3.5V16H17"/>
                </svg>
            </div>
        </div>
        <div class="panel-content" id="panel-content">
            <div style="padding:14px 16px;border-bottom:1px solid #f3f4f6;display:flex;align-items:center;justify-content:space-between;flex-shrink:0;">
                <div>
                    <div style="font-size:14px;font-weight:800;color:#111827;">{{ $vehicle->name }}</div>
                    <div style="font-size:11px;color:#9ca3af;">{{ $vehicle->license_plate }}</div>
                </div>
                <a href="{{ route('livemap.index') }}"
                   style="width:30px;height:30px;display:flex;align-items:center;justify-content:center;border-radius:8px;color:#9ca3af;text-decoration:none;"
                   onmouseover="this.style.background='#fee2e2';this.style.color='#ef4444'"
                   onmouseout="this.style.background='transparent';this.style.color='#9ca3af'">
                    <svg style="width:16px;height:16px;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </a>
            </div>
            <div class="panel-body" style="display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;padding:2rem 1.25rem;">
                <div style="width:56px;height:56px;background:#f3f4f6;border-radius:16px;display:flex;align-items:center;justify-content:center;margin-bottom:12px;font-size:26px;">🚛</div>
                <div style="font-size:13px;font-weight:700;color:#374151;margin-bottom:6px;">Belum Ada Trip</div>
                <div style="font-size:12px;color:#9ca3af;line-height:1.5;margin-bottom:16px;">Kendaraan ini belum memiliki riwayat perjalanan.</div>
                <a href="{{ route('trips.create') }}"
                   style="display:inline-flex;align-items:center;gap:6px;padding:10px 16px;background:#22c55e;color:white;border-radius:12px;font-size:12px;font-weight:700;text-decoration:none;"
                   onmouseover="this.style.background='#16a34a'" onmouseout="this.style.background='#22c55e'">
                    <svg style="width:14px;height:14px;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                    </svg>
                    Buat Trip Baru
                </a>
            </div>
        </div>
    </div>

    @else
    {{-- ── PANEL: ALL VEHICLES (halaman index livemap) ── --}}
    <div class="detail-panel" id="detail-panel" style="width:320px;">
        <div class="mini-card" id="mini-card" onclick="expandPanel()">
            <div style="width:44px;height:44px;background:#22c55e;border-radius:14px;display:flex;align-items:center;justify-content:center;box-shadow:0 4px 12px rgba(34,197,94,.4);">
                <svg style="width:22px;height:22px;" fill="none" viewBox="0 0 24 24" stroke="white" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/>
                </svg>
            </div>
        </div>
        <div class="panel-content" id="panel-content">
            <div style="padding:14px 16px;border-bottom:1px solid #f3f4f6;display:flex;align-items:center;justify-content:space-between;flex-shrink:0;">
                <span style="font-size:14px;font-weight:800;color:#111827;">All Vehicles</span>
                <button type="button"
                        style="width:30px;height:30px;display:flex;align-items:center;justify-content:center;background:transparent;border:none;border-radius:8px;cursor:pointer;color:#9ca3af;"
                        onmouseover="this.style.background='#f3f4f6'"
                        onmouseout="this.style.background='transparent'"
                        onclick="event.stopPropagation();minimizePanel();">
                    <svg style="width:16px;height:16px;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                    </svg>
                </button>
            </div>
            <div class="panel-body">
                @foreach($vehicles as $v)
                @if($v->latitude && $v->longitude)
                <a href="{{ route('livemap.show', $v->vehicle_id) }}"
                   style="display:flex;align-items:center;gap:12px;padding:10px;border-radius:12px;border:1.5px solid transparent;text-decoration:none;margin-bottom:4px;"
                   onmouseover="this.style.background='#f9fafb';this.style.borderColor='#e5e7eb'"
                   onmouseout="this.style.background='transparent';this.style.borderColor='transparent'">
                    <div style="width:36px;height:36px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0;
                        background:{{ $v->vehicle_status==='moving'?'#dcfce7':($v->vehicle_status==='idle'?'#ffedd5':'#fee2e2') }};">
                        🚛
                    </div>
                    <div style="flex:1;min-width:0;">
                        <div style="font-size:13px;font-weight:700;color:#111827;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{{ $v->vehicle_name }}</div>
                        <div style="font-size:11px;color:#9ca3af;">{{ $v->license_plate }}</div>
                    </div>
                    <span style="padding:3px 10px;border-radius:9999px;font-size:10px;font-weight:700;flex-shrink:0;
                        {{ $v->vehicle_status==='moving'?'background:#dcfce7;color:#15803d':($v->vehicle_status==='idle'?'background:#ffedd5;color:#c2410c':'background:#fee2e2;color:#b91c1c') }}">
                        {{ strtoupper($v->vehicle_status) }}
                    </span>
                </a>
                @endif
                @endforeach
            </div>
        </div>
    </div>
    @endif

    {{-- Toast --}}
    <div id="toast" style="position:fixed;bottom:24px;left:50%;transform:translateX(-50%) translateY(8px);z-index:2000;opacity:0;pointer-events:none;transition:all .3s ease;">
        <div style="background:#111827;color:white;font-size:12px;font-weight:600;padding:10px 16px;border-radius:12px;box-shadow:0 4px 20px rgba(0,0,0,.3);display:flex;align-items:center;gap:8px;">
            <svg style="width:14px;height:14px;" fill="none" viewBox="0 0 24 24" stroke="#4ade80" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
            </svg>
            <span id="toast-msg">Perintah foto dikirim ke device.</span>
        </div>
    </div>

</div>
@endsection

@push('scripts')
<script>
// ── Data dari server → diteruskan ke livemap.js via window.__livemap ──
window.__livemap = {
    gmapsKey:    "{{ $googleMapsKey ?? '' }}",
    mapType:     "{{ $mapType ?? 'osm' }}",
    allVehicles: @json($vehicles),
    gpsPoints:   @json($gpsPoints ?? []),
    activeTrip:  @json($trip ?? null),
    hasTrip:     {{ isset($trip) && $trip ? 'true' : 'false' }},
    selectedVehicleId: "{{ $vehicle->id ?? '' }}",
};
</script>
<script src="{{ asset('js/livemap.js') }}"></script>
@endpush