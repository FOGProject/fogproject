#!/bin/bash

# Get the OS Information.
read -r os_name os_version <<< $(lsb_release -ir | cut -d':' -f2 | sed -e 's/^[[:space:]]*//' -e 's/[[:space:]]*$//' | tr '\n' ' ')

# Try another method if lsb_release is not installed or returned empty values
[[ -z $os_name ]] && os_name=$(sed -n 's/^NAME=\(.*\)/\1/p' /etc/os-release | tr -d '"')
[[ -z $os_version ]] && os_version=$(sed -n 's/^VERSION_ID=\([^.]*\).*/\1/p' /etc/os-release | tr -d '"')

# Get the FOG Version.
# GH-850: this runs from cron at $fogprogramdir/reporting/report.sh with no
# installer context, so resolve the base path from the pointer the installer
# wrote before reading .fogsettings out of it.
[[ -z $fogprogramdir && -r /etc/fog/fog.conf ]] && source /etc/fog/fog.conf
[[ -z $fogprogramdir ]] && fogprogramdir="/opt/fog"
source ${fogprogramdir%/}/.fogsettings
# Both spellings: this cron job reads the installed web tree, and the version
# file moved to src/Base/System.php when core became PSR-4 on working-1.6.
system_class_php=${WEB_docroot}/${WEB_root}/src/Base/System.php
[[ ! -f ${system_class_php} ]] && system_class_php=${WEB_docroot}/${WEB_root}/lib/fog/system.class.php
fog_version=$(cat ${system_class_php} | grep FOG_VERSION | cut -d',' -f2 | cut -d"'" -f2)

# Construct correct mysql options.
options="-sN"
if [[ ${DB_host} != "" ]]; then
        options="$options -h${DB_host}"
fi
if [[ ${DB_user} != "" ]]; then
        options="$options -u${DB_user}"
fi
if [[ ${DB_password} != "" ]]; then
        options="$options -p${DB_password}"
fi
options="$options -D ${DB_name} -e"

# Construct sql statements.
FOG_TFTP_PXE_KERNEL_32_select='select settingValue from globalSettings WHERE settingKey = "FOG_TFTP_PXE_KERNEL_32";'
FOG_TFTP_PXE_KERNEL_select='select settingValue from globalSettings WHERE settingKey = "FOG_TFTP_PXE_KERNEL";'
FOG_TFTP_PXE_KERNEL_DIR_select='select settingValue from globalSettings WHERE settingKey = "FOG_TFTP_PXE_KERNEL_DIR";'
FOG_HOSTS_KERNELS_select='SELECT DISTINCT hostKernel FROM hosts;'

# Execute sql statements, get values.
FOG_TFTP_PXE_KERNEL_32=$(mysql $options "$FOG_TFTP_PXE_KERNEL_32_select")
FOG_TFTP_PXE_KERNEL=$(mysql $options "$FOG_TFTP_PXE_KERNEL_select")
FOG_TFTP_PXE_KERNEL_DIR=$(mysql $options "$FOG_TFTP_PXE_KERNEL_DIR_select")
FOG_HOST_KERNELS=$(mysql $options "$FOG_HOSTS_KERNELS_select")

# Get kernel information.
## Begin building the JSON array to send.
kernel_versions_info='['

# Begin processing global 32 bit kernel.
# Check if 32 bit global kernel file exists.
if [[ -f ${FOG_TFTP_PXE_KERNEL_DIR}${FOG_TFTP_PXE_KERNEL_32} ]]; then
    # Get file information.
    file_information=$(file --no-pad --brief ${FOG_TFTP_PXE_KERNEL_DIR}${FOG_TFTP_PXE_KERNEL_32})
    # Check if this is a linux kernel or not.
    if [[ "${file_information}" == *"Linux kernel"* ]]; then
        # Here, we are pretty sure the current file we're looking at is a Linux kernel. Parse the version information.
        version=$(echo ${file_information} | cut -d, -f2 | sed 's/version*//' | cut -d "#" -f 1 | xargs)
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
fi

# Begin processing 64 bit global kernel.
# Check if global kernel file exists.
if [[ -f ${FOG_TFTP_PXE_KERNEL_DIR}${FOG_TFTP_PXE_KERNEL} ]]; then
    # Get file information.
    file_information=$(file --no-pad --brief ${FOG_TFTP_PXE_KERNEL_DIR}${FOG_TFTP_PXE_KERNEL})
    # Check if this is a linux kernel or not.
    if [[ "${file_information}" == *"Linux kernel"* ]]; then
        # Here, we are pretty sure the current file we're looking at is a Linux kernel. Parse the version information.
        version=$(echo ${file_information} | cut -d, -f2 | sed 's/version*//' | cut -d "#" -f 1 | xargs)
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
fi

# Begin processing each unique host kernel that is not a global kernel.
for host_kernel in $FOG_HOST_KERNELS; do
    # Check if this is the name of the 32 or 64 bit global kernel. If so, skip it.
    if [[ "${host_kernel}" != "${FOG_TFTP_PXE_KERNEL}" && "${host_kernel}" != "${FOG_TFTP_PXE_KERNEL_32}" ]]; then
        if [[ -f ${FOG_TFTP_PXE_KERNEL_DIR}${host_kernel} ]]; then
            # Get file information.
            file_information=$(file --no-pad --brief ${FOG_TFTP_PXE_KERNEL_DIR}${host_kernel})
            # Check if this is a linux kernel or not.
            if [[ "${file_information}" == *"Linux kernel"* ]]; then
                # Here, we are pretty sure the current file we're looking at is a Linux kernel. Parse the version information.
                version=$(echo ${file_information} | cut -d, -f2 | sed 's/version*//' | cut -d "#" -f 1 | xargs)
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
        fi
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
curl -s -X POST -H "Content-Type: application/json" -d "${payload}" https://fog-external-reporting-entries.fogproject.us:/api/records
