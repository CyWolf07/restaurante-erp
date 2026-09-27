<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Printer;
use App\Models\RestaurantTable;
use Illuminate\Support\Facades\Log;
use Mike42\Escpos\PrintConnectors\NetworkPrintConnector;
use Mike42\Escpos\PrintConnectors\WindowsPrintConnector;
use Mike42\Escpos\PrintConnectors\FilePrintConnector;
use Mike42\Escpos\Printer as EscposPrinter;

class PrinterService
{
    /**
     * Prueba la conexión con una impresora.
     */
    public function testConnection(Printer $printer): array
    {
        try {
            $escpos = $this->connect($printer);
            $escpos->setJustification(EscposPrinter::JUSTIFY_CENTER);
            $escpos->text("ERP Restaurante\n");
            $escpos->text("Prueba de conexion OK\n");
            $escpos->text(now()->format('d/m/Y H:i:s') . "\n");
            $escpos->feed(3);
            $escpos->cut();
            $escpos->close();

            return ['success' => true, 'message' => 'Impresión de prueba enviada correctamente.'];
        } catch (\Throwable $e) {
            Log::warning("Printer test failed [{$printer->name}]: " . $e->getMessage());

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Imprime comanda de cocina para una orden.
     */
    public function printKitchenTicket(Order $order, ?Printer $printer = null): bool
    {
        return app(TicketPrintService::class)->print($order, 'kitchen');
    }

    /**
     * Pre-ticket (cuenta provisional) al registrar pedido del mesero.
     */
    public function printPreticket(Order $order, ?Printer $printer = null): bool
    {
        return app(TicketPrintService::class)->print($order, 'preticket');
    }

    /**
     * Imprime ticket de cobro.
     */
    public function printReceipt(Order $order, ?Printer $printer = null): bool
    {
        return app(TicketPrintService::class)->print($order, 'receipt');
    }

    public function resolvePrinter(Order $order, string $purpose): ?Printer
    {
        $table = $order->restaurantTable
            ?? RestaurantTable::where('number', $order->table_number)->first();

        if ($table) {
            $assigned = $purpose === 'kitchen' ? $table->kitchenPrinter : $table->receiptPrinter;
            if ($assigned?->active) {
                return $assigned;
            }
        }

        return Printer::active()
            ->forPurpose($purpose)
            ->where('is_default', true)
            ->first()
            ?? Printer::active()->forPurpose($purpose)->first();
    }

    /**
     * Lista impresoras instaladas en Windows (para el panel admin).
     */
    public function listWindowsPrinters(): array
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            return [];
        }

        $output = [];
        // Intentar con PowerShell (estándar moderno de Windows)
        $lines = [];
        @exec('powershell -Command "Get-Printer | Select-Object -ExpandProperty Name"', $lines, $resultCode);

        if ($resultCode !== 0 || empty($lines)) {
            // Fallback a wmic si PowerShell falla o no retorna nada
            $lines = [];
            @exec('wmic printer get name', $lines);
        }

        foreach ($lines as $line) {
            $name = trim($line);
            if ($name && $name !== 'Name') {
                $output[] = $name;
            }
        }

        return array_values(array_unique($output));
    }

    public function connect(Printer $printer): EscposPrinter
    {
        $connector = match ($printer->connection_type) {
            'windows' => $this->resolveWindowsConnector($printer->address),
            default   => new NetworkPrintConnector($printer->address, (int) $printer->port),
        };

        return new EscposPrinter($connector);
    }

    private function resolveWindowsConnector(string $address)
    {
        $addressUpper = strtoupper(trim($address));
        if (in_array($addressUpper, ['LPT1', 'LPT2', 'LPT3', 'COM1', 'COM2', 'COM3', 'COM4', 'PRN'], true)) {
            return new FilePrintConnector($addressUpper);
        }

        // Usar nuestro conector personalizado que interactúa con la cola de impresión de Windows
        return new WindowsSpoolPrintConnector($address);
    }
}
