<?php

namespace App\Services;

use App\Models\FiscalDocument;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Carbon;

class FiscalDocumentService
{
    public function createDraftForPaidOrder(Order $order, User $cashier, string $paymentMethod): FiscalDocument
    {
        $order->loadMissing([
            'waiter', 'cashier', 'restaurantTable',
            'details.product', 'details.modifiers.modifier',
        ]);

        $snapshot = [
            'schema_version' => 2,
            'generated_at' => now()->toIso8601String(),
            'seller' => [
                'name' => config('app.restaurant_name'),
                'tax_id' => config('fiscal.seller.tax_id'),
                'verification_digit' => config('fiscal.seller.verification_digit'),
                'address' => config('app.restaurant_address'),
                'city' => config('fiscal.seller.city'),
            ],
            'sale' => [
                'order_id' => $order->id,
                'table' => $order->restaurantTable?->display_name ?? 'Mesa '.$order->table_number,
                'waiter' => $order->waiter?->name,
                'cashier' => $cashier->name,
                'paid_at' => now()->toIso8601String(),
                'payment_method' => $paymentMethod,
                'payment_breakdown' => $order->payment_breakdown,
            ],
            'items' => $order->details->map(function ($detail) {
                return [
                    'product_id' => $detail->product_id,
                    'code' => (string) $detail->product_id,
                    'description' => $detail->product?->name ?? 'Producto',
                    'quantity' => $detail->quantity,
                    'unit_price' => (string) $detail->unit_price,
                    'discount' => (string) $detail->discount,
                    'subtotal' => (string) $detail->subtotal,
                    'tax_type' => $detail->tax_type,
                    'tax_rate' => (string) ($detail->tax_rate ?? config('app.tax_rate', 0)),
                    'modifiers' => $detail->modifiers->map(fn ($modifier) => [
                        'description' => $modifier->modifier?->name ?? 'Modificador',
                        'quantity' => $modifier->quantity,
                        'unit_price' => (string) $modifier->unit_price,
                        'subtotal' => (string) $modifier->subtotal,
                    ])->values()->all(),
                ];
            })->values()->all(),
            'totals' => [
                'subtotal' => (string) $order->subtotal,
                'tax' => (string) $order->tax,
                'total' => (string) $order->total,
                'tax_calculation' => 'Impuesto por línea según tasa guardada; redondeo del total a 2 decimales',
            ],
        ];

        return FiscalDocument::firstOrCreate(
            ['order_id' => $order->id],
            [
                'status' => 'pending_review',
                'document_type' => config('fiscal.default_document_type', 'electronic_invoice'),
                'payment_method' => $paymentMethod,
                'subtotal' => $order->subtotal,
                'tax' => $order->tax,
                'total' => $order->total,
                'buyer_data' => ['consumer_final' => true],
                'document_snapshot' => $snapshot,
            ]
        );
    }

    public function unsentSummary(?Carbon $date = null): array
    {
        $query = FiscalDocument::unsent();
        if ($date) {
            $query->whereBetween('created_at', [$date->copy()->startOfDay(), $date->copy()->endOfDay()]);
        }

        return [
            'count' => (clone $query)->count(),
            'total' => (float) $query->sum('total'),
        ];
    }
}
