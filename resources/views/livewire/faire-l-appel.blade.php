@php
    $apparences = [
        'present' => 'bg-emerald-100 text-emerald-800 ring-emerald-300',
        'retard' => 'bg-amber-100 text-amber-800 ring-amber-300',
        'excuse' => 'bg-sky-100 text-sky-800 ring-sky-300',
        'absent' => 'bg-rose-100 text-rose-800 ring-rose-300',
    ];
@endphp

<div class="mx-auto max-w-2xl">
    <h1 class="text-2xl font-semibold tracking-tight">Appel</h1>
    <p class="mt-1 text-sm text-slate-500">
        {{ $seance->cours->intitule }} &middot;
        {{ $seance->date_seance->translatedFormat('l j F') }} &middot;
        {{ substr($seance->heure_debut, 0, 5) }}
    </p>

    {{-- Dans un amphi de deux cents inscrits, on pointe les absents, pas les
         présents : tout le monde arrive donc marqué présent. --}}
    <div class="mt-5 flex flex-wrap items-center gap-3 rounded-xl border border-slate-200 bg-white p-4">
        @foreach ($statuts as $cle => $libelle)
            <span class="text-sm">
                <span class="font-semibold tabular-nums">{{ $compte[$cle] ?? 0 }}</span>
                <span class="text-slate-500">{{ mb_strtolower($libelle) }}</span>
            </span>
        @endforeach

        <button type="button" wire:click="toutPresent"
                class="ml-auto rounded-lg border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50">
            Tout remettre à présent
        </button>
    </div>

    @if ($inscrits->isEmpty())
        <p class="mt-4 rounded-xl border border-dashed border-slate-300 bg-white px-4 py-12 text-center text-sm text-slate-500">
            Aucun étudiant inscrit dans cette promotion.
        </p>
    @else
        <ul class="mt-4 divide-y divide-slate-100 overflow-hidden rounded-xl border border-slate-200 bg-white">
            @foreach ($inscrits as $etudiant)
                @php($statut = $this->statuts[$etudiant->id] ?? 'present')
                <li class="flex items-center gap-3 px-4 py-2.5">
                    <span class="min-w-0 flex-1">
                        <span class="block truncate text-sm font-medium">{{ $etudiant->nom_complet }}</span>
                        <span class="block truncate text-xs text-slate-500">{{ $etudiant->matricule }}</span>
                    </span>

                    <button
                        type="button"
                        wire:click="basculer({{ $etudiant->id }})"
                        class="shrink-0 rounded-full px-3 py-1 text-xs font-medium ring-1 ring-inset transition {{ $apparences[$statut] ?? '' }}"
                    >
                        {{ $statuts[$statut] ?? $statut }}
                    </button>
                </li>
            @endforeach
        </ul>

        <button type="submit" wire:click="enregistrer"
                class="mt-5 w-full rounded-lg bg-kelasi-600 px-4 py-3 font-medium text-white transition hover:bg-kelasi-700">
            Enregistrer l'appel
        </button>
    @endif
</div>
