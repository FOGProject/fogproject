#!/bin/bash

FOGROOT="$1"
FOGWEBROOT="$FOGROOT/packages/web"
WEBROOT="$2"

if [ `whoami` != "root" ]; then
    echo "Must run as root."
    exit 1
fi

if [ ! -d "$FOGROOT" ]; then
    echo "usage: $0 fogrootdir webrootdir"
    exit 1
fi

if [ ! -d "$WEBROOT" ]; then
    echo "usage: $0 fogrootdir webrootdir"
    exit 1
fi

CONFIG_FILE="Config.class.php"
CONFIG_FILE_SRC="${WEBROOT}/lib/fog/${CONFIG_FILE}"
BAK_CONFIG_FILE="/tmp/${CONFIG_FILE}"

if [ ! -e "$CONFIG_FILE_SRC" ]; then
    echo "$CONFIG_FILE_SRC doesn't exist."
    exit 1
else
    echo "Backing up $CONFIG_FILE_SRC to $BAK_CONFIG_FILE."
    cp -f "$CONFIG_FILE_SRC" "$BAK_CONFIG_FILE"
fi

echo "Copying from $FOGWEBROOT to $WEBROOT"
tar -cf - -C "$FOGWEBROOT" . | tar -xf - -C "$WEBROOT"

echo "Restoring $CONFIG_FILE_SRC from $BAK_CONFIG_FILE."
cp -f "$BAK_CONFIG_FILE" "$CONFIG_FILE_SRC" 

echo "Fixing ownership"
chown -R www-data:www-data "$WEBROOT"

exit 0
