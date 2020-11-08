#!/bin/bash

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


echo "<INFO> Copying .htaccess files to target location"
cp ./webfrontend/html/.htaccess REPLACELBPHTMLDIR/.htaccess
cp ./webfrontend/htmlauth/.htaccess REPLACELBPHTMLAUTHDIR/.htaccess

echo "<OK> Done copying .htaccess files"

echo "<INFO> Creating environment"
echo "PUID=`id -u`" > $PCONFIG/unifi.env
echo "PGID=`id -g`" >> $PCONFIG/unifi.env

# Exit with Status 0
exit 0
