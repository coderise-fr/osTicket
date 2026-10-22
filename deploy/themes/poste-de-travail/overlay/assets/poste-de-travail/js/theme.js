/* Theme "Poste de travail" (Coderise) for the osTicket client portal.
 * Keyboard shortcut: "/" moves the focus to the knowledge base search of the
 * top bar, unless the visitor is typing in a field or editor. */
(function () {
    'use strict';
    document.addEventListener('keydown', function (e) {
        if (e.key !== '/' || e.ctrlKey || e.metaKey || e.altKey)
            return;
        var t = e.target;
        if (t && (t.isContentEditable || /^(INPUT|TEXTAREA|SELECT)$/.test(t.tagName)))
            return;
        var q = document.getElementById('gsearch-q');
        if (!q)
            return;
        e.preventDefault();
        q.focus();
        q.select();
    });
})();
