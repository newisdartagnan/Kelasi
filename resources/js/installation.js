/**
 * L'installation sur l'écran d'accueil.
 *
 * Kelasi est une application web installable : posée sur l'écran d'accueil,
 * elle s'ouvre en plein écran, sans barre d'adresse, et se comporte comme
 * n'importe quelle autre application du téléphone. Encore faut-il que
 * l'utilisateur sache que c'est possible.
 *
 * Les deux familles de téléphones ne s'installent pas de la même façon :
 *
 *   - Android propose un événement, `beforeinstallprompt`, que le navigateur
 *     déclenche quand il juge l'application installable. On l'intercepte pour
 *     choisir NOUS-MÊMES le moment de la proposition -- sinon Chrome affiche
 *     sa propre bannière au hasard d'une visite, souvent ignorée ;
 *   - iOS n'a pas d'équivalent. Rien ne se déclenche, rien ne se demande :
 *     l'utilisateur doit passer par « Partager », puis « Sur l'écran
 *     d'accueil ». La seule chose que nous puissions faire est le lui dire.
 *
 * Le refus est retenu : une proposition écartée ne revient pas avant un mois.
 * Un chef de promotion qui a dit non deux fois ne dira pas oui la troisième.
 */

import { estInstallee, estIOS } from './push';

const CLE_REFUS = 'kelasi.installation.ecartee';
const DELAI_AVANT_NOUVELLE_PROPOSITION = 30 * 24 * 60 * 60 * 1000;

/** L'événement d'installation d'Android, mis de côté en attendant un clic. */
let inviteAndroid = null;

/**
 * Le navigateur ne laisse déclencher l'invite qu'une fois par événement.
 * Après un refus, il en émettra un nouveau à la visite suivante ; d'ici là,
 * le bouton n'a plus rien à déclencher et doit disparaître.
 */
window.addEventListener('beforeinstallprompt', (evenement) => {
    evenement.preventDefault();
    inviteAndroid = evenement;
    prevenirLInterface();
});

window.addEventListener('appinstalled', () => {
    inviteAndroid = null;
    oublierLeRefus();
    prevenirLInterface();
});

function aEteEcartee() {
    try {
        const quand = Number(window.localStorage.getItem(CLE_REFUS));

        return Number.isFinite(quand) && quand > 0
            ? Date.now() - quand < DELAI_AVANT_NOUVELLE_PROPOSITION
            : false;
    } catch {
        // Navigation privée, stockage refusé : on propose, quitte à insister.
        return false;
    }
}

export function ecarterLInvite() {
    try {
        window.localStorage.setItem(CLE_REFUS, String(Date.now()));
    } catch {
        // Sans stockage, l'invite reviendra au prochain chargement. Tant pis.
    }

    prevenirLInterface();
}

function oublierLeRefus() {
    try {
        window.localStorage.removeItem(CLE_REFUS);
    } catch {
        // Rien à faire : l'absence de stockage ne casse rien ici.
    }
}

/**
 * Ce que l'appareil permet en matière d'installation.
 *
 *   installee   -- déjà sur l'écran d'accueil, il n'y a rien à proposer ;
 *   possible    -- Android a signalé l'application installable ;
 *   mode-emploi -- iOS : à faire à la main, on explique comment ;
 *   ecartee     -- l'utilisateur a dit non, on se tait pour un mois ;
 *   impossible  -- navigateur qui n'installe pas, ou site servi en HTTP.
 */
export function etatDeLInstallation() {
    if (estInstallee()) return 'installee';
    if (aEteEcartee()) return 'ecartee';
    if (inviteAndroid) return 'possible';
    if (estIOS()) return 'mode-emploi';

    return 'impossible';
}

/**
 * Déclenche l'invite native d'Android.
 *
 * Le résultat n'est pas « installé ou non » mais « accepté ou refusé » :
 * l'installation elle-même se confirme par l'événement `appinstalled`.
 */
export async function proposerLInstallation() {
    if (!inviteAndroid) return { ok: false, raison: 'indisponible' };

    const invite = inviteAndroid;
    inviteAndroid = null;

    invite.prompt();

    const { outcome } = await invite.userChoice;

    if (outcome !== 'accepted') ecarterLInvite();

    prevenirLInterface();

    return { ok: outcome === 'accepted', raison: outcome };
}

function prevenirLInterface() {
    window.dispatchEvent(
        new CustomEvent('kelasi:installation', { detail: { etat: etatDeLInstallation() } }),
    );
}

window.KelasiInstallation = {
    etatDeLInstallation,
    proposerLInstallation,
    ecarterLInvite,
    estInstallee,
    estIOS,
};
