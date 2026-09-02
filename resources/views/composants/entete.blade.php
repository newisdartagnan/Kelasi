@php($utilisateur = auth()->user())

<header class="sticky top-0 z-30 border-b border-slate-200 bg-white/95 backdrop-blur">
    <div class="mx-auto flex w-full max-w-6xl items-center gap-4 px-4 py-3">
        <a href="{{ route('tableau-de-bord') }}" class="flex items-center gap-2.5">
            <span class="grid h-9 w-9 place-items-center rounded-lg bg-kelasi-600 text-lg font-bold text-white">K</span>
            <span class="hidden text-lg font-semibold tracking-tight sm:block">Kelasi</span>
        </a>

        {{-- Le bandeau hors ligne : discret quand tout va bien, franc quand la file se remplit. --}}
        <div x-data="etatDeConnexion()" x-cloak class="flex-1">
            <p
                x-show="!enLigne || file > 0"
                class="inline-flex items-center gap-2 rounded-full px-3 py-1 text-xs font-medium"
                :class="enLigne ? 'bg-amber-50 text-amber-800' : 'bg-slate-200 text-slate-700'"
            >
                <span class="h-1.5 w-1.5 rounded-full" :class="enLigne ? 'bg-amber-500' : 'bg-slate-500'"></span>
                <span x-show="!enLigne">Hors ligne</span>
                <span x-show="file > 0" x-text="file + (file > 1 ? ' séances à remonter' : ' séance à remonter')"></span>
            </p>
        </div>

        @if ($utilisateur)
            <nav class="hidden items-center gap-1 md:flex">
                @foreach (navigationDe($utilisateur) as $lien)
                    <a
                        href="{{ route($lien['route']) }}"
                        @class([
                            'rounded-lg px-3 py-2 text-sm font-medium transition',
                            'bg-kelasi-50 text-kelasi-700' => request()->routeIs($lien['route']),
                            'text-slate-600 hover:bg-slate-100' => ! request()->routeIs($lien['route']),
                        ])
                    >
                        {{ $lien['libelle'] }}
                        @if (($lien['pastille'] ?? 0) > 0)
                            <span class="ml-1 rounded-full bg-rose-500 px-1.5 text-xs text-white">{{ $lien['pastille'] }}</span>
                        @endif
                    </a>
                @endforeach
            </nav>

            <form method="POST" action="{{ route('deconnexion') }}" class="shrink-0">
                @csrf
                <button
                    type="submit"
                    class="flex items-center gap-2 rounded-lg py-1.5 pl-2 pr-1 text-left transition hover:bg-slate-100"
                    title="Se déconnecter"
                >
                    <span class="grid h-8 w-8 place-items-center rounded-full bg-slate-200 text-xs font-semibold text-slate-700">
                        {{ $utilisateur->initiales }}
                    </span>
                    <span class="hidden text-xs leading-tight sm:block">
                        <span class="block font-medium">{{ $utilisateur->nom_complet }}</span>
                        <span class="block text-slate-500">
                            {{ \App\Models\User::ROLES[$utilisateur->getRoleNames()->first()] ?? '' }}
                        </span>
                    </span>
                </button>
            </form>
        @endif
    </div>
</header>

<script>
    function etatDeConnexion() {
        return {
            enLigne: navigator.onLine,
            file: 0,
            init() {
                window.addEventListener('online', () => (this.enLigne = true));
                window.addEventListener('offline', () => (this.enLigne = false));
                window.addEventListener('kelasi:file', (e) => (this.file = e.detail.nombre));
            },
        };
    }
</script>
