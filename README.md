# LoxBerry UniFi Controller Plugin

Runs the [UniFi Network Controller](https://www.ui.com/) in a Docker container on
[LoxBerry](https://www.loxwiki.eu/display/LOXBERRY/LoxBerry) and integrates it into
the LoxBerry web UI. You can pick the container version, watch the controller start
live, and open the UniFi web interface.

## Requirements

- **LoxBerry 2.0+** (tested on LoxBerry 4.x / DietPi / Debian Trixie)
- A running **Docker Engine** with the **`docker compose` v2** plugin. Either
  install the LoxBerry Docker plugin, or install Docker Engine natively (the
  official Docker apt repo already ships `docker compose`). The plugin only checks
  that the Docker socket (`/var/run/docker.sock`) responds — as user `loxberry`.
- Enough free disk space: the UniFi image needs roughly **1–1.5 GB** extracted.

## Installation

Install the plugin ZIP via the LoxBerry plugin manager (install from URL or file).
On first start the container is created and the UniFi image is pulled — this can
take a few minutes, and the status shows *starting* until the controller is up.
Afterwards the UniFi web interface is reachable at `https://<loxberry-ip>:8443`.

## Features

- **Status** — service status, container version and controller version; the
  fields refresh automatically (no page reload needed).
- **Version switcher** — choose a container version and apply it. Only versions
  that have an image for your host architecture (`amd64` / `arm64` / `arm`) are
  offered; a note is shown when newer versions are no longer built for your
  architecture. Note: **downgrading** UniFi usually fails, because it cannot
  downgrade its own database.
- **Live logs** — the UniFi `server.log` (actual controller startup: MongoDB,
  database init, web server) and the docker container init log, side by side,
  with auto-refresh. Embedded on the home page and available as a full page.
- **Web interface** — one click to open the UniFi controller on port 8443.

## Notes

- Uses **`docker compose` v2** (the legacy `docker-compose` v1 binary is no longer
  required or installed).
- The `linuxserver/unifi-controller` image is **deprecated** (last version 8.0.24)
  and no longer updated by linuxserver.io. It still works fine for a local
  controller. The future migration target is
  [`unifi-network-application`](https://github.com/linuxserver/docker-unifi-network-application),
  which requires a separate MongoDB container.

## License

Licensed under the [MIT](LICENSE) License.

thx@poppins
![LoxBerry Poppins](https://user-images.githubusercontent.com/3605512/72895177-1bebb700-3d1d-11ea-8393-7e9f3a7a0207.png)
