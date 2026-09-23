const { spawn } = require('node:child_process');
const { once } = require('node:events');
const assert = require('node:assert/strict');
const net = require('node:net');
const { mkdtempSync, rmSync } = require('node:fs');
const { tmpdir } = require('node:os');
const { join } = require('node:path');

(async () => {
    const socket = net.createServer();
    socket.listen(0, '127.0.0.1');
    await once(socket, 'listening');
    const port = socket.address().port;
    await new Promise(resolve => socket.close(resolve));
    const sessionDirectory = mkdtempSync(join(tmpdir(), 'portal-location-test-'));
    const server = spawn('php', ['-d', `session.save_path="${sessionDirectory.replaceAll('\\', '/')}"`, '-S', `127.0.0.1:${port}`, '-t', process.cwd()], { stdio: ['ignore', 'pipe', 'pipe'] });
    let serverLog = '';
    server.stderr.on('data', chunk => { serverLog += chunk; });
    try {
        await Promise.race([
            once(server.stderr, 'data'),
            once(server, 'error').then(([error]) => { throw error; })
        ]);
        const base = `http://127.0.0.1:${port}/`;
        let cookie = '';
        async function request(path, body) {
            const response = await fetch(base + path, {
                redirect: 'manual', method: body ? 'POST' : 'GET',
                headers: cookie ? { Cookie: cookie } : {},
                body: body ? new URLSearchParams(body) : undefined
            });
            if (response.headers.get('set-cookie')) cookie = response.headers.get('set-cookie').split(';')[0];
            return response;
        }
        for (const page of ['index.php', 'teachers.php', 'dashboard.php', 'leave-export.php?user_id=3']) {
            const response = await request(page);
            assert.equal(response.status, 302);
            assert(response.headers.get('location').startsWith('location-required.php?next='));
        }
        const blockedPost = await request('login.php', { email: 'test@example.com', password: 'test' });
        assert.equal(blockedPost.status, 302);
        assert(blockedPost.headers.get('location').startsWith('location-required.php'));
        const gate = await request('location-required.php?next=https://example.com');
        const html = await gate.text();
        assert(html.includes('data-next="index.php"'));
        const token = html.match(/name="location-csrf" content="([^"]+)"/)[1];
        assert.equal((await request('location-access.php')).status, 405);
        assert.equal((await request('location-access.php', { action: 'verify', csrf_token: 'bad' })).status, 403);
        assert.equal((await request('location-access.php', { action: 'verify', csrf_token: token, latitude: '100', longitude: '0' })).status, 422);
        const verified = await request('location-access.php', { action: 'verify', csrf_token: token, latitude: '22', longitude: '88' });
        assert.equal((await verified.json()).success, true);
        const revoked = await request('location-access.php', { action: 'revoke', csrf_token: token });
        assert.equal((await revoked.json()).success, true);
        assert.equal((await request('teachers.php')).status, 302);
        console.log('HTTP location checks passed: protected pages/export/login, safe redirect, CSRF, method, coordinates, grant and revoke. No database access needed.');
    } catch (error) {
        console.error(serverLog);
        throw error;
    } finally {
        server.kill();
        await once(server, 'exit');
        // Remove only files in the uniquely created test session directory.
        if (sessionDirectory.startsWith(join(tmpdir(), 'portal-location-test-'))) {
            rmSync(sessionDirectory, { recursive: true, force: true });
        }
    }
})().catch(error => { console.error(error); process.exitCode = 1; });
