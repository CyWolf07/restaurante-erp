<?php

namespace App\Services;

use App\Models\Supply;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class CommerceInventoryImportService
{
    private const HEADER_ALIASES = [
        'punto'              => 'point',
        'point'              => 'point',
        'ubicacion'          => 'location',
        'location'           => 'location',
        'departamento'       => 'department_number',
        'no_departamento'    => 'department_number',
        'n_departamento'     => 'department_number',
        'no_depto'           => 'department_number',
        'depto'              => 'department_number',
        'familia'            => 'family',
        'family'             => 'family',
        'codigo'             => 'code',
        'code'               => 'code',
        'nombre'             => 'name',
        'name'               => 'name',
        'articulo'           => 'name',
        'producto'           => 'name',
        'item'               => 'name',
        'pvp'                => 'pvp',
        'p_v_p'              => 'pvp',
        'precio'             => 'pvp',
        'precio_venta'       => 'pvp',
        'stock'              => 'current_stock',
        'stock_inicial'      => 'current_stock',
        'cantidad'           => 'current_stock',
        'unidad'             => 'unit_type',
        'unit_type'          => 'unit_type',
        'costo'              => 'cost_per_unit',
        'costo_unitario'     => 'cost_per_unit',
    ];

    public function import(UploadedFile $file, bool $updateExisting = true): array
    {
        $result = [
            'created'  => 0,
            'updated'  => 0,
            'skipped'  => 0,
            'errors'   => [],
            'total_rows' => 0,
        ];

        $path = $file->getRealPath();
        $handle = fopen($path, 'r');
        if ($handle === false) {
            $result['errors'][] = 'No se pudo leer el archivo.';
            return $result;
        }

        $firstLine = fgets($handle);
        if ($firstLine === false) {
            fclose($handle);
            $result['errors'][] = 'El archivo está vacío.';
            return $result;
        }

        $delimiter = $this->detectDelimiter($firstLine);
        rewind($handle);

        $header = fgetcsv($handle, 0, $delimiter);
        if (!$header) {
            fclose($handle);
            $result['errors'][] = 'No se encontró fila de encabezados.';
            return $result;
        }

        $map = $this->buildColumnMap($header);
        if (!isset($map['code'])) {
            fclose($handle);
            $result['errors'][] = 'Falta la columna CODIGO (o código).';
            return $result;
        }

        $rowNum = 1;
        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            $rowNum++;
            if ($this->isEmptyRow($row)) {
                continue;
            }

            $result['total_rows']++;
            $data = $this->rowToData($row, $map, $rowNum, $result['errors']);

            if ($data === null) {
                $result['skipped']++;
                continue;
            }

            $existing = Supply::where('code', $data['code'])->first();

            if ($existing) {
                if (!$updateExisting) {
                    $result['skipped']++;
                    $result['errors'][] = "Fila {$rowNum}: código {$data['code']} ya existe (omitido).";
                    continue;
                }
                $existing->update($data);
                $result['updated']++;
            } else {
                Supply::create($data);
                $result['created']++;
            }
        }

        fclose($handle);

        return $result;
    }

    public function templateCsv(): string
    {
        $headers = [
            'PUNTO',
            'UBICACION',
            'No. DEPARTAMENTO',
            'FAMILIA',
            'CODIGO',
            'ARTICULO',
            'P.V.P',
            'STOCK',
            'UNIDAD',
            'COSTO',
        ];

        $example = [
            'El Muelle',
            'Estante A1',
            '1',
            'Proteína',
            'PROD-001',
            'Arroz',
            '8500',
            '50',
            'unit',
            '6000',
        ];

        return $this->toCsvLine($headers) . $this->toCsvLine($example);
    }

    private function toCsvLine(array $fields): string
    {
        $escaped = array_map(function ($field) {
            $field = (string) $field;
            if (str_contains($field, ',') || str_contains($field, '"') || str_contains($field, "\n")) {
                return '"' . str_replace('"', '""', $field) . '"';
            }
            return $field;
        }, $fields);

        return implode(',', $escaped) . "\n";
    }

    private function detectDelimiter(string $line): string
    {
        return substr_count($line, ';') > substr_count($line, ',') ? ';' : ',';
    }

    private function buildColumnMap(array $header): array
    {
        $map = [];
        foreach ($header as $index => $label) {
            $key = $this->normalizeHeader($label);
            if ($key && isset(self::HEADER_ALIASES[$key])) {
                $map[self::HEADER_ALIASES[$key]] = $index;
            }
        }
        return $map;
    }

    private function normalizeHeader(string $label): string
    {
        $label = trim($label);
        $label = preg_replace('/^\xEF\xBB\xBF/', '', $label) ?? $label;
        $label = Str::ascii(mb_strtolower($label));
        $label = preg_replace('/[^a-z0-9]+/', '_', $label) ?? $label;
        return trim($label, '_');
    }

    private function isEmptyRow(array $row): bool
    {
        return count(array_filter($row, fn ($c) => trim((string) $c) !== '')) === 0;
    }

    private function rowToData(array $row, array $map, int $rowNum, array &$errors): ?array
    {
        $get = fn (string $field) => isset($map[$field], $row[$map[$field]])
            ? trim((string) $row[$map[$field]])
            : '';

        $code = $get('code');
        if ($code === '') {
            $errors[] = "Fila {$rowNum}: código vacío.";
            return null;
        }

        $point = $this->normalizePoint($get('point'));
        if (!$point) {
            $errors[] = "Fila {$rowNum} ({$code}): punto inválido «{$get('point')}». Use: El Muelle, Bocagrande, Oficinas o Bodega.";
            return null;
        }

        $family = $this->normalizeFamily($get('family'));
        if (!$family) {
            $errors[] = "Fila {$rowNum} ({$code}): familia inválida «{$get('family')}».";
            return null;
        }

        $department = $this->normalizeDepartment($get('department_number'));
        if ($get('department_number') !== '' && $department === null) {
            $errors[] = "Fila {$rowNum} ({$code}): departamento debe ser 1-8.";
            return null;
        }

        $pvp = $this->parseMoney($get('pvp'));
        if ($pvp === null) {
            $errors[] = "Fila {$rowNum} ({$code}): P.V.P inválido.";
            return null;
        }

        $name = $get('name') ?: $code;
        $stock = $this->parseDecimal($get('current_stock')) ?? 0;
        $cost = $this->parseMoney($get('cost_per_unit')) ?? $pvp;

        return [
            'code'              => $code,
            'name'              => mb_substr($name, 0, 255),
            'point'             => $point,
            'location'          => $get('location') ?: null,
            'department_number' => $department,
            'family'            => $family,
            'pvp'               => $pvp,
            'current_stock'     => max(0, $stock),
            'min_stock'         => 0,
            'cost_per_unit'     => $cost,
            'unit_type'         => $this->normalizeUnit($get('unit_type')),
            'active'            => true,
        ];
    }

    private function normalizePoint(string $value): ?string
    {
        if ($value === '') {
            return 'el_muelle';
        }

        $slug = Str::slug(Str::ascii($value), '_');
        $aliases = [
            'el_muelle'  => 'el_muelle',
            'muelle'     => 'el_muelle',
            'bocagrande' => 'bocagrande',
            'oficinas'   => 'oficinas',
            'bodega'     => 'bodega',
        ];

        return $aliases[$slug] ?? null;
    }

    private function normalizeFamily(string $value): ?string
    {
        if ($value === '') {
            return null;
        }

        $slug = Str::slug(Str::ascii($value), '_');
        $keys = array_keys(config('restaurant.families', []));

        if (in_array($slug, $keys, true)) {
            return $slug;
        }

        foreach (config('restaurant.families', []) as $key => $label) {
            if (Str::slug(Str::ascii($label), '_') === $slug) {
                return $key;
            }
        }

        $partial = [
            'proteina' => 'proteina',
            'granos' => 'granos_abarrotes',
            'abarrotes' => 'granos_abarrotes',
            'lacteos' => 'lacteos_huevos',
            'huevos' => 'lacteos_huevos',
            'bebidas' => 'bebidas_embotelladas',
        ];

        foreach ($partial as $needle => $key) {
            if (str_contains($slug, $needle)) {
                return $key;
            }
        }

        return null;
    }

    private function normalizeDepartment(string $value): ?int
    {
        if ($value === '') {
            return null;
        }

        if (preg_match('/(\d+)/', $value, $m)) {
            $n = (int) $m[1];
            return ($n >= 1 && $n <= 8) ? $n : null;
        }

        return null;
    }

    private function normalizeUnit(string $value): string
    {
        $v = Str::slug(Str::ascii($value), '_');
        return match (true) {
            str_contains($v, 'gram'), str_contains($v, 'gr'), $v === 'g' => 'gram',
            str_contains($v, 'ml'), str_contains($v, 'lit'), str_contains($v, 'mill') => 'milliliter',
            default => 'unit',
        };
    }

    private function parseMoney(string $value): ?float
    {
        if ($value === '') {
            return 0.0;
        }

        $clean = preg_replace('/[^\d,.\-]/', '', $value) ?? '';
        if (str_contains($clean, ',') && str_contains($clean, '.')) {
            $clean = str_replace('.', '', $clean);
            $clean = str_replace(',', '.', $clean);
        } elseif (str_contains($clean, ',')) {
            $clean = str_replace(',', '.', $clean);
        }

        return is_numeric($clean) ? (float) $clean : null;
    }

    private function parseDecimal(string $value): ?float
    {
        return $this->parseMoney($value);
    }
}
