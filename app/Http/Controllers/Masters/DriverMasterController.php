<?php

namespace App\Http\Controllers\Masters;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use Illuminate\Http\Request;

class DriverMasterController extends Controller
{
    public function index()
    {
        $drivers = Driver::orderBy('full_name')->paginate(15);
        return view('masters.drivers.index', compact('drivers'));
    }

    public function create()
    {
        $nextDriverCode = $this->nextDriverCode();

        return view('masters.drivers.create', compact('nextDriverCode'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'full_name'      => 'required|string|max:100',
            'phone'          => 'nullable|string|max:20',
            'license_number' => 'nullable|string|max:50',
            'license_expiry' => 'nullable|date',
            'address'        => 'nullable|string',
            'status'         => 'required|in:available,on_duty,off_duty,inactive',
            'notes'          => 'nullable|string',
        ]);

        // driver_code digenerate otomatis di server, retry kalau kebetulan bentrok
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $validated['driver_code'] = $this->nextDriverCode();
            if (! Driver::where('driver_code', $validated['driver_code'])->exists()) {
                break;
            }
        }

        Driver::create($validated);

        return redirect()->route('master.drivers.index')
                         ->with('success', 'Supir berhasil ditambahkan dengan kode ' . $validated['driver_code'] . '.');
    }

    private function nextDriverCode(): string
    {
        $prefix = 'DRV-';

        $lastNumber = Driver::where('driver_code', 'like', $prefix . '%')
            ->pluck('driver_code')
            ->map(function ($code) use ($prefix) {
                return preg_match('/^' . preg_quote($prefix, '/') . '(\d+)$/', $code, $m) ? (int) $m[1] : 0;
            })
            ->max() ?? 0;

        return $prefix . str_pad($lastNumber + 1, 3, '0', STR_PAD_LEFT);
    }

    public function edit(Driver $driver)
    {
        return view('masters.drivers.edit', compact('driver'));
    }

    public function update(Request $request, Driver $driver)
    {
        $validated = $request->validate([
            'driver_code'    => 'required|string|max:30|unique:drivers,driver_code,' . $driver->id,
            'full_name'      => 'required|string|max:100',
            'phone'          => 'nullable|string|max:20',
            'license_number' => 'nullable|string|max:50',
            'license_expiry' => 'nullable|date',
            'address'        => 'nullable|string',
            'status'         => 'required|in:available,on_duty,off_duty,inactive',
            'notes'          => 'nullable|string',
        ]);

        $driver->update($validated);

        return redirect()->route('master.drivers.index')
                         ->with('success', 'Data supir diperbarui.');
    }

    public function destroy(Driver $driver)
    {
        $driver->delete();
        return redirect()->route('master.drivers.index')
                         ->with('success', 'Supir berhasil dihapus.');
    }
}