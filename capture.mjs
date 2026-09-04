import { chromium } from 'playwright';

const BASE = 'http://127.0.0.1:8140';
const OUT = '/tmp/claude-0/-home-user-App/09d966e6-f70b-5da9-a23a-207252666547/scratchpad';
const navigateur = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium' });

async function connecter(matricule, viewport) {
    const ctx = await navigateur.newContext({ viewport, deviceScaleFactor: 2 });
    const page = await ctx.newPage();
    await page.goto(`${BASE}/connexion`);
    await page.fill('#matricule', matricule);
    await page.fill('#password', 'kelasi2026');
    await page.click('button[type=submit]');
    await page.waitForLoadState('networkidle');
    return { ctx, page };
}

async function capturer(matricule, chemin, fichier, viewport, avant) {
    const { ctx, page } = await connecter(matricule, viewport);
    await page.goto(`${BASE}${chemin}`);
    await page.waitForLoadState('networkidle');
    if (avant) await avant(page);
    await page.screenshot({ path: `${OUT}/${fichier}` });
    await ctx.close();
}

const bureau = { width: 1280, height: 900 };
const mobile = { width: 390, height: 844 };



await capturer('VDE-001', '/demandes', 'f1-vde-demandes.png', bureau);
await capturer('CP-1', '/activites', 'f2-cp-activites.png', mobile);
await capturer('ENS-1', '/messages', 'f3-ens-messages.png', bureau);
await navigateur.close();
console.log('captures faites');
