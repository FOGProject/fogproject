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

# The name that goes in the web certificate and the vhost.
#
# FOG 1.6 cannot install without one. Every certificate it issues is issued TO a
# name, and two OpenSSL configs interpolate this value directly -- so a server
# with no usable hostname does not degrade, it fails: the Secure Boot signing
# request becomes `subjectAltName = DNS:` and OpenSSL rejects it outright,
# aborting the installer from inside configureHttpd() AFTER it has stopped the
# web server and BEFORE createSSLCA() restarts it. The reported symptom is "the
# web server fails to start after updating", and nothing in the output says
# hostname.
#
# Three things were missing and are added here:
#
#   1. Validation. This prompt has never checked what it was given, from either
#      end -- neither the machine's own answer nor the admin's. A box with no
#      hostname configured reports the kernel's literal default "(none)", which
#      is not empty, so it sailed through the -z test below and straight into
#      the OpenSSL config.
#   2. Re-asking for a bad PERSISTED value. NET_hostname is a managed
#      .fogsettings key, so an unusable value written once was carried forward
#      by every later upgrade, and `while [[ -z ... ]]` never fired for it.
#   3. A guaranteed exit. When `hostname -f` produced nothing, this loop set
#      NET_hostname="" and re-entered -- forever. Under -Y every read is skipped,
#      so it spun silently with no output at all, which is what bin/updatefog.sh
#      does (`installfog.sh -Y` with output redirected to the error log).
#
# The wording below also had to change. It used to promise the name "won't be
# set as a local hostname on your server", which is right for the case it was
# written for -- FOG does not rename a working server just because its
# certificate carries extra names -- and wrong for a server that has no name at
# all, where nothing works until one is set. So the promise is narrowed to what
# it actually means, and applySystemHostname() (lib/common/functions.sh) sets
# the system name in the one case where there is none to preserve.
hostnameNeedsSystemSet=0
systemHostname=$(_detectedHostname)
# Neutral about where the value came from, deliberately. By this point
# ${NET_hostname} may be a persisted .fogsettings value OR one just given as
# --hostname, which installfog.sh applies before this file is sourced. Naming
# either origin would be wrong half the time. --hostname is checked with
# validhostname(), which is a grammar check and accepts `localhost`; this is the
# stricter question of whether the name can serve as a certificate name.
if [[ -n ${NET_hostname} ]] && ! _usableHostname "${NET_hostname}" >/dev/null; then
    echo
    echo "  The configured hostname -- '${NET_hostname}' -- is not a name a certificate"
    echo "  can be issued for, so it is being asked for again."
    NET_hostname=""
fi
[[ -z $systemHostname ]] && hostnameNeedsSystemSet=1
while [[ -z ${NET_hostname} ]]; do
    if [[ -n $systemHostname ]]; then
        # This machine knows its own name. Unchanged behavior: suggest it,
        # offer to override, and default to accepting it.
        if [[ -n $autoaccept ]]; then
            NET_hostname="$systemHostname"
            break
        fi
        echo
        echo "  Which hostname would you like to use? Currently is: ${systemHostname}"
        echo "  Note: This hostname will be in the certificate we generate for your"
        echo "  FOG webserver, and in its web server virtual host. Your server is"
        echo "  already named, so FOG will not rename it."
        echo -n "  Would you like to change it? If you are not sure, select No. [y/N] "
        read blHost
        case $blHost in
            [Nn]|[Nn][Oo]|"")
                NET_hostname="$systemHostname"
                ;;
            [Yy]|[Yy][Ee][Ss])
                echo -n "  Which hostname would you like to use? "
                read answer
                NET_hostname=$(_usableHostname "$answer") \
                    || echo "  '${answer}' is not a usable hostname, please try again."
                ;;
            *)
                echo "  Invalid input, please try again."
                ;;
        esac
        continue
    fi
    # This machine has NO usable name of its own.
    #
    # fogserver is the fallback rather than an invention: _defaultServerNames()
    # already puts it in every certificate FOG issues, because it is the
    # fog-client installer's default value for "FOG Server Address". So a server
    # that ends up called this is covered by its own certificate with nothing
    # else having to change.
    if [[ -n $autoaccept || ! -t 0 ]]; then
        NET_hostname="fogserver"
        echo
        echo "  #################################################################"
        echo "  # WARNING: this server has no hostname set.                     #"
        echo "  #                                                               #"
        echo "  # FOG needs a name to issue its web certificate to, so it is    #"
        echo "  # using 'fogserver' and setting that as this machine's hostname.#"
        echo "  # Pass --hostname <name> to choose your own, or set the system  #"
        echo "  # hostname before installing.                                   #"
        echo "  #################################################################"
        echo
        break
    fi
    echo
    echo "  This server has no hostname set, so FOG has no name to issue its web"
    echo "  certificate to -- and it cannot install without one."
    echo
    echo "  Enter a hostname to use. FOG will put it in the certificate and the"
    echo "  web server virtual host, and will also set it as this machine's"
    echo "  hostname, since there is no existing name to preserve."
    echo
    # -t so an unattended terminal cannot park here indefinitely. Timing out
    # takes the same default a bare Enter does, which is the answer an admin who
    # is unsure should get.
    read -r -t 300 -p "  Hostname [fogserver]: " answer
    [[ -z $answer ]] && answer="fogserver"
    NET_hostname=$(_usableHostname "$answer") \
        || echo "  '${answer}' is not a usable hostname, please try again."
done
# Name constraints for the Web and Secure Boot CAs.
#
# Asked only where it can still be acted on. These are baked into a CA at the
# moment it is issued and a CA is never re-issued, so on a server that already
# has one the answer would be recorded and silently ignored.
#
# $caCreated used to be that test. GH-1120 retired it: it was a persisted key
# standing in for "does the CA exist", and both of its uses already paired it
# with an -e/-f check on the very file it stood in for. Ask the filesystem
# instead -- it cannot go stale, and it is right on a server whose .fogsettings
# was lost. Skipped under -Y as well: the defaults (this server's own domain,
# all RFC1918 ranges) are what an unattended install should get.
fogRootCAPath="${PKI_root_ca_cert:-${PKI_client_cert_dir:-$snapindir/ssl}/CA/.fogCA.pem}"
if [[ -z $autoaccept && ! -f $fogRootCAPath && -z ${PKI_allowed_domain_names} && -z ${PKI_internal_subnets} ]]; then
    echo
    echo "  FOG issues its own certificates from two CAs -- one for web servers,"
    echo "  one for signing FOS kernels. Both are constrained so they can only"
    echo "  ever issue for names inside your own network."
    echo
    echo "  By default that means ${NET_hostname#*.} and every private IP range"
    echo "  (10.x, 172.16-31.x, 192.168.x)."
    echo -n "  Would you like to narrow or extend that? [y/N] "
    read blConstrain
    case $blConstrain in
        [Yy]|[Yy][Ee][Ss])
            echo
            echo "  Additional internal domains these CAs may issue for,"
            echo "  space separated. Leave blank for none."
            echo -n "  Domains: "
            read answer
            for entry in $answer; do
                if [[ $(validhostname "$entry") -ne 0 ]]; then
                    echo "  Ignoring '${entry}': not a valid domain name."
                    continue
                fi
                PKI_allowed_domain_names="${PKI_allowed_domain_names:+${PKI_allowed_domain_names} }${entry}"
            done
            echo
            echo "  Subnets these CAs may issue for, space separated, e.g."
            echo "  10.20.30.0/24. Anything you list here REPLACES the private"
            echo "  ranges above rather than adding to them."
            echo "  Leave blank to keep all private ranges."
            echo -n "  Subnets: "
            read answer
            for entry in $answer; do
                if [[ $(validip "${entry%%/*}") -ne 0 ]]; then
                    echo "  Ignoring '${entry}': not a valid subnet."
                    continue
                fi
                PKI_internal_subnets="${PKI_internal_subnets:+${PKI_internal_subnets} }${entry}"
            done
            ;;
    esac
fi
while [[ -z ${FOG_send_reports} ]]; do
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
            FOG_send_reports="yes"
            ;;
        [Nn]|[Nn][Oo])
            FOG_send_reports="no"
            ;;
        *)
            FOG_send_reports=""
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
# --web-ca-cert/--web-ca-key/--web-ca-root already answer this. Answering it
# twice used to be worse than redundant: the prompt collected extcacert/extcakey/
# extcaroot while validateExternalCA resolved ${webExtCACert:-$extcacert}, so the
# command line always won and anything typed here was silently discarded. Under
# -y the prompt never ran and the flags worked, which made the whole thing look
# like the flags only worked with -y.
#
# GH-1120 collapsed both sets onto one run-scoped input, so the prompt and the
# flags now write the same variables and cannot disagree.
#
# All three, not any: a partial trio still needs the rest collected.
if [[ $externalca == yes && -n $importWebCACert && -n $importWebCAKey && -n $importWebCARoot ]]; then
    echo
    echo "  Using the CA files given on the command line:"
    echo "    intermediate cert: $importWebCACert"
    echo "    intermediate key:  $importWebCAKey"
    echo "    root cert:         $importWebCARoot"
elif [[ $externalca == yes && -z $autoaccept ]]; then
    echo
    echo "  Please provide the paths to your CA files. The intermediate CA"
    echo "  certificate and key are used to sign FOG's server certificate; the"
    echo "  root CA certificate is used as the trust anchor. Press [Enter] to"
    echo "  keep the value shown in brackets (from a previous install)."
    echo
    [[ -n $importWebCACert ]] && dfltcacert=" [$importWebCACert]" || dfltcacert=""
    echo -n "  Path to the intermediate CA certificate (PEM)$dfltcacert: "
    read inextcacert
    [[ -n $inextcacert ]] && importWebCACert="$inextcacert"
    [[ -n $importWebCAKey ]] && dfltcakey=" [$importWebCAKey]" || dfltcakey=""
    echo -n "  Path to the intermediate CA private key (PEM)$dfltcakey: "
    read inextcakey
    [[ -n $inextcakey ]] && importWebCAKey="$inextcakey"
    [[ -n $importWebCARoot ]] && dfltcaroot=" [$importWebCARoot]" || dfltcaroot=""
    echo -n "  Path to the root CA certificate (PEM)$dfltcaroot: "
    read inextcaroot
    [[ -n $inextcaroot ]] && importWebCARoot="$inextcaroot"
fi
