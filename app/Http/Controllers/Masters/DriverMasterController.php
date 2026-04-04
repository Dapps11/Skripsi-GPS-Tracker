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
        return view('masters.drivers.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'driver_code'    => 'required|string|max:30|unique:drivers',
            'full_name'      => 'required|string|max:100',
            'phone'          => 'nullable|string|max:20',
            'license_number' => 'nullable|string|max:50',
            'license_expiry' => 'nullable|date',
            'address'        => 'nullable|string',
            'status'         => 'required|in:available,on_duty,off_duty,inactive',
            'notes'          => 'nullable|string',
        ]);

        Driver::create($validated);

        return redirect()->route('master.drivers.index')
                         ->with('success', 'Supir berhasil ditambahkan.');
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