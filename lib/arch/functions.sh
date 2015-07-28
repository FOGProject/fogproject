#
#  FOG is a computer imaging solution.
#  Copyright (C) 2007  Chuck Syperski & Jian Zhang
#
#   This program is free software: you can redistribute it and/or modify
#   it under the terms of the GNU General Public License as published by
#   the Free Software Foundation, either version 3 of the License, or
#    any later version.
#
#   This program is distributed in the hope that it will be useful,
#   but WITHOUT ANY WARRANTY; without even the implied warranty of
#   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
#   GNU General Public License for more details.
#
#   You should have received a copy of the GNU General Public License
#   along with this program.  If not, see <http://www.gnu.org/licenses/>.
#
#
#
configureDHCP() {
    dots "Setting up and starting DHCP Server..."
    if [ -f "$dhcpconfig" ]; then
		mv "$dhcpconfig" "${dhcpconfig}.fogbackup"
	fi
	networkbase=`echo "${ipaddress}" | cut -d. -f1-3`
	network="${networkbase}.0"
	startrange="${networkbase}.10"
	endrange="${networkbase}.254"
	dhcptouse=$dhcpconfig;
	if [ -f "${dhcpconfigother}" ]; then
		dhcptouse=$dhcpconfigother
	fi
	echo "# DHCP Server Configuration file.
# see /usr/share/doc/dhcp*/dhcpd.conf.sample
# This file was created by FOG

# Definition of PXE-specific options
# Code 1: Multicast IP address of bootfile
# Code 2: UDP port that client should monitor for MTFTP responses
# Code 3: UDP port that MTFTP servers are using to listen for MTFTP requests
# Code 4: Number of seconds a client must listen for activity before trying
#         to start a new MTFTP transfer
# Code 5: Number of seconds a client must listen before trying to restart
#         a MTFTP transfer

option space PXE;
option PXE.mtftp-ip    code 1 = ip-address;
option PXE.mtftp-cport code 2 = unsigned integer 16;
option PXE.mtftp-sport code 3 = unsigned integer 16;
option PXE.mtftp-tmout code 4 = unsigned integer 8;
option PXE.mtftp-delay code 5 = unsigned integer 8;
option arch code 93 = unsigned integer 16; # RFC4578

use-host-decl-names on;
ddns-update-style interim;
ignore client-updates;
next-server ${ipaddress};

# Specify subnet of ether device you do NOT want serviced.  For systems with
# two or more ethernet devices.
# subnet 136.165.0.0 netmask 255.255.0.0 { }

subnet ${network} netmask 255.255.255.0 {
        option subnet-mask              255.255.255.0;
        range dynamic-bootp ${startrange} ${endrange};
        default-lease-time 21600;
        max-lease-time 43200;
${dnsaddress}
${routeraddress}
        filename \"undionly.kpxe\";
}" > "$dhcptouse";
	if [ "$bldhcp" = "1" ]; then
		systemctl enable dhcpd >/dev/null 2>&1
		systemctl dhcpd restart >/dev/null 2>&1
		systemctl dhcpd status  >/dev/null 2>&1
        errorStat $?
	else
		echo "Skipped"
	fi
}
