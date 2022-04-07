#!/bin/bash


# Get the OS Information.
read -r os_name os_version <<< $(lsb_release -ir | cut -d':' -f2 | sed -e 's/^[[:space:]]*//' -e 's/[[:space:]]*$//' | tr '\n' ' ')


# Get the FOG Version.
source /opt/fog/.fogsettings
system_class_php=${docroot}/${webroot}/lib/fog/system.class.php
fog_version=$(cat ${system_class_php} | grep FOG_VERSION | cut -d',' -f2 | cut -d"'" -f2)


# Get kernel information.
## Begin building the JSON array to send.
kernel_versions_info='['
## Loop over all files where fog kernels are normally stored.
for filename in ${docroot}/${webroot}/service/ipxe/*; do
    # Get the absolute paths of each file, for cleaner handling.
    absolute_path=$(readlink -f ${filename})
    # Get file information about each file.
    file_information=$(file --no-pad --brief $absolute_path)
    # Check if "Linux kernel" is a substring in the file information or not.
    if [[ "${file_information}" == *"Linux kernel"* ]]; then
        # Here, we are pretty sure the current file we're looking at is a Linux kernel. Parse the version information.
        version=$(echo ${file_information} | cut -d, -f2 | sed 's/version*//' | xargs)
        # If there are any double quotes in this version information, add a backslash in front of them for JSON escaping.
        version=$(echo $version | sed 's/"/\\"/g')
        # Wrap the version in double quotes for JSON syntax.
        version="\"${version}\""
        # Check if the last character in the kernel_versions_info variable is a double quote. If so, add a leading comma.
        if [[ "${kernel_versions_info: -1}" == '"' ]]; then
            version=",${version}"
        fi
        # Append version to kernel_versions_info JSON list.
        kernel_versions_info="${kernel_versions_info}${version}"
    fi
done
# Finish JSON list formatting.
kernel_versions_info="${kernel_versions_info}]"


# Format payload.
payload='{"fog_version":"'${fog_version}'","os_name":"'${os_name}'","os_version":"'${os_version}'","kernel_versions_info":'${kernel_versions_info}'}'

#echo "os_name=${os_name}"
#echo "os_version=${os_version}"
#echo "fog_version=${fog_version}"
#echo "kernel_versions_info=${kernel_versions_info}"
#echo "payload=${payload}"


# Send to reporting endpoint.
curl -s -X POST -H "Content-Type: application/json" -d "${payload}" https://fog-external-reporting-entries.theworkmans.us:/api/records
