@php
    use App\Support\Avancement;

    $consolide = $lignes->reduce(
        fn (Avancement $porte, array $ligne) => $porte->plus($ligne['avancement']),
        Avancement::vide(),
    );

    $titres = [
        'facultes' => 'Avancement par faculté',
        'promotions' => 'Avancement par promotion',
        'mes-cours' => 'Mes cours',
        'cours' => 'Avancement des cours',
    ];
@endphp

<div>
    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">{{ $titres[$maille] }}</h1>
            <p class="mt-1 text-sm text-slate-500">
                @if ($annee)
                    Année académique {{ $annee->libelle }} &middot; {{ $tauxAttendu }} % de l'année écoulée
                @else
                    Aucune année académique ouverte.
                @endif
            </p>
        </div>

        <div class="flex items-center gap-2">
            @canany(['export.generer.universite', 'export.generer.faculte'])
                <a
                    href="{{ route('avancement.export', array_filter(['semestre' => $semestre, 'faculte' => $faculteId])) }}"
                    class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                    title="Télécharger l'avancement au format Excel"
                >
                    Exporter
                </a>
            @endcanany

        {{-- Le sélecteur de semestre : sans lui, un premier semestre achevé et un
             second à peine commencé se moyenneraient en un chiffre trompeur. --}}
        <div class="inline-flex rounded-lg border border-slate-200 bg-white p-0.5 text-sm">
            @foreach ([1 => 'Semestre 1', 2 => 'Semestre 2', 0 => 'Année'] as $valeur => $libelle)
                <button
                    type="button"
                    wire:click="$set('semestre', {{ $valeur ?: 'null' }})"
                    @class([
                        'rounded-md px-3 py-1.5 font-medium transition',
                        'bg-kelasi-600 text-white' => $semestre === ($valeur ?: null),
                        'text-slate-600 hover:bg-slate-100' => $semestre !== ($valeur ?: null),
                    ])
                >
                    {{ $libelle }}
                </button>
            @endforeach
            </div>
        </div>
    </div>

    @if ($faculteId && auth()->user()->aPorteeUniversitaire())
        <button
            type="button"
            wire:click="$set('faculteId', null)"
            class="mb-4 text-sm font-medium text-kelasi-600 hover:underline"
        >
            &larr; Toutes les facultés
        </button>
    @endif

    <x-carte-synthese :avancement="$consolide" :taux-attendu="$tauxAttendu" />

    @if ($lignes->isEmpty())
        <p class="mt-6 rounded-xl border border-dashed border-slate-300 bg-white px-4 py-12 text-center text-sm text-slate-500">
            Rien à afficher pour ce semestre.
        </p>
    @else
        <ul class="mt-6 space-y-2">
            @foreach ($lignes as $ligne)
                <li>
                    <x-ligne-avancement
                        :libelle="$ligne['libelle']"
                        :detail="$ligne['detail']"
                        :avancement="$ligne['avancement']"
                        :taux-attendu="$tauxAttendu"
                        :lien="$ligne['lien'] ?? null"
                    />
                </li>
            @endforeach
        </ul>
    @endif
</div>
