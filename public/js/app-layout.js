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

document.addEventListener('click', function(e) {
    const wrapper = document.getElementById('notif-wrapper');
    if (wrapper && !wrapper.contains(e.target) && notifOpen) {
        notifOpen = false;
        document.getElementById('notif-dropdown')?.classList.add('hidden');
    }
});

// ── Render satu item alert ───────────────────────────────────────
function renderAlert(a) {
    const isRead    = !!a.is_read;
    const iconColor = a.severity === 'critical' ? '#dc2626'
                    : (a.severity === 'warning'  ? '#f97316'
                    :  (a.alert_type === 'trip_completed' ? '#16a34a' : '#6b7280'));

    const bgColor = isRead ? 'transparent'
                  : (a.severity === 'critical' ? '#fef2f2'
                  : (a.severity === 'warning'  ? '#fff7ed'
                  : (a.alert_type === 'trip_completed' ? '#f0fdf4' : '#f9fafb')));

    const time = a.triggered_at
        ? new Date(a.triggered_at).toLocaleString('id-ID', { day:'2-digit', month:'short', hour:'2-digit', minute:'2-digit' })
        : '';

    let href = '#';
    if (a.trip_id)       href = `/trips/${a.trip_id}`;
    else if (a.vehicle_id) href = `/live-map/${a.vehicle_id}`;

    const iconMap = {
        drowsy_driver:   '\u{1F634}',
        vehicle_stopped: '\u{1F69B}',
        trip_completed:  '\u2705',
        vehicle_offline: '\uD83D\uDCE1',
    };
    const icon = iconMap[a.alert_type] ?? '\uD83D\uDD14';

    return `<a href="${href}"
        id="notif-item-${a.id}"
        onclick="markOneRead(event, ${a.id}, '${href}')"
        style="display:flex;gap:10px;padding:12px 16px;border-bottom:1px solid #f3f4f6;background:${bgColor};text-decoration:none;cursor:pointer;transition:background .15s;"
        onmouseover="this.style.filter='brightness(.97)'"
        onmouseout="this.style.filter='none'">
        <div style="width:32px;height:32px;border-radius:10px;background:${iconColor}18;display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0;margin-top:1px;">${icon}</div>
        <div style="flex:1;min-width:0;">
            <div style="font-size:12px;font-weight:${isRead ? 500 : 700};color:${isRead ? '#6b7280' : '#111827'};line-height:1.35;margin-bottom:2px;">${a.title}</div>
            <div style="font-size:11px;color:${isRead ? '#9ca3af' : '#6b7280'};line-height:1.4;margin-bottom:3px;">${a.message ?? ''}</div>
            <div style="font-size:10px;color:#9ca3af;">${time}</div>
        </div>
        <div style="flex-shrink:0;align-self:flex-start;margin-top:6px;">
            ${!isRead ? `<div style="width:7px;height:7px;background:${iconColor};border-radius:50%;"></div>` : '<div style="width:7px;"></div>'}
        </div>
    </a>`;
}

// ── Load semua notif 30 hari (read + unread) ─────────────────────
async function loadNotifs() {
    const list = document.getElementById('notif-list');
    if (!list) return;
    list.innerHTML = '<div style="padding:1.5rem;text-align:center;color:#9ca3af;font-size:12px;">Memuat...</div>';
    try {
        const data = await fetch('/api/internal/alerts').then(r => r.json());
        if (!data.length) {
            list.innerHTML = '<div style="padding:2rem 1rem;text-align:center;color:#9ca3af;font-size:13px;">Belum ada notifikasi.</div>';
            return;
        }
        const unreadCount = data.filter(a => !a.is_read).length;
        list.innerHTML = data.map(renderAlert).join('');
        const hdr = document.getElementById('notif-header-unread');
        if (hdr) {
            hdr.textContent = unreadCount > 0 ? `${unreadCount} belum dibaca` : 'Semua sudah dibaca';
            hdr.style.color = unreadCount > 0 ? '#dc2626' : '#9ca3af';
        }
    } catch(e) {
        list.innerHTML = '<div style="padding:1rem;text-align:center;color:#ef4444;font-size:12px;">Gagal memuat notifikasi.</div>';
    }
}

// ── Klik satu notif → mark read, perbarui tampilan (tidak hilang) ──
async function markOneRead(event, alertId, href) {
    event.preventDefault();
    // Optimistic UI — langsung perbarui tampilan tanpa tunggu server
    const item = document.getElementById('notif-item-' + alertId);
    if (item) {
        item.style.background = 'transparent';
        const children = item.querySelectorAll('div');
        // Dot (elemen terakhir dengan border-radius 50%)
        item.querySelectorAll('[style*="border-radius:50%"]').forEach(d => d.style.background = 'transparent');
    }
    try {
        await fetch('/api/internal/alerts/' + alertId + '/read', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }
        });
    } catch(e) {}
    pollUnreadCount();
    if (href && href !== '#') {
        notifOpen = false;
        document.getElementById('notif-dropdown')?.classList.add('hidden');
        window.location.href = href;
    }
}

// ── Tandai semua dibaca → update UI semua item (tidak hilang) ────
async function markAllRead() {
    try {
        await fetch('/api/internal/alerts/read-all', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }
        });
    } catch(e) {}
    // Update semua item yang sedang ditampilkan
    document.querySelectorAll('[id^="notif-item-"]').forEach(item => {
        item.style.background = 'transparent';
        item.querySelectorAll('[style*="border-radius:50%"]').forEach(d => d.style.background = 'transparent');
    });
    const badge = document.getElementById('notif-badge');
    if (badge) badge.style.display = 'none';
    const hdr = document.getElementById('notif-header-unread');
    if (hdr) { hdr.textContent = 'Semua sudah dibaca'; hdr.style.color = '#9ca3af'; }
}

// ── Poll badge unread count (endpoint ringan) ─────────────────────
async function pollUnreadCount() {
    try {
        const data  = await fetch('/api/internal/alerts/unread-count').then(r => r.json());
        const count = data.count ?? 0;
        const badge   = document.getElementById('notif-badge');
        const countEl = document.getElementById('notif-count');
        if (badge && countEl) {
            countEl.textContent = count > 9 ? '9+' : count;
            badge.style.display = count > 0 ? 'flex' : 'none';
        }
    } catch(e) {}
}

// Init
pollUnreadCount();
setInterval(pollUnreadCount, 30000);

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