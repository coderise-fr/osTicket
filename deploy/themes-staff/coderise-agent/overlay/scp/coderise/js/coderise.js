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
    - Interim: wide core tables of #content get a horizontal scroll wrapper.

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
                        $e.text(n);
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

    // Interim until the page stylesheets (Task 5+): core's fixed 940 px tables
    // scroll horizontally in their own wrapper instead of widening the page.
    // Only the table is in the wrapper (core dropdowns, tips and typeahead
    // lists live outside these tables), so nothing else can be clipped.
    function wrapWideTables() {
        $('#content').find('table.list, table.ticket_info, table.form_table').each(function () {
            var $t = $(this);
            if (!$t.parent().hasClass('cdr-hscroll'))
                $t.wrap('<div class="cdr-hscroll"></div>');
        });
    }

    function init() {
        wrapWideTables();
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

    $(init);
    $(document).on('pjax:end.cdr', init);
})(window.jQuery);
