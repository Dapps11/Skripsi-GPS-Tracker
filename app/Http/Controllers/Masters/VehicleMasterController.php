<?php

namespace App\Http\Controllers\Masters;

use App\Http\Controllers\Controller;
use App\Models\Alert;
use App\Models\Trip;
use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VehicleMasterController extends Controller
{
    public function index()
    {
        $vehicles = Vehicle::orderBy('name')->paginate(15);
        return view('masters.vehicles.index', compact('vehicles'));
    }

    public function create()
    {
        $nextVehicleCode = $this->nextVehicleCode();

        return view('masters.vehicles.create', compact('nextVehicleCode'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'            => 'required|string|max:100',
            'license_plate'   => 'required|string|max:20|unique:vehicles',
            'vehicle_type'    => 'required|string|max:50',
            'brand'           => 'nullable|string|max:50',
            'model'           => 'nullable|string|max:50',
            'year'            => 'nullable|integer|min:2000|max:2030',
            'color'           => 'nullable|string|max:30',
            'capacity_liters' => 'nullable|numeric|min:0',
            'status'          => 'required|in:moving,idle,offline',
            'notes'           => 'nullable|string',
        ]);

        // vehicle_code digenerate otomatis di server, retry kalau kebetulan bentrok
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $validated['vehicle_code'] = $this->nextVehicleCode();
            if (! Vehicle::where('vehicle_code', $validated['vehicle_code'])->exists()) {
                break;
            }
        }

        Vehicle::create($validated);

        return redirect()->route('master.vehicles.index')
                         ->with('success', 'Kendaraan berhasil ditambahkan dengan kode ' . $validated['vehicle_code'] . '.');
    }

    private function nextVehicleCode(): string
    {
        $prefix = 'VHC-';

        $lastNumber = Vehicle::where('vehicle_code', 'like', $prefix . '%')
            ->pluck('vehicle_code')
            ->map(function ($code) use ($prefix) {
                return preg_match('/^' . preg_quote($prefix, '/') . '(\d+)$/', $code, $m) ? (int) $m[1] : 0;
            })
            ->max() ?? 0;

        return $prefix . str_pad($lastNumber + 1, 3, '0', STR_PAD_LEFT);
    }

    public function edit(Vehicle $vehicle)
    {
        return view('masters.vehicles.edit', compact('vehicle'));
    }

    public function update(Request $request, Vehicle $vehicle)
    {
        $validated = $request->validate([
            'vehicle_code'    => 'required|string|max:30|unique:vehicles,vehicle_code,' . $vehicle->id,
            'name'            => 'required|string|max:100',
            'license_plate'   => 'required|string|max:20|unique:vehicles,license_plate,' . $vehicle->id,
            'vehicle_type'    => 'required|string|max:50',
            'brand'           => 'nullable|string|max:50',
            'model'           => 'nullable|string|max:50',
            'year'            => 'nullable|integer|min:1990|max:2030',
            'color'           => 'nullable|string|max:30',
            'capacity_liters' => 'nullable|numeric|min:0',
            'status'          => 'required|in:moving,idle,offline',
            'notes'           => 'nullable|string',
        ]);

        $vehicle->update($validated);

        return redirect()->route('master.vehicles.index')
                         ->with('success', 'Data kendaraan diperbarui.');
    }

    public function destroy(Vehicle $vehicle)
    {
        $vehicle->delete();
        return redirect()->route('master.vehicles.index')
                         ->with('success', 'Kendaraan berhasil dihapus.');
    }

    public function history(Request $request, Vehicle $vehicle)
    {
        $date     = $request->input('date', now()->toDateString());
        $startUtc = Carbon::parse($date . ' 00:00:00', 'Asia/Jakarta')->utc();
        $endUtc   = Carbon::parse($date . ' 23:59:59', 'Asia/Jakarta')->utc();

        $allPoints = DB::table('gps_telemetry')
            ->where('vehicle_id', $vehicle->id)
            ->whereBetween('gps_timestamp', [$startUtc->toDateTimeString(), $endUtc->toDateTimeString()])
            ->orderBy('gps_timestamp')
            ->get(['latitude', 'longitude', 'speed_kmh', 'gps_timestamp']);

        // Stats
        $totalDistKm = 0.0;
        $movingSec   = 0;
        $maxSpeedKmh = 0.0;
        $count       = $allPoints->count();
        for ($i = 1; $i < $count; $i++) {
            $p  = $allPoints[$i];
            $pp = $allPoints[$i - 1];
            $totalDistKm += $this->haversineKm(
                (float)$pp->latitude, (float)$pp->longitude,
                (float)$p->latitude,  (float)$p->longitude
            );
            if ((float)$p->speed_kmh > (float)$pp->speed_kmh) {
                $maxSpeedKmh = max($maxSpeedKmh, (float)$p->speed_kmh);
            }
            if ((float)$p->speed_kmh > 2) {
                $diffSec = Carbon::parse($p->gps_timestamp, 'UTC')
                    ->diffInSeconds(Carbon::parse($pp->gps_timestamp, 'UTC'));
                $movingSec += min((int)$diffSec, 120); // cap per-interval to 2 min
            }
        }
        $maxSpeedKmh = $allPoints->max('speed_kmh') ?? 0;

        // Subsample to ≤3000 points for map performance
        $step    = $count > 3000 ? (int)ceil($count / 3000) : 1;
        $sampled = $allPoints->filter(fn($p, $k) => $k % $step === 0)->values();

        // Signal gap segments (>5 min between consecutive sampled points)
        $gapThresholdSec = 300;
        $segments        = [[]];
        $signalGaps      = [];
        foreach ($sampled as $i => $pt) {
            if ($i === 0) { $segments[0][] = $pt; continue; }
            $prev    = $sampled[$i - 1];
            $diffSec = Carbon::parse($pt->gps_timestamp, 'UTC')
                ->diffInSeconds(Carbon::parse($prev->gps_timestamp, 'UTC'));
            if ($diffSec > $gapThresholdSec) {
                $signalGaps[] = [
                    'lat'          => $prev->latitude,
                    'lng'          => $prev->longitude,
                    'start_at'     => Carbon::parse($prev->gps_timestamp, 'UTC')->addHours(7)->toISOString(),
                    'end_at'       => Carbon::parse($pt->gps_timestamp, 'UTC')->addHours(7)->toISOString(),
                    'duration_sec' => $diffSec,
                ];
                $segments[] = [];
            }
            $segments[count($segments) - 1][] = $pt;
        }

        $trips = Trip::where('vehicle_id', $vehicle->id)
            ->where(function ($q) use ($date) {
                $q->whereDate('departed_at', $date)
                  ->orWhereDate('arrived_at', $date);
            })
            ->orderBy('departed_at')
            ->get();

        $dayAlerts = Alert::where('vehicle_id', $vehicle->id)
            ->whereDate('triggered_at', $date)
            ->orderByDesc('triggered_at')
            ->get();

        $vehicles = Vehicle::orderBy('name')->get(['id', 'name', 'license_plate']);

        return view('masters.vehicles.history', compact(
            'vehicle', 'date', 'segments', 'signalGaps',
            'totalDistKm', 'movingSec', 'maxSpeedKmh', 'count',
            'trips', 'dayAlerts', 'vehicles'
        ));
    }

    private function haversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $R    = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a    = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        return $R * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}