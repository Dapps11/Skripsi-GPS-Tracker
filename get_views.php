<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$views = DB::select("SELECT TABLE_NAME, VIEW_DEFINITION FROM information_schema.VIEWS WHERE TABLE_SCHEMA = 'skripsi_production_clone'");
foreach ($views as $v) {
    echo "VIEW: " . $v->TABLE_NAME . "\n";
    echo $v->VIEW_DEFINITION . "\n\n";
}
