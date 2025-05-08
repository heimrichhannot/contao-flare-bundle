((d, a, b) => {
    b = () => d.querySelectorAll(`form${a}, ${a} form`).forEach((f) => {
        if (f.method?.toUpperCase?.() !== 'GET') return;
        f.addEventListener('submit', (e) => {
            if (!f.action || !f.name) return;
            e.preventDefault();
            const url = new URL(f.action);
            const toRemove = [];
            for (const key of url.searchParams.keys()) {
                if (key.startsWith(f.name + '[')) toRemove.push(key);
            }
            toRemove.forEach(key => url.searchParams.delete(key));
            new FormData(f).forEach((v, k) => {
                url.searchParams.append(k, v);
            });
            window.location.href = url.toString();
        });
    });
    d.readyState === 'loading' ? d.addEventListener('DOMContentLoaded', b) : b();
})(document, '[data-flare-keep-query]');