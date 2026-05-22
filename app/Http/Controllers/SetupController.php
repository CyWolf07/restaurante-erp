<?php

namespace App\Http\Controllers;

use App\Services\NetworkSetupService;

class SetupController extends Controller
{
    public function network(NetworkSetupService $network)
    {
        return view('setup.network', [
            'lan' => $network->getLanInfo(),
        ]);
    }

    public function posStatus()
    {
        $tables = \App\Models\RestaurantTable::active()->ordered()->get()->map(function ($table) {
            $order = $table->activeOrder();

            return [
                'id'     => $table->id,
                'number' => $table->number,
                'name'   => $table->display_name,
                'zone'   => $table->zone,
                'color'  => $table->status_color,
                'status' => $order?->status_label ?? 'Libre',
                'total'           => $order ? (float) $order->total : 0,
                'total_formatted' => $order ? cop($order->total) : '',
                'url'    => route('cashier.table-detail', $table->number),
            ];
        });

        return response()->json(['tables' => $tables, 'updated_at' => now()->toIso8601String()]);
    }
}
