<div>
    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">Journal des séances</h1>
            <p class="mt-1 text-sm text-slate-500">Qui a saisi quoi, quel jour, et qui l'a contresigné.</p>
        </div>

        <div class="inline-flex rounded-lg border border-slate-200 bg-white p-0.5 text-sm">
            @foreach ($statuts as $valeur => $libelle)
                <button
                    type="button"
                    wire:click="$set('statut', '{{ $valeur }}')"
                    @class([
                        'rounded-md px-3 py-1.5 font-medium transition',
                        'bg-kelasi-600 text-white' => $statut === $valeur,
                        'text-slate-600 hover:bg-slate-100' => $statut !== $valeur,
                    ])
                >
                    {{ $libelle }}
                </button>
            @endforeach
        </div>
    </div>

    @if ($seances->isEmpty())
        <p class="rounded-xl border border-dashed border-slate-300 bg-white px-4 py-12 text-center text-sm text-slate-500">
            Aucune séance à afficher.
        </p>
    @else
        <ul class="space-y-2">
            @foreach ($seances as $seance)
                <li class="rounded-xl border border-slate-200 bg-white px-4 py-3">
                    <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
                        <p class="font-medium">{{ $seance->cours->intitule }}</p>
                        <x-etat-seance :statut="$seance->statut" />
                    </div>

                    <p class="mt-1 text-xs text-slate-500">
                        {{ $seance->date_seance->translatedFormat('d/m/Y') }} &middot;
                        {{ $seance->duree_heures }} h {{ $seance->libelle_type }} &middot;
                        {{ $seance->cours->uniteEnseignement->promotion->nom_complet }}
                    </p>

                    <p class="mt-2 text-sm text-slate-700">{{ $seance->matiere_couverte }}</p>

                    <p class="mt-2 text-xs text-slate-500">
                        Saisie par {{ $seance->saisiePar->nom_complet }}
                        @if ($seance->valideePar)
                            &middot; contresignée par {{ $seance->valideePar->nom_complet }}
                            le {{ $seance->validee_at?->translatedFormat('d/m/Y') }}
                        @endif
                    </p>

                    @if ($seance->motif_contestation)
                        <p class="mt-2 rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-900">
                            Contestation : {{ $seance->motif_contestation }}
                        </p>
                    @endif
                </li>
            @endforeach
        </ul>

        <div class="mt-6">{{ $seances->links() }}</div>
    @endif
</div>
