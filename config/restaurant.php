<?php

return [
    'currency' => 'COP',
    'currency_symbol' => '$',

    'points' => [
        'el_muelle'  => 'El Muelle',
        'bocagrande' => 'Bocagrande',
        'oficinas'   => 'Oficinas',
        'bodega'     => 'Bodega',
    ],

    'families' => [
        'proteina'           => 'Proteína',
        'granos_abarrotes'   => 'Granos y Abarrotes',
        'frutas'             => 'Frutas',
        'verduras'           => 'Verduras',
        'lacteos_huevos'     => 'Lácteos y Huevos',
        'pulpas'             => 'Pulpas',
        'hierbas'            => 'Hierbas',
        'helados'            => 'Helados',
        'bebidas_embotelladas' => 'Bebidas Embotelladas',
    ],

    'departments' => [
        1 => 'MATERIA PRIMA',
        2 => 'PRODUCTOS TERMINADOS',
        3 => 'ASEO',
        4 => 'CONSUMIBLES',
        5 => 'ELEMENTOS COCINA Y SERVICIO',
        6 => 'HERRAMIENTAS',
        7 => 'EQUIPOS',
        8 => 'MUEBLES Y ENCERES',
    ],

    /** Roles que el administrador puede crear y editar */
    'staff_roles' => [
        'waiter'  => 'Mesero',
        'cashier' => 'Cajero',
        'cook'    => 'Cocinero',
    ],

    'purchase_adjustment_types' => [
        'compra'      => 'Compra',
        'bonificacion'=> 'Bonificación',
        'inventario'  => 'Inventario',
        'reposicion'  => 'Reposición',
    ],
];
