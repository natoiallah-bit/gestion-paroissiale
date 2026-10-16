<?php

if (! function_exists('fcfa')) {
    // 12500 → "12 500 F"
    function fcfa(int|float|null $montant): string
    {
        return number_format((int) $montant, 0, ',', ' ').' F';
    }
}
