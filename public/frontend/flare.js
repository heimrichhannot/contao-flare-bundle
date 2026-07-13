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
 * pagination query parameters are preserved. Qualifying list view URLs are
 * also remembered per browser tab (sessionStorage), so the button keeps
 * pointing at the filtered list when navigating between reader pages.
 */
(() => {
    'use strict';

    const KEEP_QUERY_SELECTOR = '[data-flare-form="keep-query"]';
    const QUERY_FIELD_SELECTOR = '[data-flare-form="query-field"]';
    const BACK_BUTTON_SELECTOR = '[data-flare-back-button]';
    const BACK_STORAGE_PREFIX = 'flare:back:';

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

    // strip a single trailing slash so "/list/" matches "/list"
    function normalizePathname(pathname) {
        return pathname.length > 1 && pathname.endsWith('/')
            ? pathname.slice(0, -1)
            : pathname;
    }

    function matchesPathname(url, other) {
        return normalizePathname(url.pathname) === normalizePathname(other.pathname);
    }

    function getReferrerUrl() {
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

        return referrer;
    }

    function sessionStorageSet(key, href) {
        try {
            window.sessionStorage.setItem(key, href);
        } catch {
            // sessionStorage unavailable (e.g., blocked by browser settings)
        }
    }

    function sessionStorageGet(key) {
        try {
            return window.sessionStorage.getItem(key);
        } catch {
            return null;
        }
    }

    function readStoredBackUrl(key, fallback) {
        const stored = sessionStorageGet(key);
        if (!stored) {
            return null;
        }

        let url;
        try {
            url = new URL(stored);
        } catch {
            return null;
        }

        if (url.origin !== window.location.origin
            || url.href === window.location.href
            || !matchesPathname(url, fallback)
        ) {
            return null;
        }

        return url;
    }

    function upgradeBackButton(anchor, href) {
        anchor.href = href;
        anchor.hidden = false;

        // drop the template's "no URL available" styling, if any
        const noUrlClass = anchor.getAttribute('data-flare-back-button-no-url-class');
        if (noUrlClass) {
            anchor.classList.remove(...noUrlClass.split(/\s+/).filter(Boolean));
        }
    }

    function initBackButton(anchor, referrer) {
        // the server-rendered fallback href is the configured list view page's URL
        let fallback = null;
        const fallbackHref = anchor.getAttribute('href');
        if (fallbackHref) {
            try {
                fallback = new URL(fallbackHref, window.location.href);
            } catch {
                // keep null
            }
        }

        const storageKey = fallback ? (BACK_STORAGE_PREFIX + normalizePathname(fallback.pathname)) : null;

        if (fallback && referrer && matchesPathname(referrer, fallback)) {
            // arrived from the configured list view page: link back to it with its
            // query parameters intact and remember it for reader-to-reader navigation
            sessionStorageSet(storageKey, referrer.href);
            upgradeBackButton(anchor, referrer.href);
            return;
        }

        if (referrer && anchor.getAttribute('data-flare-back-button') === 'any-referrer') {
            upgradeBackButton(anchor, referrer.href);
            return;
        }

        // no qualifying referrer (e.g. navigated from one reader page to another):
        // restore the list view URL remembered for this list page, if any
        const stored = storageKey ? readStoredBackUrl(storageKey, fallback) : null;
        if (stored) {
            upgradeBackButton(anchor, stored.href);
            // return;
        }

        // keep the server-rendered fallback href; anchors without href stay hidden
    }

    function initBackButtons() {
        const referrer = getReferrerUrl();
        document.querySelectorAll(BACK_BUTTON_SELECTOR)
            .forEach((anchor) => initBackButton(anchor, referrer))
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
