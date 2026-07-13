<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$key = env('GOOGLE_MAPS_API_KEY');
$url = "https://maps.googleapis.com/maps/api/directions/json?origin=-7.9405406,112.6914971&destination=-7.9527353,112.6900456&departure_time=now&key={$key}";
$res = Illuminate\Support\Facades\Http::timeout(5)->get($url);
echo substr($res->body(), 0, 1000);

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
echo json_encode(DB::table('gps_telemetry')->orderByDesc('id')->first(), JSON_PRETTY_PRINT);
