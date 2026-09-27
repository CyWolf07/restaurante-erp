<?php

use App\Models\ProductionOrder;
use App\Models\User;
use App\Services\ProductionService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Validation\ValidationException;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[$script, $productionId, $userId, $ready, $release] = $argv;
file_put_contents($ready, 'ready');
$deadline = microtime(true) + 20;
while (! is_file($release)) {
    if (microtime(true) > $deadline) {
        exit(2);
    }
    usleep(10000);
}
try {
    $production = ProductionOrder::findOrFail($productionId);
    app(ProductionService::class)->complete($production, 6, User::findOrFail($userId));
    echo 'completed';
} catch (ValidationException) {
    echo 'unavailable';
}
