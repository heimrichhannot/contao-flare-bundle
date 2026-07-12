/**
 * FLARE frontend script.
 *
 * Makes FLARE filter forms preserve foreign query parameters:
 * on-submit or reset of a GET form marked with data-flare-form="keep-query",
 * the form's own parameters are stripped from the action URL, the current
 * form data is re-appended (submit only), and the browser navigates there.
 *
 * Upgrades back-to-list buttons marked with data-flare-back-button:
 * when document.referrer qualifies as the originating list view, the anchor's
 * href is replaced with the referrer URL so the list view's filter and
 * pagination query parameters are preserved.
 */
(() => {
    'use strict';

    const KEEP_QUERY_SELECTOR = '[data-flare-form="keep-query"]';
    const QUERY_FIELD_SELECTOR = '[data-flare-form="query-field"]';
    const BACK_BUTTON_SELECTOR = '[data-flare-back-button]';

    function stripOwnParams(searchParams, form) {
        const fieldNames = Array.from(form.querySelectorAll(QUERY_FIELD_SELECTOR)).map((field) => field.name);

        // Collect first, then delete -- searchParams must not be mutated while iterating its keys
        const keysToDelete = [];
        for (const key of searchParams.keys()) {
            if (key.startsWith(`${form.name}[`) || fieldNames.includes(key)) {
                keysToDelete.push(key);
            }
        }
        for (const key of keysToDelete) {
            searchParams.delete(key);
        }
    }

    function buildTargetUrl(form, includeFormData) {
        const url = new URL(form.action);

        stripOwnParams(url.searchParams, form);

        if (includeFormData) {
            for (const [key, value] of new FormData(form)) {
                url.searchParams.append(key, value);
            }
        }

        return url;
    }

    function handleFormEvent(form, event, includeFormData) {
        if (!form.action || !form.name) {
            return;
        }

        event.preventDefault();

        window.location.href = buildTargetUrl(form, includeFormData).toString();
    }

    function initKeepQueryForms() {
        const forms = document.querySelectorAll(`form${KEEP_QUERY_SELECTOR}, ${KEEP_QUERY_SELECTOR} form`);

        for (const form of forms) {
            // form.method may be an input element if the form has a field named "method"
            if (form.method?.toUpperCase?.() !== 'GET') {
                continue;
            }

            form.addEventListener('submit', (event) => handleFormEvent(form, event, true));
            form.addEventListener('reset', (event) => handleFormEvent(form, event, false));
        }
    }

    function normalizePathname(pathname) {
        // strip a single trailing slash so "/list/" matches "/list"
        return pathname.length > 1 && pathname.endsWith('/') ? pathname.slice(0, -1) : pathname;
    }

    function getQualifyingReferrer(anchor) {
        if (!document.referrer) {
            return null;
        }

        let referrer;
        try {
            referrer = new URL(document.referrer);
        } catch {
            return null;
        }

        if (referrer.origin !== window.location.origin) {
            return null;
        }

        if (referrer.href === window.location.href) {
            // never link back to the current page itself
            return null;
        }

        if (anchor.getAttribute('data-flare-back-button') === 'any-referrer') {
            return referrer;
        }

        // strict mode: the referrer path must match the configured list view page's path,
        // which is the path of the server-rendered fallback href
        const fallbackHref = anchor.getAttribute('href');
        if (!fallbackHref) {
            return null;
        }

        const fallback = new URL(fallbackHref, window.location.href);

        return normalizePathname(referrer.pathname) === normalizePathname(fallback.pathname) ? referrer : null;
    }

    function initBackButtons() {
        for (const anchor of document.querySelectorAll(BACK_BUTTON_SELECTOR)) {
            const referrer = getQualifyingReferrer(anchor);
            if (!referrer) {
                // keep the server-rendered fallback href; anchors without href stay hidden
                continue;
            }

            anchor.href = referrer.href;
            anchor.hidden = false;
        }
    }

    function onDocumentReady(callback) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', callback);
        } else {
            callback();
        }
    }

    onDocumentReady(() => {
        initKeepQueryForms();
        initBackButtons();
    });
})();
