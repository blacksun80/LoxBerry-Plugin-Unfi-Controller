#!/bin/bash
# Docker is mandatory. Verify the daemon answers on its unix socket AS THIS USER
# (loxberry) - a working socket returns exit 0 (even the 404 for /ping counts).
# Any non-zero means: socket missing, daemon down, or loxberry has no access.
echo "<INFO> Checking if docker is available"
curl -s --unix-socket /var/run/docker.sock http://ping >/dev/null 2>&1
status=$?
if [ "$status" != "0" ]; then
    echo '<ERROR> Docker is not available (docker socket did not respond).'
    echo '<ERROR> This plugin needs a running Docker Engine reachable as user loxberry.'
    echo '<ERROR> Install the LoxBerry Docker plugin (https://wiki.loxberry.de) or Docker'
    echo '<ERROR> natively, and make sure user loxberry may use /var/run/docker.sock'
    echo '<ERROR> (e.g. add loxberry to the docker group). Aborting installation.'
    exit 2
fi
echo "<OK> Docker is available"

# 64-bit is required: the UniFi Network Application and MongoDB are only published
# as 64-bit images (amd64 / arm64). There are no arm32 images, so a 32-bit host
# cannot run this plugin at all - block the install with a clear message.
echo "<INFO> Checking CPU architecture (64-bit required)"
ARCH=$(uname -m)
case "$ARCH" in
    x86_64|amd64|aarch64|arm64) echo "<OK> 64-bit architecture: $ARCH" ;;
    *)
        echo "<ERROR> Detected 32-bit / unsupported architecture: $ARCH"
        echo "<ERROR> The UniFi Network Application and MongoDB are only available as 64-bit"
        echo "<ERROR> images (amd64 / arm64). A 64-bit LoxBerry (Pi 4/5 or x86) is required."
        exit 2
        ;;
esac

# Warn (do not block) on low-memory hosts. The real constraint is RAM, not the
# CPU architecture or Pi model: the UniFi controller (Java heap + MongoDB) needs
# ~1-2 GB. On 1 GB devices such as the Raspberry Pi 3B it runs slowly or crashes.
echo "<INFO> Checking available memory"
MEM_TOTAL_MB=$(( $(awk '/MemTotal/ {print $2}' /proc/meminfo) / 1024 ))
echo "<INFO> Detected ${MEM_TOTAL_MB} MB RAM"
if [ "$MEM_TOTAL_MB" -lt 1500 ]; then
    echo "<WARNING> This system has only ${MEM_TOTAL_MB} MB RAM."
    echo "<WARNING> The UniFi controller (Java + MongoDB) needs ~1-2 GB. On 1 GB devices"
    echo "<WARNING> (e.g. Raspberry Pi 3B) it may be slow or unstable."
    echo "<WARNING> Consider lowering MEM_LIMIT in src/Docker/docker-compose.yml (e.g. 512),"
    echo "<WARNING> or use a device with more RAM (Raspberry Pi 4 with 2 GB+ recommended)."
fi


echo "<INFO> Copying plugin configuration into installation"
cp ./plugin.cfg ./config/plugin.cfg
echo "<OK> Done copying plugin configuration"

echo "<INFO> Installing composer"
curl -sS https://getcomposer.org/installer -o composer-setup.php
HASH=$(curl -L https://composer.github.io/installer.sig)
php -r "if (hash_file('SHA384', 'composer-setup.php') === '$HASH') { echo 'Installer verified'; } else { echo 'Installer corrupt'; unlink('composer-setup.php'); } echo PHP_EOL;"
php composer-setup.php --install-dir=. --filename=composer

php ./composer -v > /dev/null 2>&1
COMPOSER=$?
if [[ $COMPOSER -ne 0 ]]; then
  echo "<ALERT> Failed to install composer"
  echo "<ALERT> Could not install plugin"
  exit 2
fi

echo "<INFO> Preparing vendor libraries"
php ./composer install --no-interaction --no-dev --no-progress --prefer-dist
echo "<OK> Done installing vendor libraries"

echo "<INFO> Moving translation files to templates folder"
mkdir -p ./resources/templates/lang
find ./translations/*.ini -maxdepth 0 ! -name . ! -name .. -print -exec mv {} ./resources/templates/lang/ \;
rm -rf ./translations

echo "<INFO> Moving asset files to html folder"
rm -rf ./resources/webfrontend/html/assets
mv ./assets ./resources/webfrontend/html/assets

echo "<INFO> Moving all code folders to data"
mkdir ./data
find . -maxdepth 1 -type d ! -name "data" ! -name "resources" ! -name . -print -exec mv {} ./data \;

echo "<INFO> Moving all resource folders and files to plugin root"
find ./resources/* -maxdepth 0 ! -name . ! -name .. -print -exec mv {} ./ \;
rm -rf ./resources

echo "<OK> Done moving files and folders."
echo "<INFO> Ready for installation."

# Exit with Status 0
exit 0
