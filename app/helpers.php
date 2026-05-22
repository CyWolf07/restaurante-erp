<?php

if (! function_exists('cop')) {
    /** Formato moneda pesos colombianos (COP). */
    function cop(float|int|string|null $amount, bool $withSymbol = true): string
    {
        $value = (float) ($amount ?? 0);
        $formatted = number_format($value, 0, ',', '.');

        return $withSymbol ? '$ ' . $formatted : $formatted;
    }
}

if (! function_exists('cop_decimal')) {
    function cop_decimal(float|int|string|null $amount): string
    {
        $value = (float) ($amount ?? 0);
        return '$ ' . number_format($value, 2, ',', '.');
    }
}
