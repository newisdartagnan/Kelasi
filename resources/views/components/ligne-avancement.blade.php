@props(['libelle', 'detail' => null, 'avancement', 'tauxAttendu' => 0, 'lien' => null])

@php
    $ecart = $avancement->ecartSurAttendu($tauxAttendu);

    $couleur = match (true) {
        $ecart >= 0 => 'bg-emerald-500',
        $ecart >= -10 => 'bg-amber-500',
        default => 'bg-rose-500',
    };

    // La part saisie mais pas encore contresignée, mesurée sur le volume prévu.
    $partEnAttente = $avancement->minutesPrevues > 0
        ? round(min(100 - $avancement->taux(), $avancement->minutesEnAttente / $avancement->minutesPrevues * 100), 1)
        : 0;
@endphp

<div
    @if ($lien) wire:click="$set('faculteId', {{ $lien['faculteId'] }})" role="button" tabindex="0" @endif
    @class([
        'rounded-xl border border-slate-200 bg-white px-4 py-3 transition',
        'cursor-pointer hover:border-kelasi-200 hover:bg-kelasi-50/40' => $lien,
    ])
>
    <div class="flex items-baseline justify-between gap-4">
        <div class="min-w-0">
            <p class="truncate font-medium">{{ $libelle }}</p>
            @if ($detail)
                <p class="truncate text-xs text-slate-500">{{ $detail }}</p>
            @endif
        </div>

        <div class="shrink-0 text-right">
            <p class="font-semibold tabular-nums">{{ $avancement->tauxReel() }}%</p>
            <p class="text-xs tabular-nums text-slate-500">
                {{ $avancement->heuresRealisees() }} / {{ $avancement->heuresPrevues() }} h
            </p>
        </div>
    </div>

    <div class="jauge relative mt-2.5">
        <div class="jauge-valeur {{ $couleur }}" style="width: {{ $avancement->taux() }}%"></div>

        @if ($partEnAttente > 0)
            {{-- Ce qui est saisi mais pas encore contresigné, en gris clair. --}}
            <div
                class="jauge-en-attente"
                style="left: {{ $avancement->taux() }}%; width: {{ $partEnAttente }}%"
                title="{{ $avancement->heuresEnAttente() }} h en attente de contreseing"
            ></div>
        @endif

        {{-- Le repère du calendrier : la barre devrait avoir atteint ce trait. --}}
        <div
            class="jauge-repere"
            style="left: {{ min(100, $tauxAttendu) }}%"
            title="Attendu à ce jour : {{ $tauxAttendu }} %"
        ></div>
    </div>
</div>
