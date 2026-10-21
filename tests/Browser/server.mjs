import { spawn, execFileSync } from 'node:child_process';
import { mkdirSync, writeFileSync, existsSync } from 'node:fs';
import path from 'node:path';

// Every browser run gets a fresh, synthetic SQLite database. Never use the developer's .env database.
const root = process.cwd();
const storage = path.join(root, 'storage/framework/testing');
mkdirSync(storage, { recursive: true });
const database = path.join(storage, 'browser-test.sqlite');
writeFileSync(database, '');
const herdPhp = 'C:/Users/JANNY/.config/herd/bin/php84/php.exe';
const php = process.env.PHP_BINARY || (existsSync(herdPhp) ? herdPhp : 'php');
const env = { ...process.env, APP_ENV: 'testing', APP_DEBUG: 'false', APP_URL: 'http://127.0.0.1:8765',
    APP_KEY: `base64:${Buffer.alloc(32, 7).toString('base64')}`, DB_CONNECTION: 'sqlite', DB_DATABASE: database, DB_URL: '',
    APP_CONFIG_CACHE: path.join(storage, 'browser-config.php'), APP_ROUTES_CACHE: path.join(storage, 'browser-routes.php'),
    SESSION_DRIVER: 'file', SESSION_COOKIE: 'hris_browser_test', SESSION_SECURE_COOKIE: 'false', SESSION_DOMAIN: '',
    CACHE_STORE: 'database', QUEUE_CONNECTION: 'database', MAIL_MAILER: 'array', LOG_CHANNEL: 'single' };
execFileSync(php, ['artisan', 'migrate', '--force', '--no-interaction'], { cwd: root, env, stdio: 'inherit', windowsHide: true });
execFileSync(php, ['tests/Browser/seed.php'], { cwd: root, env, stdio: 'inherit', windowsHide: true });
const server = spawn(php, ['-S', '127.0.0.1:8765', path.join(root, 'vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php')], { cwd: path.join(root, 'public'), env, stdio: ['ignore', 'pipe', 'pipe'], windowsHide: true });
server.stderr.on('data', chunk => { if (/Warning|Fatal|Development Server/.test(String(chunk))) process.stderr.write(chunk); });
for (const signal of ['SIGINT', 'SIGTERM']) process.on(signal, () => { server.kill(); process.exit(0); });
server.on('exit', code => process.exit(code ?? 0));
