@props(['activite'])

{{-- La portée dit qui est concerné : c'est l'information que le lecteur
     cherche en premier, avant même la date. --}}
<span>
    @switch($activite->portee)
        @case(\App\Models\Activite::PORTEE_UNIVERSITE)
            Toute l'université
            @break
        @case(\App\Models\Activite::PORTEE_FACULTE)
            {{ $activite->faculte?->nom ?? 'Faculté' }}
            @break
        @case(\App\Models\Activite::PORTEE_PROMOTION)
            {{ $activite->promotion?->nom_complet ?? 'Promotion' }}
            @break
        @default
            {{ $activite->departement?->nom ?? 'Département' }}
    @endswitch
</span>
