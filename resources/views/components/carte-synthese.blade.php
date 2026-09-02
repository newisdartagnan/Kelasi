@props(['avancement', 'tauxAttendu' => 0])

@php
    $ecart = $avancement->ecartSurAttendu($tauxAttendu);
@endphp

<div class="rounded-xl border border-slate-200 bg-white p-5">
    <div class="flex flex-wrap items-baseline gap-x-8 gap-y-3">
        <div>
            <p class="text-3xl font-semibold tabular-nums tracking-tight">{{ $avancement->tauxReel() }}<span class="text-lg text-slate-400">%</span></p>
            <p class="mt-0.5 text-xs uppercase tracking-wide text-slate-500">Avancement</p>
        </div>

        <div>
            <p class="text-lg font-medium tabular-nums text-slate-700">
                {{ $avancement->heuresRealisees() }} <span class="text-slate-400">/ {{ $avancement->heuresPrevues() }} h</span>
            </p>
            <p class="mt-0.5 text-xs uppercase tracking-wide text-slate-500">Heures contresignées</p>
        </div>

        @if ($avancement->heuresEnAttente() > 0)
            <div>
                <p class="text-lg font-medium tabular-nums text-amber-700">{{ $avancement->heuresEnAttente() }} h</p>
                <p class="mt-0.5 text-xs uppercase tracking-wide text-slate-500">En attente de contreseing</p>
            </div>
        @endif

        <div>
            <p @class([
                'text-lg font-medium tabular-nums',
                'text-emerald-700' => $ecart >= 0,
                'text-amber-700' => $ecart < 0 && $ecart >= -10,
                'text-rose-700' => $ecart < -10,
            ])>
                {{ $ecart > 0 ? '+' : '' }}{{ $ecart }} pts
            </p>
            <p class="mt-0.5 text-xs uppercase tracking-wide text-slate-500">Écart sur le calendrier</p>
        </div>
    </div>

    <div class="jauge mt-5">
        <div class="jauge-valeur bg-kelasi-600" style="width: {{ $avancement->taux() }}%"></div>

        {{-- Le repère du calendrier : la barre devrait avoir atteint ce trait. --}}
        <div
            class="jauge-repere"
            style="left: {{ min(100, $tauxAttendu) }}%"
            title="Attendu à ce jour : {{ $tauxAttendu }} %"
        ></div>
    </div>

    <p class="mt-2.5 text-xs text-slate-500">
        Le trait vertical marque où l'avancement devrait se trouver à ce jour.
    </p>
</div>
