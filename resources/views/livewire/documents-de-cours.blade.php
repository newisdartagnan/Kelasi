<div class="mx-auto max-w-3xl">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">Documents de cours</h1>
            <p class="mt-1 text-sm text-slate-500">
                Les supports déposés par les enseignants pour les promotions qui les suivent.
            </p>
        </div>

        @if ($peutDeposer && ! $formulaireOuvert)
            <button
                type="button"
                wire:click="ouvrirFormulaire"
                class="rounded-lg bg-kelasi-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-kelasi-700"
            >
                Déposer un document
            </button>
        @endif
    </div>

    @if ($formulaireOuvert)
        <form wire:submit="deposer" class="mt-6 space-y-4 rounded-xl border border-slate-200 bg-white p-5">
            <div>
                <label for="coursId" class="block text-sm font-medium text-slate-700">Cours</label>
                <select id="coursId" wire:model="coursId"
                        class="mt-1 w-full rounded-lg border-slate-300 px-3 py-2.5 shadow-sm focus:border-kelasi-500 focus:ring-kelasi-500">
                    @foreach ($coursDepot as $unCours)
                        <option value="{{ $unCours->id }}">
                            {{ $unCours->code }} — {{ $unCours->intitule }}
                            ({{ $unCours->uniteEnseignement->promotion->niveau }})
                        </option>
                    @endforeach
                </select>
                @error('coursId') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="titre" class="block text-sm font-medium text-slate-700">Titre</label>
                <input id="titre" type="text" wire:model="titre"
                       placeholder="Syllabus — chapitres 1 à 4"
                       class="mt-1 w-full rounded-lg border-slate-300 px-3 py-2.5 shadow-sm focus:border-kelasi-500 focus:ring-kelasi-500">
                @error('titre') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="fichier" class="block text-sm font-medium text-slate-700">Fichier</label>
                <input id="fichier" type="file" wire:model="fichier"
                       class="mt-2 block w-full text-sm text-slate-600 file:mr-4 file:rounded-lg file:border-0 file:bg-kelasi-50 file:px-4 file:py-2 file:text-sm file:font-medium file:text-kelasi-700 hover:file:bg-kelasi-100">
                <p class="mt-1 text-xs text-slate-500">
                    PDF, document bureautique, image ou archive. 20 Mo au maximum.
                </p>
                @error('fichier') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                <div wire:loading wire:target="fichier" class="mt-2 text-sm text-slate-500">Téléversement...</div>
            </div>

            <div>
                <label for="description" class="block text-sm font-medium text-slate-700">
                    Précisions <span class="font-normal text-slate-400">(facultatif)</span>
                </label>
                <textarea id="description" wire:model="description" rows="2"
                          class="mt-1 w-full rounded-lg border-slate-300 px-3 py-2.5 shadow-sm focus:border-kelasi-500 focus:ring-kelasi-500"></textarea>
            </div>

            <label class="flex items-start gap-2 text-sm text-slate-700">
                <input type="checkbox" wire:model="publie"
                       class="mt-0.5 rounded border-slate-300 text-kelasi-600 focus:ring-kelasi-500">
                <span>
                    Partager tout de suite avec la promotion
                    <span class="block text-xs text-slate-500">
                        Sinon le document reste visible de vous seul, le temps de le finir.
                    </span>
                </span>
            </label>

            <div class="flex gap-2">
                <button type="submit" wire:loading.attr="disabled"
                        class="rounded-lg bg-kelasi-600 px-4 py-2 text-sm font-medium text-white hover:bg-kelasi-700 disabled:opacity-60">
                    Déposer
                </button>
                <button type="button" wire:click="fermerFormulaire"
                        class="rounded-lg px-4 py-2 text-sm text-slate-600 hover:bg-slate-100">
                    Annuler
                </button>
            </div>
        </form>
    @endif

    @if ($coursLisibles->isNotEmpty())
        <div class="mt-6">
            <label for="coursFiltre" class="sr-only">Filtrer par cours</label>
            <select id="coursFiltre" wire:model.live="coursFiltre"
                    class="w-full rounded-lg border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-kelasi-500 focus:ring-kelasi-500 sm:w-auto">
                <option value="">Tous les cours</option>
                @foreach ($coursLisibles as $unCours)
                    <option value="{{ $unCours->id }}">{{ $unCours->intitule }}</option>
                @endforeach
            </select>
        </div>
    @endif

    @if ($documents->isEmpty())
        <p class="mt-4 rounded-xl border border-dashed border-slate-300 bg-white px-4 py-12 text-center text-sm text-slate-500">
            Aucun document pour l'instant.
        </p>
    @else
        <ul class="mt-4 space-y-2">
            @foreach ($documents as $document)
                <li class="rounded-xl border border-slate-200 bg-white p-4">
                    <div class="flex flex-wrap items-baseline justify-between gap-2">
                        <div class="min-w-0">
                            <p class="font-medium">{{ $document->titre }}</p>
                            <p class="text-xs text-slate-500">
                                {{ $document->cours->intitule }} &middot;
                                {{ $document->taille_lisible }} &middot;
                                déposé par {{ $document->deposant->nom_complet }}
                                le {{ $document->created_at->translatedFormat('d/m/Y') }}
                            </p>
                        </div>

                        @unless ($document->publie)
                            <span class="inline-flex shrink-0 items-center rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600 ring-1 ring-inset ring-slate-200">
                                Non partagé
                            </span>
                        @endunless
                    </div>

                    @if ($document->description)
                        <p class="mt-2 text-sm text-slate-700">{{ $document->description }}</p>
                    @endif

                    <div class="mt-3 flex flex-wrap items-center gap-2">
                        <a href="{{ route('documents.telecharger', $document) }}"
                           class="rounded-lg bg-kelasi-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-kelasi-700">
                            Télécharger
                        </a>

                        @if ($document->deposant_id === auth()->id())
                            <button type="button" wire:click="basculerPublication({{ $document->id }})"
                                    class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                                {{ $document->publie ? 'Retirer du partage' : 'Publier' }}
                            </button>

                            <button type="button" wire:click="retirer({{ $document->id }})"
                                    wire:confirm="Supprimer définitivement ce document ?"
                                    class="rounded-lg px-3 py-2 text-sm text-slate-500 hover:bg-slate-100">
                                Supprimer
                            </button>
                        @endif

                        @if ($document->telechargements > 0)
                            <span class="text-xs text-slate-500">
                                {{ $document->telechargements }} téléchargement{{ $document->telechargements > 1 ? 's' : '' }}
                            </span>
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
</div>
