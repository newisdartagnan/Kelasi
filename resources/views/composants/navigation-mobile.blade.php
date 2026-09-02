@php($utilisateur = auth()->user())

@if ($utilisateur)
    {{-- La barre du bas : c'est par là que passent les chefs de promotion,
         qui travaillent debout, au téléphone, entre deux amphis. --}}
    <nav class="fixed inset-x-0 bottom-0 z-30 border-t border-slate-200 bg-white pb-[env(safe-area-inset-bottom)] md:hidden">
        <div class="mx-auto flex max-w-md">
            @foreach (navigationDe($utilisateur) as $lien)
                <a
                    href="{{ route($lien['route']) }}"
                    @class([
                        'relative flex flex-1 flex-col items-center gap-1 py-2.5 text-[11px] font-medium transition',
                        'text-kelasi-700' => request()->routeIs($lien['route']),
                        'text-slate-500' => ! request()->routeIs($lien['route']),
                    ])
                >
                    <span class="text-lg leading-none">{{ $lien['icone'] }}</span>
                    {{ $lien['libelle'] }}

                    @if (($lien['pastille'] ?? 0) > 0)
                        <span class="absolute right-1/4 top-1 rounded-full bg-rose-500 px-1.5 text-[10px] text-white">
                            {{ $lien['pastille'] }}
                        </span>
                    @endif
                </a>
            @endforeach
        </div>
    </nav>
@endif
