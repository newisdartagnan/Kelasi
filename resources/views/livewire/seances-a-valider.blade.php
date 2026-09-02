<div class="mx-auto max-w-3xl">
    <h1 class="text-2xl font-semibold tracking-tight">Séances à contresigner</h1>
    <p class="mt-1 text-sm text-slate-500">
        Votre signature fait entrer ces heures dans l'avancement officiel du cours.
    </p>

    @if ($seances->isEmpty())
        <p class="mt-6 rounded-xl border border-dashed border-slate-300 bg-white px-4 py-12 text-center text-sm text-slate-500">
            Aucune séance en attente. Tout est à jour.
        </p>
    @else
        <ul class="mt-6 space-y-3">
            @foreach ($seances as $seance)
                <li class="rounded-xl border border-slate-200 bg-white p-4">
                    <div class="flex flex-wrap items-baseline justify-between gap-2">
                        <div>
                            <p class="font-medium">{{ $seance->cours->intitule }}</p>
                            <p class="text-xs text-slate-500">
                                {{ $seance->cours->uniteEnseignement->promotion->nom_complet }}
                            </p>
                        </div>

                        <p class="text-sm tabular-nums text-slate-600">
                            {{ $seance->date_seance->translatedFormat('D d/m') }} &middot;
                            {{ substr($seance->heure_debut, 0, 5) }}–{{ substr($seance->heure_fin, 0, 5) }}
                            <span class="text-slate-400">({{ $seance->duree_heures }} h {{ $seance->libelle_type }})</span>
                        </p>
                    </div>

                    <p class="mt-3 rounded-lg bg-slate-50 px-3 py-2.5 text-sm leading-relaxed text-slate-700">
                        {{ $seance->matiere_couverte }}
                    </p>

                    <p class="mt-2 text-xs text-slate-500">
                        Saisie par {{ $seance->saisiePar->nom_complet }}
                        @if ($seance->local) &middot; {{ $seance->local->nom }} @endif
                        @if ($seance->effectif_present) &middot; {{ $seance->effectif_present }} présents @endif
                    </p>

                    @if ($seanceContestee === $seance->id)
                        <div class="mt-3 rounded-lg border border-amber-200 bg-amber-50 p-3">
                            <label for="motif" class="block text-sm font-medium text-amber-900">
                                Pourquoi renvoyez-vous cette séance ?
                            </label>

                            <textarea
                                id="motif"
                                wire:model="motif"
                                rows="2"
                                placeholder="Le chapitre annoncé n'a pas été traité ce jour-là..."
                                class="mt-1 w-full rounded-lg border-amber-300 px-3 py-2 text-sm focus:border-amber-500 focus:ring-amber-500"
                            ></textarea>

                            @error('motif')
                                <p class="mt-1 text-sm text-rose-600">{{ $message }}</p>
                            @enderror

                            <div class="mt-2 flex gap-2">
                                <button
                                    type="button"
                                    wire:click="contester"
                                    class="rounded-lg bg-amber-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-amber-700"
                                >
                                    Renvoyer au chef de promotion
                                </button>

                                <button
                                    type="button"
                                    wire:click="$set('seanceContestee', null)"
                                    class="rounded-lg px-3 py-1.5 text-sm text-slate-600 hover:bg-slate-100"
                                >
                                    Annuler
                                </button>
                            </div>
                        </div>
                    @else
                        <div class="mt-3 flex gap-2">
                            <button
                                type="button"
                                wire:click="valider({{ $seance->id }})"
                                class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-emerald-700"
                            >
                                Contresigner
                            </button>

                            <button
                                type="button"
                                wire:click="ouvrirContestation({{ $seance->id }})"
                                class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                            >
                                Contester
                            </button>
                        </div>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
</div>
