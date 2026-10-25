# Respaldo y restauración local

Los archivos de respaldo contienen datos privados. Guárdelos en `backend/storage/app/private/backups/` o en otro almacén privado. No use `docker compose down -v` para reiniciar un servidor: elimina los volúmenes.

Ejemplo para la **base ficticia libreria_demo**, sin claves literales. Desde PowerShell:

```powershell
New-Item -ItemType Directory -Force backend/storage/app/private/backups | Out-Null
docker exec -it mongodb_libreria bash
```

Dentro de ese shell del contenedor:

```bash
mongodump --host 127.0.0.1 --port 27018 \
  --username "$MONGO_INITDB_ROOT_USERNAME" \
  --password "$MONGO_INITDB_ROOT_PASSWORD" --authenticationDatabase admin \
  --db libreria_demo --archive=/tmp/libreria-demo.archive --gzip
exit
```

De nuevo en PowerShell, copiar al almacén privado:

```powershell
docker cp mongodb_libreria:/tmp/libreria-demo.archive backend/storage/app/private/backups/libreria-demo.archive
```

Para recuperar, **usar primero una base de destino nueva**, por ejemplo `libreria_restauracion`. Conserve el archivo original y no añada `--drop`.

```powershell
docker cp backend/storage/app/private/backups/libreria-demo.archive mongodb_libreria:/tmp/libreria-demo-restaurar.archive
docker exec -it mongodb_libreria bash
```

En el shell del contenedor, comprobar que el destino está vacío antes de restaurar:

```bash
mongosh --quiet --host 127.0.0.1 --port 27018 \
  --username "$MONGO_INITDB_ROOT_USERNAME" \
  --password "$MONGO_INITDB_ROOT_PASSWORD" --authenticationDatabase admin \
  --eval 'if (db.getSiblingDB("libreria_restauracion").getCollectionNames().length) { print("Destino ocupado: detenerse"); quit(2); } print("Destino vacio");'
```

Solo si esa comprobación finalizó correctamente:

```bash
mongorestore --host 127.0.0.1 --port 27018 \
  --username "$MONGO_INITDB_ROOT_USERNAME" \
  --password "$MONGO_INITDB_ROOT_PASSWORD" --authenticationDatabase admin \
  --archive=/tmp/libreria-demo-restaurar.archive --gzip \
  --nsInclude='libreria_demo.*' \
  --nsFrom='libreria_demo.*' --nsTo='libreria_restauracion.*'
exit
```

Revise documentos, existencias y kardex en el destino antes de configurar una aplicación para usarlo. Estos comandos son instrucciones de recuperación; no declaran que se haya restaurado la base comercial. Para Atlas, use su URI privada y los comandos de conexión del panel, sin publicarlos.
