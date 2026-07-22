# CLAUDE.md — LoxBerry UniFi Controller plugin

Guidance for working on this plugin.

This is a **fork** of `romanlum/LoxBerry-Plugin-Unfi-Controller` (git remote
`upstream`); this fork's remote is `origin` (blacksun80). The plugin is used
directly via the branch ZIP; there is no published release/auto-update chain.

## Branch note: `UniFi-Network-Server` (this branch)

This branch replaces the deprecated all-in-one `linuxserver/unifi-controller`
image with the modern **`lscr.io/linuxserver/unifi-network-application`** plus a
**separate MongoDB** container. Differences from `master`:

- `src/Docker/docker-compose.yml` defines **two** services: `unifi-network-application`
  (app, `container_name: unifi-network-application`) and `unifi-db`
  (`mongo:4.4`, `container_name: unifi-db`). `mongo:4.4` is chosen for arm64 support
  and no AVX requirement on x86.
- `src/Docker/init-mongo.js` creates the `unifi` DB user on first mongo start.
  The password is a **placeholder** (`unifipass`) that must be changed in **both**
  the compose file (`MONGO_PASS`) and `init-mongo.js` before first launch.
- **64-bit only.** `preinstall.sh` blocks the install on 32-bit (no arm32 images).
- `SystemService::CONTAINER_NAME = "unifi-network-application"` (logs/stats target
  the app container).
- `DockerHubService::SEARCH_URL` queries the `unifi-network-application` repo; tags
  are `latest` / `9.0.108` (no `version-` prefix). Default version in `postroot.sh`
  is `latest`.
- Data is **not** auto-migrated from the old image; export/restore a UniFi backup.
- `MONGO_PASS` is **auto-generated** at install (`postroot.sh`, random 32-char,
  `tr -dc 'A-Za-z0-9' < /dev/urandom 2>/dev/null | head -c 32` — stderr redirected
  because `tr` hits a harmless "Broken pipe" once `head` stops reading), stored in
  the plugin env file (survives upgrades) and written into both
  `docker-compose.yml` (`${MONGO_PASS}`, resolved via the auto-loaded `.env`
  symlink in the compose working directory) and `init-mongo.js` (via `sed`
  replacing `MONGO_PASS_PLACEHOLDER`). Aborts the install if generation produced
  fewer than 32 chars.
- `1900:1900/udp` (SSDP) is **commented out** in `docker-compose.yml` by default:
  commonly already bound by another host service (LoxBerry itself uses SSDP) and
  not needed for admin access via 8443. A live conflict shows as `driver failed
  programming external connectivity ... address already in use`.
- Two containers can be independently stuck: `resetContainers()` /
  `HomeController::reset()` (the home page "Reset" button) force-removes both
  (`docker rm -f unifi-network-application unifi-db`) and restarts the service so
  compose recreates them + the network cleanly - fixes both "stuck in Created"
  and "references a network ID that no longer exists" (e.g. after `docker system
  prune` removed the network while a container existed but was not running).
- `SystemService::getDiagnostics()` (Diagnostics page) exists because the normal
  log views can go **silently blank**: `docker logs` on a container that exists
  but was never started (e.g. after the port/network issues above) returns empty
  output with no error, so the "No such container" journalctl fallback never
  triggers. Diagnostics always includes `systemctl status unifi` (the actual
  failure reason), `docker ps -a` state for both containers, disk space, and
  **both** containers' raw logs (previously only the app container's logs were
  shown anywhere in the GUI, never MongoDB's - relevant since mongo exiting with
  code 100 on its own is invisible otherwise). The home page shows a "failed"
  banner linking to Diagnostics, live-toggled by the status poller.
- The UniFi app repeatedly logging `Manifest request to ULP failed ... Connect to
  http://127.0.0.1:9080 ... Connection refused` is **cosmetic and expected** in
  this bare Docker image: port 9080 is served by "UniFi OS" on real Ubiquiti
  hardware (UDM/Cloud Gateway), which does not exist in this container-only
  deployment. Does not affect adoption or normal operation.
- The bundled `config_howto.png` (and its instructions) show the **UniFi 5.6.39**
  UI ("Settings → Controller → Override inform host..."); the setting still
  exists in modern UniFi Network Application versions but the menu was
  reorganized multiple times since - the screenshot/instructions are stale for
  this branch and the exact current path is unconfirmed.

All the QoL features from `master` (live logs, auto-refresh status, arch filter,
back button, low-RAM warning, clean uninstall) are kept and adapted.

## What this is

A LoxBerry plugin that runs the UniFi Network Controller in a Docker container and
integrates it into the LoxBerry web UI. There is **no build/test toolchain**: the
plugin is a ZIP installed via the LoxBerry web UI, and the only feedback loop is
the **install log** plus the plugin's own pages and SSH on the LoxBerry.

## Layout

- `plugin.cfg` — plugin metadata (version, `LB_MINIMUM`/`LB_MAXIMUM`, interface).
- `config/*.yaml` — Poppins framework config: `routes.yaml` (route → controller
  action), `navigation.yaml` (navbar items), `services.yaml` (DI container),
  `help.yaml`.
- `src/Controller/HomeController.php` — page actions.
- `src/Service/` — `SystemService` (systemd, docker, env + log files),
  `DockerHubService` (available versions from Docker Hub); model in `src/Model`.
- `views/pages/*.html.twig` — Twig templates; most extend
  `pages/templates/adminBasePage.html.twig`. Text via `trans('section.key')`.
- `src/Docker/` — `docker-compose.yml` (runtime compose file) and `unifi.service`
  (the systemd unit that runs `docker compose up/down`).
- `translations/language_*.ini` — `[section]` / `key=value`.
- Install scripts: `preinstall.sh`, `resources/postinstall.sh`, `postroot.sh`,
  `preupgrade.sh`, `resources/dpkg/apt`, `uninstall/uninstall`.

## Install lifecycle (important)

- `preinstall.sh` (as `loxberry`): checks the Docker **socket** responds, installs
  composer + vendor libs, then moves code into `data/` and resources to the root.
- `resources/dpkg/apt` lists apt packages to install (currently **none** — see gotchas).
- `resources/postinstall.sh` (as `loxberry`): writes `unifi.env` (PUID/PGID),
  links the UniFi `server.log` into the LoxBerry log dir.
- `postroot.sh` (as `root`): sets a default `VERSION` in `config/env` if missing,
  symlinks `config/env` → `src/Docker/.env` and the systemd unit, enables + starts
  the `unifi` service.
- On **upgrade**, `preupgrade.sh` backs up `data/` (the UniFi volume) and the
  `config/` folder (env) to `/tmp/..._upgrade`, and `postroot.sh` copies them back
  — so **VERSION and UniFi data survive an upgrade**, while the yaml/code under
  `data/plugins/unifi/` is installed fresh.
- `REPLACELBP*` placeholders (e.g. `REPLACELBPLOGDIR`) are replaced at install time
  **including inside .php files** — use them to reference runtime paths.

## Hard-won gotchas

- **`docker compose` v2 only.** The systemd unit calls `docker compose` (v2
  subcommand), not `/usr/bin/docker-compose` (v1). Do **not** add `docker-compose`
  to `resources/dpkg/apt`: the Debian package conflicts with `docker-compose-plugin`
  (both own `/usr/libexec/docker/cli-plugins/docker-compose`) and aborts the install.
- **`MEM_LIMIT` is a plain number**, not `1024M`. Newer linuxserver images append
  the `M` themselves; `1024M` becomes `-Xmx1024MM` → "Invalid maximum heap size"
  and the container crash-loops (older images tolerated it).
- **`config/env` needs a trailing newline.** `docker compose` v2 ignores the last
  line of a `.env` file if it is not newline-terminated, leaving `VERSION` unset
  ("invalid reference format: linuxserver/unifi-controller:").
  `SystemService::setContainerVersion` writes the `\n`.
- **The Poppins navbar does not render visibly** on the tested LoxBerry, so pages
  are reached by **path URL**: `/admin/plugins/unifi/<route>` (routes from
  `routes.yaml`; scheme `/admin/plugins/{pluginDir}/{route}`). Provide explicit
  in-page buttons for navigation (e.g. the "back to overview" button).
- **Two logs, two sources.** `docker logs <app container>` shows only container
  init (s6) and stops once the Java app takes over; the real controller startup is
  the UniFi `server.log` in the data volume (`data/logs/server.log`, linked into
  the LoxBerry log dir, readable because the container runs as PUID = `loxberry`).
  During a version change the container is removed/recreated (image pull), so
  `docker logs` reports "No such container" — `SystemService::getContainerLogs`
  then falls back to the `unifi` systemd journal (pull progress). (On the
  `UniFi-Network-Server` branch, see also the Diagnostics page below for the case
  where the container exists but was never started - that fallback does not
  trigger and the box goes blank instead.)
- Names on `master`: container `unifi-controller`, systemd service `unifi`. On
  `UniFi-Network-Server`: app container `unifi-network-application`, database
  container `unifi-db`, systemd service still `unifi`
  (`SystemService::CONTAINER_NAME` / `MONGO_CONTAINER_NAME`).
- **Downgrading** UniFi versions usually breaks the controller (it cannot downgrade
  its database). Treat the switcher as newer-only.
- The `linuxserver/unifi-controller` image is **deprecated** (last 8.0.24); future
  migration target is `unifi-network-application` + a separate MongoDB container.
- `LB_MAXIMUM=false` in `plugin.cfg` so it installs on LoxBerry 4.x.
- No dev server / linter here — verify by installing the ZIP on a real LoxBerry and
  reading the install log, `docker logs`, and `server.log`. Line endings are pinned
  to LF via `.gitattributes` (protects shell scripts when edited on Windows).

## Contributing upstream

Open a PR from `origin` (blacksun80) `master` against `upstream`
(`romanlum/LoxBerry-Plugin-Unfi-Controller`).
