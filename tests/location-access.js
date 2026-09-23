const { readFileSync } = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const source = readFileSync(__dirname + '/../assets/location-access.js', 'utf8');

async function run({ gate = false, code = 0, secure = true, supported = true, serverOk = true } = {}) {
    const classes = new Set();
    const calls = [];
    const events = {};
    const status = {};
    const retry = { addEventListener(name, callback) { events.retry = callback; } };
    const location = { pathname: '/teacher-portal/teachers.php', search: '', replace(url) { calls.push(url); } };
    const context = {
        URLSearchParams, AbortSignal,
        location,
        window: { isSecureContext: secure, setInterval() {}, addEventListener(name, callback) { events[name] = callback; } },
        navigator: {
            geolocation: supported ? { getCurrentPosition(success, failure) { code ? failure({ code }) : success({ coords: { latitude: 22, longitude: 88 } }); } } : undefined,
            permissions: { query: async () => ({ addEventListener(name, callback) { events.revoke = callback; }, state: 'denied' }) }
        },
        document: {
            documentElement: { classList: { add(name) { classes.add(name); }, remove(name) { classes.delete(name); } } },
            getElementById(id) { return id === 'location-gate' ? (gate ? { dataset: { next: 'teachers.php' } } : null) : id === 'location-status' ? status : retry; },
            querySelector() { return { content: 'csrf-test' }; },
            addEventListener(name, callback) { events[name] = callback; }
        },
        fetch: async (url, options) => {
            calls.push(options.body.get('action'));
            assert.equal(options.body.get('csrf_token'), 'csrf-test');
            return { ok: serverOk, json: async () => ({ success: serverOk }) };
        }
    };
    vm.runInNewContext(source, context);
    await new Promise(setImmediate);
    return { classes, calls, events, status, retry };
}

(async () => {
    const allowed = await run();
    assert(allowed.classes.has('location-ready'));
    allowed.events.revoke();
    assert(!allowed.classes.has('location-ready'));
    const gate = await run({ gate: true });
    assert(gate.calls.includes('teachers.php'));
    for (const options of [{ code: 1 }, { code: 2 }, { code: 3 }, { secure: false }, { supported: false }, { serverOk: false }]) {
        const blocked = await run(options);
        assert(!blocked.classes.has('location-ready'));
        assert(blocked.calls.some(call => call.startsWith('location-required.php')));
    }
    const denied = await run({ gate: true, code: 1 });
    assert.match(denied.status.textContent, /denied/);
    assert.equal(denied.retry.disabled, false);
    console.log('Location browser-flow checks passed: grant, denial, unavailable, timeout, HTTPS, unsupported browser, server failure and revocation.');
})().catch(error => { console.error(error); process.exitCode = 1; });
