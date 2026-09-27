<?php

use App\Models\Modifier;
use App\Models\ProductCategory;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $options = [
            ['name' => 'Sancocho', 'group' => 'Tipo de sopa', 'sort_order' => 1],
            ['name' => 'Consome', 'group' => 'Tipo de sopa', 'sort_order' => 2],
            ['name' => 'Frito', 'group' => 'Adicionales', 'sort_order' => 3],
            ['name' => 'Encocado', 'group' => 'Adicionales', 'sort_order' => 4],
            ['name' => 'Criolla', 'group' => 'Adicionales', 'sort_order' => 5],
            ['name' => 'Criolla Directa', 'group' => 'Adicionales', 'sort_order' => 6],
            ['name' => 'Encocado a parte', 'group' => 'Adicionales', 'sort_order' => 7],
            ['name' => 'Sin sopa', 'group' => 'Sin acompanamiento', 'sort_order' => 8],
            ['name' => 'Sin arroz', 'group' => 'Sin acompanamiento', 'sort_order' => 9],
            ['name' => 'Sin ensalada', 'group' => 'Sin acompanamiento', 'sort_order' => 10],
            ['name' => 'Sin patacon', 'group' => 'Sin acompanamiento', 'sort_order' => 11],
            ['name' => 'Sin vinagreta', 'group' => 'Sin acompanamiento', 'sort_order' => 12],
            ['name' => 'Mas arroz', 'group' => 'Extras', 'sort_order' => 13],
            ['name' => 'Mas ensalada', 'group' => 'Extras', 'sort_order' => 14],
            ['name' => 'Mas patacon', 'group' => 'Extras', 'sort_order' => 15],
        ];

        $sync = [];

        foreach ($options as $option) {
            $modifier = Modifier::updateOrCreate(
                ['name' => $option['name'], 'type' => 'option'],
                [
                    'group' => $option['group'],
                    'sort_order' => $option['sort_order'],
                    'price' => 0,
                    'extra_quantity' => 0,
                    'active' => true,
                ]
            );

            $sync[$modifier->id] = [
                'id' => (string) Str::uuid(),
                'enabled' => true,
                'sort_order' => $option['sort_order'],
            ];
        }

        $almuerzos = ProductCategory::where('name', 'Almuerzos')->first();

        if ($almuerzos) {
            $almuerzos->modifiers()->syncWithoutDetaching($sync);
        }
    }

    public function down(): void
    {
        $names = [
            'Sancocho',
            'Consome',
            'Frito',
            'Encocado',
            'Criolla',
            'Criolla Directa',
            'Encocado a parte',
            'Sin sopa',
            'Sin arroz',
            'Sin ensalada',
            'Sin patacon',
            'Sin vinagreta',
            'Mas arroz',
            'Mas ensalada',
            'Mas patacon',
        ];

        Modifier::where('type', 'option')->whereIn('name', $names)->delete();
    }
};
