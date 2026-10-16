<?php

namespace App\Models;

use App\Models\Concerns\Synchronisable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Produit extends Model
{
    use HasFactory, Synchronisable;

    protected $fillable = [
        'uuid',
        'nom', 'code', 'categorie_id', 'unite', 'prix_achat', 'prix_vente', 'seuil_alerte', 'actif',
    ];

    protected function casts(): array
    {
        return [
            'actif' => 'boolean',
            'prix_achat' => 'integer',
            'prix_vente' => 'integer',
            'stock' => 'integer',
            'seuil_alerte' => 'integer',
        ];
    }

    public function categorie(): BelongsTo
    {
        return $this->belongsTo(Categorie::class);
    }

    public function mouvements(): HasMany
    {
        return $this->hasMany(MouvementStock::class);
    }

    public function scopeStockBas(Builder $query): Builder
    {
        return $query->where('actif', true)->whereColumn('stock', '<=', 'seuil_alerte');
    }

    public function estEnAlerte(): bool
    {
        return $this->stock <= $this->seuil_alerte;
    }

    /**
     * Seule façon de modifier le stock : chaque changement laisse une trace.
     * À appeler dans une transaction, sur un produit verrouillé (lockForUpdate).
     */
    public function mouvementer(int $quantite, string $type, int $userId, array $details = []): MouvementStock
    {
        $this->stock += $quantite;
        $this->save();

        return $this->mouvements()->create([
            'type' => $type,
            'quantite' => $quantite,
            'stock_apres' => $this->stock,
            'user_id' => $userId,
        ] + $details);
    }
}
