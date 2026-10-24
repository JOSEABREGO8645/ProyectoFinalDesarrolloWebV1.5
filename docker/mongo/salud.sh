#!/usr/bin/env bash
set -euo pipefail

mongosh --quiet --host 127.0.0.1 --port 27018 \
  --username "$MONGO_INITDB_ROOT_USERNAME" \
  --password "$MONGO_INITDB_ROOT_PASSWORD" --authenticationDatabase admin \
  --eval '
    try {
      const estado = db.hello();
      if (!estado.setName) {
        rs.initiate({_id: "rs0", members: [{_id: 0, host: "localhost:27018"}]});
        quit(1);
      }
      quit(estado.isWritablePrimary ? 0 : 1);
    } catch (error) { quit(1); }
  '
