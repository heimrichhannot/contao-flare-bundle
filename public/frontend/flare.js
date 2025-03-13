((d, a, b) => {
    b = () => d.querySelectorAll(`form${a}, ${a} form`).forEach((f) => {
        if (f.method?.toUpperCase?.() !== 'GET') return;
        f.addEventListener('submit', (e) => {
            if (!f.action || !f.name) return;
            e.preventDefault();
            const url = new URL(f.action);
            url.searchParams.forEach((_, k) => {
                if (k.startsWith(f.name + '[')) url.searchParams.delete(k);
            });
            new FormData(f).forEach((v, k) => {
                url.searchParams.append(k, v);
            });
            window.location.href = url.toString();
        });
    });
    d.readyState === 'loading' ? d.addEventListener('DOMContentLoaded', b) : b();
})(document, '[data-flare-keep-query]');