<div class="mx-auto max-w-3xl">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">Activités</h1>
            <p class="mt-1 text-sm text-slate-500">
                Examens, interrogations, visites guidées et conférences qui vous concernent.
            </p>
        </div>

        @if ($peutAnnoncer && ! $formulaireOuvert)
            <button
                type="button"
                wire:click="ouvrirFormulaire"
                class="rounded-lg bg-kelasi-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-kelasi-700"
            >
                Annoncer une activité
            </button>
        @endif
    </div>

    @if ($formulaireOuvert)
        <form wire:submit="enregistrer" class="mt-6 space-y-4 rounded-xl border border-slate-200 bg-white p-5">
            <div>
                <label for="titre" class="block text-sm font-medium text-slate-700">Titre</label>
                <input id="titre" type="text" wire:model="titre"
                       placeholder="Examen de droit civil — session de janvier"
                       class="mt-1 w-full rounded-lg border-slate-300 px-3 py-2.5 shadow-sm focus:border-kelasi-500 focus:ring-kelasi-500">
                @error('titre') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label for="type" class="block text-sm font-medium text-slate-700">Nature</label>
                    <select id="type" wire:model="type"
                            class="mt-1 w-full rounded-lg border-slate-300 px-3 py-2.5 shadow-sm focus:border-kelasi-500 focus:ring-kelasi-500">
                        @foreach ($types as $valeur => $libelle)
                            <option value="{{ $valeur }}">{{ $libelle }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="portee" class="block text-sm font-medium text-slate-700">Qui est concerné</label>
                    <select id="portee" wire:model.live="portee"
                            class="mt-1 w-full rounded-lg border-slate-300 px-3 py-2.5 shadow-sm focus:border-kelasi-500 focus:ring-kelasi-500">
                        @foreach ($portees as $valeur => $libelle)
                            <option value="{{ $valeur }}">{{ $libelle }}</option>
                        @endforeach
                    </select>
                    @error('portee') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                </div>
            </div>

            @if ($portee === 'promotion' && $promotions->isNotEmpty())
                <div>
                    <label for="promotionId" class="block text-sm font-medium text-slate-700">Promotion</label>
                    <select id="promotionId" wire:model="promotionId"
                            class="mt-1 w-full rounded-lg border-slate-300 px-3 py-2.5 shadow-sm focus:border-kelasi-500 focus:ring-kelasi-500">
                        <option value="">Choisir...</option>
                        @foreach ($promotions as $promotion)
                            <option value="{{ $promotion->id }}">
                                {{ $promotion->departement->sigle }} &middot; {{ $promotion->nom_complet }}
                            </option>
                        @endforeach
                    </select>
                    @error('promotionId') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                </div>
            @endif

            @if ($portee === 'faculte' && $facultes->isNotEmpty())
                <div>
                    <label for="faculteId" class="block text-sm font-medium text-slate-700">Faculté</label>
                    <select id="faculteId" wire:model="faculteId"
                            class="mt-1 w-full rounded-lg border-slate-300 px-3 py-2.5 shadow-sm focus:border-kelasi-500 focus:ring-kelasi-500">
                        <option value="">Choisir...</option>
                        @foreach ($facultes as $faculte)
                            <option value="{{ $faculte->id }}">{{ $faculte->nom }}</option>
                        @endforeach
                    </select>
                    @error('faculteId') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                </div>
            @endif

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label for="debut" class="block text-sm font-medium text-slate-700">Début</label>
                    <input id="debut" type="datetime-local" wire:model="debut"
                           class="mt-1 w-full rounded-lg border-slate-300 px-3 py-2.5 shadow-sm focus:border-kelasi-500 focus:ring-kelasi-500">
                    @error('debut') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="fin" class="block text-sm font-medium text-slate-700">
                        Fin <span class="font-normal text-slate-400">(facultatif)</span>
                    </label>
                    <input id="fin" type="datetime-local" wire:model="fin"
                           class="mt-1 w-full rounded-lg border-slate-300 px-3 py-2.5 shadow-sm focus:border-kelasi-500 focus:ring-kelasi-500">
                    @error('fin') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                </div>
            </div>

            @if ($locaux->isNotEmpty())
                <div>
                    <label for="localId" class="block text-sm font-medium text-slate-700">
                        Local <span class="font-normal text-slate-400">(facultatif)</span>
                    </label>
                    <select id="localId" wire:model="localId"
                            class="mt-1 w-full rounded-lg border-slate-300 px-3 py-2.5 shadow-sm focus:border-kelasi-500 focus:ring-kelasi-500">
                        <option value="">Non précisé</option>
                        @foreach ($locaux as $local)
                            <option value="{{ $local->id }}">{{ $local->nom }}</option>
                        @endforeach
                    </select>
                </div>
            @endif

            <div>
                <label for="description" class="block text-sm font-medium text-slate-700">
                    Précisions <span class="font-normal text-slate-400">(facultatif)</span>
                </label>
                <textarea id="description" wire:model="description" rows="2"
                          class="mt-1 w-full rounded-lg border-slate-300 px-3 py-2.5 shadow-sm focus:border-kelasi-500 focus:ring-kelasi-500"></textarea>
            </div>

            <div class="flex gap-2">
                <button type="submit" class="rounded-lg bg-kelasi-600 px-4 py-2 text-sm font-medium text-white hover:bg-kelasi-700">
                    {{ $activiteModifiee ? 'Enregistrer' : 'Annoncer' }}
                </button>
                <button type="button" wire:click="fermerFormulaire" class="rounded-lg px-4 py-2 text-sm text-slate-600 hover:bg-slate-100">
                    Annuler
                </button>
            </div>
        </form>
    @endif

    <label class="mt-6 flex items-center gap-2 text-sm text-slate-600">
        <input type="checkbox" wire:model.live="inclurePassees"
               class="rounded border-slate-300 text-kelasi-600 focus:ring-kelasi-500">
        Afficher aussi les activités passées
    </label>

    @if ($activites->isEmpty())
        <p class="mt-4 rounded-xl border border-dashed border-slate-300 bg-white px-4 py-12 text-center text-sm text-slate-500">
            Aucune activité annoncée.
        </p>
    @else
        <ul class="mt-4 space-y-3">
            @foreach ($activites as $activite)
                <li @class([
                    'rounded-xl border bg-white p-4',
                    'border-slate-200' => $activite->statut === 'planifiee',
                    'border-slate-200 opacity-60' => $activite->statut !== 'planifiee',
                ])>
                    <div class="flex flex-wrap items-baseline justify-between gap-2">
                        <div class="min-w-0">
                            <p class="font-medium">{{ $activite->titre }}</p>
                            <p class="text-xs text-slate-500">
                                {{ $types[$activite->type] ?? $activite->type }} &middot;
                                <x-portee-activite :activite="$activite" />
                            </p>
                        </div>

                        <p class="text-sm tabular-nums text-slate-600">
                            {{ $activite->debut->translatedFormat('D d/m à H\hi') }}
                            @if ($activite->fin)
                                <span class="text-slate-400">→ {{ $activite->fin->translatedFormat('H\hi') }}</span>
                            @endif
                        </p>
                    </div>

                    @if ($activite->description)
                        <p class="mt-2 text-sm text-slate-700">{{ $activite->description }}</p>
                    @endif

                    <p class="mt-2 text-xs text-slate-500">
                        Annoncée par {{ $activite->createur->nom_complet }}
                        @if ($activite->local) &middot; {{ $activite->local->nom }} @endif
                        @if ($activite->statut !== 'planifiee')
                            &middot; <span class="font-medium">{{ $activite->statut === 'cloturee' ? 'Clôturée' : 'Annulée' }}</span>
                        @endif
                    </p>

                    @if ($activite->statut === 'planifiee' && $gestion->peutAgirSur(auth()->user(), $activite))
                        <div class="mt-3 flex flex-wrap gap-2">
                            <button type="button" wire:click="modifier({{ $activite->id }})"
                                    class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50">
                                Modifier
                            </button>
                            <button type="button" wire:click="cloturer({{ $activite->id }})"
                                    class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50">
                                Clôturer
                            </button>
                            <button type="button" wire:click="annuler({{ $activite->id }})"
                                    class="rounded-lg px-3 py-1.5 text-sm text-slate-500 hover:bg-slate-100">
                                Annuler l'activité
                            </button>
                        </div>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
</div>
