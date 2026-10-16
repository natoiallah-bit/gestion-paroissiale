<?php

namespace App\Models;

use App\Models\Concerns\Synchronisable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, Synchronisable;

    public const ROLES = [
        'admin' => 'Administrateur (vicariat)',
        'secretaire' => 'Secrétaire paroissial',
        'tresorier' => 'Trésorier',
    ];

    protected $fillable = ['name', 'email', 'password', 'role', 'actif', 'paroisse_id'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'actif' => 'boolean',
        ];
    }

    public function paroisse(): BelongsTo
    {
        return $this->belongsTo(Paroisse::class);
    }

    public function estAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /** Un compte rattaché à une paroisse ne voit et ne modifie que celle-ci. */
    public function estLimiteAUneParoisse(): bool
    {
        return $this->paroisse_id !== null && ! $this->estAdmin();
    }

    public function libelleRole(): string
    {
        return (self::ROLES[$this->role] ?? $this->role).($this->paroisse ? ' — '.$this->paroisse->nom : '');
    }
}
