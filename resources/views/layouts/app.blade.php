<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Greenfields Fleet Tracking')</title>

    {{-- Fonts --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    {{-- Leaflet --}}
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

    {{-- Tailwind CDN --}}
    <script src="https://cdn.tailwindcss.com"></script>

    {{-- Vite — Echo + pusher-js --}}
    @vite(['resources/js/app.js'])

    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: #f8fafc;
            color: #111827;
        }

        /* ── Sidebar ── */
        .sidebar-link {
            display: flex; align-items: center; gap: 10px;
            padding: 9px 12px; border-radius: 10px;
            font-size: 13px; font-weight: 600;
            color: #6b7280; text-decoration: none;
            transition: all .15s;
        }
        .sidebar-link:hover { background: #f3f4f6; color: #111827; }
        .sidebar-link.active { background: #f0fdf4; color: #16a34a; }
        .sidebar-link.active svg { stroke: #16a34a; }

        /* ── Card ── */
        .card {
            background: white;
            border-radius: 1rem;
            border: 1.5px solid #f1f5f9;
            box-shadow: 0 1px 4px rgba(0,0,0,.05);
        }

        /* ── Animations ── */
        @keyframes pulse {
            0%,100% { opacity:1; transform:scale(1); }
            50%      { opacity:.7; transform:scale(1.3); }
        }
        @keyframes spin {
            from { transform: rotate(0deg); }
            to   { transform: rotate(360deg); }
        }

        /* ── Mobile Responsive ── */
        @media (max-width: 768px) {
            aside {
                position: fixed !important;
                left: -240px;
                z-index: 9999;
                transition: left .3s ease;
                height: 100vh;
                top: 0;
            }
            aside.open { left: 0 !important; }
            #sidebar-overlay {
                display: none;
                position: fixed; inset: 0;
                background: rgba(0,0,0,0.5);
                z-index: 9998;
            }
            #sidebar-overlay.show { display: block; }
            .detail-panel {
                width: calc(100vw - 16px) !important;
                top: auto !important; bottom: 0 !important;
                right: 0 !important; left: 0 !important;
                margin: 0 8px !important;
                max-height: 55vh !important;
                border-radius: 1.25rem 1.25rem 0 0 !important;
            }
            .detail-panel.minimized {
                width: 56px !important; max-height: 56px !important;
                border-radius: 1rem !important;
                bottom: 16px !important; right: 16px !important;
                left: auto !important; margin: 0 !important;
            }

            /* Tabel responsive — scroll horizontal di mobile */
            .table-responsive { overflow-x: auto; -webkit-overflow-scrolling: touch; }
            .table-responsive table { min-width: 600px; }

            /* Card padding lebih kecil di mobile */
            .card { border-radius: .875rem; }

            /* Page padding */
            .page-pad { padding: 1rem !important; }

            /* Header action button — sembunyikan teks, tampilkan ikon saja */
            .btn-text-hide { display: none; }

            /* Summary grid — 2 kolom di mobile */
            .summary-grid { grid-template-columns: repeat(2, 1fr) !important; }

            /* Stack flex jadi column di mobile */
            .mobile-stack { flex-direction: column !important; align-items: flex-start !important; }

            /* Font size lebih kecil untuk heading di mobile */
            .page-title { font-size: 1.25rem !important; }
        }

        @media (max-width: 480px) {
            /* Summary grid — 1 kolom di layar sangat kecil */
            .summary-grid { grid-template-columns: 1fr !important; }
        }
    </style>

    @stack('styles')
</head>

<body class="flex h-screen overflow-hidden">

{{-- ── SIDEBAR ── --}}
<aside class="w-56 bg-white border-r border-gray-100 flex flex-col flex-shrink-0 h-full overflow-y-auto">

    {{-- Logo --}}
    <div class="px-4 py-5 border-b border-gray-100 flex-shrink-0">
        <div class="flex items-center gap-2.5">
            <div class="w-8 h-8 bg-green-500 rounded-lg flex items-center justify-center flex-shrink-0">
                <svg class="w-4 h-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/>
                </svg>
            </div>
            <div>
                <div class="text-sm font-extrabold text-gray-900 leading-tight">Greenfields</div>
                <div class="text-[9px] text-gray-400 font-semibold uppercase tracking-wider">Fleet Tracking</div>
            </div>
        </div>
    </div>

    {{-- Navigation --}}
    <nav class="flex-1 px-3 py-4 space-y-1">
        <div class="text-[9px] font-bold text-gray-400 uppercase tracking-widest px-3 mb-2">Main</div>

        <a href="{{ route('dashboard') }}"
           class="sidebar-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
            </svg>
            Dashboard
        </a>

        <a href="{{ route('livemap.index') }}"
           class="sidebar-link {{ request()->routeIs('livemap.*') ? 'active' : '' }}">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/>
            </svg>
            Live Map
        </a>

        <a href="{{ route('trips.index') }}"
           class="sidebar-link {{ request()->routeIs('trips.*') ? 'active' : '' }}">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
            </svg>
            Trip Management
        </a>

        <div class="text-[9px] font-bold text-gray-400 uppercase tracking-widest px-3 mb-2 mt-4">Master Data</div>

        <a href="{{ route('master.drivers.index') }}"
           class="sidebar-link {{ request()->routeIs('master.drivers.*') ? 'active' : '' }}">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
            </svg>
            Drivers
        </a>

        <a href="{{ route('master.vehicles.index') }}"
           class="sidebar-link {{ request()->routeIs('master.vehicles.*') ? 'active' : '' }}">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path d="M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0z"/>
                <path stroke-linecap="round" stroke-linejoin="round" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10l2 .001M13 16H9m4 0h4.5M13 16V9.5l3.5 1.5 2 3.5V16H17"/>
            </svg>
            Vehicles
        </a>

        <a href="{{ route('devices.index') }}"
           class="sidebar-link {{ request()->routeIs('devices.*') ? 'active' : '' }}">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"/>
            </svg>
            Devices
        </a>

        @if(app()->isLocal() || app()->environment('testing'))
        <div class="text-[9px] font-bold text-gray-400 uppercase tracking-widest px-3 mb-2 mt-4">Tools</div>
        <a href="/gps-tester"
           class="sidebar-link {{ request()->is('gps-tester') ? 'active' : '' }}">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
            </svg>
            GPS Tester
        </a>
        @endif
    </nav>

    {{-- Fleet Stats --}}
    <div class="px-3 py-4 border-t border-gray-100 flex-shrink-0">
        <div class="bg-gray-50 rounded-xl p-3">
            <div class="flex items-center justify-between mb-2">
                <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Fleet Active</span>
                <span id="sb-fleet-count" class="text-xs font-bold text-gray-700">—</span>
            </div>
            <div class="w-full bg-gray-200 rounded-full h-1.5">
                <div id="sb-fleet-bar" class="bg-green-500 h-1.5 rounded-full transition-all duration-500" style="width:0%"></div>
            </div>
        </div>
    </div>

    {{-- User --}}
    <div class="px-3 pb-4 flex-shrink-0">
        <div class="flex items-center gap-2.5 p-2.5 rounded-xl hover:bg-gray-50">
            <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center flex-shrink-0">
                <span class="text-sm font-bold text-green-700">
                    {{ substr(Auth::user()->name ?? 'A', 0, 1) }}
                </span>
            </div>
            <div class="flex-1 min-w-0">
                <div class="text-xs font-bold text-gray-900 truncate">{{ Auth::user()->name ?? 'Admin' }}</div>
                <div class="text-[10px] text-gray-400 truncate">{{ Auth::user()->email ?? '' }}</div>
            </div>
            <form action="{{ route('logout') }}" method="POST">
                @csrf
                <button type="submit"
                        class="text-gray-400 hover:text-red-500 transition-colors"
                        title="Logout">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                    </svg>
                </button>
            </form>
        </div>
    </div>
</aside>

{{-- Sidebar Overlay (mobile) --}}
<div id="sidebar-overlay" onclick="toggleSidebar()"></div>

{{-- ── MAIN ── --}}
<div class="flex-1 flex flex-col overflow-hidden">

    {{-- Header --}}
    <header class="bg-white border-b border-gray-100 px-4 md:px-6 py-3 flex items-center justify-between flex-shrink-0">
        <div class="flex items-center gap-3">
            {{-- Hamburger (mobile) --}}
            <button id="btn-sidebar-toggle"
                    class="md:hidden p-1.5 text-gray-500 hover:bg-gray-100 rounded-lg"
                    onclick="toggleSidebar()">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/>
                </svg>
            </button>

            <div class="hidden md:flex items-center gap-2 bg-gray-50 border border-gray-200 rounded-xl px-3 py-2 w-64 relative">
                <svg class="w-4 h-4 text-gray-400 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
                <input id="global-search-input"
                       type="text"
                       placeholder="Cari kendaraan, driver..."
                       autocomplete="off"
                       class="bg-transparent text-sm text-gray-700 placeholder-gray-400 outline-none w-full">
                <div id="global-search-results"
                     class="hidden absolute left-0 top-full mt-1 w-full bg-white border border-gray-200 rounded-xl shadow-lg z-[9999] overflow-hidden"
                     style="min-width:280px;max-height:320px;overflow-y:auto;">
                </div>
            </div>
        </div>

        <div class="flex items-center gap-2 md:gap-3">

            {{-- Map Switcher --}}
            <div class="flex bg-gray-100 rounded-xl p-1 gap-1">
                <button id="global-opt-osm"
                        onclick="globalSwitchMap('osm')"
                        class="flex items-center gap-1.5 px-2 md:px-3 py-1.5 rounded-lg text-xs font-semibold transition-all
                               {{ session('map_type','osm') === 'osm'
                                   ? 'bg-white text-green-700 shadow-sm'
                                   : 'text-gray-500 hover:text-gray-700' }}">
                    🌍 <span class="hidden md:inline">OpenStreetMap</span>
                </button>
                <button id="global-opt-gmaps"
                        onclick="globalSwitchMap('gmaps')"
                        class="flex items-center gap-1.5 px-2 md:px-3 py-1.5 rounded-lg text-xs font-semibold transition-all
                               {{ session('map_type','osm') === 'gmaps'
                                   ? 'bg-white text-red-600 shadow-sm'
                                   : 'text-gray-500 hover:text-gray-700' }}">
                    <svg viewBox="0 0 24 24" class="w-3.5 h-3.5 flex-shrink-0">
                        <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7z" fill="#ea4335"/>
                        <circle cx="12" cy="9" r="2.5" fill="white"/>
                    </svg>
                    <span class="hidden md:inline">Google Maps</span>
                </button>
            </div>

            {{-- WebSocket Status --}}
            <div id="ws-indicator"
                 title="WebSocket Status"
                 style="display:flex;align-items:center;gap:5px;padding:5px 9px;background:#f9fafb;border:1px solid #e5e7eb;border-radius:8px;">
                <span id="ws-dot" style="width:7px;height:7px;background:#9ca3af;border-radius:50%;transition:background .3s;flex-shrink:0;"></span>
                <span id="ws-text" style="font-size:10px;font-weight:600;color:#6b7280;">WS</span>
            </div>

            {{-- Active trucks badge --}}
            <div class="hidden md:flex items-center gap-2 px-3 py-2 bg-green-50 border border-green-200 rounded-xl">
                <div style="width:7px;height:7px;background:#22c55e;border-radius:50%;animation:pulse 2s infinite;"></div>
                <span id="hdr-active-text" class="text-xs font-bold text-green-700">— TRUCKS ACTIVE</span>
            </div>

            {{-- Bell --}}
            <div class="relative" id="notif-wrapper">
                <button id="notif-btn"
                        onclick="toggleNotif()"
                        class="relative w-9 h-9 flex items-center justify-center bg-gray-50 border border-gray-200 rounded-xl text-gray-500 hover:text-gray-700 hover:bg-gray-100 transition-all">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                    </svg>
                    <span id="notif-badge"
                          style="display:none;position:absolute;top:-4px;right:-4px;width:16px;height:16px;background:#ef4444;color:white;font-size:9px;font-weight:700;border-radius:50%;align-items:center;justify-content:center;">
                        <span id="notif-count">0</span>
                    </span>
                </button>

                {{-- Dropdown --}}
                <div id="notif-dropdown"
                     class="hidden absolute right-0 top-full mt-2 w-80 bg-white border border-gray-200 rounded-2xl shadow-xl z-[9999] overflow-hidden"
                     style="max-height:420px;">
                    <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100">
                        <span class="text-sm font-bold text-gray-900">Notifikasi</span>
                        <button onclick="markAllRead()" class="text-xs text-green-600 hover:text-green-700 font-semibold">Tandai semua dibaca</button>
                    </div>
                    <div id="notif-list" class="overflow-y-auto" style="max-height:360px;">
                        <div class="px-4 py-8 text-center text-sm text-gray-400">Memuat...</div>
                    </div>
                </div>
            </div>
        </div>
    </header>

    {{-- Content --}}
    <main class="flex-1 overflow-auto">
        @yield('content')
    </main>
</div>

{{-- ════════════════════════════════════════════════════════════ --}}
{{-- SCRIPTS GLOBAL                                              --}}
{{-- ════════════════════════════════════════════════════════════ --}}

{{-- Map switcher --}}
<script src="{{ asset('js/app-layout.js') }}"></script>

@stack('scripts')

</body>
</html>