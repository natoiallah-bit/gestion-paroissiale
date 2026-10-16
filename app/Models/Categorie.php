<?php

namespace App\Models;

use App\Models\Concerns\Synchronisable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Categorie extends Model
{
    use Synchronisable;

    protected $table = 'categories';

    protected $fillable = [
        'uuid', 'nom'];

    public function produits(): HasMany
    {
        return $this->hasMany(Produit::class);
    }
}
