/*
 * Theme "Parcours" (Coderise) for the osTicket client portal.
 * Progressive enhancement only: every page works without this file.
 *
 * 1. Header menus (<details>): close with Escape and on a click elsewhere.
 * 2. Ticket wizard (open.php, #ticketForm[data-wizard]): shows the four
 *    fieldsets one at a time. The form stays one osTicket form, posted once,
 *    with the same field names. The rows of tbody#dynamic-form (help topic
 *    forms) are dispatched to steps 2 (topic forms), 3 (subject, message,
 *    attachments) and 4 (priority); again after each AJAX reload of the
 *    help topic forms. After a rejected POST the step of the first error is
 *    opened and the errors are listed with links.
 * No external request, no dependency beyond the jQuery shipped by osTicket
 * (used only to reload the help topic forms exactly like the core does).
 */
(function () {
    'use strict';

    function ready(fn) {
        if (document.readyState !== 'loading') fn();
        else document.addEventListener('DOMContentLoaded', fn);
    }

    function text(el) {
        return (el && el.textContent || '').replace(/\s+/g, ' ').trim();
    }

    function fmt(s) {
        var args = Array.prototype.slice.call(arguments, 1), i = 0;
        return String(s).replace(/%(?:(\d)\$)?d/g, function (m, n) {
            return n ? args[n - 1] : args[i++];
        });
    }

    /* 1. Header menus ---------------------------------------------------- */
    function initMenus() {
        var menus = document.querySelectorAll('.site-head details');
        if (!menus.length) return;
        document.addEventListener('keydown', function (e) {
            if (e.key !== 'Escape') return;
            Array.prototype.forEach.call(menus, function (d) {
                if (d.open) {
                    d.open = false;
                    d.querySelector('summary').focus();
                }
            });
        });
        document.addEventListener('click', function (e) {
            Array.prototype.forEach.call(menus, function (d) {
                if (d.open && !d.contains(e.target)) d.open = false;
            });
        });
    }

    /* 2. Ticket wizard --------------------------------------------------- */
    function initWizard() {
        var form = document.getElementById('ticketForm');
        if (!form || !form.getAttribute('data-wizard') || !window.jQuery) return;
        var cfg;
        try { cfg = JSON.parse(form.getAttribute('data-wizard')); } catch (e) { return; }
        var $ = window.jQuery;
        var S = cfg.str, roles = cfg.roles || {};
        var steps = [1, 2, 3, 4].map(function (n) { return document.getElementById('pcrs-step-' + n); });
        var dyn = document.getElementById('dynamic-form');
        var nav = document.getElementById('pcrs-steps-nav');
        var overall = document.getElementById('pcrs-overall');
        var live = document.getElementById('pcrs-live');
        var summary = document.getElementById('pcrs-errors');
        var recap = document.getElementById('pcrs-recap');
        var topic = document.getElementById('topicId');
        if (steps.indexOf(null) >= 0 || !dyn || !nav) return;
        var current = 0;

        document.documentElement.classList.add('pcrs-wizard-on');
        // Required fields hidden in another step must not block the POST:
        // osTicket validates on the server and the wizard shows its errors.
        form.noValidate = true;

        steps.forEach(function (s, i) {
            s.hidden = false;
            s.setAttribute('tabindex', '-1');
            s.querySelector('legend .n').textContent = String(i + 1);
            var lt = s.querySelector('legend .lt');
            if (lt && lt.getAttribute('data-js-label')) lt.textContent = lt.getAttribute('data-js-label');
        });

        // Step navigation (buttons: every step can be reached at any time)
        var ol = nav.querySelector('ol');
        var navItems = cfg.steps.map(function (st, i) {
            var li = document.createElement('li');
            var b = document.createElement('button');
            b.type = 'button';
            b.className = 'step-link';
            b.innerHTML = '<span class="node" aria-hidden="true"></span><span class="txt"><strong></strong><small></small></span>';
            b.querySelector('strong').textContent = st.title;
            b.querySelector('.node').textContent = String(i + 1);
            b.addEventListener('click', function () { go(i, true); });
            li.appendChild(b);
            ol.appendChild(li);
            return li;
        });
        nav.hidden = false;
        overall.hidden = false;

        // Previous / next buttons at the end of each step
        steps.forEach(function (s, i) {
            var bar;
            if (i === 3) {
                bar = s.querySelector('.buttons');
            } else {
                bar = document.createElement('p');
                bar.className = 'step-actions actions';
                s.appendChild(bar);
            }
            if (i > 0) {
                var p = document.createElement('button');
                p.type = 'button';
                p.className = 'btn btn-line step-prev';
                p.textContent = S.prev;
                p.addEventListener('click', function () { go(i - 1, true); });
                bar.insertBefore(p, bar.firstChild);
            }
            if (i < 3) {
                var n = document.createElement('button');
                n.type = 'button';
                n.className = 'btn btn-primary step-next';
                n.textContent = S.next;
                n.addEventListener('click', function () { go(i + 1, true); });
                bar.appendChild(n);
            }
        });

        function rowsOf(step) { return step.querySelector('tbody.pcrs-rows'); }

        function hasName(row, name) {
            return !!(name && row.querySelector('[name="' + name + '"]'));
        }

        function isFormHeader(row) {
            var td = row.firstElementChild;
            if (!td) return false;
            for (var c = td.firstElementChild; c; c = c.nextElementSibling)
                if (c.classList.contains('form-header') && !c.classList.contains('section-break')) return true;
            return false;
        }

        // Moves the rows of #dynamic-form to their step
        function dispatch() {
            var rows = Array.prototype.filter.call(dyn.children, function (el) { return el.tagName === 'TR'; });
            var groups = [], g = null;
            rows.forEach(function (r) {
                if (!g || isFormHeader(r)) { g = []; groups.push(g); }
                g.push(r);
            });
            groups.forEach(function (grp) {
                var ticket = grp.some(function (r) { return hasName(r, roles.message) || hasName(r, roles.subject); });
                grp.forEach(function (r) {
                    var to = ticket ? 2 : 1;
                    if (ticket && hasName(r, roles.priority)) to = 3;
                    r.classList.add('pcrs-dyn');
                    rowsOf(steps[to]).appendChild(r);
                });
            });
            notes();
            markErrors();
        }

        function notes() {
            var chosen = topic && topic.value;
            [1, 2].forEach(function (i) {
                var note = steps[i].querySelector('.step-note');
                var empty = !rowsOf(steps[i]).querySelector('tr');
                note.hidden = !empty;
                note.textContent = empty ? (chosen && i === 1 ? S.noContext : S.noTopic) : '';
            });
        }

        // Error messages printed by osTicket (div.error) and their step
        function errors() {
            var out = [];
            steps.forEach(function (s, i) {
                Array.prototype.forEach.call(s.querySelectorAll('div.error'), function (el) {
                    if (text(el) && text(el) !== '*') out.push({ el: el, step: i });
                });
            });
            return out;
        }

        function fieldOf(row) {
            return row && row.querySelector('textarea, select, input:not([type=hidden]):not([type=file])');
        }

        function labelOf(row) {
            var l = row && row.querySelector('label .required, label, .lbl');
            return l ? text(l).replace(/\s*\*$/, '') : '';
        }

        var uid = 0;
        function markErrors() {
            Array.prototype.forEach.call(form.querySelectorAll('tr.has-error'), function (r) {
                if (r.classList.contains('pcrs-err')) { r.classList.remove('has-error', 'pcrs-err'); }
            });
            errors().forEach(function (e) {
                var row = e.el.closest('tr');
                if (!row) return;
                if (!row.classList.contains('has-error')) row.classList.add('has-error', 'pcrs-err');
                if (!e.el.id) e.el.id = 'pcrs-err-' + (++uid);
                var f = fieldOf(row);
                if (f) {
                    f.setAttribute('aria-invalid', 'true');
                    var d = (f.getAttribute('aria-describedby') || '').split(/\s+/).filter(Boolean);
                    if (d.indexOf(e.el.id) < 0) { d.push(e.el.id); f.setAttribute('aria-describedby', d.join(' ')); }
                }
            });
        }

        function buildSummary() {
            var list = errors();
            var ul = summary.querySelector('ul');
            ul.innerHTML = '';
            list.forEach(function (e) {
                var row = e.el.closest('tr'), f = fieldOf(row);
                var li = document.createElement('li'), a = document.createElement('a');
                var label = labelOf(row);
                a.href = '#' + (f && f.id ? f.id : e.el.id);
                a.textContent = fmt(S.stepN, e.step + 1) + S.sep + (label ? label + S.sep : '') + text(e.el);
                a.addEventListener('click', function (ev) {
                    ev.preventDefault();
                    go(e.step, false);
                    focusField(row, e.el);
                });
                li.appendChild(a);
                ul.appendChild(li);
            });
            summary.querySelector('h2 span').textContent = list.length === 1 ? S.summary1 : fmt(S.summaryN, list.length);
            summary.hidden = !list.length;
            return list;
        }

        function focusField(row, fallback) {
            var editor = row && row.querySelector('.redactor-editor, [contenteditable=true]');
            var f = editor || fieldOf(row);
            if (f && f.offsetParent !== null) f.focus();
            else if (fallback) { fallback.setAttribute('tabindex', '-1'); fallback.focus(); }
        }

        function counts() {
            var c = [0, 0, 0, 0];
            errors().forEach(function (e) { c[e.step]++; });
            return c;
        }

        function refreshNav() {
            var c = counts();
            navItems.forEach(function (li, i) {
                li.className = i < current ? 'done' : (i === current ? 'current' : 'todo');
                if (c[i]) li.classList.add('has-error');
                var b = li.querySelector('button');
                if (i === current) b.setAttribute('aria-current', 'step');
                else b.removeAttribute('aria-current');
                li.querySelector('small').textContent = c[i] ? (c[i] === 1 ? S.fix1 : fmt(S.fixN, c[i])) : cfg.steps[i].hint;
                li.querySelector('.node').innerHTML = i < current && !c[i]
                    ? '<svg class="ic" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="m5 12.5 4.5 4.5L19 7"/></svg>'
                    : String(i + 1);
            });
            var label = fmt(S.stepOf, current + 1, 4) + S.sep + cfg.steps[current].title;
            document.getElementById('pcrs-overall-text').textContent = label;
            overall.querySelector('.progress span').style.width = ((current + 1) * 25) + '%';
            return label;
        }

        function updateRecap() {
            var sel = topic && topic.options[topic.selectedIndex];
            var subj = roles.subject && form.querySelector('[name="' + roles.subject + '"]');
            var files = form.querySelectorAll('#pcrs-step-3 .filedrop .files .file').length;
            var items = [
                [S.topic, sel && topic.value ? text(sel) : S.none],
                [S.subject, subj && subj.value.trim() ? subj.value.trim() : S.none],
                [S.files, files ? String(files) : S.none]
            ];
            recap.innerHTML = '<h3></h3><dl class="props"></dl>';
            recap.querySelector('h3').textContent = S.recap;
            var dl = recap.querySelector('dl');
            items.forEach(function (it) {
                var d = document.createElement('div');
                d.innerHTML = '<dt></dt><dd></dd>';
                d.firstChild.textContent = it[0];
                d.lastChild.textContent = it[1];
                dl.appendChild(d);
            });
            recap.hidden = false;
        }

        function go(i, focus) {
            current = Math.max(0, Math.min(3, i));
            steps.forEach(function (s, k) {
                s.hidden = k !== current;
                s.classList.toggle('current', k === current);
            });
            if (current === 3) updateRecap();
            var label = refreshNav();
            if (live) live.textContent = label;
            if (focus) {
                steps[current].focus({ preventScroll: true });
                var top = document.getElementById('pcrs-wizard').getBoundingClientRect().top;
                if (top < 0 || top > window.innerHeight / 2) document.getElementById('pcrs-wizard').scrollIntoView();
            }
        }

        // Enter in a one-line field goes to the next step instead of posting
        form.addEventListener('keydown', function (e) {
            if (e.key !== 'Enter' || current >= 3) return;
            var t = e.target;
            if (t.tagName === 'INPUT' && !/^(submit|button|reset|checkbox|radio|file)$/i.test(t.type)) {
                e.preventDefault();
                go(current + 1, true);
            }
        });
        form.addEventListener('reset', function () {
            setTimeout(function () { go(0, true); }, 0);
        });

        // Help topic change: same AJAX call and data as the core template,
        // the values of the dispatched rows included, then dispatch again.
        window.pcrsTopicChange = function (select) {
            var data = $(':input[name]', $('.pcrs-dyn').add(dyn)).serialize();
            var drop = function () {
                $('.pcrs-dyn').remove();
                $(dyn).empty();
            };
            if (!select.value) {
                drop();
                dispatch();
                buildSummary();
                refreshNav();
                return false;
            }
            form.setAttribute('aria-busy', 'true');
            if (live) live.textContent = S.loading;
            $.ajax('ajax.php/form/help-topic/' + select.value, {
                data: data,
                dataType: 'json',
                success: function (json) {
                    drop();
                    $(dyn).append(json.html);
                    $(document.head).append(json.media);
                    dispatch();
                    buildSummary();
                    refreshNav();
                    if (live) live.textContent = '';
                },
                complete: function () { form.removeAttribute('aria-busy'); }
            });
            return false;
        };

        dispatch();
        var errs = buildSummary();
        if (errs.length) {
            // Rejected POST: open the step of the first error, focus the list
            go(errs[0].step, false);
            summary.focus();
        } else if (cfg.posted) {
            go(3, false);
        } else {
            go(0, false);
        }
    }

    ready(function () {
        initMenus();
        initWizard();
    });
})();
