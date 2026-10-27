/* =====================================================================
   INPUT GUARDS — user create / edit forms
   ---------------------------------------------------------------------
   - LRN fields:   only digits can be typed/pasted (max 12).
   - Name fields:  only letters (any language) and single spaces.
   Characters that are not allowed are never inserted; instead a clear
   inline error message is shown under the field. Messages are also shown
   on blur / submit when a value is incomplete (e.g. LRN shorter than 12).
   Works through event delegation, so it covers the Add User modal and
   the Edit User form (and anything rendered later).
===================================================================== */
(function () {
    if (window.__inputGuardsLoaded) return;
    window.__inputGuardsLoaded = true;

    var LRN_LENGTH = 12;

    // input name -> { label, required }
    var NAME_FIELDS = {
        firstname:          { label: 'First name',    required: true  },
        lastname:           { label: 'Last name',     required: true  },
        middlename:         { label: 'Middle name',   required: false },
        teacher_firstname:  { label: 'First name',    required: true  },
        teacher_lastname:   { label: 'Last name',     required: true  },
        teacher_middlename: { label: 'Middle name',   required: false },
        guardian_name:      { label: 'Guardian name', required: false }
    };

    var LETTER_OR_SPACE = /^[\p{L}\p{M} ]*$/u;
    var NOT_LETTER_OR_SPACE = /[^\p{L}\p{M} ]/gu;   // global: only for replace()
    var HAS_BAD_NAME_CHAR = /[^\p{L}\p{M} ]/u;      // non-global: for test()
    var FINAL_NAME = /^[\p{L}\p{M}]+(?: [\p{L}\p{M}]+)*$/u;

    // Email fields (student_email, email) follow the Gmail username rules.
    var EMAIL_FIELDS = { student_email: 1, email: 1 };
    var EMAIL_MIN = 6, EMAIL_MAX = 30;

    // Returns the first rule the value breaks, or null. finalCheck adds the "is it complete" rules.
    function emailProblem(value, finalCheck, required) {
        if (value === '') return (finalCheck && required) ? 'Email is required.' : null;
        if (/\s/.test(value)) return 'Email cannot contain spaces.';
        var at = value.indexOf('@');
        if (value.indexOf('@', at + 1) !== -1 && at !== -1) return 'Email can only contain one @.';
        var local = at === -1 ? value : value.slice(0, at);
        var domain = at === -1 ? '' : value.slice(at + 1);
        if (/[^A-Za-z0-9.]/.test(local)) return 'Email username can only contain letters (a-z), numbers (0-9) and periods (.). Other symbols like _ - + & = \' , < > are not allowed.';
        if (local.charAt(0) === '.') return 'Email username cannot start with a period.';
        if (local.indexOf('..') !== -1) return 'Email username cannot have more than one period in a row.';
        if (local.length > EMAIL_MAX) return 'Email username must be at most ' + EMAIL_MAX + ' characters.';
        if (at !== -1 && local.slice(-1) === '.') return 'Email username cannot end with a period.';
        if (/[^A-Za-z0-9.-]/.test(domain)) return 'Email domain can only contain letters, numbers, periods and hyphens.';
        if (/^[.-]/.test(domain) || domain.indexOf('..') !== -1) return 'Email domain is invalid.';
        if (finalCheck) {
            if (at === -1) return 'Email must include @ and a domain (e.g. name@gmail.com).';
            if (local.length < EMAIL_MIN) return 'Email username (before @) must be at least ' + EMAIL_MIN + ' characters (you entered ' + local.length + ').';
            if (!/^(?:[A-Za-z0-9](?:[A-Za-z0-9-]*[A-Za-z0-9])?\.)+[A-Za-z]{2,}$/.test(domain)) return 'Email domain is invalid (e.g. gmail.com).';
        }
        return null;
    }

    function kindOf(el) {
        if (!el || el.tagName !== 'INPUT') return null;
        if (el.name === 'lrn') return 'lrn';
        if (EMAIL_FIELDS[el.name]) return 'email';
        if (NAME_FIELDS[el.name]) return 'name';
        return null;
    }

    function isVisible(el) {
        return !el.disabled && el.getClientRects().length > 0;
    }

    // ---------- inline error message ----------
    function messageEl(input) {
        var id = input.dataset.igMsg;
        var el = id ? document.getElementById(id) : null;
        if (el) return el;
        el = document.createElement('small');
        el.id = 'ig-msg-' + Math.random().toString(36).slice(2, 9);
        el.className = 'ig-error';
        el.setAttribute('role', 'alert');
        el.style.cssText = 'display:none;margin-top:6px;font-size:.78rem;line-height:1.35;color:#dc2626;';
        input.dataset.igMsg = el.id;
        var anchor = input.closest('.aum-input-wrap') || input;
        anchor.insertAdjacentElement('afterend', el);
        return el;
    }

    function showError(input, text) {
        var el = messageEl(input);
        el.innerHTML = '<i class="fas fa-exclamation-circle"></i> ';
        el.appendChild(document.createTextNode(text));
        el.style.display = 'block';
        input.setAttribute('aria-invalid', 'true');
        input.style.borderColor = '#dc2626';
        input.setCustomValidity(text);
    }

    function clearError(input) {
        var id = input.dataset.igMsg;
        var el = id ? document.getElementById(id) : null;
        if (el) el.style.display = 'none';
        input.removeAttribute('aria-invalid');
        input.style.borderColor = '';
        input.setCustomValidity('');
        clearTimeout(input._igTimer);
    }

    function blockedMessage(input) {
        if (kindOf(input) === 'lrn') return 'LRN accepts numbers only (0-9). Letters, spaces and symbols are not allowed.';
        return NAME_FIELDS[input.name].label + ' accepts letters only. Numbers and symbols are not allowed.';
    }

    // Message for a character that was blocked while typing. It disappears on its own
    // unless the value itself is still invalid.
    function flashBlocked(input, msg) {
        showError(input, msg || blockedMessage(input));
        clearTimeout(input._igTimer);
        input._igTimer = setTimeout(function () { validate(input, false); }, 2500);
    }

    // ---------- validation ----------
    // finalCheck = true -> also complain about required / incomplete values.
    function validate(input, finalCheck) {
        var kind = kindOf(input);
        if (!kind) return true;
        var value = input.value;

        if (kind === 'lrn') {
            if (value === '') {
                if (finalCheck) { showError(input, 'LRN is required.'); return false; }
            } else if (!/^\d+$/.test(value)) {
                showError(input, 'LRN accepts numbers only (0-9).'); return false;
            } else if (value.length !== LRN_LENGTH && finalCheck) {
                showError(input, 'LRN must be exactly ' + LRN_LENGTH + ' digits (you entered ' + value.length + ').'); return false;
            }
            clearError(input);
            return true;
        }

        if (kind === 'email') {
            var prob = emailProblem(value, finalCheck, input.required);
            if (prob) { showError(input, prob); return false; }
            clearError(input);
            return true;
        }

        var cfg = NAME_FIELDS[input.name];
        var trimmed = value.trim();
        if (trimmed === '') {
            if (finalCheck && (cfg.required || input.required)) { showError(input, cfg.label + ' is required.'); return false; }
            clearError(input);
            return true;
        }
        if (!FINAL_NAME.test(trimmed)) {
            showError(input, cfg.label + ' can only contain letters, with single spaces between words.');
            return false;
        }
        clearError(input);
        return true;
    }

    // Remove disallowed characters from a string for this field.
    function sanitize(input, text) {
        if (kindOf(input) === 'lrn') return text.replace(/\D/g, '');
        if (kindOf(input) === 'email') return text.replace(/[^A-Za-z0-9.@-]/g, '');
        return text.replace(NOT_LETTER_OR_SPACE, '');
    }

    function allowedTyped(input, text) {
        if (kindOf(input) === 'lrn') return /^\d*$/.test(text);
        return LETTER_OR_SPACE.test(text);
    }

    // ---------- events ----------
    // Typing (and paste/drop where the browser reports the text): refuse bad characters.
    document.addEventListener('beforeinput', function (e) {
        var input = e.target;
        var kind = kindOf(input);
        if (!kind || e.isComposing) return;
        if (e.inputType.indexOf('insert') !== 0) return;      // deletes etc. are always fine
        var text = e.data;
        if (typeof text !== 'string' || text === '') return;  // handled by the input event below

        if (kind === 'email') {
            if (text.length !== 1) return;   // paste: cleaned up by the input handler
            var v0 = input.value, s0 = input.selectionStart, e0 = input.selectionEnd;
            var result = v0.slice(0, s0) + text + v0.slice(e0);
            var why = emailProblem(result, false, false);
            if (why) { e.preventDefault(); flashBlocked(input, why); }
            return;
        }
        if (!allowedTyped(input, text)) {
            // A single typed character is simply refused. Longer text (paste / drop / autofill)
            // goes through and the input handler below strips the bad characters.
            if (text.length === 1) {
                e.preventDefault();
                flashBlocked(input);
            }
            return;
        }
        if (kind === 'name' && text.indexOf(' ') !== -1) {
            // no leading space, no double spaces
            var start = input.selectionStart, end = input.selectionEnd, v = input.value;
            var before = v.slice(0, start), after = v.slice(end);
            if (before === '' || /\s$/.test(before) || /^\s/.test(after) && text === ' ') {
                e.preventDefault();
                flashBlocked(input);
            }
        }
    });

    // Fallback for paste / drop / autofill / IME: strip whatever slipped through.
    document.addEventListener('input', function (e) {
        var input = e.target;
        var kind = kindOf(input);
        if (!kind || e.isComposing) return;

        var original = input.value;
        var cleaned = sanitize(input, original);
        if (kind === 'name') cleaned = cleaned.replace(/^ +/, '').replace(/ {2,}/g, ' ');
        if (kind === 'lrn') cleaned = cleaned.slice(0, LRN_LENGTH);
        if (kind === 'email') cleaned = cleaned.replace(/\s/g, '');

        if (cleaned !== original) {
            var pos = input.selectionStart;
            var removed = original.length - cleaned.length;
            input.value = cleaned;
            try { input.setSelectionRange(Math.max(0, pos - removed), Math.max(0, pos - removed)); } catch (_) {}
            if (kind === 'email') { validate(input, false); return; }
            if (kind === 'lrn' ? /\D/.test(original) : HAS_BAD_NAME_CHAR.test(original)) {
                flashBlocked(input);
                return;
            }
        }
        // Normal typing: only clear an existing error, never nag about "incomplete" yet.
        if (!input._igTimer) validate(input, false);
    });

    document.addEventListener('compositionend', function (e) {
        var input = e.target;
        if (kindOf(input)) input.dispatchEvent(new Event('input', { bubbles: true }));
    });

    document.addEventListener('focusout', function (e) {
        var input = e.target;
        if (!kindOf(input)) return;
        if (kindOf(input) === 'name') input.value = input.value.trim();
        // Leave a transient "character not allowed" notice to expire by itself when the value
        // is fine, so the layout doesn't jump under the cursor (e.g. when pressing Save).
        var hasNotice = !!input._igTimer && kindOf(input) !== 'email';
        if (hasNotice) {
            var v = input.value, ok = kindOf(input) === 'lrn'
                ? v.length === LRN_LENGTH
                : (v === '' ? !(NAME_FIELDS[input.name].required || input.required) : FINAL_NAME.test(v));
            if (ok) return;
        }
        clearTimeout(input._igTimer);
        input._igTimer = null;
        validate(input, true);
    });

    // Make mobile keyboards show digits for the LRN and set sensible attributes.
    function prepare(root) {
        (root || document).querySelectorAll('input[name="lrn"]').forEach(function (i) {
            i.setAttribute('inputmode', 'numeric');
            i.setAttribute('autocomplete', 'off');
            i.setAttribute('maxlength', String(LRN_LENGTH));
            i.removeAttribute('pattern'); // our own validation (with a clear message) replaces the generic browser one
            i.setAttribute('title', 'Numbers only, exactly ' + LRN_LENGTH + ' digits');
        });
        (root || document).querySelectorAll('input').forEach(function (i) {
            if (NAME_FIELDS[i.name]) i.setAttribute('autocomplete', 'off');
            if (EMAIL_FIELDS[i.name]) {
                // type=email blocks caret/selection APIs and its own rules differ from Gmail's,
                // so use a text input with an email keyboard instead.
                i.type = 'text';
                i.setAttribute('inputmode', 'email');
                i.setAttribute('autocomplete', 'off');
                i.setAttribute('autocapitalize', 'none');
                i.setAttribute('spellcheck', 'false');
            }
        });
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', function () { prepare(); });
    else prepare();

    // Block submit (create modal + edit form) while a guarded field is invalid.
    // Capture phase on document runs before the forms' own submit handlers.
    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (!form || !form.querySelectorAll) return;
        var firstBad = null;
        form.querySelectorAll('input[name="lrn"], input[name="' + Object.keys(NAME_FIELDS).concat(Object.keys(EMAIL_FIELDS)).join('"], input[name="') + '"]').forEach(function (input) {
            if (!isVisible(input)) { clearError(input); return; }
            if (!validate(input, true) && !firstBad) firstBad = input;
        });
        if (firstBad) {
            e.preventDefault();
            e.stopImmediatePropagation();
            firstBad.focus();
        }
    }, true);

    // Clear messages when a form is reset / modal reopened.
    document.addEventListener('reset', function (e) {
        var form = e.target;
        if (!form || !form.querySelectorAll) return;
        setTimeout(function () {
            form.querySelectorAll('.ig-error').forEach(function (el) { el.style.display = 'none'; });
            form.querySelectorAll('[aria-invalid]').forEach(function (i) {
                i.removeAttribute('aria-invalid'); i.style.borderColor = ''; i.setCustomValidity('');
            });
        }, 0);
    }, true);

    // Edit form fills values programmatically: clear any stale error from the previous user.
    window.inputGuardsReset = function (root) {
        (root || document).querySelectorAll('input').forEach(function (i) { if (kindOf(i)) clearError(i); });
    };
})();
