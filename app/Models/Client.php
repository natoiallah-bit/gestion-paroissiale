<?php

namespace App\Models;

use App\Models\Concerns\Synchronisable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Client extends Model
{
    use Synchronisable;

    protected $fillable = [
        'uuid', 'nom', 'telephone', 'adresse'];

    public function ventes(): HasMany
    {
        return $this->hasMany(Vente::class);
    }

    public function remboursements(): HasMany
    {
        return $this->hasMany(Remboursement::class);
    }

    /**
     * Ajoute la colonne "dette" : reste à payer des ventes validées moins les remboursements.
     */
    public function scopeAvecDette(Builder $query): Builder
    {
        return $query->addSelect([
            'dette' => fn ($q) => $q->selectRaw(
                '(SELECT COALESCE(SUM(total - montant_paye), 0) FROM ventes'
                .' WHERE ventes.client_id = clients.id AND ventes.statut = ?)'
                .' - (SELECT COALESCE(SUM(montant), 0) FROM remboursements WHERE remboursements.client_id = clients.id)',
                ['validee']
            ),
        ]);
    }

    public function dette(): int
    {
        $credit = (int) $this->ventes()->where('statut', 'validee')->sum(DB::raw('total - montant_paye'));

        return $credit - (int) $this->remboursements()->sum('montant');
    }
}
