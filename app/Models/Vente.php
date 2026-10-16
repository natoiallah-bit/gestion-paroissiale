<?php

namespace App\Models;

use App\Models\Concerns\Synchronisable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class Vente extends Model
{
    use Synchronisable;

    public const MODES = [
        'especes' => 'Espèces',
        'mobile_money' => 'Mobile money',
    ];

    protected $fillable = [
        'uuid',
        'numero', 'client_id', 'user_id', 'total', 'montant_paye', 'mode_paiement',
        'statut', 'annulee_par', 'annulee_le', 'motif_annulation', 'appareil_id',
    ];

    protected function casts(): array
    {
        return [
            'total' => 'integer',
            'montant_paye' => 'integer',
            'annulee_le' => 'datetime',
        ];
    }

    public function lignes(): HasMany
    {
        return $this->hasMany(VenteLigne::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function annuleePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'annulee_par');
    }

    public function scopeValidees(Builder $query): Builder
    {
        return $query->where('statut', 'validee');
    }

    public function reste(): int
    {
        return $this->total - $this->montant_paye;
    }

    public function estAnnulee(): bool
    {
        return $this->statut === 'annulee';
    }

    /**
     * Enregistre une vente : prix lus en base (jamais depuis le navigateur),
     * stock vérifié et décrémenté sous verrou, le tout dans une transaction.
     *
     * @param  array<int, array{produit_id:int, quantite:int}>  $lignes
     */
    public static function enregistrer(array $lignes, int $montantPaye, string $mode, ?int $clientId, int $userId): self
    {
        return DB::transaction(function () use ($lignes, $montantPaye, $mode, $clientId, $userId) {
            // Regroupe les lignes d'un même produit
            $quantites = [];
            foreach ($lignes as $ligne) {
                $id = (int) $ligne['produit_id'];
                $quantites[$id] = ($quantites[$id] ?? 0) + (int) $ligne['quantite'];
            }

            $produits = Produit::whereIn('id', array_keys($quantites))->lockForUpdate()->get()->keyBy('id');

            $erreurs = [];
            $total = 0;
            foreach ($quantites as $id => $quantite) {
                $produit = $produits->get($id);
                if (! $produit || ! $produit->actif) {
                    $erreurs[] = 'Un produit du panier n\'est plus disponible.';
                } elseif ($produit->stock < $quantite) {
                    $erreurs[] = "Stock insuffisant pour « {$produit->nom} » (reste {$produit->stock}).";
                } else {
                    $total += $produit->prix_vente * $quantite;
                }
            }
            if ($erreurs) {
                throw ValidationException::withMessages(['lignes' => $erreurs]);
            }

            if ($montantPaye > $total) {
                $montantPaye = $total; // la monnaie rendue n'est pas un encaissement
            }
            if ($montantPaye < $total && ! $clientId) {
                throw ValidationException::withMessages([
                    'client_id' => 'Pour une vente à crédit (paiement incomplet), choisissez le client.',
                ]);
            }

            $vente = self::create([
                'numero' => 'TMP-'.uniqid(),
                'client_id' => $clientId,
                'user_id' => $userId,
                'total' => $total,
                'montant_paye' => $montantPaye,
                'mode_paiement' => $mode,
            ]);
            $vente->update(['numero' => 'V'.str_pad((string) $vente->id, 6, '0', STR_PAD_LEFT)]);

            foreach ($quantites as $id => $quantite) {
                $produit = $produits->get($id);
                $vente->lignes()->create([
                    'produit_id' => $produit->id,
                    'designation' => $produit->nom,
                    'quantite' => $quantite,
                    'prix_unitaire' => $produit->prix_vente,
                    'prix_achat_unitaire' => $produit->prix_achat,
                    'total' => $produit->prix_vente * $quantite,
                ]);
                $produit->mouvementer(-$quantite, 'vente', $userId, ['vente_id' => $vente->id]);
            }

            return $vente;
        });
    }

    /**
     * Vente faite sur un appareil, peut-être hors ligne, reçue lors d'une synchronisation.
     *
     * Contrairement à la caisse en ligne, la vente a déjà eu lieu : on l'enregistre avec
     * les prix affichés sur l'appareil, même si le stock du serveur passe en négatif.
     * Renvoyer la même vente (même uuid) ne la compte pas deux fois.
     */
    public static function importer(array $donnees, Appareil $appareil, User $user): self
    {
        if ($existante = self::parUuid($donnees['uuid'])) {
            return $existante;
        }

        return DB::transaction(function () use ($donnees, $appareil, $user) {
            $produits = Produit::whereIn('uuid', array_column($donnees['lignes'], 'produit_uuid'))
                ->lockForUpdate()->get()->keyBy('uuid');

            $total = 0;
            foreach ($donnees['lignes'] as $ligne) {
                if (! $produits->has($ligne['produit_uuid'])) {
                    throw ValidationException::withMessages(['lignes' => 'Produit inconnu du serveur.']);
                }
                $total += (int) $ligne['quantite'] * (int) $ligne['prix_unitaire'];
            }

            $date = isset($donnees['created_at']) ? Carbon::parse($donnees['created_at'])->setTimezone(config('app.timezone')) : now();
            $numero = $donnees['numero'] ?? $appareil->code.'-'.substr($donnees['uuid'], 0, 8);
            if (self::where('numero', $numero)->exists()) {
                $numero .= '-'.substr($donnees['uuid'], 0, 4);
            }

            $vente = new self([
                'uuid' => $donnees['uuid'],
                'numero' => $numero,
                'client_id' => Client::parUuid($donnees['client_uuid'] ?? null)?->id,
                'user_id' => $user->id,
                'appareil_id' => $appareil->id,
                'total' => $total,
                'montant_paye' => min(max((int) $donnees['montant_paye'], 0), $total),
                'mode_paiement' => array_key_exists($donnees['mode_paiement'] ?? '', self::MODES) ? $donnees['mode_paiement'] : 'especes',
            ]);
            $vente->created_at = $date->isFuture() ? now() : $date;
            $vente->save();

            foreach ($donnees['lignes'] as $ligne) {
                $produit = $produits[$ligne['produit_uuid']];
                $vente->lignes()->create([
                    'uuid' => $ligne['uuid'] ?? null,
                    'produit_id' => $produit->id,
                    'designation' => $ligne['designation'] ?? $produit->nom,
                    'quantite' => (int) $ligne['quantite'],
                    'prix_unitaire' => (int) $ligne['prix_unitaire'],
                    'prix_achat_unitaire' => $produit->prix_achat,
                    'total' => (int) $ligne['quantite'] * (int) $ligne['prix_unitaire'],
                ]);
                $produit->mouvementer(-(int) $ligne['quantite'], 'vente', $user->id, ['vente_id' => $vente->id]);
            }

            return $vente;
        });
    }

    /**
     * Annule la vente et remet les articles en stock. La dette du client disparaît
     * d'elle-même puisqu'elle ne compte que les ventes validées.
     */
    public function annuler(int $userId, ?string $motif): void
    {
        DB::transaction(function () use ($userId, $motif) {
            $vente = self::whereKey($this->id)->lockForUpdate()->firstOrFail();
            if ($vente->estAnnulee()) {
                return;
            }

            $ids = $vente->lignes()->pluck('produit_id');
            $produits = Produit::whereIn('id', $ids)->lockForUpdate()->get()->keyBy('id');
            foreach ($vente->lignes as $ligne) {
                $produits[$ligne->produit_id]->mouvementer($ligne->quantite, 'annulation', $userId, ['vente_id' => $vente->id]);
            }

            $vente->update([
                'statut' => 'annulee',
                'annulee_par' => $userId,
                'annulee_le' => now(),
                'motif_annulation' => $motif,
            ]);
            $this->setRawAttributes($vente->getAttributes(), true);
        });
    }
}
