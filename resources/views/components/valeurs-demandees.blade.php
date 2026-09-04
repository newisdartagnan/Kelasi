@props(['demande'])

@php
    $libelles = [
        'intitule' => 'Intitulé',
        'credits' => 'Crédits',
        'heures_cmi' => 'CMI',
        'heures_td' => 'TD',
        'heures_tp' => 'TP',
    ];

    $precedent = $demande->modifications['etat_precedent'] ?? [];

    // On ne montre que ce qui change réellement : réafficher une valeur
    // identique noierait la seule ligne qui compte.
    $lignes = collect($demande->modifications)
        ->only(array_keys($libelles))
        ->filter(fn ($valeur, $champ) => ! isset($precedent[$champ]) || (string) $precedent[$champ] !== (string) $valeur);
@endphp

@if ($lignes->isNotEmpty())
    <dl class="mt-2 flex flex-wrap gap-x-5 gap-y-1 rounded-lg bg-kelasi-50/60 px-3 py-2 text-xs">
        @foreach ($lignes as $champ => $valeur)
            <div>
                <dt class="inline text-slate-500">{{ $libelles[$champ] }} :</dt>
                <dd class="inline font-medium text-slate-800">
                    @isset($precedent[$champ])
                        <span class="text-slate-400 line-through">{{ $precedent[$champ] }}</span>
                        &rarr;
                    @endisset
                    {{ $valeur }}
                </dd>
            </div>
        @endforeach
    </dl>
@endif
