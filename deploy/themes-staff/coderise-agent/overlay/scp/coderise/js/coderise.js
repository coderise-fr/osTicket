/*********************************************************************
    scp/coderise/js/coderise.js

    Behaviour of the "coderise-agent" staff panel theme (Coderise).
    Not part of osTicket core: shipped by deploy/themes-staff/coderise-agent.

    - Queue counts of the rail. The rail renames core's #customQ_nav to
      #cdr-queues (so jb.overflow.menu.js leaves it alone), which also
      unbinds core's hover-triggered count loader: fetch the counts here,
      on load and after every PJAX navigation (at most once a minute: the
      response is kept in sessionStorage). The same response gives the
      top-level queues a count too (core shows none there).
    - "Menu" button of the rail below 920 px (aria-expanded).
    - The top bar search submits through PJAX, as core's #basic_search does.
    - Empty queues: the rail count is tagged .cdr-zero (no "0" badge).
    - Fallback: a core table that still overflows the card gets its own
      horizontal scroll wrapper (only while it overflows).
    - Core pagination links are tagged (.cdr-pager, .cdr-page-current).
    - Ticket view: the properties column (aside.cdr-props), the latest
      thread entry, the note mode of the reply area.
    - Compact search button of the top bar below 920 px.
    - Dashboard chart: readable x axis on narrow screens, keyboard legend.
    - Admin forms: changed-field marks and the save bar counter.

    Every handler is delegated and bound once; init() only reads the DOM
    of the current page, so running it again (pjax:end) is harmless.

    Released under the GNU General Public License WITHOUT ANY WARRANTY.
    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/
(function ($) {
    'use strict';
    if (!$ || window.cdrBound)
        return;
    window.cdrBound = true;

    var countsRequest = null;

    // Containers of user or rich content (thread bodies, Redactor editors,
    // quick notes, FAQ category descriptions): the theme's DOM changes never
    // reach inside them. coderise.css excludes the same list.
    var RICH = '.thread-body, .redactor-box, [contenteditable], .quicknote, .cat-desc';

    // The rail item "Tickets" (main menu, not the queue list)
    function ticketsItem() {
        return $('.rail nav.menu a.item').filter(function () {
            return /(^|\/)tickets\.php$/.test((this.getAttribute('href') || '').split(/[?#]/)[0]);
        }).first();
    }

    // Neutral pill on the Tickets item: the count of the top "Open" queue (the
    // id of the top queue link, "1" when the queue list is not on the page).
    // Core's counts response has no task count: no pill on Tasks.
    function showTicketsBadge(json) {
        var $item = ticketsItem();
        if (!$item.length)
            return;
        var id = (/[?&]queue=(\d+)/.exec($('#cdr-queues > ul > li.top-queue > a').first().attr('href') || '') || [])[1] || '1',
            n = json['q' + id],
            $b = $item.children('.badge.cdr-badge');
        if (n === undefined || n === null || !/^\d+$/.test(String(n)) || +n === 0) {
            $b.remove();
            return;
        }
        if (!$b.length)
            $b = $('<span class="badge cdr-badge"></span>').appendTo($item);
        $b.text(n);
    }

    // Counts of the top-level queues (mockup "Ouverts 24"): core prints a
    // count on sub-queues only, but the same response has every queue id.
    // The id is read from the link (tickets.php?queue=N; the search entry
    // has none). Own class: core's loader rewrites every li span.queue-count.
    function showTopCounts(json) {
        $('#cdr-queues > ul > li.top-queue > a[href]').each(function () {
            var m = /[?&]queue=(\d+)(&|#|$)/.exec(this.getAttribute('href') || ''),
                n = m ? json['q' + m[1]] : null,
                $c = $(this).children('.cdr-qcount');
            if (n === undefined || n === null || !/^\d+$/.test(String(n))) {
                $c.remove();
                return;
            }
            if (!$c.length)
                $c = $('<span class="cdr-qcount"></span>').appendTo(this);
            $c.text(n).toggleClass('cdr-zero', +n === 0);
        });
    }

    // The counts are fetched once per minute at most: the response is kept in
    // sessionStorage (per tab) for COUNTS_TTL ms, so PJAX navigations and
    // reloads reuse it instead of calling ajax.php/queue/counts on every page
    // (core caches them for five minutes on the server anyway). The entry is
    // tied to the signed-in session (a hash of the sign-out link, which carries
    // the session's token: the token itself is not stored), and ignored when a
    // queue shown in the rail has no count in it (a queue added meanwhile).
    // Every storage access is guarded: private windows, blocked storage and a
    // full quota only mean "no cache".
    var COUNTS_KEY = 'cdr.queueCounts', COUNTS_TTL = 60000;

    function sessionKey() {
        var s = $('.rail .foot a[href*="logout.php"]').first().attr('href') || '',
            h = 5381, i;
        for (i = 0; i < s.length; i++)
            h = ((h << 5) + h + s.charCodeAt(i)) | 0;
        return s ? String(h) : '';
    }
    function readCounts($counts) {
        var k = sessionKey(), c, ok = true;
        if (!k)
            return null;
        try {
            c = JSON.parse(window.sessionStorage.getItem(COUNTS_KEY) || 'null');
        } catch (e) {
            return null;
        }
        if (!c || c.k !== k || !c.json || typeof c.json !== 'object'
                || !(Date.now() - c.t < COUNTS_TTL) || Date.now() < c.t)
            return null;
        $counts.each(function () {
            if (c.json['q' + $(this).data('queueId')] === undefined)
                ok = false;
        });
        return ok ? c.json : null;
    }
    function storeCounts(json) {
        var k = sessionKey();
        if (!k || !json || typeof json !== 'object')
            return;
        try {
            window.sessionStorage.setItem(COUNTS_KEY,
                JSON.stringify({ k: k, t: Date.now(), json: json }));
        } catch (e) { /* no storage: the next page fetches again */ }
    }
    function showCounts(json, $counts) {
        $counts.each(function () {
            var $e = $(this),
                n = json['q' + $e.data('queueId')];
            if (n !== undefined && n !== null)
                $e.text(n).toggleClass('cdr-zero', +n === 0);
        });
        $('#cdr-queues').find('[id="queue-count-bucket"]')
            .removeClass('hidden').show();
        showTopCounts(json);
        showTicketsBadge(json);
    }

    function loadCounts() {
        var $counts = $('#cdr-queues .queue-count[data-queue-id]');
        if (!$counts.length && !ticketsItem().length)
            return;
        var cached = readCounts($counts);
        if (cached) {
            showCounts(cached, $counts);
            return;
        }
        if (countsRequest)
            countsRequest.abort();
        countsRequest = $.ajax({
            url: 'ajax.php/queue/counts',
            dataType: 'json',
            success: function (json) {
                if (!json)
                    return;
                storeCounts(json);
                showCounts(json, $counts);
            },
            complete: function () {
                countsRequest = null;
            }
        });
    }

    function setMenu($rail, open) {
        $rail.toggleClass('is-open', open);
        $rail.find('.menu-toggle').attr('aria-expanded', open ? 'true' : 'false');
    }

    // Fallback for wide core tables. coderise.css makes table.list and
    // .form_table take the card width (width="940" neutralised); a table whose
    // content still does not fit (many columns at 390 px) scrolls sideways in
    // its own .cdr-hscroll wrapper. A scroll box clips vertically too, so a
    // table is wrapped only while it overflows and unwrapped once it fits.
    // Core menus are absolutely positioned against body (the dropdown plugin
    // uses the trigger's offset parent): the wrapper is not their containing
    // block, so a menu opened from a wrapped table is not cut (measured with
    // an injected menu on the last row of a wrapped list at 390 px).
    // Hidden tables (inactive tabs) measure 0 and are left alone until a tab
    // click or resize re-runs. table.ticket_info is never wrapped: it holds
    // the user/org stats dropdowns. Tables typed or pasted by users (thread
    // bodies, editors, notes) are content, never re-parented.
    // Moving a table drops the focus of the field inside it (the queue editor
    // autofocuses its first field): the focus and caret are restored.
    function refocus(el) {
        var s = null, e = null;
        try {
            s = el.selectionStart;
            e = el.selectionEnd;
        } catch (x) { /* types without a selection */ }
        return function () {
            if (document.activeElement === el || !$.contains(document, el))
                return;
            el.focus({ preventScroll: true });
            if (s !== null) {
                try {
                    el.setSelectionRange(s, e);
                } catch (x) { /* ignore */ }
            }
        };
    }
    function fitTables() {
        $('#content').find('table.list, table.form_table, table.settings_table, table.table')
            .not('.ticket_info').filter(function () {
                return !$(this).closest(RICH).length;
            }).each(function () {
                var $t = $(this),
                    $p = $t.parent(),
                    wrapped = $p.hasClass('cdr-hscroll'),
                    box = wrapped ? $p[0] : $t[0].parentNode;
                if (!this.offsetWidth || !box)
                    return;
                var over = this.offsetWidth > box.clientWidth + 1;
                if (over === wrapped)
                    return;
                var active = $.contains(this, document.activeElement)
                        ? document.activeElement : null,
                    restore = active ? refocus(active) : null;
                if (over)
                    $t.wrap('<div class="cdr-hscroll"></div>');
                else
                    $t.unwrap();
                if (restore)
                    restore();
            });
    }

    // Core pagination has no classes. Pagenate::getPageLinks() prints
    // "Page:" then "<a href="...&p=1"><b>1</b></a>", "<b>[2]</b>" (current)
    // and "<a ...><strong>&raquo;</strong></a>". Tag the container and the
    // current page (brackets dropped) only for that exact shape: a bare <b>[n]</b>
    // next to a page link (href with p=), or <b>[1]</b> right after the one-word
    // "Page:" label (single page), never inside user content (thread bodies,
    // editors).
    function isPageLink(el) {
        return !!el && el.tagName === 'A'
            && /[?&](amp;)?p=\d+/.test(el.getAttribute('href') || '')
            && $(el).children('b, strong').length === 1;
    }
    function tagPagers() {
        $('#content').find('b').filter(function () {
            var prev = this.previousSibling;
            if (this.children.length
                    || !/^\[\d+\]$/.test($.trim($(this).text()))
                    || $(this).closest('#ticket_thread, .thread-entry, ' + RICH).length)
                return false;
            // No neighbouring page link means a single page: core then prints
            // "Page:" (one short word and the colon, maybe after &nbsp;) and
            // "[1]", never another number
            return isPageLink(this.previousElementSibling)
                || isPageLink(this.nextElementSibling)
                || (prev && prev.nodeType === 3 && $.trim($(this).text()) === '[1]'
                    && /(^|[\s\u00a0])[^\s:\u00a0]{1,24}:\s*$/.test(prev.nodeValue));
        }).each(function () {
            var $b = $(this);
            $b.text($.trim($b.text()).slice(1, -1))
                .addClass('cdr-page-current').attr('aria-current', 'page')
                .parent().addClass('cdr-pager');
        });
    }

    // Ticket view (#content.cdr-ticket only; edit and print pages do not
    // have the class). Core markup: #content > [message bars], a bare <div>
    // (action bar), .tixTitle, table.ticket_info x N (with <br>),
    // ul#ticket_tabs, #ticket_tabs_container. The "First reply" panel
    // (section.cdr-sla, object.view signal) is printed before the bare div.
    // - ul#ticket_tabs goes first into #ticket_tabs_container: the grid then
    //   has one item in column 1 (core's tab code finds the container by id
    //   and only toggles its .tab_content children);
    // - section.cdr-sla and the table.ticket_info blocks go, in that order,
    //   into one <aside class="cdr-props"> placed after the container.
    // Runs before scp.js's own ready/pjax:end handler (this file is loaded
    // in <head>), so the sticky bar offsets it measures are final. Moving
    // nodes keeps their handlers; a second run finds nothing left to move.
    function ticketLayout() {
        var $c = $('#content.cdr-ticket');
        if (!$c.length)
            return;
        var $box = $c.children('#ticket_tabs_container'),
            $tabs = $c.children('ul#ticket_tabs'),
            $aside = $c.children('aside.cdr-props');
        if ($box.length && $tabs.length)
            $box.prepend($tabs);
        var $parts = $c.children('section.cdr-sla, table.ticket_info');
        if ($parts.length && !$aside.length) {
            $aside = $('<aside class="cdr-props"></aside>');
            // named from data-cdr-props; no name rather than an empty one
            if ($.trim($c.attr('data-cdr-props') || ''))
                $aside.attr('aria-label', $c.attr('data-cdr-props'));
            if ($box.length)
                $aside.insertAfter($box);
            else
                $c.append($aside);
        }
        // the panel first, then the tables in their core order
        $aside.append($parts.filter('section.cdr-sla'))
            .append($parts.filter('table.ticket_info'));
        markLatest();
        syncNote();
        fitProps();
    }

    // The most recent thread entry (highest id: works for both sort orders)
    function markLatest() {
        var $items = $('#content.cdr-ticket #thread-items')
                .children('[id^="thread-entry-"]'),
            best = null, max = -1;
        $items.each(function () {
            var n = parseInt(this.id.replace('thread-entry-', ''), 10);
            if (n > max) {
                max = n;
                best = this;
            }
        });
        $items.children('.thread-entry').removeClass('cdr-latest');
        if (best)
            $(best).children('.thread-entry').addClass('cdr-latest');
    }

    // Reply area: the "Post Internal Note" tab tints the whole area
    function syncNote() {
        var $r = $('#content.cdr-ticket #response_options');
        if (!$r.length)
            return;
        var $note = $r.find('#post-note-tab');
        $r.toggleClass('is-note', !!$note.length
            && ($note.closest('li').hasClass('active')
                || $r.children('form#note').is(':visible')));
    }

    // The aside sticks under the top bar and core's floating action bar.
    // A sticky box is released at the end of its grid area, so at the
    // bottom of the page its top sits at (viewport - space under #content -
    // its height): when that would slide under the floating bar, or when it
    // is simply taller than the window, it scrolls with the page instead
    // (.cdr-tall). Space taken above it = the measured top bar + the floating
    // bar (its height once floated, --cdr-fixed-bar-h from ticket-chrome.js,
    // else the bar's height in the page) + 16 px above and below.
    function fitProps() {
        var $a = $('#content.cdr-ticket > aside.cdr-props');
        if (!$a.length)
            return;
        var $c = $a.parent(),
            tail = $(document).height() - ($c.offset().top + $c.outerHeight()),
            top = $('.topbar').first().outerHeight() || 0,
            bar = parseFloat(window.getComputedStyle($c[0])
                .getPropertyValue('--cdr-fixed-bar-h'))
                || $c.find('.sticky.bar').first().outerHeight() || 0;
        $a.removeClass('cdr-tall');
        if ($a[0].offsetHeight > window.innerHeight - (top + bar + 32) - Math.max(0, tail))
            $a.addClass('cdr-tall');
    }

    // core's dropdown plugin positions a menu against the TRIGGER's offset
    // parent; the sticky aside is one, while some menus of its links live
    // elsewhere (Status: #action-dropdown-statuses is in the action bar).
    // Re-place such a menu against its own offset parent, once the plugin
    // has shown it. The plugin's body handler stops propagation, so this one
    // is bound on body too and waits for it.
    function placeMenu(t) {
        var $t = $(t),
            $d = $($t.attr('data-dropdown'));
        if (!$d.length || !$d.is(':visible') || $.contains($t.offsetParent()[0], $d[0]))
            return;
        var p = $d.offsetParent(),
            o = p.offset(),
            to = $t.offset(),
            left = $d.hasClass('anchor-right')
                ? to.left - ($d.outerWidth() - $t.outerWidth() - 4) : to.left;
        if (p[0] !== document.body && p[0] !== document.documentElement)
            o.top -= p.scrollTop();
        $d.css({ left: left - o.left, top: to.top + $t.outerHeight() - o.top });
    }
    $(function () {
        $('body').on('click.cdr', 'aside.cdr-props [data-dropdown]', function () {
            var t = this;
            setTimeout(function () { placeMenu(t); }, 0);
        });
    });

    $(document).on('click.cdr', '#content.cdr-ticket #response-tabs a', function () {
        setTimeout(syncNote, 0);
    });

    // Admin forms (#content.cdr-is-admin form.save): changed-field marks and
    // the save bar counter. Additive to core's own tracking (scp.js: "save
    // pending", beforeunload, pjax:beforeSend.changed, the submit handler that
    // clones the submit inputs): nothing is prevented, no input is moved.
    // Each field's value is recorded once (data cdrInit) when the page is
    // ready, or on first focus for fields added later; a field whose value
    // differs marks its row (tr, else its parent) .is-changed with a text
    // flag, and the form gets .is-dirty. The labels come from data-
    // attributes of #content (header.inc.php, cdr__()); text is written with
    // textContent only.
    var ADMIN_FORM = '#content.cdr-is-admin form.save',
        SKIP_TYPES = /^(hidden|submit|reset|button|image)$/;

    function trackable(el) {
        return !!el.name && !SKIP_TYPES.test(el.type || '')
            && !$(el).hasClass('nowarn');
    }
    function fieldValue(el) {
        if (el.type === 'checkbox' || el.type === 'radio')
            return el.checked ? '1' : '';
        if (el.tagName === 'SELECT') {
            var v = [];
            for (var i = 0; i < el.options.length; i++)
                if (el.options[i].selected)
                    v.push(i);
            return v.join(',');
        }
        return String(el.value);
    }
    function snapshot(el) {
        if (trackable(el) && $.data(el, 'cdrInit') === undefined)
            $.data(el, 'cdrInit', fieldValue(el));
    }
    function snapshotForms() {
        $(ADMIN_FORM).find(':input').each(function () {
            snapshot(this);
        });
    }
    function rowOf(el) {
        var $r = $(el).closest('tr');
        return $r.length ? $r[0] : el.parentNode;
    }
    function label(name) {
        return $('#content').attr('data-cdr-changed-' + name) || '';
    }
    // The button row: the last <p> holding a submit, child of the form or of
    // a wrapper <div> (settings-agents/users: the *-tabs_container)
    function saveRow($f) {
        return $f.children('p').add($f.children('div').children('p')).filter(function () {
            return $(this).children('input[type=submit], button[type=submit]').length > 0;
        }).last();
    }
    function trackForm(form) {
        var $f = $(form),
            rows = [];
        $f.find(':input').each(function () {
            var init = $.data(this, 'cdrInit'),
                row;
            if (init === undefined || !trackable(this) || fieldValue(this) === init)
                return;
            row = rowOf(this);
            if (row && rows.indexOf(row) < 0)
                rows.push(row);
        });
        $f.find('.is-changed').each(function () {
            if (rows.indexOf(this) < 0)
                $(this).removeClass('is-changed')
                    .find('.cdr-changed-flag').first().remove();
        });
        $.each(rows, function (i, row) {
            var $row = $(row), cell, flag;
            if ($row.hasClass('is-changed'))
                return;
            $row.addClass('is-changed');
            cell = $row.is('tr') ? $row.children('td, th').first()[0] : row;
            if (!cell)
                return;
            flag = document.createElement('span');
            flag.className = 'cdr-changed-flag';
            flag.textContent = label('flag');
            cell.appendChild(flag);
        });
        $f.toggleClass('is-dirty', rows.length > 0);
        var $bar = saveRow($f),
            $count = $bar.children('.cdr-savebar-count');
        if (!$bar.length || (!rows.length && !$count.length))
            return;
        if (!$count.length) {
            $count = $(document.createElement('span'))
                .addClass('cdr-savebar-count').attr('role', 'status');
            $bar.prepend($count);
        }
        $count[0].textContent = rows.length
            ? label(rows.length === 1 ? 'one' : 'many')
                .replace(/%d/g, String(rows.length))
            : '';
    }
    var trackTimer = null;
    function scheduleTrack(form, delay) {
        clearTimeout(trackTimer);
        trackTimer = setTimeout(function () {
            if (form && $.contains(document, form))
                trackForm(form);
        }, delay || 0);
    }
    $(document).on('focusin.cdr', ADMIN_FORM + ' :input', function () {
        snapshot(this);
    });
    // select2 reports through "change" on the underlying <select> (jQuery
    // trigger: it bubbles to this delegated handler)
    $(document).on('input.cdr change.cdr', ADMIN_FORM + ' :input', function () {
        scheduleTrack(this.form || $(this).closest('form')[0]);
    });
    // Redactor: the editor is a contenteditable <div>, not a form field; the
    // value that is submitted is its source textarea (.redactor-source), which
    // Redactor rewrites (normalised markup) right after it starts, and keeps in
    // sync a moment after each edit. The textarea is therefore recorded again
    // when its editor appears or gets the focus, until the first edit, and
    // compared a little after each edit.
    function editorField(el) {
        return $(el).closest('.redactor-box').find('textarea[name]').first()[0];
    }
    function snapshotEditor(ta) {
        if (!ta || !trackable(ta) || $.data(ta, 'cdrEdited'))
            return;
        $.data(ta, 'cdrInit', fieldValue(ta));
    }
    $(document).on('focusin.cdr', ADMIN_FORM + ' .redactor-in', function () {
        snapshotEditor(editorField(this));
    });
    $(document).on('input.cdr keyup.cdr', ADMIN_FORM + ' .redactor-in', function (e) {
        var ta = editorField(this);
        if (!ta || !trackable(ta))
            return;
        // keyup alone (arrows, Tab) is no edit: wait for a source change
        if (e.type === 'input')
            $.data(ta, 'cdrEdited', true);
        if ($.data(ta, 'cdrEdited'))
            scheduleTrack(ta.form || $(ta).closest('form')[0], 250);
    });
    // Native reset restores the values after the event: re-check afterwards
    $(document).on('reset.cdr', ADMIN_FORM, function () {
        scheduleTrack(this);
    });
    // Fields and editors added after the page is ready (AJAX, select2 and
    // Redactor start-up): recorded when they appear, not on their first focus.
    var fieldsTimer = null,
        fieldsObserver = null;
    function watchFields() {
        if (fieldsObserver || !window.MutationObserver)
            return;
        fieldsObserver = new MutationObserver(function (list) {
            var found = false;
            $.each(list, function (i, m) {
                $.each(m.addedNodes, function (j, n) {
                    if (n.nodeType === 1 && (/^(INPUT|SELECT|TEXTAREA)$/.test(n.tagName)
                            || n.querySelector('input, select, textarea, .redactor-box')))
                        found = true;
                });
            });
            if (!found || !document.querySelector(ADMIN_FORM))
                return;
            clearTimeout(fieldsTimer);
            fieldsTimer = setTimeout(function () {
                snapshotForms();
                $(ADMIN_FORM).find('.redactor-box textarea[name]').each(function () {
                    snapshotEditor(this);
                });
            }, 80);
        });
        fieldsObserver.observe(document.body, { childList: true, subtree: true });
    }

    // Compact search below 920 px: the top bar search is hidden there, a
    // button (the search icon of the field, labelled with the field's own
    // aria-label) reveals it. Not added where the queue's own search is the
    // page's (its top bar field is hidden for good). Idempotent: the header
    // is re-emitted by every PJAX response, the button is only added when
    // missing.
    function searchToggle() {
        var $bar = $('.cdr-main > .topbar').first(),
            $form = $bar.children('form.tsearch').first(),
            $input = $form.find('input[type=search]').first();
        if (!$form.length || !$input.length || $bar.children('.cdr-search-toggle').length
                || $('#content.cdr-tickets #basic_search').length)
            return;
        var id = $form.attr('id') || 'cdr-tsearch',
            name = $input.attr('aria-label') || '',
            $btn = $('<button type="button" class="btn btn-ghost icon-btn cdr-search-toggle"></button>')
                .attr({ 'aria-expanded': 'false', 'aria-controls': id,
                    'aria-label': name, title: name });
        $form.attr('id', id);
        $form.children('.ic').first().clone().attr('aria-hidden', 'true').appendTo($btn);
        $btn.insertAfter($form);
    }
    function setSearch($bar, open) {
        $bar.toggleClass('cdr-search-open', open)
            .children('.cdr-search-toggle').attr('aria-expanded', open ? 'true' : 'false');
        if (open)
            $bar.find('form.tsearch input[type=search]').first().trigger('focus');
    }
    $(document).on('click.cdr', '.topbar .cdr-search-toggle', function () {
        var $bar = $(this).closest('.topbar');
        setSearch($bar, !$bar.hasClass('cdr-search-open'));
    });
    // Escape closes the field and gives the focus back to the button. Core's
    // typeahead stops keydown on its input from bubbling, hence the capture
    // phase (bound once: the whole file is guarded by window.cdrBound).
    document.addEventListener('keydown', function (e) {
        var $bar = $(e.target).closest('.topbar.cdr-search-open');
        if (e.key === 'Escape' && $bar.length && $(e.target).closest('form.tsearch').length) {
            setSearch($bar, false);
            $bar.children('.cdr-search-toggle').trigger('focus');
        }
    }, true);

    // Dashboard activity chart (core's js/dashboard.inc.js draws it with
    // Raphael into #line-chart-here and rebuilds the legend on every refresh).
    // - x axis: the date labels are the <text> elements sharing one y; every
    //   second one of the lowest row is tagged .cdr-skip, which coderise.css
    //   hides up to 780 px (they overlap there);
    // - legend: each series label toggles its series through jQuery click
    //   handlers bound by the core script, so it becomes a keyboard button
    //   (tabindex, role, aria-pressed; Enter and Space trigger the click).
    // A MutationObserver re-applies both after each redraw; the series colours
    // are set inline by the core script and stay as they are.
    function tagAxis() {
        var rows = {}, low = -1;
        $('#line-chart-here svg text').each(function () {
            var y = this.getAttribute('y');
            low = Math.max(low, +y);
            (rows[y] = rows[y] || []).push(this);
        });
        // the lowest row of text is the x axis (hover tags sit above it)
        $.each(rows, function (y, list) {
            if (+y !== low || list.length < 3)
                return;
            list.sort(function (a, b) {
                return (+a.getAttribute('x')) - (+b.getAttribute('x'));
            });
            $.each(list, function (i, t) {
                if (i % 2)
                    t.setAttribute('class', 'cdr-skip');
                else
                    t.removeAttribute('class');
            });
        });
    }
    function enhanceLegend() {
        $('#line-chart-legend span.label').each(function () {
            $(this).attr({ tabindex: '0', role: 'button',
                'aria-pressed': $(this).hasClass('disabled') ? 'false' : 'true' });
        });
    }
    function initChart() {
        var host = document.getElementById('line-chart-here'),
            legend = document.getElementById('line-chart-legend');
        if (!host || !window.MutationObserver)
            return;
        if (!$.data(host, 'cdrObserved')) {
            $.data(host, 'cdrObserved', true);
            var timer = null,
                obs = new MutationObserver(function () {
                    clearTimeout(timer);
                    timer = setTimeout(function () {
                        tagAxis();
                        enhanceLegend();
                    }, 80);
                });
            obs.observe(host, { childList: true, subtree: true, characterData: true });
            if (legend)
                obs.observe(legend, { childList: true });
        }
        tagAxis();
        enhanceLegend();
    }
    $(document).on('keydown.cdr', '#line-chart-legend span.label', function (e) {
        if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            $(this).trigger('click');
        }
    });
    $(document).on('click.cdr', '#line-chart-legend span.label', function () {
        $(this).attr('aria-pressed', $(this).hasClass('disabled') ? 'false' : 'true');
    });

    var refreshTimer = null;
    function refresh() {
        clearTimeout(refreshTimer);
        refreshTimer = setTimeout(function () {
            fitTables();
            tagPagers();
            ticketLayout();
        }, 60);
    }

    function init() {
        ticketLayout();
        fitTables();
        tagPagers();
        searchToggle();
        initChart();
        loadCounts();
        // After the page's own ready/pjax:end handlers (select2, previews)
        setTimeout(snapshotForms, 0);
        watchFields();
    }

    // Rail "Menu" button (mobile)
    $(document).on('click.cdr', '.rail .menu-toggle', function (e) {
        var $rail = $(this).closest('.rail');
        e.preventDefault();
        setMenu($rail, !$rail.hasClass('is-open'));
    });
    $(document).on('keydown.cdr', function (e) {
        var $rail = $('.rail.is-open');
        if (e.key === 'Escape' && $rail.length) {
            setMenu($rail, false);
            $rail.find('.menu-toggle').trigger('focus');
        }
    });

    // Top bar search through PJAX (falls back to a normal GET)
    $(document).on('submit.cdr', 'form.tsearch', function (e) {
        var $form = $(this);
        if (!$.pjax || !$.support.pjax || $('#pjax-container').hasClass('no-pjax'))
            return;
        e.preventDefault();
        $.pjax({
            url: $form.attr('action') + '?' + $form.serialize(),
            container: '#pjax-container',
            timeout: 30000
        });
    });

    // Tables shown later (tabs, AJAX-loaded lists, window size) are re-fitted
    $(window).on('resize.cdr', refresh);
    $(document).on('click.cdr', 'ul.tabs a', refresh);
    $(document).on('ajaxComplete.cdr', function (e, xhr, settings) {
        if (!settings || !/queue\/counts/.test(settings.url || ''))
            refresh();
    });

    $(init);
    $(document).on('pjax:end.cdr', init);
})(window.jQuery);
