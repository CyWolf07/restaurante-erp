<?php

namespace App\Events;

use App\Models\Supply;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SupplyStockLow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Supply $supply) {}
}
