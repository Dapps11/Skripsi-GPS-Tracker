<?php

namespace App\Http\Controllers\Masters;

use App\Http\Controllers\Controller;
use App\Models\Vehicle;
use Illuminate\Http\Request;

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
}