// Contrôle des éditeurs Redactor de la page courante (thème coderise-agent).
//
// Expression de fonction pour `browser_evaluate` de Playwright (coller telle quelle
// dans le paramètre `function`, ou `page.evaluate(<contenu du fichier>)`), sur une
// page du panneau où un ou plusieurs éditeurs sont visibles, de préférence après
// avoir tapé du texte (plusieurs paragraphes, gras, liste, titre, italique).
// Pour chaque .redactor-box visible, vérifie que les règles du thème n'atteignent
// pas le contenu saisi :
//   - zone éditable : pas de marge automatique ni de max-width (largeur = largeur
//     intérieure de la boîte, bord gauche = bord intérieur de la boîte), sauf largeur
//     fixe voulue par le cœur (textarea data-width : FAQ, pages) et plein écran
//     (colonne centrée de Redactor, non contrôlée) ;
//   - chaque <p> (et titre h1-h6) enfant direct : décalage gauche = padding de la
//     zone éditable, display block, text-align start/left (sauf alignement saisi
//     par l'utilisateur en style inline), largeur = largeur utile ;
//   - titres : pas la police de titre du thème (Montserrat) ; <em>/<i> : même
//     couleur que leur parent ; <hr>, <th> : pas de fond gris du thème ;
//   - vue source (textarea.redactor-source) : police à chasse fixe, largeur de la
//     boîte ;
//   - barre d'outils : largeur de la boîte.
// Résultat : { ok: <bool>, editors: [{ id, ok, fails: [...], m: {mesures} }] }.
() => {
    const near = (a, b, tol) => Math.abs(a - b) <= (tol === undefined ? 1 : tol);
    const visible = (el) => !!el && el.getClientRects().length > 0
        && getComputedStyle(el).visibility !== 'hidden';
    const out = [];
    document.querySelectorAll('.redactor-box').forEach((box, i) => {
        if (!visible(box))
            return;
        const ed = box.querySelector('.redactor-in');
        // Redactor keeps the original textarea in the box as textarea.redactor-source
        // (hidden, shown in source view)
        const ta = box.querySelector('textarea');
        const id = (ta && (ta.name || ta.id)) || ('editor-' + i);
        const fails = [];
        const m = {};
        const br = box.getBoundingClientRect();
        const innerLeft = br.left + box.clientLeft;
        const src = box.querySelector('textarea.redactor-source');
        if (src && visible(src)) {
            const s = getComputedStyle(src);
            m.source = { font: s.fontFamily, width: Math.round(src.getBoundingClientRect().width), bg: s.backgroundColor };
            if (!/mono|consolas|menlo|courier/i.test(s.fontFamily))
                fails.push('source view font is not monospace: ' + s.fontFamily);
            if (!near(src.getBoundingClientRect().width, box.clientWidth, 2))
                fails.push('source view width ' + Math.round(src.getBoundingClientRect().width) + ' != box ' + box.clientWidth);
        }
        if (!ed || !visible(ed)) {
            out.push({ id, ok: !fails.length, fails, m, note: 'editable area hidden (source view?)' });
            return;
        }
        const cs = getComputedStyle(ed);
        const er = ed.getBoundingClientRect();
        const padL = parseFloat(cs.paddingLeft), padR = parseFloat(cs.paddingRight);
        const usable = ed.clientWidth - padL - padR;
        m.box = Math.round(box.clientWidth);
        m.editor = Math.round(er.width);
        m.editorLeft = Math.round(er.left - innerLeft);
        m.padding = padL;
        m.marginLeft = cs.marginLeft;
        m.maxWidth = cs.maxWidth;
        // Core gives some editors a fixed width on purpose (FAQ answer, pages:
        // textarea data-width, the width of the client portal's article column)
        const fixedW = ta && parseFloat(ta.getAttribute('data-width') || '');
        m.dataWidth = fixedW || null;
        // Fullscreen (box moved to <body>): Redactor's centred 950 px column is kept
        m.fullscreen = box.classList.contains('redactor-box-fullscreen');
        if (m.fullscreen)
            m.note = 'fullscreen: width and offset not checked';
        else if (fixedW ?!near(er.width, Math.min(fixedW, box.clientWidth)) : !near(er.width, box.clientWidth))
            fails.push('editable width ' + Math.round(er.width) + ' != ' + (fixedW ? 'data-width ' + fixedW : 'box inner width ' + box.clientWidth));
        if (!m.fullscreen && !near(er.left, innerLeft))
            fails.push('editable area offset ' + Math.round(er.left - innerLeft) + 'px from the box edge');
        const tb = box.querySelector('.redactor-toolbar');
        if (tb && visible(tb) && !near(tb.getBoundingClientRect().width, box.clientWidth, 2))
            fails.push('toolbar width ' + Math.round(tb.getBoundingClientRect().width) + ' != box ' + box.clientWidth);
        const blocks = Array.from(ed.children).filter((c) => /^(P|H[1-6])$/.test(c.tagName));
        m.blocks = [];
        blocks.forEach((b, k) => {
            const s = getComputedStyle(b);
            const r = b.getBoundingClientRect();
            const left = Math.round((r.left - er.left - ed.clientLeft) * 10) / 10;
            const userAlign = /text-align/i.test(b.getAttribute('style') || '');
            const info = { tag: b.tagName, left, align: s.textAlign, display: s.display, width: Math.round(r.width) };
            if (k === 0 || k === blocks.length - 1 || /^H/.test(b.tagName))
                m.blocks.push(info);
            const where = b.tagName + '#' + k + ' ';
            if (!near(left, padL + parseFloat(s.marginLeft)) || parseFloat(s.marginLeft) > 0)
                fails.push(where + 'left offset ' + left + ' != padding ' + padL);
            if (s.display !== 'block')
                fails.push(where + 'display ' + s.display);
            if (!userAlign && !/^(start|left)$/.test(s.textAlign))
                fails.push(where + 'text-align ' + s.textAlign);
            if (!near(r.width, usable, 2))
                fails.push(where + 'width ' + Math.round(r.width) + ' != usable ' + Math.round(usable));
            if (/^H/.test(b.tagName) && /montserrat/i.test(s.fontFamily))
                fails.push(where + 'heading uses the theme title font');
        });
        ed.querySelectorAll('em, i').forEach((e) => {
            if (getComputedStyle(e).color !== getComputedStyle(e.parentElement).color)
                fails.push(e.tagName + ' colour ' + getComputedStyle(e).color + ' differs from its parent');
        });
        ed.querySelectorAll('hr, th').forEach((e) => {
            const bg = getComputedStyle(e).backgroundColor;
            if (bg === 'rgb(232, 236, 241)')
                fails.push(e.tagName + ' gets the theme gris background');
        });
        out.push({ id, ok: !fails.length, fails, m });
    });
    return { ok: out.length > 0 && out.every((e) => e.ok), width: window.innerWidth, editors: out };
}
