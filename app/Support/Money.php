<?php

namespace App\Support;

use Brick\Math\BigDecimal;
use Illuminate\Validation\ValidationException;

class Money
{
    public static function lineTotal(string $unitPrice, int $quantity, string $discount = '0'): string
    {
        $gross = BigDecimal::of($unitPrice)->multipliedBy($quantity);
        $reduction = BigDecimal::of($discount);
        if ($reduction->isNegative() || $reduction->isGreaterThan($gross)) {
            throw ValidationException::withMessages(['discount' => 'El descuento debe estar entre cero y el importe del plato.']);
        }
        $net = $gross->minus($reduction)->toScale(2);
        if ($net->isNegative() || $net->isGreaterThan('9999999999.99')) {
            throw ValidationException::withMessages(['quantity' => 'El importe supera el límite admitido. Revisa precio y cantidad.']);
        }

        return (string) $net;
    }
}
