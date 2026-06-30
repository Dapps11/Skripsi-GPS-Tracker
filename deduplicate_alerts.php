<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Alert;

$alerts = Alert::orderByDesc('triggered_at')->get();
$map = [];

foreach ($alerts as $a) {
    $dateStr = $a->triggered_at ? $a->triggered_at->toDateString() : '';
    $key = $a->trip_id 
        ? ($a->trip_id . '_' . $a->alert_type) 
        : ($a->vehicle_id . '_' . $a->alert_type . '_' . $dateStr);
        
    if (!isset($map[$key])) {
        $a->group_count_value = 1; // avoid model property saving issues
        $map[$key] = $a;
    } else {
        $map[$key]->group_count_value++;
        $a->delete();
    }
}

foreach ($map as $a) {
    if ($a->group_count_value > 1) {
        $baseMsg = preg_replace('/ \(x\d+\)$/', '', $a->message);
        $newMessage = $baseMsg . " (x{$a->group_count_value})";
        Alert::where('id', $a->id)->update(['message' => $newMessage]);
    }
}

echo "Database deduplicated successfully.\n";
