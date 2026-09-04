@php
    use App\Support\Navigation;

    $utilisateur = auth()->user();
    $routeCourante = request()->route()?->getName();
@endphp

@if ($utilisateur)
    @php
        $principales = Navigation::principales($utilisateur, $routeCourante);
        $secondaires = Navigation::secondaires($utilisateur, $routeCourante);
        $pastillePlus = Navigation::pastilleSecondaire($utilisateur, $routeCourante);
    @endphp

    {{-- La barre du bas : c'est par là que passent les chefs de promotion,
         qui travaillent debout, au téléphone, entre deux amphis.

         Elle ne porte que quatre entrées. Au-delà, les libellés se touchent
         et la barre devient illisible : le reste passe derrière « Plus ». --}}
    <div x-data="{ plusOuvert: false }" class="md:hidden">
        <div
            x-show="plusOuvert"
            x-transition.opacity
            x-on:click="plusOuvert = false"
            class="fixed inset-0 z-30 bg-slate-900/30"
            aria-hidden="true"
        ></div>

        <nav
            x-show="plusOuvert"
            x-transition
            x-cloak
            class="fixed inset-x-0 bottom-[3.75rem] z-40 mx-3 rounded-2xl border border-slate-200 bg-white p-2 shadow-lg"
            aria-label="Autres écrans"
        >
            @foreach ($secondaires as $lien)
                <a
                    href="{{ route($lien['route']) }}"
                    class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-slate-700 transition hover:bg-slate-100"
                >
                    <span class="w-5 text-center text-lg leading-none text-slate-500">{{ $lien['icone'] }}</span>
                    <span class="flex-1">{{ $lien['libelle'] }}</span>
                    @if (($lien['pastille'] ?? 0) > 0)
                        <span class="rounded-full bg-rose-500 px-1.5 text-xs text-white">{{ $lien['pastille'] }}</span>
                    @endif
                </a>
            @endforeach
        </nav>

        <nav class="fixed inset-x-0 bottom-0 z-40 border-t border-slate-200 bg-white pb-[env(safe-area-inset-bottom)]">
            <div class="mx-auto flex max-w-md">
                @foreach ($principales as $lien)
                    <a
                        href="{{ route($lien['route']) }}"
                        @class([
                            'relative flex flex-1 flex-col items-center gap-1 px-1 py-2.5 text-[11px] font-medium transition',
                            'text-kelasi-700' => request()->routeIs($lien['route']),
                            'text-slate-500' => ! request()->routeIs($lien['route']),
                        ])
                    >
                        <span class="text-lg leading-none">{{ $lien['icone'] }}</span>
                        <span class="max-w-full truncate">{{ $lien['libelle'] }}</span>

                        @if (($lien['pastille'] ?? 0) > 0)
                            <span class="absolute right-1/2 top-1 translate-x-3.5 rounded-full bg-rose-500 px-1.5 text-[10px] text-white">
                                {{ $lien['pastille'] }}
                            </span>
                        @endif
                    </a>
                @endforeach

                @if ($secondaires)
                    <button
                        type="button"
                        x-on:click="plusOuvert = !plusOuvert"
                        class="relative flex flex-1 flex-col items-center gap-1 px-1 py-2.5 text-[11px] font-medium text-slate-500 transition"
                        x-bind:class="plusOuvert && 'text-kelasi-700'"
                        aria-label="Autres écrans"
                    >
                        <span class="text-lg leading-none">⋯</span>
                        <span>Plus</span>

                        @if ($pastillePlus > 0)
                            <span class="absolute right-1/2 top-1 translate-x-3.5 rounded-full bg-rose-500 px-1.5 text-[10px] text-white">
                                {{ $pastillePlus }}
                            </span>
                        @endif
                    </button>
                @endif
            </div>
        </nav>
    </div>
@endif
