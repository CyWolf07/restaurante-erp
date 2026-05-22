<?php

namespace App\Services;

use App\Models\Order;
use App\Models\PrintTemplate;
use App\Models\Printer;
use Illuminate\Support\Facades\Log;
use Mike42\Escpos\Printer as EscposPrinter;

class TicketPrintService
{
    public function __construct(
        private PrinterService $printers
    ) {}

    public function print(Order $order, string $templateSlug): bool
    {
        $settings = PrintTemplate::settingsFor($templateSlug);
        $purpose = $settings['printer_purpose'] ?? ($templateSlug === 'kitchen' ? 'kitchen' : 'receipt');

        $printer = $this->printers->resolvePrinter($order, $purpose);
        if (!$printer) {
            Log::info("Sin impresora ({$purpose}) para plantilla {$templateSlug}");

            return false;
        }

        $order->load([
            'details.product',
            'details.modifiers.modifier',
            'waiter',
            'kitchenSentBy',
            'restaurantTable',
            'cashier',
        ]);

        try {
            $escpos = $this->connect($printer);
            $width = ($printer->paper_width ?? 80) >= 80 ? 48 : 32;

            $this->renderHeader($escpos, $order, $settings, $width, $templateSlug);
            $this->renderBody($escpos, $order, $settings, $width, $templateSlug);
            $this->renderFooter($escpos, $order, $settings, $width);

            $escpos->feed(3);
            $escpos->cut();
            $escpos->close();

            return true;
        } catch (\Throwable $e) {
            Log::error("Ticket print [{$templateSlug}] failed: " . $e->getMessage());

            return false;
        }
    }

    /** Vista previa en texto plano para el programador. */
    public function preview(string $templateSlug, ?array $settings = null): string
    {
        $settings ??= PrintTemplate::settingsFor($templateSlug);
        $width = 48;
        $lines = [];

        $replace = fn (string $text) => str_replace(
            ['{{restaurant_name}}', '{{title}}', '{{subtitle}}', '{{table}}', '{{waiter}}', '{{datetime}}', '{{subtotal}}', '{{tax}}', '{{total}}'],
            [config('app.restaurant_name'), $settings['title'] ?? '', $settings['subtitle'] ?? '', 'Mesa 5', 'Juan Mesero', now()->format('d/m/Y H:i'), cop(45000), cop(7200), cop(52200)],
            $text
        );

        if ($settings['show_restaurant_name'] ?? true) {
            $lines[] = $this->center(config('app.restaurant_name'), $width);
        }
        if (!empty($settings['title'])) {
            $lines[] = $this->center($replace($settings['title']), $width);
        }
        if (!empty($settings['subtitle'])) {
            $lines[] = $this->center($replace($settings['subtitle']), $width);
        }
        foreach ($settings['extra_header_lines'] ?? [] as $line) {
            if (trim($line) !== '') {
                $lines[] = $this->center($replace($line), $width);
            }
        }
        $lines[] = str_repeat('-', $width);

        if ($settings['show_table'] ?? true) {
            $lines[] = 'Mesa 5';
        }
        if ($settings['show_waiter'] ?? true) {
            $lines[] = 'Mesero: Juan Mesero';
        }
        if ($settings['show_kitchen_sender'] ?? false) {
            $lines[] = 'Envio cocina: Juan (14:30)';
        }
        if ($settings['show_datetime'] ?? true) {
            $lines[] = now()->format('d/m/Y H:i');
        }
        $lines[] = str_repeat('-', $width);

        if ($settings['show_items'] ?? true) {
            $lines[] = '2x Hamburguesa';
            if ($settings['show_item_subtotal'] ?? true) {
                $lines[] = '   ' . cop(36000);
            }
            if ($settings['show_modifiers'] ?? true) {
                $lines[] = '  + 1x Queso extra';
            }
            if ($settings['show_item_comments'] ?? true) {
                $lines[] = '  >> Sin cebolla';
            }
        }

        $lines[] = str_repeat('-', $width);
        if ($settings['show_subtotal'] ?? false) {
            $lines[] = 'Subtotal: ' . cop(45000);
        }
        if ($settings['show_tax'] ?? false) {
            $lines[] = 'IVA: ' . cop(7200);
        }
        if ($settings['show_total'] ?? true) {
            $lines[] = 'TOTAL: ' . cop(52200);
        }
        foreach ($settings['footer_lines'] ?? [] as $line) {
            if (trim($line) !== '') {
                $lines[] = $this->center($replace($line), $width);
            }
        }

        return implode("\n", $lines);
    }

    private function renderHeader(EscposPrinter $escpos, Order $order, array $s, int $width, string $slug): void
    {
        $escpos->setJustification(EscposPrinter::JUSTIFY_CENTER);

        if ($s['show_restaurant_name'] ?? true) {
            $escpos->text(config('app.restaurant_name') . "\n");
        }

        if (!empty($s['title'])) {
            if ($s['title_bold'] ?? true) {
                $escpos->setEmphasis(true);
            }
            $escpos->text(($s['title']) . "\n");
            $escpos->setEmphasis(false);
        }

        if (!empty($s['subtitle'])) {
            $escpos->text($s['subtitle'] . "\n");
        }

        foreach ($s['extra_header_lines'] ?? [] as $line) {
            $line = trim($line);
            if ($line !== '') {
                $escpos->text($line . "\n");
            }
        }

        $escpos->text(str_repeat('-', $width) . "\n");
        $escpos->setJustification(EscposPrinter::JUSTIFY_LEFT);
    }

    private function renderBody(EscposPrinter $escpos, Order $order, array $s, int $width, string $slug): void
    {
        $tableLabel = $order->restaurantTable?->display_name ?? "Mesa {$order->table_number}";

        if ($s['show_table'] ?? true) {
            if ($s['table_large'] ?? false) {
                $escpos->setEmphasis(true);
                $escpos->setTextSize(2, 2);
                $escpos->text("{$tableLabel}\n");
                $escpos->setTextSize(1, 1);
                $escpos->setEmphasis(false);
            } else {
                $escpos->text("{$tableLabel}\n");
            }
        }

        if ($s['show_waiter'] ?? true) {
            $escpos->text('Mesero: ' . ($order->waiter->name ?? '—') . "\n");
        }

        if (($s['show_kitchen_sender'] ?? false) && $slug === 'kitchen') {
            $sender = $order->kitchenSentBy?->name ?? $order->waiter?->name ?? '—';
            $sentAt = $order->kitchen_sent_at?->format('H:i') ?? now()->format('H:i');
            $escpos->text("Envio cocina: {$sender} ({$sentAt})\n");
        }

        if ($s['show_datetime'] ?? true) {
            $escpos->text(now()->format('d/m/Y H:i') . "\n");
        }

        $escpos->text(str_repeat('-', $width) . "\n");

        if ($s['show_items'] ?? true) {
            foreach ($order->details as $detail) {
                $escpos->setEmphasis($slug === 'kitchen');
                $escpos->text("{$detail->quantity}x {$detail->product->name}\n");
                $escpos->setEmphasis(false);

                if ($s['show_modifiers'] ?? true) {
                    foreach ($detail->modifiers as $mod) {
                        $escpos->text("  + {$mod->quantity}x {$mod->modifier->name}\n");
                    }
                }

                if ($s['show_item_subtotal'] ?? false) {
                    $escpos->text('   ' . cop($detail->subtotal) . "\n");
                }

                if ($s['show_item_comments'] ?? true && $detail->comments) {
                    $escpos->text("  >> {$detail->comments}\n");
                }
            }
        }

        $escpos->text(str_repeat('-', $width) . "\n");
    }

    private function renderFooter(EscposPrinter $escpos, Order $order, array $s, int $width): void
    {
        if ($s['show_subtotal'] ?? false) {
            $escpos->text('Subtotal: ' . cop($order->subtotal) . "\n");
        }
        if ($s['show_tax'] ?? false) {
            $escpos->text('IVA: ' . cop($order->tax) . "\n");
        }
        if ($s['show_total'] ?? true) {
            if ($s['total_bold'] ?? true) {
                $escpos->setEmphasis(true);
            }
            $escpos->text('TOTAL: ' . cop($order->total) . "\n");
            $escpos->setEmphasis(false);
        }

        foreach ($s['footer_lines'] ?? [] as $line) {
            $line = trim($line);
            if ($line !== '') {
                $escpos->setJustification(EscposPrinter::JUSTIFY_CENTER);
                $escpos->text($line . "\n");
                $escpos->setJustification(EscposPrinter::JUSTIFY_LEFT);
            }
        }
    }

    private function center(string $text, int $width): string
    {
        $len = mb_strlen($text);
        if ($len >= $width) {
            return mb_substr($text, 0, $width);
        }
        $pad = (int) floor(($width - $len) / 2);

        return str_repeat(' ', $pad) . $text;
    }

    private function connect(Printer $printer): EscposPrinter
    {
        return $this->printers->connect($printer);
    }
}
