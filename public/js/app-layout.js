function globalSwitchMap(type) {
    fetch('/map-preference', {
        method:  'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
        },
        body: JSON.stringify({ type }),
    }).then(() => window.location.reload());
}



function toggleSidebar() {
    const sidebar = document.querySelector('aside');
    const overlay = document.getElementById('sidebar-overlay');
    const isOpen  = sidebar.classList.contains('open');
    sidebar.classList.toggle('open', !isOpen);
    overlay.classList.toggle('show', !isOpen);
}
window.addEventListener('resize', () => {
    if (window.innerWidth >= 768) {
        document.querySelector('aside')?.classList.remove('open');
        document.getElementById('sidebar-overlay')?.classList.remove('show');
    }
});



document.addEventListener('DOMContentLoaded', function () {
    if (typeof window.Echo === 'undefined') {
        console.warn('Laravel Echo tidak tersedia. Pastikan npm run build sudah dijalankan.');
        return;
    }

    // ── WebSocket connection indicator ────────────────────────────
    const wsDot  = document.getElementById('ws-dot');
    const wsText = document.getElementById('ws-text');

    function setWSStatus(connected) {
        if (wsDot)  wsDot.style.background  = connected ? '#22c55e' : '#ef4444';
        if (wsText) wsText.textContent       = connected ? 'Live'    : 'Off';
        if (wsText) wsText.style.color       = connected ? '#15803d' : '#b91c1c';
    }

    // Monitor koneksi Reverb
    window.Echo.connector.pusher.connection.bind('connected',    () => setWSStatus(true));
    window.Echo.connector.pusher.connection.bind('disconnected', () => setWSStatus(false));
    window.Echo.connector.pusher.connection.bind('error', (err) => {
        console.error('WS Error:', err);
        setWSStatus(false);
    });

    window.Echo.connector.pusher.connection.bind('state_change', (states) => {
        console.log('WS State:', states.previous, '→', states.current);
        setWSStatus(states.current === 'connected');
    });

    // ── Listen fleet-tracking channel ────────────────────────────
    window.Echo.channel('fleet-tracking')

        .listen('.vehicle.position.updated', (data) => {
            // Update dashboard marker
            if (typeof window.updateDashboardMarker === 'function') {
                window.updateDashboardMarker(data);
            }
            // Update livemap marker & panel
            if (typeof window.updateLivemapMarker  === 'function') window.updateLivemapMarker(data);
            if (typeof window.updateLivemapPanel   === 'function') window.updateLivemapPanel(data);
        })

        .listen('.fleet.status.updated', (data) => {
            ['cnt-moving','cnt-idle','cnt-offline'].forEach((id, i) => {
                const el = document.getElementById(id);
                if (el) el.textContent = [data.moving, data.idle, data.offline][i] || 0;
            });
            if (typeof window.updateDashboardFleet === 'function') {
                window.updateDashboardFleet(data);
            }
            // Header badge
            const active  = Number(data.moving || 0) + Number(data.idle || 0);
            const total   = Number(data.total_vehicles || 0);
            const hdrEl   = document.getElementById('hdr-active-text');
            if (hdrEl) hdrEl.textContent = `${active} TRUCKS ACTIVE`;
            // Sidebar fleet bar
            const cntEl = document.getElementById('sb-fleet-count');
            const barEl = document.getElementById('sb-fleet-bar');
            if (cntEl) cntEl.textContent = `${active}/${total}`;
            if (barEl && total > 0) barEl.style.width = `${Math.round((active/total)*100)}%`;
        })

        .listen('.trip.status.updated', (data) => {
            if (typeof window.handleTripUpdate === 'function') {
                window.handleTripUpdate(data);
            }
        });
});

// ── Fleet summary — init saat halaman load ────────────────────────
fetch('/api/internal/fleet-summary')
    .then(r => r.json())
    .then(d => {
        const active = Number(d.moving||0) + Number(d.idle||0);
        const total  = Number(d.total_vehicles||0);
        const hdrEl  = document.getElementById('hdr-active-text');
        const cntEl  = document.getElementById('sb-fleet-count');
        const barEl  = document.getElementById('sb-fleet-bar');
        if (hdrEl) hdrEl.textContent = `${active} TRUCKS ACTIVE`;
        if (cntEl) cntEl.textContent = `${active}/${total}`;
        if (barEl && total > 0) barEl.style.width = `${Math.round((active/total)*100)}%`;
    }).catch(() => {});

// ════════════════════════════════════════════════════════════════
// NOTIFICATION SYSTEM
// ════════════════════════════════════════════════════════════════
let notifOpen = false;

function toggleNotif() {
    notifOpen = !notifOpen;
    const dd = document.getElementById('notif-dropdown');
    if (!dd) return;
    dd.classList.toggle('hidden', !notifOpen);
    if (notifOpen) loadNotifs();
}

// Tutup dropdown saat klik luar
document.addEventListener('click', function(e) {
    const wrapper = document.getElementById('notif-wrapper');
    if (wrapper && !wrapper.contains(e.target) && notifOpen) {
        notifOpen = false;
        document.getElementById('notif-dropdown')?.classList.add('hidden');
    }
});

async function loadNotifs() {
    const list = document.getElementById('notif-list');
    if (!list) return;
    try {
        const data = await fetch('/api/internal/alerts').then(r => r.json());
        if (!data.length) {
            list.innerHTML = '<div style="padding:2rem 1rem;text-align:center;color:#9ca3af;font-size:13px;">Tidak ada notifikasi baru.</div>';
            return;
        }
        list.innerHTML = data.map(a => {
            const iconColor = a.severity === 'critical' ? '#dc2626' : (a.severity === 'warning' ? '#f97316' : '#6b7280');
            const bgColor   = a.severity === 'critical' ? '#fef2f2' : (a.severity === 'warning' ? '#fff7ed' : '#f9fafb');
            const time      = a.triggered_at
                ? new Date(a.triggered_at).toLocaleString('id-ID', { day:'2-digit', month:'short', hour:'2-digit', minute:'2-digit' })
                : '';

            // Tentukan URL tujuan berdasarkan alert_type
            let href = '#';
            if (a.trip_id) {
                href = `/trips/${a.trip_id}`;
            } else if (a.alert_type === 'drowsy_driver' && a.vehicle_id) {
                href = `/live-map/${a.vehicle_id}`;
            } else if (a.alert_type === 'vehicle_stopped' && a.vehicle_id) {
                href = `/live-map/${a.vehicle_id}`;
            } else if (a.vehicle_id) {
                href = `/live-map/${a.vehicle_id}`;
            }

            const icon = a.alert_type === 'drowsy_driver'
                ? '😴'
                : (a.alert_type === 'vehicle_stopped' ? '🚛' : '⚠️');

            return `<a href="${href}"
                onclick="markOneRead(event, ${a.id}, '${href}')"
                style="display:flex;gap:10px;padding:12px 16px;border-bottom:1px solid #f3f4f6;background:${bgColor};text-decoration:none;cursor:pointer;transition:filter .15s;"
                onmouseover="this.style.filter='brightness(.97)'"
                onmouseout="this.style.filter='brightness(1)'">
                <div style="width:30px;height:30px;border-radius:10px;background:${iconColor}18;display:flex;align-items:center;justify-content:center;font-size:15px;flex-shrink:0;">${icon}</div>
                <div style="flex:1;min-width:0;">
                    <div style="font-size:12px;font-weight:700;color:#111827;margin-bottom:2px;line-height:1.3;">${a.title}</div>
                    <div style="font-size:11px;color:#6b7280;line-height:1.4;margin-bottom:3px;">${a.message ?? ''}</div>
                    <div style="font-size:10px;color:#9ca3af;">${time}</div>
                </div>
                <div style="flex-shrink:0;align-self:center;">
                    <div style="width:6px;height:6px;background:${iconColor};border-radius:50%;"></div>
                </div>
            </a>`;
        }).join('');
    } catch(e) {
        list.innerHTML = '<div style="padding:1rem;text-align:center;color:#ef4444;font-size:12px;">Gagal memuat notifikasi.</div>';
    }
}

async function markOneRead(event, alertId, href) {
    event.preventDefault();
    try {
        await fetch(`/api/internal/alerts/${alertId}/read`, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }
        });
    } catch(e) {}
    // Tutup dropdown lalu navigasi
    notifOpen = false;
    document.getElementById('notif-dropdown')?.classList.add('hidden');
    pollUnreadCount();
    if (href && href !== '#') window.location.href = href;
}

async function markAllRead() {
    try {
        await fetch('/api/internal/alerts/read-all', { method:'POST', headers:{'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content} });
        const badge = document.getElementById('notif-badge');
        if (badge) badge.style.display = 'none';
        loadNotifs();
    } catch(e) {}
}

// Polling jumlah unread tiap 30 detik
async function pollUnreadCount() {
    try {
        const data = await fetch('/api/internal/alerts').then(r => r.json());
        const count = Array.isArray(data) ? data.length : 0;
        const badge = document.getElementById('notif-badge');
        const countEl = document.getElementById('notif-count');
        if (badge && countEl) {
            countEl.textContent = count > 9 ? '9+' : count;
            badge.style.display = count > 0 ? 'flex' : 'none';
        }
    } catch(e) {}
}

// ── Global Search ─────────────────────────────────────────────────
(function() {
    const inp = document.getElementById('global-search-input');
    const res = document.getElementById('global-search-results');
    if (!inp || !res) return;

    let _t;
    inp.addEventListener('input', function() {
        clearTimeout(_t);
        const q = this.value.trim();
        if (q.length < 2) { res.classList.add('hidden'); return; }
        _t = setTimeout(() => fetchSearch(q), 300);
    });

    inp.addEventListener('focus', function() {
        if (this.value.trim().length >= 2) res.classList.remove('hidden');
    });

    document.addEventListener('click', function(e) {
        if (!inp.contains(e.target) && !res.contains(e.target)) {
            res.classList.add('hidden');
        }
    });

    async function fetchSearch(q) {
        try {
            const [vehicles, drivers] = await Promise.all([
                fetch(`/api/internal/search?q=${encodeURIComponent(q)}&type=vehicle`).then(r => r.json()),
                fetch(`/api/internal/search?q=${encodeURIComponent(q)}&type=driver`).then(r => r.json()),
            ]);

            res.innerHTML = '';
            let hasResult = false;

            if (vehicles.length) {
                hasResult = true;
                res.innerHTML += `<div style="padding:6px 12px;font-size:10px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:.08em;">Kendaraan</div>`;
                vehicles.forEach(v => {
                    const stColor = v.status === 'moving' ? '#15803d' : (v.status === 'idle' ? '#c2410c' : '#9ca3af');
                    res.innerHTML += `<a href="/live-map/${v.id}" style="display:flex;align-items:center;gap:10px;padding:9px 12px;text-decoration:none;border-bottom:1px solid #f3f4f6;"
                        onmouseover="this.style.background='#f9fafb'" onmouseout="this.style.background='transparent'">
                        <span style="font-size:16px;">🚛</span>
                        <div style="flex:1;min-width:0;">
                            <div style="font-size:13px;font-weight:600;color:#111827;">${v.name}</div>
                            <div style="font-size:11px;color:#9ca3af;">${v.license_plate}</div>
                        </div>
                        <span style="font-size:10px;font-weight:700;color:${stColor};">${v.status.toUpperCase()}</span>
                    </a>`;
                });
            }

            if (drivers.length) {
                hasResult = true;
                res.innerHTML += `<div style="padding:6px 12px;font-size:10px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:.08em;">Supir</div>`;
                drivers.forEach(d => {
                    res.innerHTML += `<a href="/master/drivers/${d.id}/edit" style="display:flex;align-items:center;gap:10px;padding:9px 12px;text-decoration:none;border-bottom:1px solid #f3f4f6;"
                        onmouseover="this.style.background='#f9fafb'" onmouseout="this.style.background='transparent'">
                        <div style="width:32px;height:32px;background:#dcfce7;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;color:#15803d;flex-shrink:0;">${d.full_name.charAt(0).toUpperCase()}</div>
                        <div style="flex:1;min-width:0;">
                            <div style="font-size:13px;font-weight:600;color:#111827;">${d.full_name}</div>
                            <div style="font-size:11px;color:#9ca3af;">${d.driver_code}</div>
                        </div>
                    </a>`;
                });
            }

            if (!hasResult) {
                res.innerHTML = `<div style="padding:1rem;text-align:center;color:#9ca3af;font-size:13px;">Tidak ditemukan.</div>`;
            }

            res.classList.remove('hidden');
        } catch(e) {
            res.innerHTML = `<div style="padding:1rem;text-align:center;color:#ef4444;font-size:12px;">Gagal mencari.</div>`;
            res.classList.remove('hidden');
        }
    }
})();

// Update badge saat ada WS event drowsy/alarm
document.addEventListener('DOMContentLoaded', function() {
    if (typeof window.Echo !== 'undefined') {
        window.Echo.channel('fleet-tracking')
            .listen('.alert.created', () => pollUnreadCount());
    }
});