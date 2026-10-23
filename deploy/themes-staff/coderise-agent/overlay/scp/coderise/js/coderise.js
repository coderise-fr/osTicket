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
    function fitTables() {
        $('#content').find('table.list, table.form_table, table.settings_table, table.table')
            .not('.ticket_info').each(function () {
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
                    || $(this).closest('.thread-body, #ticket_thread, .thread-entry, '
                        + '.redactor-box, [contenteditable]').length)
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

    var refreshTimer = null;
    function refresh() {
        clearTimeout(refreshTimer);
        refreshTimer = setTimeout(function () {
            fitTables();
            tagPagers();
        }, 60);
    }

    function init() {
        fitTables();
        tagPagers();
        loadCounts();
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
