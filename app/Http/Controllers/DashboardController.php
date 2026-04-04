<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $fleetSummary     = DB::table('v_fleet_summary')->first();
        $vehiclePositions = DB::table('v_vehicle_last_position')->get();
        $unreadAlerts     = Alert::where('is_read', false)
                                 ->orderByDesc('triggered_at')
                                 ->take(10)
                                 ->get();

        return view('dashboard.index', compact(
            'fleetSummary',
            'vehiclePositions',
            'unreadAlerts'
        ));
    }
}