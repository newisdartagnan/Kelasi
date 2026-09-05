<div>
    <h1 class="text-2xl font-semibold tracking-tight">Comptes</h1>
    <p class="mt-1 text-sm text-slate-500">
        Suspendre, réactiver, désigner un chef de promotion et remettre un mot de passe.
    </p>

    {{-- Le mot de passe provisoire n'est affiché qu'une fois : il n'est stocké
         nulle part en clair, et cet encart est le seul endroit où le lire. --}}
    @if ($motDePasseProvisoire)
        <div class="mt-6 rounded-xl border-2 border-kelasi-300 bg-kelasi-50 p-5">
            <p class="text-sm font-medium text-kelasi-900">
                Mot de passe provisoire de {{ $beneficiaireProvisoire }}
            </p>
            <p class="mt-2 select-all font-mono text-2xl font-bold tracking-wider text-kelasi-900">
                {{ $motDePasseProvisoire }}
            </p>
            <p class="mt-2 text-sm leading-relaxed text-kelasi-800">
                Remettez-le à l'intéressé maintenant : il n'est enregistré nulle part et
                ne pourra pas être réaffiché. La personne devra en choisir un autre à sa
                prochaine connexion.
            </p>
            <button type="button" wire:click="fermerProvisoire"
                    class="mt-3 rounded-lg bg-kelasi-600 px-4 py-2 text-sm font-medium text-white hover:bg-kelasi-700">
                J'ai noté
            </button>
        </div>
    @endif

    @if ($demandes->isNotEmpty())
        <section class="mt-6">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">
                Demandes de mot de passe ({{ $demandes->count() }})
            </h2>

            <ul class="mt-3 space-y-2">
                @foreach ($demandes as $demande)
                    <li class="rounded-xl border border-amber-200 bg-amber-50/60 p-4">
                        <div class="flex flex-wrap items-baseline justify-between gap-2">
                            <div>
                                <p class="font-medium">{{ $demande->utilisateur->nom_complet }}</p>
                                <p class="text-xs text-slate-600">
                                    {{ $demande->utilisateur->matricule }}
                                    @if ($demande->utilisateur->promotion)
                                        &middot; {{ $demande->utilisateur->promotion->nom_complet }}
                                    @endif
                                    &middot; demandé le {{ $demande->created_at->translatedFormat('d/m/Y') }}
                                </p>
                            </div>
                        </div>

                        @if ($demande->motif)
                            <p class="mt-2 text-sm text-slate-700">{{ $demande->motif }}</p>
                        @endif

                        @if ($demandeVisee === $demande->id)
                            <div class="mt-3">
                                <label for="motifRejet" class="block text-sm font-medium text-slate-700">
                                    Motif du rejet
                                </label>
                                <textarea id="motifRejet" wire:model="motifRejet" rows="2"
                                          class="mt-1 w-full rounded-lg border-slate-300 px-3 py-2 text-sm focus:border-kelasi-500 focus:ring-kelasi-500"></textarea>
                                @error('motifRejet') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror

                                <div class="mt-2 flex gap-2">
                                    <button type="button" wire:click="rejeterReinitialisation"
                                            class="rounded-lg bg-amber-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-amber-700">
                                        Confirmer le rejet
                                    </button>
                                    <button type="button" wire:click="$set('demandeVisee', null)"
                                            class="rounded-lg px-3 py-1.5 text-sm text-slate-600 hover:bg-slate-100">
                                        Annuler
                                    </button>
                                </div>
                            </div>
                        @else
                            <div class="mt-3 flex flex-wrap gap-2">
                                <button type="button" wire:click="approuverReinitialisation({{ $demande->id }})"
                                        class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700">
                                    Approuver et générer
                                </button>
                                <button type="button" wire:click="ouvrirRejet({{ $demande->id }})"
                                        class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                                    Rejeter
                                </button>
                            </div>
                        @endif
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    <div class="mt-8">
        <label for="recherche" class="sr-only">Rechercher un compte</label>
        <input id="recherche" type="search" wire:model.live.debounce.300ms="recherche"
               placeholder="Nom, prénom ou matricule"
               class="w-full rounded-lg border-slate-300 px-3 py-2.5 shadow-sm focus:border-kelasi-500 focus:ring-kelasi-500 sm:max-w-sm">
    </div>

    @if ($comptes->isEmpty())
        <p class="mt-4 rounded-xl border border-dashed border-slate-300 bg-white px-4 py-12 text-center text-sm text-slate-500">
            Aucun compte dans votre périmètre.
        </p>
    @else
        <ul class="mt-4 space-y-2">
            @foreach ($comptes as $compte)
                @php($role = $compte->getRoleNames()->first())
                <li @class([
                    'rounded-xl border bg-white p-4',
                    'border-rose-200 bg-rose-50/40' => $compte->estSuspendu(),
                    'border-slate-200' => ! $compte->estSuspendu(),
                ])>
                    <div class="flex flex-wrap items-baseline justify-between gap-2">
                        <div class="min-w-0">
                            <p class="font-medium">{{ $compte->nom_complet }}</p>
                            <p class="text-xs text-slate-500">
                                {{ $compte->matricule }} &middot;
                                {{ \App\Models\User::ROLES[$role] ?? $role }}
                                @if ($compte->promotion)
                                    &middot; {{ $compte->promotion->nom_complet }}
                                @endif
                            </p>
                        </div>

                        @if ($compte->estSuspendu())
                            <span class="inline-flex shrink-0 items-center rounded-full bg-rose-100 px-2 py-0.5 text-xs font-medium text-rose-700 ring-1 ring-inset ring-rose-200">
                                Suspendu
                            </span>
                        @endif
                    </div>

                    @if ($compte->motif_suspension)
                        <p class="mt-2 rounded-lg bg-rose-100/60 px-3 py-2 text-xs text-rose-900">
                            {{ $compte->motif_suspension }}
                        </p>
                    @endif

                    @if ($compteVise === $compte->id)
                        <div class="mt-3">
                            <label for="motifSuspension" class="block text-sm font-medium text-slate-700">
                                Pourquoi suspendez-vous ce compte ?
                            </label>
                            <textarea id="motifSuspension" wire:model="motifSuspension" rows="2"
                                      class="mt-1 w-full rounded-lg border-slate-300 px-3 py-2 text-sm focus:border-kelasi-500 focus:ring-kelasi-500"></textarea>
                            @error('motifSuspension') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror

                            <div class="mt-2 flex gap-2">
                                <button type="button" wire:click="suspendre"
                                        class="rounded-lg bg-rose-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-rose-700">
                                    Confirmer la suspension
                                </button>
                                <button type="button" wire:click="$set('compteVise', null)"
                                        class="rounded-lg px-3 py-1.5 text-sm text-slate-600 hover:bg-slate-100">
                                    Annuler
                                </button>
                            </div>
                        </div>
                    @else
                        <div class="mt-3 flex flex-wrap gap-2">
                            @if ($compte->estSuspendu())
                                <button type="button" wire:click="reactiver({{ $compte->id }})"
                                        class="rounded-lg bg-emerald-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-emerald-700">
                                    Réactiver
                                </button>
                            @else
                                <button type="button" wire:click="ouvrirSuspension({{ $compte->id }})"
                                        class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50">
                                    Suspendre
                                </button>
                            @endif

                            @if ($compte->promotion_id && in_array($role, ['etudiant', 'cp', 'cpa'], true))
                                @if ($role !== 'cp')
                                    <button type="button" wire:click="designer({{ $compte->id }}, 'cp')"
                                            class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50">
                                        Nommer chef
                                    </button>
                                @endif
                                @if ($role !== 'cpa')
                                    <button type="button" wire:click="designer({{ $compte->id }}, 'cpa')"
                                            class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50">
                                        Nommer adjoint
                                    </button>
                                @endif
                                @if ($role !== 'etudiant')
                                    <button type="button" wire:click="designer({{ $compte->id }}, 'etudiant')"
                                            class="rounded-lg px-3 py-1.5 text-sm text-slate-500 hover:bg-slate-100">
                                        Rendre au rang d'étudiant
                                    </button>
                                @endif
                            @endif
                        </div>
                    @endif
                </li>
            @endforeach
        </ul>

        <div class="mt-6">{{ $comptes->links() }}</div>
    @endif
</div>
