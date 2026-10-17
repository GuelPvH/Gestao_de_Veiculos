import { Modal } from 'bootstrap';

const panel = document.querySelector('[data-session-status]');
if (panel) {
    const modal = Modal.getOrCreateInstance(panel);
    const form = document.getElementById('fleet-session-continue');
    const password = form.querySelector('[name=senha]');
    const error = panel.querySelector('[data-session-error]');
    const submit = document.querySelector('[form=fleet-session-continue]');
    let remaining = null, anchor = 0, syncing = false, renewing = false, ended = false;
    let lastSync = -Infinity, latestStatus = null, statusGeneration = 0;
    const scope = panel.dataset.sessionScope || '';
    const statusKey = `fleet-session-status:${scope}`;
    let channel;
    try { channel = new BroadcastChannel('fleet-session'); } catch (_) {}
    const announce = type => {
        channel?.postMessage({ type });
        try { localStorage.setItem('fleet-session-event', JSON.stringify({ type, nonce: Math.random() })); } catch (_) {}
    };
    const end = (notify = true) => {
        if (ended) return;
        ended = true;
        try { if (scope) localStorage.removeItem(statusKey); } catch (_) {}
        password.value = '';
        dispatchEvent(new Event('fleet-session-ended'));
        if (notify) announce('ended');
        location.replace(panel.dataset.sessionLogin);
    };
    const accept = (state, transit = 0) => {
        statusGeneration++;
        const duration = Date.parse(state.expires_at) - Date.parse(state.server_time);
        if (!Number.isFinite(duration) || duration <= 0) { end(); return; }
        remaining = Math.max(0, duration - transit);
        anchor = performance.now();
        error.hidden = true;
        tick();
    };
    const shareStatus = (state, transit = 0) => {
        lastSync = performance.now();
        latestStatus = { type: 'status', scope, state, transit, receivedAt: Date.now() };
        if (!scope) return;
        channel?.postMessage(latestStatus);
        try { localStorage.setItem(statusKey, JSON.stringify(latestStatus)); } catch (_) {}
    };
    const acceptShared = (snapshot, minimumTime = 0) => {
        if (!scope || snapshot?.scope !== scope || !snapshot.state || !Number.isFinite(snapshot.receivedAt) || !Number.isFinite(snapshot.transit)) return false;
        const age = Date.now() - snapshot.receivedAt;
        if (age < 0 || age > 25000 || snapshot.receivedAt < minimumTime) return false;
        latestStatus = snapshot;
        accept(snapshot.state, snapshot.transit + age);
        lastSync = performance.now() - age;
        return true;
    };
    const sharedStatus = (minimumTime = 0) => {
        if (acceptShared(latestStatus, minimumTime)) return true;
        try { return acceptShared(JSON.parse(localStorage.getItem(statusKey)), minimumTime); } catch (_) { return false; }
    };
    const sync = async (force = false) => {
        if (syncing || ended || renewing || document.visibilityState === 'hidden') return;
        if (!force && performance.now() - lastSync < 5000) return;
        const requestedAt = force ? Date.now() : 0;
        syncing = true;
        try {
            const check = async () => {
                if (ended || document.visibilityState === 'hidden' || sharedStatus(requestedAt)) return;
                const generation = statusGeneration;
                const started = performance.now();
                const response = await fetch(panel.dataset.sessionStatus, { headers: { Accept: 'application/json' }, cache: 'no-store', signal: AbortSignal.timeout(8000) });
                if (generation !== statusGeneration || renewing || ended) return;
                if (response.status === 401) { end(); return; }
                if (!response.ok) throw new Error();
                const state = await response.json();
                if (generation !== statusGeneration || renewing || ended) return;
                if (state.state !== 'ACTIVE') { end(); return; }
                const transit = performance.now() - started;
                accept(state, transit);
                if (!ended) shareStatus(state, transit);
            };
            if (scope && navigator.locks) {
                await navigator.locks.request(statusKey, { signal: AbortSignal.timeout(9000) }, check);
            } else {
                await check();
            }
        } catch (_) {
            error.textContent = 'Não foi possível conferir a sessão. Tente novamente antes do prazo.';
            error.hidden = false;
        } finally { syncing = false; }
    };
    const tick = () => {
        if (remaining === null || ended) return;
        const seconds = Math.max(0, Math.ceil((remaining - (performance.now() - anchor)) / 1000));
        panel.querySelector('[data-session-countdown]').textContent = `${Math.floor(seconds / 60)}:${String(seconds % 60).padStart(2, '0')}`;
        if (seconds <= 60 && seconds > 0) modal.show();
        if (seconds > 60) modal.hide();
        if (seconds === 0 && !renewing) { password.value = ''; end(); }
    };
    panel.addEventListener('shown.bs.modal', () => password.focus());
    form.addEventListener('submit', async event => {
        event.preventDefault();
        if (renewing || ended || !form.reportValidity()) return;
        renewing = true;
        statusGeneration++;
        submit.disabled = true;
        const started = performance.now();
        const body = new FormData(form);
        password.value = '';
        try {
            const response = await fetch(form.action, { method: 'POST', body, headers: { Accept: 'application/json' }, signal: AbortSignal.timeout(8000) });
            if (response.status === 401) { end(); return; }
            if (response.status === 419) throw new Error('Recarregue a página para confirmar novamente.');
            if (response.status === 429) throw new Error('Muitas tentativas. Aguarde o limite informado pelo sistema.');
            if (response.status === 422) throw new Error('Não foi possível confirmar sua senha.');
            if (!response.ok) throw new Error('Não foi possível confirmar a sessão. Tente novamente antes do prazo.');
            const state = await response.json();
            const transit = performance.now() - started;
            accept(state, transit);
            shareStatus(state, transit);
            announce('renewed');
            if (state.mfa_required) location.assign(panel.dataset.sessionMfa);
        } catch (failure) {
            error.textContent = failure.message || 'Falha de conexão. Confira sua sessão novamente.';
            error.hidden = false;
        } finally {
            renewing = false;
            submit.disabled = false;
            tick();
        }
    });
    const receive = event => {
        if (event?.type === 'ended') end(false);
        if (event?.type === 'status') acceptShared(event);
        if (event?.type === 'renewed') { password.value = ''; if (!sharedStatus()) sync(true); }
    };
    if (channel) channel.onmessage = event => receive(event.data);
    addEventListener('storage', event => {
        if (event.key === 'fleet-session-event') {
            try { receive(JSON.parse(event.newValue)); } catch (_) {}
        }
        if (event.key === statusKey) {
            try { acceptShared(JSON.parse(event.newValue)); } catch (_) {}
        }
    });
    panel.querySelector('[data-session-logout]').addEventListener('submit', () => { dispatchEvent(new Event('fleet-session-ended')); announce('ended'); });
    document.addEventListener('visibilitychange', () => { if (document.visibilityState === 'visible') sync(true); });
    addEventListener('focus', () => sync(true));
    setInterval(tick, 1000);
    setInterval(sync, 30000);
    try {
        if (panel.dataset.sessionInitial) {
            const initial = JSON.parse(panel.dataset.sessionInitial);
            const navigation = performance.getEntriesByType('navigation')[0];
            accept(initial, navigation ? Math.max(0, performance.now() - navigation.responseStart) : 0);
            if (!ended) shareStatus(initial);
        }
    } catch (_) {}
    if (remaining === null) sync();
}
