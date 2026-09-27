<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class EnsureApplicationKey extends Command
{
    protected $signature = 'app:ensure-key';

    protected $description = 'Genera la clave solamente si la instalación todavía no tiene una.';

    public function handle(): int
    {
        if (filled(config('app.key'))) {
            $this->info('Clave existente conservada.');

            return self::SUCCESS;
        }

        return $this->call('key:generate', ['--force' => true]);
    }
}
