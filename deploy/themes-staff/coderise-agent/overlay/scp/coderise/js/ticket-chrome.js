/* coderise-agent: ticket page chrome behaviour (tab icons/counters, panel titles, action labels). One workstream owns this file. IIFE, idempotent on ready and pjax:end, no eval, textContent only. */
(function ($) {
    'use strict';
    if (!$) return;

    // Identity block (section.cdr-idline, printed by header.inc.php through
    // the object.view signal, before the action bar): the subject (core
    // .tixTitle, no handlers) goes INTO the section, between the identity
    // line and the client subtitle, so the section is the mockup's title
    // block (.title-row > div: identity, subject, subtitle) and one grid
    // item, which lets the action bar sit to its right on wide screens
    // (ticket-chrome.css). The title is moved, not copied. Without this
    // script the subject stays under the section.
    function identity() {
        var $c = $('#content.cdr-ticket'),
            $idl = $c.children('section.cdr-idline').first(),
            $title = $c.children('.tixTitle').first();
        if (!$idl.length || !$title.length) return;
        var $line = $idl.children('p.idline').first();
        if ($line.length) $line.after($title);
        else $idl.prepend($title);
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
    // emitted its label and the target status (open ticket; the stock
    // "Resolved" status, id 2, else the only closed status: never a guess
    // between several) and core's status menu holds that exact entry for
    // this agent (core prints the menu only with the close permission).
    // The button forwards the click to that core link: core's own handler
    // opens core's status dialog (comment, confirmation, permission checks
    // on the server). Its title is the status name the link shows.
    function closeLink($c) {
        var id = String($c.attr('data-cdr-resolve-status') || '');
        if (!/^[1-9][0-9]*$/.test(id)) return $();
        return $c.find('#action-dropdown-statuses a.ticket-action').filter(function () {
            return new RegExp('/status/close/' + id + '$').test(this.getAttribute('href') || '');
        }).first();
    }
    function resolveButton() {
        var $c = $('#content.cdr-ticket'), $bar = barOf($c),
            label = $.trim($c.attr('data-cdr-label-resolve') || ''),
            $box = $bar.find('.content > .pull-right.flush-right').first();
        if (!label || !$box.length || $box.find('.cdr-resolve').length) return;
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
    // then the other core actions). The labelled buttons go into a theme
    // span.cdr-acts, core's other actions (reply, note, status, assign,
    // transfer, more) into span.cdr-tools, a compact icon cluster: two
    // static wrappers (no position, so core's dropdown plugin, which places
    // a menu against the trigger's offset parent, is not affected); the
    // core nodes are moved, never copied (ids, data-dropdown targets,
    // tooltips and handlers are kept), so the focus order is the visual
    // one. The menus (.action-dropdown) and unknown items (plugins, core's
    // PARENT/CHILD label) stay where they are, after the wrappers.
    var LABELLED = ['a.action-button[href*="a=edit"]', '.action-button:has(#ticket-print)', '.cdr-resolve'];
    var TOOLS = ['a.post-response[href="#post-reply"]', 'a#post-note', '[data-dropdown="#action-dropdown-statuses"]',
        '[data-dropdown="#action-dropdown-assign"]', 'a#ticket-transfer', '[data-dropdown="#action-dropdown-more"]'];
    function barOrder() {
        var $c = $('#content.cdr-ticket');
        if (!$c.children('section.cdr-idline').length) return;
        var $box = barOf($c).find('.content > .pull-right.flush-right').first();
        if (!$box.length || $box.hasClass('cdr-ordered')) return;
        var pick = function (list) {
            var out = [];
            $.each(list, function (_, sel) {
                var $el = $box.children().filter(sel).first();
                if ($el.length) out.push($el[0]);
            });
            return out;
        };
        var acts = pick(LABELLED), tools = pick(TOOLS), wraps = [];
        if (acts.length) wraps.push($('<span class="cdr-acts"></span>').append(acts)[0]);
        if (tools.length) {
            var $tools = $('<span class="cdr-tools" role="group"></span>').append(tools),
                label = $.trim($c.attr('data-cdr-label-tools') || '');
            if (label) $tools.attr('aria-label', label);
            wraps.push($tools[0]);
        }
        $box.prepend(wraps).addClass('cdr-ordered');
    }

    // core's scp.js gives the bar's .content an inline width (the bar's
    // width when the page loaded) so its line breaks are the same once the
    // bar floats; it never updates it. After a window resize, the width is
    // set again the same way while the bar is in the page (not fixed).
    var resizeTimer = null;
    function barWidth() {
        var $bar = barOf($('#content.cdr-ticket')), $in = $bar.children('.content');
        if (!$bar.length || $bar.hasClass('fixed') || !$in.length || !$in[0].style.width) return;
        $in.width($bar.width());
    }
    if (!window.cdrBarResize) {
        window.cdrBarResize = true;
        $(window).on('resize.cdrchrome', function () {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(function () { barWidth(); barState(); }, 150);
        });
    }

    // When scp.js makes the bar float (.fixed), it leaves the page flow:
    // - on wide screens its grid column (auto, ticket-chrome.css) would
    //   shrink and the title block would reflow under the floating bar: the
    //   bar's cell keeps the width it had in the page (min-width) while the
    //   bar floats, and loses it when the bar comes back;
    // - the properties column sticks under the floating bar: its real
    //   height goes into --cdr-fixed-bar-h on #content (no hard-coded value).
    // Watched with a MutationObserver on the bar's class (scp.js toggles it).
    var barObserver = null, cellWidth = 0;
    function barState() {
        var $c = $('#content.cdr-ticket'), $bar = barOf($c), $cell = $bar.parent();
        if (!$bar.length) return;
        var wide = window.matchMedia && window.matchMedia('(min-width: 1560px)').matches;
        if ($bar.hasClass('fixed')) {
            if (wide && cellWidth) $cell.css('min-width', cellWidth + 'px');
            var h = $bar.outerHeight();
            if (h) $c[0].style.setProperty('--cdr-fixed-bar-h', Math.round(h) + 'px');
        } else {
            $cell.css('min-width', '');
            cellWidth = wide ? $cell.width() : 0;
        }
    }
    function watchBar() {
        var bar = barOf($('#content.cdr-ticket'))[0];
        if (barObserver) { barObserver.disconnect(); barObserver = null; }
        cellWidth = 0;
        if (!bar || !window.MutationObserver) return;
        barState();
        barObserver = new MutationObserver(barState);
        barObserver.observe(bar, { attributes: true, attributeFilter: ['class'] });
        // web fonts change the buttons' widths after ready
        if (document.fonts && document.fonts.ready) document.fonts.ready.then(barState);
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

    // Reply and note forms, compact (mockup .compose: a "To:" / "Visible to
    // the team only" line, the editor, then the footer). The secondary core
    // fields are folded behind an "Options" disclosure button:
    // - reply: From (tbody#to_sec), Recipients / Collaborators / Reply To
    //   (tbody#recipients), Signature (its row) and the blank spacer row;
    // - note: the optional note title (its row) and the blank spacer row.
    // Only their visibility changes (a class on the form): nothing is moved
    // out of the form, no name or id changes, the form posts the same
    // values. The panel starts folded only when every folded field holds its
    // default value (From, Reply To: the first option, as core prints them;
    // Signature: the agent's default, data-cdr-sig-default; collaborators:
    // the page's selection; title: empty) and no folded part shows an error
    // (server validation): otherwise it starts open. While a folded field
    // differs from its default, the button carries a visible "modified"
    // mark (also in its accessible name). The canned response select, the
    // editor, the attachments and the state + buttons line stay visible.
    function foldParts($f) {
        var $t = $f.children('table').first(), parts = [];
        if (!$t.length) return $();
        if ($f.is('#reply')) {
            $t.children('tbody#to_sec, tbody#recipients').each(function () { parts.push(this); });
            var $sig = $t.find('input[type=radio][name=signature]').first().closest('tr');
            if ($sig.length) {
                if (!$sig.attr('id')) $sig.attr('id', 'cdr-reply-sig');
                parts.push($sig[0]);
            }
        } else if ($f.is('#note')) {
            var $title = $t.find('input[name=title]').first().closest('tr');
            if ($title.length) {
                if (!$title.attr('id')) $title.attr('id', 'cdr-note-title');
                parts.push($title[0]);
            }
        }
        return $(parts);
    }
    // Blank layout rows of the form (a single cell, no field, no text):
    // hidden with the folded fields
    function spacerRows($f) {
        return $f.children('table').first().children('tbody').children('tr').filter(function () {
            var $td = $(this).children('td');
            return $td.length === 1 && !$td.find('input, select, textarea').length && !$.trim($td.text());
        });
    }
    function fieldsChanged($parts, sigDefault) {
        var diff = false;
        $parts.find('select, input, textarea').each(function () {
            var el = this, type = String(el.type || '').toLowerCase();
            if (diff || el.disabled || /^(hidden|submit|button|reset|image|file)$/.test(type)) return;
            if (el.tagName === 'SELECT') {
                if (el.multiple) {
                    $.each(el.options, function () { if (this.selected !== this.defaultSelected) diff = true; });
                } else if (el.options.length && el.selectedIndex > 0) {
                    diff = true;
                }
            } else if (type === 'radio') {
                if (!el.checked) return;
                var $group = $parts.find('input[type=radio]').filter(function () { return this.name === el.name; }),
                    def = el.name === 'signature' ? sigDefault : '';
                if (!def || !$group.filter(function () { return this.value === def; }).length) {
                    var $dc = $group.filter(function () { return this.defaultChecked; }).last();
                    def = $dc.length ? $dc[0].value : $group[0].value;
                }
                if (el.value !== def) diff = true;
            } else if (type === 'checkbox') {
                if (el.checked !== el.defaultChecked) diff = true;
            } else if ($.trim(el.value) !== '') {
                diff = true;
            }
        });
        return diff;
    }
    function partsError($parts) {
        var err = false;
        $parts.find('.error').each(function () {
            // core's required marks ("*") are not errors
            if ($.trim($(this).text()).replace(/^[\s*]+|[\s*]+$/g, '')) err = true;
        });
        return err;
    }
    function replyOptions() {
        var $c = $('#content.cdr-ticket'),
            sigDefault = $.trim($c.attr('data-cdr-sig-default') || '');
        $c.find('#response_options > form#reply, #response_options > form#note').each(function () {
            var $f = $(this);
            if ($f.children('.cdr-opts').length) return;
            var isNote = $f.is('#note'),
                label = $.trim($c.attr(isNote ? 'data-cdr-opts-note' : 'data-cdr-opts-reply') || ''),
                $parts = foldParts($f);
            if (!label || !$parts.length) return;
            var $mod = $('<span class="cdr-opts-mod" hidden></span>').text($.trim($c.attr('data-cdr-opts-changed') || '')),
                $btn = $('<button type="button" class="cdr-opts-btn" aria-expanded="false"></button>')
                    .attr('aria-controls', $parts.map(function () { return this.id; }).get().join(' '))
                    .append($('<span class="cdr-opts-lbl"></span>').text(label), $mod),
                $sum = $('<span class="cdr-opts-sum"></span>'),
                $line = $('<div class="cdr-opts"></div>').addClass(isNote ? 'cdr-opts-note' : 'cdr-opts-reply')
                    .append($sum, $btn);
            var summary = function () {
                if (isNote) return $sum.text($.trim($c.attr('data-cdr-opts-team') || ''));
                var $to = $f.find('select[name="reply-to"]').first();
                if ($to.length && $to.val() === 'none')
                    return $sum.text($.trim($to.find('option:selected').text()));
                var who = $.trim($f.find('tr#user-row > td').last().text()).replace(/\s+/g, ' '),
                    fmt = $.trim($c.attr('data-cdr-opts-to') || '');
                $sum.text(who && fmt.indexOf('%s') !== -1 ? fmt.replace('%s', who) : who);
            };
            var update = function () {
                var changed = fieldsChanged($parts, sigDefault);
                $mod.prop('hidden', !changed || !$mod.text());
                $btn.toggleClass('is-changed', changed);
                summary();
            };
            var set = function (open) {
                $f.toggleClass('cdr-opts-closed', !open);
                $btn.attr('aria-expanded', open ? 'true' : 'false');
            };
            $parts.add(spacerRows($f)).addClass('cdr-fold');
            $f.children('table').first().before($line);
            set(fieldsChanged($parts, sigDefault) || partsError($parts));
            update();
            $btn.on('click', function () { set($btn.attr('aria-expanded') !== 'true'); });
            $f.on('change.cdropts input.cdropts', function (e) {
                if ($parts.has(e.target).length) update();
            });
            $f.on('reset.cdropts', function () { setTimeout(update, 0); });
            // A value the browser restores without a change event (back /
            // forward cache, form restore) is caught again on pageshow and
            // just before a submit: the mark is recomputed and the panel
            // opens if a folded field differs or shows an error; it is never
            // folded again by this. Visibility only: the submit listener
            // (capture, bound once per form) never blocks or alters the post.
            var recheck = function () {
                update();
                if (fieldsChanged($parts, sigDefault) || partsError($parts)) set(true);
            };
            $f.data('cdrOptsCheck', recheck);
            this.addEventListener('submit', function () { try { recheck(); } catch (e) { /* never in the way of a post */ } }, true);
        });
    }
    if (!window.cdrOptsPageshow) {
        window.cdrOptsPageshow = true;
        window.addEventListener('pageshow', function () {
            $('#content.cdr-ticket #response_options > form').each(function () {
                var fn = $(this).data('cdrOptsCheck');
                if (typeof fn === 'function') fn();
            });
        });
    }

    // "Log" tab (mockup: Conversation | Tasks | Log): the same tab content
    // as the thread (#ticket_thread) in a view mode that shows only the
    // thread's events (.thread-event rows, already in the page): the tab
    // toggles .cdr-journal on #ticket_thread, the CSS hides the entries
    // (the reply area stays, as the mockup's composer). Built like core's tabs (ul#ticket_tabs > li > a), but
    // its link points to no tab content (#cdr-journal) and its own click
    // handler, bound on the link, stops the click before core's delegated
    // tab handler (which would look for a content or a data-url): it does
    // what core does for a tab (li.active, the container's other contents
    // hidden, the hash) and adds the mode. Any other tab of the strip, also
    // when clicked by core (reply / note buttons click the thread tab),
    // leaves the mode. Core's hash restore (scp.js clicks the tab whose href
    // is the hash) reopens the log after a reload.
    function journalTab() {
        var $c = $('#content.cdr-ticket'),
            label = $.trim($c.attr('data-cdr-journal-tab') || ''),
            $ul = $c.find('ul#ticket_tabs').first(),
            $box = $('#ticket_tabs_container'),
            $thread = $box.children('#ticket_thread').first();
        if (!label || !$ul.length || !$thread.length || $ul.find('#cdr-journal-tab').length) return;
        var $items = $thread.find('#thread-items').first();
        if ($items.length && !$items.children('.thread-event').length && !$thread.children('.cdr-journal-empty').length) {
            var empty = $.trim($c.attr('data-cdr-journal-empty') || '');
            if (empty) $items.parent().after($('<p class="cdr-journal-empty"></p>').text(empty));
        }
        var $a = $('<a id="cdr-journal-tab" href="#cdr-journal"></a>').text(label);
        $a.on('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            var $t = $('#ticket_tabs_container').children('#ticket_thread');
            $(this).closest('ul').children('li.active').removeClass('active');
            $(this).closest('li').addClass('active');
            $('#ticket_tabs_container').children('.tab_content').not($t).hide();
            $t.addClass('cdr-journal').show();
            if ($.changeHash) $.changeHash('#cdr-journal', true);
        });
        $ul.append($('<li class="cdr-journal-li"></li>').append($a));
        // a hash restored before this tab existed (core's ready runs later,
        // so this is only for a run after it, e.g. a second pass)
        if (window.location.hash === '#cdr-journal' && !$thread.hasClass('cdr-journal')) $a.trigger('click');
    }
    if (!window.cdrJournalBound) {
        window.cdrJournalBound = true;
        $(document).on('click.cdrjournal', 'ul#ticket_tabs > li > a', function () {
            if (this.id !== 'cdr-journal-tab') $('#ticket_thread').removeClass('cdr-journal');
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

    // The buttons now start at the left of the page: a core menu anchored
    // to the right of its button (anchor-right, e.g. Print) would open past
    // the left edge of the content (over the rail, or off screen at 390).
    // Once core's dropdown plugin has placed the menu (it stops the click
    // propagation, so a capture listener + timeout runs after it), the menu
    // is shifted back inside the content area, else inside the viewport.
    // Only the left offset of the action bar's own menus changes.
    function keepMenuInView(e) {
        var t = e.target && e.target.closest && e.target.closest('[data-dropdown]');
        if (!t || !t.closest('#content.cdr-ticket .sticky.bar')) return;
        setTimeout(function () {
            var $dd = $($(t).attr('data-dropdown'));
            if (!$dd.length || !$dd.is(':visible')) return;
            // the area: #content in the page, the bar itself once fixed
            var $bar = $(t).closest('.sticky.bar'),
                area = $bar.hasClass('fixed') ? $bar[0] : document.getElementById('content'),
                r = $dd[0].getBoundingClientRect(),
                cr = area ? area.getBoundingClientRect() : null,
                vw = document.documentElement.clientWidth,
                min = 8, max = vw - 8, shift = 0;
            if (cr && r.width <= Math.min(max, cr.right) - Math.max(min, cr.left)) {
                min = Math.max(min, cr.left);
                max = Math.min(max, cr.right);
            }
            if (r.left < min) shift = min - r.left;
            else if (r.right > max) shift = Math.max(max - r.right, min - r.left);
            if (shift) $dd.css('left', ((parseFloat($dd.css('left')) || 0) + shift) + 'px');
        }, 0);
    }
    if (!window.cdrMenuInView && document.addEventListener) {
        window.cdrMenuInView = true;
        document.addEventListener('click', keepMenuInView, true);
    }

    function run() {
        identity();
        barLabels();
        resolveButton();
        barOrder();
        watchBar();
        tabCounts();
        watchTabs();
        journalTab();
        replyFoot();
        replyOptions();
        propsLabels();
        propsTitles();
    }
    $(run);
    $(document).off('pjax:end.cdrchrome').on('pjax:end.cdrchrome', run);
}(window.jQuery));
