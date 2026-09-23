(() => {
    'use strict';
    const gate = document.getElementById('location-gate');
    const status = document.getElementById('location-status');
    const retry = document.getElementById('location-retry');
    const token = document.querySelector('meta[name="location-csrf"]').content;
    let checking = false;
    let blocked = false;

    async function updateAccess(action, coords) {
        const body = new URLSearchParams({ action, csrf_token: token });
        if (coords) {
            body.set('latitude', coords.latitude);
            body.set('longitude', coords.longitude);
        }
        const response = await fetch('location-access.php', {
            method: 'POST', body, credentials: 'same-origin', cache: 'no-store',
            signal: AbortSignal.timeout(10000)
        });
        const result = await response.json();
        if (!response.ok || !result.success) throw new Error('Location verification failed.');
    }

    function deny(message) {
        blocked = true;
        document.documentElement.classList.remove('location-ready');
        // The destination also clears verification, even if this request cannot finish.
        void updateAccess('revoke').catch(() => {});
        if (gate) {
            status.textContent = message;
        } else {
            const next = location.pathname.split('/').pop() + location.search;
            location.replace('location-required.php?next=' + encodeURIComponent(next));
        }
    }

    async function check() {
        if (checking) return;
        checking = true;
        blocked = false;
        if (retry) retry.disabled = true;
        if (status) status.textContent = 'Checking your location…';
        try {
            if (!window.isSecureContext) throw new Error('Open this portal using HTTPS to enable location.');
            if (!navigator.geolocation) throw new Error('This browser does not support location. Please use a browser with location support.');
            const position = await new Promise((resolve, reject) => {
                navigator.geolocation.getCurrentPosition(resolve, reject, {
                    enableHighAccuracy: false, timeout: 15000, maximumAge: 0
                });
            });
            if (blocked) return;
            await updateAccess('verify', position.coords);
            if (blocked) {
                await updateAccess('revoke');
                return;
            }
            if (gate) {
                status.textContent = 'Location enabled. Opening the requested page…';
                location.replace(gate.dataset.next);
            } else {
                document.documentElement.classList.add('location-ready');
            }
        } catch (error) {
            const messages = {
                1: 'Location permission was denied. Allow Location in your browser’s site settings, then try again.',
                2: 'Your location is unavailable. Enable device location services and try again.',
                3: 'The location request timed out. Please try again.'
            };
            deny(messages[error.code] || error.message || 'Unable to verify location. Please try again.');
        } finally {
            checking = false;
            if (retry) retry.disabled = false;
        }
    }

    if (retry) retry.addEventListener('click', check);
    if (navigator.permissions) {
        navigator.permissions.query({ name: 'geolocation' }).then(permission => {
            permission.addEventListener('change', () => {
                if (permission.state !== 'granted') deny('Location access must remain enabled. Allow Location and try again.');
            });
        }).catch(() => {});
    }
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible' && !gate) {
            document.documentElement.classList.remove('location-ready');
            void check();
        }
    });
    window.addEventListener('pageshow', event => { if (event.persisted) void check(); });
    window.setInterval(() => {
        if (!gate && document.visibilityState === 'visible') void check();
    }, 300000);
    void check();
})();
