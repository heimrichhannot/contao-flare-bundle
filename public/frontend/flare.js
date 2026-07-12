/**
 * FLARE frontend script.
 *
 * Makes FLARE filter forms preserve foreign query parameters:
 * on-submit or reset of a GET form marked with data-flare-form="keep-query",
 * the form's own parameters are stripped from the action URL, the current
 * form data is re-appended (submit only), and the browser navigates there.
 */
(() => {
    'use strict';

    const KEEP_QUERY_SELECTOR = '[data-flare-form="keep-query"]';
    const QUERY_FIELD_SELECTOR = '[data-flare-form="query-field"]';

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

    function onDocumentReady(callback) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', callback);
        } else {
            callback();
        }
    }

    onDocumentReady(initKeepQueryForms);
})();
