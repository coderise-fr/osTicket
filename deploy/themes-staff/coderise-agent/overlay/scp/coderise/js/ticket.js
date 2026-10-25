/*********************************************************************
    scp/coderise/js/ticket.js

    Ticket thread behaviour of the "coderise-agent" staff panel theme.
    Not part of osTicket core: shipped by deploy/themes-staff/coderise-agent.

    - Initials disc in front of every thread entry (span.cdr-av): letters
      derived from the author's name in the entry header (textContent only);
      client (entry.message) = voile disc, agent (response, note, system)
      = nuit disc. Core's avatar span stays in the DOM, hidden by CSS.
    - Empty entry subtitles are tagged so they do not take a row.
    - Event time (<time> inside the sentence) is moved to the end of the
      event row, where the mockup shows it.
    - Scroll correction: core's scp/js/thread.js scrolls to the latest entry
      minus 50 px, which leaves its header under the top bar and the fixed
      action bar. Once core's animation has ended, scroll up by exactly the
      hidden part (measured from the real bars), unless the user scrolled.

    Idempotent: init() runs on ready and on every pjax:end, and again when
    the thread is edited in place (MutationObserver).

    Released under the GNU General Public License WITHOUT ANY WARRANTY.
    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/
(function ($) {
    'use strict';
    if (!$ || window.cdrTicketBound)
        return;
    window.cdrTicketBound = true;

    var observer = null, observed = null, queued = false;
    var userMoved = false, scrollRun = 0;

    function initials(name) {
        var words = $.trim(name || '').split(/\s+/).filter(function (w) {
            return /[\p{L}\p{N}]/u.test(w);
        });
        if (!words.length)
            return '?';
        var first = Array.from(words[0].replace(/^[^\p{L}\p{N}]+/u, ''))[0] || '';
        if (words.length === 1)
            return (first + (Array.from(words[0].replace(/^[^\p{L}\p{N}]+/u, ''))[1] || '')).toUpperCase();
        var last = Array.from(words[words.length - 1].replace(/^[^\p{L}\p{N}]+/u, ''))[0] || '';
        return (first + last).toUpperCase();
    }

    function decorate() {
        queued = false;
        var $thread = $('#content.cdr-ticket #thread-items');
        if (!$thread.length)
            return;
        $thread.find('.thread-entry').each(function () {
            var $e = $(this);
            if (!$e.children('.cdr-av').length) {
                var name = $e.children('.header').children('b').first().text();
                var $av = $('<span class="cdr-av" aria-hidden="true"></span>')
                    .text(initials(name))
                    .toggleClass('cdr-av-team', !$e.hasClass('message'));
                $e.prepend($av).addClass('cdr-has-av');
            }
            $e.children('.header').children('.title').each(function () {
                $(this).toggleClass('cdr-empty', !$.trim($(this).text()));
            });
        });
        $thread.find('.thread-event').each(function () {
            var $ev = $(this);
            if ($ev.children('time.cdr-ev-time').length)
                return;
            var $t = $ev.children('.description').find('time').first();
            if ($t.length)
                $t.addClass('cdr-ev-time').appendTo($ev);
        });
    }

    function schedule() {
        if (queued)
            return;
        queued = true;
        (window.requestAnimationFrame || setTimeout)(decorate);
    }

    function observe() {
        var el = document.getElementById('ticket_thread');
        if (!el || !window.MutationObserver)
            return;
        if (observer && observed === el)
            return;
        if (observer)
            observer.disconnect();
        observed = el;
        observer = new MutationObserver(schedule);
        observer.observe(el, {childList: true, subtree: true});
    }

    // Bottom edge of what covers the top of the viewport: the top bar and the
    // action bar once it is fixed. Real rectangles, no hard-coded heights.
    function coveredHeight() {
        var h = 0;
        $('.topbar, #content .sticky.bar.fixed').each(function () {
            var r = this.getBoundingClientRect();
            if (r.height && r.bottom > h)
                h = r.bottom;
        });
        // Action bar not fixed yet (it becomes fixed as soon as we scroll):
        // use its height under the top bar.
        var $bar = $('#content .sticky.bar').first();
        if ($bar.length && !$bar.hasClass('fixed')) {
            var top = 0;
            $('.topbar').each(function () {
                top = Math.max(top, this.getBoundingClientRect().bottom);
            });
            h = Math.max(h, top + $bar.outerHeight());
        }
        return h;
    }

    function correctScroll() {
        var run = ++scrollRun, tries = 0;
        userMoved = false;
        var $items = $('#content.cdr-ticket #thread-items');
        if (!$items.length)
            return;
        (function poll() {
            if (run !== scrollRun || userMoved)
                return;
            var $html = $('html, body');
            var $bar = $('#content .sticky.bar').first();
            var busy = $html.is(':animated') || $bar.is(':animated');
            // the bar is fixed some ms after the scroll event
            var settling = window.pageYOffset > 0 && $bar.length && !$bar.hasClass('fixed');
            if ((busy || settling) && ++tries < 40)
                return void setTimeout(poll, 100);
            if (window.pageYOffset <= 0)
                return;
            // same entry as thread.js: the last visible thread entry
            var $entry = $('#ticket_thread .thread-entry').filter(':visible').last();
            if (!$entry.length)
                return;
            var gap = 8;
            var hidden = coveredHeight() + gap - $entry[0].getBoundingClientRect().top;
            if (hidden > 1)
                $html.animate({scrollTop: Math.max(0, window.pageYOffset - hidden)}, 200);
        })();
    }

    function init() {
        decorate();
        observe();
        if ($('#content.cdr-ticket #thread-items').length)
            setTimeout(correctScroll, 150);
    }

    // The user scrolling cancels the correction (never fight the user).
    $(window).on('wheel.cdrticket touchstart.cdrticket keydown.cdrticket mousedown.cdrticket', function () {
        userMoved = true;
    });

    $(document).on('pjax:start.cdrticket', function () {
        scrollRun++;
    });
    $(init);
    $(document).on('pjax:end.cdrticket', init);
})(window.jQuery);
