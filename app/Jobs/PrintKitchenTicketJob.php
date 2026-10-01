<?php

namespace App\Jobs;

use App\Models\Order;
use App\Services\PrinterService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class PrintKitchenTicketJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 30;

    public function __construct(public Order $order) {}

    public function handle(PrinterService $printer): bool
    {
        return $printer->printKitchenTicket($this->order);
    }
}
