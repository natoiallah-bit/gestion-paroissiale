<?php

namespace App\Models;

use App\Models\Concerns\Synchronisable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Depense extends Model
{
    use Synchronisable;

    public const ACHAT_MARCHANDISE = 'Achat marchandise';

    public const CATEGORIES = [
        'Loyer', 'Électricité / eau', 'Transport', 'Salaires', 'Achat marchandise', 'Taxes', 'Téléphone / internet', 'Autre',
    ];

    protected $fillable = [
        'uuid', 'libelle', 'categorie', 'montant', 'date_depense', 'fournisseur_id', 'user_id'];

    protected function casts(): array
    {
        return ['date_depense' => 'date', 'montant' => 'integer'];
    }

    public function fournisseur(): BelongsTo
    {
        return $this->belongsTo(Fournisseur::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
