/* =====================================================================
   PHONE GUARD - contact number fields (Philippine mobile numbers)
   Applies to input[name="contact"], input[name="guardian_contact"] and
   anything marked data-ph-mobile (Add User modal + Edit User form).
   Only digits and a single leading "+" can be typed or pasted, and a red
   inline message explains anything that gets refused.
   Valid formats: 09XXXXXXXXX or +639XXXXXXXXX.
===================================================================== */
(function () {
    if (window.__phoneGuardLoaded) return;
    window.__phoneGuardLoaded = true;

    var FORMAT_MSG = 'Enter a valid Philippine mobile number: 09XXXXXXXXX or +639XXXXXXXXX.';
    var BLOCKED_MSG = 'Contact number accepts numbers only (0-9). Letters, spaces and symbols are not allowed.';

    function isPhoneInput(el) {
        return !!el && el.tagName === 'INPUT' &&
            (el.name === 'contact' || el.name === 'guardian_contact' || el.hasAttribute('data-ph-mobile'));
    }
    function maxLen(value) { return value.charAt(0) === '+' ? 13 : 11; }
    function isValid(value) { return /^(?:09\d{9}|\+639\d{9})$/.test(value.replace(/[\s-]+/g, '')); }

    function cleanPhone(value) {
        var plus = value.replace(/^\s+/, '').charAt(0) === '+';
        var digits = value.replace(/\D/g, '');
        return ((plus ? '+' : '') + digits).slice(0, plus ? 13 : 11);
    }

    // ----- inline message (same look as the other user-form fields) -----
    function msgEl(input) {
        var el = input._pgMsg;
        if (el && el.isConnected) return el;
        el = document.createElement('small');
        el.className = 'ig-error';
        el.setAttribute('role', 'alert');
        el.style.cssText = 'display:none;margin-top:6px;font-size:.78rem;line-height:1.35;color:#dc2626;';
        var anchor = input.closest('.aum-input-wrap') || input;
        anchor.insertAdjacentElement('afterend', el);
        input._pgMsg = el;
        return el;
    }
    function showError(input, text) {
        var el = msgEl(input);
        el.innerHTML = '<i class="fas fa-exclamation-circle"></i> ';
        el.appendChild(document.createTextNode(text));
        el.style.display = 'block';
        input.setAttribute('aria-invalid', 'true');
        input.style.borderColor = '#dc2626';
    }
    function clearError(input) {
        if (input._pgMsg) input._pgMsg.style.display = 'none';
        input.removeAttribute('aria-invalid');
        input.style.borderColor = '';
        clearTimeout(input._pgTimer);
    }
    // Message for a refused character; goes away by itself unless the number is still invalid.
    function flash(input, text) {
        showError(input, text);
        clearTimeout(input._pgTimer);
        input._pgTimer = setTimeout(function () { settle(input, false); }, 2500);
    }
    function settle(input, finalCheck) {
        var v = input.value.trim();
        if (v === '') {
            if (finalCheck && input.required) { showError(input, 'Contact number is required.'); return false; }
            clearError(input); return true;
        }
        if (finalCheck && !isValid(v)) { showError(input, FORMAT_MSG); return false; }
        clearError(input); return true;
    }

    // Typing: block any printable key that is not a digit (or a leading "+").
    document.addEventListener('keydown', function (e) {
        var input = e.target;
        if (!isPhoneInput(input) || e.ctrlKey || e.metaKey || e.altKey) return;
        if (!e.key || e.key.length !== 1) return;            // Backspace, arrows, Tab, etc.
        var hasSelection = input.selectionStart !== input.selectionEnd;
        if (/^[0-9]$/.test(e.key)) {
            if (!hasSelection && input.value.length >= maxLen(input.value)) {
                e.preventDefault();
                flash(input, 'Too many digits. A mobile number is ' + (input.value.charAt(0) === '+' ? '+639XXXXXXXXX (13 characters).' : '09XXXXXXXXX (11 digits).'));
            }
            return;
        }
        var atStart = (input.selectionStart || 0) === 0;
        if (e.key === '+' && atStart && input.value.charAt(0) !== '+') return;
        e.preventDefault();
        flash(input, e.key === '+' ? 'The + sign can only be used once, at the very start (e.g. +639XXXXXXXXX).' : BLOCKED_MSG);
    }, true);

    // Mobile keyboards / IME: block before insertion where supported.
    document.addEventListener('beforeinput', function (e) {
        var input = e.target;
        if (!isPhoneInput(input)) return;
        if (!e.inputType || e.inputType.indexOf('insert') !== 0 || !e.data) return;
        if (e.inputType === 'insertFromPaste' || e.inputType === 'insertFromDrop') return;
        var ok = /^[0-9]+$/.test(e.data) ||
                 (e.data === '+' && (input.selectionStart || 0) === 0 && input.value.charAt(0) !== '+');
        if (!ok) { e.preventDefault(); flash(input, BLOCKED_MSG); }
    }, true);

    // Paste / drop / autofill / anything else: clean the value and say so.
    function sanitize(e) {
        var input = e.target;
        if (!isPhoneInput(input)) return;
        var before = input.value, cleaned = cleanPhone(before);
        if (cleaned !== before) {
            input.value = cleaned;
            var stripped = before.replace(/[\s\-]/g, '');
            if (/[^\d+]/.test(stripped) || stripped.replace(/^\+/, '').indexOf('+') !== -1) flash(input, BLOCKED_MSG);
            else if (cleaned.length < stripped.length) flash(input, 'Too many digits. The extra digits were removed.');
            return;
        }
        if (e.type === 'input' && input.getAttribute('aria-invalid') === 'true' && isValid(cleaned)) clearError(input);
    }
    document.addEventListener('input', sanitize, true);
    document.addEventListener('change', sanitize, true);

    // Leaving the field: check the finished number.
    document.addEventListener('focusout', function (e) {
        var input = e.target;
        if (!isPhoneInput(input)) return;
        input.value = cleanPhone(input.value);
        settle(input, input.value.trim() !== '');
    });

    // When the browser rejects the form (required / pattern), show the message under the field too.
    document.addEventListener('invalid', function (e) {
        var input = e.target;
        if (!isPhoneInput(input)) return;
        var v = input.value.trim();
        showError(input, v === '' ? 'Contact number is required.' : FORMAT_MSG);
    }, true);

    // Clear messages when a form is reset.
    document.addEventListener('reset', function (e) {
        var form = e.target;
        if (form && form.querySelectorAll) form.querySelectorAll('input').forEach(function (i) { if (isPhoneInput(i)) clearError(i); });
    }, true);

    // Clean any pre-filled value (e.g. Edit form) right away.
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('input[name="contact"], input[name="guardian_contact"], input[data-ph-mobile]')
            .forEach(function (i) { i.value = cleanPhone(i.value); });
    });
})();
