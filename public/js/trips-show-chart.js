// ── Config dari window.__tripshow ────────────────────────────────
const monitoringEvents = window.__tripshow?.monitoringEvents ?? [];

// ════════════════════════════════════════════════════════════════
// GRAFIK DETEKSI KANTUK
// ════════════════════════════════════════════════════════════════

// Warna per event_type
function eventColor(type, alpha = 1) {
    if (type === 'alarm')         return `rgba(220,38,38,${alpha})`;
    if (type === 'drowsy')        return `rgba(249,115,22,${alpha})`;
    if (type === 'drowsy_warning')return `rgba(234,179,8,${alpha})`;
    return `rgba(34,197,94,${alpha})`;
}

const labels      = monitoringEvents.map(e => e.time);
const perclosData = monitoringEvents.map(e => e.perclos_value !== null ? +e.perclos_value : null);
const earData     = monitoringEvents.map(e => e.ear_value     !== null ? +e.ear_value     : null);
const marData     = monitoringEvents.map(e => e.mar_value     !== null ? +e.mar_value     : null);
const bgColors    = monitoringEvents.map(e => eventColor(e.event_type, 0.85));
const borderColors= monitoringEvents.map(e => eventColor(e.event_type, 1));

const chartDefaults = {
    responsive: true,
    interaction: { mode: 'index', intersect: false },
    plugins: {
        legend: { position: 'top', labels: { font: { size: 11 }, boxWidth: 14 } },
        tooltip: {
            callbacks: {
                afterBody: (items) => {
                    const idx = items[0]?.dataIndex;
                    const ev  = monitoringEvents[idx];
                    if (!ev) return '';
                    const lines = [`Tipe: ${ev.event_type.toUpperCase()}`];
                    if (ev.reasons) lines.push(`Alasan: ${ev.reasons}`);
                    if (ev.is_alarm) lines.push('🚨 ALARM AKTIF');
                    return lines;
                }
            }
        }
    },
    scales: {
        x: {
            ticks: { font: { size: 9 }, maxTicksLimit: 12, maxRotation: 0 },
            grid:  { color: '#f1f5f9' }
        },
        y: {
            min: 0, max: 1.3,
            ticks: { font: { size: 10 }, stepSize: 0.2 },
            grid:  { color: '#f1f5f9' }
        }
    }
};

// ── Chart 1: PERCLOS + event type background ──────────────────
new Chart(document.getElementById('chart-perclos'), {
    type: 'bar',
    data: {
        labels,
        datasets: [
            {
                label: 'PERCLOS',
                data: perclosData,
                backgroundColor: bgColors,
                borderColor:     borderColors,
                borderWidth: 1.5,
                borderRadius: 3,
                order: 2,
            },
            {
                label: 'Threshold Alarm (0.4)',
                data: monitoringEvents.map(() => 0.4),
                type: 'line',
                borderColor: 'rgba(220,38,38,0.5)',
                borderDash: [5, 4],
                borderWidth: 1.5,
                pointRadius: 0,
                fill: false,
                order: 1,
            }
        ]
    },
    options: {
        ...chartDefaults,
        plugins: {
            ...chartDefaults.plugins,
            tooltip: {
                ...chartDefaults.plugins.tooltip,
                callbacks: {
                    ...chartDefaults.plugins.tooltip.callbacks,
                    label: (ctx) => {
                        if (ctx.datasetIndex === 1) return null;
                        const val = ctx.parsed.y;
                        return ` PERCLOS: ${val !== null ? val.toFixed(3) : '—'}`;
                    }
                }
            }
        }
    }
});

// ── Chart 2: EAR & MAR line chart ────────────────────────────
new Chart(document.getElementById('chart-ear-mar'), {
    type: 'line',
    data: {
        labels,
        datasets: [
            {
                label: 'EAR (Eye Aspect Ratio)',
                data: earData,
                borderColor: '#3b82f6',
                backgroundColor: 'rgba(59,130,246,0.08)',
                borderWidth: 2,
                pointRadius: 3,
                pointBackgroundColor: borderColors,
                pointBorderColor: '#fff',
                pointBorderWidth: 1.5,
                tension: 0.3,
                fill: false,
                spanGaps: true,
            },
            {
                label: 'MAR (Mouth Aspect Ratio)',
                data: marData,
                borderColor: '#8b5cf6',
                backgroundColor: 'rgba(139,92,246,0.08)',
                borderWidth: 2,
                pointRadius: 3,
                pointBackgroundColor: borderColors,
                pointBorderColor: '#fff',
                pointBorderWidth: 1.5,
                tension: 0.3,
                fill: false,
                spanGaps: true,
            },
            {
                label: 'Threshold EAR (0.2)',
                data: monitoringEvents.map(() => 0.2),
                borderColor: 'rgba(59,130,246,0.35)',
                borderDash: [5, 4],
                borderWidth: 1.5,
                pointRadius: 0,
                fill: false,
            },
            {
                label: 'Threshold MAR (0.8)',
                data: monitoringEvents.map(() => 0.8),
                borderColor: 'rgba(139,92,246,0.35)',
                borderDash: [5, 4],
                borderWidth: 1.5,
                pointRadius: 0,
                fill: false,
            }
        ]
    },
    options: {
        ...chartDefaults,
        scales: {
            ...chartDefaults.scales,
            y: { ...chartDefaults.scales.y, max: 1.5 }
        }
    }
});