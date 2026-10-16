<?php

namespace App\Models;

use App\Models\Concerns\Synchronisable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VenteLigne extends Model
{
    use Synchronisable;

    public $timestamps = false;

    protected $fillable = [
        'uuid',
        'vente_id', 'produit_id', 'designation', 'quantite', 'prix_unitaire', 'prix_achat_unitaire', 'total',
    ];

    public function vente(): BelongsTo
    {
        return $this->belongsTo(Vente::class);
    }

    public function produit(): BelongsTo
    {
        return $this->belongsTo(Produit::class);
    }
}
