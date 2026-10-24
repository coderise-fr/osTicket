// Mesure du débordement horizontal du panneau agent/admin à 390, 820 et 1280 px.
//
// Corps d'une fonction asynchrone pour `browser_evaluate` de Playwright, à exécuter
// dans un contexte AUTHENTIFIÉ du panneau (envelopper : async () => { ...corps... }).
// Chaque chemin est chargé dans une iframe cachée de même origine (le panneau envoie
// frame-ancestors 'self'), la page attend `load` (15 s au plus) + 400 ms, puis
// scrollWidth est comparé à la largeur utile (clientWidth, barre de défilement
// déduite : un débordement plus petit que la barre est aussi compté). Résultat : la
// chaîne « aucun débordement » ou une table {"<largeur>/<chemin>": <px de débordement>}.
// Un agent non administrateur est redirigé vers index.php pour les pages
// d'administration : la mesure porte alors sur la page d'arrivée.
//
// La page de connexion (/scp/login.php) redirige quand on est connecté : la mesurer
// dans un contexte Playwright neuf (non authentifié), pas ici.
const paths = [
    '/scp/index.php',
    '/scp/tickets.php',
    '/scp/tickets.php?id=1',
    '/scp/dashboard.php',
    '/scp/users.php',
    '/scp/orgs.php',
    '/scp/tasks.php',
    '/scp/kb.php',
    '/scp/profile.php',
    '/scp/admin.php',
    '/scp/settings.php?t=tickets',
    '/scp/staff.php',
    '/scp/helptopics.php',
    '/scp/plugins.php',
    '/scp/logs.php',
];
const widths = [390, 820, 1280];
const result = {};
const wait = (ms) => new Promise((r) => setTimeout(r, ms));

for (const w of widths) {
    for (const p of paths) {
        const frame = document.createElement('iframe');
        frame.style.cssText = 'position:fixed;left:-99999px;top:0;height:900px;border:0;width:' + w + 'px';
        document.body.appendChild(frame);
        await Promise.race([
            new Promise((resolve) => {
                frame.addEventListener('load', resolve, { once: true });
                frame.src = p;
            }),
            wait(15000),
        ]);
        await wait(400);
        try {
            const doc = frame.contentDocument;
            const over = Math.max(
                doc.documentElement.scrollWidth,
                doc.body ? doc.body.scrollWidth : 0
            ) - doc.documentElement.clientWidth;
            if (over > 0) result[w + '/' + p] = over;
        } catch (e) {
            result[w + '/' + p] = 'illisible : ' + e.message;
        }
        frame.remove();
    }
}
return Object.keys(result).length ? result : 'aucun débordement';
