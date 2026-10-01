<?php

namespace App\Jobs;

use App\Models\Order;
use App\Services\PrinterService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class PrintPreticketJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public Order $order) {}

    public function handle(PrinterService $printer): bool
    {
        $printed = $printer->printPreticket($this->order);
        if ($printed) {
            $this->order->update(['preticket_printed' => true]);
        }

        return $printed;
    }
}
