for (const panel of document.querySelectorAll('[data-webauthn]')) {
    const start = panel.querySelector('[data-webauthn-start]');
    const error = panel.querySelector('[data-webauthn-error]');
    const decode = value => Uint8Array.from(atob(value.replace(/-/g, '+').replace(/_/g, '/') + '='.repeat((4 - value.length % 4) % 4)), character => character.charCodeAt(0));
    const encode = value => btoa(String.fromCharCode(...new Uint8Array(value))).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
    const post = async (url, data) => {
        const response = await fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': panel.dataset.csrf }, body: JSON.stringify(data), signal: AbortSignal.timeout(15000) });
        if (!response.ok) throw new Error(response.status === 429 ? 'Muitas tentativas. Aguarde antes de tentar novamente.' : response.status === 403 ? 'Saia e entre novamente para iniciar o cadastro com uma confirmação recente de senha.' : 'Não foi possível confirmar o fator. Inicie uma nova tentativa.');
        return response.json();
    };
    start.addEventListener('click', async () => {
        start.disabled = true;
        error.hidden = true;
        try {
            if (!window.PublicKeyCredential || !window.isSecureContext) throw new Error('Use um navegador compatível, na origem HTTPS configurada.');
            const purpose = panel.dataset.purpose;
            const state = await post(panel.dataset.optionsUrl, { purpose });
            const publicKey = state.publicKey;
            publicKey.challenge = decode(publicKey.challenge);
            for (const descriptor of publicKey.allowCredentials || publicKey.excludeCredentials || []) descriptor.id = decode(descriptor.id);
            if (publicKey.user) publicKey.user.id = decode(publicKey.user.id);
            const credential = purpose === 'registration' ? await navigator.credentials.create({ publicKey }) : await navigator.credentials.get({ publicKey });
            if (!credential) throw new Error('A confirmação foi cancelada.');
            const response = { clientDataJSON: encode(credential.response.clientDataJSON) };
            if (purpose === 'registration') {
                response.attestationObject = encode(credential.response.attestationObject);
                response.transports = credential.response.getTransports?.() || [];
            } else {
                response.authenticatorData = encode(credential.response.authenticatorData);
                response.signature = encode(credential.response.signature);
                response.userHandle = credential.response.userHandle ? encode(credential.response.userHandle) : null;
            }
            const result = await post(panel.dataset.verifyUrl, { challenge_id: state.challenge_id, credential: { id: credential.id, rawId: encode(credential.rawId), type: credential.type, response, clientExtensionResults: credential.getClientExtensionResults() } });
            location.assign(result.redirect);
        } catch (failure) {
            error.textContent = failure.name === 'NotAllowedError' ? 'A confirmação foi cancelada ou o prazo terminou. Tente novamente.' : failure.message;
            error.hidden = false;
        } finally { start.disabled = false; }
    });
}
