<?php

namespace App\Http\Controllers;

use App\Models\Trip;
use App\Models\Vehicle;
use App\Models\Driver;
use App\Models\IotDevice;
use App\Models\GpsTelemetry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class TripController extends Controller
{
    public function index()
    {
        $trips = Trip::with(['vehicle', 'driver'])
                     ->orderByDesc('created_at')
                     ->paginate(15);

        $summary = [
            'planned'     => Trip::where('status', 'planned')->count(),
            'in_progress' => Trip::where('status', 'in_progress')->count(),
            'completed'   => Trip::where('status', 'completed')->count(),
        ];

        return view('trips.index', compact('trips', 'summary'));
    }

    public function create()
    {
        $vehicles = Vehicle::whereNull('deleted_at')->orderBy('name')->get();
        $drivers  = Driver::whereNull('deleted_at')
                        ->whereIn('status', ['available', 'on_duty'])
                        ->orderBy('full_name')->get();

        return view('trips.create', array_merge(
            compact('vehicles', 'drivers'),
            $this->mapConfig()
        ));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'vehicle_id'           => 'required|exists:vehicles,id',
            'driver_id'            => 'nullable|exists:drivers,id',
            'origin_name'          => 'required|string|max:150',
            'origin_address'       => 'nullable|string',
            'origin_lat'           => 'required|numeric|between:-90,90',
            'origin_lng'           => 'required|numeric|between:-180,180',
            'dest_name'            => 'required|string|max:150',
            'dest_address'         => 'nullable|string',
            'dest_lat'             => 'required|numeric|between:-90,90',
            'dest_lng'             => 'required|numeric|between:-180,180',
            'estimated_arrival_at' => 'nullable|string',
            'notes'                => 'nullable|string',
        ]);

        // Pastikan string kosong dikonversi ke null
        $validated['driver_id']            = $validated['driver_id'] ?: null;
        $validated['notes']                = $validated['notes'] ?: null;
        $validated['origin_address']       = $validated['origin_address'] ?: null;
        $validated['dest_address']         = $validated['dest_address'] ?: null;
        $validated['estimated_arrival_at'] = $validated['estimated_arrival_at'] ?: null;

        // Generate trip code
        $validated['trip_code']   = 'TRIP-' . strtoupper(substr(uniqid(), -6));
        $validated['status']      = 'planned';
        $validated['departed_at'] = null;

        // Cari device terpasang di kendaraan
        $device = \App\Models\IotDevice::where('vehicle_id', $validated['vehicle_id'])
                                    ->whereNull('deleted_at')
                                    ->first();

        if ($device) {
            $validated['device_id'] = $device->id;
            if (empty($validated['driver_id']) && $device->driver_id) {
                $validated['driver_id'] = $device->driver_id;
            }
        }

        $trip = Trip::create($validated);

        return redirect()->route('trips.index')
                        ->with('success', "Trip {$trip->trip_code} berhasil dibuat. Waktu berangkat otomatis tercatat saat kendaraan mulai bergerak.");
    }

    public function show(Trip $trip)
    {
        $trip->load(['vehicle', 'driver', 'device']);

        $gpsPoints = GpsTelemetry::where('trip_id', $trip->id)
                                ->orderBy('gps_timestamp')
                                ->get(['latitude', 'longitude', 'speed_kmh', 'gps_timestamp']);

        if ($gpsPoints->isEmpty() && $trip->departed_at) {
            $query = GpsTelemetry::where('vehicle_id', $trip->vehicle_id)
                                ->where('gps_timestamp', '>=', $trip->departed_at);
            if ($trip->arrived_at) {
                $query->where('gps_timestamp', '<=',
                    \Carbon\Carbon::parse($trip->arrived_at)->addMinutes(5)
                );
            }
            $gpsPoints = $query->orderBy('gps_timestamp')
                            ->get(['latitude', 'longitude', 'speed_kmh', 'gps_timestamp']);
        }

        if (!$trip->total_distance_km && $gpsPoints->count() >= 2) {
            $totalKm = 0.0;
            for ($i = 1; $i < $gpsPoints->count(); $i++) {
                $totalKm += $this->haversine(
                    $gpsPoints[$i-1]->latitude, $gpsPoints[$i-1]->longitude,
                    $gpsPoints[$i]->latitude,   $gpsPoints[$i]->longitude
                );
            }
            $totalKm = round($totalKm, 2);
            $trip->update(['total_distance_km' => $totalKm]);
            $trip->total_distance_km = $totalKm;
        }

        // Hitung ETA awal dengan haversine
        $etaHaversine = null;
        if ($trip->origin_lat && $trip->dest_lat) {
            $dist       = $this->haversine(
                $trip->origin_lat, $trip->origin_lng,
                $trip->dest_lat,   $trip->dest_lng
            );
            $rf         = $dist < 3 ? 1.6 : ($dist < 10 ? 1.4 : 1.25);
            $distRoad   = $dist * $rf;
            $speed      = $distRoad < 5 ? 25 : ($distRoad < 15 ? 35 : 50);
            $delay      = $distRoad < 5 ? 5  : ($distRoad < 15 ? 4  : 3);
            $etaHaversine = (int) round(($distRoad / $speed) * 60 + $delay);
        }

        // Ambil data monitoring kantuk untuk trip ini
        // Prioritas: trip_id match, fallback: vehicle_id + rentang waktu trip
        $monitoringEvents = \App\Models\DriverMonitoringEvent::where('trip_id', $trip->id)
            ->orderBy('event_timestamp')
            ->get();

        if ($monitoringEvents->isEmpty() && $trip->departed_at) {
            $query = \App\Models\DriverMonitoringEvent::where('vehicle_id', $trip->vehicle_id)
                ->where('event_timestamp', '>=', $trip->departed_at);
            if ($trip->arrived_at) {
                $query->where('event_timestamp', '<=',
                    \Carbon\Carbon::parse($trip->arrived_at)->addMinutes(10)
                );
            }
            $monitoringEvents = $query->orderBy('event_timestamp')->get();
        }

        $mapType       = session('map_type', 'osm');
        $googleMapsKey = config('services.google_maps.key', '');

        // Deteksi titik-titik berhenti (stop events) — pakai data mentah dulu
        $stops = $this->detectStops($gpsPoints);

        // Bersihkan/smoothing jalur + deteksi gap sinyal
        $smoothed        = $this->smoothTrack($gpsPoints);
        $gpsSegments     = $smoothed['segments'];  // array of segment (tiap segment = array titik)
        $signalGaps      = $smoothed['gaps'];      // titik gap di mana sinyal terputus

        // Flatten semua segmen untuk backward compat (stats, bounds, timeline)
        $gpsPointsForMap = count($gpsSegments) > 0
            ? array_merge(...$gpsSegments)
            : [];

        // Transform untuk JS chart
        $monitoringForChart = $monitoringEvents->map(function($e) {
            return [
                'time'          => \Carbon\Carbon::parse($e->event_timestamp)->setTimezone('Asia/Jakarta')->format('H:i:s'),
                'event_type'    => $e->event_type,
                'reasons'       => $e->reasons,
                'perclos_value' => $e->perclos_value,
                'ear_value'     => $e->ear_value,
                'mar_value'     => $e->mar_value,
                'is_alarm'      => $e->is_alarm,
            ];
        });

        return view('trips.show', compact(
            'trip', 'gpsPoints', 'gpsPointsForMap', 'gpsSegments', 'signalGaps',
            'mapType', 'googleMapsKey', 'etaHaversine', 'stops',
            'monitoringEvents', 'monitoringForChart'
        ));
    }

    /**
     * Bersihkan jalur GPS untuk digambar di peta:
     *  1. Buang outlier — lompatan jarak yang implies kecepatan tidak masuk akal (> MAX_REALISTIC_SPEED)
     *  2. Collapse segmen "diam" (speed <= threshold, durasi >= 1 menit) jadi 1 titik representatif,
     *     supaya jitter GPS saat parkir/macet tidak tergambar sebagai jalur belok-belok.
     *
     * @param  \Illuminate\Support\Collection $gpsPoints  Urut by gps_timestamp ASC
     * @return array  Array assoc sederhana [['latitude'=>,'longitude'=>,'speed_kmh'=>,'gps_timestamp'=>], ...]
     */
    private function smoothTrack($gpsPoints): array
    {
        $MAX_REALISTIC_SPEED  = 150;  // km/h — di atas ini, titik dianggap outlier
        $GAP_THRESHOLD_SEC    = 60;   // detik — jeda > 60 detik dianggap sinyal terputus
        $STOP_SPEED_THRESHOLD = 2;    // km/h
        $STOP_MIN_DURATION    = 1;    // menit

        $points = $gpsPoints->values();
        $total  = $points->count();

        if ($total === 0) {
            return ['segments' => [], 'gaps' => []];
        }

        // ── Tahap 1: buang outlier kecepatan tidak masuk akal ──────────
        $clean = [$points[0]];
        for ($i = 1; $i < $total; $i++) {
            $prev = $clean[count($clean) - 1];
            $curr = $points[$i];

            $distKm    = $this->haversine(
                $prev->latitude, $prev->longitude,
                $curr->latitude, $curr->longitude
            );
            $timeSec   = abs(\Carbon\Carbon::parse($curr->gps_timestamp)
                            ->diffInSeconds(\Carbon\Carbon::parse($prev->gps_timestamp)));
            $timeHours = $timeSec / 3600;
            $impliedSpeed = $timeHours > 0 ? ($distKm / $timeHours) : ($distKm > 0.05 ? 9999 : 0);

            if ($impliedSpeed > $MAX_REALISTIC_SPEED) continue;

            $clean[] = $curr;
        }

        // ── Tahap 2: collapse segmen diam ──────────────────────────────
        $cleanCount = count($clean);
        $flattened  = [];
        $i = 0;

        while ($i < $cleanCount) {
            $pt = $clean[$i];

            if (($pt->speed_kmh ?? 0) <= $STOP_SPEED_THRESHOLD) {
                $segStart = $i;
                $j = $i;
                while ($j + 1 < $cleanCount && ($clean[$j + 1]->speed_kmh ?? 0) <= $STOP_SPEED_THRESHOLD) {
                    $j++;
                }
                $segEnd = $j;
                $startTime = \Carbon\Carbon::parse($clean[$segStart]->gps_timestamp);
                $endTime   = \Carbon\Carbon::parse($clean[$segEnd]->gps_timestamp);
                $durationMinutes = $startTime->diffInSeconds($endTime) / 60;

                if ($durationMinutes >= $STOP_MIN_DURATION && $segEnd > $segStart) {
                    $midIdx   = $segStart + intdiv($segEnd - $segStart, 2);
                    $midPt    = $clean[$midIdx];
                    $flattened[] = [
                        'latitude'      => $midPt->latitude,
                        'longitude'     => $midPt->longitude,
                        'speed_kmh'     => $midPt->speed_kmh,
                        'gps_timestamp' => $midPt->gps_timestamp,
                    ];
                    $i = $segEnd + 1;
                    continue;
                }
            }

            $flattened[] = [
                'latitude'      => $pt->latitude,
                'longitude'     => $pt->longitude,
                'speed_kmh'     => $pt->speed_kmh,
                'gps_timestamp' => $pt->gps_timestamp,
            ];
            $i++;
        }

        // ── Tahap 3: split jadi segmen terpisah saat ada gap > threshold ─
        $segments = [];
        $gaps     = [];
        $curSeg   = [];

        $flatTotal = count($flattened);
        for ($i = 0; $i < $flatTotal; $i++) {
            $pt = $flattened[$i];

            if (empty($curSeg)) {
                $curSeg[] = $pt;
                continue;
            }

            $prev    = end($curSeg);
            $timeSec = abs(\Carbon\Carbon::parse($pt['gps_timestamp'])
                            ->diffInSeconds(\Carbon\Carbon::parse($prev['gps_timestamp'])));

            if ($timeSec > $GAP_THRESHOLD_SEC) {
                // Simpan segmen saat ini
                $segments[] = $curSeg;

                // Catat gap event — posisi midpoint antara 2 titik
                $gapDurSec = $timeSec;
                $gapMin    = intdiv($gapDurSec, 60);
                $gapSec    = $gapDurSec % 60;
                $gapLabel  = $gapMin > 0
                    ? ($gapSec > 0 ? "{$gapMin} mnt {$gapSec} dtk" : "{$gapMin} mnt")
                    : "{$gapSec} dtk";

                $gaps[] = [
                    'lat'         => ($prev['latitude']  + $pt['latitude'])  / 2,
                    'lng'         => ($prev['longitude'] + $pt['longitude']) / 2,
                    'duration_sec' => $gapDurSec,
                    'duration_label' => $gapLabel,
                    'from_time'   => \Carbon\Carbon::parse($prev['gps_timestamp'])
                                        ->setTimezone('Asia/Jakarta')->format('H:i:s'),
                    'to_time'     => \Carbon\Carbon::parse($pt['gps_timestamp'])
                                        ->setTimezone('Asia/Jakarta')->format('H:i:s'),
                ];

                // Mulai segmen baru
                $curSeg = [$pt];
            } else {
                $curSeg[] = $pt;
            }
        }

        // Tambahkan segmen terakhir
        if (!empty($curSeg)) {
            $segments[] = $curSeg;
        }

        return ['segments' => $segments, 'gaps' => $gaps];
    }

    /**
     * Deteksi stop events dari rangkaian GPS points.
     * Stop = rangkaian titik berurutan dengan speed <= STOP_SPEED_THRESHOLD km/h
     * yang berlangsung >= STOP_MIN_DURATION menit.
     *
     * @param  \Illuminate\Support\Collection $gpsPoints  Koleksi GpsTelemetry, urut by gps_timestamp ASC
     * @return array  List stop events: [['lat'=>,'lng'=>,'started_at'=>,'ended_at'=>,'duration_minutes'=>,'duration_label'=>], ...]
     */
    private function detectStops($gpsPoints): array
    {
        $STOP_SPEED_THRESHOLD = 2;   // km/h — di bawah ini dianggap diam
        $STOP_MIN_DURATION    = 1;   // menit — minimal durasi supaya dihitung "stop"

        $stops      = [];
        $points     = $gpsPoints->values();
        $total      = $points->count();

        if ($total < 2) {
            return $stops;
        }

        $i = 0;
        while ($i < $total) {
            $pt = $points[$i];

            // Cari awal segmen "diam"
            if (($pt->speed_kmh ?? 0) <= $STOP_SPEED_THRESHOLD) {
                $segStartIdx = $i;
                $j = $i;

                // Perluas selama speed masih <= threshold
                while ($j + 1 < $total && ($points[$j + 1]->speed_kmh ?? 0) <= $STOP_SPEED_THRESHOLD) {
                    $j++;
                }

                $segEndIdx = $j;
                $startTime = \Carbon\Carbon::parse($points[$segStartIdx]->gps_timestamp);
                $endTime   = \Carbon\Carbon::parse($points[$segEndIdx]->gps_timestamp);
                $durationMinutes = $startTime->diffInSeconds($endTime) / 60;

                if ($durationMinutes >= $STOP_MIN_DURATION) {
                    // Titik representatif = titik tengah segmen (median index)
                    $midIdx = $segStartIdx + intdiv($segEndIdx - $segStartIdx, 2);
                    $midPt  = $points[$midIdx];

                    $totalSeconds = $startTime->diffInSeconds($endTime);
                    $durMin       = intdiv($totalSeconds, 60);
                    $durSec       = $totalSeconds % 60;

                    // Label detail: "1 menit 13 detik", atau "2 jam 5 menit 30 detik"
                    if ($durMin >= 60) {
                        $jam     = intdiv($durMin, 60);
                        $sisaMin = $durMin % 60;
                        $durLabel = "{$jam} jam {$sisaMin} menit {$durSec} detik";
                    } elseif ($durMin > 0) {
                        $durLabel = "{$durMin} menit {$durSec} detik";
                    } else {
                        $durLabel = "{$durSec} detik";
                    }

                    $stops[] = [
                        'lat'              => (float) $midPt->latitude,
                        'lng'              => (float) $midPt->longitude,
                        'started_at'       => $startTime->setTimezone('Asia/Jakarta')->format('H:i:s'),
                        'ended_at'         => $endTime->setTimezone('Asia/Jakarta')->format('H:i:s'),
                        'duration_seconds' => $totalSeconds,
                        'duration_minutes' => $durMin,
                        'duration_label'   => $durLabel,
                    ];
                }

                // Lanjut dari setelah segmen ini
                $i = $segEndIdx + 1;
            } else {
                $i++;
            }
        }

        return $stops;
    }

    private function haversine(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $R    = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a    = sin($dLat/2)**2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng/2)**2;
        return $R * 2 * atan2(sqrt($a), sqrt(1-$a));
    }

    public function edit(Trip $trip)
    {
        $vehicles = Vehicle::whereNull('deleted_at')->orderBy('name')->get();
        $drivers  = Driver::whereNull('deleted_at')->orderBy('full_name')->get();

        // Jika bukan planned, tetap tampilkan halaman tapi dengan banner locked
        // (view sudah handle ini dengan $isLocked)
        return view('trips.edit', array_merge(
            compact('trip', 'vehicles', 'drivers'),
            $this->mapConfig()
        ));
    }

    public function update(Request $request, Trip $trip)
    {
        // Hanya planned yang boleh diedit
        if ($trip->status !== 'planned') {
            return redirect()->route('trips.show', $trip)
                ->withErrors(['error' => 'Trip yang sudah berjalan/selesai tidak bisa diedit.']);
        }

        $validated = $request->validate([
            'vehicle_id'     => 'required|exists:vehicles,id',
            'driver_id'      => 'nullable|exists:drivers,id',
            'origin_name'    => 'required|string|max:150',
            'origin_address' => 'nullable|string',
            'origin_lat'     => 'required|numeric|between:-90,90',
            'origin_lng'     => 'required|numeric|between:-180,180',
            'dest_name'      => 'required|string|max:150',
            'dest_address'   => 'nullable|string',
            'dest_lat'       => 'required|numeric|between:-90,90',
            'dest_lng'       => 'required|numeric|between:-180,180',
            'notes'          => 'nullable|string',
        ]);

        // Status tetap planned, waktu tidak diubah user
        $validated['status']      = 'planned';
        $validated['driver_id']   = $validated['driver_id'] ?: null;
        $validated['notes']       = $validated['notes'] ?: null;
        $validated['origin_address'] = $validated['origin_address'] ?: null;
        $validated['dest_address']   = $validated['dest_address'] ?: null;

        $trip->update($validated);

        return redirect()->route('trips.show', $trip)
            ->with('success', "Trip {$trip->trip_code} berhasil diperbarui.");
    }

    public function destroy(Trip $trip)
    {
        // Hanya boleh hapus trip yang belum in_progress
        if ($trip->status === 'in_progress') {
            return back()->withErrors(['error' => 'Trip yang sedang berjalan tidak bisa dihapus.']);
        }

        $trip->delete();
        return redirect()->route('trips.index')
                         ->with('success', 'Trip berhasil dihapus.');
    }

    /**
     * Tandai trip sebagai selesai
     */
    public function complete(Trip $trip)
    {
        $trip->update([
            'status'     => 'completed',
            'arrived_at' => now(),
        ]);

        // Broadcast trip status update → WS indicator update di frontend
        broadcast(new \App\Events\TripStatusUpdated(
            $trip->vehicle_id,
            [
                'trip_id'    => $trip->id,
                'trip_code'  => $trip->trip_code,
                'status'     => 'completed',
                'arrived_at' => $trip->arrived_at,
            ]
        ))->toOthers();

        // Buat alert notifikasi trip selesai
        \App\Models\Alert::create([
            'alert_type'   => 'trip_completed',
            'severity'     => 'info',
            'vehicle_id'   => $trip->vehicle_id,
            'driver_id'    => $trip->driver_id,
            'trip_id'      => $trip->id,
            'title'        => "Trip Selesai — {$trip->trip_code}",
            'message'      => "Kendaraan tiba di {$trip->dest_name}. " .
                              ($trip->total_distance_km
                                  ? number_format($trip->total_distance_km, 1) . " km ditempuh."
                                  : ''),
            'triggered_at' => now(),
        ]);

        return back()->with('success', "Trip {$trip->trip_code} ditandai selesai.");
    }
    

    /**
     * Mulai trip (ubah planned → in_progress)
     */
    public function start(Trip $trip)
    {
        if ($trip->status !== 'planned') {
            return back()->withErrors(['error' => 'Trip ini tidak bisa dimulai.']);
        }

        $trip->update([
            'status'      => 'in_progress',
            'departed_at' => now(),
        ]);

        // Broadcast trip status update
        broadcast(new \App\Events\TripStatusUpdated(
            $trip->vehicle_id,
            [
                'trip_id'     => $trip->id,
                'trip_code'   => $trip->trip_code,
                'status'      => 'in_progress',
                'departed_at' => $trip->departed_at,
            ]
        ))->toOthers();

        return back()->with('success', "Trip {$trip->trip_code} dimulai.");
    }
    
}