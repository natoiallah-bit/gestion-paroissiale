<?php

namespace App\Models;

use App\Models\Concerns\Synchronisable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MouvementStock extends Model
{
    use Synchronisable;

    protected $table = 'mouvements_stock';

    public const TYPES = [
        'entree' => 'Entrée de marchandise',
        'ajustement' => 'Ajustement (inventaire, perte…)',
        'vente' => 'Vente',
        'annulation' => 'Annulation de vente',
    ];

    protected $fillable = [
        'uuid',
        'produit_id', 'type', 'quantite', 'stock_apres', 'prix_achat_unitaire',
        'fournisseur_id', 'vente_id', 'user_id', 'note',
    ];

    public function produit(): BelongsTo
    {
        return $this->belongsTo(Produit::class);
    }

    public function fournisseur(): BelongsTo
    {
        return $this->belongsTo(Fournisseur::class);
    }

    public function vente(): BelongsTo
    {
        return $this->belongsTo(Vente::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function libelleType(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }
}
