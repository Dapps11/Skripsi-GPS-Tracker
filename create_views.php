<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

DB::statement("DROP VIEW IF EXISTS v_active_trip_detail");
DB::statement("
CREATE VIEW v_active_trip_detail AS
SELECT 
    t.*,
    v.name AS vehicle_name,
    v.license_plate,
    v.vehicle_type,
    d.full_name AS driver_name,
    d.phone AS driver_phone
FROM trips t
JOIN vehicles v ON t.vehicle_id = v.id
LEFT JOIN drivers d ON t.driver_id = d.id
WHERE t.status = 'in_progress'
");

DB::statement("DROP VIEW IF EXISTS v_vehicle_last_position");
DB::statement("
CREATE VIEW v_vehicle_last_position AS
SELECT 
    v.id,
    v.name,
    v.license_plate,
    v.status,
    v.vehicle_code,
    i.last_latitude AS latitude,
    i.last_longitude AS longitude,
    i.last_speed_kmh AS speed_kmh,
    NULL AS heading,
    i.last_heartbeat AS updated_at,
    d.full_name AS driver_name
FROM vehicles v
LEFT JOIN iot_devices i ON v.id = i.vehicle_id
LEFT JOIN drivers d ON i.driver_id = d.id
WHERE v.deleted_at IS NULL
");

DB::statement("DROP VIEW IF EXISTS v_fleet_summary");
DB::statement("
CREATE VIEW v_fleet_summary AS
SELECT 
    COUNT(v.id) AS total_vehicles,
    SUM(CASE WHEN v.status = 'moving' THEN 1 ELSE 0 END) AS moving,
    SUM(CASE WHEN v.status = 'idle' THEN 1 ELSE 0 END) AS idle,
    SUM(CASE WHEN i.status = 'offline' THEN 1 ELSE 0 END) AS offline,
    SUM(CASE WHEN i.status = 'online' THEN 1 ELSE 0 END) AS online
FROM vehicles v
LEFT JOIN iot_devices i ON v.id = i.vehicle_id
WHERE v.deleted_at IS NULL
");

echo "Views created successfully.\n";
