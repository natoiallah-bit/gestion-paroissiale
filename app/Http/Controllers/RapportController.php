<?php

namespace App\Http\Controllers;

use App\Models\Paroisse;
use App\Support\Ressources;
use App\Support\Statistiques;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class RapportController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $donnees = $request->validate([
            'du' => 'nullable|date', 'au' => 'nullable|date|after_or_equal:du',
            'paroisse' => 'nullable|integer', 'annee' => 'nullable|integer|min:1900|max:2200',
        ]);

        $du = Carbon::parse($donnees['du'] ?? today()->startOfYear());
        $au = Carbon::parse($donnees['au'] ?? today());
        $annee = (int) ($donnees['annee'] ?? today()->year);
        $paroisseId = $user->estLimiteAUneParoisse() ? $user->paroisse_id : ($donnees['paroisse'] ?? null);

        return view('rapports.index', [
            'du' => $du, 'au' => $au, 'annee' => $annee,
            'paroisseId' => $paroisseId,
            'paroisses' => $user->estLimiteAUneParoisse() ? collect() : Paroisse::orderBy('nom')->get(),
            'finances' => Ressources::peutLire($user, 'operations') ? Statistiques::bilan($user, $du, $au, $paroisseId) : null,
            'sacrements' => Ressources::peutLire($user, 'sacrements') ? Statistiques::sacrementsParAnnee($user, $annee, $paroisseId) : null,
            'effectifs' => Ressources::peutLire($user, 'fideles') ? Statistiques::tableauDeBord($user, $paroisseId)['resume'] : null,
            'parParoisse' => $user->estAdmin() && ! $paroisseId ? Statistiques::parParoisse($user) : null,
        ]);
    }
}
