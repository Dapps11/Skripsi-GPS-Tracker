// ── Config dari window.__dashboard ──────────────────────────────
const chartDays   = window.__dashboard?.chartDays   ?? [];
const chartTrips  = window.__dashboard?.chartTrips  ?? [];
const chartKm     = window.__dashboard?.chartKm     ?? [];
const chartDrowsy = window.__dashboard?.chartDrowsy ?? [];
const chartAlarms = window.__dashboard?.chartAlarms ?? [];


const gridColor = '#f1f5f9';
const tickFont  = { size: 10 };

// ── Chart 1: Trip & Jarak per hari ───────────────────────────────
new Chart(document.getElementById('chart-trip-daily'), {
    data: {
        labels: chartDays,
        datasets: [
            {
                type: 'bar',
                label: 'Trip Selesai',
                data: chartTrips,
                backgroundColor: 'rgba(34,197,94,.75)',
                borderRadius: 5,
                yAxisID: 'y',
                order: 2,
            },
            {
                type: 'line',
                label: 'Jarak (km)',
                data: chartKm,
                borderColor: '#2563eb',
                backgroundColor: 'rgba(37,99,235,.08)',
                borderWidth: 2,
                pointRadius: 3,
                pointBackgroundColor: '#2563eb',
                tension: 0.35,
                fill: true,
                yAxisID: 'y2',
                order: 1,
            }
        ]
    },
    options: {
        responsive: true,
        interaction: { mode: 'index', intersect: false },
        plugins: {
            legend: { position: 'top', labels: { font: { size: 11 }, boxWidth: 12, padding: 12 } }
        },
        scales: {
            x:  { grid: { color: gridColor }, ticks: { font: tickFont } },
            y:  { grid: { color: gridColor }, ticks: { font: tickFont, stepSize: 1 },
                  title: { display: true, text: 'Trip', font: { size: 10 }, color: '#9ca3af' }, beginAtZero: true },
            y2: { position: 'right', grid: { drawOnChartArea: false }, ticks: { font: tickFont },
                  title: { display: true, text: 'km', font: { size: 10 }, color: '#9ca3af' }, beginAtZero: true }
        }
    }
});

// ── Chart 2: Drowsy events per hari ──────────────────────────────
new Chart(document.getElementById('chart-drowsy-daily'), {
    type: 'bar',
    data: {
        labels: chartDays,
        datasets: [
            {
                label: 'Drowsy Events',
                data: chartDrowsy,
                backgroundColor: 'rgba(249,115,22,.7)',
                borderRadius: 5,
                stack: 's1',
            },
            {
                label: 'Alarm Aktif',
                data: chartAlarms,
                backgroundColor: 'rgba(220,38,38,.85)',
                borderRadius: 5,
                stack: 's1',
            }
        ]
    },
    options: {
        responsive: true,
        interaction: { mode: 'index', intersect: false },
        plugins: {
            legend: { position: 'top', labels: { font: { size: 11 }, boxWidth: 12, padding: 12 } }
        },
        scales: {
            x: { grid: { color: gridColor }, ticks: { font: tickFont }, stacked: true },
            y: { grid: { color: gridColor }, ticks: { font: tickFont, stepSize: 1 }, beginAtZero: true, stacked: true }
        }
    }
});

// ── Update fleet counter via WS ───────────────────────────────────
window.updateDashboardFleet = function(data) {
    ['moving','idle','offline'].forEach(s => {
        const el = document.getElementById('cnt-' + s);
        if (el && data[s] !== undefined) el.textContent = data[s];
    });
};