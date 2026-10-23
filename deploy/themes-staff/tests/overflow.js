// Mesure du débordement horizontal du panneau agent/admin à 390, 820 et 1280 px.
//
// Corps d'une fonction asynchrone pour `browser_evaluate` de Playwright, à exécuter
// dans un contexte AUTHENTIFIÉ du panneau (envelopper : async () => { ...corps... }).
// Chaque chemin est chargé dans une iframe cachée de même origine (le panneau envoie
// frame-ancestors 'self'), la page attend `load` + 200 ms, puis scrollWidth est
// comparé à la largeur de la fenêtre. Résultat : la chaîne « aucun débordement » ou
// une table {"<largeur>/<chemin>": <px de débordement>}.
//
// La page de connexion (/scp/login.php) redirige quand on est connecté : la mesurer
// dans un contexte Playwright neuf (non authentifié), pas ici.
const paths = [
    '/scp/index.php',
    '/scp/tickets.php',
    '/scp/tickets.php?a=open',
    '/scp/users.php',
    '/scp/orgs.php',
    '/scp/directory.php',
    '/scp/profile.php',
    '/scp/settings.php',
    '/scp/helptopics.php',
    '/scp/staff.php',
];
const widths = [390, 820, 1280];
const result = {};
const wait = (ms) => new Promise((r) => setTimeout(r, ms));

for (const w of widths) {
    for (const p of paths) {
        const frame = document.createElement('iframe');
        frame.style.cssText = 'position:fixed;left:-99999px;top:0;height:900px;border:0;width:' + w + 'px';
        document.body.appendChild(frame);
        await new Promise((resolve) => {
            frame.addEventListener('load', resolve, { once: true });
            frame.src = p;
        });
        await wait(200);
        try {
            const doc = frame.contentDocument;
            const over = Math.max(
                doc.documentElement.scrollWidth,
                doc.body ? doc.body.scrollWidth : 0
            ) - w;
            if (over > 0) result[w + '/' + p] = over;
        } catch (e) {
            result[w + '/' + p] = 'illisible : ' + e.message;
        }
        frame.remove();
    }
}
return Object.keys(result).length ? result : 'aucun débordement';
