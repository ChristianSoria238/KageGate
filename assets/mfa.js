(() => {
    const qrContainer = document.querySelector('#mfa-qr-code');
    const qrError = document.querySelector('#mfa-qr-error');
    const qrPanel = document.querySelector('#mfa-qr-panel');
    const keyPanel = document.querySelector('#mfa-key-panel');
    const tabs = document.querySelectorAll('[data-mfa-method]');

    if (!qrContainer || !qrPanel || !keyPanel) return;

    const selectMethod = (method) => {
        const showQr = method === 'qr';
        qrPanel.hidden = !showQr;
        keyPanel.hidden = showQr;
        tabs.forEach((tab) => {
            const selected = tab.dataset.mfaMethod === method;
            tab.classList.toggle('active', selected);
            tab.setAttribute('aria-selected', String(selected));
            tab.tabIndex = selected ? 0 : -1;
        });
    };

    tabs.forEach((tab) => {
        tab.addEventListener('click', () => selectMethod(tab.dataset.mfaMethod));
        tab.addEventListener('keydown', (event) => {
            if (!['ArrowLeft', 'ArrowRight'].includes(event.key)) return;
            event.preventDefault();
            const nextMethod = tab.dataset.mfaMethod === 'qr' ? 'key' : 'qr';
            selectMethod(nextMethod);
            document.querySelector(`[data-mfa-method="${nextMethod}"]`)?.focus();
        });
    });

    if (typeof QRCode === 'undefined') {
        if (qrError) qrError.hidden = false;
        return;
    }

    try {
        new QRCode(qrContainer, {
            text: qrContainer.dataset.otpauth || '',
            width: 220,
            height: 220,
            colorDark: '#090b19',
            colorLight: '#ffffff',
            correctLevel: QRCode.CorrectLevel.M,
        });
    } catch {
        if (qrError) qrError.hidden = false;
    }
})();
