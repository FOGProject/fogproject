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

while [[ -z $hostname ]]; do
    strSuggestedHostname=$(hostname -f)
    blHost="N"
    if [[ -z $autoaccept ]]; then
        echo
        echo "  Which hostname would you like to use? Currently is: ${strSuggestedHostname}"
        echo "  Note: This hostname will be in the certificate we generate for your"
        echo "  FOG webserver. The hostname will only be used for this but won't be"
        echo "  set as a local hostname on your server!"
        echo -n "  Would you like to change it? If you are not sure, select No. [y/N] "
        read blHost
    fi
    case $blHost in
        [Nn]|[Nn][Oo]|"")
            hostname=$strSuggestedHostname
            ;;
        [Yy]|[Yy][Ee][Ss])
            echo -n "  Which hostname would you like to use? "
            read hostname
            ;;
        *)
            echo "  Invalid input, please try again."
            ;;
    esac
done
while [[ -z $sendreports ]]; do
    blReports="Y"
    if [[ -z $autoaccept ]]; then
        echo "  FOG would like to collect some data:"
        echo "      We would like to collect the following information:"
        echo "        1. OS Name (CentOS, RedHat, Debian, etc....)"
        echo "        2. OS Version (8.0.2004, 7.2.1409, 9, etc....)"
        echo "        3. FOG Version (1.5.9, 1.6, etc....)"
        echo
        echo "  What is this information used for?"
        echo "      We would like to simply track the common types of OS"
        echo "      being used, along with the OS Version, and the various"
        echo "      versions of FOG being used."
        echo
        echo -n "  Are you ok with sending this information? [Y/n] "
        read blReports
    fi
    case $blReports in
        [Yy]|[Yy][Ee][Ss]|"")
            sendreports="Y"
            ;;
        [Nn]|[Nn][Oo])
            sendreports="N"
            ;;
        *)
            sendreports=""
            echo "  Invalid input, please try again."
            ;;
    esac
done
while [[ -z $externalca ]]; do
    blExtCA="N"
    if [[ -z $autoaccept && -z $sexternalca ]]; then
        echo
        echo "  By default FOG generates its own self-signed Certificate Authority"
        echo "  (CA) and uses it to sign the SSL certificate for the web server, the"
        echo "  iPXE binaries and the fog-client. If you already have an external or"
        echo "  intermediate CA (for example one issued by Smallstep step-ca), FOG"
        echo "  can sign its server certificate with that CA instead so the chain of"
        echo "  trust rolls up to your own authority."
        echo -n "  Do you have an existing external/intermediate CA to use? [y/N] "
        read blExtCA
    fi
    [[ -n $sexternalca ]] && blExtCA="Y"
    case $blExtCA in
        [Nn]|[Nn][Oo]|"")
            externalca="no"
            ;;
        [Yy]|[Yy][Ee][Ss])
            externalca="yes"
            ;;
        *)
            externalca=""
            echo "  Invalid input, please try again."
            ;;
    esac
done
if [[ $externalca == yes && -z $autoaccept ]]; then
    echo
    echo "  Please provide the paths to your CA files. The intermediate CA"
    echo "  certificate and key are used to sign FOG's server certificate; the"
    echo "  root CA certificate is used as the trust anchor. Press [Enter] to"
    echo "  keep the value shown in brackets (from a previous install)."
    echo
    [[ -n $extcacert ]] && dfltcacert=" [$extcacert]" || dfltcacert=""
    echo -n "  Path to the intermediate CA certificate (PEM)$dfltcacert: "
    read inextcacert
    [[ -n $inextcacert ]] && extcacert="$inextcacert"
    [[ -n $extcakey ]] && dfltcakey=" [$extcakey]" || dfltcakey=""
    echo -n "  Path to the intermediate CA private key (PEM)$dfltcakey: "
    read inextcakey
    [[ -n $inextcakey ]] && extcakey="$inextcakey"
    [[ -n $extcaroot ]] && dfltcaroot=" [$extcaroot]" || dfltcaroot=""
    echo -n "  Path to the root CA certificate (PEM)$dfltcaroot: "
    read inextcaroot
    [[ -n $inextcaroot ]] && extcaroot="$inextcaroot"
fi
