// Creates the database user the UniFi Network Application uses to connect to
// MongoDB. Runs once, only while the mongo data directory is still empty (first
// start).
//
// The password below is a placeholder. postroot.sh replaces MONGO_PASS_PLACEHOLDER
// at install time with the password that is auto-generated and stored in the
// plugin's env file (the same value docker-compose.yml substitutes as ${MONGO_PASS}).
// Do not edit it by hand.
db.getSiblingDB("unifi").createUser({
  user: "unifi",
  pwd: "MONGO_PASS_PLACEHOLDER",
  roles: [{ role: "dbOwner", db: "unifi" }]
});
db.getSiblingDB("unifi_stat").createUser({
  user: "unifi",
  pwd: "MONGO_PASS_PLACEHOLDER",
  roles: [{ role: "dbOwner", db: "unifi_stat" }]
});
