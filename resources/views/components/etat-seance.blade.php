@props(['statut'])

@php
    $apparence = match ($statut) {
        \App\Models\Seance::STATUT_VALIDEE => ['Contresignée', 'bg-emerald-50 text-emerald-700 ring-emerald-200'],
        \App\Models\Seance::STATUT_SOUMISE => ['En attente', 'bg-amber-50 text-amber-800 ring-amber-200'],
        \App\Models\Seance::STATUT_CONTESTEE => ['Contestée', 'bg-rose-50 text-rose-700 ring-rose-200'],
        \App\Models\Seance::STATUT_ANNULEE => ['Annulée', 'bg-slate-100 text-slate-600 ring-slate-200'],
        default => ['Brouillon', 'bg-slate-100 text-slate-600 ring-slate-200'],
    };
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex shrink-0 items-center rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset '.$apparence[1]]) }}>
    {{ $apparence[0] }}
</span>
