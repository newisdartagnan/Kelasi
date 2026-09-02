<div class="mx-auto max-w-2xl">
    <h1 class="text-2xl font-semibold tracking-tight">Saisir une séance</h1>
    <p class="mt-1 text-sm text-slate-500">
        {{ auth()->user()->promotion?->nom_complet ?? 'Aucune promotion rattachée à votre compte.' }}
    </p>

    @if ($cours->isEmpty())
        <p class="mt-6 rounded-xl border border-dashed border-slate-300 bg-white px-4 py-12 text-center text-sm text-slate-500">
            Aucun cours au programme de votre promotion.
        </p>
    @else
        <form wire:submit="enregistrer" class="mt-6 space-y-4 rounded-xl border border-slate-200 bg-white p-5">
            <div>
                <label for="coursId" class="block text-sm font-medium text-slate-700">Cours</label>
                <select id="coursId" wire:model="coursId"
                        class="mt-1 w-full rounded-lg border-slate-300 px-3 py-2.5 shadow-sm focus:border-kelasi-500 focus:ring-kelasi-500">
                    <option value="">Choisir un cours...</option>
                    @foreach ($cours->groupBy('uniteEnseignement.semestre') as $semestre => $groupe)
                        <optgroup label="Semestre {{ $semestre }}">
                            @foreach ($groupe as $unCours)
                                <option value="{{ $unCours->id }}">{{ $unCours->code }} — {{ $unCours->intitule }}</option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
                @error('coursId') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div class="col-span-2 sm:col-span-1">
                    <label for="dateSeance" class="block text-sm font-medium text-slate-700">Date</label>
                    <input id="dateSeance" type="date" wire:model="dateSeance" max="{{ now()->toDateString() }}"
                           class="mt-1 w-full rounded-lg border-slate-300 px-3 py-2.5 shadow-sm focus:border-kelasi-500 focus:ring-kelasi-500">
                    @error('dateSeance') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                </div>

                <div class="col-span-2 sm:col-span-1">
                    <label for="type" class="block text-sm font-medium text-slate-700">Nature</label>
                    <select id="type" wire:model="type"
                            class="mt-1 w-full rounded-lg border-slate-300 px-3 py-2.5 shadow-sm focus:border-kelasi-500 focus:ring-kelasi-500">
                        @foreach (\App\Models\Seance::TYPES as $valeur => $libelle)
                            <option value="{{ $valeur }}">{{ $libelle }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="heureDebut" class="block text-sm font-medium text-slate-700">Début</label>
                    <input id="heureDebut" type="time" wire:model="heureDebut"
                           class="mt-1 w-full rounded-lg border-slate-300 px-3 py-2.5 shadow-sm focus:border-kelasi-500 focus:ring-kelasi-500">
                </div>

                <div>
                    <label for="heureFin" class="block text-sm font-medium text-slate-700">Fin</label>
                    <input id="heureFin" type="time" wire:model="heureFin"
                           class="mt-1 w-full rounded-lg border-slate-300 px-3 py-2.5 shadow-sm focus:border-kelasi-500 focus:ring-kelasi-500">
                    @error('heureFin') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                </div>
            </div>

            <div>
                <label for="matiereCouverte" class="block text-sm font-medium text-slate-700">
                    Matière effectivement traitée
                </label>
                <textarea id="matiereCouverte" wire:model="matiereCouverte" rows="3"
                          placeholder="Chapitre 3 : la formation du contrat. Points abordés..."
                          class="mt-1 w-full rounded-lg border-slate-300 px-3 py-2.5 shadow-sm focus:border-kelasi-500 focus:ring-kelasi-500"></textarea>
                <p class="mt-1 text-xs text-slate-500">
                    C'est sur cette déclaration que portera le contreseing de l'enseignant.
                </p>
                @error('matiereCouverte') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label for="localId" class="block text-sm font-medium text-slate-700">Local</label>
                    <select id="localId" wire:model="localId"
                            class="mt-1 w-full rounded-lg border-slate-300 px-3 py-2.5 shadow-sm focus:border-kelasi-500 focus:ring-kelasi-500">
                        <option value="">Non précisé</option>
                        @foreach ($locaux as $local)
                            <option value="{{ $local->id }}">{{ $local->nom }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="effectifPresent" class="block text-sm font-medium text-slate-700">Presents</label>
                    <input id="effectifPresent" type="number" inputmode="numeric" min="0" wire:model="effectifPresent"
                           class="mt-1 w-full rounded-lg border-slate-300 px-3 py-2.5 shadow-sm focus:border-kelasi-500 focus:ring-kelasi-500">
                </div>
            </div>

            <button type="submit"
                    class="w-full rounded-lg bg-kelasi-600 px-4 py-3 font-medium text-white transition hover:bg-kelasi-700 disabled:opacity-60"
                    wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="enregistrer">Enregistrer la séance</span>
                <span wire:loading wire:target="enregistrer">Enregistrement...</span>
            </button>
        </form>
    @endif

    @if ($dernieres->isNotEmpty())
        <section class="mt-8">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Vos dernières saisies</h2>
            <ul class="mt-3 space-y-2">
                @foreach ($dernieres as $seance)
                    <li class="flex items-baseline justify-between gap-3 rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm">
                        <span class="min-w-0">
                            <span class="block truncate font-medium">{{ $seance->cours->intitule }}</span>
                            <span class="text-xs text-slate-500">
                                {{ $seance->date_seance->translatedFormat('d/m/Y') }} &middot; {{ $seance->duree_heures }} h
                            </span>
                        </span>
                        <x-etat-seance :statut="$seance->statut" />
                    </li>
                @endforeach
            </ul>
        </section>
    @endif
</div>
