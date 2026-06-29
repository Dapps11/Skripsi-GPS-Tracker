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
            // departed_at & arrived_at disimpan sebagai WIB (now() + app_tz Asia/Jakarta),
            // sedangkan gps_timestamp disimpan UTC — konversi ke UTC dulu.
            $departedUtc = \Carbon\Carbon::parse(
                $trip->departed_at->format('Y-m-d H:i:s'), 'Asia/Jakarta'
            )->utc()->toDateTimeString();

            $query = GpsTelemetry::where('vehicle_id', $trip->vehicle_id)
                                ->where('gps_timestamp', '>=', $departedUtc);
            if ($trip->arrived_at) {
                $arrivedUtc = \Carbon\Carbon::parse(
                    $trip->arrived_at->format('Y-m-d H:i:s'), 'Asia/Jakarta'
                )->utc()->addMinutes(5)->toDateTimeString();
                $query->where('gps_timestamp', '<=', $arrivedUtc);
            } else {
                // Trip ini belum punya arrived_at (masih berjalan / belum
                // ditandai selesai). Tanpa batas atas, fallback ini bisa
                // ikut menarik data milik trip BERIKUTNYA untuk kendaraan
                // yang sama. Cari trip lain yang berangkat setelah trip ini
                // dan jadikan itu batas atasnya.
                $nextTripDepartedAt = Trip::where('vehicle_id', $trip->vehicle_id)
                    ->where('id', '!=', $trip->id)
                    ->whereNotNull('departed_at')
                    ->where('departed_at', '>', $trip->departed_at)
                    ->orderBy('departed_at')
                    ->value('departed_at');

                if ($nextTripDepartedAt) {
                    $nextUtc = \Carbon\Carbon::parse($nextTripDepartedAt, 'Asia/Jakarta')
                                ->utc()->toDateTimeString();
                    $query->where('gps_timestamp', '<', $nextUtc);
                }
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
            } else {
                // Sama seperti fallback GPS di atas — batasi ke trip
                // berikutnya (jika ada) supaya tidak ikut menarik data
                // monitoring milik trip lain untuk kendaraan yang sama.
                $nextTripDepartedAt = Trip::where('vehicle_id', $trip->vehicle_id)
                    ->where('id', '!=', $trip->id)
                    ->whereNotNull('departed_at')
                    ->where('departed_at', '>', $trip->departed_at)
                    ->orderBy('departed_at')
                    ->value('departed_at');

                if ($nextTripDepartedAt) {
                    $query->where('event_timestamp', '<', $nextTripDepartedAt);
                }
            }
            $monitoringEvents = $query->orderBy('event_timestamp')->get();
        }

        $mapType       = session('map_type', 'gmaps');
        $googleMapsKey = config('services.google_maps.key', '');

        // Deteksi titik-titik berhenti (stop events) — pakai data mentah dulu
        $stops = $this->detectStops($gpsPoints);

        // Bersihkan/smoothing jalur sebelum digambar di peta —
        // supaya "jitter" GPS saat diam tidak digambar sebagai belokan aneh
        $gpsPointsForMap = $this->smoothTrack($gpsPoints);

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

        // ── Deteksi celah sinyal & pecah jalur jadi beberapa segmen ──────
        // >30 detik tanpa data baru dianggap sinyal putus (interval normal ±5 detik)
        $gapThresholdSec = 30;
        $gpsSegments     = [];
        $signalGaps      = [];
        $currentSegment  = [];
        $pointCount      = count($gpsPointsForMap);

        foreach ($gpsPointsForMap as $i => $point) {
            $currentSegment[] = $point;

            if ($i < $pointCount - 1) {
                // gps_timestamp adalah Carbon object; app tz = Asia/Jakarta sehingga DB UTC
                // harus di-parse ulang secara eksplisit sebagai UTC agar diffInSeconds benar
                $rawTs1 = $point['gps_timestamp'];
                $rawTs2 = $gpsPointsForMap[$i + 1]['gps_timestamp'];
                $t1     = \Carbon\Carbon::parse(
                    ($rawTs1 instanceof \Carbon\Carbon ? $rawTs1->format('Y-m-d H:i:s') : $rawTs1), 'UTC'
                );
                $t2     = \Carbon\Carbon::parse(
                    ($rawTs2 instanceof \Carbon\Carbon ? $rawTs2->format('Y-m-d H:i:s') : $rawTs2), 'UTC'
                );
                $gapSec = abs($t1->diffInSeconds($t2));

                if ($gapSec > $gapThresholdSec) {
                    $gpsSegments[]  = $currentSegment;
                    $currentSegment = [];

                    $signalGaps[] = [
                        'start_at'     => $t1->setTimezone('Asia/Jakarta')->toISOString(),
                        'end_at'       => $t2->setTimezone('Asia/Jakarta')->toISOString(),
                        'duration_sec' => $gapSec,
                        'lat'          => $point['latitude'],
                        'lng'          => $point['longitude'],
                    ];
                }
            }
        }
        if (!empty($currentSegment)) {
            $gpsSegments[] = $currentSegment;
        }

        // ── Deteksi keluar jalur (route deviation) ──────────────────
        $routeDeviations = [];
        if ($trip->origin_lat && $trip->dest_lat && $gpsPoints->count() >= 2) {
            $routeDeviations = $this->detectRouteDeviations(
                $gpsPoints,
                $trip->origin_lat, $trip->origin_lng,
                $trip->dest_lat,   $trip->dest_lng,
                500,   // maxDistanceMeters
                3,     // minDurationMinutes
                2      // minOccurrences
            );
        }

        return view('trips.show', compact(
            'trip', 'gpsPoints', 'gpsPointsForMap', 'mapType', 'googleMapsKey',
            'etaHaversine', 'stops', 'monitoringEvents', 'monitoringForChart',
            'gpsSegments', 'signalGaps', 'routeDeviations'
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
        $MAX_REALISTIC_SPEED  = 150;  // km/h — di atas ini, titik dianggap outlier/error GPS
        $STOP_SPEED_THRESHOLD = 2;    // km/h — sama dengan threshold di detectStops()
        $STOP_MIN_DURATION    = 1;    // menit

        $points = $gpsPoints->values();
        $total  = $points->count();

        if ($total === 0) {
            return [];
        }

        // ── Tahap 1: buang outlier berdasarkan kecepatan implisit antar titik ──
        $clean = [$points[0]];
        for ($i = 1; $i < $total; $i++) {
            $prev = $clean[count($clean) - 1];
            $curr = $points[$i];

            $distKm = $this->haversine(
                $prev->latitude, $prev->longitude,
                $curr->latitude, $curr->longitude
            );
            $timeHours = abs(
                \Carbon\Carbon::parse($curr->gps_timestamp)
                    ->diffInSeconds(\Carbon\Carbon::parse($prev->gps_timestamp))
            ) / 3600;

            // Hindari pembagian dengan nol — kalau selisih waktu hampir 0 tapi jarak besar, anggap outlier
            $impliedSpeed = $timeHours > 0 ? ($distKm / $timeHours) : ($distKm > 0.05 ? 9999 : 0);

            if ($impliedSpeed > $MAX_REALISTIC_SPEED) {
                // Lompatan tidak masuk akal → skip titik ini, jangan jadikan referensi "prev" berikutnya
                continue;
            }

            $clean[] = $curr;
        }

        // ── Tahap 2: collapse segmen diam jadi 1 titik representatif ───────────
        $cleanCount = count($clean);
        $result     = [];
        $i          = 0;

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
                    // Segmen diam yang signifikan → ambil 1 titik representatif (tengah segmen)
                    // supaya tidak digambar sebagai jalur belok-belok/jitter
                    $midIdx = $segStart + intdiv($segEnd - $segStart, 2);
                    $midPt  = $clean[$midIdx];

                    $result[] = [
                        'latitude'      => $midPt->latitude,
                        'longitude'     => $midPt->longitude,
                        'speed_kmh'     => $midPt->speed_kmh,
                        'gps_timestamp' => $midPt->gps_timestamp,
                    ];

                    $i = $segEnd + 1;
                    continue;
                }
                // Kalau durasi diam singkat (bukan stop signifikan), tetap masukkan apa adanya
            }

            $result[] = [
                'latitude'      => $pt->latitude,
                'longitude'     => $pt->longitude,
                'speed_kmh'     => $pt->speed_kmh,
                'gps_timestamp' => $pt->gps_timestamp,
            ];
            $i++;
        }

        return $result;
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
        $MERGE_GAP_SEC        = 30;  // detik — dua stop dipisah gap < ini, digabung jadi satu

        $stops      = [];
        $points     = $gpsPoints->values();
        $total      = $points->count();

        if ($total < 2) {
            return $stops;
        }

        // Helper: parse gps_timestamp dengan benar (DB simpan UTC, app tz = Asia/Jakarta)
        $parseUTC = fn($pt) => \Carbon\Carbon::parse(
            $pt->gps_timestamp->format('Y-m-d H:i:s'), 'UTC'
        );

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
                $startTime = $parseUTC($points[$segStartIdx]);
                $endTime   = $parseUTC($points[$segEndIdx]);
                $durationMinutes = $startTime->diffInSeconds($endTime) / 60;

                if ($durationMinutes >= $STOP_MIN_DURATION) {
                    $midIdx = $segStartIdx + intdiv($segEndIdx - $segStartIdx, 2);
                    $midPt  = $points[$midIdx];

                    $stops[] = [
                        'lat'        => (float) $midPt->latitude,
                        'lng'        => (float) $midPt->longitude,
                        '_startTime' => $startTime,
                        '_endTime'   => $endTime,
                    ];
                }

                $i = $segEndIdx + 1;
            } else {
                $i++;
            }
        }

        // Gabungkan stop yang gap-nya < MERGE_GAP_SEC (cegah jitter GPS memecah satu stop jadi banyak)
        $merged = [];
        foreach ($stops as $stop) {
            if (!empty($merged)) {
                $prev    = &$merged[count($merged) - 1];
                $gapSec  = $prev['_endTime']->diffInSeconds($stop['_startTime']);
                if ($gapSec <= $MERGE_GAP_SEC) {
                    $prev['_endTime'] = $stop['_endTime'];
                    continue;
                }
            }
            $merged[] = $stop;
        }

        // Format hasil akhir
        $result = [];
        foreach ($merged as $s) {
            $startTime    = $s['_startTime'];
            $endTime      = $s['_endTime'];
            $totalSeconds = $startTime->diffInSeconds($endTime);
            $durMin       = intdiv($totalSeconds, 60);
            $durSec       = $totalSeconds % 60;

            if ($durMin >= 60) {
                $jam      = intdiv($durMin, 60);
                $sisaMin  = $durMin % 60;
                $durLabel = "{$jam} jam {$sisaMin} menit {$durSec} detik";
            } elseif ($durMin > 0) {
                $durLabel = "{$durMin} menit {$durSec} detik";
            } else {
                $durLabel = "{$durSec} detik";
            }

            $result[] = [
                'lat'              => $s['lat'],
                'lng'              => $s['lng'],
                'started_at'       => $startTime->setTimezone('Asia/Jakarta')->format('H:i:s'),
                'ended_at'         => $endTime->setTimezone('Asia/Jakarta')->format('H:i:s'),
                'duration_seconds' => $totalSeconds,
                'duration_minutes' => $durMin,
                'duration_label'   => $durLabel,
            ];
        }

        return $result;
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

    /**
     * Hitung jarak titik ke garis (origin→dest) menggunakan cross-track distance.
     * Return dalam meter.
     */
    private function pointToLineDist(float $ptLat, float $ptLng, float $oLat, float $oLng, float $dLat, float $dLng): float
    {
        $R = 6371000; // bumi dalam meter
        $distOP = $this->haversine($oLat, $oLng, $ptLat, $ptLng) * 1000; // meter
        $bearOP = deg2rad($this->initialBearing($oLat, $oLng, $ptLat, $ptLng));
        $bearOD = deg2rad($this->initialBearing($oLat, $oLng, $dLat, $dLng));

        // Cross-track distance
        $xtd = abs(asin(sin($distOP / $R) * sin($bearOP - $bearOD)) * $R);
        return $xtd;
    }

    private function initialBearing(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $lat1 = deg2rad($lat1); $lat2 = deg2rad($lat2);
        $dLng = deg2rad($lng2 - $lng1);
        $y = sin($dLng) * cos($lat2);
        $x = cos($lat1) * sin($lat2) - sin($lat1) * cos($lat2) * cos($dLng);
        return fmod(rad2deg(atan2($y, $x)) + 360, 360);
    }

    /**
     * Deteksi keluar jalur dari koridor origin→dest.
     *
     * @param  \Illuminate\Support\Collection $gpsPoints
     * @param  float $oLat Origin latitude
     * @param  float $oLng Origin longitude
     * @param  float $dLat Destination latitude
     * @param  float $dLng Destination longitude
     * @param  int   $maxDistanceMeters Threshold jarak keluar koridor
     * @param  int   $minDurationMinutes Durasi minimum deviasi
     * @param  int   $minOccurrences Jumlah minimum deviasi untuk ditampilkan
     * @return array List of route deviations
     */
    private function detectRouteDeviations($gpsPoints, float $oLat, float $oLng, float $dLat, float $dLng, int $maxDistanceMeters = 500, int $minDurationMinutes = 3, int $minOccurrences = 2): array
    {
        $pts     = $gpsPoints->values();
        $total   = $pts->count();
        if ($total < 2) return [];

        $parseUTC = fn($pt) => \Carbon\Carbon::parse(
            $pt->gps_timestamp->format('Y-m-d H:i:s'), 'UTC'
        );

        $raw = [];
        $i   = 0;
        while ($i < $total) {
            $pt = $pts[$i];
            $dist = $this->pointToLineDist(
                (float)$pt->latitude, (float)$pt->longitude,
                $oLat, $oLng, $dLat, $dLng
            );

            if ($dist > $maxDistanceMeters) {
                // Start of deviation
                $startIdx = $i;
                $maxDist  = $dist;
                $j = $i;

                while ($j + 1 < $total) {
                    $nextDist = $this->pointToLineDist(
                        (float)$pts[$j + 1]->latitude, (float)$pts[$j + 1]->longitude,
                        $oLat, $oLng, $dLat, $dLng
                    );
                    if ($nextDist > $maxDistanceMeters) {
                        $maxDist = max($maxDist, $nextDist);
                        $j++;
                    } else {
                        break;
                    }
                }

                $startTime = $parseUTC($pts[$startIdx]);
                $endTime   = $parseUTC($pts[$j]);
                $durMin    = $startTime->diffInSeconds($endTime) / 60;

                if ($durMin >= $minDurationMinutes) {
                    $midIdx = $startIdx + intdiv($j - $startIdx, 2);
                    $midPt  = $pts[$midIdx];
                    $raw[] = [
                        'lat'          => (float) $midPt->latitude,
                        'lng'          => (float) $midPt->longitude,
                        'max_distance_m' => (int) round($maxDist),
                        'started_at'   => $startTime->setTimezone('Asia/Jakarta')->format('H:i:s'),
                        'ended_at'     => $endTime->setTimezone('Asia/Jakarta')->format('H:i:s'),
                        'duration_sec' => (int) $startTime->diffInSeconds($endTime),
                        'duration_label' => $durMin >= 60
                            ? intdiv((int)$durMin, 60) . 'j ' . ((int)$durMin % 60) . 'm'
                            : ((int)$durMin) . ' menit',
                    ];
                }
                $i = $j + 1;
            } else {
                $i++;
            }
        }

        // Hanya tampilkan jika jumlah deviasi >= minOccurrences
        if (count($raw) < $minOccurrences) {
            return [];
        }

        return $raw;
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

        return back()->with('success', "Trip {$trip->trip_code} dimulai.");
    }
    
}