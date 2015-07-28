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
    dots "Setting up and starting DHCP Server";
	activeconfig="/dev/null";
	if [ -f "$dhcpconfig" ]
	then
		mv "$dhcpconfig" "${dhcpconfig}.fogbackup"
		activeconfig="$dhcpconfig"
	elif [ -f "$olddhcpconfig" ]
	then
		mv "$olddhcpconfig" "${olddhcpconfig}.fogbackup"
		activeconfig="$olddhcpconfig"
	fi

	networkbase=`echo "${ipaddress}" | cut -d. -f1-3`;
	network="${networkbase}.0";
	startrange="${networkbase}.10";
	endrange="${networkbase}.254";

	echo "# DHCP Server Configuration file.
# see /usr/share/doc/dhcp*/dhcpd.conf.sample
# This file was created by FOG
use-host-decl-names on;
ddns-update-style interim;
ignore client-updates;
next-server ${ipaddress};

subnet ${network} netmask 255.255.255.0 {
        option subnet-mask              255.255.255.0;
        range dynamic-bootp ${startrange} ${endrange};
        default-lease-time 21600;
        max-lease-time 43200;
${dnsaddress}
${routeraddress}
        filename \"undionly.kpxe\";
}" > "$activeconfig";
	if [ "$bldhcp" = "1" ]; then
        if [ "$systemctl" ]; then
			systemctl enable ${dhcpname} >/dev/null 2>&1;
			systemctl enable ${olddhcpname} >/dev/null 2>&1;
			systemctl restart ${dhcpname} >/dev/null 2>&1;
			try1="$?";
			systemctl restart ${dhcpname} >/dev/null 2>&1;
			try2="$?";
		else
			sysv-rc-conf ${dhcpname} on >/dev/null 2>&1;
			sysv-rc-conf ${olddhcpname} on >/dev/null 2>&1;

			/etc/init.d/${dhcpname} stop >/dev/null 2>&1;
			/etc/init.d/${dhcpname} start >/dev/null 2>&1;
			try1="$?";

			/etc/init.d/${olddhcpname} stop >/dev/null 2>&1;
			/etc/init.d/${olddhcpname} start >/dev/null 2>&1;
			try2="$?";
		fi
		if [ "$try1" != "0" -o "$try2" != "0" ]
		then
			echo "OK";
		else
			echo "Failed!";
			exit 1;
		fi
	else
		echo "Skipped";
	fi

}
