/* coderise-agent: ticket page chrome behaviour (tab icons/counters, panel titles, action labels). One workstream owns this file. IIFE, idempotent on ready and pjax:end, no eval, textContent only. */
(function ($) {
    'use strict';
    if (!$) return;

    // Identity block (section.cdr-idline, printed by header.inc.php through
    // the object.view signal, before the action bar): the subject (core
    // .tixTitle) goes right after the identity line and the client
    // subtitle after the subject, so the order is the mockup's: identity,
    // subject, subtitle, then the action bar. Theme node and a core title
    // without handlers are moved; nothing is copied. Without this script
    // the subtitle stays under the identity line.
    function identity() {
        var $c = $('#content.cdr-ticket'),
            $idl = $c.children('section.cdr-idline').first(),
            $title = $c.children('.tixTitle').first();
        if (!$idl.length || !$title.length) return;
        if ($idl.next()[0] !== $title[0]) $idl.after($title);
        var $sub = $idl.children('p.cdr-sub');
        if ($sub.length) $title.after($sub);
    }

    // Action bar: compact visible labels on the two buttons the mockup
    // labels (Edit, Print), found by their stable markers (edit href, print
    // id), never by text. The text is the theme's (data-cdr-label-edit,
    // data-cdr-label-print on #content: "Modifier", "Imprimer"), else the
    // button's own core tooltip. Everything else stays an icon button.
    function labelOf($el) {
        var t = $el.attr('data-original-title') || $el.attr('title') || $el.attr('aria-label') || '';
        return $.trim(t);
    }
    function barOf($c) {
        return $c.find('.sticky.bar:not(#response_options)').first();
    }
    function barLabels() {
        var $c = $('#content.cdr-ticket'), $bar = barOf($c);
        if (!$bar.length) return;
        var targets = [
            [$bar.find('a.action-button[href*="a=edit"]'), null, 'data-cdr-label-edit'],
            [$bar.find('a#ticket-print'), '.action-button', 'data-cdr-label-print']
        ];
        $.each(targets, function (_, t) {
            var $a = t[0].first();
            if (!$a.length || $a.children('.cdr-lbl').length) return;
            var $src = t[1] ? $a.closest(t[1]) : $a,
                text = $.trim($c.attr(t[2]) || '') || labelOf($src) || labelOf($a);
            if (!text) return;
            $a.addClass('cdr-has-lbl').append($('<span class="cdr-lbl"></span>').text(text));
            // the accessible name holds the visible label (core's aria-label
            // on the print link would hide it)
            if ($a.is('[aria-label]')) $a.attr('aria-label', text);
        });
    }

    // "Mark as resolved" (mockup primary button): only when header.inc.php
    // emitted its label (open ticket) and core's status menu holds a
    // "closed" entry for this agent (core prints the menu only with the
    // close permission, in its own sort order: the first closed status,
    // "Resolved" on a stock install). The button forwards the click to that
    // core link: core's own handler opens core's status dialog (comment,
    // confirmation, permission checks on the server). Its title is the
    // status name the link shows.
    function closeLink($c) {
        return $c.find('#action-dropdown-statuses a.ticket-action[href*="/status/close/"]').first();
    }
    function resolveButton() {
        var $c = $('#content.cdr-ticket'), $bar = barOf($c),
            label = $.trim($c.attr('data-cdr-label-resolve') || ''),
            $box = $bar.find('.content > .pull-right.flush-right').first();
        if (!label || !$box.length || $box.children('.cdr-resolve').length) return;
        var $link = closeLink($c);
        if (!$link.length) return;
        var $btn = $('<button type="button" class="btn btn-primary cdr-resolve"><i class="icon-ok-circle"></i></button>');
        $btn.append($('<span></span>').text(label)).attr('title', $.trim($link.text()));
        $btn.on('click', function () {
            var $l = closeLink($('#content.cdr-ticket'));
            if ($l.length) $l[0].click();
        });
        $box.append($btn);
    }

    // Action bar order (mockup .t-actions: Edit, Print, Mark as resolved,
    // then the other core actions): the buttons are moved inside their own
    // container (ids, data-dropdown targets and handlers are kept), so the
    // focus order is the visual one. Unknown items (plugins) stay after them.
    var ORDER = ['a.action-button[href*="a=edit"]', '.action-button:has(#ticket-print)', '.cdr-resolve',
        'a.post-response[href="#post-reply"]', 'a#post-note', '[data-dropdown="#action-dropdown-statuses"]',
        '[data-dropdown="#action-dropdown-assign"]', 'a#ticket-transfer', '[data-dropdown="#action-dropdown-more"]'];
    function barOrder() {
        var $c = $('#content.cdr-ticket');
        if (!$c.children('section.cdr-idline').length) return;
        var $box = barOf($c).find('.content > .pull-right.flush-right').first();
        if (!$box.length || $box.hasClass('cdr-ordered')) return;
        var moved = [];
        $.each(ORDER, function (_, sel) {
            var $el = $box.children().filter(sel).first();
            if ($el.length) moved.push($el[0]);
        });
        $box.prepend(moved).addClass('cdr-ordered');
    }

    // Tabs: the trailing "(n)" the core prints in a tab label ("Fil du
    // ticket (4)", "Tâches ( 1 )") becomes a .count pill.
    var COUNT = /\s*\(\s*(\d+)\s*\)\s*$/;
    function tabCounts() {
        $('#content.cdr-ticket ul#ticket_tabs a').each(function () {
            var $a = $(this);
            if ($a.children('.count').length) return;
            // "Tâches (<span id=ticket-tasks-count>1</span>)": core updates
            // that span, so it becomes the pill itself; only the brackets go.
            var $n = $a.children('span[id$="-count"]').first();
            if ($n.length) {
                var prev = $n[0].previousSibling, next = $n[0].nextSibling;
                if (prev && prev.nodeType === 3) prev.nodeValue = prev.nodeValue.replace(/[\s ]*\(\s*$/, '');
                if (next && next.nodeType === 3) next.nodeValue = next.nodeValue.replace(/^\s*\)/, '');
                $n.addClass('count');
                return;
            }
            var node = null;
            $.each(this.childNodes, function () { if (this.nodeType === 3 && COUNT.test(this.nodeValue)) node = this; });
            if (!node) return;
            var n = COUNT.exec(node.nodeValue)[1];
            node.nodeValue = node.nodeValue.replace(COUNT, '');
            $a.append($('<span class="count"></span>').text(n));
        });
    }
    // The core rewrites a tab label when its tasks load or change: redo it
    var tabObserver = null;
    function watchTabs() {
        var el = document.getElementById('ticket_tabs');
        if (tabObserver) { tabObserver.disconnect(); tabObserver = null; }
        if (!el || !window.MutationObserver) return;
        tabObserver = new MutationObserver(function () { tabCounts(); });
        tabObserver.observe(el, { childList: true, characterData: true, subtree: true });
    }

    // Reply and note forms: the ticket state (label + select, in a table
    // row of its own in the core) moves into the button paragraph, so that
    // it sits on the buttons' line like the mockup. The nodes are moved, not
    // copied: names, ids and scp.js lookups by name keep working.
    function replyFoot() {
        $('#content.cdr-ticket #response_options > form').each(function () {
            var $f = $(this);
            if ($f.find('.cdr-state').length) return;
            var $sel = $f.find('select[name=reply_status_id], select[name=note_status_id]').first(),
                $p = $f.children('p').filter(function () { return $(this).find('input[type=submit]').length; }).last();
            if (!$sel.length || !$p.length) return;
            var $row = $sel.closest('tr'), $cells = $row.children('td');
            if ($cells.length !== 2) return;
            var $box = $('<span class="cdr-state"></span>');
            $box.append($cells.eq(0).contents(), $cells.eq(1).contents());
            $row.addClass('cdr-moved');
            $p.addClass('cdr-foot').prepend($box);
        });
    }

    // Right column: the core's trailing ":" of the property names is dropped
    // (text nodes only). Panel titles come from data attributes of #content
    // when the page emits them (data-cdr-title-props, data-cdr-title-client);
    // none is hard-coded here.
    function propsLabels() {
        $('#content.cdr-ticket aside.cdr-props table.ticket_info th').each(function () {
            var last = this.lastChild;
            while (last && last.nodeType === 3 && !$.trim(last.nodeValue)) last = last.previousSibling;
            if (last && last.nodeType === 3 && /[:\uFF1A]\s*$/.test(last.nodeValue))
                last.nodeValue = last.nodeValue.replace(/\s*[:\uFF1A]\s*$/, '');
        });
    }
    function propsTitles() {
        var $c = $('#content.cdr-ticket'), titles = [$c.attr('data-cdr-title-props'), $c.attr('data-cdr-title-client')];
        var $t = $c.find('aside.cdr-props > table.ticket_info:not(.custom-data)').first();
        if (!$t.length) return;
        $t.children('tbody').children('tr').first().children('td').each(function (i) {
            var $td = $(this);
            if (!titles[i] || $td.children('.cdr-title').length) return;
            $td.prepend($('<h2 class="cdr-title"></h2>').text(titles[i]));
        });
    }

    function run() {
        identity();
        barLabels();
        resolveButton();
        barOrder();
        tabCounts();
        watchTabs();
        replyFoot();
        propsLabels();
        propsTitles();
    }
    $(run);
    $(document).off('pjax:end.cdrchrome').on('pjax:end.cdrchrome', run);
}(window.jQuery));
