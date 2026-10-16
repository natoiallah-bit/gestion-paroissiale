<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Vente;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ClientController extends Controller
{
    public function index(Request $request)
    {
        $requete = Client::query()->select('clients.*')->avecDette()->orderBy('nom');
        if ($request->filled('q')) {
            $q = $request->string('q');
            $requete->where(fn ($w) => $w->where('nom', 'like', "%{$q}%")->orWhere('telephone', 'like', "%{$q}%"));
        }

        $clients = $requete->get();
        if ($request->boolean('debiteurs')) {
            $clients = $clients->where('dette', '>', 0);
        }

        return view('clients.index', [
            'clients' => $clients,
            'totalDettes' => $clients->where('dette', '>', 0)->sum('dette'),
        ]);
    }

    public function create()
    {
        return view('clients.form', ['client' => new Client]);
    }

    public function store(Request $request)
    {
        $client = Client::create($this->valider($request));

        return redirect()->route('clients.show', $client)->with('succes', 'Client ajouté.');
    }

    public function show(Client $client)
    {
        $ventes = $client->ventes()->with('user')->latest()->get();
        $remboursements = $client->remboursements()->with('user')->latest()->get();

        // Historique unique, du plus récent au plus ancien
        $historique = $ventes->map(fn ($v) => ['date' => $v->created_at, 'type' => 'vente', 'objet' => $v])
            ->concat($remboursements->map(fn ($r) => ['date' => $r->created_at, 'type' => 'remboursement', 'objet' => $r]))
            ->sortByDesc('date')->values();

        return view('clients.show', [
            'client' => $client,
            'dette' => $client->dette(),
            'historique' => $historique,
        ]);
    }

    public function edit(Client $client)
    {
        return view('clients.form', compact('client'));
    }

    public function update(Request $request, Client $client)
    {
        $client->update($this->valider($request));

        return redirect()->route('clients.show', $client)->with('succes', 'Client modifié.');
    }

    public function destroy(Client $client)
    {
        if ($client->dette() !== 0 || $client->ventes()->exists()) {
            return back()->withErrors(['client' => 'Ce client a un historique de ventes : il ne peut pas être supprimé.']);
        }
        $client->delete();

        return redirect()->route('clients.index')->with('succes', 'Client supprimé.');
    }

    public function rembourser(Request $request, Client $client)
    {
        $dette = $client->dette();
        $donnees = $request->validate([
            'montant' => ['required', 'integer', 'min:1', 'max:'.max($dette, 1)],
            'mode_paiement' => ['required', Rule::in(array_keys(Vente::MODES))],
            'note' => 'nullable|string|max:255',
        ], [
            'montant.max' => 'Le montant dépasse la dette du client ('.fcfa($dette).').',
        ]);

        if ($dette <= 0) {
            return back()->withErrors(['montant' => 'Ce client n\'a pas de dette.']);
        }

        $client->remboursements()->create($donnees + ['user_id' => $request->user()->id]);

        return redirect()->route('clients.show', $client)->with('succes', 'Remboursement de '.fcfa($donnees['montant']).' enregistré.');
    }

    private function valider(Request $request): array
    {
        return $request->validate([
            'nom' => 'required|string|max:150',
            'telephone' => 'nullable|string|max:30',
            'adresse' => 'nullable|string|max:255',
        ]);
    }
}
