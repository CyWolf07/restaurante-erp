<?php

namespace App\Http\Controllers\Programmer;

use App\Http\Controllers\Controller;
use App\Models\PrintTemplate;
use App\Services\TicketPrintService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PrintTemplateController extends Controller
{
    public function index()
    {
        PrintTemplate::ensureDefaults();

        $templates = PrintTemplate::orderBy('slug')->get();
        $types = config('ticket_print.types', []);

        return view('programmer.print-templates.index', compact('templates', 'types'));
    }

    public function edit(string $slug, TicketPrintService $preview)
    {
        PrintTemplate::ensureDefaults();

        $template = PrintTemplate::where('slug', $slug)->firstOrFail();
        $settings = PrintTemplate::settingsFor($slug);
        $placeholders = config("ticket_print.placeholders.{$slug}", config('ticket_print.placeholders.preticket', []));

        return view('programmer.print-templates.edit', [
            'template'     => $template,
            'settings'     => $settings,
            'placeholders' => $placeholders,
            'previewText'  => $preview->preview($slug, $settings),
            'types'        => config('ticket_print.types', []),
        ]);
    }

    public function update(Request $request, string $slug, TicketPrintService $preview)
    {
        PrintTemplate::ensureDefaults();

        $template = PrintTemplate::where('slug', $slug)->firstOrFail();

        $data = $request->validate([
            'title'                => 'nullable|string|max:80',
            'subtitle'             => 'nullable|string|max:120',
            'extra_header_lines'   => 'nullable|string',
            'footer_lines'         => 'nullable|string',
            'printer_purpose'      => 'required|in:kitchen,receipt',
            'show_restaurant_name' => 'sometimes|boolean',
            'show_table'           => 'sometimes|boolean',
            'table_large'          => 'sometimes|boolean',
            'show_waiter'          => 'sometimes|boolean',
            'show_kitchen_sender'  => 'sometimes|boolean',
            'show_datetime'        => 'sometimes|boolean',
            'show_items'           => 'sometimes|boolean',
            'show_item_subtotal'   => 'sometimes|boolean',
            'show_modifiers'       => 'sometimes|boolean',
            'show_item_comments'   => 'sometimes|boolean',
            'show_subtotal'        => 'sometimes|boolean',
            'show_tax'             => 'sometimes|boolean',
            'show_total'           => 'sometimes|boolean',
            'title_bold'           => 'sometimes|boolean',
            'total_bold'           => 'sometimes|boolean',
        ]);

        $settings = [
            'title'                => $data['title'] ?? '',
            'subtitle'             => $data['subtitle'] ?? '',
            'extra_header_lines'   => $this->linesFromTextarea($data['extra_header_lines'] ?? ''),
            'footer_lines'         => $this->linesFromTextarea($data['footer_lines'] ?? ''),
            'printer_purpose'      => $data['printer_purpose'],
            'show_restaurant_name' => $request->boolean('show_restaurant_name'),
            'show_table'           => $request->boolean('show_table'),
            'table_large'          => $request->boolean('table_large'),
            'show_waiter'          => $request->boolean('show_waiter'),
            'show_kitchen_sender'  => $request->boolean('show_kitchen_sender'),
            'show_datetime'        => $request->boolean('show_datetime'),
            'show_items'           => $request->boolean('show_items'),
            'show_item_subtotal'   => $request->boolean('show_item_subtotal'),
            'show_modifiers'       => $request->boolean('show_modifiers'),
            'show_item_comments'   => $request->boolean('show_item_comments'),
            'show_subtotal'        => $request->boolean('show_subtotal'),
            'show_tax'             => $request->boolean('show_tax'),
            'show_total'           => $request->boolean('show_total'),
            'title_bold'           => $request->boolean('title_bold'),
            'total_bold'           => $request->boolean('total_bold'),
        ];

        $template->update([
            'settings'   => $settings,
            'updated_by' => Auth::id(),
        ]);

        return redirect()
            ->route('programmer.print-templates.edit', $slug)
            ->with('success', 'Formato de impresión guardado.')
            ->with('preview', $preview->preview($slug, $settings));
    }

    private function linesFromTextarea(string $text): array
    {
        return array_values(array_filter(
            array_map('trim', preg_split('/\r\n|\r|\n/', $text) ?: []),
            fn ($l) => $l !== ''
        ));
    }
}
