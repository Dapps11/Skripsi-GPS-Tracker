<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Greenfields Logistics')</title>

    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Plus Jakarta Sans', 'sans-serif'] },
                    colors: {
                        brand: { DEFAULT: '#22c55e', dark: '#16a34a', light: '#dcfce7', 50: '#f0fdf4' }
                    }
                }
            }
        }
    </script>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <style>
        [x-cloak] { display: none !important; }
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        .card { background: white; border-radius: 1rem; border: 1px solid #f1f5f9; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
        .sidebar-link { display: flex; align-items: center; gap: 0.75rem; padding: 0.625rem 0.75rem; border-radius: 0.5rem; font-size: 0.875rem; font-weight: 500; color: #4b5563; transition: all 0.15s; text-decoration: none; }
        .sidebar-link:hover { background: #f0fdf4; color: #16a34a; }
        .sidebar-link.active { background: #22c55e; color: white; }
        .sidebar-link.active:hover { background: #16a34a; color: white; }
        .badge-moving  { display:inline-flex;align-items:center;gap:0.375rem;padding:0.25rem 0.625rem;border-radius:9999px;font-size:0.75rem;font-weight:600;background:#dcfce7;color:#15803d; }
        .badge-idle    { display:inline-flex;align-items:center;gap:0.375rem;padding:0.25rem 0.625rem;border-radius:9999px;font-size:0.75rem;font-weight:600;background:#ffedd5;color:#c2410c; }
        .badge-offline { display:inline-flex;align-items:center;gap:0.375rem;padding:0.25rem 0.625rem;border-radius:9999px;font-size:0.75rem;font-weight:600;background:#fee2e2;color:#b91c1c; }
        .badge-online  { display:inline-flex;align-items:center;gap:0.375rem;padding:0.25rem 0.625rem;border-radius:9999px;font-size:0.75rem;font-weight:600;background:#dcfce7;color:#15803d; }
    </style>

    @stack('styles')
</head>
<body class="bg-gray-50 antialiased">

<div class="flex h-screen overflow-hidden" x-data="{}">

    {{-- SIDEBAR --}}
    <aside class="w-60 bg-white border-r border-gray-100 flex flex-col flex-shrink-0">

        {{-- Logo --}}
        <div class="px-4 py-5 border-b border-gray-100">
            <div class="flex items-center gap-2.5">
                <div class="w-9 h-9 bg-green-500 rounded-xl flex items-center justify-center">
                    <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path d="M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10l2 .001M13 16H9m4 0h4.5M13 16V9.5l3.5 1.5 2 3.5V16H17"/>
                    </svg>
                </div>
                <div>
                    <div class="text-sm font-bold text-gray-900 leading-none">Greenfields</div>
                    <div class="text-[10px] text-green-500 font-semibold uppercase tracking-wide mt-0.5">Live Fleet Tracking</div>
                </div>
            </div>
        </div>

        {{-- Operation Center --}}
        <div class="px-4 py-3 border-b border-gray-100">
            <div class="flex items-center gap-2">
                <div class="w-8 h-8 bg-green-50 rounded-lg flex items-center justify-center">
                    <svg class="w-4 h-4 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                    </svg>
                </div>
                <div>
                    <div class="text-xs font-semibold text-gray-900">Operation Center</div>
                    <div class="text-[10px] text-gray-400">Central Hub</div>
                </div>
            </div>
        </div>

        {{-- Nav --}}
        <nav class="flex-1 p-3 space-y-0.5 overflow-y-auto">
            <a href="{{ route('dashboard') }}"
               class="sidebar-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/>
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

            <a href="{{ route('devices.index') }}"
               class="sidebar-link {{ request()->routeIs('devices.*') ? 'active' : '' }}">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 3H5a2 2 0 00-2 2v4m6-6h10a2 2 0 012 2v4M9 3v18m0 0h10a2 2 0 002-2V9M9 21H5a2 2 0 01-2-2V9m0 0h18"/>
                </svg>
                Device
            </a>

            {{-- Master Data dropdown --}}
            <div x-data="{ open: {{ request()->routeIs('master.*') ? 'true' : 'false' }} }">
                <button @click="open = !open"
                        class="sidebar-link w-full {{ request()->routeIs('master.*') ? 'bg-green-50 text-green-700' : '' }}">
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                    </svg>
                    <span class="flex-1 text-left">Master Data</span>
                    <svg class="w-3 h-3 transition-transform duration-200" :class="open ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>
                <div x-show="open" x-cloak class="ml-7 mt-0.5 space-y-0.5">
                    <a href="{{ route('master.drivers.index') }}"
                       class="sidebar-link text-xs {{ request()->routeIs('master.drivers.*') ? 'active' : '' }}">
                        <span>👤</span> Supir
                    </a>
                    <a href="{{ route('master.vehicles.index') }}"
                       class="sidebar-link text-xs {{ request()->routeIs('master.vehicles.*') ? 'active' : '' }}">
                        <span>🚛</span> Kendaraan
                    </a>
                </div>
            </div>
        </nav>

        {{-- Fleet Status --}}
        <div class="p-3 border-t border-gray-100">
            <div class="bg-gray-50 rounded-xl p-3 mb-3">
                <div class="text-[10px] font-bold text-green-600 uppercase tracking-wider mb-2">Fleet Status</div>
                <div class="flex items-center justify-between text-xs mb-1.5">
                    <span class="text-gray-500">Active Vehicle</span>
                    <span id="sb-fleet-count" class="font-bold text-gray-900">–/–</span>
                </div>
                <div class="h-1.5 bg-gray-200 rounded-full overflow-hidden">
                    <div id="sb-fleet-bar" class="h-full bg-green-500 rounded-full transition-all duration-500" style="width:0%"></div>
                </div>
            </div>

            {{-- User --}}
            <div class="flex items-center gap-2">
                <div class="w-7 h-7 bg-green-100 rounded-full flex items-center justify-center flex-shrink-0">
                    <svg class="w-3.5 h-3.5 text-green-700" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                    </svg>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="text-xs font-semibold text-gray-900 truncate">{{ auth()->user()->name }}</div>
                    <div class="text-[10px] text-gray-400 capitalize">{{ auth()->user()->role }}</div>
                </div>
                <form action="{{ route('logout') }}" method="POST">
                    @csrf
                    <button type="submit" class="text-gray-400 hover:text-red-500 transition-colors" title="Logout">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                        </svg>
                    </button>
                </form>
            </div>
        </div>
    </aside>

    {{-- MAIN --}}
    <div class="flex-1 flex flex-col overflow-hidden">

        {{-- Header --}}
        <header class="h-14 bg-white border-b border-gray-100 flex items-center justify-between px-6 flex-shrink-0">
            <div class="flex items-center gap-3 flex-1">
                <div class="relative">
                    <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <input type="text" placeholder="Search truck ID, driver, or location..."
                           class="pl-10 pr-4 py-2 text-sm bg-gray-50 border border-gray-200 rounded-lg w-80 focus:outline-none focus:border-green-500 focus:bg-white transition-all">
                </div>
            </div>
            <div class="flex items-center gap-3">
                <div class="flex items-center gap-2 px-3 py-1.5 bg-green-50 rounded-full">
                    <span class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></span>
                    <span class="text-xs font-semibold text-green-700" id="hdr-active-text">– TRUCKS ACTIVE</span>
                </div>
                <button class="relative p-2 text-gray-500 hover:text-gray-900 hover:bg-gray-100 rounded-lg transition-all">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                    </svg>
                    <span id="hdr-alert-dot" class="absolute top-1 right-1 w-2 h-2 bg-red-500 rounded-full hidden"></span>
                </button>
                <div class="w-8 h-8 bg-gray-100 rounded-full flex items-center justify-center">
                    <svg class="w-4 h-4 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                    </svg>
                </div>
            </div>
        </header>

        {{-- Content --}}
        <main class="flex-1 overflow-auto">
            @if(session('success'))
            <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)"
                 class="mx-6 mt-4 p-3 bg-green-50 border border-green-200 rounded-xl text-sm text-green-700 flex items-center justify-between gap-2">
                <div class="flex items-center gap-2">
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                    </svg>
                    {{ session('success') }}
                </div>
                <button @click="show = false" class="text-green-500 hover:text-green-700">✕</button>
            </div>
            @endif

            @yield('content')
        </main>
    </div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
async function globalPollFleet() {
    try {
        const res  = await fetch('/api/internal/fleet-summary');
        const data = await res.json();
        const active = (parseInt(data.moving) || 0) + (parseInt(data.idle) || 0);
        const total  = parseInt(data.total_vehicles) || 0;

        const txt = document.getElementById('hdr-active-text');
        const cnt = document.getElementById('sb-fleet-count');
        const bar = document.getElementById('sb-fleet-bar');
        if (txt) txt.textContent = `${active} TRUCKS ACTIVE`;
        if (cnt) cnt.textContent = `${active}/${total}`;
        if (bar && total > 0) bar.style.width = `${Math.round((active / total) * 100)}%`;
    } catch(e) {}
}

async function globalPollAlerts() {
    try {
        const res  = await fetch('/api/internal/alerts');
        const data = await res.json();
        const dot  = document.getElementById('hdr-alert-dot');
        if (dot) dot.classList.toggle('hidden', data.length === 0);
    } catch(e) {}
}

globalPollFleet();
globalPollAlerts();
setInterval(globalPollFleet,  15000);
setInterval(globalPollAlerts, 30000);
</script>
@stack('scripts')
</body>
</html>