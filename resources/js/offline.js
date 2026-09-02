/**
 * La file d'attente hors ligne.
 *
 * Un chef de promotion saisit sa seance dans un amphi ou le reseau ne passe
 * pas. La saisie doit aboutir quand meme : elle part en file locale, et
 * remonte des que la connexion revient.
 *
 * L'uuid est genere ici, sur l'appareil. C'est lui qui rend la remontee
 * rejouable : si l'envoi coupe en cours de route, relancer ne cree pas de
 * doublon -- le serveur reconnait les seances qu'il a deja.
 */

const BASE = 'kelasi';
const VERSION = 1;
const FILE = 'seances_en_attente';

let connexion = null;

function ouvrir() {
    if (connexion) return connexion;

    connexion = new Promise((resoudre, rejeter) => {
        const requete = indexedDB.open(BASE, VERSION);

        requete.onupgradeneeded = () => {
            const base = requete.result;
            if (!base.objectStoreNames.contains(FILE)) {
                base.createObjectStore(FILE, { keyPath: 'uuid' });
            }
        };

        requete.onsuccess = () => resoudre(requete.result);
        requete.onerror = () => rejeter(requete.error);
    });

    return connexion;
}

async function transaction(mode, action) {
    const base = await ouvrir();

    return new Promise((resoudre, rejeter) => {
        const tx = base.transaction(FILE, mode);
        const resultat = action(tx.objectStore(FILE));

        tx.oncomplete = () => resoudre(resultat?.result ?? resultat);
        tx.onerror = () => rejeter(tx.error);
    });
}

export async function mettreEnFile(seance) {
    const enregistrement = {
        ...seance,
        uuid: seance.uuid ?? crypto.randomUUID(),
        saisie_locale_at: new Date().toISOString(),
    };

    await transaction('readwrite', (file) => file.put(enregistrement));
    await annoncerLaFile();

    return enregistrement;
}

export async function fileEnAttente() {
    return transaction('readonly', (file) => file.getAll());
}

async function retirerDeLaFile(uuids) {
    if (!uuids.length) return;

    await transaction('readwrite', (file) => uuids.forEach((uuid) => file.delete(uuid)));
}

/**
 * Remonte la file au serveur.
 *
 * Les seances acceptees et celles que le serveur connaissait deja sortent de
 * la file. Celles qu'il refuse en sortent aussi -- les renvoyer indefiniment
 * ne les rendrait pas plus valides ; elles sont signalees au CP.
 */
export async function synchroniser() {
    if (!navigator.onLine) return null;

    const enAttente = await fileEnAttente();
    if (!enAttente.length) return null;

    const jeton = document.querySelector('meta[name="csrf-token"]')?.content;

    let reponse;
    try {
        reponse = await fetch('/seances/synchroniser', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': jeton ?? '',
            },
            body: JSON.stringify({ seances: enAttente }),
        });
    } catch {
        return null;   // toujours hors ligne : la file reste intacte
    }

    if (!reponse.ok) return null;

    const resultat = await reponse.json();
    const traitees = [
        ...(resultat.acceptees ?? []),
        ...(resultat.ignorees ?? []),
        ...Object.keys(resultat.refusees ?? {}),
    ];

    await retirerDeLaFile(traitees);
    await annoncerLaFile();

    window.dispatchEvent(new CustomEvent('kelasi:synchronise', { detail: resultat }));

    return resultat;
}

/** Previent l'interface du nombre de seances encore en attente. */
async function annoncerLaFile() {
    const enAttente = await fileEnAttente();

    window.dispatchEvent(
        new CustomEvent('kelasi:file', { detail: { nombre: enAttente.length } }),
    );
}

window.Kelasi = { mettreEnFile, fileEnAttente, synchroniser };

window.addEventListener('online', synchroniser);
window.addEventListener('load', () => {
    annoncerLaFile();
    synchroniser();
});
