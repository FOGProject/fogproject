#!/bin/bash

# Get the OS Information.
read -r os_name os_version <<< $(lsb_release -ir | cut -d':' -f2 | sed -e 's/^[[:space:]]*//' -e 's/[[:space:]]*$//' | tr '\n' ' ')

# Get the FOG Version.
fog_version=$(cat /var/www/fog/lib/fog/system.class.php | grep FOG_VERSION | cut -d',' -f2 | cut -d"'" -f2)

# Format payload.
payload='{"fog_version":"'${fog_version}'","os_name":"'${os_name}'","os_version":"'${os_version}'"}'

#echo "os_name=${os_name}"
#echo "os_version=${os_version}"
#echo "fog_version=${fog_version}"
#echo "payload=${payload}"

# Send to reporting endpoint.
curl -s -X POST -H "Content-Type: application/json" -d "${payload}" https://fog-analytics-entries.theworkmans.us:/api/records
