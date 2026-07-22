# LoxBerry UniFi Network Application Plugin

Runs the [UniFi Network Application](https://www.ui.com/) (the successor of the
discontinued *UniFi Controller*) in Docker on
[LoxBerry](https://www.loxwiki.eu/display/LOXBERRY/LoxBerry), together with its
now-required MongoDB, and integrates it into the LoxBerry web UI. You can pick the
application version, watch it start live, and open the UniFi web interface.

> This branch (`UniFi-Network-Server`) uses the modern
> `lscr.io/linuxserver/unifi-network-application` image plus a separate MongoDB
> container. The `master` branch still uses the old, deprecated
> `linuxserver/unifi-controller` all-in-one image.

## Requirements

- **64-bit LoxBerry** (LB4 image on Raspberry Pi 4/5 or x86). The UniFi Network
  Application and MongoDB are only published as 64-bit images (amd64 / arm64) —
  **there are no arm32 images**, so a 32-bit system cannot run this (the install
  is blocked with a clear message).
- A running **Docker Engine** with the **`docker compose` v2** plugin. Either
  install the LoxBerry Docker plugin, or install Docker Engine natively. The plugin
  only checks that the Docker socket (`/var/run/docker.sock`) responds — as user
  `loxberry`.
- Enough free disk space and RAM: the application (Java) + MongoDB need roughly
  **1–2 GB RAM**; a Raspberry Pi 3B (1 GB) is marginal.

## Before the first start — set the database password

The MongoDB password ships as the placeholder `unifipass`. Change it in **both**
places (they must match), then (re)start:

- `src/Docker/docker-compose.yml` → `MONGO_PASS=...`
- `src/Docker/init-mongo.js` → the `pwd` value

`init-mongo.js` only runs while the MongoDB data directory is still empty (first
start), so set the password *before* the very first launch.

## Installation

Install the plugin ZIP via the LoxBerry plugin manager (install from URL or file).
On first start both containers are created and the images are pulled — this can take
a few minutes, and the status shows *starting* until the application is up.
Afterwards the UniFi web interface is reachable at `https://<loxberry-ip>:8443`.

**Migrating from the old `unifi-controller`?** The data is not migrated
automatically. Export a backup in the old controller (≤ 7.1.x) and restore it in the
new Network Application after it is running.

## Features

- **Status** — service status, container version and application version; the fields
  refresh automatically (no page reload needed).
- **Version switcher** — choose a version (e.g. `latest` or `9.0.108`) and apply it.
  Only tags with an image for your host architecture are offered. Note: **downgrading**
  usually fails, because UniFi cannot downgrade its own database.
- **Live logs** — the UniFi `server.log` (actual startup: MongoDB, database init, web
  server) and the docker container init log, side by side, with auto-refresh. Embedded
  on the home page and available as a full page.
- **Web interface** — one click to open the UniFi web interface on port 8443.

## Notes

- Two containers: `unifi-network-application` (the app) and `unifi-db` (`mongo:4.4`,
  chosen because it runs on arm64 and needs no AVX on x86).
- Uses **`docker compose` v2**.

## License

Licensed under the [MIT](LICENSE) License.

thx@poppins
![LoxBerry Poppins](https://user-images.githubusercontent.com/3605512/72895177-1bebb700-3d1d-11ea-8393-7e9f3a7a0207.png)
