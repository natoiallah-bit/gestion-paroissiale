<?php

namespace App\Models;

use App\Models\Concerns\Synchronisable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Remboursement extends Model
{
    use Synchronisable;

    protected $fillable = [
        'uuid', 'client_id', 'user_id', 'montant', 'mode_paiement', 'note'];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
