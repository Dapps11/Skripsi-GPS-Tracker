<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AnalisisAkurasi extends Command
{
    protected $signature   = 'analisis:akurasi {jenis=semua : gps|eta|semua}';
    protected $description = 'Hitung MAE, RMSE, MAPE, dan korelasi untuk data uji akurasi GPS & ETA';

    public function handle(): void
    {
        $jenis = $this->argument('jenis');

        if ($jenis === 'gps' || $jenis === 'semua') {
            $this->analisisGps();
        }

        if ($jenis === 'eta' || $jenis === 'semua') {
            $this->analisisEta();
        }
    }

    /**
     * MAE & RMSE jarak error (meter) dari gps_accuracy_tests,
     * plus korelasi Pearson antara HDOP dan distance_error_m.
     */
    private function analisisGps(): void
    {
        $this->info('=== ANALISIS AKURASI GPS ===');

        $points = DB::table('gps_accuracy_tests')
                    ->select('test_point_name')
                    ->distinct()
                    ->pluck('test_point_name');

        if ($points->isEmpty()) {
            $this->warn('Belum ada data di tabel gps_accuracy_tests.');
            return;
        }

        $rows = [];
        foreach ($points as $point) {
            $stats = DB::table('gps_accuracy_tests')
                ->where('test_point_name', $point)
                ->selectRaw('
                    COUNT(*) as n,
                    AVG(distance_error_m) as mae,
                    SQRT(AVG(POW(distance_error_m, 2))) as rmse,
                    MIN(distance_error_m) as min_err,
                    MAX(distance_error_m) as max_err
                ')->first();

            $rows[] = [
                $point,
                $stats->n,
                round($stats->mae, 2) . ' m',
                round($stats->rmse, 2) . ' m',
                round($stats->min_err, 2) . ' m',
                round($stats->max_err, 2) . ' m',
            ];
        }

        $this->table(
            ['Titik Uji', 'N Sampel', 'MAE', 'RMSE', 'Error Min', 'Error Max'],
            $rows
        );

        // ── Korelasi Pearson: HDOP vs distance_error_m (keseluruhan titik) ──
        $corr = DB::table('gps_accuracy_tests')
            ->whereNotNull('hdop')
            ->selectRaw('
                COUNT(*) as n,
                SUM(hdop) as sum_x,
                SUM(distance_error_m) as sum_y,
                SUM(hdop * distance_error_m) as sum_xy,
                SUM(hdop * hdop) as sum_x2,
                SUM(distance_error_m * distance_error_m) as sum_y2
            ')->first();

        if ($corr->n >= 2) {
            $num = ($corr->n * $corr->sum_xy) - ($corr->sum_x * $corr->sum_y);
            $den = sqrt(
                (($corr->n * $corr->sum_x2) - pow($corr->sum_x, 2)) *
                (($corr->n * $corr->sum_y2) - pow($corr->sum_y, 2))
            );
            $r = $den != 0 ? $num / $den : null;

            $this->line('');
            $this->line('Korelasi Pearson (HDOP vs Error Aktual): ' .
                ($r !== null ? round($r, 3) . ' ' . $this->labelKorelasi($r) : 'N/A'));
        }

        $this->line('');
    }

    /**
     * MAE & MAPE selisih waktu (menit) antara prediksi ETA vs waktu tiba aktual,
     * dipecah per metode (realtime_haversine, realtime_google_traffic, initial_haversine).
     */
    private function analisisEta(): void
    {
        $this->info('=== ANALISIS AKURASI ETA ===');

        $rows = [];

        // -- Metode dari trip_eta_logs (snapshot berkala) --
        $methods = DB::table('trip_eta_logs')->select('method')->distinct()->pluck('method');

        foreach ($methods as $method) {
            $diffs = DB::table('trip_eta_logs as l')
                ->join('trips as t', 't.id', '=', 'l.trip_id')
                ->where('l.method', $method)
                ->where('t.status', 'completed')
                ->whereNotNull('t.arrived_at')
                ->selectRaw('
                    TIMESTAMPDIFF(MINUTE, l.predicted_arrival_at, t.arrived_at) as diff_minutes,
                    TIMESTAMPDIFF(MINUTE, t.departed_at, t.arrived_at) as durasi_aktual
                ')
                ->get();

            if ($diffs->isEmpty()) continue;

            $n    = $diffs->count();
            $mae  = $diffs->avg(fn($d) => abs($d->diff_minutes));
            $mape = $diffs->filter(fn($d) => $d->durasi_aktual > 0)
                          ->avg(fn($d) => abs($d->diff_minutes) / $d->durasi_aktual * 100);

            $rows[] = [$method, $n, round($mae, 1) . ' menit', round($mape ?? 0, 1) . ' %'];
        }

        // -- Metode 'initial_haversine' langsung dari tabel trips --
        $diffsInitial = DB::table('trips')
            ->where('status', 'completed')
            ->whereNotNull('estimated_arrival_at')
            ->whereNotNull('arrived_at')
            ->selectRaw('
                TIMESTAMPDIFF(MINUTE, estimated_arrival_at, arrived_at) as diff_minutes,
                TIMESTAMPDIFF(MINUTE, departed_at, arrived_at) as durasi_aktual
            ')->get();

        if ($diffsInitial->isNotEmpty()) {
            $n    = $diffsInitial->count();
            $mae  = $diffsInitial->avg(fn($d) => abs($d->diff_minutes));
            $mape = $diffsInitial->filter(fn($d) => $d->durasi_aktual > 0)
                                 ->avg(fn($d) => abs($d->diff_minutes) / $d->durasi_aktual * 100);

            $rows[] = ['initial_haversine', $n, round($mae, 1) . ' menit', round($mape ?? 0, 1) . ' %'];
        }

        if (empty($rows)) {
            $this->warn('Belum ada data trip yang completed dengan ETA tercatat.');
            return;
        }

        $this->table(['Metode ETA', 'N Trip', 'MAE', 'MAPE'], $rows);
        $this->line('');
    }

    private function labelKorelasi(float $r): string
    {
        $abs = abs($r);
        $label = match (true) {
            $abs < 0.2 => 'sangat lemah',
            $abs < 0.4 => 'lemah',
            $abs < 0.6 => 'sedang',
            $abs < 0.8 => 'kuat',
            default    => 'sangat kuat',
        };
        return "({$label}, " . ($r >= 0 ? 'positif' : 'negatif') . ')';
    }
}