// Creates the database user the UniFi Network Application uses to connect to
// MongoDB. This runs once, only when the mongo data directory is still empty
// (first start). If you change the password here you MUST change MONGO_PASS in
// docker-compose.yml to the exact same value.
//
// !!! CHANGE "unifipass" below to your own password (and keep it in sync with
// !!! MONGO_PASS in docker-compose.yml).
db.getSiblingDB("unifi").createUser({
  user: "unifi",
  pwd: "unifipass",
  roles: [{ role: "dbOwner", db: "unifi" }]
});
db.getSiblingDB("unifi_stat").createUser({
  user: "unifi",
  pwd: "unifipass",
  roles: [{ role: "dbOwner", db: "unifi_stat" }]
});
