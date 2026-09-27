<?php

namespace App\Services;

use App\Models\CashierDailyClosure;
use App\Models\DailyReportZ;
use App\Models\Order;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PosOperationService
{
    /** Serialize short POS writes, including closure checks, on both database engines. */
    public function run(callable $operation, bool $requireOpen = true): mixed
    {
        return DB::transaction(function () use ($operation, $requireOpen) {
            // UPDATE also obtains a write lock on SQLite, where FOR UPDATE is ignored.
            $locked = DB::table('pos_operation_locks')->where('name', 'operations')->update(['used_at' => now()]);
            if ($locked !== 1) {
                throw new \RuntimeException('No se pudo obtener el control de operaciones del POS. Revisa las migraciones.');
            }

            if ($requireOpen && (
                CashierDailyClosure::whereDate('fiscal_date', today())->exists()
                || DailyReportZ::whereDate('fiscal_date', today())->exists()
            )) {
                throw ValidationException::withMessages(['order' => 'La caja de hoy está cerrada. No se pueden registrar ni modificar ventas.']);
            }

            return $operation();
        }, 3);
    }

    public function editableOrder(string $id): Order
    {
        $order = Order::whereKey($id)->lockForUpdate()->firstOrFail();
        if ($order->isPaid() || $order->isCancelled() || $order->isLocked()) {
            throw ValidationException::withMessages(['order' => 'La orden está cerrada o bloqueada. Actualiza la pantalla.']);
        }

        return $order;
    }

    public function record(Order $order, string $action, array $data = []): void
    {
        DB::table('order_events')->insert([
            'order_id' => $order->id,
            'user_id' => Auth::id() ?? $order->waiter_id,
            'action' => $action,
            'data' => json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'created_at' => now(),
        ]);
    }
}
