<?php

namespace App\Http\Controllers;

use App\Models\IotDevice;
use App\Models\Vehicle;
use App\Models\Driver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DeviceController extends Controller
{
    public function index()
    {
        $devices = IotDevice::with(['vehicle', 'driver'])
                            ->orderByDesc('created_at')
                            ->paginate(15);

        $summary = DB::table('v_device_summary')->first();

        return view('devices.index', compact('devices', 'summary'));
    }

    public function create()
    {
        $vehicles = Vehicle::whereNull('deleted_at')->orderBy('name')->get();
        $drivers  = Driver::whereNull('deleted_at')
                          ->whereIn('status', ['available', 'on_duty'])
                          ->orderBy('full_name')
                          ->get();

        $nextDeviceId = $this->nextDeviceId();

        return view('devices.create', compact('vehicles', 'drivers', 'nextDeviceId'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'vehicle_id'       => 'nullable|exists:vehicles,id',
            'driver_id'        => 'nullable|exists:drivers,id',
            'imei'             => 'nullable|string|max:20',
            'iccid'            => 'nullable|string|max:30',
            'apn'              => 'nullable|string|max:100',
            'phone_number'     => 'nullable|string|max:20',
            'network_operator' => 'nullable|string|max:50',
            'notes'            => 'nullable|string',
        ]);

        $validated['device_type'] = 'tracker'; // set otomatis

        // device_id digenerate otomatis di server, retry kalau kebetulan bentrok (race condition)
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $validated['device_id'] = $this->nextDeviceId();
            if (! IotDevice::where('device_id', $validated['device_id'])->exists()) {
                break;
            }
        }

        IotDevice::create($validated);

        return redirect()->route('devices.index')
                        ->with('success', 'Device berhasil ditambahkan dengan ID ' . $validated['device_id'] . '.');
    }

    private function nextDeviceId(): string
    {
        $prefix = 'TRACKER-';

        $lastNumber = IotDevice::where('device_id', 'like', $prefix . '%')
            ->pluck('device_id')
            ->map(function ($code) use ($prefix) {
                return preg_match('/^' . preg_quote($prefix, '/') . '(\d+)$/', $code, $m) ? (int) $m[1] : 0;
            })
            ->max() ?? 0;

        return $prefix . str_pad($lastNumber + 1, 3, '0', STR_PAD_LEFT);
    }

    public function edit(IotDevice $device)
    {
        $vehicles = Vehicle::whereNull('deleted_at')->orderBy('name')->get();
        $drivers  = Driver::whereNull('deleted_at')->orderBy('full_name')->get();

        return view('devices.edit', compact('device', 'vehicles', 'drivers'));
    }

    public function update(Request $request, IotDevice $device)
    {
        $validated = $request->validate([
            'device_id'        => 'required|string|max:50|unique:iot_devices,device_id,' . $device->id,
            'device_type'      => 'required|in:sim7600,raspberry,combined',
            'vehicle_id'       => 'nullable|exists:vehicles,id',
            'driver_id'        => 'nullable|exists:drivers,id',
            'imei'             => 'nullable|string|max:20',
            'iccid'            => 'nullable|string|max:30',
            'apn'              => 'nullable|string|max:100',
            'phone_number'     => 'nullable|string|max:20',
            'network_operator' => 'nullable|string|max:50',
            'notes'            => 'nullable|string',
        ]);

        $device->update($validated);

        return redirect()->route('devices.index')
                         ->with('success', 'Device berhasil diperbarui.');
    }

    public function destroy(IotDevice $device)
    {
        $device->delete();
        return redirect()->route('devices.index')
                         ->with('success', 'Device berhasil dihapus.');
    }
}