/* coderise-agent: ticket page chrome behaviour (tab icons/counters, panel titles, action labels). One workstream owns this file. IIFE, idempotent on ready and pjax:end, no eval, textContent only. */
(function ($) {
    'use strict';
    if (!$) return;

    // Action bar: compact visible labels on the two buttons the mockup
    // labels (Edit, Print). The text is the button's own tooltip, which the
    // core already translates (data-original-title once the tooltip plugin
    // has run, title before). Everything else stays an icon button.
    function labelOf($el) {
        var t = $el.attr('data-original-title') || $el.attr('title') || $el.attr('aria-label') || '';
        return $.trim(t);
    }
    function barLabels() {
        var $bar = $('#content.cdr-ticket .sticky.bar:not(#response_options)');
        if (!$bar.length) return;
        var targets = [
            [$bar.find('a.action-button[href*="a=edit"]'), null],
            [$bar.find('a#ticket-print'), '.action-button']
        ];
        $.each(targets, function (_, t) {
            var $a = t[0].first();
            if (!$a.length || $a.children('.cdr-lbl').length) return;
            var $src = t[1] ? $a.closest(t[1]) : $a,
                text = labelOf($src) || labelOf($a);
            if (!text) return;
            $a.addClass('cdr-has-lbl').append($('<span class="cdr-lbl"></span>').text(text));
        });
    }

    // Tabs: the trailing "(n)" the core prints in a tab label ("Fil du
    // ticket (4)", "Tâches ( 1 )") becomes a .count pill.
    var COUNT = /\s*\(\s*(\d+)\s*\)\s*$/;
    function tabCounts() {
        $('#content.cdr-ticket ul#ticket_tabs a').each(function () {
            var $a = $(this);
            if ($a.children('.count').length) return;
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
        barLabels();
        tabCounts();
        watchTabs();
        replyFoot();
        propsLabels();
        propsTitles();
    }
    $(run);
    $(document).off('pjax:end.cdrchrome').on('pjax:end.cdrchrome', run);
}(window.jQuery));
