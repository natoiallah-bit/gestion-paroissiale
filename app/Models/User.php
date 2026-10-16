<?php

namespace App\Models;

use App\Models\Concerns\Synchronisable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, Synchronisable;

    public const ROLES = [
        'gerant' => 'Gérant',
        'vendeur' => 'Vendeur',
    ];

    protected $fillable = ['name', 'email', 'password', 'role', 'actif'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'actif' => 'boolean',
        ];
    }

    public function estGerant(): bool
    {
        return $this->role === 'gerant';
    }

    public function libelleRole(): string
    {
        return self::ROLES[$this->role] ?? $this->role;
    }
}
