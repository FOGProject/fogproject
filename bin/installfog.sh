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
bindir=$(dirname $(readlink -f "$BASH_SOURCE"))
cd $bindir
workingdir=$(pwd)

if [[ ! $EUID -eq 0 ]]; then
    echo "FOG Installation must be run as root user"
    exit 1 # Fail Sudo
fi

# The installer calls a number of tools that live in an sbin directory, so it
# has to be sure they are reachable. This used to be inferred rather than
# tested: `which adduser`, plus a demand that the string "sbin" appear at least
# TWICE in $PATH -- a stand-in for "/sbin and /usr/sbin are both listed".
#
# That stand-in stopped being true once distributions merged /sbin and
# /usr/sbin into /usr/bin. A correct root PATH may now name a single sbin
# directory, and got rejected. Arch is the clearest case (GH-447): root's login
# PATH is /usr/local/sbin:/usr/local/bin:/usr/bin, "sbin" occurs once, and the
# installer refused to start while telling the user to load root's environment
# -- which they had. The reporter's workaround proves the check was measuring
# nothing: appending /usr/sbin got them past it, and on Arch /usr/sbin is a
# symlink to /usr/bin, so it added no binaries at all. It only made the
# substring appear a second time.
#
# So ask the real question. Put the standard sbin directories on PATH when they
# exist and are not already listed, then check we can actually reach the tool.
#
# Only account creation is tested here, deliberately. Widening the list would
# make this gate reject platforms it used to allow: Alpine has no groupadd or
# usermod at all (busybox ships addgroup/adduser, and shadow supplies them only
# because FOG now asks for it).
#
# Either tool is accepted. configureUsers prefers adduser where it takes the
# long options and falls back to useradd otherwise, because Arch ships no
# adduser and Alpine's is the busybox applet.
# The perl directories are here for the same reason. Arch keeps perl's own
# scripts out of /usr/bin -- pod2man lives in /usr/bin/core_perl -- and adds
# them to PATH from /etc/profile.d/perlbin.sh. Relying on the invoking shell
# having sourced that is the same mistake as relying on it for sbin, and it
# shows up late and confusingly: the UDPCast build dies at "pod2man: command
# not found" long after everything else has succeeded. They do not exist
# elsewhere, so adding them costs nothing.
for sbindir in /usr/local/sbin /usr/sbin /sbin /usr/bin/core_perl /usr/bin/vendor_perl /usr/bin/site_perl; do
    [[ -d $sbindir ]] || continue
    case ":${PATH}:" in
        *:"${sbindir}":*) ;;
        *) PATH="${PATH}:${sbindir}" ;;
    esac
done
export PATH
if ! command -v adduser >/dev/null 2>&1 && ! command -v useradd >/dev/null 2>&1; then
    echo "The installer could not find 'adduser' or 'useradd'."
    echo
    echo "They normally live in an sbin directory. If you became root with a"
    echo "plain 'su' or with 'sudo', switch using 'sudo -i' or 'su -' instead"
    echo "(skip the ' and note the hyphen at the end of the su command, as it"
    echo "is what loads root's own environment)."
    echo
    echo "If neither is genuinely installed, FOG cannot create its system"
    echo "account and the install would fail later on regardless."
    exit 1
fi

[[ -z $OS ]] && OS=$(uname -s)
if [[ ! $(echo "$OS" | tr [:upper:] [:lower:]) =~ "linux" ]]; then
    echo "We do not currently support Installation on non-Linux Operating Systems"
    exit 2 # Fail OS Check
fi

[[ -z $version ]] && version="$(awk -F\' /"define\('FOG_VERSION'[,](.*)"/'{print $4}' ../packages/web/lib/fog/system.class.php | tr -d '[[:space:]]')"
[[ ! -d ./error_logs/ ]] && mkdir -p ./error_logs >/dev/null 2>&1
error_log=${workingdir}/error_logs/fog_error_${version}.log
timestamp=$(date +%s)
backupconfig=""
. ../lib/common/functions.sh
. ../lib/common/uninstall.sh
usage() {
    echo -e "Usage: $0 [-h?odEUHSCKYyXTFl] [-f <filename>] [-N <databasename>]"
    echo -e "\t\t[-D </directory/to/document/root/>] [-c <ssl-path>]"
    echo -e "\t\t[-W <webroot/to/fog/after/docroot/>] [-B </backup/path/>]"
    echo -e "\t\t[-s <192.168.1.10>] [-e <192.168.1.254>]"
    echo -e "\t-h -? --help\t\t\tDisplay this info"
    echo -e "\t-o    --oldcopy\t\t\tCopy back old data"
    echo -e "\t-d    --no-defaults\t\tDon't guess defaults"
    echo -e "\t-U    --no-upgrade\t\tDon't attempt to upgrade"
    echo -e "\t-H    --no-htmldoc\t\tNo htmldoc, means no PDFs"
    echo -e "\t      --install-mode\t\tPreset for the four settings below."
    echo -e "\t                  \t\t\tstandard (default): HTTPS web UI, HTTP"
    echo -e "\t                  \t\t\t  netboot, no redirect, no rebuild"
    echo -e "\t                  \t\t\thttp-only: plain HTTP everywhere"
    echo -e "\t                  \t\t\tpublic-cert: a publicly-trusted cert, so"
    echo -e "\t                  \t\t\t  netboot can use HTTPS with no rebuild"
    echo -e "\t                  \t\t\tembed-ca: rebuild iPXE with your CA"
    echo -e "\t                  \t\t\t  (adds 10-25 min and a Secure Boot step)"
    echo -e "\t-S    --force-https\t\tForce the HTTP->HTTPS redirect"
    echo -e "\t      --https-redirect\t\t  (same thing, clearer name)"
    echo -e "\t      --no-force-https\t\tUndo --force-https: serve both HTTP and"
    echo -e "\t      --no-https-redirect\t\t\tHTTPS without redirecting"
    echo -e "\t      --public-web-cert\t\tThe web certificate chains to a PUBLIC"
    echo -e "\t                       \t\t\troot, so iPXE can validate it without"
    echo -e "\t                       \t\t\ta rebuild. Needs an FQDN, not an IP"
    echo -e "\t      --no-public-web-cert\tUndo --public-web-cert"
    echo -e "\t      --rebuild-ipxe-with-my-ca\tRebuild iPXE embedding the"
    echo -e "\t                              \t\tconfigured CA. Slow, and the"
    echo -e "\t                              \t\tresult is not upstream's signed"
    echo -e "\t                              \t\tbinary, so its MOK must be"
    echo -e "\t                              \t\tenrolled before a client netboots"
    echo -e "\t      --no-rebuild-ipxe-with-my-ca\tUndo the above"
    echo -e "\t      --netboot-proto\t\thttp or https: the protocol iPXE uses to"
    echo -e "\t      --boot-delay\t\tseconds to sleep before the first DHCP"
    echo -e "\t\t\t\t\tattempt, for switches slow out of STP or"
    echo -e "\t\t\t\t\tpowersave. 0 (default) writes no sleep."
    echo -e "\t                     \t\t\tfetch boot.php. Defaults to http, and"
    echo -e "\t                     \t\t\tto https when the certificate is public"
    echo -e "\t                     \t\t\tor iPXE was rebuilt with your CA"
    echo -e "\t-C    --recreate-CA\t\tRecreate the CA Keys"
    echo -e "\t                   \t\t\tImplies --recreate-keys below, and"
    echo -e "\t                   \t\t\tre-anchors what fog-client pins"
    echo -e "\t-K    --recreate-keys\t\tRecreate the SSL Keys"
    echo -e "\t                     \t\t\tReplaces the client communication"
    echo -e "\t                     \t\t\tkeypair. EVERY registered fog-client"
    echo -e "\t                     \t\t\tmust then be reinstalled or re-pinned"
    echo -e "\t      --external-ca\t\tSign FOG's server certificate with an"
    echo -e "\t                  \t\t\texisting external/intermediate CA instead"
    echo -e "\t                  \t\t\tof generating a self-signed CA"
    echo -e "\t      --ca-cert\t\t\tPath to the intermediate CA certificate (PEM)"
    echo -e "\t      --ca-key\t\t\tPath to the intermediate CA private key (PEM)"
    echo -e "\t      --ca-root\t\t\tPath to the root CA certificate (PEM)"
    echo -e "\t-Y -y --autoaccept\t\tAuto accept defaults and install"
    echo -e "\t-f    --file\t\t\tUse different update file"
    echo -e "\t-c    --ssl-path\t\tSpecify the ssl path"
    echo -e "\t               \t\t\t\tdefaults to /opt/fog/snapins/ssl"
    echo -e "\t-D    --docroot\t\t\tSpecify the Apache Docroot for fog"
    echo -e "\t               \t\t\t\tdefaults to OS DocumentRoot"
    echo -e "\t-W    --webroot\t\t\tSpecify the web root url want fog to use"
    echo -e "\t            \t\t\t\t(E.G. http://127.0.0.1/fog,"
    echo -e "\t            \t\t\t\t      http://127.0.0.1/)"
    echo -e "\t            \t\t\t\tDefaults to /fog/"
    echo -e "\t      --fogprogramdir\t\tSpecify the FOG base directory"
    echo -e "\t               \t\t\t\tdefaults to /opt/fog"
    echo -e "\t               \t\t\t\tremembered in /etc/fog/fog.conf, so it"
    echo -e "\t               \t\t\t\tonly needs giving on a first install"
    echo -e "\t      --hostname\t\tOverride the vhost/cert hostname"
    echo -e "\t                \t\tdefaults to \`hostname -f\`, remembered in .fogsettings"
    echo -e "\t      --extra-server-name\tAdd an extra vhost/cert name (repeatable)"
    echo -e "\t                       \t\talongside the primary hostname and detected IPs"
    echo -e "\t      --internal-domain\t\tPermit this domain in the Web and Secure Boot"
    echo -e "\t                 \t\t\tCAs' name constraints (repeatable). The server's"
    echo -e "\t                 \t\t\town domain is always permitted"
    echo -e "\t      --internal-subnet\t\tRestrict those CAs to this subnet, e.g."
    echo -e "\t                 \t\t\t10.20.30.0/24 (repeatable). REPLACES the default"
    echo -e "\t                 \t\t\tof all RFC1918 ranges"
    echo -e "\t      --web-ca-cert/-key/-root\tBring your own CA for the WEB zone only"
    echo -e "\t                 \t\t\t(equivalent to --external-ca --ca-*)"
    echo -e "\t      --client-cert/--client-key\tYour own CLIENT COMMUNICATION keypair,"
    echo -e "\t                 \t\t\tthe one every registered fog-client pins. Both"
    echo -e "\t                 \t\t\tor neither. Swapping it warns and proceeds --"
    echo -e "\t                 \t\t\tevery client must then be reinstalled or"
    echo -e "\t                 \t\t\tre-pinned"
    echo -e "\t      --secureboot-ca-cert\tYour own SECURE BOOT intermediate: the"
    echo -e "\t                 \t\t\tcertificate enrolled in firmware. Pair it with"
    echo -e "\t                 \t\t\t--secure-boot-key/--secure-boot-cert, which name"
    echo -e "\t                 \t\t\tthe code-signing leaf issued from it. Rotate the"
    echo -e "\t                 \t\t\tleaf freely; the enrolled CA never changes"
    echo -e "\t      --kernel-backup-count\tHow many prior kernel/init generations to"
    echo -e "\t                       \t\tkeep (default 3). Restore one with"
    echo -e "\t                       \t\tbin/restorekernel.sh. See"
    echo -e "\t                       \t\tdocs/SUPPORTED_CUSTOMIZATIONS.md"
    echo -e "\t      --restore-kernel-backup\tAlso restore the previous kernel/init set"
    echo -e "\t                       \t\tthis run. Used by updatefog.sh when reverting;"
    echo -e "\t                       \t\tnot normally passed by hand"
    echo -e "\t-N    --mysqldbname\t\tSpecify the FOG database name"
    echo -e "\t               \t\t\t\tdefaults to fog"
    echo -e "\t-B    --backuppath\t\tSpecify the backup path"
    echo -e "\t      --uninstall\t\tUninstall FOG. Removes FOG's own files,"
    echo -e "\t               \t\t\t\tservices and config, and restores the"
    echo -e "\t               \t\t\t\tfiles FOG replaced. Your database,"
    echo -e "\t               \t\t\t\timages, snapins, SSL CA and the fog"
    echo -e "\t               \t\t\t\taccount are KEPT unless purged below."
    echo -e "\t               \t\t\t\tPackages are never removed."
    echo -e "\t      --dry-run\t\t\tWith --uninstall, list what would be"
    echo -e "\t               \t\t\t\tremoved and exit without changing anything"
    echo -e "\t      --force\t\t\tWith --uninstall, skip the typed"
    echo -e "\t               \t\t\t\tconfirmation (-Y does NOT skip it)"
    echo -e "\t      --purge-db\t\tAlso drop the FOG database"
    echo -e "\t      --purge-images\t\tAlso delete the image storage"
    echo -e "\t      --purge-snapins\t\tAlso delete the snapins"
    echo -e "\t      --purge-ssl\t\tAlso delete the SSL CA. This permanently"
    echo -e "\t               \t\t\t\tbreaks every deployed fog-client"
    echo -e "\t      --purge-user\t\tAlso delete the fog Linux account"
    echo -e "\t      --purge-all\t\tAll of the --purge-* options above"
    echo -e "\t-s    --startrange\t\tDHCP Start range"
    echo -e "\t-e    --endrange\t\tDHCP End range"
    echo -e "\t-E    --no-exportbuild\t\tSkip building nfs file"
    echo -e "\t-X    --exitFail\t\tDo not exit if item fails"
    echo -e "\t-T    --no-tftpbuild\t\tDo not rebuild the tftpd config file"
    echo -e "\t-F    --no-vhost\t\tDo not touch the vhost file at all. FOG"
    echo -e "\t                \t\t\tnormally rewrites only the region between its"
    echo -e "\t                \t\t\tMANAGED BLOCK markers and leaves your own"
    echo -e "\t                \t\t\tadditions alone, so skipping also skips its"
    echo -e "\t                \t\t\tsecurity fixes to the parts it owns."
    echo -e "\t                \t\t\tSee docs/SUPPORTED_CUSTOMIZATIONS.md"
    echo -e "\t-l    --list-packages\t\tList of the basic packages FOG needs for install or is currently installed for FOG"
    echo -e "\t      --secure-boot-key\t\tPrivate key used to re-sign the FOS"
    echo -e "\t                       \t\t\tkernels for UEFI Secure Boot"
    echo -e "\t      --secure-boot-cert\tCertificate matching --secure-boot-key"
    echo -e "\t                        \t\t\t(both are required together)"
    echo -e "\t      --no-secure-boot\t\tDo not publish Secure Boot ENROLMENT"
    echo -e "\t                      \t\t\tmaterial: no MOK.der, no PK/KEK/db.auth,"
    echo -e "\t                      \t\t\tand no 'Enroll Secure Boot Key' menu"
    echo -e "\t                      \t\t\tentry. Binaries are still signed -- a"
    echo -e "\t                      \t\t\tsignature is inert with Secure Boot off"
    exit 0
}

sPKI_san_dns_names=()

shortopts="h?odEUHSCKYyXTFf:c:W:D:B:s:e:N:l"
longopts="help,uninstall,purge-db,purge-images,purge-snapins,purge-ssl,purge-user,purge-all,dry-run,force,mysqldbname:,ssl-path:,oldcopy,no-vhost,no-defaults,no-upgrade,no-htmldoc,force-https,no-force-https,https-redirect,no-https-redirect,public-web-cert,no-public-web-cert,rebuild-ipxe-with-my-ca,no-rebuild-ipxe-with-my-ca,install-mode:,recreate-keys,recreate-CA,recreate-Ca,recreate-cA,recreate-ca,external-ca,ca-cert:,ca-key:,ca-root:,autoaccept,file:,docroot:,webroot:,backuppath:,startrange:,endrange:,no-exportbuild,exitFail,no-tftpbuild,list-packages,fogprogramdir:,secure-boot-key:,secure-boot-cert:,no-secure-boot,hostname:,extra-server-name:,kernel-backup-count:,restore-kernel-backup,netboot-proto:,boot-delay:,web-ca-cert:,web-ca-key:,web-ca-root:,secureboot-ca-cert:,internal-domain:,internal-subnet:"

optargs=$(getopt -o $shortopts -l $longopts -n "$0" -- "$@")
[[ $? -ne 0 ]] && usage
eval set -- "$optargs"

while :; do
    case $1 in
		-h | -\? | --help)
			usage
			exit 0
			;;
		--uninstall)
			# Only flags the intent. The uninstall itself runs further down,
			# once .fogsettings and the distro config have been read -- it
			# cannot know what to remove before then.
			douninstall=1
			shift
			;;
		--purge-db|--purge-images|--purge-snapins|--purge-ssl|--purge-user)
			printf -v "purge${1#--purge-}" 1
			shift
			;;
		--purge-all)
			purgedb=1; purgeimages=1; purgesnapins=1; purgessl=1; purgeuser=1
			shift
			;;
		--dry-run)
			uninstalldryrun=1
			shift
			;;
		--force)
			uninstallforce=1
			shift
			;;
        -c | --ssl-path)
            if [[ -n "${2}" ]] && [[ "${2}" != -* ]]; then
                sPKI_client_cert_dir="${2}"
                sPKI_client_cert_dir="${sPKI_client_cert_dir#'/'}"
                sPKI_client_cert_dir="${sPKI_client_cert_dir%'/'}"
                sPKI_client_cert_dir="/${sPKI_client_cert_dir}/"
            else
                echo "Error: Missing argument for --$1"
                usage
                exit 9
            fi
            shift 2
            ;;
        --fogprogramdir)
            # GH-850: the FOG base directory. Needed on a FIRST install to a
            # non-default path; afterwards /etc/fog/fog.conf remembers it, so
            # upgrades do not have to repeat the flag.
            if [[ -n "${2}" ]] && [[ "${2}" == /* ]]; then
                sfogprogramdir="${2%/}"
            else
                echo "Error: --fogprogramdir requires an absolute path"
                usage
                exit 9
            fi
            shift 2
            ;;
        --hostname)
            if [[ -n "${2}" ]] && [[ $(validhostname "${2}") -eq 0 ]]; then
                sNET_hostname="${2}"
            else
                echo "Error: --hostname requires a valid hostname"
                exit 9
            fi
            shift 2
            ;;
        --extra-server-name)
            if [[ -n "${2}" ]] && [[ $(validhostname "${2}") -eq 0 ]]; then
                sextraServerNames+=("${2}")
            else
                echo "Error: --extra-server-name requires a valid hostname"
                exit 9
            fi
            shift 2
            ;;
        -o | --oldcopy)
            sFOG_copy_back_old="yes"
            shift
			;;
        -d | --no-defaults)
            guessdefaults=0
            shift
            ;;
        -U | --no-upgrade)
            doupdate=0
            shift
            ;;
        -H | --no-htmldoc)
            signorehtmldoc=1
            shift
            ;;
        -S | --force-https | --https-redirect)
            # -S now sets ONLY the redirect, which is what its help text has
            # always described: --no-force-https is documented as "serve both
            # HTTP and HTTPS without redirecting". It used to set httpproto,
            # which also silently decided whether iPXE got rebuilt and whether
            # Secure Boot binaries were staged at all. Those are separate keys
            # now -- see --rebuild-ipxe-with-my-ca and --public-web-cert.
            sWEB_https_redirect="yes"
            shift
            ;;
        --no-force-https | --no-https-redirect)
            # GH-978: the counterpart to -S, and the only way back out of it.
            # httpsRedirect is a managed key, so once -S writes yes into
            # .fogsettings a default can never fire again -- .fogsettings is
            # sourced first. Re-running without -S therefore kept forcing the
            # redirect, and hand-editing .fogsettings was the only escape.
            # Setting the same shadow -S uses keeps the two symmetric.
            sWEB_https_redirect="no"
            shift
            ;;
        --public-web-cert)
            # The web certificate chains to a PUBLIC root. Persisted, never
            # measured: a value re-derived each run from a trust store other
            # software also writes to is not something to hang a 25-minute
            # build on. It also cannot be measured reliably -- FOG adds its own
            # CA to the host store by default, so a plain `openssl verify`
            # answers "trusted" for FOG's own leaf, which is exactly the case
            # that needs the rebuild.
            sPKI_web_cert_publicly_trusted="yes"
            shift
            ;;
        --no-public-web-cert)
            sPKI_web_cert_publicly_trusted="no"
            shift
            ;;
        --rebuild-ipxe-with-my-ca)
            # Deliberately long: it states WHY the rebuild happens. Not
            # "...WithMyFogCA", because it must adapt to a configured external
            # CA too -- the build embeds whichever CA signs the web leaf.
            sBOOT_rebuild_ipxe_with_my_ca="yes"
            shift
            ;;
        --no-rebuild-ipxe-with-my-ca)
            sBOOT_rebuild_ipxe_with_my_ca="no"
            shift
            ;;
        --install-mode)
            case $2 in
                standard|http-only|public-cert|embed-ca) sinstallMode="$2" ;;
                *) echo "$1 must be standard, http-only, public-cert or embed-ca"; usage; exit 3 ;;
            esac
            shift 2
            ;;
        -K | --recreate-keys)
            srecreateKeys="yes"
            shift
            ;;
        -C | --recreate-[Cc][Aa])
            srecreateCA="yes"
            shift
            ;;
        --external-ca)
            sexternalca="yes"
            shift
            ;;
        --ca-cert)
            if [[ -n "${2}" ]] && [[ "${2}" != -* ]]; then
                sImportWebCACert="${2}"
            else
                echo "Error: Missing argument for $1"
                usage
                exit 9
            fi
            shift 2
            ;;
        --ca-key)
            if [[ -n "${2}" ]] && [[ "${2}" != -* ]]; then
                sImportWebCAKey="${2}"
            else
                echo "Error: Missing argument for $1"
                usage
                exit 9
            fi
            shift 2
            ;;
        --ca-root)
            if [[ -n "${2}" ]] && [[ "${2}" != -* ]]; then
                sImportWebCARoot="${2}"
            else
                echo "Error: Missing argument for $1"
                usage
                exit 9
            fi
            shift 2
            ;;
        -y | -Y | --autoaccept)
            autoaccept="yes"
            dbupdate="yes"
            shift
            ;;
        -f | --file)
            if [[ -f $2 ]]; then
                fogpriorconfig=$2
            else
                echo "$1 requires file after"
                usage
                exit 3
            fi
            shift 2
            ;;
        -D | --docroot)
            if [[ -n "${2}" ]] && [[ "${2}" != -* ]]; then
                sWEB_docroot="${2}"
                sWEB_docroot="${sWEB_docroot#'/'}"
                sWEB_docroot="${sWEB_docroot%'/'}"
                sWEB_docroot="/${sWEB_docroot}/"
            else
                echo "Error: Missing argument for $1"
                usage
                exit 9
			fi
            shift 2
            ;;
        -W | --webroot)
            if [[ $2 != */* ]]; then
                echo -e "$1 needs a url path for access either / or /fog.\n\t\tFor example if you access fog using http://127.0.0.1/ without\n\t\tany trail, set the path to /"
                usage
                exit 2
            fi
            sWEB_root="${2}"
            sWEB_root="${sWEB_root#'/'}"
            sWEB_root="${sWEB_root%'/'}"
            # Store the FINAL "/x/" form here rather than the bare "x". Two
            # separate bugs came out of not doing so, both because the
            # normalisation further down only runs on the upgrade path (it is
            # gated on grepping an existing .fogsettings):
            #
            #   -W /      stripped to "", and the application tested `-n
            #             ${sWEB_root}`, so the one case the help text exists to
            #             document was discarded and fell back to /fog/.
            #   -W /fog   on a FRESH install left webroot as "fog" with no
            #             slashes, producing URLs like http://1.2.3.4fogmanagement.
            #
            # swebrootset records that the flag was given, separately from its
            # value, so an empty value still counts.
            sWEB_root_set=1
            [[ -z ${sWEB_root} ]] && sWEB_root="/" || sWEB_root="/${sWEB_root}/"
            shift 2
            ;;
        -N | --mysqldbname)
            # -N has been declared in $shortopts, and documented in the usage
            # synopsis as [-N <databasename>], since 0c9064e52 -- but never had
            # a handler, so it hung the option loop. The application half
            # (smysqldbname -> mysqldbname) was already in place below.
            if [[ -n "${2}" ]] && [[ "${2}" != -* ]]; then
                sDB_name="${2}"
            else
                echo "Error: Missing argument for $1"
                usage
                exit 9
            fi
            shift 2
            ;;
        -B | --backuppath)
            if [[ ! -d $2 ]]; then
                echo "Path must be an existing directory"
                usage
                exit 4
            fi
            sDB_backup_path=$2
            shift 2
            ;;
        -s | --startrange)
            if [[ $(validip $2) != 0 ]]; then
                echo "Invalid ip passed"
                usage
                exit 5
            fi
            sDHCP_range_start=$2
            sDHCP_enabled="yes"
            shift 2
            ;;
        -e | --endrange)
            if [[ $(validip $2) != 0 ]]; then
                echo "Invalid ip passed"
                usage
                exit 6
            fi
            sDHCP_range_end=$2
            sDHCP_enabled="yes"
            shift 2
            ;;
        -E | --no-exportbuild)
            sSTORAGE_rebuild_nfs_exports="no"
            shift
            ;;
        -X | --exitFail)
            sexitFail=1
            shift
            ;;
        -T | --no-tftpbuild)
            sBOOT_external_tftp_server="yes"
            shift
            ;;
        -F | --no-vhost)
            novhost="y"
            shift
            ;;
        -l | --list-packages)
            listPackages=1
            shift
            ;;
        --secure-boot-key)
            if [[ -f $2 ]]; then
                sPKI_sb_codesign_key="$2"
            else
                echo "$1 requires a readable private key file after"
                usage
                exit 3
            fi
            shift 2
            ;;
        --secure-boot-cert)
            if [[ -f $2 ]]; then
                sPKI_sb_codesign_cert="$2"
            else
                echo "$1 requires a readable certificate file after"
                usage
                exit 3
            fi
            shift 2
            ;;
        --no-secure-boot)
            sPKI_sb_enabled="no"
            shift
            ;;
        --netboot-proto)
            case $2 in
                http|https) sBOOT_url_proto="$2" ;;
                *) echo "$1 must be http or https"; usage; exit 3 ;;
            esac
            shift 2
            ;;
        --boot-delay)
            # Bounded rather than free: this is a sleep every client waits
            # through on every boot, and a fat-fingered 600 is indistinguishable
            # from a hung PXE stack to the person standing at the machine.
            if [[ ! $2 =~ ^[0-9]+$ ]] || [[ $2 -gt 120 ]]; then
                echo "$1 requires a whole number of seconds, 0 to 120"
                usage
                exit 3
            fi
            sBOOT_dhcp_delay_seconds="$2"
            shift 2
            ;;
        --internal-domain)
            if [[ $(validhostname "${2}") -ne 0 ]]; then
                echo "$1 requires a valid domain name after"
                usage
                exit 3
            fi
            sPKI_allowed_domain_names="${sPKI_allowed_domain_names:+${sPKI_allowed_domain_names} }${2}"
            shift 2
            ;;
        --internal-subnet)
            # address, or address/prefixlen, or address/netmask. Validated here
            # rather than at use: an unchecked value reaches an OpenSSL
            # extension config, and a malformed one there fails to build the
            # certificate with an error that names neither the flag nor the
            # value.
            if [[ $(validip "${2%%/*}") -ne 0 ]]; then
                echo "$1 requires a subnet like 10.20.30.0/24 after"
                usage
                exit 3
            fi
            sPKI_internal_subnets="${sPKI_internal_subnets:+${sPKI_internal_subnets} }${2}"
            shift 2
            ;;
        --web-ca-cert | --web-ca-key | --web-ca-root | --secureboot-ca-cert \
            | --client-cert | --client-key)
            if [[ ! -f $2 ]]; then
                echo "$1 requires a readable file after"
                usage
                exit 3
            fi
            case $1 in
                --web-ca-cert)    sImportWebCACert="$2" ;;
                --web-ca-key)     sImportWebCAKey="$2" ;;
                --web-ca-root)    sImportWebCARoot="$2" ;;
                # The client-communication keypair, which every registered
                # fog-client pins. Named so it can be supplied rather than only
                # dropped at the canonical paths by hand -- the client zone was
                # the last one an admin could not point at their own files.
                #
                # These name the leaf itself, not a CA: there is no intermediate
                # in this zone, because fog-client pins the root and the leaf is
                # signed by it directly.
                --client-cert)    sPKI_client_encrypt_cert="$2" ;;
                --client-key)     sPKI_client_encrypt_key="$2" ;;
                # The Secure Boot zone's anchor: what gets ENROLLED in
                # firmware. Pairs with --secure-boot-key/--secure-boot-cert,
                # which name the leaf that actually signs. Supplying only the
                # leaf pair (the historic form) still works and enrols that
                # certificate, exactly as before.
                --secureboot-ca-cert) sPKI_sb_ca_cert="$2" ;;
            esac
            shift 2
            ;;
        --kernel-backup-count)
            if [[ -n "${2}" && "${2}" =~ ^[0-9]+$ && "${2}" -ge 1 ]]; then
                sBOOT_kernel_backups_kept="${2}"
            else
                echo "$1 requires a positive integer after"
                usage
                exit 3
            fi
            shift 2
            ;;
        --restore-kernel-backup)
            srestoreKernelBackup=1
            shift
            ;;
        --)
            shift
            break
            ;;
        *)
            # Nothing below shifts $1, so an option that getopt accepts but no
            # branch above handles used to spin here forever at 100% CPU rather
            # than failing. That was reachable for every letter left in
            # $shortopts after its handler was removed. Fail loudly instead.
            echo "Error: unhandled option '$1'. This is an installer bug --"
            echo "please report it at https://github.com/FOGProject/fogproject/issues"
            exit 10
            ;;
    esac
done


if [[ -f /etc/os-release ]]; then
    [[ -z $linuxReleaseName ]] && linuxReleaseName=$(sed -n 's/^NAME=\(.*\)/\1/p' /etc/os-release | tr -d '"')
    [[ -z $OSVersion ]] && OSVersion=$(sed -n 's/^VERSION_ID=\([^.]*\).*/\1/p' /etc/os-release | tr -d '"')
elif [[ -f /etc/redhat-release ]]; then
    [[ -z $linuxReleaseName ]] && linuxReleaseName=$(cat /etc/redhat-release | awk '{print $1}')
    [[ -z $OSVersion ]] && OSVersion=$(cat /etc/redhat-release | sed s/.*release\ // | sed s/\ .*// | awk -F. '{print $1}')
elif [[ -f /etc/debian_version ]]; then
    [[ -z $linuxReleaseName ]] && linuxReleaseName='Debian'
    [[ -z $OSVersion ]] && OSVersion=$(cat /etc/debian_version)
fi

linuxReleaseName_lower=$(echo "$linuxReleaseName" | tr [:upper:] [:lower:])
listPackages

echo "Installing LSB_Release as needed"
dots "Attempting to get release information"
command -v lsb_release >$error_log 2>&1
exitcode=$?
if [[ ! $exitcode -eq 0 ]]; then
    case $linuxReleaseName_lower in
        *bian*|*ubuntu*|*mint*)
            apt-get -yq install lsb-release >>$error_log 2>&1
            ;;
        *centos*|*red*hat*|*fedora*|*alma*|*rocky*)
            command -v dnf >>$error_log 2>&1
            exitcode=$?
            case $exitcode in
                0)
                    dnf -y install redhat-lsb-core >>$error_log 2>&1
                    exitcode=$?
                    ;;
                *)
                    yum -y install redhat-lsb-core >>$error_log 2>&1
                    exitcode=$?
                    ;;
            esac
            if [[ ! $exitcode -eq 0 ]]; then
                command -v dnf >>$error_log 2>&1
                exitcode=$?
                case $exitcode in
                    0)
                        dnf -y install lsb-release >>$error_log 2>&1
                        exitcode=$?
                        ;;
                    *)
                        yum -y install lsb-release >>$error_log 2>&1
                        exitcode=$?
                        ;;
                esac
            fi
            ;;

        *[Aa][Ll][Pp][Ii][Nn][Ee]*)
            ;;
        *arch*|*manjaro*)
            # -Syu, not -Sy: Arch does not support partial upgrades, and this
            # runs before lib/arch/config.sh has been sourced.
            pacman -Syu --noconfirm lsb-release >>$error_log 2>&1
            ;;
    esac
fi
[[ -z $OSVersion ]] && OSVersion=$(lsb_release -rs| awk -F'.' '{print $1}')
[[ -z $OSMinorVersion ]] && OSMinorVersion=$(lsb_release -rs| awk -F'.' '{print $2}')
echo "Done"
# GH-850: establish the FOG base directory BEFORE lib/common/config.sh, because
# servicedst/servicelogs/snapindir and the .fogsettings location all derive from
# it. Precedence, highest first:
#
#   1. --fogprogramdir           explicit, this run
#   2. an exported $fogprogramdir  scripted installs
#   3. /etc/fog/fog.conf         what the last install recorded
#   4. /opt/fog                  the default, applied by config.sh
#
# .fogsettings is deliberately NOT in that list: it lives at
# $fogprogramdir/.fogsettings, so it cannot be what tells us where to look.
# That is why the pointer file exists.
[[ -z $fogprogramdir && -r /etc/fog/fog.conf ]] && . /etc/fog/fog.conf
[[ -n $sfogprogramdir ]] && fogprogramdir="$sfogprogramdir"
fogprogramdir="${fogprogramdir%/}"
. ../lib/common/config.sh
# Captured after config.sh so the /opt/fog default is included, and re-asserted
# once .fogsettings has been sourced below. A stale fogprogramdir line in that
# file must not silently relocate the install half-way through a run, after
# config.sh has already derived servicedst/servicelogs from it.
resolvedfogprogramdir="$fogprogramdir"
# Same reasoning as resolvedfogprogramdir immediately above: fog_git_path is
# where THIS run of installfog.sh actually lives, computed by config.sh from
# $workingdir. Captured here so it can be re-asserted after .fogsettings is
# sourced below, the same way resolvedfogprogramdir is -- otherwise a stale
# path recorded from a moved/re-cloned checkout would silently win.
resolvedfoggitpath="${FOG_git_path}"
[[ -z ${DHCP_dns_server_ip} ]] && DHCP_dns_server_ip=""
[[ -z ${SVC_user} ]] && SVC_user=""
[[ -z ${SVC_password} ]] && SVC_password=""
[[ -z ${FOG_os_id} ]] && FOG_os_id=""
[[ -z ${FOG_os_name} ]] && FOG_os_name=""
[[ -z ${DHCP_enabled} ]] && DHCP_enabled=""
[[ -z ${FOG_install_type} ]] && FOG_install_type=""
[[ -z ${NET_interface} ]] && NET_interface=""
[[ -z ${NET_fog_server_ip} ]] && NET_fog_server_ip=""
[[ -z ${NET_hostname} ]] && NET_hostname=""
[[ -z ${DHCP_router} ]] && DHCP_router=""
[[ -z ${STORAGE_rebuild_nfs_exports} ]] && STORAGE_rebuild_nfs_exports="yes"
[[ -z ${FOG_install_lang} ]] && FOG_install_lang="no"
[[ -z $bluseralreadyexists ]] && bluseralreadyexists=0
[[ -z $guessdefaults ]] && guessdefaults=1
[[ -z $doupdate ]] && doupdate=1
[[ -z $ignorehtmldoc ]] && ignorehtmldoc=0
# NOT the new default. httpproto is forced to https for everyone further down,
# after .fogsettings has been sourced -- the migration there has to be able to
# tell a PERSISTED https (the only evidence an admin ever asked for -S) from a
# defaulted one, and it cannot if the default has already written https here.
[[ -z ${WEB_url_proto} ]] && WEB_url_proto="http"
[[ -z $externalca ]] && externalca="no"
[[ -z ${DB_name} ]] && DB_name="fog"
[[ -z ${BOOT_tftp_options} ]] && BOOT_tftp_options=""
[[ -z $fogpriorconfig ]] && fogpriorconfig="$fogprogramdir/.fogsettings"
#clearScreen
if [[ -z $* || $* != +(-h|-?|--help|--uninstall) ]]; then
    echo > "$workingdir/error_logs/foginstall.log"
    exec &> >(tee -a "$workingdir/error_logs/foginstall.log")
fi
displayBanner
echo -e "   Version: $version Installer/Updater\n"
checkSELinux
# Whether this machine has EVER had FOG installed, captured before anything
# writes .fogsettings. ADR 0023 Decision 7 applies a bounded retention default
# to new installs and never to upgrades, and this is the only signal that can
# tell them apart -- $doupdate says whether an upgrade was ATTEMPTED, which is
# a different question and is 0 for --no-upgrade on a server that has been
# running for years.
priorInstall=0
[[ -f $fogpriorconfig ]] && priorInstall=1
case $doupdate in
    1)
        if [[ -f $fogpriorconfig ]]; then
            echo -e "\n * Found FOG Settings from previous install at: $fogprogramdir/.fogsettings\n"
            echo -n " * Performing upgrade using these settings"
            . "$fogpriorconfig"
            # GH-850: .fogsettings records fogprogramdir but does not control
            # it (see writeFogSettings). Re-assert before doOSSpecificIncludes,
            # which derives snapindir from it.
            fogprogramdir="$resolvedfogprogramdir"
            FOG_git_path="$resolvedfoggitpath"
            doOSSpecificIncludes
            # This was `STORAGE_rebuild_nfs_exports=${STORAGE_rebuild_nfs_exports}` -- a self-assignment that did
            # nothing, so -E was silently discarded on upgrades: the handler
            # wrote blexports directly and the .fogsettings sourced just above
            # overwrote it (blexports is a managed key). -E/-s/-e now use the
            # s-prefixed shadows every other flag uses, which is what
            # 0d49b78e1 introduced the convention for.
            [[ -n ${sSTORAGE_rebuild_nfs_exports} ]] && STORAGE_rebuild_nfs_exports=${sSTORAGE_rebuild_nfs_exports}
            [[ -n ${sBOOT_external_tftp_server} ]] && BOOT_external_tftp_server=${sBOOT_external_tftp_server}
            [[ -n ${sDB_backup_path} ]] && DB_backup_path=${sDB_backup_path}
            [[ -n ${sWEB_root_set} ]] && WEB_root=${sWEB_root}
            [[ -n ${sWEB_docroot} ]] && WEB_docroot=${sWEB_docroot}
            [[ -n $signorehtmldoc ]] && ignorehtmldoc=$signorehtmldoc
            [[ -n ${sFOG_copy_back_old} ]] && FOG_copy_back_old=${sFOG_copy_back_old}
        fi
        ;;
    *)
        echo -e "\n * FOG Installer will NOT attempt to upgrade from\n    previous version of FOG."
        ;;
esac
# --- GH-1120 key rename: carry every pre-1.6 value onto its new key ----------
#
# Runs after .fogsettings is sourced and BEFORE the flag shadows below, so the
# order stays: explicit flag > persisted value > migrated value. It must also
# run before the WEB_https_redirect migration further down, which reads
# ${WEB_url_proto} -- on an upgrade that value only exists once this block has
# copied $httpproto onto it.
#
# GH-1120 renamed all 79 managed keys to CATEGORY_lower_snake_case. .fogsettings
# is SOURCED, so the old names are still live shell variables at this point,
# holding everything the previous install recorded. This is the only thing that
# moves them: deprecatedKeys in writeUpdateFile() strips the old lines and
# carries NO value, so removing this block does not degrade the migration -- it
# wipes every setting on the next upgrade, silently, and under -y.
#
# Each pair is guarded on the NEW key, so the block fires exactly once: after
# this run the new name is persisted and the old line is gone, and a flag that
# already set the new key on this run correctly wins over the persisted value.
#
# Modelled on the httpproto/httpsRedirect seeding immediately below, which is
# the same shape for a single key.
# FOG
[[ -z ${FOG_install_type} ]] && FOG_install_type="$installtype"
[[ -z ${FOG_os_id} ]] && FOG_os_id="$osid"
[[ -z ${FOG_os_name} ]] && FOG_os_name="$osname"
[[ -z ${FOG_packages} ]] && FOG_packages="$packages"
[[ -z ${FOG_install_lang} ]] && FOG_install_lang="$installlang"
[[ -z ${FOG_send_reports} ]] && FOG_send_reports="$sendreports"
[[ -z ${FOG_installed} ]] && FOG_installed="$fogupdateloaded"
[[ -z ${FOG_copy_back_old} ]] && FOG_copy_back_old="$copybackold"
[[ -z ${FOG_update_channel} ]] && FOG_update_channel="$fog_update_channel"
[[ -z ${FOG_git_path} ]] && FOG_git_path="$fog_git_path"
[[ -z ${FOG_program_dir} ]] && FOG_program_dir="$fogprogramdir"
# NET
[[ -z ${NET_interface} ]] && NET_interface="$interface"
[[ -z ${NET_fog_server_ip} ]] && NET_fog_server_ip="$ipaddress"
[[ -z ${NET_subnet_mask} ]] && NET_subnet_mask="$submask"
[[ -z ${NET_hostname} ]] && NET_hostname="$hostname"
# DHCP
[[ -z ${DHCP_engine} ]] && DHCP_engine="$dhcpengine"
[[ -z ${DHCP_service_name} ]] && DHCP_service_name="$dhcpd"
[[ -z ${DHCP_dns_server_ip} ]] && DHCP_dns_server_ip="$dnsaddress"
[[ -z ${DHCP_range_start} ]] && DHCP_range_start="$startrange"
[[ -z ${DHCP_range_end} ]] && DHCP_range_end="$endrange"
# DB
[[ -z ${DB_name} ]] && DB_name="$mysqldbname"
[[ -z ${DB_host} ]] && DB_host="$snmysqlhost"
[[ -z ${DB_user} ]] && DB_user="$snmysqluser"
[[ -z ${DB_password} ]] && DB_password="$snmysqlpass"
[[ -z ${DB_external} ]] && DB_external="$snmysqlexternal"
[[ -z ${DB_backup_path} ]] && DB_backup_path="$backupPath"
# WEB
[[ -z ${WEB_server_engine} ]] && WEB_server_engine="$webserver"
[[ -z ${WEB_docroot} ]] && WEB_docroot="$docroot"
[[ -z ${WEB_root} ]] && WEB_root="$webroot"
[[ -z ${WEB_php_version} ]] && WEB_php_version="$php_ver"
[[ -z ${WEB_url_proto} ]] && WEB_url_proto="$httpproto"
[[ -z ${WEB_https_redirect} ]] && WEB_https_redirect="$httpsRedirect"
# BOOT
[[ -z ${BOOT_url_proto} ]] && BOOT_url_proto="$netbootproto"
[[ -z ${BOOT_url_proto_forced} ]] && BOOT_url_proto_forced="$netbootProtoForced"
[[ -z ${BOOT_rebuild_ipxe_with_my_ca} ]] && BOOT_rebuild_ipxe_with_my_ca="$rebuildIpxeWithMyCA"
[[ -z ${BOOT_dhcp_delay_seconds} ]] && BOOT_dhcp_delay_seconds="$bootdelay"
[[ -z ${BOOT_external_tftp_server} ]] && BOOT_external_tftp_server="$noTftpBuild"
[[ -z ${BOOT_tftp_options} ]] && BOOT_tftp_options="$tftpAdvOpts"
[[ -z ${BOOT_kernel_backups_kept} ]] && BOOT_kernel_backups_kept="$kernelBackupGenerations"
# STORAGE
[[ -z ${STORAGE_image_share_path} ]] && STORAGE_image_share_path="$storageLocation"
[[ -z ${STORAGE_rebuild_nfs_exports} ]] && STORAGE_rebuild_nfs_exports="$blexports"
# SVC
[[ -z ${SVC_user} ]] && SVC_user="$username"
[[ -z ${SVC_password} ]] && SVC_password="$password"
[[ -z ${SVC_firewall_control} ]] && SVC_firewall_control="$fwconfigure"
# PKI
[[ -z ${PKI_root_ca_cert} ]] && PKI_root_ca_cert="$rootCAPem"
[[ -z ${PKI_root_ca_key} ]] && PKI_root_ca_key="$rootCAKey"
[[ -z ${PKI_web_trust_chain} ]] && PKI_web_trust_chain="$sslcachain"
[[ -z ${PKI_client_cert_dir} ]] && PKI_client_cert_dir="$sslpath"
[[ -z ${PKI_sb_ca_cert} ]] && PKI_sb_ca_cert="$secureBootMokCert"
[[ -z ${PKI_sb_codesign_cert} ]] && PKI_sb_codesign_cert="$secureBootCert"
[[ -z ${PKI_sb_codesign_key} ]] && PKI_sb_codesign_key="$secureBootKey"
[[ -z ${PKI_sb_enabled} ]] && PKI_sb_enabled="$secureboot"
[[ -z ${PKI_web_cert_publicly_trusted} ]] && PKI_web_cert_publicly_trusted="$publicWebCert"
[[ -z ${PKI_allowed_domain_names} ]] && PKI_allowed_domain_names="$internalDomains"
[[ -z ${PKI_internal_subnets} ]] && PKI_internal_subnets="$internalSubnets"
[[ -z ${PKI_san_ip_addresses} ]] && PKI_san_ip_addresses="$ipaddresses"
[[ -z ${PKI_san_dns_names} ]] && PKI_san_dns_names="$extraServerNames"
# --- and the seven merges, where two old keys held one answer ----------------
#
# DHCP_enabled: dodhcp was Y/N and bldhcp was 1/0, both written from the same
# prompt. Seeded from bldhcp because every DECISION read that one; dodhcp was
# read only by the prompt loop that wrote it. Either encoding is fine to copy
# here -- _normalizeBooleanSettings below converts whatever arrives to yes/no,
# so the seed stays a copy and does not have to know which literal it is
# carrying.
[[ -z ${DHCP_enabled} ]] && DHCP_enabled="$bldhcp"
#
# DHCP_router: routeraddress doubled as a config-file comment -- declining a
# router stored the literal "#   No router address added" -- which is why
# plainrouter existed at all, to hold the clean value for display. One key holds
# the clean value or nothing; the config writers emit the comment. Prefer
# plainrouter, and fall back to routeraddress only when it is a real address.
if [[ -z ${DHCP_router} ]]; then
    if [[ -n $plainrouter ]]; then
        DHCP_router="$plainrouter"
    elif [[ -n $routeraddress && $routeraddress != \#* ]]; then
        DHCP_router="$routeraddress"
    fi
fi
# DHCP_dns_server_ip had the identical wart with no clean twin, so it is
# cleaned here rather than merged.
[[ ${DHCP_dns_server_ip} == \#* ]] && DHCP_dns_server_ip=""
#
# The Web CA pair is seeded from FOG's OWN canonical paths, not from the import
# paths. validateExternalCA() already copies an imported CA into the canonical
# location, so $sslcapem/$sslcakey are the right values on an external-CA
# install too -- and --ca-cert/--ca-key/--web-ca-cert/--web-ca-key keep working
# as run-scoped INPUTS. That is the whole point of the merge: six persisted keys
# holding three values is what silently discarded anything typed at the prompt
# whenever the flags were also given.
[[ -z ${PKI_web_ca_cert} ]] && PKI_web_ca_cert="$sslcapem"
[[ -z ${PKI_web_ca_key} ]]  && PKI_web_ca_key="$sslcakey"
#
# The imported root IS value-carrying, and stays separate from PKI_root_ca_cert:
# validateExternalCA() feeds it to the chain file only, and conflating it with
# the root fog-client pins is exactly what the three-zone split exists to
# prevent. Flag spelling wins over prompt spelling, as it always did.
[[ -z ${PKI_web_external_root_cert} ]] && PKI_web_external_root_cert="${webExtCARoot:-$extcaroot}"
#
# The vhost pair absorbs webCertFile/webKeyFile, which recorded where an
# externally-managed leaf actually lived. Those win when set: on such a server
# createSSLCA() had already reassigned $sslpubcert/$sslprivkey to them, but the
# recorded pair is the one the admin's tooling renews.
[[ -z ${PKI_web_vhost_cert} ]] && PKI_web_vhost_cert="${webCertFile:-$sslpubcert}"
[[ -z ${PKI_web_vhost_key} ]]  && PKI_web_vhost_key="${webKeyFile:-$sslprivkey}"
# --- WEB_url_proto / WEB_https_redirect migration ---------------------------
#
# Runs after .fogsettings is sourced and BEFORE the flags below, so the order
# stays: explicit flag > persisted value > migrated value.
#
# ${WEB_url_proto} used to mean three unrelated things at once -- "FOG uses HTTPS for
# its own URLs", "redirect HTTP to HTTPS", and "rebuild iPXE with the CA baked
# in". They are separate keys now. Splitting them needs one guess made once,
# about existing servers:
#
#   An existing WEB_url_proto=https is the ONLY evidence its admin ever asked for
#   -S, so seed WEB_https_redirect from it. Everybody else gets no redirect,
#   is the point -- trust in FOG's CA reaches a client when fog-client installs
#   it there, so on a fresh server a forced redirect breaks exactly the
#   machines that cannot fix themselves.
#
# Guarded on WEB_https_redirect being unset, so it fires once. After that it is
# persisted and this branch can never re-run -- which matters, because an admin
# who turns the redirect off must not have it turned back on by the next
# upgrade re-reading WEB_url_proto.
if [[ -z ${WEB_https_redirect} ]]; then
    [[ ${WEB_url_proto} == https ]] && WEB_https_redirect="yes" || WEB_https_redirect="no"
fi
# Safe for everyone: 443 already listens on every install (both web servers
# emit their :443 vhost in both arms), and no redirect follows from this.
WEB_url_proto="https"
[[ -z ${PKI_web_cert_publicly_trusted} ]] && PKI_web_cert_publicly_trusted="no"
[[ -z ${BOOT_rebuild_ipxe_with_my_ca} ]] && BOOT_rebuild_ipxe_with_my_ca="no"

# --- --install-mode ----------------------------------------------------------
#
# A preset over the model, never a replacement for it: it writes the same four
# keys an admin could set individually. Applied BEFORE the discrete flags, so
# `--install-mode public-cert --no-rebuild-ipxe-with-my-ca` means what it reads
# like -- each discrete key overrides its own field and nothing else.
_applyInstallMode

# evaluation of command line options
[[ -n ${sWEB_https_redirect} ]] && WEB_https_redirect=${sWEB_https_redirect}
[[ -n ${sPKI_web_cert_publicly_trusted} ]] && PKI_web_cert_publicly_trusted=${sPKI_web_cert_publicly_trusted}
[[ -n ${sBOOT_rebuild_ipxe_with_my_ca} ]] && BOOT_rebuild_ipxe_with_my_ca=${sBOOT_rebuild_ipxe_with_my_ca}
[[ -n ${sNET_hostname} ]] && NET_hostname=${sNET_hostname}
[[ ${#sextraServerNames[@]} -gt 0 ]] && PKI_san_dns_names="${sPKI_san_dns_names[*]}"
[[ -n ${sDHCP_range_start} ]] && DHCP_range_start=${sDHCP_range_start}
[[ -n ${sDHCP_range_end} ]] && DHCP_range_end=${sDHCP_range_end}
# -s/-e imply "set DHCP up". These were written directly by the handlers, so on
# an upgrade the .fogsettings sourced above overwrote them (both are managed
# keys) and the ranges were accepted while DHCP configuration stayed off.
[[ -n ${sDHCP_enabled} ]] && DHCP_enabled=${sDHCP_enabled}
[[ -n ${sPKI_client_cert_dir} ]] && PKI_client_cert_dir=${sPKI_client_cert_dir}
[[ -n ${sPKI_client_encrypt_cert} ]] && PKI_client_encrypt_cert=${sPKI_client_encrypt_cert}
[[ -n ${sPKI_client_encrypt_key} ]] && PKI_client_encrypt_key=${sPKI_client_encrypt_key}
[[ -n $srecreateCA ]] && recreateCA=$srecreateCA
[[ -n $srecreateKeys ]] && recreateKeys=$srecreateKeys
[[ -n $sexternalca ]] && externalca=$sexternalca
[[ -n ${sWEB_docroot} ]] && WEB_docroot=${sWEB_docroot}
[[ -n ${sWEB_root_set} ]] && WEB_root=${sWEB_root}
[[ -n ${sDB_backup_path} ]] && DB_backup_path=${sDB_backup_path}
[[ -n $sexitFail ]] && exitFail=$sexitFail
[[ -n ${sBOOT_external_tftp_server} ]] && BOOT_external_tftp_server=${sBOOT_external_tftp_server}
[[ -n ${sPKI_sb_codesign_key} ]] && PKI_sb_codesign_key=${sPKI_sb_codesign_key}
[[ -n ${sPKI_sb_codesign_cert} ]] && PKI_sb_codesign_cert=${sPKI_sb_codesign_cert}
[[ -n ${sPKI_sb_enabled} ]] && PKI_sb_enabled=${sPKI_sb_enabled}
[[ -n ${sBOOT_kernel_backups_kept} ]] && BOOT_kernel_backups_kept=${sBOOT_kernel_backups_kept}
# Applied here, after .fogsettings is sourced, so an explicit flag beats a
# persisted value. A persisted value does NOT beat the computed default any
# more: netbootproto is re-derived every run unless netbootProtoForced records
# that somebody actually passed --netboot-proto. It used to, and that is how a
# value one run derived went on overriding the keys it was derived from.
[[ -n ${sBOOT_url_proto} ]] && BOOT_url_proto=${sBOOT_url_proto}
[[ -n ${sBOOT_dhcp_delay_seconds} ]] && BOOT_dhcp_delay_seconds=${sBOOT_dhcp_delay_seconds}
# The external-CA IMPORT paths. GH-1120 collapsed six persisted keys
# (extcacert/extcakey/extcaroot from the prompt, webExtCACert/webExtCAKey/
# webExtCARoot from --web-ca-*) that only ever held three values -- and that
# duplication is what silently discarded anything typed at the prompt whenever
# the flags were also given. Both flag spellings now write one run-scoped input,
# and the canonical PKI_web_ca_* slots are set by validateExternalCA() once the
# import has actually been validated.
[[ -n $sImportWebCACert ]] && importWebCACert=$sImportWebCACert
[[ -n $sImportWebCAKey ]] && importWebCAKey=$sImportWebCAKey
[[ -n $sImportWebCARoot ]] && importWebCARoot=$sImportWebCARoot
[[ -n ${sPKI_sb_ca_cert} ]] && PKI_sb_ca_cert=${sPKI_sb_ca_cert}
# Repeatable flags REPLACE the persisted list rather than appending to it, the
# same way --extra-server-name does: an admin re-running with a narrower set
# means that set, and appending would make a value impossible to remove
# without hand-editing .fogsettings.
[[ -n ${sPKI_allowed_domain_names} ]] && PKI_allowed_domain_names=${sPKI_allowed_domain_names}
[[ -n ${sPKI_internal_subnets} ]] && PKI_internal_subnets=${sPKI_internal_subnets}
# --- one boolean encoding ----------------------------------------------------
#
# Deliberately AFTER the flag shadows above, not before them: every source of a
# boolean feeds in by this point -- the value .fogsettings persisted, the value
# the rename seed block copied off a pre-1.6 key, and the value a flag set this
# run -- and the flag layer was itself the worst offender for mixed encodings.
# Normalizing earlier would leave whatever the flags assigned unconverted.
#
# input.sh/newinput.sh are sourced later still and write yes/no directly, since
# they run after this point.
_normalizeBooleanSettings
# Supplying any web-zone CA file implies --external-ca, the same way supplying
# --ca-cert always has. Saves an admin from the "I gave you the files and
# nothing happened" failure, which produces a working install with the wrong
# CA and no error to explain it.
# Derived, never persisted (GH-1120 retired the key). It has to test the IMPORT
# inputs: PKI_web_ca_cert names FOG own Web CA on every ordinary install, so
# testing that would declare every install an external-CA install.
[[ -n $importWebCACert || -n $importWebCAKey || -n $importWebCARoot ]] && externalca="yes"
# Deliberately NOT persisted to .fogsettings: this is a one-shot instruction
# for a single run (revertUpdate passes it), not a preference. Persisting it
# would make every later update silently roll the kernels back.
restoreKernelBackup=${srestoreKernelBackup:-0}

# Secure Boot signing is generated by default now (see _ensureSecureBootKeys),
# but an explicitly supplied key is still only meaningful as a pair. Refuse half
# a pair rather than silently leaving kernels unsigned on a server whose admin
# believes they are signed -- that failure only shows up at a client, as a
# Security Policy Violation with nothing on the server to explain it.
if [[ -n ${PKI_sb_codesign_key} || -n ${PKI_sb_codesign_cert} ]]; then
    if [[ -z ${PKI_sb_codesign_key} || -z ${PKI_sb_codesign_cert} ]]; then
        echo " * --secure-boot-key and --secure-boot-cert must be set together"
        exit 9
    fi
    for sbfile in "${PKI_sb_codesign_key}" "${PKI_sb_codesign_cert}"; do
        if [[ ! -r $sbfile ]]; then
            # ${PKI_sb_codesign_key}/${PKI_sb_codesign_cert} may be this run's
            # --secure-boot-key/--secure-boot-cert (staged in
            # ${sPKI_sb_codesign_key}/${sPKI_sb_codesign_cert} below), or they may only be
            # non-empty because .fogsettings recorded a previous run's pair
            # (see writeUpdateFile) -- the two are otherwise indistinguishable
            # once sourced. Only the first is a mistake worth refusing the
            # install over; the second just means an admin removed the file
            # to force a fresh key, and should fall through to
            # _ensureSecureBootKeys() regenerating one, not exit 9 before that
            # function ever runs.
            if [[ -n ${sPKI_sb_codesign_key} || -n ${sPKI_sb_codesign_cert} ]]; then
                echo " * Cannot read Secure Boot signing file: $sbfile"
                exit 9
            fi
            echo " * The Secure Boot key/certificate recorded in .fogsettings is"
            echo "   missing on disk: $sbfile"
            echo "   Treating it as unset and generating a new one."
            PKI_sb_codesign_key=""
            PKI_sb_codesign_cert=""
            PKI_sb_ca_cert=""
            break
        fi
    done
    unset sbfile
fi
# --client-cert / --client-key: only meaningful as a pair, and gated on the
# SHADOWS rather than on the resolved values.
#
# That differs from the Secure Boot pair above and has to. These two are managed
# keys -- canonical-path RECORDS that writeUpdateFile persists on every run --
# so on any upgrade they are non-empty from .fogsettings alone. Testing the
# resolved values would refuse every ordinary upgrade. The shadows are set only
# when a flag was actually passed on this run.
if [[ -n ${sPKI_client_encrypt_cert} || -n ${sPKI_client_encrypt_key} ]]; then
    if [[ -z ${sPKI_client_encrypt_cert} || -z ${sPKI_client_encrypt_key} ]]; then
        echo " * --client-cert and --client-key must be set together"
        echo "   Half a pair cannot be used: fog-client encrypts to the public"
        echo "   half and the server decrypts with the private half, so a"
        echo "   certificate without its key locks out every registered host."
        exit 9
    fi
    # A supplied pair that does not actually pair is a typo, not history, and
    # every registered client stops authenticating the moment it is installed.
    # The admin has just named both files, so say so now rather than letting
    # _createCommLeaf's mismatch warning report it after the fact.
    # The raw modulus, NOT piped through `openssl md5`. That idiom appears
    # elsewhere in this codebase and it defeats the emptiness test below: an
    # unreadable file makes the x509/rsa call print nothing, and `openssl md5`
    # of nothing is the perfectly non-empty MD5 of the empty string. So two
    # unreadable files produce identical non-empty values and "pair".
    ccmod=$(openssl x509 -noout -modulus -in "${sPKI_client_encrypt_cert}" 2>/dev/null)
    ckmod=$(openssl rsa -noout -modulus -in "${sPKI_client_encrypt_key}" 2>/dev/null)
    if [[ -z $ccmod || -z $ckmod ]]; then
        echo " * Could not read --client-cert/--client-key as a certificate and key"
        echo "   cert: ${sPKI_client_encrypt_cert}"
        echo "   key:  ${sPKI_client_encrypt_key}"
        exit 9
    fi
    if [[ $ccmod != "$ckmod" ]]; then
        echo " * --client-cert and --client-key do not pair"
        echo "   cert: ${sPKI_client_encrypt_cert}"
        echo "   key:  ${sPKI_client_encrypt_key}"
        echo "   Installing these would stop every registered fog-client"
        echo "   authenticating, and no re-pin would fix it."
        exit 9
    fi
    unset ccmod ckmod
fi
# Immediately after validation and long before configureHttpd() rebuilds the
# web tree, so a pair the admin parked somewhere that gets deleted is copied
# to safety first. Handles a path from .fogsettings as well as one from this
# run's flags, and no-ops once the recorded path is already protected.
# $fogprogramdir is settled by config.sh above, so the destination is real.
preserveSecureBootAdminFiles

[[ -f $fogpriorconfig ]] && grep -l webroot $fogpriorconfig >>$error_log 2>&1
case $? in
    0)
        if [[ -n ${WEB_root} ]]; then
            WEB_root=${WEB_root#'/'}
            WEB_root=${WEB_root%'/'}
            [[ -z ${WEB_root} ]] && WEB_root="/" || WEB_root="/${WEB_root}/"
        fi
        ;;
    *)
        [[ -z ${WEB_root} ]] && WEB_root="/fog/"
        ;;
esac
if [[ -z ${DB_backup_path} ]]; then
    DB_backup_path="/home/"
    DB_backup_path="${DB_backup_path%'/'}"
    DB_backup_path="${DB_backup_path#'/'}"
    DB_backup_path="/${DB_backup_path}/"
fi
[[ -n ${sDB_name} ]] && DB_name=${sDB_name}
# --uninstall runs here: late enough that .fogsettings and the distro config
# have been read (both are needed to know what to remove -- docroot, storage
# location, service list, config paths), but before input.sh, which would
# otherwise start prompting about an install nobody asked for.
if [[ $douninstall -eq 1 ]]; then
    # Normally both are already loaded by the upgrade path above. They are not
    # if -U/--no-upgrade was also given, so load them here rather than
    # uninstalling with half the paths unset.
    if [[ -z ${FOG_os_id} ]]; then
        [[ -f $fogprogramdir/.fogsettings ]] && . "$fogprogramdir/.fogsettings"
        [[ -n ${FOG_os_id} ]] && doOSSpecificIncludes >/dev/null
    fi
    uninstallFOG
fi
[[ ! $doupdate -eq 1 || ! ${FOG_installed} -eq 1 ]] && . ../lib/common/input.sh
# ask user input for newly added options like hostname etc.
. ../lib/common/newinput.sh
# GH-954: after BOTH paths that can set ${NET_fog_server_ip} -- fresh detection in
# input.sh, and .fogsettings sourced earlier on an upgrade. An install written
# by an older installer has the multi-line value persisted, so normalizing only
# at detection would leave every upgrade carrying the broken form forward.
normalizeIpAddress
echo
echo "   ######################################################################"
echo "   #     FOG now has everything it needs for this setup, but please     #"
echo "   #   understand that this script will overwrite any setting you may   #"
echo "   #   have setup for services like DHCP, apache, pxe, tftp, and NFS.   #"
echo "   ######################################################################"
echo "   # It is not recommended that you install this on a production system #"
echo "   #        as this script modifies many of your system settings.       #"
echo "   ######################################################################"
echo "   #             This script should be run by the root user.            #"
echo "   #      It will prepend the running with sudo if root is not set      #"
echo "   ######################################################################"
echo "   #            Please see our wiki for more information at:            #"
echo "   ######################################################################"
echo "   #             https://wiki.fogproject.org/wiki/index.php             #"
echo "   ######################################################################"
echo
echo " * Here are the settings FOG will use:"
echo " * Base Linux: ${FOG_os_name}"
echo " * Detected Linux Distribution: $linuxReleaseName"
echo " * Interface: ${NET_interface}"
echo " * Server IP Address: ${NET_fog_server_ip}"
echo " * Server Subnet Mask: ${NET_subnet_mask}"
echo " * Hostname: ${NET_hostname}"
echo
case ${FOG_install_type} in
    N)
        echo " * Installation Type: Normal Server"
        echo -n " * Internationalization: "
        case ${FOG_install_lang} in
            yes)
                echo "Yes"
                ;;
            *)
                echo "No"
                ;;
        esac
        echo " * Image Storage Location: ${STORAGE_image_share_path}"
        case ${DHCP_enabled} in
            yes)
                echo " * Using FOG DHCP: Yes"
                echo " * DHCP router Address: ${DHCP_router}"
                ;;
            *)
                echo " * Using FOG DHCP: No"
                echo " * DHCP will NOT be setup but you must setup your"
                echo " | current DHCP server to use FOG for PXE services."
                echo
                echo " * On a Linux DHCP server you must set: next-server and filename"
                echo
                echo " * On a Windows DHCP server you must set options 066 and 067"
                echo
                echo " * Option 066/next-server is the IP of the FOG Server: (e.g. ${NET_fog_server_ip})"
                echo " * Option 067/filename is the bootfile: (e.g. undionly.kkpxe or snponly.efi)"
                ;;
        esac
        ;;
    S)
        echo " * Installation Type: Storage Node"
        echo " * Node IP Address: ${NET_fog_server_ip}"
        echo " * MySQL Database Host: ${DB_host}"
        echo " * MySQL Database User: ${DB_user}"
        ;;
esac
echo -n " * Send OS Name, OS Version, and FOG Version: "
case ${FOG_send_reports} in
    yes)
        echo "Yes"
        ;;
    *)
        echo "No"
        ;;
esac
# Echoed for unattended runs too, so `installfog.sh -y` leaves a record of what
# it resolved rather than only what was passed.
echo " * Web protocol: ${WEB_url_proto}"
echo " * Netboot (PXE) protocol: ${BOOT_url_proto:-http (resolved during install)}"
echo -n " * Force HTTP->HTTPS redirect: "; [[ ${WEB_https_redirect} == yes ]] && echo "Yes" || echo "No"
echo -n " * Web certificate chains to a public root: "; [[ ${PKI_web_cert_publicly_trusted} == yes ]] && echo "Yes" || echo "No"
echo -n " * Rebuild iPXE with your CA: "; [[ ${BOOT_rebuild_ipxe_with_my_ca} == yes ]] && echo "Yes (adds 10-25 min)" || echo "No"
echo
promptInstallMode
while [[ -z $blGo ]]; do
    echo
    [[ -n $autoaccept ]] && blGo="y"
    if [[ -z $autoaccept ]]; then
        echo -n " * Are you sure you wish to continue (Y/N) "
        read blGo
    fi
    echo
    case $blGo in
        [Yy]|[Yy][Ee][Ss])
            echo " * Installation Started"
            echo
            checkInternetConnection
            if [[ $ignorehtmldoc -eq 1 ]]; then
                [[ -z $newpackagelist ]] && newpackagelist=""
                for z in ${FOG_packages}; do
                    [[ $z != htmldoc ]] && newpackagelist="$newpackagelist $z"
                done
                FOG_packages="$(echo $newpackagelist)"
            fi
            if [[ ${DHCP_enabled} != yes ]]; then
                [[ -z $newpackagelist ]] && newpackagelist=""
                for z in ${FOG_packages}; do
                    [[ $z != $dhcpname ]] && newpackagelist="$newpackagelist $z"
                done
                FOG_packages="$(echo $newpackagelist)"
            fi
            case ${FOG_install_type} in
                [Ss])
                    FOG_packages=$(echo ${FOG_packages} | sed -e 's/[-a-zA-Z]*dhcp[-a-zA-Z]*//g')
                    ;;
            esac
            installPackages
            echo
            echo " * Confirming package installation"
            echo
            confirmPackageInstallation
            echo
            echo " * Configuring services"
            echo
            if [[ -z ${STORAGE_image_share_path} ]]; then
                case $autoaccept in
                    [Yy]|[Yy][Ee][Ss])
                        STORAGE_image_share_path="/images"
                        ;;
                    *)
                        echo
                        echo -n " * What is the storage location for your images directory? (/images) "
                        read storageLocation
                        [[ -z ${STORAGE_image_share_path} ]] && STORAGE_image_share_path="/images"
                        while [[ ! -d ${STORAGE_image_share_path} && ${STORAGE_image_share_path} != "/images" ]]; do
                            echo -n " * Please enter a valid directory for your storage location (/images) "
                            read storageLocation
                            [[ -z ${STORAGE_image_share_path} ]] && STORAGE_image_share_path="/images"
                        done
                        ;;
                esac
            fi
            configureUsers
            # GH-964: before either branch configures a web tier. Storage nodes
            # get this too -- configureMinHttpd still runs as httpd_t and still
            # has to reach the master over HTTP.
            installSELinuxModule
            case ${FOG_install_type} in
                [Ss])
                    checkDatabaseConnection
                    backupReports
                    configureMinHttpd
                    configureStorage
                    configureDHCP
                    configureTFTPandPXE
                    configureFTP
                    configureSnapins
                    # After configureSnapins, whose recursive chown over $snapindir
                    # is what used to leave the CA private key readable by the
                    # web user. Running it earlier has no effect at all.
                    _hardenPkiPermissions
                    configureUDPCast
                    installInitScript
                    installFOGServices
                    configureFOGService
                    configureNFS
                    # GH-964 sibling: after every service is configured, so
                    # the port set matches what was actually installed, and
                    # before writeUpdateFile so the chosen action persists.
                    configureFirewall
                    writeUpdateFile
                    linkOptFogDir
                    installUtilities
                    if [[ $bluseralreadyexists == 1 ]]; then
                        # Already registered, so the master will answer.
                        _installNodeWebCert
                        # After it, not before: on a node the anchor is pulled
                        # out of the chain that call is what fetches.
                        _installCATrustAnchor
                        echo
                        echo "\n * Upgrade complete\n"
                        echo
                    else
                        registerStorageNode
                        updateStorageNodeCredentials
                        # AFTER registration: the master only issues to a node
                        # it already knows, and this is the first point in a
                        # fresh node install where that is true.
                        _installNodeWebCert
                        # After it, not before: on a node the anchor is pulled
                        # out of the chain that call is what fetches.
                        _installCATrustAnchor
                        [[ -n ${DB_host} ]] && fogserver=${DB_host} || fogserver="fog-server"
                        echo
                        echo " * Setup complete"
                        echo
                        echo
                        echo " * You still need to setup this node in the fog management "
                        echo " | portal. You will need the username and password listed"
                        echo " | below."
                        echo
                        echo " * Management Server URL:"
                        echo "   ${WEB_url_proto}://${fogserver}${WEB_root}"
                        echo
                        echo "   You will need this, write this down!"
                        echo "   IP Address:          ${NET_fog_server_ip}"
                        echo "   Interface:           ${NET_interface}"
                        echo "   Management Username: ${SVC_user}"
                        echo "   Management Password: ${SVC_password}"
                        echo
                    fi
                    ;;
                [Nn])
                    configureMySql
                    # GH-632: persist the settings the moment the database
                    # credentials exist, not fifteen fallible steps later.
                    #
                    # configureMySql generates snmysqlpass with generatePassword
                    # when it is unset -- which is every fresh install -- and
                    # applies it to the database user immediately. .fogsettings
                    # was then written dead last, after the web tier, DHCP,
                    # TFTP, FTP, snapins, udpcast, the services and NFS. Any one
                    # of those failing left a database whose password existed
                    # only in this shell's memory, and the reporter's exact
                    # question: "not sure how to find out the sql password".
                    #
                    # Only fresh installs can lose it -- on an upgrade
                    # snmysqlpass is read back from .fogsettings and never
                    # regenerated -- but a fresh install is precisely when
                    # there is nothing else to recover it from.
                    #
                    # The final writeUpdateFile below still runs and records
                    # everything settled after this point; this one just makes
                    # sure a half-finished install is recoverable. Leaving a
                    # .fogsettings behind also means a re-run finds the prior
                    # settings and reuses the SAME password, which is what has
                    # to happen for it to connect at all.
                    writeUpdateFile
                    backupReports
                    # Before configureHttpd(), which rm -rf's $webdirdest --
                    # this is the last point anything under it can be saved.
                    # configureMySql has already run, so the FOG_IPXE_BG_FILE
                    # lookup inside has a database to ask.
                    backupPreservedCustomizations
                    # Before configureHttpd, which is what copies
                    # packages/web into the web root. The plugins are fetched
                    # into that package, so they have to be there first.
                    downloadplugins
                    configureHttpd
                    checkWebTier
                    backupDB
                    updateDB
                    configureStorage
                    configureDHCP
                    configureTFTPandPXE
                    # After configureTFTPandPXE -> downloadfiles() has re-laid
                    # the default-named kernel/init set, so restoring here puts
                    # the admin's own files back on top of fresh defaults
                    # rather than being overwritten by them.
                    restorePreservedCustomizations
                    # AFTER the restore, not with the kernels inside
                    # downloadfiles(): the restore above copies the preserved
                    # refind set back unconditionally, so anything signed
                    # earlier in the run would be replaced by an unsigned copy.
                    # Sign what actually ends up on disk.
                    _resignRefind
                    # Same ordering, stronger reason: a custom kernel is not in
                    # the web package, so configureHttpd's rm -rf removed it and
                    # the restore above is what put it back. Before this point
                    # there was nothing here to sign. _resignRefind runs first
                    # so the rEFInd set is already signed when this one walks
                    # the same directory.
                    _resignCustomKernels
                    # A different tree entirely -- $tftpdirdst, not the web root
                    # -- so unlike the two above this has no dependency on
                    # restorePreservedCustomizations, which only ever touches
                    # service/ipxe. What it does need is configureTFTPandPXE's
                    # copy loop to have filled $tftpdirdst and downloadfiles() to
                    # have set up the signing keys, both of which have run by
                    # here. Grouped with the other signing for cohesion.
                    #
                    # Deliberately absent from the storage-node branch above, for
                    # the same reason _resignRefind and _resignKernels are: a node
                    # does not hold the signing key.
                    _signLocalIpxe
                    # Separate call, not folded into the one above, because the
                    # two do not share a trigger. Signing needs a Secure Boot
                    # key; publishing does not -- booting a machine from an iPXE
                    # binary on its own ESP predates Secure Boot, and unsigned
                    # copies still work on every machine booting with it off. The
                    # web root is also rebuilt from scratch every run by
                    # configureHttpd, so publishing has to happen even on a run
                    # that signed nothing.
                    #
                    # Also downstream of _publishSecureBootKit and
                    # _publishSecureBootAuthVars, which run inside configureHttpd
                    # above via downloadfiles(). Each archive carries whatever
                    # enrolment material those two actually published -- MOK.der
                    # and the PK/KEK/db variable updates -- so one archive on a
                    # USB stick holds every enrolment route. Taken by existence,
                    # so a server that published neither simply ships neither.
                    _publishLocalBootFiles
                    configureFTP
                    configureSnapins
                    # After configureSnapins, whose recursive chown over $snapindir
                    # is what used to leave the CA private key readable by the
                    # web user. Running it earlier has no effect at all.
                    _hardenPkiPermissions
                    # Master only, and after the permissions above so the
                    # sudoers rule is the only route the web tier has to the
                    # CA keys rather than one of two.
                    _installNodeCertSigner
                    # Anchors the root the two calls above have just finished
                    # settling, so this host's own curl/wget/PHP can verify
                    # this host. Reads only ${PKI_root_ca_cert} -- no key -- so it is
                    # deliberately on the far side of _hardenPkiPermissions.
                    _installCATrustAnchor
                    configureUDPCast
                    installInitScript
                    installFOGServices
                    configureFOGService
                    configureNFS
                    # GH-964 sibling: after every service is configured, so
                    # the port set matches what was actually installed, and
                    # before writeUpdateFile so the chosen action persists.
                    configureFirewall
                    writeUpdateFile
                    linkOptFogDir
                    installUtilities
                    updateStorageNodeCredentials
                    recordGitUpdateSettings
                    applyNewInstallDefaults
                    # Beside recordGitUpdateSettings because it is the same kind
                    # of write -- a record, not a control -- and this is the
                    # earliest point where both halves it needs are in place: the
                    # schema is deployed, and configureHttpd has put the served
                    # certificate on disk for _resolveNetbootHost to read.
                    recordNetbootWebHost
                    setupFogReporting
                    # Last, so it is the part still on screen. An admin who
                    # asked for HTTPS and got HTTP netboot has to be told; the
                    # resolution used to happen silently in the middle of
                    # writing default.ipxe.
                    _reportNetbootProto
                    echo
                    echo " * Setup complete"
                    echo
                    echo "   You can now login to the FOG Management Portal using"
                    echo "   the information listed below.  The login information"
                    echo "   is only if this is the first install."
                    echo
                    echo "   This can be done by opening a web browser and going to:"
                    echo
                    echo "   ${WEB_url_proto}://${NET_fog_server_ip}${WEB_root}management"
                    echo
                    echo "   Default User Information"
                    echo "   Username: fog"
                    echo "   Password: password"
                    echo
                    ;;
            esac
            [[ -d $webdirdest/maintenance ]] && rm -rf $webdirdest/maintenance
            ;;
        [Nn]|[Nn][Oo])
            echo " * FOG installer exited by user request"
            exit 0
            ;;
        *)
            echo
            echo " * Sorry, answer not recognized"
            echo
            exit 1
            ;;
    esac
done
if [[ -n "${backupconfig}" ]]; then
    echo " * Changed configurations:"
    echo
    echo "   The FOG installer changed configuration files and created the"
    echo "   following backup files from your original files:"
    for conffile in ${backupconfig}; do
        echo "   * ${conffile} <=> ${conffile}.${timestamp}"
    done
    echo
fi
