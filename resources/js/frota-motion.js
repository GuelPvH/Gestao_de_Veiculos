const instances = new WeakMap();
const states = ['idle', 'hover', 'click', 'intro', 'loading'];
const durations = Object.freeze({ click: 800, intro: 1200 });

export function mount(svg, options = {}) {
    if (!svg || !svg.matches('svg.frota-motion')) {
        throw new TypeError('Informe o SVG horizontal com a classe frota-motion.');
    }
    if (instances.has(svg)) return instances.get(svg);

    const doc = svg.ownerDocument;
    const trigger = options.trigger || svg;
    const media = window.matchMedia ? window.matchMedia('(prefers-reduced-motion: reduce)') : null;
    let reduced = Boolean(media && media.matches);
    let state = 'idle';
    let timer = null;
    let destroyed = false;
    let inside = false;
    let focused = false;
    let visible = true;
    let paused = false;
    const unbind = [];
    let observer = null;
    const saved = {};

    if (trigger === svg) {
        for (const name of ['tabindex', 'role', 'aria-label']) {
            saved[name] = trigger.getAttribute(name);
        }
        trigger.setAttribute('tabindex', '0');
        trigger.setAttribute('role', 'button');
        trigger.setAttribute('aria-label', 'FROTA PÚBLICA RO. Ativar animação da marca');
    }

    function listen(target, name, handler) {
        target.addEventListener(name, handler);
        unbind.push(() => target.removeEventListener(name, handler));
    }

    function notify() {
        if (typeof options.onStateChange === 'function') {
            options.onStateChange({ state, reduced, paused: paused || !visible || doc.hidden });
        }
    }

    function clear() {
        if (timer !== null) {
            window.clearTimeout(timer);
            timer = null;
        }
        for (const name of states) {
            svg.classList.remove(`is-${name}`);
        }
    }

    function syncPause() {
        svg.classList.toggle('is-paused', Boolean(paused || !visible || doc.hidden));
    }

    function setState(next) {
        if (destroyed) return;
        if (!states.includes(next)) {
            throw new RangeError(`Estado de movimento desconhecido: ${next}`);
        }
        if (next === state && !durations[next]) {
            notify();
            return;
        }
        clear();
        state = reduced && durations[next] ? 'idle' : next;
        svg.setAttribute('data-state', state);
        svg.setAttribute('aria-busy', state === 'loading' ? 'true' : 'false');
        svg.classList.toggle('is-reduced', reduced);
        if (durations[state]) svg.getBoundingClientRect();
        svg.classList.add(`is-${state}`);
        syncPause();
        notify();
        if (durations[state]) {
            timer = window.setTimeout(() => {
                timer = null;
                setState(inside || focused ? 'hover' : 'idle');
            }, durations[state]);
        }
    }

    function onReduction() {
        reduced = Boolean(media?.matches);
        svg.classList.toggle('is-reduced', reduced);
        if (reduced && durations[state]) setState('idle');
        else notify();
    }

    function hoverIn() {
        inside = true;
        if (state === 'idle') setState('hover');
    }

    function hoverOut() {
        inside = false;
        if (state === 'hover' && !focused) setState('idle');
    }

    function focusIn() {
        focused = true;
        if (state === 'idle') setState('hover');
    }

    function focusOut() {
        focused = false;
        if (state === 'hover' && !inside) setState('idle');
    }

    function activate() {
        if (state !== 'loading') setState('click');
    }

    listen(trigger, 'pointerenter', hoverIn);
    listen(trigger, 'pointerleave', hoverOut);
    listen(trigger, 'focus', focusIn);
    listen(trigger, 'blur', focusOut);
    listen(trigger, 'click', activate);
    if (trigger === svg) {
        listen(trigger, 'keydown', (event) => {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                activate();
            }
        });
    }
    listen(doc, 'visibilitychange', syncPause);
    if (media) {
        if (media.addEventListener) {
            listen(media, 'change', onReduction);
        } else if (media.addListener) {
            media.addListener(onReduction);
            unbind.push(() => media.removeListener(onReduction));
        }
    }
    if (window.IntersectionObserver) {
        observer = new window.IntersectionObserver(
            (entries) => {
                visible = Boolean(entries[0]?.isIntersecting);
                syncPause();
            },
            { threshold: 0 },
        );
        observer.observe(svg);
    }

    const api = {
        setState,
        getState: () => state,
        pause() {
            paused = true;
            syncPause();
            notify();
        },
        resume() {
            paused = false;
            syncPause();
            notify();
        },
        destroy() {
            if (destroyed) return;
            clear();
            for (const off of unbind) off();
            if (observer) observer.disconnect();
            svg.classList.remove('is-reduced', 'is-paused');
            svg.setAttribute('data-state', 'idle');
            svg.setAttribute('aria-busy', 'false');
            for (const key of Object.keys(saved)) {
                if (saved[key] === null) trigger.removeAttribute(key);
                else trigger.setAttribute(key, saved[key]);
            }
            destroyed = true;
            instances.delete(svg);
        },
    };

    instances.set(svg, api);
    const initial = svg.getAttribute('data-state') || 'idle';
    svg.classList.toggle('is-reduced', reduced);
    setState(states.includes(initial) ? initial : 'idle');

    return api;
}

export const FrotaPublicaMotion = Object.freeze({ mount, durations });

if (typeof window !== 'undefined') {
    window.FrotaPublicaMotion = FrotaPublicaMotion;

    const controles = [];
    for (const svg of document.querySelectorAll('svg.frota-motion')) {
        const trigger = svg.closest('a, button') || svg;
        const controle = mount(svg, { trigger });
        controles.push(controle);
        if (svg.hasAttribute('data-motion-intro')) {
            controle.setState('intro');
        }
    }

    if (controles.length > 0) {
        for (const formulario of document.querySelectorAll('form[data-loading-form]')) {
            formulario.addEventListener('submit', (evento) => {
                if (evento.defaultPrevented || !formulario.checkValidity()) return;
                for (const controle of controles) {
                    controle.setState('loading');
                }
            });
        }
        window.addEventListener('pageshow', () => {
            for (const controle of controles) {
                if (controle.getState() === 'loading') {
                    controle.setState('idle');
                }
            }
        });
    }
}
