/*********************************************************************
    scp/coderise/js/coderise.js

    Behaviour of the "coderise-agent" staff panel theme (Coderise).
    Not part of osTicket core: shipped by deploy/themes-staff/coderise-agent.

    - Queue counts of the rail. The rail renames core's #customQ_nav to
      #cdr-queues (so jb.overflow.menu.js leaves it alone), which also
      unbinds core's hover-triggered count loader: fetch the counts here,
      on load and after every PJAX navigation.
    - "Menu" button of the rail below 920 px (aria-expanded).
    - The top bar search submits through PJAX, as core's #basic_search does.
    - Empty queues: the rail count is tagged .cdr-zero (no "0" badge).
    - Fallback: a core table that still overflows the card gets its own
      horizontal scroll wrapper (only while it overflows).
    - Core pagination links are tagged (.cdr-pager, .cdr-page-current).
    - Ticket view: the properties column (aside.cdr-props), the latest
      thread entry, the note mode of the reply area.
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

    function loadCounts() {
        var $counts = $('#cdr-queues .queue-count[data-queue-id]');
        if (!$counts.length)
            return;
        if (countsRequest)
            countsRequest.abort();
        countsRequest = $.ajax({
            url: 'ajax.php/queue/counts',
            dataType: 'json',
            success: function (json) {
                if (!json)
                    return;
                $counts.each(function () {
                    var $e = $(this),
                        n = json['q' + $e.data('queueId')];
                    if (n !== undefined && n !== null)
                        $e.text(n).toggleClass('cdr-zero', +n === 0);
                });
                $('#cdr-queues').find('[id="queue-count-bucket"]')
                    .removeClass('hidden').show();
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
    // table is wrapped only while it overflows and unwrapped once it fits:
    // the dropdowns of fitting tables are never clipped. Hidden tables (inactive
    // tabs) measure 0 and are left alone until a tab click or resize re-runs.
    // table.ticket_info is never wrapped: it holds the user/org stats dropdowns.
    // Tables typed or pasted by users (thread bodies, editors, notes) are
    // content, never re-parented.
    function fitTables() {
        $('#content').find('table.list, table.form_table, table.settings_table, table.table')
            .not('.ticket_info').filter(function () {
                return !$(this).closest(RICH).length;
            }).each(function () {
                var $t = $(this),
                    $p = $t.parent(),
                    wrapped = $p.hasClass('cdr-hscroll'),
                    box = wrapped ? $p[0] : $t[0].parentNode;
                // Never re-parent a table while focus is inside it (moving the
                // node would drop the focus of the field being typed in)
                if (!this.offsetWidth || !box
                        || $.contains(this, document.activeElement))
                    return;
                var over = this.offsetWidth > box.clientWidth + 1;
                if (over && !wrapped)
                    $t.wrap('<div class="cdr-hscroll"></div>');
                else if (!over && wrapped)
                    $t.unwrap();
            });
    }

    // Core pagination has no classes. Pagenate::getPageLinks() prints
    // "Page:" then "<a href="...&p=1"><b>1</b></a>", "<b>[2]</b>" (current)
    // and "<a ...><strong>&raquo;</strong></a>". Tag the container and the
    // current page (brackets dropped) only for that exact shape: a bare <b>[n]</b>
    // next to a page link (href with p=) or right after the "Page:" label,
    // never inside user content (thread bodies, editors).
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
            return isPageLink(this.previousElementSibling)
                || isPageLink(this.nextElementSibling)
                || (prev && prev.nodeType === 3 && /:\s*$/.test(prev.nodeValue));
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
            $aside = $('<aside class="cdr-props"></aside>')
                .attr('aria-label', $c.attr('data-cdr-props') || '');
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

    // The aside sticks under the top bar and core's floating action bar
    // (~130 px). A sticky box is released at the end of its grid area, so
    // at the bottom of the page its top sits at (viewport - space under
    // #content - its height): when that would slide under the floating bar,
    // or when it is simply taller than the window, it scrolls with the page
    // instead (.cdr-tall).
    function fitProps() {
        var $a = $('#content.cdr-ticket > aside.cdr-props');
        if (!$a.length)
            return;
        var $c = $a.parent(),
            tail = $(document).height() - ($c.offset().top + $c.outerHeight());
        $a.removeClass('cdr-tall');
        if ($a[0].offsetHeight > window.innerHeight - 140 - Math.max(0, tail))
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
                .replace('%d', String(rows.length))
            : '';
    }
    var trackTimer = null;
    function scheduleTrack(form) {
        clearTimeout(trackTimer);
        trackTimer = setTimeout(function () {
            if (form && $.contains(document, form))
                trackForm(form);
        }, 0);
    }
    $(document).on('focusin.cdr', ADMIN_FORM + ' :input', function () {
        snapshot(this);
    });
    $(document).on('input.cdr change.cdr', ADMIN_FORM + ' :input', function () {
        scheduleTrack(this.form || $(this).closest('form')[0]);
    });
    // Native reset restores the values after the event: re-check afterwards
    $(document).on('reset.cdr', ADMIN_FORM, function () {
        scheduleTrack(this);
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
        loadCounts();
        // After the page's own ready/pjax:end handlers (select2, previews)
        setTimeout(snapshotForms, 0);
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
