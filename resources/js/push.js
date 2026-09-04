/**
 * L'abonnement au push web.
 *
 * Sur Android, l'autorisation se demande et la notification arrive comme
 * celle d'une application native. Sur iOS, rien ne fonctionne tant que la
 * page n'a pas été ajoutée à l'écran d'accueil : ce n'est pas une panne, et
 * l'interface doit le dire plutôt que de laisser l'utilisateur réessayer.
 */

/** Vrai quand la page tourne comme application installée. */
export function estInstallee() {
    return (
        window.matchMedia('(display-mode: standalone)').matches ||
        window.navigator.standalone === true
    );
}

export function estIOS() {
    return /iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream;
}

/**
 * Ce que l'appareil permet aujourd'hui. L'interface s'en sert pour afficher
 * le bon message plutôt qu'un bouton qui échouerait.
 */
export function etatDuPush() {
    if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
        return estIOS() && !estInstallee() ? 'ios-a-installer' : 'indisponible';
    }

    if (Notification.permission === 'denied') return 'refuse';
    if (Notification.permission === 'granted') return 'accorde';

    return 'a-demander';
}

function versTableauOctets(base64) {
    const complement = '='.repeat((4 - (base64.length % 4)) % 4);
    const normalise = (base64 + complement).replace(/-/g, '+').replace(/_/g, '/');
    const binaire = atob(normalise);

    return Uint8Array.from([...binaire].map((c) => c.charCodeAt(0)));
}

async function envoyer(url, corps) {
    const jeton = document.querySelector('meta[name="csrf-token"]')?.content;

    return fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN': jeton ?? '',
        },
        body: JSON.stringify(corps),
    });
}

export async function activerLePush(clePublique) {
    if (!clePublique) return { ok: false, raison: 'non-configure' };

    const permission = await Notification.requestPermission();

    if (permission !== 'granted') return { ok: false, raison: 'refuse' };

    const worker = await navigator.serviceWorker.ready;

    const abonnement =
        (await worker.pushManager.getSubscription()) ??
        (await worker.pushManager.subscribe({
            userVisibleOnly: true,
            applicationServerKey: versTableauOctets(clePublique),
        }));

    const { endpoint, keys } = abonnement.toJSON();

    const reponse = await envoyer('/notifications/abonnement', {
        endpoint,
        cles: keys,
        appareil: navigator.userAgent.slice(0, 200),
    });

    return { ok: reponse.ok, raison: reponse.ok ? null : 'serveur' };
}

export async function desactiverLePush() {
    const worker = await navigator.serviceWorker.ready;
    const abonnement = await worker.pushManager.getSubscription();

    if (!abonnement) return { ok: true };

    const { endpoint } = abonnement.toJSON();

    await abonnement.unsubscribe();
    await envoyer('/notifications/abonnement/retrait', { endpoint });

    return { ok: true };
}

window.KelasiPush = { etatDuPush, activerLePush, desactiverLePush, estInstallee, estIOS };
