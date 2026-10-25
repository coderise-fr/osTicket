/*********************************************************************
    scp/coderise/js/queue.js

    Queue page behaviour of the "coderise-agent" staff panel theme
    (/scp/tickets.php, table.queue). Loaded after coderise.js. Same
    conventions: IIFE, idempotent on ready and pjax:end, no eval, DOM text
    through textContent only, every string comes from the page itself
    (core translated it): nothing is hard-coded here.

    - Title block before #content: h1 with the queue name, the "Visible
      1 - 25 of about N" text as subtitle, the real refresh and export links
      moved into a button group (core handlers are delegated, so they work
      from there).
    - Bulk bar: labels from each action's tooltip text, a selection counter,
      a claim button cloned from the Assign menu (same delegated handler).
    - Header check box wired to core's #selectAll / #selectNone.
    - Assignee cell: initials disc + name.
    - The core autofocus of the quick search is dropped on load.

    Released under the GNU General Public License WITHOUT ANY WARRANTY.
    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/
(function ($) {
    'use strict';
    if (!$ || window.cdrQueueBound)
        return;
    window.cdrQueueBound = true;

    var userActed = false;

    function txt(s) {
        return $.trim(String(s == null ? '' : s).replace(/\s+/g, ' '));
    }

    // Decorative inner nodes never change the accessible name, so the label
    // is the tooltip text core already translated.
    function labelOf($el) {
        return txt($el.attr('data-original-title') || $el.attr('title')
            || $el.attr('aria-label') || '');
    }

    // Translated labels emitted by header.inc.php on #content (data-cdr-*).
    function label(key) {
        return txt($('#content.cdr-tickets').attr('data-cdr-' + key));
    }

    // "No ticket selected" / "1 ticket selected" / "N tickets selected".
    function selText(n) {
        var zero = label('sel-zero'), one = label('sel-one'), many = label('sel-many');
        if (!many)
            return '';
        if (n === 0 && zero)
            return zero;
        return (n === 1 && one ? one : many).replace('%d', String(n));
    }

    function initials(name) {
        var parts = txt(name).split(' ').filter(Boolean), first, last;
        if (!parts.length)
            return '';
        first = Array.from(parts[0])[0] || '';
        last = parts.length > 1 ? (Array.from(parts[parts.length - 1])[0] || '') : '';
        return (first + last).toUpperCase();
    }

    function pageText($content, $form) {
        var $f = $form.children('div').has('.faded').last();
        return $f.length ? txt($f.children('.faded').first().text()) : '';
    }

    // ───────── Title block, A9 ─────────
    function buildTitle($content, $form) {
        var $bar = $content.find('.sticky.bar').has('.configureQ').first(),
            $h2 = $bar.find('h2').first(),
            $refresh = $h2.children('a').first(),
            name, $title, $tt, $h1, $act, sub, $exp, $tip, $filter, label;

        if (!$bar.length || !$refresh.length)
            return;
        if ($content.prev('.cdr-titlebar').length)
            return;
        name = txt($refresh.text());
        if (!name)
            return;

        $title = $('<div class="cdr-titlebar"></div>');
        $tt = $('<div class="cdr-tt"></div>').appendTo($title);
        $h1 = $('<h1></h1>').text(name).appendTo($tt);
        // The criteria icon (with its tooltip) goes next to the name.
        $filter = $h2.children('i.icon-filter').first();
        if ($filter.length)
            $h1.append($filter.detach());
        sub = $form.length ? pageText($content, $form) : '';
        if (sub)
            $('<p class="sub"></p>').text(sub).appendTo($tt);

        $act = $('<div class="cdr-tact"></div>').appendTo($title);
        // Refresh: the real link, icon kept, label = its title ("Refresh").
        label = txt($refresh.attr('title'));
        $refresh.detach().addClass('btn btn-line')
            .contents().filter(function () { return this.nodeType === 3; }).remove();
        if (label)
            $refresh.append(document.createTextNode(label));
        else
            $refresh.attr('aria-label', name);
        $act.append($refresh);
        // Export: the real link and its help tip.
        $exp = $form.find('#queue-export').first();
        if ($exp.length) {
            $tip = $exp.nextAll('.help-tip').first();
            $exp.detach().addClass('btn btn-line');
            $act.append($exp);
            if ($tip.length)
                $act.append($tip.detach());
        }
        $content.before($title);
        $bar.addClass('cdr-bulk');
    }

    // ───────── Footer: showing text and pager, A30 ─────────
    function tagFooter($form) {
        var $f = $form.children('div').has('.faded').last(), label = '';
        if (!$f.length || $f.hasClass('cdr-qfoot'))
            return;
        $f.contents().filter(function () { return this.nodeType === 3; }).each(function () {
            var t = txt(this.nodeValue).replace(/[:：]\s*$/, '');
            if (t && !label)
                label = t;
        });
        $f.addClass('cdr-qfoot');
        if (label)
            $f.attr({ role: 'navigation', 'aria-label': label });
    }

    // ───────── Bulk bar: labels, counter, claim, A13 ─────────
    function selectionLabel($form) {
        var t = '';
        $form.find('tfoot').first().find('td').first().contents().each(function () {
            if (!t && this.nodeType === 3 && txt(this.nodeValue))
                t = txt(this.nodeValue);
        });
        return t;
    }

    function buildBulk($content, $form) {
        var $bar = $content.find('.sticky.bar.cdr-bulk').first(), $right, $left, $claim, $src, $sel;
        if (!$bar.length || $bar.hasClass('cdr-bulk-done'))
            return;
        $right = $bar.find('.pull-right.flush-right').first();
        if (!$right.find('.tickets-action').length)
            return;
        $bar.addClass('cdr-bulk-done');

        $right.find('span.action-button, a.action-button').each(function () {
            var $b = $(this), label = labelOf($b), $a = $b.is('a') ? $b : $b.children('a').first();
            if (!label || !$a.length || $a.children('.cdr-lbl').length)
                return;
            $('<span class="cdr-lbl"></span>').text(label).appendTo($a);
            // The label is now the accessible name; the tooltip repeats it.
            if (!$a.attr('aria-label'))
                $a.attr('aria-label', label);
        });

        // Claim, cloned from the Assign menu (document-level handler on a.tickets-action).
        $src = $right.find('a.tickets-action[href$="mass/claim"]').first();
        if ($src.length) {
            $claim = $src.clone().removeAttr('id').addClass('button action-button cdr-claim');
            $claim.find('i').remove().end();
            $claim.prepend('<i class="icon-ok" aria-hidden="true"></i>');
            $right.append($claim);
        }

        $left = $bar.find('.pull-left.flush-left').first();
        if (!$left.length)
            $left = $('<div class="pull-left flush-left"></div>').prependTo($bar.children('.content').first());
        $sel = $('<span class="cdr-sel" aria-live="polite"></span>');
        if (selText(0)) {
            // The mockup's "N tickets selected" (header.inc.php labels)
            $sel.addClass('cdr-sel-text').text(selText(0));
        } else {
            // Fallback: core's own "Select:" word and a count pill
            $('<span class="cdr-sel-l"></span>').text(selectionLabel($form)).appendTo($sel);
            $('<b>0</b>').appendTo($sel);
            $sel.children('.cdr-sel-l').filter(function () { return !$(this).text(); }).remove();
        }
        $left.append($sel);
    }

    function updateSelection() {
        var $form = $('form#tickets'), n = $form.find('input.ckb:checked').length,
            total = $form.find('input.ckb:enabled').length,
            $all = $form.find('input.cdr-ckb-all');
        $('.cdr-bulk .cdr-sel').toggleClass('is-on', n > 0).each(function () {
            var $s = $(this);
            if ($s.hasClass('cdr-sel-text'))
                $s.text(selText(n));
            else
                $s.children('b').text(n);
        });
        if ($all.length) {
            $all.prop('checked', total > 0 && n === total);
            $all.prop('indeterminate', n > 0 && n < total);
        }
    }

    // ───────── Header check box, A29 ─────────
    function buildSelectAll($form) {
        var $th = $form.find('table.queue > thead > tr > th:first-child').not('[data-id]').first(),
            $all = $('#selectAll'), $none = $('#selectNone');
        if (!$th.length || !$all.length || !$none.length || $th.find('input').length)
            return;
        $('<input type="checkbox" class="cdr-ckb-all">')
            .attr('aria-label', label('select-all') || txt($all.text()))
            .appendTo($th);
        $all.closest('tfoot').addClass('cdr-sel-foot');
    }

    // ───────── Assignee, A25 ─────────
    // Seeded core column 8 ("Assignee", primary "assignee"); other columns are left alone.
    function buildAssignee($form) {
        var $table = $form.find('table.queue').first(), $ths, idx,
            none = label('unassigned');
        if (!$table.length)
            return;
        $ths = $table.find('> thead > tr > th');
        idx = $ths.index($ths.filter('[data-id="8"]').first());
        if (idx < 0)
            return;
        $table.find('> tbody > tr').each(function () {
            var $td = $(this).children('td').eq(idx), name, $who;
            if (!$td.length || $td.hasClass('cdr-who-done') || $td.children().length)
                return;
            name = txt($td.text());
            $td.addClass('cdr-who-done');
            if (!name) {
                // Nobody assigned: the "Not assigned" label of header.inc.php
                // (user icon, italic), else a dashed placeholder disc.
                if (none) {
                    $td.append($('<span class="cdr-who is-none cdr-unassigned"></span>')
                        .append('<i class="icon-user" aria-hidden="true"></i>')
                        .append(document.createTextNode(none)));
                    return;
                }
                $td.append($('<span class="cdr-who is-none" aria-hidden="true"></span>')
                    .append('<span class="cdr-av"></span>'));
                return;
            }
            $who = $('<span class="cdr-who"></span>');
            $('<span class="cdr-av" aria-hidden="true"></span>').text(initials(name)).appendTo($who);
            $('<span class="cdr-name"></span>').text(name).appendTo($who);
            $td.empty().append($who);
        });
    }

    // ───────── Search field: core autofocus, A12 ─────────
    // The queue's field replaces the top bar search on this page (queue.css):
    // same placeholder, accessible name and "/" shortcut (texts from the top bar).
    function searchField() {
        var $f = $('#content.cdr-tickets #basic_search input.basic-search').first(),
            $top = $('.topbar .tsearch input[type="search"]').first();
        if (!$f.length || !$top.length)
            return;
        if (!$f.attr('placeholder') && $top.attr('placeholder'))
            $f.attr('placeholder', $top.attr('placeholder'));
        if (!$f.attr('aria-label') && $top.attr('aria-label'))
            $f.attr('aria-label', $top.attr('aria-label'));
        $f.attr('aria-keyshortcuts', '/');
    }

    function dropAutofocus() {
        var f = $('#content.cdr-tickets input.basic-search')[0];
        setTimeout(function () {
            if (f && !userActed && document.activeElement === f && !f.value)
                f.blur();
        }, 0);
    }

    function init() {
        var $content = $('#content.cdr-tickets'), $form;
        if (!$content.length)
            return;
        $form = $content.find('form#tickets').first();
        if ($form.length) {
            buildTitle($content, $form);
            tagFooter($form);
            buildBulk($content, $form);
            buildSelectAll($form);
            buildAssignee($form);
            updateSelection();
        }
        searchField();
        dropAutofocus();
    }

    $(document)
        .on('keydown.cdrq mousedown.cdrq touchstart.cdrq', function () { userActed = true; })
        .on('change.cdrq click.cdrq', 'form#tickets input.ckb', function () {
            // after core's own handler toggled the row
            setTimeout(updateSelection, 0);
        })
        .on('change.cdrq', 'form#tickets input.cdr-ckb-all', function () {
            $(this.checked ? '#selectAll' : '#selectNone').trigger('click');
        })
        .on('pjax:end.cdrq', function () {
            userActed = false;
            init();
        });

    $(init);
}(window.jQuery));
