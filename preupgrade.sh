#!/bin/sh

# Bash script which is executed in case of an update (if this plugin is already
# installed on the system). This script is executed as very first step (*BEFORE*
# preinstall.sh) and can be used e.g. to save existing configfiles to /tmp
# during installation. Use with caution and remember, that all systems may be
# different!
#
# Exit code must be 0 if executed successfull.
# Exit code 1 gives a warning but continues installation.
# Exit code 2 cancels installation.
#
# Will be executed as user "loxberry".
#
# You can use all vars from /etc/environment in this script.
#
# We add 5 additional arguments when executing this script:
# command <TEMPFOLDER> <NAME> <FOLDER> <VERSION> <BASEFOLDER>
#
# For logging, print to STDOUT. You can use the following tags for showing
# different colorized information during plugin installation:
#
# <OK> This was ok!"
# <INFO> This is just for your information."
# <WARNING> This is a warning!"
# <ERROR> This is an error!"
# <FAIL> This is a fail!"

# To use important variables from command line use the following code:
COMMAND=$0  # Zero argument is shell command
PTEMPDIR=$1 # First argument is temp folder during install
PSHNAME=$2  # Second argument is Plugin-Name for scipts etc.
PDIR=$3     # Third argument is Plugin installation folder
PVERSION=$4 # Forth argument is Plugin version
#LBHOMEDIR=$5 # Comes from /etc/environment now. Fifth argument is
# Base folder of LoxBerry

# Combine them with /etc/environment
PCGI=$LBPCGI/$PDIR
PHTML=$LBPHTML/$PDIR
PTEMPL=$LBPTEMPL/$PDIR
PDATA=$LBPDATA/$PDIR
PLOG=$LBPLOG/$PDIR # Note! This is stored on a Ramdisk now!
PCONFIG=$LBPCONFIG/$PDIR
PSBIN=$LBPSBIN/$PDIR
PBIN=$LBPBIN/$PDIR

echo "Looking for storage folder in REPLACELBPDATADIR/storage"
if [ -d REPLACELBPDATADIR/storage ]; then
  if [ -d ./storage ]; then
    echo "<INFO> Within your release folder, there is a storage folder. This storage folder will be replaced with the one in your current plugin installation during the update."
    echo "<INFO> The plugin author should remove the storage folder from the release."
    rm -rf ./storage
  fi
  echo "<INFO> Copying storage files from existing release to new release"
  cp -r REPLACELBPDATADIR/storage ./storage
  echo "<OK> Done copying storage"
fi

echo "<INFO> Stopping unifi service"
sudo systemctl stop unifi

echo "<INFO> Creating temporary folders for upgrading"
mkdir -p /tmp/$PTEMPDIR\_upgrade/data
mkdir -p /tmp/$PTEMPDIR\_upgrade/config

if [ -d $PDATA/data ]; then
  echo "<INFO> Backing up existing files"
  cp -v -r $PDATA/data /tmp/$PTEMPDIR\_upgrade/data
fi

cp -v -r $PCONFIG/ /tmp/$PTEMPDIR\_upgrade/config

