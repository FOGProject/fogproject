#!/bin/bash
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
# Bootstraps acme.sh to issue and renew the web vhost's LEAF certificate
# against a CA already imported via installfog.sh --external-ca. Never
# touches the imported intermediate/root -- only the leaf -- so fog-client's
# pinned CA never changes across a renewal. acme.sh's own installer sets up
# its own renewal cron job; this script does not add a second one. See
# docs/superpowers/specs/2026-08-07-cert-separation-letsencrypt-design.md and
# FOGProject/fogproject#1013.
bindir=$(dirname $(readlink -f "$BASH_SOURCE"))
cd $bindir
workingdir=$(pwd)

if [[ ! $EUID -eq 0 ]]; then
    echo "setupacme.sh must be run as root user"
    exit 1
fi

usage() {
    echo -e "Usage: $0 [-h?] --directory-url <url> (--http01 | --dns <acme.sh-plugin>) [-d <domain>]"
    echo -e "\t-h -? --help\t\tDisplay this info"
    echo -e "\t      --directory-url\tACME server directory URL (public Let's Encrypt or"
    echo -e "\t                     \tan internal ACME CA such as step-ca)"
    echo -e "\t      --http01\t\tUse HTTP-01 validation (acme.sh's --webroot mode against"
    echo -e "\t               \t\tthis server's own vhost docroot)"
    echo -e "\t      --dns\t\tUse DNS-01 validation via the named acme.sh DNS plugin --"
    echo -e "\t           \t\tthe plugin's own provider credentials must already be set"
    echo -e "\t           \t\tup in this shell's environment; setupacme.sh never stores them"
    echo -e "\t-d\t\t\tDomain to issue the certificate for (repeatable). Defaults to"
    echo -e "\t  \t\t\tthe hostname plus any --extra-server-name from .fogsettings,"
    echo -e "\t  \t\t\tso the leaf covers exactly what the vhost answers to"
    exit 0
}

shortopts="h?d:"
longopts="help,directory-url:,http01,dns:"
optargs=$(getopt -o $shortopts -l $longopts -n "$0" -- "$@")
# Not `usage` -- usage() exits 0, which would report a malformed flag as
# success. getopt -n "$0" already printed its own error to stderr.
[[ $? -ne 0 ]] && exit 9
eval set -- "$optargs"

domains=()
while :; do
    case $1 in
        -h | -\? | --help)
            usage
            ;;
        --directory-url)
            directoryUrl="$2"
            shift 2
            ;;
        --http01)
            validationMethod="http01"
            shift
            ;;
        --dns)
            validationMethod="dns"
            dnsPlugin="$2"
            shift 2
            ;;
        -d)
            domains+=("$2")
            shift 2
            ;;
        --)
            shift
            break
            ;;
        *)
            echo "Error: unhandled option '$1'."
            exit 10
            ;;
    esac
done

[[ ! -d ./error_logs/ ]] && mkdir -p ./error_logs >/dev/null 2>&1
error_log="${workingdir}/error_logs/fog_setupacme_error.log"
: > "$error_log"

if [[ -z $directoryUrl ]]; then
    echo " * --directory-url is required (a public Let's Encrypt endpoint, or an internal ACME CA such as step-ca)."
    exit 9
fi
if [[ -z $validationMethod ]]; then
    echo " * Pass either --http01 or --dns <plugin>."
    exit 9
fi

. ../lib/common/functions.sh

[[ -z $fogprogramdir && -r /etc/fog/fog.conf ]] && . /etc/fog/fog.conf
[[ -z $fogprogramdir ]] && fogprogramdir="/opt/fog"
fogprogramdir="${fogprogramdir%/}"

if [[ ! -r "$fogprogramdir/.fogsettings" ]]; then
    echo " * No existing FOG install found at $fogprogramdir (.fogsettings missing)."
    echo " * setupacme.sh configures an EXISTING install -- run installfog.sh first."
    exit 1
fi
. "$fogprogramdir/.fogsettings"
linuxReleaseName_lower="${osname,,}"
. ../lib/common/config.sh
[[ -n $osid ]] && doOSSpecificIncludes >/dev/null

# Default the domain set to exactly the names the vhost/cert already advertise,
# so an admin who used --hostname/--extra-server-name at install time cannot
# accidentally get an ACME leaf covering fewer names than the vhost answers to.
# Deliberately checked here rather than right after argument parsing: both
# values come from .fogsettings, which is only sourced above.
if [[ ${#domains[@]} -eq 0 ]]; then
    for extraname in $hostname $extraServerNames; do
        domains+=("$extraname")
    done
fi
if [[ ${#domains[@]} -eq 0 ]]; then
    echo " * No -d <domain> given, and no hostname/extra server name found in .fogsettings."
    echo " * Pass at least one -d <domain>."
    exit 9
fi

# Precondition: --external-ca must already have imported a CA. $externalca is
# the only reliable sentinel -- the CA/.fogCA.pem and CA/.fogCA.key paths below
# are written by FOG's OWN self-signed CA path too (same filenames, different
# origin), so testing them alone passes on every install and enforces nothing.
if [[ $externalca != yes ]]; then
    echo " * No external CA configured -- run installfog.sh --external-ca first."
    echo " * setupacme.sh only ever renews a LEAF against a CA you already imported;"
    echo " * it does not create or manage a CA itself."
    exit 1
fi
if [[ ! -e "$sslpath/CA/.fogCA.pem" || ! -e "$sslpath/CA/.fogCA.key" ]]; then
    echo " * --external-ca is configured but its files are missing at $sslpath/CA/ -- re-run installfog.sh --external-ca."
    exit 1
fi

dots "Checking for acme.sh"
if [[ ! -x "$HOME/.acme.sh/acme.sh" ]]; then
    dots "Installing acme.sh"
    # $? here would be sh's exit code, not curl's -- with no network, curl
    # writes nothing, sh reads an empty script and exits 0. The executable
    # check below is the only honest signal that the install actually happened.
    curl -fsSL https://get.acme.sh | sh -s email=root@localhost >>$error_log 2>&1
    if [[ ! -x "$HOME/.acme.sh/acme.sh" ]]; then
        echo " * acme.sh installation failed. See $error_log."
        exit 1
    fi
    echo "Done"
else
    echo "Found"
fi
acmesh="$HOME/.acme.sh/acme.sh"

case $webserver in
    nginx)
        if [[ $systemctl == yes ]]; then
            reloadcmd="systemctl reload nginx"
        else
            reloadcmd="$initdpath/nginx reload"
        fi
        ;;
    httpd|apache*)
        if [[ $systemctl == yes ]]; then
            reloadcmd="systemctl reload $webserver"
        else
            reloadcmd="$initdpath/$webserver reload"
        fi
        ;;
    *)
        echo " * Unrecognized \$webserver ($webserver) -- cannot pick a reload command."
        exit 1
        ;;
esac

domainArgs=()
for domain in "${domains[@]}"; do
    domainArgs+=(-d "$domain")
done

dots "Issuing certificate via acme.sh"
case $validationMethod in
    http01)
        "$acmesh" --issue --server "$directoryUrl" "${domainArgs[@]}" --webroot "$docroot" >>$error_log 2>&1
        ;;
    dns)
        "$acmesh" --issue --server "$directoryUrl" "${domainArgs[@]}" --dns "$dnsPlugin" >>$error_log 2>&1
        ;;
esac
issueStatus=$?
# acme.sh's own exit code 2 means "already valid, no renewal needed yet" --
# not a failure of this run.
if [[ $issueStatus -ne 0 && $issueStatus -ne 2 ]]; then
    echo " * acme.sh --issue failed (exit $issueStatus). See $error_log."
    exit $issueStatus
fi
echo "Done"

dots "Installing certificate"
# --fullchain-file, not --cert-file: $sslpubcert is what the vhost's
# ssl_certificate/SSLCertificateFile points at, so it must carry the
# intermediate as well or clients see an incomplete chain.
"$acmesh" --install-cert "${domainArgs[@]}" \
    --fullchain-file "$sslpubcert" \
    --key-file "$sslprivkey" \
    --reloadcmd "$reloadcmd" >>$error_log 2>&1
errorStat $?

# Tell every later installfog.sh/updatefog.sh run that this leaf is ACME-managed
# so createSSLCA() stops regenerating it from the original (now stale) CSR.
# writeUpdateFile() merges just this key into the existing .fogsettings, but it
# also refreshes the "## Version:" header from $version -- which nothing has set
# in this script, so derive it the same way installfog.sh/updatefog.sh do or the
# header gets blanked as a side effect.
[[ -z $version ]] && version="$(awk -F\' /"define\('FOG_VERSION'[,](.*)"/'{print $4}' ../packages/web/lib/fog/system.class.php | tr -d '[[:space:]]')"
acmeLeaf="yes"
writeUpdateFile

echo " * setupacme.sh complete. acme.sh's own installer already scheduled its"
echo "   own renewal cron job -- no further action is needed for renewals."
