// ── Config dari window.__tripshow ────────────────────────────────
const monitoringEvents = window.__tripshow?.monitoringEvents ?? [];

// ════════════════════════════════════════════════════════════════
// GRAFIK DETEKSI KANTUK
// ════════════════════════════════════════════════════════════════

function eventColor(type, alpha = 1) {
    if (type === 'alarm')          return `rgba(220,38,38,${alpha})`;
    if (type === 'drowsy')         return `rgba(249,115,22,${alpha})`;
    if (type === 'drowsy_warning') return `rgba(234,179,8,${alpha})`;
    return `rgba(34,197,94,${alpha})`;
}

const labels       = monitoringEvents.map(e => e.time);
const perclosData  = monitoringEvents.map(e => e.perclos_value !== null ? +e.perclos_value : null);
const earData      = monitoringEvents.map(e => e.ear_value     !== null ? +e.ear_value     : null);
const marData      = monitoringEvents.map(e => e.mar_value     !== null ? +e.mar_value     : null);
const bgColors     = monitoringEvents.map(e => eventColor(e.event_type, 0.85));
const borderColors = monitoringEvents.map(e => eventColor(e.event_type, 1));

// Kondisi supir: 0=Normal, 1=Mengantuk, 2=Alarm
const conditionMap = { alarm: 2, drowsy: 1, drowsy_warning: 1 };
const conditionData = monitoringEvents.map(e => conditionMap[e.event_type] ?? 0);

const xScale = {
    ticks: { font: { size: 9 }, maxTicksLimit: 12, maxRotation: 0 },
    grid:  { color: '#f1f5f9' }
};

const commonTooltip = {
    callbacks: {
        afterBody: (items) => {
            const ev = monitoringEvents[items[0]?.dataIndex];
            if (!ev) return '';
            const lines = [`Tipe: ${ev.event_type.toUpperCase()}`];
            if (ev.reasons) lines.push(`Alasan: ${ev.reasons}`);
            if (ev.is_alarm) lines.push('🚨 ALARM AKTIF');
            return lines;
        }
    }
};

const legend = { position: 'top', labels: { font: { size: 11 }, boxWidth: 14 } };

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
                borderColor: borderColors,
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
        responsive: true,
        interaction: { mode: 'index', intersect: false },
        plugins: {
            legend,
            tooltip: {
                callbacks: {
                    ...commonTooltip.callbacks,
                    label: (ctx) => {
                        if (ctx.datasetIndex === 1) return null;
                        return ` PERCLOS: ${ctx.parsed.y !== null ? ctx.parsed.y.toFixed(3) : '—'}`;
                    }
                }
            }
        },
        scales: {
            x: xScale,
            y: { min: 0, max: 1.3, ticks: { font: { size: 10 }, stepSize: 0.2 }, grid: { color: '#f1f5f9' } }
        }
    }
});

// ── Chart 2: Kondisi Supir (status timeline) ──────────────────
new Chart(document.getElementById('chart-condition'), {
    type: 'bar',
    data: {
        labels,
        datasets: [{
            label: 'Kondisi Supir',
            data: conditionData,
            backgroundColor: bgColors,
            borderColor: borderColors,
            borderWidth: 1.5,
            borderRadius: 3,
        }]
    },
    options: {
        responsive: true,
        interaction: { mode: 'index', intersect: false },
        plugins: {
            legend,
            tooltip: {
                callbacks: {
                    ...commonTooltip.callbacks,
                    label: (ctx) => {
                        const labels = ['Normal', 'Mengantuk', 'Alarm/Bahaya'];
                        return ` Status: ${labels[ctx.parsed.y] ?? '—'}`;
                    }
                }
            }
        },
        scales: {
            x: xScale,
            y: {
                min: 0, max: 2,
                ticks: {
                    font: { size: 10 },
                    stepSize: 1,
                    callback: (val) => ['Normal', 'Mengantuk', 'Alarm'][val] ?? val,
                },
                grid: { color: '#f1f5f9' }
            }
        }
    }
});

// ── Chart 3: EAR (Eye Aspect Ratio) ──────────────────────────
new Chart(document.getElementById('chart-ear'), {
    type: 'line',
    data: {
        labels,
        datasets: [
            {
                label: 'EAR (Eye Aspect Ratio)',
                data: earData,
                borderColor: '#3b82f6',
                backgroundColor: 'rgba(59,130,246,0.07)',
                borderWidth: 2,
                pointRadius: 3,
                pointBackgroundColor: borderColors,
                pointBorderColor: '#fff',
                pointBorderWidth: 1.5,
                tension: 0.3,
                fill: true,
                spanGaps: true,
            },
            {
                label: 'Threshold (0.2 — mata mulai menutup)',
                data: monitoringEvents.map(() => 0.2),
                borderColor: 'rgba(220,38,38,0.45)',
                borderDash: [5, 4],
                borderWidth: 1.5,
                pointRadius: 0,
                fill: false,
            }
        ]
    },
    options: {
        responsive: true,
        interaction: { mode: 'index', intersect: false },
        plugins: { legend, tooltip: commonTooltip },
        scales: {
            x: xScale,
            y: { min: 0, max: 0.6, ticks: { font: { size: 10 }, stepSize: 0.1 }, grid: { color: '#f1f5f9' } }
        }
    }
});

// ── Chart 4: MAR (Mouth Aspect Ratio) ────────────────────────
new Chart(document.getElementById('chart-mar'), {
    type: 'line',
    data: {
        labels,
        datasets: [
            {
                label: 'MAR (Mouth Aspect Ratio)',
                data: marData,
                borderColor: '#8b5cf6',
                backgroundColor: 'rgba(139,92,246,0.07)',
                borderWidth: 2,
                pointRadius: 3,
                pointBackgroundColor: borderColors,
                pointBorderColor: '#fff',
                pointBorderWidth: 1.5,
                tension: 0.3,
                fill: true,
                spanGaps: true,
            },
            {
                label: 'Threshold (0.8 — mulut terbuka lebar/menguap)',
                data: monitoringEvents.map(() => 0.8),
                borderColor: 'rgba(139,92,246,0.45)',
                borderDash: [5, 4],
                borderWidth: 1.5,
                pointRadius: 0,
                fill: false,
            }
        ]
    },
    options: {
        responsive: true,
        interaction: { mode: 'index', intersect: false },
        plugins: { legend, tooltip: commonTooltip },
        scales: {
            x: xScale,
            y: { min: 0, max: 1.5, ticks: { font: { size: 10 }, stepSize: 0.2 }, grid: { color: '#f1f5f9' } }
        }
    }
});
