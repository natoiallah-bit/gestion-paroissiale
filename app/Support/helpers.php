<?php

use App\Support\Ressources;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

if (! function_exists('fcfa')) {
    // 12500 → "12 500 F"
    function fcfa(int|float|null $montant): string
    {
        return number_format((int) $montant, 0, ',', ' ').' F';
    }
}

if (! function_exists('champ_valeur')) {
    /** Valeur d'un champ d'une fiche, prête à afficher */
    function champ_valeur($fiche, array $champ, bool $court = true): string
    {
        $v = $fiche->{$champ['type'] === 'ref' ? Ressources::relation($champ) : $champ['nom']};
        if ($v === null || $v === '') {
            return '—';
        }

        return match ($champ['type']) {
            'ref' => $v->intitule(),
            'choix' => $champ['options'][$v] ?? (string) $v,
            'date' => Carbon::parse($v)->format('d/m/Y'),
            'montant' => fcfa($v),
            'long' => $court ? Str::limit((string) $v, 80) : (string) $v,
            default => (string) $v,
        };
    }
}
