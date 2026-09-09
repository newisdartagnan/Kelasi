{{-- L'invite d'installation.

     Un chef de promotion saisit ses séances entre deux amphis, debout, sur son
     téléphone. Passer par le navigateur à chaque fois -- ouvrir Chrome, taper
     l'adresse, attendre -- suffit à décourager la saisie du jour même, et une
     séance saisie le surlendemain n'est plus une séance saisie de mémoire.

     Posée sur l'écran d'accueil, l'application s'ouvre d'un geste, garde la
     session, fonctionne hors ligne et peut recevoir les rappels du matin.
     D'où cette bande : elle ne s'affiche que là où l'installation est
     réellement possible, et une seule fois si on l'écarte. --}}
<div
    x-data="inviteDInstallation()"
    x-show="visible"
    x-cloak
    x-transition
    class="mb-4 rounded-xl border border-kelasi-200 bg-kelasi-50 px-4 py-3"
>
    <div class="flex items-start gap-3">
        <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-kelasi-600 text-lg font-bold text-white">
            K
        </span>

        <div class="min-w-0 flex-1">
            <p class="text-sm font-semibold text-kelasi-900">Installer Kelasi sur ce téléphone</p>

            {{-- Android : le navigateur fait le travail, il suffit d'un bouton. --}}
            <template x-if="etat === 'possible'">
                <div>
                    <p class="mt-0.5 text-sm text-slate-600">
                        L'application s'ouvre alors d'un seul geste, sans barre d'adresse, et
                        reste utilisable hors connexion.
                    </p>

                    <div class="mt-2.5 flex flex-wrap items-center gap-2">
                        <button
                            type="button"
                            x-on:click="installer()"
                            class="rounded-lg bg-kelasi-600 px-3.5 py-2 text-sm font-medium text-white transition hover:bg-kelasi-700"
                        >
                            Installer
                        </button>
                        <button
                            type="button"
                            x-on:click="ecarter()"
                            class="rounded-lg px-3 py-2 text-sm font-medium text-slate-600 transition hover:bg-white"
                        >
                            Plus tard
                        </button>
                    </div>
                </div>
            </template>

            {{-- iOS : aucun événement d'installation n'existe. Safari n'offre que
                 le menu de partage, et rien ne peut l'ouvrir à sa place. On
                 décrit donc les deux gestes, dans l'ordre. --}}
            <template x-if="etat === 'mode-emploi'">
                <div>
                    <p class="mt-0.5 text-sm text-slate-600">
                        Sur iPhone, l'installation se fait à la main, depuis Safari :
                    </p>

                    {{-- Le pictogramme est celui de la barre de Safari : c'est lui
                         qu'on cherche des yeux, pas le mot. Il reste dans le fil du
                         texte pour que la phrase se coupe proprement sur un écran
                         étroit. --}}
                    <ol class="mt-2 space-y-1.5 text-sm text-slate-700">
                        <li class="flex gap-2">
                            <span class="mt-0.5 grid h-5 w-5 shrink-0 place-items-center rounded-full bg-white text-[11px] font-semibold text-kelasi-700">1</span>
                            <span>
                                Toucher
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="inline-block h-4 w-4 -translate-y-px align-middle text-kelasi-700" aria-hidden="true">
                                    <path d="M12 15V3" />
                                    <path d="m8 7 4-4 4 4" />
                                    <path d="M6 11H5a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-6a2 2 0 0 0-2-2h-1" />
                                </svg>
                                <span class="font-semibold">Partager</span>, en bas de l'écran
                            </span>
                        </li>
                        <li class="flex gap-2">
                            <span class="mt-0.5 grid h-5 w-5 shrink-0 place-items-center rounded-full bg-white text-[11px] font-semibold text-kelasi-700">2</span>
                            <span>Choisir <span class="font-semibold">&laquo;&nbsp;Sur l'écran d'accueil&nbsp;&raquo;</span></span>
                        </li>
                    </ol>

                    <button
                        type="button"
                        x-on:click="ecarter()"
                        class="mt-3 rounded-lg border border-kelasi-200 bg-white px-3.5 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                    >
                        J'ai compris
                    </button>
                </div>
            </template>
        </div>
    </div>
</div>

<script>
    function inviteDInstallation() {
        return {
            etat: 'impossible',

            get visible() {
                return this.etat === 'possible' || this.etat === 'mode-emploi';
            },

            init() {
                this.relire();

                // Android annonce l'application installable après le chargement,
                // parfois plusieurs secondes plus tard : la bande apparaît quand
                // le navigateur le dit, pas avant.
                window.addEventListener('kelasi:installation', (e) => (this.etat = e.detail.etat));
            },

            relire() {
                this.etat = window.KelasiInstallation?.etatDeLInstallation() ?? 'impossible';
            },

            installer() {
                window.KelasiInstallation?.proposerLInstallation();
            },

            ecarter() {
                window.KelasiInstallation?.ecarterLInvite();
                this.etat = 'ecartee';
            },
        };
    }
</script>
