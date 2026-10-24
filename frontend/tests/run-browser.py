"""Inicia servidores aislados, ejecuta pruebas en Edge/Chrome vía CDP y limpia su BD."""
from pathlib import Path
import os
import secrets
import socket
import subprocess
import sys
import time
import urllib.request

root = Path(__file__).resolve().parents[2]
backend = root / 'backend'
frontend = root / 'frontend'
database = 'libreria_auth_e2e_' + secrets.token_hex(4)
results = frontend / 'test-results'
results.mkdir(exist_ok=True)
for port in (8002, 5175):
    with socket.socket() as connection:
        if connection.connect_ex(('127.0.0.1', port)) == 0:
            raise RuntimeError(f'El puerto {port} ya está ocupado; no se reemplazará su servidor.')
env = dict(os.environ, APP_ENV='testing', DB_DATABASE=database,
           JWT_SECRET=secrets.token_hex(32), BCRYPT_ROUNDS='4', JWT_COOKIE_SECURE='false',
           FRONTEND_ORIGINS='http://127.0.0.1:5175,http://localhost:5175',
           CACHE_STORE='file', CACHE_FILE_PATH=str(results / database / 'cache'),
           CACHE_FILE_LOCK_PATH=str(results / database / 'locks'),
           VITE_API_URL='/api', VITE_API_PROXY_TARGET='http://127.0.0.1:8002',
           E2E_FRONTEND_URL='http://127.0.0.1:5175', E2E_PASSWORD='AutenticacionE2E2026!')
processes = []
logs = []
try:
    subprocess.run(['php', 'tests/Fixtures/auth_e2e.php'], cwd=backend, env=env, check=True)
    for name, args, cwd in [
        ('php', ['php', '-S', '127.0.0.1:8002', str(backend / 'vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php')], backend / 'public'),
        ('vite', ['node', str(frontend / 'node_modules/vite/bin/vite.js'), '--host', '127.0.0.1', '--port', '5175', '--strictPort'], frontend),
    ]:
        log = (results / f'{name}-e2e.log').open('w', encoding='utf-8')
        logs.append(log)
        processes.append(subprocess.Popen(args, cwd=cwd, env=env, stdout=log, stderr=subprocess.STDOUT))
    for url in ['http://127.0.0.1:8002/api/ping', 'http://127.0.0.1:5175']:
        deadline = time.monotonic() + 15
        while True:
            try:
                with urllib.request.urlopen(url, timeout=2) as response:
                    if response.status == 200:
                        break
            except (OSError, urllib.error.URLError):
                if time.monotonic() > deadline:
                    raise RuntimeError(f'No inició el servidor {url}')
                time.sleep(0.1)
    scripts = sys.argv[1:] or ['auth.browser.mjs', 'catalogos.browser.mjs', 'operaciones.browser.mjs']
    for script in scripts:
        if script in ('operaciones.browser.mjs', 'inventario.postman_collection.json'):
            subprocess.run(['php', 'tests/Fixtures/inventario_e2e.php'], cwd=backend, env=env, check=True)
        if script.endswith('.postman_collection.json'):
            runner = next((frontend / 'node_modules/.cache/newman/_npx').glob('*/node_modules/newman/bin/newman.js'))
            subprocess.run(['node', str(runner), 'run', str(root / 'docs/postman' / script),
                            '--env-var', 'base_url=http://127.0.0.1:8002/api',
                            '--env-var', 'usuario=' + ('inventario.postman.e2e' if script == 'inventario.postman_collection.json' else 'postman.e2e'),
                            '--env-var', 'password=' + env['E2E_PASSWORD'], '--env-var', 'codigo=4321',
                            '--env-var', 'password_empleado=EmpleadoPostman2026!',
                            '--reporters', 'cli,json', '--reporter-json-export', str(results / script.replace('.postman_collection.json', '-postman.json'))],
                           cwd=root, env=env, check=True)
            continue
        env['E2E_USER'] = ('operaciones.e2e' if script == 'operaciones.browser.mjs'
                           else 'catalogos.e2e' if script == 'catalogos.browser.mjs' else 'admin.e2e')
        subprocess.run(['node', 'tests/' + script], cwd=frontend, env=env, check=True)
finally:
    for process in processes:
        if process.poll() is None:
            process.terminate()
            try:
                process.wait(timeout=5)
            except subprocess.TimeoutExpired:
                process.kill()
                process.wait()
    for log in logs:
        log.close()
    cleanup = r'''
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$base = config('database.connections.mongodb.database');
if (!app()->environment('testing') || !preg_match('/^libreria_auth_e2e_[a-f0-9]{8}$/', $base)) {
    throw new RuntimeException('No se permite limpiar esta base.');
}
Illuminate\Support\Facades\DB::connection('mongodb')->getMongoDB()->drop();
echo "Base temporal eliminada.\n";
'''
    subprocess.run(['php', '-r', cleanup], cwd=backend, env=env, check=True)
