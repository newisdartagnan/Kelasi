<div class="mx-auto max-w-3xl">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">
                {{ $arbitre ? 'Demandes à arbitrer' : 'Mes demandes de modification' }}
            </h1>
            <p class="mt-1 text-sm text-slate-500">
                @if ($arbitre)
                    Approuver applique la modification au programme, immédiatement.
                @else
                    Le programme est arrêté par l'autorité académique : toute modification passe par le vice-recteur.
                @endif
            </p>
        </div>

        @if ($peutDemander && ! $formulaireOuvert)
            <button
                type="button"
                wire:click="ouvrirFormulaire"
                class="rounded-lg bg-kelasi-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-kelasi-700"
            >
                Déposer une demande
            </button>
        @endif
    </div>

    @if ($formulaireOuvert)
        <form wire:submit="deposer" class="mt-6 space-y-4 rounded-xl border border-slate-200 bg-white p-5">
            <div>
                <label for="coursId" class="block text-sm font-medium text-slate-700">Cours concerné</label>
                <select
                    id="coursId"
                    wire:model.live="coursId"
                    class="mt-1 w-full rounded-lg border-slate-300 px-3 py-2.5 shadow-sm focus:border-kelasi-500 focus:ring-kelasi-500"
                >
                    @foreach ($cours as $unCours)
                        <option value="{{ $unCours->id }}">
                            {{ $unCours->code }} — {{ $unCours->intitule }}
                            ({{ $unCours->uniteEnseignement->promotion->niveau }})
                        </option>
                    @endforeach
                </select>
                @error('coursId') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="type" class="block text-sm font-medium text-slate-700">Nature de la demande</label>
                <select
                    id="type"
                    wire:model.live="type"
                    class="mt-1 w-full rounded-lg border-slate-300 px-3 py-2.5 shadow-sm focus:border-kelasi-500 focus:ring-kelasi-500"
                >
                    @foreach ($types as $valeur => $libelle)
                        <option value="{{ $valeur }}">{{ $libelle }}</option>
                    @endforeach
                </select>
            </div>

            @if ($type === 'intitule')
                <div>
                    <label for="nouvelIntitule" class="block text-sm font-medium text-slate-700">Nouvel intitulé</label>
                    <input
                        id="nouvelIntitule"
                        type="text"
                        wire:model="modifications.intitule"
                        class="mt-1 w-full rounded-lg border-slate-300 px-3 py-2.5 shadow-sm focus:border-kelasi-500 focus:ring-kelasi-500"
                    >
                </div>
            @elseif (in_array($type, ['volume', 'repartition'], true))
                <div>
                    <p class="text-sm font-medium text-slate-700">Volumes proposés</p>
                    <div class="mt-2 grid grid-cols-2 gap-3 sm:grid-cols-4">
                        @if ($type === 'volume')
                            <label class="block text-xs text-slate-600">
                                Crédits
                                <input type="number" min="0" wire:model="modifications.credits"
                                       class="mt-1 w-full rounded-lg border-slate-300 px-2 py-2 text-sm shadow-sm focus:border-kelasi-500 focus:ring-kelasi-500">
                            </label>
                        @endif
                        @foreach (['heures_cmi' => 'CMI', 'heures_td' => 'TD', 'heures_tp' => 'TP'] as $champ => $libelle)
                            <label class="block text-xs text-slate-600">
                                {{ $libelle }} (h)
                                <input type="number" min="0" wire:model="modifications.{{ $champ }}"
                                       class="mt-1 w-full rounded-lg border-slate-300 px-2 py-2 text-sm shadow-sm focus:border-kelasi-500 focus:ring-kelasi-500">
                            </label>
                        @endforeach
                    </div>
                    @if ($type === 'repartition')
                        <p class="mt-2 text-xs text-slate-500">
                            Une redistribution conserve le volume total : elle déplace des heures entre
                            cours magistral, travaux dirigés et travaux pratiques.
                        </p>
                    @endif
                </div>
            @endif

            <div>
                <label for="description" class="block text-sm font-medium text-slate-700">Ce que vous demandez</label>
                <textarea id="description" wire:model="description" rows="2"
                          class="mt-1 w-full rounded-lg border-slate-300 px-3 py-2.5 shadow-sm focus:border-kelasi-500 focus:ring-kelasi-500"></textarea>
                @error('description') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="justification" class="block text-sm font-medium text-slate-700">Pourquoi</label>
                <textarea id="justification" wire:model="justification" rows="3"
                          placeholder="Le volume prévu ne permet pas de couvrir le chapitre sur..."
                          class="mt-1 w-full rounded-lg border-slate-300 px-3 py-2.5 shadow-sm focus:border-kelasi-500 focus:ring-kelasi-500"></textarea>
                @error('justification') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
            </div>

            <div class="flex gap-2">
                <button type="submit" class="rounded-lg bg-kelasi-600 px-4 py-2 text-sm font-medium text-white hover:bg-kelasi-700">
                    Déposer
                </button>
                <button type="button" wire:click="fermerFormulaire" class="rounded-lg px-4 py-2 text-sm text-slate-600 hover:bg-slate-100">
                    Annuler
                </button>
            </div>
        </form>
    @endif

    @if ($demandes->isEmpty())
        <p class="mt-6 rounded-xl border border-dashed border-slate-300 bg-white px-4 py-12 text-center text-sm text-slate-500">
            Aucune demande pour l'instant.
        </p>
    @else
        <ul class="mt-6 space-y-3">
            @foreach ($demandes as $demande)
                <li class="rounded-xl border border-slate-200 bg-white p-4">
                    <div class="flex flex-wrap items-baseline justify-between gap-2">
                        <div class="min-w-0">
                            <p class="font-medium">{{ $demande->cours->intitule }}</p>
                            <p class="text-xs text-slate-500">
                                {{ $types[$demande->type] ?? $demande->type }} &middot;
                                {{ $demande->cours->uniteEnseignement->promotion->nom_complet }}
                            </p>
                        </div>

                        <x-etat-demande :statut="$demande->statut" />
                    </div>

                    <p class="mt-3 text-sm text-slate-700">{{ $demande->description }}</p>
                    <p class="mt-2 rounded-lg bg-slate-50 px-3 py-2.5 text-sm leading-relaxed text-slate-600">
                        {{ $demande->justification }}
                    </p>

                    @if ($demande->modifications)
                        <x-valeurs-demandees :demande="$demande" />
                    @endif

                    <p class="mt-2 text-xs text-slate-500">
                        Déposée par {{ $demande->demandeur->nom_complet }}
                        le {{ $demande->created_at->translatedFormat('d/m/Y') }}
                        @if ($demande->decideur)
                            &middot; tranchée par {{ $demande->decideur->nom_complet }}
                            le {{ $demande->decidee_at?->translatedFormat('d/m/Y') }}
                        @endif
                    </p>

                    @if ($demande->motif_decision)
                        <p class="mt-2 rounded-lg bg-slate-100 px-3 py-2 text-xs text-slate-700">
                            Motif : {{ $demande->motif_decision }}
                        </p>
                    @endif

                    @if ($demande->statut === \App\Models\DemandeModification::STATUT_EN_ATTENTE)
                        @if ($arbitre && $demande->demandeur_id !== auth()->id())
                            @if ($demandeArbitree === $demande->id)
                                <div class="mt-3 rounded-lg border border-amber-200 bg-amber-50 p-3">
                                    <label for="motifDecision" class="block text-sm font-medium text-amber-900">
                                        Motif du rejet
                                    </label>
                                    <textarea id="motifDecision" wire:model="motifDecision" rows="2"
                                              class="mt-1 w-full rounded-lg border-amber-300 px-3 py-2 text-sm focus:border-amber-500 focus:ring-amber-500"></textarea>
                                    @error('motifDecision') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror

                                    <div class="mt-2 flex gap-2">
                                        <button type="button" wire:click="rejeter"
                                                class="rounded-lg bg-amber-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-amber-700">
                                            Confirmer le rejet
                                        </button>
                                        <button type="button" wire:click="$set('demandeArbitree', null)"
                                                class="rounded-lg px-3 py-1.5 text-sm text-slate-600 hover:bg-slate-100">
                                            Annuler
                                        </button>
                                    </div>
                                </div>
                            @else
                                <div class="mt-3 flex gap-2">
                                    <button type="button" wire:click="approuver({{ $demande->id }})"
                                            class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-emerald-700">
                                        Approuver et appliquer
                                    </button>
                                    <button type="button" wire:click="ouvrirRejet({{ $demande->id }})"
                                            class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
                                        Rejeter
                                    </button>
                                </div>
                            @endif
                        @elseif ($demande->demandeur_id === auth()->id())
                            <button type="button" wire:click="retirer({{ $demande->id }})"
                                    class="mt-3 text-sm font-medium text-slate-500 hover:underline">
                                Retirer ma demande
                            </button>
                        @endif
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
</div>
