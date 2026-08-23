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
# Grants or revokes the web server's write access to the external plugin
# directory, which is what the Plugin Management upload flow needs (ADR 0009).
#
# This is a separate, root-run act on purpose. The directory holds PHP that
# FOG autoloads, so making it web-writable means any file-write bug anywhere
# in FOG can put executable code on the server. That is a decision for whoever
# owns the machine, taken deliberately, not something the web UI can grant
# itself by writing a settings row.
#
# Enabling the FOG_PLUGIN_UI_INSTALL_ENABLED setting without running this
# leaves uploads refused; running this without the setting leaves the route
# closed. Both are required, and either one alone is reversible.

set -u

progdir="${fogprogramdir:-/opt/fog}"
[[ -r $progdir/.fogsettings ]] && . "$progdir/.fogsettings"
progdir="${fogprogramdir:-/opt/fog}"
plugindir="$progdir/plugins"

usage() {
    cat <<USAGE
Usage: $(basename "$0") {enable|disable|status}

  enable   let the web server write to $plugindir
  disable  return it to root ownership (the default)
  status   report what it is now

After 'enable', also switch on FOG Configuration -> FOG Settings ->
Plugin System -> FOG_PLUGIN_UI_INSTALL_ENABLED. Both are needed.
USAGE
}

# The user PHP runs as. Taken from the deployed web root rather than guessed
# per distro: the installer chowns that tree to $apacheuser, so whatever owns
# it is by definition the account the web server reads and writes as, and this
# stays right on a distro whose package renames the account.
webuser() {
    local root="${WEB_docroot:-/var/www/html/}${WEB_root:-/fog/}"
    root="${root//\/\///}"
    if [[ -d $root ]]; then
        stat -c '%U' "$root" 2>/dev/null && return 0
    fi
    for u in nginx apache www-data http; do
        id -u "$u" >/dev/null 2>&1 && { echo "$u"; return 0; }
    done
    return 1
}

# httpd_sys_rw_content_t only where SELinux is actually enforcing a policy.
# Without this the chown succeeds and the writes still fail, with nothing in
# the FOG logs to say why -- the denial only appears in the audit log.
relabel() {
    local ctx="$1"
    command -v semanage >/dev/null 2>&1 || return 0
    command -v getenforce >/dev/null 2>&1 || return 0
    [[ $(getenforce) == "Disabled" ]] && return 0
    semanage fcontext -m -t "$ctx" "${plugindir}(/.*)?" >/dev/null 2>&1 \
        || semanage fcontext -a -t "$ctx" "${plugindir}(/.*)?" >/dev/null 2>&1
    restorecon -RF "$plugindir" >/dev/null 2>&1
}

status() {
    if [[ ! -d $plugindir ]]; then
        echo "$plugindir does not exist. Re-run the FOG installer."
        return 1
    fi
    local owner mode
    owner=$(stat -c '%U:%G' "$plugindir")
    mode=$(stat -c '%a' "$plugindir")
    echo "$plugindir  owner=$owner mode=$mode"
    if [[ $owner == root:root ]]; then
        echo "Uploads are DISABLED. The web server cannot write here."
    else
        echo "Uploads are ENABLED for '$owner'. Anyone who can write here can"
        echo "run code on this server."
    fi
}

case "${1:-}" in
    enable)
        [[ $EUID -ne 0 ]] && { echo "Run this as root." >&2; exit 1; }
        [[ -d $plugindir ]] || { echo "$plugindir does not exist. Re-run the FOG installer." >&2; exit 1; }
        user=$(webuser) || { echo "Could not work out the web server user." >&2; exit 1; }
        chown -R "$user":"$user" "$plugindir" || exit 1
        chmod 0755 "$plugindir" || exit 1
        relabel httpd_sys_rw_content_t
        echo "$plugindir is now writable by '$user'."
        echo "Now switch on FOG_PLUGIN_UI_INSTALL_ENABLED in FOG Settings."
        ;;
    disable)
        [[ $EUID -ne 0 ]] && { echo "Run this as root." >&2; exit 1; }
        [[ -d $plugindir ]] || { echo "$plugindir does not exist." >&2; exit 1; }
        # Ownership only. Plugins already installed keep working -- the web
        # server still reads them, it just cannot add or replace one.
        chown -R root:root "$plugindir" || exit 1
        chmod 0755 "$plugindir" || exit 1
        relabel httpd_sys_content_t
        echo "$plugindir is back to root ownership. Uploads will be refused."
        ;;
    status)
        status
        ;;
    *)
        usage
        exit 1
        ;;
esac
