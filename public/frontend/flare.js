((d, a, b, l) => {
    b = () => d.querySelectorAll(`form${a}, ${a} form`).forEach(f => {
        if (f.method?.toUpperCase?.() !== 'GET') return;
        l = add => (e => {
            if (!f.action || !f.name) return;
            e.preventDefault();
            const url = new URL(f.action), del = [];
            for (const k of url.searchParams.keys()) {
                if (k.startsWith(f.name + '[')) del.push(k);
            }
            del.forEach(key => url.searchParams.delete(key));
            add ? new FormData(f).forEach((v, k) => {
                url.searchParams.append(k, v);
            }) : null;
            window.location.href = url.toString();
        });
        f.addEventListener('submit', l(1));
        f.addEventListener('reset', l(0));
    });
    d.readyState === 'loading' ? d.addEventListener('DOMContentLoaded', b) : b();
})(document, '[data-flare-keep-query]');