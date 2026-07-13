<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
echo json_encode(DB::table('gps_telemetry')->orderByDesc('id')->first(), JSON_PRETTY_PRINT);
