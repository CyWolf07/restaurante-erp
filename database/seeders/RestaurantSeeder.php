<?php

namespace Database\Seeders;

use App\Models\Modifier;
use App\Models\Printer;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Recipe;
use App\Models\RestaurantTable;
use App\Models\Supply;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class RestaurantSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            ['name' => 'Dev Programador', 'email' => 'dev@local.test', 'pin_code' => '0000', 'role' => 'programmer', 'password' => 'password'],
            ['name' => 'Admin Principal', 'email' => 'admin@local.test', 'pin_code' => '1111', 'role' => 'administrator', 'password' => 'password'],
            ['name' => 'Cajero Turno', 'email' => 'cajero@local.test', 'pin_code' => '2222', 'role' => 'cashier', 'password' => 'password'],
            ['name' => 'Mesero Juan', 'email' => 'mesero@local.test', 'pin_code' => '3333', 'role' => 'waiter', 'password' => 'password'],
            ['name' => 'Chef María', 'email' => 'cocina@local.test', 'pin_code' => '4444', 'role' => 'cook', 'password' => 'password'],
        ];

        foreach ($users as $u) {
            User::updateOrCreate(
                ['email' => $u['email']],
                [
                    'name' => $u['name'],
                    'pin_code' => $u['pin_code'],
                    'role' => $u['role'],
                    'active' => true,
                    'password' => Hash::make($u['password']),
                ]
            );
        }

        $entradas = ProductCategory::updateOrCreate(
            ['name' => 'Entradas'],
            ['color' => '#22c55e', 'sort_order' => 1]
        );
        $principales = ProductCategory::updateOrCreate(
            ['name' => 'Platos Fuertes'],
            ['color' => '#6366f1', 'sort_order' => 2]
        );
        $bebidas = ProductCategory::updateOrCreate(
            ['name' => 'Bebidas'],
            ['color' => '#f59e0b', 'sort_order' => 3]
        );

        $carne = Supply::updateOrCreate(['name' => 'Carne de res'], [
            'unit_type' => 'gram', 'current_stock' => 50000, 'min_stock' => 5000,
            'cost_per_unit' => 0.08, 'supplier' => 'Carnicería Local',
        ]);
        $pan = Supply::updateOrCreate(['name' => 'Pan brioche'], [
            'unit_type' => 'unit', 'current_stock' => 200, 'min_stock' => 30,
            'cost_per_unit' => 3.50, 'supplier' => 'Panadería',
        ]);
        $queso = Supply::updateOrCreate(['name' => 'Queso cheddar'], [
            'unit_type' => 'gram', 'current_stock' => 10000, 'min_stock' => 1000,
            'cost_per_unit' => 0.05,
        ]);
        $lechuga = Supply::updateOrCreate(['name' => 'Lechuga'], [
            'unit_type' => 'gram', 'current_stock' => 8000, 'min_stock' => 800,
            'cost_per_unit' => 0.02,
        ]);
        $refresco = Supply::updateOrCreate(['name' => 'Jarabe cola'], [
            'unit_type' => 'milliliter', 'current_stock' => 20000, 'min_stock' => 2000,
            'cost_per_unit' => 0.01,
        ]);

        $burger = Product::updateOrCreate(['name' => 'Hamburguesa Clásica'], [
            'category_id' => $principales->id,
            'description' => 'Carne 180g, queso, lechuga y pan brioche',
            'price' => 149.00,
            'recipe_instructions' => "1. Sazonar y cocinar la carne a término medio.\n2. Tostar el pan.\n3. Armar con queso, lechuga y servir.",
            'preparation_time' => 15,
            'active' => true,
            'sort_order' => 1,
        ]);

        $cola = Product::updateOrCreate(['name' => 'Refresco Cola'], [
            'category_id' => $bebidas->id,
            'description' => 'Vaso 500ml',
            'price' => 35.00,
            'recipe_instructions' => 'Servir con hielo.',
            'preparation_time' => 2,
            'active' => true,
            'sort_order' => 1,
        ]);

        $nachos = Product::updateOrCreate(['name' => 'Nachos con Queso'], [
            'category_id' => $entradas->id,
            'description' => 'Totopos con queso fundido',
            'price' => 89.00,
            'recipe_instructions' => 'Fundir queso y servir caliente.',
            'preparation_time' => 8,
            'active' => true,
            'sort_order' => 1,
        ]);

        $this->seedRecipe($burger, $carne, 180);
        $this->seedRecipe($burger, $pan, 1);
        $this->seedRecipe($burger, $queso, 40);
        $this->seedRecipe($burger, $lechuga, 30);
        $this->seedRecipe($cola, $refresco, 500);
        $this->seedRecipe($nachos, $queso, 80);

        Modifier::updateOrCreate(['name' => 'Extra queso'], [
            'price' => 15.00,
            'supply_id' => $queso->id,
            'extra_quantity' => 30,
            'active' => true,
        ]);

        Modifier::updateOrCreate(['name' => 'Sin cebolla'], [
            'price' => 0,
            'supply_id' => null,
            'extra_quantity' => 0,
            'active' => true,
        ]);

        $kitchenPrinter = Printer::updateOrCreate(['name' => 'Cocina Demo'], [
            'purpose' => 'kitchen',
            'connection_type' => 'network',
            'address' => '192.168.1.100',
            'port' => 9100,
            'paper_width' => 80,
            'is_default' => true,
            'active' => true,
        ]);

        Printer::updateOrCreate(['name' => 'Caja Demo'], [
            'purpose' => 'receipt',
            'connection_type' => 'windows',
            'address' => 'Microsoft Print to PDF',
            'port' => 9100,
            'paper_width' => 80,
            'is_default' => true,
            'active' => true,
        ]);

        $tableCount = (int) config('app.restaurant_tables', 12);
        for ($n = 1; $n <= $tableCount; $n++) {
            RestaurantTable::updateOrCreate(
                ['number' => $n],
                [
                    'name' => null,
                    'zone' => $n <= 8 ? 'Salón' : 'Terraza',
                    'capacity' => 4,
                    'kitchen_printer_id' => $kitchenPrinter->id,
                    'sort_order' => $n,
                    'active' => true,
                ]
            );
        }
    }

    private function seedRecipe(Product $product, Supply $supply, float $qty): void
    {
        Recipe::updateOrCreate(
            ['product_id' => $product->id, 'supply_id' => $supply->id],
            ['quantity_required' => $qty]
        );
    }
}
