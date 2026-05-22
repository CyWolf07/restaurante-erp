<?php

namespace App\Listeners;

use App\Events\SupplyStockLow;
use Illuminate\Support\Facades\Log;

class NotifyLowStockListener
{
    public function handle(SupplyStockLow $event): void
    {
        $supply = $event->supply;
        Log::warning("⚠️ STOCK BAJO: {$supply->name} — Stock actual: {$supply->current_stock} {$supply->unit_label} (Mínimo: {$supply->min_stock})");

        // Almacenar notificación en sesión para mostrar en el dashboard
        $notifications = cache()->get('low_stock_notifications', []);
        $notifications[$supply->id] = [
            'supply_name'   => $supply->name,
            'current_stock' => $supply->current_stock,
            'min_stock'     => $supply->min_stock,
            'unit_label'    => $supply->unit_label,
            'timestamp'     => now()->toIso8601String(),
        ];
        cache()->put('low_stock_notifications', $notifications, now()->addHours(12));
    }
}
