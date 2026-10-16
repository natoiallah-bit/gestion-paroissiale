<?php

namespace App\Models;

use App\Models\Concerns\Synchronisable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Fournisseur extends Model
{
    use Synchronisable;

    protected $fillable = [
        'uuid', 'nom', 'telephone', 'adresse', 'note'];

    public function mouvements(): HasMany
    {
        return $this->hasMany(MouvementStock::class);
    }

    public function depenses(): HasMany
    {
        return $this->hasMany(Depense::class);
    }
}
