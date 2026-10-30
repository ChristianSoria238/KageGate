(() => {
    const status = document.querySelector('#passkey-status');
    const message = (text, isError = false) => {
        if (!status) return;
        status.textContent = text;
        status.className = isError ? 'error' : 'success';
    };
    const decode = (value) => {
        const normalized = value.replace(/-/g, '+').replace(/_/g, '/');
        const binary = atob(normalized + '='.repeat((4 - normalized.length % 4) % 4));
        return Uint8Array.from(binary, (character) => character.charCodeAt(0));
    };
    const encode = (value) => {
        const bytes = new Uint8Array(value);
        let binary = '';
        for (const byte of bytes) binary += String.fromCharCode(byte);
        return btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/g, '');
    };
    const post = async (action, csrf, values = {}) => {
        const response = await fetch('passkey-api.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrf,
            },
            body: JSON.stringify({ action, ...values }),
        });
        const body = await response.text();
        let result;
        try {
            result = JSON.parse(body);
        } catch {
            throw new Error(`Passkey service returned an invalid response (HTTP ${response.status}). Check the hosting PHP error log.`);
        }
        if (!result || typeof result !== 'object' || Array.isArray(result)) {
            throw new Error('Passkey service returned an invalid response. Try again or check the hosting PHP error log.');
        }
        if (!response.ok) {
            const diagnostic = typeof result.diagnostic === 'string'
                ? ` (${result.diagnostic})`
                : '';
            throw new Error((result.error || 'Passkey request failed.') + diagnostic);
        }
        return result;
    };
    const creationOptions = (options) => {
        options.challenge = decode(options.challenge);
        options.user.id = decode(options.user.id);
        options.excludeCredentials = (options.excludeCredentials || []).map((item) => ({
            ...item,
            id: decode(item.id),
        }));
        return options;
    };
    const requestOptions = (options) => {
        options.challenge = decode(options.challenge);
        if (Array.isArray(options.allowCredentials)) {
            options.allowCredentials = options.allowCredentials.map((item) => ({
                ...item,
                id: decode(item.id),
            }));
        }
        return options;
    };
    const credentialPayload = (credential) => ({
        id: credential.id,
        clientDataJSON: encode(credential.response.clientDataJSON),
        ...(credential.response.attestationObject
            ? { attestationObject: encode(credential.response.attestationObject) }
            : {
                authenticatorData: encode(credential.response.authenticatorData),
                signature: encode(credential.response.signature),
                userHandle: credential.response.userHandle ? encode(credential.response.userHandle) : '',
            }),
    });

    const loginButton = document.querySelector('#passkey-login');
    if (loginButton) {
        loginButton.addEventListener('click', async () => {
            try {
                if (!window.PublicKeyCredential || !navigator.credentials) {
                    throw new Error('This browser does not support passkeys.');
                }
                const login = document.querySelector('input[name="login"]').value.trim();
                if (!login) throw new Error('Enter your username or email first.');
                const result = await post('login-options', loginButton.dataset.csrf, { login });
                const credential = await navigator.credentials.get({
                    publicKey: requestOptions(result.publicKey),
                });
                if (!credential) throw new Error('Passkey sign-in was cancelled.');
                const authenticated = await post('login', loginButton.dataset.csrf, credentialPayload(credential));
                window.location.assign(authenticated.redirect);
            } catch (error) {
                message(error.message, true);
            }
        });
    }

    const registerButton = document.querySelector('#passkey-register');
    if (registerButton) {
        registerButton.addEventListener('click', async () => {
            try {
                if (!window.PublicKeyCredential || !navigator.credentials) {
                    throw new Error('This browser does not support passkeys.');
                }
                const password = document.querySelector('#passkey-password').value;
                const label = document.querySelector('#passkey-label').value;
                const result = await post('register-options', registerButton.dataset.csrf, { password });
                const credential = await navigator.credentials.create({
                    publicKey: creationOptions(result.publicKey),
                });
                if (!credential) throw new Error('Passkey registration was cancelled.');
                await post('register', registerButton.dataset.csrf, {
                    ...credentialPayload(credential),
                    label,
                });
                window.location.reload();
            } catch (error) {
                message(error.message, true);
            }
        });
    }

    document.querySelectorAll('.passkey-delete').forEach((button) => {
        button.addEventListener('click', async () => {
            const password = window.prompt('Enter your current password to remove this passkey.');
            if (password === null) return;
            try {
                await post('delete', button.dataset.csrf, {
                    id: button.dataset.id,
                    password,
                });
                window.location.reload();
            } catch (error) {
                message(error.message, true);
            }
        });
    });
})();
