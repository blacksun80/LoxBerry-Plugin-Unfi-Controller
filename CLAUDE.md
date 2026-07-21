# CLAUDE.md — LoxBerry UniFi Controller plugin

Guidance for working on this plugin.

This is a **fork** of `romanlum/LoxBerry-Plugin-Unfi-Controller` (git remote
`upstream`); this fork's remote is `origin` (blacksun80). The plugin is used
directly via the `master` ZIP; there is no published release/auto-update chain.

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
- **Two logs, two sources.** `docker logs unifi-controller` shows only container
  init (s6) and stops once the Java app takes over; the real controller startup is
  the UniFi `server.log` in the data volume (`data/logs/server.log`, linked into
  the LoxBerry log dir, readable because the container runs as PUID = `loxberry`).
  During a version change the container is removed/recreated (image pull), so
  `docker logs` reports "No such container" — `SystemService::getContainerLogs`
  then falls back to the `unifi` systemd journal (pull progress).
- Names: container `unifi-controller`, systemd service `unifi`.
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
