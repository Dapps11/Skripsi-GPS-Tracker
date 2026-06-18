@extends('layouts.app')
@section('title', 'Live Map — Greenfields')

@push('styles')
<style>
    #live-map  { width:100%; height:100%; }
    #live-gmap { width:100%; height:100%; position:absolute; inset:0; display:none; }
    .map-wrap  { position:relative; flex:1; overflow:hidden; }

    .detail-panel {
        position:absolute; top:16px; right:16px;
        width:400px; max-height:calc(100vh - 3.5rem - 32px);
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
                                <div style="font-size:11px;color:#9ca3af;margin-top:1px;">Departed: {{ \Carbon\Carbon::parse($trip->departed_at)->format('h:i A') }}</div>
                            </div>
                            <div>
                                <div style="font-size:9px;font-weight:700;color:#ef4444;text-transform:uppercase;letter-spacing:.08em;margin-bottom:2px;">Destination</div>
                                <div style="font-size:13px;font-weight:700;color:#111827;line-height:1.3;">{{ $trip->dest_name }}</div>
                                <div style="font-size:11px;color:#9ca3af;margin-top:1px;">Est. Arrival: {{ \Carbon\Carbon::parse($trip->estimated_arrival_at)->format('h:i A') }}</div>
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
                                <span style="font-size:13px;color:#374151;">Real-time ({{ $mapType === 'gmaps' ? 'Google' : 'OSRM' }})</span>
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
                                <span style="font-size:13px;color:#374151;">Awal ({{ $mapType === 'gmaps' ? 'Google' : 'OSRM' }})</span>
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
                        <a href="tel:{{ $trip->driver_phone ?? '' }}"
                           style="display:flex;align-items:center;justify-content:center;gap:7px;padding:11px 8px;border:1.5px solid #e5e7eb;border-radius:12px;font-size:12px;font-weight:600;color:#374151;text-decoration:none;"
                           onmouseover="this.style.background='#f9fafb'"
                           onmouseout="this.style.background='transparent'">
                            <svg style="width:15px;height:15px;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                            </svg>
                            Contact Driver
                        </a>
                        <button style="display:flex;align-items:center;justify-content:center;gap:7px;padding:11px 8px;background:#22c55e;border:none;border-radius:12px;font-size:12px;font-weight:700;color:white;cursor:pointer;"
                                onmouseover="this.style.background='#16a34a'"
                                onmouseout="this.style.background='#22c55e'">
                            <svg style="width:15px;height:15px;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                            </svg>
                            View Telemetry
                        </button>
                    </div>
                    <button id="btn-take-picture" type="button"
                            onclick="takePicture({{ $trip->device_id ?? 'null' }})"
                            style="width:100%;display:flex;align-items:center;justify-content:center;gap:8px;padding:12px;background:#eef2ff;border:1.5px solid #c7d2fe;border-radius:12px;font-size:13px;font-weight:700;color:#4338ca;cursor:pointer;"
                            onmouseover="this.style.background='#e0e7ff';this.style.borderColor='#a5b4fc'"
                            onmouseout="this.style.background='#eef2ff';this.style.borderColor='#c7d2fe'">
                        <svg style="width:18px;height:18px;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                        Take Picture Driver
                    </button>
                </div>

            </div>
        </div>
    </div>

    @else
    {{-- ── PANEL: TIDAK ADA TRIP ── --}}
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
                    <div style="width:36px;height:36px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0;
                        background:{{ $v->vehicle_status==='moving'?'#dcfce7':($v->vehicle_status==='idle'?'#ffedd5':'#fee2e2') }};">🚛</div>
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
// ── Config ────────────────────────────────────────────────────────
const GMAPS_KEY    = "{{ $googleMapsKey ?? '' }}";
const MAP_TYPE     = "{{ $mapType ?? 'osm' }}";
const allVehicles  = @json($vehicles);
const gpsPoints    = @json($gpsPoints ?? []);
const activeTrip   = @json($trip ?? null);
const STATUS_COLOR = { moving:'#22c55e', idle:'#f97316', offline:'#ef4444', online:'#22c55e' };

// ════════════════════════════════════════════════════════════════
// UI HELPERS
// ════════════════════════════════════════════════════════════════
let isMinimized = false;
function minimizePanel() {
    const p = document.getElementById('detail-panel');
    if (!p || isMinimized) return;
    isMinimized = true;
    p.classList.add('minimized');
}
function expandPanel() {
    const p = document.getElementById('detail-panel');
    if (!p || !isMinimized) return;
    isMinimized = false;
    p.classList.remove('minimized');
}

function takePicture(deviceId) {
    const btn = document.getElementById('btn-take-picture');
    if (!btn) return;
    btn.disabled = true; btn.style.opacity = '.7';
    btn.innerHTML = `<svg style="width:18px;height:18px;animation:spin 1s linear infinite;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg> Mengirim...`;
    setTimeout(() => {
        btn.disabled = false; btn.style.opacity = '1';
        btn.innerHTML = `<svg style="width:18px;height:18px;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/></svg> Take Picture Driver`;
        showToast('📸 Perintah foto berhasil dikirim ke OpenMV!');
    }, 1500);
}

function showToast(msg) {
    const el = document.getElementById('toast');
    const tx = document.getElementById('toast-msg');
    if (!el) return;
    tx.textContent = msg;
    el.style.opacity = '1';
    el.style.transform = 'translateX(-50%) translateY(0)';
    el.style.pointerEvents = 'auto';
    setTimeout(() => {
        el.style.opacity = '0';
        el.style.transform = 'translateX(-50%) translateY(8px)';
        el.style.pointerEvents = 'none';
    }, 3000);
}

// ════════════════════════════════════════════════════════════════
// SHARED HELPERS
// ════════════════════════════════════════════════════════════════
function samplePoints(pts, max) {
    if (pts.length <= max) return pts;
    const r = [pts[0]], step = (pts.length - 2) / (max - 2);
    for (let i = 1; i < max - 1; i++) r.push(pts[Math.round(i * step)]);
    r.push(pts[pts.length - 1]);
    return r;
}

function haversineJS(lat1, lng1, lat2, lng2) {
    const R    = 6371;
    const dLat = (lat2 - lat1) * Math.PI / 180;
    const dLng = (lng2 - lng1) * Math.PI / 180;
    const a    = Math.sin(dLat/2)**2
               + Math.cos(lat1*Math.PI/180) * Math.cos(lat2*Math.PI/180)
               * Math.sin(dLng/2)**2;
    return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
}

function calcRealtimeETA(curLat, curLng, destLat, destLng, currentSpeed) {
    const dist     = haversineJS(curLat, curLng, destLat, destLng);
    const rf       = dist < 3 ? 1.6 : (dist < 10 ? 1.4 : 1.25);
    const distRoad = dist * rf;
    const speed    = currentSpeed > 5
        ? currentSpeed
        : (distRoad < 5 ? 25 : (distRoad < 15 ? 35 : 50));
    const delay    = distRoad < 5 ? 5 : (distRoad < 15 ? 4 : 3);
    return Math.round((distRoad / speed) * 60 + delay);
}

// ════════════════════════════════════════════════════════════════
// OPENSTREETMAP
// ════════════════════════════════════════════════════════════════
let osmMap         = null;
let osmMarkers     = {};
let osmTrackLayers = [];
let osmRouteMain   = null;
let osmRouteShadow = null;

function initOSM() {
    document.getElementById('live-map').style.display  = 'block';
    document.getElementById('live-gmap').style.display = 'none';

    osmMap = L.map('live-map', { zoomControl:false }).setView([-7.965, 112.60], 13);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution:'© OpenStreetMap contributors', maxZoom:19
    }).addTo(osmMap);
    L.control.zoom({ position:'topleft' }).addTo(osmMap);

    const mkTruck = (status, isActive = false) => {
        const c = STATUS_COLOR[status] || '#6b7280';
        const s = isActive ? 48 : 40;
        return L.divIcon({
            html: `<div style="width:${s}px;height:${s}px;background:${c};border-radius:50%;border:3px solid white;box-shadow:0 0 0 ${isActive?`5px ${c}35,`:''} 0 3px 12px rgba(0,0,0,.25);display:flex;align-items:center;justify-content:center;font-size:${isActive?22:18}px;transition:all .4s;">🚛</div>`,
            iconSize:[s,s], iconAnchor:[s/2,s/2], className:''
        });
    };

    const mkWP = (color, size = 42) => L.divIcon({
        html: `<div style="width:${size}px;height:${size}px;background:${color};border-radius:50%;border:4px solid white;box-shadow:0 0 0 3px ${color}55,0 4px 14px rgba(0,0,0,.25);"></div>`,
        iconSize:[size,size], iconAnchor:[size/2,size/2], className:''
    });

    allVehicles.forEach(v => {
        if (!v.latitude || !v.longitude) return;
        const isActive = activeTrip && activeTrip.vehicle_id == v.vehicle_id;
        const m = L.marker([+v.latitude, +v.longitude], { icon: mkTruck(v.vehicle_status, isActive) })
            .addTo(osmMap)
            .bindPopup(`
                <div style="font-family:'Plus Jakarta Sans',sans-serif;font-size:12px;min-width:160px;">
                    <div style="font-weight:800;font-size:13px;">${v.vehicle_name}</div>
                    <div style="color:#9ca3af;font-size:10px;margin-bottom:6px;">${v.license_plate}</div>
                    <div style="font-size:11px;line-height:1.8;">👤 ${v.driver_name||'—'}<br>⚡ ${v.speed_kmh||0} km/h</div>
                    <a href="/live-map/${v.vehicle_id}" style="display:block;margin-top:8px;background:#22c55e;color:white;text-align:center;padding:6px;border-radius:8px;font-size:11px;font-weight:700;text-decoration:none;">Lihat Detail →</a>
                </div>`);
        m.on('click', () => window.location.href = `/live-map/${v.vehicle_id}`);
        osmMarkers[v.vehicle_id] = { marker: m, mkIcon: mkTruck };
    });

    if (activeTrip && activeTrip.origin_lat) {
        L.marker([+activeTrip.origin_lat, +activeTrip.origin_lng], { icon: mkWP('#22c55e', 42), zIndexOffset:500 })
         .addTo(osmMap)
         .bindTooltip(`<b style="font-size:12px;">🟢 ${activeTrip.origin_name}</b>`, { permanent:true, direction:'top', offset:[0,-26] });
        L.marker([+activeTrip.dest_lat, +activeTrip.dest_lng], { icon: mkWP('#ef4444', 42), zIndexOffset:500 })
         .addTo(osmMap)
         .bindTooltip(`<b style="font-size:12px;">🔴 ${activeTrip.dest_name}</b>`, { permanent:true, direction:'bottom', offset:[0,26] });
    }

    (async () => {
        if (activeTrip && activeTrip.origin_lat) {
            const rc = await drawOSMRoute(+activeTrip.origin_lat, +activeTrip.origin_lng, +activeTrip.dest_lat, +activeTrip.dest_lng);
            if (gpsPoints.length >= 2) drawOSMTrack(gpsPoints);
            if (rc && rc.length) osmMap.fitBounds(rc, { padding:[80, 420] });
        } else {
            const coords = allVehicles.filter(v => v.latitude && v.longitude).map(v => [+v.latitude, +v.longitude]);
            if (coords.length) osmMap.fitBounds(coords, { padding:[60, 340] });
        }
    })();

    @if(isset($trip) && $trip)
    const leg = L.control({ position:'bottomleft' });
    leg.onAdd = () => {
        const d = L.DomUtil.create('div');
        d.style.cssText = 'background:white;padding:10px 14px;border-radius:12px;box-shadow:0 2px 12px rgba(0,0,0,.12);font-size:11px;font-family:Plus Jakarta Sans,sans-serif;line-height:2;';
        d.innerHTML = `
            <div style="font-weight:700;color:#374151;margin-bottom:4px;">Keterangan</div>
            <div style="display:flex;align-items:center;gap:6px;"><div style="width:28px;height:4px;background:#4f46e5;border-radius:2px;"></div><span style="color:#4f46e5;font-weight:600;">Rute Jalan</span></div>
            <div style="display:flex;align-items:center;gap:6px;"><div style="width:28px;height:4px;background:#f97316;border-radius:2px;"></div><span style="color:#f97316;font-weight:600;">Riwayat GPS</span></div>
            <div style="display:flex;align-items:center;gap:6px;"><div style="width:14px;height:14px;background:#22c55e;border-radius:50%;border:2px solid white;"></div><span>Titik Awal</span></div>
            <div style="display:flex;align-items:center;gap:6px;"><div style="width:14px;height:14px;background:#ef4444;border-radius:50%;border:2px solid white;"></div><span>Titik Tujuan</span></div>`;
        return d;
    };
    leg.addTo(osmMap);
    @endif
}

function drawOSMTrack(points) {
    if (!points || points.length < 2) return;
    osmTrackLayers.forEach(l => osmMap.removeLayer(l));
    osmTrackLayers = [];
    const coords = points.map(p => [+p.latitude, +p.longitude]);
    osmTrackLayers.push(
        L.polyline(coords, { color:'#fb923c', weight:10, opacity:.18, lineCap:'round', lineJoin:'round' }).addTo(osmMap),
        L.polyline(coords, { color:'#f97316', weight:4.5, opacity:.9,  lineCap:'round', lineJoin:'round' }).addTo(osmMap)
    );
}

async function drawOSMRoute(oLat, oLng, dLat, dLng) {
    try {
        const res  = await fetch(`https://router.project-osrm.org/route/v1/driving/${oLng},${oLat};${dLng},${dLat}?overview=full&geometries=geojson`, { signal: AbortSignal.timeout(8000) });
        const data = await res.json();
        if (data.code !== 'Ok' || !data.routes.length) throw new Error('no route');
        const coords = data.routes[0].geometry.coordinates.map(c => [c[1], c[0]]);
        if (osmRouteShadow) osmMap.removeLayer(osmRouteShadow);
        if (osmRouteMain)   osmMap.removeLayer(osmRouteMain);
        osmRouteShadow = L.polyline(coords, { color:'#818cf8', weight:10, opacity:.2,  lineCap:'round', lineJoin:'round' }).addTo(osmMap);
        osmRouteMain   = L.polyline(coords, { color:'#4f46e5', weight:5,  opacity:.88, lineCap:'round', lineJoin:'round' }).addTo(osmMap);
        return coords;
    } catch(e) {
        if (!activeTrip) return null;
        const coords = [[+activeTrip.origin_lat, +activeTrip.origin_lng], [+activeTrip.dest_lat, +activeTrip.dest_lng]];
        osmRouteMain = L.polyline(coords, { color:'#4f46e5', weight:4, opacity:.6, dashArray:'10,7' }).addTo(osmMap);
        return coords;
    }
}

// ════════════════════════════════════════════════════════════════
// GOOGLE MAPS
// ════════════════════════════════════════════════════════════════
let gMap               = null;
let gMapReady          = false;
let gVMarkers          = {};
let gTrackLines        = [];
let gDirectionsRenderers = [];
let gTraffic           = null;

function initGMaps() {
    document.getElementById('live-map').style.display  = 'none';
    document.getElementById('live-gmap').style.display = 'block';

    if (!GMAPS_KEY) return;
    if (window.google && window.google.maps) { onLiveGmapReady(); return; }
    if (document.getElementById('gmaps-sdk')) return;

    const s   = document.createElement('script');
    s.id      = 'gmaps-sdk';
    s.src     = `https://maps.googleapis.com/maps/api/js?key=${GMAPS_KEY}&libraries=geometry&callback=onLiveGmapReady&loading=async`;
    s.async   = true;
    s.defer   = true;
    document.head.appendChild(s);
}

const mkGIcon = (status, isActive = false) => {
    const c    = STATUS_COLOR[status] || '#6b7280';
    const size = isActive ? 60 : 52;
    const svg  = `<svg xmlns="http://www.w3.org/2000/svg" width="${size}" height="${size}" viewBox="0 0 ${size} ${size}">
        <circle cx="${size/2}" cy="${size/2}" r="${size/2}" fill="${c}" fill-opacity="0.22"/>
        <circle cx="${size/2}" cy="${size/2}" r="${size/2-5}" fill="${c}" stroke="white" stroke-width="3.5"/>
        <text x="${size/2}" y="${size/2+8}" text-anchor="middle" font-size="${isActive?24:20}" font-family="Apple Color Emoji,Segoe UI Emoji,Noto Color Emoji,sans-serif">🚛</text>
    </svg>`;
    return {
        url:        'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(svg),
        scaledSize: new google.maps.Size(size, size),
        anchor:     new google.maps.Point(size/2, size/2),
    };
};

window.onLiveGmapReady = function () {
    gMapReady = true;

    const center = activeTrip
        ? { lat: +activeTrip.origin_lat, lng: +activeTrip.origin_lng }
        : { lat: -7.965, lng: 112.60 };

    gMap = new google.maps.Map(document.getElementById('live-gmap'), {
        center, zoom:13, mapTypeId:'roadmap',
        mapTypeControl:false, fullscreenControl:false, streetViewControl:false,
        zoomControlOptions: { position: google.maps.ControlPosition.LEFT_TOP },
    });

    gTraffic = new google.maps.TrafficLayer();
    gTraffic.setMap(gMap);

    allVehicles.forEach(v => {
        if (!v.latitude || !v.longitude) return;
        const isActive = activeTrip && activeTrip.vehicle_id == v.vehicle_id;
        const marker = new google.maps.Marker({
            position:  { lat: +v.latitude, lng: +v.longitude },
            map:       gMap,
            icon:      mkGIcon(v.vehicle_status, isActive),
            title:     v.vehicle_name,
            optimized: false,
            zIndex:    isActive ? 999 : 10,
        });
        const info = new google.maps.InfoWindow({
            content: `<div style="font-family:'Plus Jakarta Sans',sans-serif;font-size:12px;min-width:160px;padding:4px;">
                <div style="font-weight:800;font-size:13px;">${v.vehicle_name}</div>
                <div style="color:#9ca3af;font-size:10px;margin-bottom:6px;">${v.license_plate}</div>
                <div style="font-size:11px;line-height:1.8;">👤 ${v.driver_name||'—'}<br>⚡ ${v.speed_kmh||0} km/h</div>
                <a href="/live-map/${v.vehicle_id}" style="display:block;margin-top:8px;background:#22c55e;color:white;text-align:center;padding:6px;border-radius:8px;font-size:11px;font-weight:700;text-decoration:none;">Lihat Detail →</a>
            </div>`
        });
        marker.addListener('click', () => info.open(gMap, marker));
        gVMarkers[v.vehicle_id] = { marker, isActive };
    });

    if (activeTrip && activeTrip.origin_lat) {
        new google.maps.Marker({
            position: { lat: +activeTrip.origin_lat, lng: +activeTrip.origin_lng },
            map: gMap,
            icon: { path: google.maps.SymbolPath.CIRCLE, scale:14, fillColor:'#22c55e', fillOpacity:1, strokeColor:'white', strokeWeight:4 },
            title: activeTrip.origin_name, zIndex:998,
        });
        new google.maps.Marker({
            position: { lat: +activeTrip.dest_lat, lng: +activeTrip.dest_lng },
            map: gMap,
            icon: { path: google.maps.SymbolPath.CIRCLE, scale:14, fillColor:'#ef4444', fillOpacity:1, strokeColor:'white', strokeWeight:4 },
            title: activeTrip.dest_name, zIndex:998,
        });
    }

    if (activeTrip) {
        drawGoogleRoute();
        if (gpsPoints.length >= 2) drawGoogleTrack(gpsPoints);
        if (activeTrip.origin_lat) {
            const bounds = new google.maps.LatLngBounds();
            bounds.extend({ lat: +activeTrip.origin_lat, lng: +activeTrip.origin_lng });
            bounds.extend({ lat: +activeTrip.dest_lat,   lng: +activeTrip.dest_lng });
            gpsPoints.forEach(p => bounds.extend({ lat: +p.latitude, lng: +p.longitude }));
            gMap.fitBounds(bounds, 80);
        }
    } else {
        const vp = allVehicles.filter(v => v.latitude && v.longitude);
        if (vp.length) {
            const b = new google.maps.LatLngBounds();
            vp.forEach(v => b.extend({ lat: +v.latitude, lng: +v.longitude }));
            gMap.fitBounds(b, 60);
        }
    }
};

function drawGoogleRoute() {
    if (!gMap || !activeTrip) return;
    const svc = new google.maps.DirectionsService();
    const rdr = new google.maps.DirectionsRenderer({
        map: gMap, suppressMarkers: true,
        polylineOptions: { strokeColor:'#4f46e5', strokeWeight:5, strokeOpacity:.88 },
    });
    svc.route({
        origin:      { lat: +activeTrip.origin_lat, lng: +activeTrip.origin_lng },
        destination: { lat: +activeTrip.dest_lat,   lng: +activeTrip.dest_lng },
        travelMode:  google.maps.TravelMode.DRIVING,
    }, (result, status) => { if (status === 'OK') rdr.setDirections(result); });
}

async function drawGoogleTrack(points) {
    if (!gMap || !window.google || !points || points.length < 2) return;
    gTrackLines.forEach(p => p.setMap(null));
    gTrackLines = [];
    gDirectionsRenderers.forEach(r => r.setMap(null));
    gDirectionsRenderers = [];

    const CHUNK_SIZE = 100;
    const allSnapped = [];
    try {
        const chunks = [];
        for (let i = 0; i < points.length; i += CHUNK_SIZE - 1) {
            chunks.push(points.slice(i, i + CHUNK_SIZE));
        }
        for (const chunk of chunks) {
            const pathParam = chunk.map(p => `${+p.latitude},${+p.longitude}`).join('|');
            const url       = `https://roads.googleapis.com/v1/snapToRoads?path=${encodeURIComponent(pathParam)}&interpolate=true&key=${GMAPS_KEY}`;
            const res       = await fetch(url);
            const data      = await res.json();
            if (data.error) throw new Error(data.error.message);
            if (data.snappedPoints?.length > 0) {
                const startIdx = allSnapped.length > 0 ? 1 : 0;
                data.snappedPoints.slice(startIdx).forEach(sp => {
                    allSnapped.push({ lat: sp.location.latitude, lng: sp.location.longitude });
                });
            }
        }
        if (allSnapped.length < 2) throw new Error('No snapped points');
        gTrackLines.push(
            new google.maps.Polyline({ path:allSnapped, map:gMap, strokeColor:'#fb923c', strokeOpacity:.2,  strokeWeight:10, zIndex:1 }),
            new google.maps.Polyline({ path:allSnapped, map:gMap, strokeColor:'#f97316', strokeOpacity:.9, strokeWeight:4.5, zIndex:2 })
        );
    } catch(e) {
        console.warn('Roads API gagal, fallback:', e.message);
        drawGoogleTrackViaDirections(points);
    }
}

function drawGoogleTrackViaDirections(points) {
    if (!gMap || !window.google || !points || points.length < 2) return;
    const sampled     = samplePoints(points, 25);
    const origin      = { lat: +sampled[0].latitude,                lng: +sampled[0].longitude };
    const destination = { lat: +sampled[sampled.length-1].latitude, lng: +sampled[sampled.length-1].longitude };
    const waypoints   = sampled.slice(1, -1).map(p => ({
        location: new google.maps.LatLng(+p.latitude, +p.longitude),
        stopover: true,
    }));
    const svc = new google.maps.DirectionsService();
    svc.route({
        origin, destination, waypoints,
        travelMode: google.maps.TravelMode.DRIVING,
        optimizeWaypoints: false,
    }, (result, status) => {
        if (status === 'OK') {
            const path = [];
            result.routes[0].legs.forEach(leg => {
                leg.steps.forEach(step => {
                    google.maps.geometry.encoding.decodePath(step.polyline.points).forEach(p => path.push(p));
                });
            });
            gTrackLines.push(
                new google.maps.Polyline({ path, map:gMap, strokeColor:'#fb923c', strokeOpacity:.2,  strokeWeight:10, zIndex:1 }),
                new google.maps.Polyline({ path, map:gMap, strokeColor:'#f97316', strokeOpacity:.9, strokeWeight:4.5, zIndex:2 })
            );
        } else {
            const coords = points.map(p => ({ lat:+p.latitude, lng:+p.longitude }));
            gTrackLines.push(
                new google.maps.Polyline({ path:coords, map:gMap, strokeColor:'#fb923c', strokeOpacity:.2, strokeWeight:10, zIndex:1 }),
                new google.maps.Polyline({ path:coords, map:gMap, strokeColor:'#f97316', strokeOpacity:.9, strokeWeight:4.5, zIndex:2 })
            );
        }
    });
}

// ════════════════════════════════════════════════════════════════
// ETA — API OSRM / Google
// ════════════════════════════════════════════════════════════════
async function fetchAPIeta(lat1, lng1, lat2, lng2) {
    if (MAP_TYPE === 'gmaps' && window.google) return fetchGoogleETA(lat1, lng1, lat2, lng2);
    return fetchOSRMeta(lat1, lng1, lat2, lng2);
}

async function fetchOSRMeta(lat1, lng1, lat2, lng2) {
    try {
        const url  = `https://router.project-osrm.org/route/v1/driving/${lng1},${lat1};${lng2},${lat2}?overview=false`;
        const res  = await fetch(url, { signal: AbortSignal.timeout(5000) });
        const data = await res.json();
        if (data.code !== 'Ok' || !data.routes.length) return null;
        return Math.round(data.routes[0].duration / 60);
    } catch(e) { return null; }
}

function fetchGoogleETA(lat1, lng1, lat2, lng2) {
    return new Promise(resolve => {
        if (!window.google?.maps) { resolve(null); return; }

        new google.maps.DirectionsService().route({
            origin:        { lat: lat1, lng: lng1 },
            destination:   { lat: lat2, lng: lng2 },
            travelMode:    google.maps.TravelMode.DRIVING,
            // ── Traffic real-time ──────────────────────────────
            drivingOptions: {
                departureTime: new Date(), // waktu sekarang → aktifkan traffic
                trafficModel:  google.maps.TrafficModel.BEST_GUESS,
                // BEST_GUESS   → estimasi terbaik (default Google Maps)
                // PESSIMISTIC  → kondisi terburuk (macet lebih)
                // OPTIMISTIC   → kondisi terbaik (lancar)
            },
        }, (result, status) => {
            if (status !== 'OK' || !result.routes.length) {
                resolve(null);
                return;
            }

            const leg = result.routes[0].legs[0];

            // Pakai duration_in_traffic jika tersedia (ada traffic data)
            // fallback ke duration biasa jika tidak ada
            const durationSeconds = leg.duration_in_traffic
                ? leg.duration_in_traffic.value
                : leg.duration.value;

            resolve(Math.round(durationSeconds / 60));
        });
    });
}

// ── ETA awal (origin → dest) — dipanggil SEKALI ──────────────────
let etaInitFetched = false;

async function fetchInitialAPIeta() {
    if (!activeTrip || etaInitFetched) return;
    etaInitFetched = true;

    // Haversine awal
    if (activeTrip.origin_lat && activeTrip.dest_lat) {
        const dist     = haversineJS(+activeTrip.origin_lat, +activeTrip.origin_lng, +activeTrip.dest_lat, +activeTrip.dest_lng);
        const rf       = dist < 3 ? 1.6 : (dist < 10 ? 1.4 : 1.25);
        const distRoad = dist * rf;
        const speed    = distRoad < 5 ? 25 : (distRoad < 15 ? 35 : 50);
        const delay    = distRoad < 5 ? 5  : (distRoad < 15 ? 4  : 3);
        const etaHav   = Math.round((distRoad / speed) * 60 + delay);
        const havEl    = document.getElementById('eta-init-haversine');
        if (havEl) havEl.textContent = etaHav;
    }

    // API awal
    const etaAPI = await fetchAPIeta(+activeTrip.origin_lat, +activeTrip.origin_lng, +activeTrip.dest_lat, +activeTrip.dest_lng);
    const apiEl  = document.getElementById('eta-init-api');
    if (apiEl) apiEl.textContent = etaAPI !== null ? etaAPI : '—';
}

// ── ETA real-time dari API — dipanggil tiap 30 detik ─────────────
async function pollAPIeta() {
    if (!activeTrip) return;
    try {
        const data = await fetch(`/api/internal/trip/${activeTrip.vehicle_id}`)
                           .then(r => r.json());

        if (!data?.trip?.current_lat) return;

        // Gunakan realtime_google dari server jika tersedia
        if (data.eta?.realtime_google !== null && data.eta?.realtime_google !== undefined) {
            const el = document.getElementById('eta-rt-api');
            if (el) el.textContent = data.eta.realtime_google;
            return; // sudah dapat dari server, tidak perlu fetch lagi dari client
        }

        // Fallback: fetch dari client jika server tidak return
        const lat     = +data.trip.current_lat;
        const lng     = +data.trip.current_lng;
        const destLat = +activeTrip.dest_lat;
        const destLng = +activeTrip.dest_lng;

        const etaAPI = await fetchAPIeta(lat, lng, destLat, destLng);
        const el     = document.getElementById('eta-rt-api');
        if (el) el.textContent = etaAPI !== null ? etaAPI : '—';

    } catch(e) { console.warn('pollAPIeta:', e.message); }
}

// ════════════════════════════════════════════════════════════════
// GPS TRACK UPDATE dari server
// ════════════════════════════════════════════════════════════════
let lastTrackLen = gpsPoints.length; // satu deklarasi saja

async function updateTrackFromServer() {
    if (!activeTrip) return;
    try {
        const data = await (await fetch(`/api/internal/trip/${activeTrip.vehicle_id}`)).json();
        if (data.gps_track && data.gps_track.length > lastTrackLen) {
            lastTrackLen = data.gps_track.length;
            if (MAP_TYPE === 'osm')                drawOSMTrack(data.gps_track);
            if (MAP_TYPE === 'gmaps' && gMapReady) drawGoogleTrack(data.gps_track);
        }
    } catch(e) {}
}

// ════════════════════════════════════════════════════════════════
// WEBSOCKET HANDLERS
// (dipanggil dari global Echo listener di layouts/app.blade.php)
// ════════════════════════════════════════════════════════════════
window.updateLivemapMarker = function(data) {
    const lat = +data.latitude, lng = +data.longitude;
    const vid = String(data.vehicle_id);

    if (osmMarkers[vid]) {
        osmMarkers[vid].marker.setLatLng([lat, lng]);
        const mkFn = osmMarkers[vid].mkIcon;
        if (mkFn) osmMarkers[vid].marker.setIcon(
            mkFn(data.vehicle_status, activeTrip && String(activeTrip.vehicle_id) === vid)
        );
    }
    if (gVMarkers[vid] && gMapReady) {
        gVMarkers[vid].marker.setPosition({ lat, lng });
        gVMarkers[vid].marker.setIcon(
            mkGIcon(data.vehicle_status, activeTrip && String(activeTrip.vehicle_id) === vid)
        );
    }
};

window.updateLivemapPanel = function(data) {
    if (!activeTrip || String(activeTrip.vehicle_id) !== String(data.vehicle_id)) return;

    // Speed
    const spEl = document.getElementById('live-speed');
    if (spEl) spEl.textContent = Math.round(data.speed_kmh || 0);

    // Sisa jarak
    if (data.latitude && data.longitude) {
        const lat     = +data.latitude, lng = +data.longitude;
        const destLat = +activeTrip.dest_lat, destLng = +activeTrip.dest_lng;
        const distKm  = haversineJS(lat, lng, destLat, destLng);
        const distM   = Math.round(distKm * 1000);
        const distEl  = document.getElementById('live-dist');
        if (distEl) {
            distEl.textContent = distM >= 1000 ? distKm.toFixed(1) : distM;
            distEl.nextElementSibling.textContent = distM >= 1000 ? ' km' : ' m';
        }

        // ETA real-time haversine
        const etaRTel = document.getElementById('eta-rt-haversine');
        if (etaRTel) etaRTel.textContent = calcRealtimeETA(lat, lng, destLat, destLng, data.speed_kmh || 0);
    }

    // Update GPS track
    updateTrackFromServer();
};

window.handleTripUpdate = function(data) {
    if (!activeTrip || String(activeTrip.vehicle_id) !== String(data.vehicle_id)) return;
    if (data.status === 'completed') {
        if (apiEtaTimer) clearInterval(apiEtaTimer);
        if (fallbackTimer) clearInterval(fallbackTimer);
        setTimeout(() => { window.location.href = `/trips/${data.trip_id}`; }, 2000);
    }
};

// ════════════════════════════════════════════════════════════════
// TIMERS — satu tempat, tidak duplikat
// ════════════════════════════════════════════════════════════════
let apiEtaTimer   = null;
let fallbackTimer = null;

// ════════════════════════════════════════════════════════════════
// INIT — dipanggil SEKALI di paling bawah
// ════════════════════════════════════════════════════════════════
if (MAP_TYPE === 'gmaps') {
    initGMaps();
} else {
    initOSM();
}

if (activeTrip) {
    // ETA awal — sekali
    fetchInitialAPIeta();

    // ETA real-time API — tiap 30 detik
    pollAPIeta();
    apiEtaTimer = setInterval(pollAPIeta, 30000);

    // Fallback polling tiap 10 detik — HANYA jika WebSocket tidak konek
    let wsConnected = false;
    if (typeof window.Echo !== 'undefined') {
        window.Echo.connector.pusher.connection.bind('connected',    () => { wsConnected = true; });
        window.Echo.connector.pusher.connection.bind('disconnected', () => { wsConnected = false; });
    }
    fallbackTimer = setInterval(() => {
        if (!wsConnected) updateTrackFromServer();
    }, 10000);
}

window.addEventListener('beforeunload', () => {
    if (apiEtaTimer)   clearInterval(apiEtaTimer);
    if (fallbackTimer) clearInterval(fallbackTimer);
});
</script>
@endpush