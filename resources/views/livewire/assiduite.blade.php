<div class="mx-auto max-w-3xl">
    <h1 class="text-2xl font-semibold tracking-tight">Assiduité</h1>
    <p class="mt-1 text-sm text-slate-500">
        Du taux le plus faible au plus élevé : ceux qui décrochent apparaissent en premier.
    </p>

    <div class="mt-5 flex flex-wrap gap-3">
        <div>
            <label for="promotionId" class="sr-only">Promotion</label>
            <select id="promotionId" wire:model.live="promotionId"
                    class="rounded-lg border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-kelasi-500 focus:ring-kelasi-500">
                @foreach ($promotions as $uneP)
                    <option value="{{ $uneP->id }}">{{ $uneP->departement->sigle }} &middot; {{ $uneP->nom_complet }}</option>
                @endforeach
            </select>
        </div>

        @if ($cours->isNotEmpty())
            <div>
                <label for="coursId" class="sr-only">Cours</label>
                <select id="coursId" wire:model.live="coursId"
                        class="rounded-lg border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-kelasi-500 focus:ring-kelasi-500">
                    <option value="">Tous les cours</option>
                    @foreach ($cours as $unCours)
                        <option value="{{ $unCours->id }}">{{ $unCours->intitule }}</option>
                    @endforeach
                </select>
            </div>
        @endif
    </div>

    @if ($lignes->isEmpty())
        <p class="mt-4 rounded-xl border border-dashed border-slate-300 bg-white px-4 py-12 text-center text-sm text-slate-500">
            Aucun relevé pour cette promotion. L'assiduité apparaîtra dès que les appels seront faits.
        </p>
    @else
        <ul class="mt-4 space-y-2">
            @foreach ($lignes as $ligne)
                @php($taux = $ligne['taux'])
                <li class="rounded-xl border border-slate-200 bg-white px-4 py-3">
                    <div class="flex items-baseline justify-between gap-4">
                        <div class="min-w-0">
                            <p class="truncate font-medium">{{ $ligne['etudiant']->nom_complet }}</p>
                            <p class="truncate text-xs text-slate-500">{{ $ligne['etudiant']->matricule }}</p>
                        </div>

                        <div class="shrink-0 text-right">
                            @if ($ligne['seances'] === 0)
                                <p class="text-sm text-slate-400">Pas encore d'appel</p>
                            @else
                                <p class="font-semibold tabular-nums">{{ $taux }}%</p>
                                <p class="text-xs tabular-nums text-slate-500">
                                    {{ $ligne['presences'] }} / {{ $ligne['seances'] }} séances
                                </p>
                            @endif
                        </div>
                    </div>

                    @if ($ligne['seances'] > 0)
                        <div class="jauge mt-2.5">
                            <div @class([
                                'jauge-valeur',
                                'bg-emerald-500' => $taux >= 75,
                                'bg-amber-500' => $taux >= 50 && $taux < 75,
                                'bg-rose-500' => $taux < 50,
                            ]) style="width: {{ $taux }}%"></div>
                        </div>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
</div>
