/* ==========================================================
   Quiz Generator — searchable "Subject / Class" picker
   public/teacher/assets/js/quiz_class_picker.js

   Builds a searchable, scrollable dropdown from the native
   <select id="qgOffering">. The select remains the source of
   truth (FormData + quiz_generator.js read from it); this script
   only changes its value and fires a 'change' event.
   Load AFTER quiz_generator.js.
   ========================================================== */
(function () {
    'use strict';

    var select = document.getElementById('qgOffering');
    var input = document.getElementById('qgOfferingSearch');
    var list = document.getElementById('qgComboList');
    var combo = document.getElementById('qgCombo');
    var toggle = document.getElementById('qgComboToggle');
    if (!select || !input || !list || !combo) return;

    var options = Array.prototype.slice.call(select.options)
        .filter(function (o) { return o.value !== ''; })
        .map(function (o) {
            return {
                value: o.value,
                subject: o.dataset.subject || o.textContent.trim(),
                meta: o.dataset.meta || '',
                label: o.textContent.replace(/\s+/g, ' ').trim()
            };
        });

    var visible = [];
    var activeIndex = -1;

    function esc(str) {
        return String(str).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    function selectedOption() {
        for (var i = 0; i < options.length; i++) {
            if (options[i].value === select.value) return options[i];
        }
        return null;
    }

    function syncValidity() {
        input.setCustomValidity(select.value ? '' : 'Please choose a class from the list.');
    }

    function render(filterText) {
        var q = (filterText || '').toLowerCase().trim();
        visible = options.filter(function (o) {
            return !q || (o.subject + ' ' + o.meta).toLowerCase().indexOf(q) !== -1;
        });
        if (!visible.length) {
            list.innerHTML = '<li class="qg-combo__empty">No classes match “' + esc(filterText.trim()) + '”.</li>';
            activeIndex = -1;
            return;
        }
        list.innerHTML = visible.map(function (o, i) {
            var sel = o.value === select.value;
            return '<li class="qg-combo__option' + (sel ? ' is-selected' : '') + '" role="option" data-index="' + i + '"'
                + ' aria-selected="' + (sel ? 'true' : 'false') + '">'
                + '<span class="qg-combo__text"><span class="qg-combo__name">' + esc(o.subject) + '</span>'
                + '<span class="qg-combo__meta">' + esc(o.meta) + '</span></span>'
                + (sel ? '<i class="fas fa-check qg-combo__check"></i>' : '')
                + '</li>';
        }).join('');
        // start on the current selection, otherwise on the first match when searching
        activeIndex = -1;
        visible.forEach(function (o, i) { if (o.value === select.value) activeIndex = i; });
        if (activeIndex === -1 && q) activeIndex = 0;
        paintActive(false);
    }

    function paintActive(scroll) {
        var items = list.querySelectorAll('.qg-combo__option');
        items.forEach(function (el, i) { el.classList.toggle('is-active', i === activeIndex); });
        if (scroll && items[activeIndex]) items[activeIndex].scrollIntoView({ block: 'nearest' });
    }

    function open() {
        if (!list.hidden) return;
        render(selectedOption() && input.value === selectedOption().label ? '' : input.value);
        list.hidden = false;
        combo.classList.add('is-open');
        input.setAttribute('aria-expanded', 'true');
        paintActive(true);
    }

    function close() {
        list.hidden = true;
        combo.classList.remove('is-open');
        input.setAttribute('aria-expanded', 'false');
        // restore the text of the chosen class if the user abandoned a half-typed search
        var sel = selectedOption();
        input.value = sel ? sel.label : '';
        syncValidity();
    }

    function choose(opt) {
        var changed = select.value !== opt.value;
        select.value = opt.value;
        input.value = opt.label;
        syncValidity();
        close();
        if (changed) select.dispatchEvent(new Event('change', { bubbles: true }));
    }

    input.addEventListener('focus', open);
    input.addEventListener('click', open);

    input.addEventListener('input', function () {
        // typing invalidates the previous choice until a class is picked again
        if (select.value) {
            select.value = '';
            select.dispatchEvent(new Event('change', { bubbles: true }));
        }
        syncValidity();
        if (list.hidden) {
            list.hidden = false;
            combo.classList.add('is-open');
            input.setAttribute('aria-expanded', 'true');
        }
        render(input.value);
    });

    input.addEventListener('keydown', function (e) {
        if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
            e.preventDefault();
            if (list.hidden) { open(); return; }
            if (!visible.length) return;
            var step = e.key === 'ArrowDown' ? 1 : -1;
            activeIndex = (activeIndex + step + visible.length) % visible.length;
            paintActive(true);
        } else if (e.key === 'Enter') {
            if (!list.hidden) {
                e.preventDefault(); // don't submit the form while picking
                if (activeIndex >= 0 && visible[activeIndex]) choose(visible[activeIndex]);
                else if (visible.length === 1) choose(visible[0]);
            }
        } else if (e.key === 'Escape') {
            if (!list.hidden) { e.preventDefault(); close(); }
        } else if (e.key === 'Tab') {
            if (!list.hidden) close();
        }
    });

    // mousedown (not click) so the input doesn't blur/close before the pick registers
    list.addEventListener('mousedown', function (e) {
        var li = e.target.closest('.qg-combo__option');
        if (!li) return;
        e.preventDefault();
        var opt = visible[Number(li.dataset.index)];
        if (opt) choose(opt);
    });
    list.addEventListener('mousemove', function (e) {
        var li = e.target.closest('.qg-combo__option');
        if (!li) return;
        var idx = Number(li.dataset.index);
        if (idx !== activeIndex) { activeIndex = idx; paintActive(false); }
    });

    toggle.addEventListener('mousedown', function (e) {
        e.preventDefault();
        if (list.hidden) { input.focus(); open(); } else { close(); }
    });

    document.addEventListener('mousedown', function (e) {
        if (!combo.contains(e.target) && !list.hidden) close();
    });

    // initial state (a class may be preselected from Class Overview → New Quiz)
    var initial = selectedOption();
    if (initial) {
        input.value = initial.label;
        select.dispatchEvent(new Event('change', { bubbles: true })); // loads that class's PDF modules
    }
    syncValidity();
})();
