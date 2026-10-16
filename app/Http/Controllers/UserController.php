<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    public function index()
    {
        return view('users.index', ['users' => User::orderBy('name')->get()]);
    }

    public function create()
    {
        return view('users.form', ['user' => new User(['role' => 'vendeur', 'actif' => true])]);
    }

    public function store(Request $request)
    {
        $donnees = $this->valider($request);
        User::create($donnees);

        return redirect()->route('users.index')->with('succes', 'Compte créé.');
    }

    public function edit(User $user)
    {
        return view('users.form', compact('user'));
    }

    public function update(Request $request, User $user)
    {
        $donnees = $this->valider($request, $user);
        if (empty($donnees['password'])) {
            unset($donnees['password']);
        }

        // Le gérant ne peut pas se retirer lui-même ses droits ni se bloquer
        if ($user->is($request->user()) && ($donnees['role'] !== 'gerant' || ! $donnees['actif'])) {
            return back()->withErrors(['role' => 'Vous ne pouvez pas retirer votre propre accès gérant.'])->withInput();
        }

        $user->update($donnees);

        return redirect()->route('users.index')->with('succes', 'Compte modifié.');
    }

    private function valider(Request $request, ?User $user = null): array
    {
        $donnees = $request->validate([
            'name' => 'required|string|max:120',
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($user?->id)],
            'role' => ['required', Rule::in(array_keys(User::ROLES))],
            'password' => [$user ? 'nullable' : 'required', 'confirmed', Password::min(8)],
        ]);
        $donnees['actif'] = $request->boolean('actif');

        return $donnees;
    }
}
