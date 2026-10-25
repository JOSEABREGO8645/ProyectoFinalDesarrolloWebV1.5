"""Demo persistente aislada. Ctrl+C detiene sus servidores y conserva su base."""
from pathlib import Path
import argparse
import json
import os
import socket
import subprocess
import time
import urllib.request

args = argparse.ArgumentParser()
args.add_argument('--verificar', action='store_true', help='Ejecuta Postman y navegador y luego detiene los servidores')
options = args.parse_args()
root = Path(__file__).resolve().parents[1]
backend, frontend = root / 'backend', root / 'frontend'
private = backend / 'storage/app/private/demo/libreria_demo'
private.mkdir(parents=True, exist_ok=True)
for port in (8003, 5176):
    with socket.socket() as connection:
        if connection.connect_ex(('127.0.0.1', port)) == 0:
            raise RuntimeError(f'El puerto {port} está ocupado. No se reemplazará su servidor.')
# Evita que una configuración de producción cacheada ignore las variables del proceso.
seed_env = dict(os.environ, APP_ENV='local', APP_CONFIG_CACHE=str(private / 'config-no-cache.php'))
subprocess.run(['php','artisan','libreria:demo','--no-interaction'],cwd=backend,env=seed_env,check=True)
credentials = json.loads((private / 'accesos.json').read_text(encoding='utf-8'))
if credentials.get('base') != 'libreria_demo' or credentials.get('version') != 1:
    raise RuntimeError('El archivo de accesos no corresponde a esta demostración.')
env = dict(seed_env, DB_DATABASE='libreria_demo', JWT_SECRET=credentials['jwt_secret'],
           JWT_COOKIE_NAME='libreria_demo_refresh', JWT_COOKIE_SECURE='false',
           FRONTEND_ORIGINS='http://127.0.0.1:5176,http://localhost:5176',
           CACHE_STORE='file', CACHE_FILE_PATH=str(private / 'cache'), CACHE_FILE_LOCK_PATH=str(private / 'locks'),
           VITE_API_URL='/api', VITE_API_PROXY_TARGET='http://127.0.0.1:8003', VITE_DEMO='true',
           E2E_FRONTEND_URL='http://127.0.0.1:5176', E2E_CUENTAS=json.dumps(credentials['cuentas']))
processes, logs = [], []
try:
    for name, command, cwd in [
        ('backend',['php','-S','127.0.0.1:8003',str(backend / 'vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php')],backend / 'public'),
        ('frontend',['node',str(frontend / 'node_modules/vite/bin/vite.js'),'--host','127.0.0.1','--port','5176','--strictPort'],frontend),
    ]:
        log = (private / (name+'.log')).open('w',encoding='utf-8')
        logs.append(log)
        processes.append(subprocess.Popen(command,cwd=cwd,env=env,stdout=log,stderr=subprocess.STDOUT))
    for url in ['http://127.0.0.1:8003/api/ping','http://127.0.0.1:5176']:
        deadline = time.monotonic()+20
        while True:
            try:
                with urllib.request.urlopen(url,timeout=2) as response:
                    if response.status == 200:
                        break
            except (OSError,urllib.error.URLError):
                if time.monotonic()>deadline:
                    raise RuntimeError('No inició la demostración; revise sus logs privados.')
                time.sleep(0.1)
    print('Demostración: http://127.0.0.1:5176',flush=True)
    print('Usuario: admin.demo. Contraseña y códigos en backend/storage/app/private/demo/libreria_demo/accesos.json',flush=True)
    if options.verificar:
        subprocess.run(['node','tests/demostracion.postman.mjs'],cwd=frontend,env=env,check=True)
        subprocess.run(['node','tests/demostracion.browser.mjs'],cwd=frontend,env=env,check=True)
    else:
        while True:
            if any(p.poll() is not None for p in processes):
                raise RuntimeError('Un servidor de demostración se detuvo; revise sus logs privados.')
            time.sleep(0.5)
except KeyboardInterrupt:
    print('Servidores de demostración detenidos. Los datos se conservan.',flush=True)
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
