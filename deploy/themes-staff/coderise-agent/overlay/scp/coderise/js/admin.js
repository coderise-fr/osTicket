/*********************************************************************
    scp/coderise/js/admin.js

    Administration pages of the "coderise-agent" staff panel theme
    (#content.cdr-is-admin). Loaded after coderise.js. Same conventions:
    IIFE, idempotent on ready and pjax:end, no eval, DOM text through
    textContent only, no string of its own (everything shown comes from
    the page itself). Every change is cosmetic and optional: without this
    script the pages are complete, the stylesheet (admin.css) only has
    fewer details to draw.

    - Title: core prints the page title (h2) inside the form on most edit
      pages; it is moved in front of the form, so the title sits on the
      page background above the white panel (nodes moved, not copied).
    - Lists: the cell of the "Status" column (the header link sorting on
      status, never its translated label) is wrapped in span.cdr-st, with
      .cdr-st-on or .cdr-st-off (core prints the off states in bold), so
      the stylesheet can draw an icon next to the text.
    - Agents list: an initials disc before the agent name.
    - Save bar: the height of the tallest pinned bar is published as
      --cdr-savebar-h (the page keeps that room under its last line), measured
      again when a bar changes size, when the window is resized and after a
      tab switch (a bar inside a hidden tab measures 0 until its tab shows);
      the submit button goes last in the button row, so the
      primary action is at the end of the bar (mockup) and the keyboard
      order stays the visual order.

    Released under the GNU General Public License WITHOUT ANY WARRANTY.
    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/
(function ($) {
    'use strict';
    if (!$ || window.cdrAdminBound)
        return;
    window.cdrAdminBound = true;

    var SCOPE = '#content.cdr-is-admin';

    function txt(s) {
        return $.trim(String(s == null ? '' : s).replace(/[\s ]+/g, ' '));
    }

    function initials(name) {
        var parts = txt(name).split(' ').filter(Boolean), first, last;
        if (!parts.length)
            return '';
        first = Array.from(parts[0])[0] || '';
        last = parts.length > 1 ? (Array.from(parts[parts.length - 1])[0] || '') : '';
        return (first + last).toUpperCase();
    }

    // The h2 that core prints as the first child of the form goes before it.
    function title() {
        $(SCOPE).children('form').each(function () {
            var $f = $(this), $h = $f.children('h2').first();
            if ($h.length && !$f.prev('h2').length)
                $f.before($h);
        });
        // core's "&nbsp;<a>Plugins</a>" title: the space is an indent, not a word
        $(SCOPE).children('h2').each(function () {
            var first = this.firstChild;
            if (first && first.nodeType === 3 && /^[\s ]+$/.test(first.nodeValue))
                this.removeChild(first);
        });
    }

    // Index of the column whose header links to a sort on the status.
    function statusColumn($table) {
        var $ths = $table.find('> thead > tr > th'),
            $th = $ths.filter(function () {
                return $(this).find('a[href*="sort=status"]').length > 0;
            }).first();
        return $th.length ? $ths.index($th) : -1;
    }

    // Not on the departments list: core compares the translated status with the English
    // word there, so outside English the active departments are the ones printed in bold.
    function statuses() {
        $(SCOPE).not('.cdr-departments').find('table.list').each(function () {
            var $t = $(this), idx = statusColumn($t);
            if (idx < 0)
                return;
            $t.find('> tbody > tr').each(function () {
                var $td = $(this).children('td').eq(idx), off, first, $s;
                if (!$td.length || $td.children('.cdr-st').length || !txt($td.text()))
                    return;
                off = $td.find('b, strong').length > 0;
                $s = $('<span class="cdr-st"></span>').addClass(off ? 'cdr-st-off' : 'cdr-st-on');
                first = $td[0].firstChild;
                if (first && first.nodeType === 3)
                    first.nodeValue = first.nodeValue.replace(/^[\s ]+/, '');
                $s.append($td.contents());
                $td.append($s);
            });
        });
    }

    function agents() {
        $(SCOPE + '.cdr-staff').find('table.list').each(function () {
            var $t = $(this), $ths = $t.find('> thead > tr > th'),
                idx = $ths.index($ths.filter(function () {
                    return $(this).find('a[href*="sort=name"]').length > 0;
                }).first());
            if (idx < 0)
                return;
            $t.find('> tbody > tr').each(function () {
                var $td = $(this).children('td').eq(idx), $a = $td.children('a').first(), $who;
                if (!$a.length || $td.children('.cdr-who').length)
                    return;
                $who = $('<span class="cdr-who"></span>');
                $('<span class="cdr-av" aria-hidden="true"></span>').text(initials($a.text())).appendTo($who);
                $('<span class="cdr-name"></span>').appendTo($who).append($a);
                $td.empty().append($who);
            });
        });
    }

    // The button row (last <p> of the form, or of a wrapper div) holding the submit.
    function saveButtons() {
        $(SCOPE + ' form.save').each(function () {
            var $f = $(this);
            $f.children('p').add($f.children('div').children('p')).each(function () {
                var $p = $(this), $sub = $p.children('input[type=submit], button[type=submit]').first();
                if ($sub.length && !$sub.is($p.children('input, button').last()))
                    $p.append($sub);
            });
        });
    }

    // The save bar is pinned to the window (admin.css): its height, which grows when the
    // buttons wrap on a narrow screen, is published as --cdr-savebar-h so the page keeps
    // that much room under its last line. Every row admin.css pins (position: fixed) is
    // measured: pinned bars share the bottom of the window, so the room is the tallest
    // visible one (a bar in a hidden tab measures 0).
    var barWatch = null, barEls = [];
    function barHeight() {
        var h = 0, i;
        for (i = 0; i < barEls.length; i++)
            h = Math.max(h, barEls[i].offsetHeight);
        if (barEls.length && document.documentElement)
            document.documentElement.style.setProperty('--cdr-savebar-h', h + 'px');
    }
    function saveBar() {
        if (barWatch) {
            barWatch.disconnect();
            barWatch = null;
        }
        barEls = $(SCOPE + ' form.save > p, ' + SCOPE + ' form.save > div > p').filter(function () {
            return $(this).children('input[type=submit], button[type=submit]').length > 0
                && $(this).css('position') === 'fixed';
        }).get();
        if (!barEls.length)
            return;
        barHeight();
        if (window.ResizeObserver) {
            barWatch = new window.ResizeObserver(barHeight);
            barEls.forEach(function (el) { barWatch.observe(el); });
        }
    }
    $(window).on('resize.cdradmin', barHeight);
    // core's tabs (ul.tabs) show and hide panels on click: measure once the panel is shown
    // (the ResizeObserver also sees it; this is for a bar whose size does not change)
    $(document).on('click.cdradmin', SCOPE + ' ul.tabs a', function () {
        window.setTimeout(barHeight, 0);
    });

    function run() {
        title();
        statuses();
        agents();
        saveButtons();
        saveBar();
    }

    $(run);
    $(document).off('pjax:end.cdradmin').on('pjax:end.cdradmin', run);
})(window.jQuery);
