#!/bin/bash
#
#  FOG - Free, Open-Source Ghost is a computer imaging solution.
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
dots() {
    local pad=$(printf "%0.1s" "."{1..60})
    printf " * %s%*.*s" "$1" 0 $((60-${#1})) "$pad"
    return 0
}
# Create a symlink only when nothing already owns the destination.
#
# Bare `ln -s` logged "failed to create symbolic link ...: File exists" on every
# re-install, because the link survives from the previous run. Harmless, but it
# made a successful upgrade read as a failed one and sent at least one reporter
# chasing the installer instead of the real fault (forums topic 18204).
#
# `ln -sf` is deliberately not used: some distros own these paths themselves
# (Fedora ships /usr/lib/systemd/system/mysql.service), and clobbering a
# packaged file is worse than skipping a link we did not need to make.
linkIfAbsent() {
    local target="$1" link="$2"
    # A dangling link here is one we created ourselves on an older version, back
    # when the systemd unit sources below were missing their .service suffix. It
    # is useless to systemd -- and worse, one in /etc/systemd/system shadows the
    # working unit in /usr/lib -- so replace it. A real file, or a link that
    # resolves, is left strictly alone.
    if [[ -L $link && ! -e $link ]]; then
        rm -f "$link" >>$error_log 2>&1
    fi
    [[ -e $link || -L $link ]] && return 0
    ln -s "$target" "$link" >>$error_log 2>&1
}
# ONE channel vocabulary, shared by the update track and the version label.
#
# FOG used to have two things called a channel that shared the word and shared
# nothing else (GH-1279). `fog_update_channel` said stable/staging/dev; the
# FOG_CHANNEL stamped into system.class.php said Patches/Beta/Release
# Candidate/Feature. So working-1.6 was simultaneously channel "dev" and channel
# "Beta", and dev-branch was channel "staging" and channel "Patches". Nothing
# reconciled them and neither name said which one it was, so the docs
# contradicted themselves and an admin reading one while configuring the other
# got it wrong, silently.
#
# They are now the same word: the stored value is lowercase, the FOG_CHANNEL
# label is its title-case form. .githooks/lib/fog-version.sh owns the label end
# and must be kept in step with the table below; tests/update-channel-vocabulary
# .test.sh fails if the two drift.
#
#   branch        channel   FOG_CHANNEL
#   stable        stable    Stable
#   dev-branch    patches   Patches
#   working-1.6   beta      Beta
#   rc-*          rc        Release Candidate
#   feature-*     --        Feature
#
# feature has no update channel: nobody tracks a feature branch as a standing
# preference, so FOG_CHANNEL stays a superset rather than a mismatch.
#
# rc DID have none, for the stated reason that nobody tracks a release
# candidate as a standing preference. That turned out to be wrong about who is
# tracking one. The 1.5 -> 1.6 crossing is the case: a 1.5 server moving onto
# 1.6 wants the release candidate, not the beta branch, and "track the current
# RC until it becomes stable" is exactly a standing preference. So the reason
# is retired rather than the decision quietly reversed.
#
# rc is also the one channel that is a QUERY rather than a constant. The others
# name a branch that always exists; rc-* is a family whose members come and go,
# so channelToBranch has to ask the remote which one is current. Two
# consequences that are not obvious:
#
#   * It can legitimately resolve to NOTHING, when no release candidate is
#     published. That is not the same failure as a misspelled channel and does
#     not deserve the same message, so channelToBranch returns 2 for it and 1
#     for an unknown name.
#   * "Current" is the highest VERSION, not the newest commit date. Version
#     order is what an RC series means (rc-1.6.10 follows rc-1.6.2, which a
#     lexical sort gets backwards), it survives someone pushing a fix to an
#     older RC branch, and -- the deciding reason -- it is answerable from
#     `git ls-remote`, which reports no dates at all. bin/bootstrap.sh has to
#     resolve this before it has a clone to run for-each-ref against, so a
#     date-based answer could not be shared with it.
#
# And rc is the one label that is not its channel's title-case form: the stored
# value is the abbreviation, the label spells it out. That is a deliberate
# exception to the rule above rather than a drift -- "Release Candidate" is
# already in released version strings, and nobody reads it as naming something
# other than rc. tests/update-channel-vocabulary.test.sh pins the pair
# explicitly instead of deriving it.
#
# WHY THIS DIRECTION, given GH-1012 deliberately chose stable/staging/dev to
# match README.md's table. That decision was "match README", not "these three
# words are fixed", so changing README honors it rather than reversing it. And
# FOG_CHANNEL was already the more accurate half: dev-branch really is the
# 1.5.x PATCHES line rather than anything staged for stable, and working-1.6
# really is the 1.6 BETA. Worse, `dev` pointed at working-1.6 while a branch
# literally named `dev-branch` existed -- an admin who read
# fog_update_channel='dev' and assumed it tracked dev-branch was wrong, and
# nothing told them. Aligning the other way would have preserved that.
#
# Maps a channel name to the git branch it tracks. Accepts the retired
# stable/staging/dev spellings so an existing .fogsettings keeps updating; see
# normalizeChannel().
channelToBranch() {
    case "$(normalizeChannel "$1")" in
        stable) echo "stable" ;;
        patches) echo "dev-branch" ;;
        beta) echo "working-1.6" ;;
        rc) rcBranch || return 2 ;;
        *) return 1 ;;
    esac
}
# The current release-candidate branch, or nothing.
#
# Asked of the REMOTE, not of local refs: a checkout that has never fetched
# has no origin/rc-* to find, and bin/bootstrap.sh runs before there is a
# checkout at all. ls-remote answers both cases identically and needs no fetch.
#
# --sort=-v:refname is a version sort, so rc-1.6.10 beats rc-1.6.2 -- which a
# plain lexical sort gets backwards, and which is the whole point of asking.
# Sorting by date is not an option here even if it were preferable: the remote
# ref advertisement carries no commit dates.
#
# Returns 1 with no output when nothing matches. The caller is expected to say
# "no release candidate is published" rather than "unknown channel"; those are
# different problems for the admin.
# refs/heads/rc-*, NOT a bare rc-*. ls-remote matches a pattern against the
# TAIL of each ref at slash boundaries, so `rc-*` also matches
# refs/heads/feat/rc-update-channel -- a feature branch, offered to an admin as
# the current release candidate. Anchoring at refs/heads/ makes "rc-" mean the
# start of the branch name rather than the start of its last path segment, and
# the sed re-checks that on the way out: matching rules are not the place to
# rely on one layer alone when the answer decides what gets checked out.
rcBranch() {
    local ref
    # Sorted with `sort -Vr`, NOT with git's own --sort=-v:refname.
    #
    # `git ls-remote --sort` needs git 2.18 (2018). RHEL/CentOS 7 ships
    # 1.8.3.1 and Ubuntu 18.04 ships 2.17.1 -- and those are exactly the
    # hosts this matters on, because they are 1.5-era servers and `rc` is the
    # DEFAULT channel of the 1.5 updater whose whole job is moving them to
    # 1.6. On an older git the option is a usage error, 2>/dev/null swallows
    # it, and every caller reports "no release candidate is currently
    # published": a confident, wrong diagnosis. sort -V is coreutils 7
    # (2008), which predates every distro FOG supports.
    #
    # Asks the checkout's OWN origin where there is one; the constant is only
    # a fallback for bin/bootstrap.sh, which has no clone yet. A server
    # installed from a fork or an internal mirror must not be told about a
    # release candidate its origin does not carry -- gitUpdateToBranch would
    # then fail at `git checkout` -- and an air-gapped one must not reach for
    # github.com.
    local remote
    remote=$(git -C "${FOG_git_path}" remote get-url origin 2>/dev/null)
    [[ -n $remote ]] || remote="${FOG_git_remote:-https://github.com/FOGProject/fogproject.git}"
    ref=$(git ls-remote --heads "$remote" 'refs/heads/rc-*' 2>/dev/null \
        | sed -n 's#^[0-9a-f]\{7,\}[[:space:]]\{1,\}refs/heads/\(rc-[^/]\{1,\}\)$#\1#p' \
        | sort -Vr \
        | head -n1) || return 1
    [[ -n $ref ]] || return 1
    echo "$ref"
}
# Folds a channel name to its canonical spelling, so exactly one place knows the
# retired names. Returns 1 for anything unrecognized rather than echoing it
# back: a caller asking "is this a channel" needs a no, and a typo silently
# passed through would be resolved as a branch name later and fail further from
# the cause.
#
# The retired spellings are accepted FOREVER, not for a deprecation window.
# Every server installed before this change carries one in .fogsettings, that
# file is the admin's own record, and an update that refuses to run because a
# value was renamed under it would be a far worse outcome than two extra case
# arms.
normalizeChannel() {
    case "$1" in
        stable) echo "stable" ;;
        patches|staging) echo "patches" ;;
        beta|dev) echo "beta" ;;
        rc) echo "rc" ;;
        *) return 1 ;;
    esac
}
# The inverse of channelToBranch(), used to derive a sensible FOG_update_channel
# default from whatever branch happens to be checked out. Echoes nothing for a
# branch that is not one of the three channels -- a feature/PR branch has no
# channel, and guessing one would be worse than leaving it for the admin to set.
branchToChannel() {
    case "$1" in
        stable) echo "stable" ;;
        dev-branch) echo "patches" ;;
        working-1.6) echo "beta" ;;
        rc-*) echo "rc" ;;
        *) return 1 ;;
    esac
}
# Records the commit this install is being built from, so a LATER failed run
# can name something to go back to. Called immediately before writeUpdateFile,
# which is what persists it -- see FOG_last_good_commit in managedKeys for why
# that is the point chosen.
#
# Never fails the install. A tarball install, or a checkout whose .git has been
# removed, simply has no commit to record, and that is not an error: it means
# offerRevert stays quiet, which is correct for a tree that cannot be reset.
markInstallCommit() {
    local head
    head=$(git -C "${FOG_git_path}" rev-parse HEAD 2>/dev/null) || return 0
    [[ -n $head ]] && FOG_last_good_commit="$head"
    return 0
}
# Names the way back after a failed install, and does not take it.
#
# Deliberately an OFFER. Reverting means git-resetting the working copy and
# re-running the installer -- and a process that has just failed is the worst
# thing to trust with a second, equally invasive run. The admin has the
# context to decide; this only removes the part they cannot easily reconstruct,
# which is WHICH commit was last known to install cleanly.
#
# Silent unless all four hold: this run failed, a good commit was recorded, the
# checkout is a git tree, and HEAD has actually moved since that commit. A
# fresh install has nothing recorded, and a re-run at the same commit has
# nothing to go back to -- in both cases the failure is not about the code
# having changed, so pointing at git would be a wrong diagnosis.
offerRevert() {
    local status=$1 head recorded
    [[ $status -eq 0 ]] && return 0
    [[ -d ${FOG_git_path}/.git ]] || return 0

    # Read back off disk, NOT from $FOG_last_good_commit in memory.
    #
    # markInstallCommit() sets the in-memory value before writeUpdateFile()
    # persists it -- it has to, because writeUpdateFile emits the file FROM
    # that variable. So if anything between the two fails, including
    # writeUpdateFile itself, memory already holds the current HEAD and a
    # comparison against it finds them equal and says nothing. That is exactly
    # the run this function exists for.
    #
    # .fogsettings is the honest answer: it still names the last commit that
    # actually got as far as being written down. On a run that succeeded it
    # holds HEAD, and the comparison below correctly stays quiet.
    # No back-reference: strip the key, then the quoting. Simpler to read and
    # immune to the escaping accidents a capture group invites here.
    recorded=$(sed -n 's/^FOG_last_good_commit=//p' \
        "${fogprogramdir}/.fogsettings" 2>/dev/null | tr -d "\"' " | tail -n1)
    [[ -n $recorded ]] || return 0

    head=$(git -C "${FOG_git_path}" rev-parse HEAD 2>/dev/null) || return 0
    [[ -z $head || $head == $recorded ]] && return 0
    echo
    # --detach, not `reset --hard`. The case this exists for is an update that
    # switched channels and then failed, so the checkout is on (say)
    # working-1.6 while the last good commit belongs to stable -- and
    # `reset --hard` would point the working-1.6 REF at a stable commit,
    # leaving a diverged branch that git status complains about long after the
    # install is fixed. Detaching moves only HEAD, which is all that is wanted:
    # the next updatefog.sh run checks out a branch again anyway.
    #
    # This is a different judgment from gitUpdateToBranch, which keeps
    # reset --hard deliberately -- there it is discarding local mess to make an
    # update possible, here it is navigating history.
    echo " * This install did not finish, and the checkout has moved since the"
    echo " | last one that did. To put the code back where it was and re-run:"
    echo " |"
    echo " |     git -C ${FOG_git_path} checkout --detach ${recorded}"
    echo " |     cd ${FOG_git_path}/bin && ./installfog.sh"
    echo " |"
    echo " | bin/revertupdate.sh does the same checkout for you, and it can be run"
    echo " | later -- this message appears only now, the script reads the same record."
    echo " |"
    echo " | Nothing has been reverted for you. Your customizations were already"
    echo " | restored by this run -- see docs/SUPPORTED_CUSTOMIZATIONS.md -- and"
    echo " | bin/restorekernel.sh --list will show the kernel sets kept for you."

    # THE CODE IS ONLY HALF THE STATE.
    #
    # updateDB() migrates the schema, and a great many install steps run after
    # it -- so a failure is quite likely to be one where the database has
    # already gone forward. Telling somebody to check out the old commit and
    # re-run, without saying that, sends old code at a newer schema.
    #
    # Said only when it actually happened. recordPreUpgradeSchema() captures
    # the version immediately before the migration; if the database still
    # reports that number, nothing moved and the short message above is the
    # whole truth.
    #
    # bin/revertfog.sh is deliberately NOT offered here. It restores a
    # pre-upgrade 1.5 dump and _dumpNotFifteenReason() refuses anything that
    # looks like 1.6 -- so pointing at it would send someone to a tool that
    # turns them away. bin/revertupdate.sh is the tool for this case (GH-1659):
    # it restores a dump only when the dump's schema matches the checked-out
    # code, which is the proof this situation needs. The manual command is
    # still printed, because the dump path is the one thing worth having in
    # the log if the script is never run.
    local nowSchema
    nowSchema=$(schemaVersionInDB 2>/dev/null)
    if [[ -n $fogPreUpgradeSchema && -n $nowSchema && $nowSchema != "$fogPreUpgradeSchema" ]]; then
        echo " |"
        echo " | THE DATABASE HAS ALREADY BEEN MIGRATED by this run, from schema"
        echo " | ${fogPreUpgradeSchema} to ${nowSchema}. Moving the checkout back is NOT enough on"
        echo " | its own -- the older code would run against the newer schema."
        if [[ -n $fogPreUpgradeDump && -s $fogPreUpgradeDump ]]; then
            echo " |"
            echo " | Restore the dump taken immediately before the migration, after"
            echo " | the checkout above so the code matches it:"
            echo " |"
            echo " |     cd ${FOG_git_path}/bin && ./revertupdate.sh --checkout --restore-db ${fogPreUpgradeDump}"
            echo " |"
            echo " | or by hand:  mysql -u ${DB_user} -p ${DB_name} < ${fogPreUpgradeDump}"
        else
            echo " |"
            echo " | No pre-migration dump was written for this run, so there is"
            echo " | nothing here to restore the schema from."
        fi
    fi
    echo
    return 0
}
# Which of FOG's four packaging families this box belongs to, so a caller can
# pick a package manager instead of hardcoding one.
#
# Sets linuxReleaseName / OSVersion / linuxReleaseName_lower -- the same three
# globals installfog.sh has always derived at this point in its flow -- plus
# $osfamily. Each is guarded with [[ -z ]] like the values it replaces, so a
# caller that has already set them (a test, an override) is left alone.
#
# MUST be called directly, never as $(detectOSFamily): command substitution
# runs it in a subshell and every one of those globals is lost.
#
# The patterns are lib/common/input.sh's, which were the more complete of the
# two copies -- installfog.sh's inline block did not know *mageia*. That is the
# drift this exists to end: the distro-name parse lived in installfog.sh, the
# family map lived in input.sh, and neither could see the other.
#
# Returns 1 with $osfamily empty for a distro matching none of the four. It
# does NOT fall back to redhat the way input.sh's suggestion does, and the
# difference is deliberate: there, redhat is a pre-filled answer to a prompt a
# person can correct; here it would be a guess about which package-manager
# binary actually exists, made by something with nobody watching.
detectOSFamily() {
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
    linuxReleaseName_lower=$(echo "$linuxReleaseName" | tr "[:upper:]" "[:lower:]")
    osfamily=""
    case $linuxReleaseName_lower in
        *fedora*|*red*hat*|*centos*|*mageia*|*alma*|*rocky*)
            osfamily="redhat"
            ;;
        *ubuntu*|*bian*|*mint*)
            osfamily="debian"
            ;;
        *alpine*)
            osfamily="alpine"
            ;;
        *arch*|*manjaro*)
            osfamily="arch"
            ;;
        *)
            return 1
            ;;
    esac
    return 0
}
backupReports() {
    dots "Backing up user reports"
    # EMPTIED first. It was only created-if-absent, and nothing ever removed
    # it, so it accumulated across upgrades -- which was harmless while nothing
    # read it back and is not now: a report the admin deleted through the web
    # UI was still sitting here from a previous run and would be restored on
    # the next one, undeleting itself.
    rm -rf ../rpttmp >>$error_log 2>&1
    mkdir -p ../rpttmp >>$error_log 2>&1
    [[ -d $webdirdest/management/reports/ ]] && cp -a $webdirdest/management/reports/. ../rpttmp/ >>$error_log 2>&1
    echo "Done"
    return 0
}
# Where backupPreservedCustomizations() stashes anything that has to outlive
# configureHttpd()'s rm -rf $webdirdest. Deliberately under $fogprogramdir,
# never inside $webdirdest -- that is the same "survives the wipe by
# construction" property $fogprogramdir/secureboot already relies on, rather
# than a copy that has to be re-made correctly every time.
#
# Resolved on CALL, not when this file is sourced. installfog.sh sources
# functions.sh at line ~93 but does not settle $fogprogramdir until config.sh
# runs several hundred lines later, so a top-level assignment here evaluated to
# "/customizations" and wrote the backups to the filesystem root. That is what
# the first real-server run actually did -- the sandbox never caught it because
# it always set $fogprogramdir before sourcing.
# Record the checksum of a file FOG just downloaded, so a later run can tell
# whether it is still the file FOG put there.
#
# The version/tag_name xattrs alone cannot answer that. Overwriting a file IN
# PLACE -- `> bzImage`, dd, cp onto an existing path, which is exactly how a
# custom kernel gets installed -- leaves the existing xattrs untouched, so the
# admin's kernel keeps FOG's old tag and looks original. Confirmed on a real
# server: a hand-written bzImage still reported 2 xattrs.
#
# A checksum recorded at download time is not defeated by that: the content
# changed, so the comparison fails, however the write was done.
_stampFogSum() {
    local f="$1" sum
    [[ -f $f ]] || return 0
    command -v sha256sum >/dev/null 2>&1 || return 0
    command -v attr >/dev/null 2>&1 || return 0
    sum=$(sha256sum "$f" 2>/dev/null | cut -d' ' -f1)
    [[ -n $sum ]] && attr -s fogsum -V "$sum" "$f" >>$error_log 2>&1
    return 0
}
# Echoes 0 when $1 still matches the checksum FOG stamped, 1 when it differs
# (admin-modified), 2 when there is nothing to compare against -- an older
# install whose kernels predate the stamp. 2 is NOT "modified": reporting a
# custom kernel on every existing server at first upgrade would be noise, and
# the file is safely backed up regardless.
_fogSumStatus() {
    local f="$1" want have
    [[ -f $f ]] || { echo 2; return; }
    command -v sha256sum >/dev/null 2>&1 || { echo 2; return; }
    command -v attr >/dev/null 2>&1 || { echo 2; return; }
    want=$(attr -q -g fogsum "$f" 2>/dev/null)
    [[ -z $want ]] && { echo 2; return; }
    have=$(sha256sum "$f" 2>/dev/null | cut -d' ' -f1)
    [[ $want == "$have" ]] && echo 0 || echo 1
}
_resolveCustomizationsDir() {
    [[ -n $customizationsDir ]] && return 0
    local base="${fogprogramdir:-/opt/fog}"
    customizationsDir="${base%/}/customizations"
}
# Backs up whatever is actually customized under $webdirdest/service/ipxe/
# BEFORE configureHttpd() destroys that tree.
#
# This used to live only in bin/updatefog.sh (backupCustomizations, since
# removed), which meant a bare `./installfog.sh` upgrade -- the way most
# people upgrade -- silently got none of it. Running it from installfog.sh's
# own sequence is the point: the protection now applies to every run.
#
# $bgfile is intentionally NOT local: restorePreservedCustomizations() runs
# later in the same shell and needs the name that was actually backed up, not
# a re-read of the setting (which an admin could have changed mid-install).
backupPreservedCustomizations() {
    dots "Backing up customizations"
    _resolveCustomizationsDir
    local ipxedir="${webdirdest}service/ipxe"
    local f st=0
    # Severity is split deliberately, because errorStat() EXITS the installer
    # when $exitFail is unset -- which is every normal installfog.sh run:
    #
    #   $st   -> fatal. Only set when a customization we positively identified
    #            could not be copied to safety. Aborting here is the safe
    #            outcome: configureHttpd() has not wiped anything yet, so the
    #            admin's file is still sitting untouched where it always was.
    #   warn  -> non-fatal. An optional file we merely tried for. Killing an
    #            install over an unreadable legacy refind blob would be absurd.
    # A failed mkdir is not itself fatal -- if there is nothing to preserve,
    # nothing is lost. If there IS a background to preserve, the copy below
    # fails too and that is what stops the run.
    mkdir -p "$customizationsDir/ipxe-bg" "$customizationsDir/ipxe-legacy" >>$error_log 2>&1 || true

    # FOG_IPXE_BG_FILE is a real, GUI-editable globalSettings row (see
    # packages/web/commons/schema.php) whose whole purpose is letting an admin
    # rename the background file. Read the ACTUAL value rather than assuming
    # "bg.png", which is what the old hardcoded list got wrong.
    #
    # On a first-ever install globalSettings does not exist yet -- updateDB()
    # runs after configureHttpd() -- so this errors into $error_log and leaves
    # $bgfile empty, which is treated exactly like "nothing customized". No
    # special-casing needed for a fresh install.
    bgfile=$(mysql $sqloptionsuser --password="${DB_password}" -N -B \
        --execute="SELECT settingValue FROM globalSettings WHERE settingKey='FOG_IPXE_BG_FILE'" \
        ${DB_name} 2>>$error_log)
    # Strip surrounding whitespace, and treat mysql's literal NULL output as
    # empty -- an unset settingValue comes back as the four characters "NULL"
    # under -N, which would otherwise be looked for as a filename.
    bgfile="${bgfile#"${bgfile%%[![:space:]]*}"}"
    bgfile="${bgfile%"${bgfile##*[![:space:]]}"}"
    [[ $bgfile == NULL ]] && bgfile=""
    # basename guards against a settingValue containing a path: this string
    # reaches a cp destination, and "../../something" would write outside the
    # backup directory entirely.
    [[ -n $bgfile ]] && bgfile=$(basename "$bgfile")
    if [[ -n $bgfile && -f "${ipxedir}/${bgfile}" ]]; then
        cp -f "${ipxedir}/${bgfile}" "${customizationsDir}/ipxe-bg/${bgfile}" >>$error_log 2>&1 || st=1
    fi

    local warn=0
    for f in refind.conf refind.efi refind_x64.efi refind_ia32.efi refind_aa64.efi; do
        [[ -f "${ipxedir}/${f}" ]] && { cp -f "${ipxedir}/${f}" "${customizationsDir}/ipxe-legacy/${f}" >>$error_log 2>&1 || warn=1; }
    done
    if [[ $st -ne 0 ]]; then
        echo "Failed"
        echo " * Could not copy the customized iPXE background (${bgfile}) to"
        echo "   ${customizationsDir}/ipxe-bg/."
        echo " * Stopping BEFORE the web tree is rebuilt, so your file is still"
        echo "   intact at ${ipxedir}/${bgfile}. Fix the permissions or free"
        echo "   space under ${customizationsDir} and re-run. See $error_log."
        exit 1
    fi

    # Snapshot the KERNEL/INIT set into a rotated generation -- not the whole
    # directory.
    #
    # service/ipxe is a mixed bag: FOG's own boot.php/advanced.php/index.php,
    # bg images, grub.exe/memdisk/memtest.bin, the refind set, AND the kernels.
    # An earlier version copied all of it, which made a "kernel backup" full of
    # PHP and led directly to restoring a previous release's boot.php over a
    # freshly installed one. Everything in here that FOG ships is already
    # versioned in git; only the kernel/init material is worth generations.
    #
    # Do not try to ENUMERATE custom kernel names -- subtract what FOG ships
    # instead.
    #
    # Enumerating cannot be made complete. A custom kernel name can come from
    # hostKernel/hostInit, from groupKernel/groupInit, from the
    # FOG_TFTP_PXE_KERNEL/_32/_ARM settings, or from nothing FOG records at all
    # -- an admin's own pre-boot customization can chain a kernel this server
    # has never heard of. Any list of places to look is a list that will be
    # short one place.
    #
    # What IS knowable exactly is the set FOG ships: the contents of
    # packages/web/service/ipxe in the source tree (13 files -- the PHP, the
    # bg images, grub.exe/memdisk/memtest.bin, refind). Everything else living
    # in the live directory is either a kernel/init downloadfiles() fetched or
    # something the admin put there, and both are worth keeping.
    #
    # So: back up (live directory) minus (what the source tree ships). No
    # guessing, and a fully custom name is covered however it got there.
    [[ -z ${BOOT_kernel_backups_kept} || ! ${BOOT_kernel_backups_kept} =~ ^[0-9]+$ || ${BOOT_kernel_backups_kept} -lt 1 ]] && BOOT_kernel_backups_kept=3
    local kbdir="${customizationsDir}/kernel-backups" k kf bn n same
    local shippeddir="${webdirsrc%/}/service/ipxe"
    if [[ -d $ipxedir ]]; then
        mkdir -p "$kbdir" >>$error_log 2>&1 || warn=1
        # Every generation at or above the retention, not just gen-N. Lowering
        # --kernel-backup-count from 5 to 3 used to leave gen-4 and gen-5 on
        # disk forever: outside the rotation loop below, so never shifted and
        # never pruned, frozen at whatever they last held -- while
        # restorekernel.sh --list went on offering them as though they were
        # current snapshots.
        for k in "${kbdir}"/gen-*; do
            [[ -d $k ]] || continue
            n="${k##*/gen-}"
            [[ $n =~ ^[0-9]+$ ]] || continue
            [[ $n -ge ${BOOT_kernel_backups_kept} ]] && rm -rf "$k" >>$error_log 2>&1
        done
        # Does gen-1 already hold exactly this capture set, byte for byte?
        #
        # The rotation used to run on every install unconditionally, and not
        # every FOG release ships a new FOS kernel -- so three upgrades
        # carrying one kernel left gen-1, gen-2 and gen-3 holding three
        # identical copies, and "keep 3" meant one real version. The depth an
        # admin asked for silently evaporated, and restorekernel.sh --list
        # offered three snapshots that were the same snapshot.
        #
        # Compared by CONTENT and in BOTH directions. A file the admin added
        # since the last install is a change; so is one they deleted, which a
        # subset test would call unchanged. Same filters as the copy loop
        # below, so the two cannot disagree about what a generation holds.
        same=1
        if [[ -d "${kbdir}/gen-1" ]]; then
            for kf in "${ipxedir}"/*; do
                [[ -f $kf ]] || continue
                bn=$(basename "$kf")
                [[ -e "${shippeddir}/${bn}" ]] && continue
                case $bn in
                    bzImage.*|bzImage32.*|arm_Image.*|init.xz.*|init_32.xz.*|arm_init.cpio.gz.*) continue ;;
                esac
                cmp -s "$kf" "${kbdir}/gen-1/${bn}" || { same=0; break; }
            done
            if [[ $same -eq 1 ]]; then
                for kf in "${kbdir}/gen-1"/*; do
                    [[ -f $kf ]] || continue
                    bn=$(basename "$kf")
                    [[ -f "${ipxedir}/${bn}" ]] || { same=0; break; }
                done
            fi
        else
            same=0
        fi
        # GH-1579: rotate on BOOT_kernel_backups_kept, not on the retired
        # pre-GH-1120 spelling kernelBackupGenerations. That name now survives
        # only as a migration source (see migrateDeprecatedKeys) and in the
        # deprecated-key strip list, so on a migrated install -- and on every
        # fresh one -- it is unset, bash reads it as 0, k starts at -1 and this
        # loop never runs. gen-1 was overwritten in place forever and gen-2
        # onward were never created, which silently made
        # --kernel-backup-count a no-op and restorekernel.sh --generation N
        # unusable for any N above 1.
        #
        # Skipped entirely when nothing changed: rotating identical content
        # spends a generation to record that an upgrade happened, and the
        # generations are for kernels, not for installer runs.
        if [[ $same -eq 0 ]]; then
            for ((k = BOOT_kernel_backups_kept - 1; k >= 1; k--)); do
                [[ -d "${kbdir}/gen-${k}" ]] && mv "${kbdir}/gen-${k}" "${kbdir}/gen-$((k + 1))" >>$error_log 2>&1
            done
        fi
        mkdir -p "${kbdir}/gen-1" >>$error_log 2>&1 || warn=1
        # cp -a preserves the version/tag_name xattrs downloadfiles() stamps on
        # each kernel, so every generation says which FOS release it came from
        # without a separate manifest to keep in sync.
        for kf in "${ipxedir}"/*; do
            [[ -f $kf ]] || continue
            bn=$(basename "$kf")
            # Shipped by FOG -> already versioned in git, skip. If the source
            # tree cannot be found, $shippeddir does not exist, every file
            # fails this test and everything is kept -- the safe direction.
            [[ -e "${shippeddir}/${bn}" ]] && continue
            # Skip the per-version siblings this function itself leaves behind
            # (bzImage.20260806-111046). They are already a copy of a kernel;
            # snapshotting them into every generation would multiply the same
            # bytes by the generation count for no added recoverability.
            case $bn in
                bzImage.*|bzImage32.*|arm_Image.*|init.xz.*|init_32.xz.*|arm_init.cpio.gz.*) continue ;;
            esac
            cp -a "$kf" "${kbdir}/gen-1/${bn}" >>$error_log 2>&1 || warn=1
        done
        # A custom kernel installed under a DEFAULT name is the case none of
        # the rules above can catch on their own: it is backed up like any
        # other non-shipped file, but downloadfiles() will re-download FOG's
        # own kernel over it, and the restore deliberately leaves a
        # freshly-installed default name alone.
        #
        # Compared by CHECKSUM, not by whether the version xattrs are present.
        # Overwriting in place -- `> bzImage`, dd, cp onto the existing path,
        # which is how a custom kernel actually gets installed -- preserves the
        # existing xattrs, so FOG's old tag survives on the admin's file and an
        # absence test sees nothing. _stampFogSum records the checksum at
        # download time precisely so the content can be compared instead.
        #
        # Detected here, reported after the restore. Silently keeping the
        # custom kernel means never getting kernel updates again; silently
        # replacing it means losing it. Both fail the same way -- the admin
        # does not find out. So do neither, and say so.
        customDefaultKernels=""
        for bn in bzImage bzImage32 arm_Image init.xz init_32.xz arm_init.cpio.gz; do
            [[ -f "${ipxedir}/${bn}" ]] || continue
            [[ $(_fogSumStatus "${ipxedir}/${bn}") -eq 1 ]] && customDefaultKernels="${customDefaultKernels}${bn} "
        done
    fi

    [[ $warn -ne 0 ]] && echo -n "(some optional files could not be backed up) "
    errorStat 0
}
# Restores what backupPreservedCustomizations() saved, AFTER
# configureTFTPandPXE()'s downloadfiles() has re-laid the default-named
# kernel/init set.
#
# Deliberately does NOT restore the six default kernel/init names here -- the
# point of an update is to pick up the latest kernel. That is what the
# versioned backup provides an explicit, admin-invoked restore path for
# instead.
restorePreservedCustomizations() {
    dots "Restoring customizations"
    _resolveCustomizationsDir
    local ipxedir="${webdirdest}service/ipxe"
    local f st=0

    if [[ -n $bgfile && -f "${customizationsDir}/ipxe-bg/${bgfile}" ]]; then
        cp -f "${customizationsDir}/ipxe-bg/${bgfile}" "${ipxedir}/${bgfile}" >>$error_log 2>&1 || st=1
    fi
    for f in refind.conf refind.efi refind_x64.efi refind_ia32.efi refind_aa64.efi; do
        [[ -f "${customizationsDir}/ipxe-legacy/${f}" ]] && { cp -f "${customizationsDir}/ipxe-legacy/${f}" "${ipxedir}/${f}" >>$error_log 2>&1 || st=1; }
    done

    # The snapshot now holds only kernel/init material (see the backup side),
    # so the restore rule is simple and safe:
    #
    #   absent from the live tree  -> put it back. Only a per-host custom
    #                                 kernel/init reaches this: FOG re-downloads
    #                                 its own six every run, so they are never
    #                                 absent, and nothing else was captured.
    #   present                    -> leave the freshly installed file alone.
    #                                 Picking up the new kernel is the point of
    #                                 an update.
    #
    # $restoreKernelBackup is the one exception: --restore-kernel-backup, which
    # an admin passes when re-running the installer against a previous
    # commit. An older commit wants its older kernels, so the defaults are
    # forced back over the fresh ones -- what the retired
    # _restorePreviousKernel() used to do on that path.
    local kbdir="${customizationsDir}/kernel-backups"
    local defaultnames=" bzImage bzImage32 arm_Image init.xz init_32.xz arm_init.cpio.gz "
    local bn
    if [[ -d "${kbdir}/gen-1" ]]; then
        for f in "${kbdir}/gen-1"/*; do
            [[ -f $f ]] || continue
            bn=$(basename "$f")
            if [[ ! -e "${ipxedir}/${bn}" ]]; then
                cp -a "$f" "${ipxedir}/${bn}" >>$error_log 2>&1 || st=1
            elif [[ ${restoreKernelBackup:-0} -eq 1 && $defaultnames == *" $bn "* ]]; then
                cp -a "$f" "${ipxedir}/${bn}" >>$error_log 2>&1 || st=1
            fi
        done
    fi
    # Leave the OUTGOING kernel next to the new one, named for the release it
    # came from: bzImage.20260806-111046 beside bzImage.
    #
    # Done here, not at backup time, because configureHttpd() rm -rf's the whole
    # web tree between the two -- a sibling written before that is deleted
    # minutes later, which is exactly what the first attempt did. The generation
    # snapshot is the surviving copy, so build the sibling from it.
    #
    # The generation directories remain the complete rotated history; this is
    # the copy visible while looking at the boot directory, and the one a single
    # host can be pointed at by name without restoring anything. Per version
    # rather than a single .prev so several updates accumulate -- cheap next to
    # the images this server already holds.
    if [[ -d "${kbdir}/gen-1" ]]; then
        local tag
        for bn in bzImage bzImage32 arm_Image init.xz init_32.xz arm_init.cpio.gz; do
            [[ -f "${kbdir}/gen-1/${bn}" ]] || continue
            tag=$(attr -q -g tag_name "${kbdir}/gen-1/${bn}" 2>/dev/null | tr -d '"' | tr -c 'A-Za-z0-9.-' '_')
            [[ -z $tag ]] && tag="prev"
            # Same content under the same name means the update did not change
            # this kernel; a sibling would just be a duplicate.
            cmp -s "${kbdir}/gen-1/${bn}" "${ipxedir}/${bn}" && continue
            [[ -e "${ipxedir}/${bn}.${tag}" ]] || cp -a "${kbdir}/gen-1/${bn}" "${ipxedir}/${bn}.${tag}" >>$error_log 2>&1
        done
    fi
    # Files the admin marked to be kept, put back if the fresh tree has none
    # of that name.
    #
    # The web root is deleted and rebuilt on every install, and a per-release
    # sibling is deliberately NOT part of a generation -- it is already a copy
    # of a kernel, and snapshotting it would multiply the same bytes by the
    # generation count. So nothing else brings one back, and "keep this
    # kernel" would have survived exactly until the next upgrade.
    #
    # The six default names are excluded, the same way the generation restore
    # above excludes them: picking up the new kernel is the point of an
    # update. Keeping bzImage ITSELF is therefore close to meaningless -- keep
    # the sibling or a custom name, which is what the tab steers toward.
    #
    # ORDER DEPENDENCY, and it fails silently if broken: the directory is
    # created by _ensureCustomizationsTree(), which configureHttpd() calls
    # first thing -- installfog.sh calls that BEFORE this function
    # (backup, then configureHttpd, then restore). Moving either past the
    # other leaves this reading a directory that does not exist yet, which
    # the -d guard turns into "nothing was kept" rather than an error, and
    # the admin's kernel is gone with the record still saying it was kept.
    if [[ -d "${kbdir}/keep" ]]; then
        for f in "${kbdir}/keep"/*; do
            [[ -f $f ]] || continue
            bn=$(basename "$f")
            [[ $defaultnames == *" $bn "* ]] && continue
            [[ -e "${ipxedir}/${bn}" ]] || cp -a "$f" "${ipxedir}/${bn}" >>$error_log 2>&1 || st=1
        done
    fi
    [[ -d $ipxedir ]] && chown -R ${SVC_user}:${apacheuser} "$ipxedir" >>$error_log 2>&1
    # Never fatal, unlike the backup side. By this point configureHttpd() has
    # already rebuilt the web tree, so aborting would strand a nearly-complete
    # install and fix nothing -- and unlike the backup case, the files are NOT
    # lost: they are still sitting in $customizationsDir for the admin to put
    # back by hand. Say exactly that instead of dying.
    if [[ $st -ne 0 ]]; then
        echo "Failed"
        echo " * One or more customizations could not be restored to ${ipxedir}."
        echo " * Nothing was lost -- your files are still in ${customizationsDir}."
        echo "   Copy them back by hand once the install finishes. See $error_log."
        return 0
    fi
    errorStat 0
    # Say it plainly rather than picking for them -- see the detection comment
    # in backupPreservedCustomizations.
    if [[ -n $customDefaultKernels ]]; then
        echo
        echo " * NOTE: these looked like hand-installed kernels under FOG's own"
        echo "   names, and this update has replaced them with the versions it"
        echo "   downloaded:"
        for f in $customDefaultKernels; do
            echo "     ${f}"
        done
        echo "   Your copies were saved first and are still available:"
        echo "     ${bindirsrc:-.}/restorekernel.sh --list"
        echo "     ${bindirsrc:-.}/restorekernel.sh --generation 1"
        echo "   Restoring puts them back over the downloaded ones."
    fi
}
# GH-685: the MariaDB client library turns TLS on by default from 10.10.1
# onward and then refuses to connect at all when the server offers none --
# "ERROR 2026 (HY000): TLS/SSL error: SSL is required, but the server does not
# support it". Debian 12 and Ubuntu 24.04 both ship such a client, so a storage
# node installed on either could not reach an older master and the install died
# at "Checking connection to master database" with an empty error log.
#
# FOG's own web tier reaches the same database over PDO without TLS, so a
# plaintext-only master is not a reason to refuse the install. The fallback is
# only ever tried after the default attempt -- encrypted wherever the server
# offers it -- has already failed, so an install that was getting TLS keeps it.
#
# The flag differs by client: MySQL 8.4 dropped --skip-ssl and takes only
# --ssl-mode, while MariaDB before 11.4 has no --ssl-mode. Try both and keep
# whichever the installed client accepts. Empty means none was ever needed.
mysqlsslopt=""
detectMysqlSslOption() {
    local opt
    [[ -n $mysqlsslopt ]] && return 0
    for opt in "--ssl-mode=DISABLED" "--skip-ssl"; do
        if mysql "$@" $opt --execute="quit" >/dev/null 2>&1; then
            mysqlsslopt="$opt"
            return 0
        fi
    done
    return 1
}
checkDatabaseConnection() {
    dots "Checking connection to master database"
    [[ -n ${DB_host} ]] && host="--host=${DB_host}"
    sqloptionsuser="${host} -s --user=${DB_user}"
    mysql $sqloptionsuser --password="${DB_password}" --execute="quit" >/dev/null 2>&1
    local connected=$?
    # Only the whole option string is reusable, so widen $host too -- the
    # fogstorage checks later on build their own command line from it.
    if [[ $connected -ne 0 ]] && detectMysqlSslOption $sqloptionsuser --password="${DB_password}"; then
        host="${host} ${mysqlsslopt}"
        sqloptionsuser="${sqloptionsuser} ${mysqlsslopt}"
        connected=0
        errorStat 0
        echo " * Note: the master database offers no TLS, so the installer will"
        echo "   connect unencrypted, the same way the FOG web tier already does."
        return 0
    fi
    errorStat $connected
}
# The name this node registers itself under in Storage Management.
#
# It used to be the node's IP address, which made the record's Name useless for
# the one thing it is now needed for. A storage node has no certificate of its
# own until the master issues one, and the master builds that certificate's SAN
# from its record of the node -- reverse DNS first, then the Name. A Name that
# is an IP literal yields "DNS:10.0.0.5", which is not a name: it matches no DNS
# subtree in the Web CA's nameConstraints, so the certificate signs, fails
# `openssl verify` inside fog-sign-node-cert, and the node is told only that
# "a requested name is probably outside the CA's name constraints".
#
# Derived here rather than from ${NET_hostname} alone because ${NET_hostname} is set in
# lib/common/input.sh, and a node install driven from a seeded .fogsettings runs
# the installer's UPGRADE path, which never sources it -- the same gap that
# leaves osid unrecoverable there. `hostname -f` is asked directly so the value
# does not depend on which path got us here.
#
# Anything that cannot serve as a certificate name falls back to the address,
# which is exactly the old behavior. Rejected: an empty value, an IP literal,
# localhost (the RHEL/Rocky minimal default, and identical on every node), and
# anything outside the hostname grammar fog-sign-node-cert enforces.
# The first argument that can actually serve as a certificate/vhost name, or
# nothing at all (status 1) when none of them can.
#
# Rejects, in this order: the empty string; the kernel's literal default
# "(none)", which is what a machine with no hostname configured reports and
# which is NOT empty, so every -z test in the tree waves it through; an IP
# literal; localhost (the RHEL/Rocky minimal default, and identical on every
# node); and anything outside the hostname grammar fog-sign-node-cert enforces,
# which is validhostname().
#
# One helper rather than a list repeated per caller, for the reason
# _defaultServerNames() gives above itself: two sites deriving names separately
# is how a name gets into a certificate that its own CA does not permit. It is
# also the guard the interactive prompt never had -- see lib/common/newinput.sh,
# where an unvalidated answer used to reach an OpenSSL config directly.
#
# Echoes nothing on failure rather than a fallback, because the two callers want
# DIFFERENT fallbacks: a node registration wants the address, and the installer
# wants to ask the admin. Deciding here would take that choice away from both.
_usableHostname() {
    local n
    for n in "$@"; do
        n="${n%.}"
        [[ -z $n ]] && continue
        [[ ${n,,} == "(none)" ]] && continue
        [[ $n =~ ^[0-9]{1,3}(\.[0-9]{1,3}){3}$ ]] && continue
        [[ ${n,,} == localhost || ${n,,} == localhost.* ]] && continue
        [[ $(validhostname "$n") -ne 0 ]] && continue
        echo "$n"
        return 0
    done
    return 1
}
# What this MACHINE says its own name is, filtered through _usableHostname --
# for callers that have to discover the name rather than be told it. Echoes
# nothing when the machine has no usable name of its own, which is the state
# this whole cluster of helpers exists to detect.
#
# hostnamectl is last and is not redundant with the two before it: it answers on
# a systemd host whose name is set but not resolvable, which is exactly when
# `hostname -f` fails. lib/common/input.sh used to reach for it as a fallback
# and never got the chance, because lib/common/newinput.sh overwrote the
# suggestion with a bare `hostname -f` a few lines later.
#
# stderr is discarded on all three. `hostname -f` on a machine with no
# resolvable name prints "hostname: Name or service not known", and the
# installer tees its output to a log admins are asked to attach to bug reports
# -- so an unsuppressed error here reads as the failure rather than as the
# reason for the question that follows it.
_detectedHostname() {
    _usableHostname "$(hostname -f 2>/dev/null)" "$(hostname 2>/dev/null)" "$(hostnamectl --static 2>/dev/null)"
}
_nodeRegistrationName() {
    local n
    n=$(_usableHostname "${NET_hostname}") || n=$(_detectedHostname)
    [[ -n $n ]] && { echo "$n"; return 0; }
    echo "${NET_fog_server_ip}"
}
# The name to put in a certificate, guaranteed to be usable as one.
#
# ${NET_hostname} is normally it, but three OpenSSL call sites interpolate that
# value directly and none of them survives a bad one, so none of them may read
# it raw:
#
#   * the Secure Boot signing request's `subjectAltName = DNS:` -- an empty
#     value makes the whole extension string `DNS:`, which OpenSSL rejects
#     outright ("X509V3_parse_list:invalid null value"). That aborts the
#     installer from inside configureHttpd(), which has already stopped the web
#     server and has not yet reached the createSSLCA() call that restarts it.
#     The admin sees a dead web server after an update and no mention of a
#     hostname anywhere.
#   * the web leaf's `-subj "/CN=..."` -- an empty value is SKIPPED with a
#     warning and status 0, so the leaf is issued with no commonName at all,
#     which then poisons _servedCertName() on every later run.
#   * the platform key's subject, which becomes "FOG Project ()".
#
# Unlike _nodeRegistrationName() this never falls back to an address: its
# callers all need a DNS name specifically, and an IP literal in a DNS SAN is
# the exact failure the nameConstraints note in createSecureBootIntermediateCA()
# describes. fogserver is the floor for the reason _defaultServerNames() gives:
# it is already in every certificate FOG issues.
_certLeafName() {
    local n
    n=$(_usableHostname "${NET_hostname}") || n=$(_detectedHostname)
    [[ -n $n ]] && { echo "$n"; return 0; }
    echo "fogserver"
}
# Make ${NET_hostname} resolve on this box, so the installer's own calls to
# itself can reach it.
#
# Not cosmetic. checkWebTier() dials $(_servedCertName) over HTTPS and exits on
# curl status 6 with "the name ... did not resolve from this server", and the
# schema deploy carries the same hint -- both of which is what an admin sees if
# the name is set but nothing maps it to an address.
#
# Adds nothing when the name already resolves, by any means: a real DNS record
# is the better answer and this must not shadow it. The address is this server's
# own, falling back to 127.0.1.1 -- Debian's convention for a machine's own FQDN
# and the safe choice when NET_fog_server_ip has not been resolved yet.
_ensureHostsEntry() {
    local fqdn="$1" short="$2" target line st=0
    [[ -z $fqdn ]] && return 0
    getent hosts "$fqdn" >/dev/null 2>&1 && return 0
    # normalizeIpAddress() has already collapsed this to a single address by the
    # time the install phase runs, but take the first field regardless -- a
    # multi-address value here would write an unparseable /etc/hosts line.
    target=$(echo ${NET_fog_server_ip} | awk '{print $1}')
    [[ -z $target ]] && target="127.0.1.1"
    line="${target}\t${fqdn}"
    [[ -n $short && $short != "$fqdn" ]] && line="${line} ${short}"
    dots "Adding ${fqdn} to /etc/hosts"
    printf '%b\n' "$line" >> /etc/hosts 2>>$error_log || st=1
    errorStat $st
}
# Give this machine a hostname when it has none.
#
# Runs only when lib/common/newinput.sh found no usable name on the system
# itself -- a working server is never renamed, which is the promise that prompt
# has always made. There is simply nothing to preserve in this case, and nothing
# works until a name exists: see the DNS: note in newinput.sh for what the
# installer does without one.
#
# Deliberately NOT fatal. A container whose UTS namespace belongs to the host
# cannot set a hostname, and refusing to install there would trade one broken
# case for another. FOG still issues its certificate for ${NET_hostname}; the
# admin is told what did not happen and can make the name resolve themselves.
#
# hostnamectl first, /etc/hostname + hostname(1) second: Alpine's OpenRC has no
# hostnamectl at all, and on a systemd host that has it, writing /etc/hostname
# alone would not take effect until reboot.
applySystemHostname() {
    [[ ${hostnameNeedsSystemSet:-0} -eq 1 ]] || return 0
    [[ -z ${NET_hostname} ]] && return 0
    local fqdn short st=1
    fqdn="${NET_hostname}"
    short="${fqdn%%.*}"
    dots "Setting this server's hostname to ${short}"
    if command -v hostnamectl >/dev/null 2>&1; then
        hostnamectl set-hostname "$short" >>$error_log 2>&1 && st=0
    fi
    if [[ $st -ne 0 ]]; then
        st=0
        echo "$short" > /etc/hostname 2>>$error_log || st=1
        hostname "$short" >>$error_log 2>&1 || st=1
    fi
    if [[ $st -ne 0 ]]; then
        echo "Skipped"
        echo "   This server's hostname could not be set -- no privilege, or a"
        echo "   container whose hostname belongs to its host. FOG will still"
        echo "   issue its certificate for '${fqdn}'; make that name resolve to"
        echo "   this server yourself, or the web UI will not validate."
    else
        echo "OK"
    fi
    _ensureHostsEntry "$fqdn" "$short"
}
# Reports one node<->master maintenance POST that did not land, and says how.
#
# GH-575: the two calls below post to this node's own web tier, and what
# actually reaches that web tier is not always what the installer aimed at.
# Three things intercept it, and none of them is a connection failure -- curl
# exits 0 every time:
#
#   * an inline filtering proxy answering for the address (the reporter's was
#     an iboss appliance returning ERR_CONNECT_FAIL as an HTML block page),
#   * this node's own web tier bouncing every request to ?node=schema when it
#     cannot read the master's database -- a 308, which is what a storage node
#     under SELinux did before fog.te grew its mysqld_port_t rule,
#   * anything else in front of the server that answers 200 with markup.
#
# So both a status check and a body check are needed, and they catch different
# halves: a 3xx has no markup in it, and an interception answering 200 has no
# bad status. create_update_node.php outputs nothing at all on success -- both
# of its branches fall off the end after save() -- so a '<' in the body is the
# response of something that is not it.
#
# Not fatal, in either caller. By this point the node's shares, services and
# FTP are configured, and both operations have a normal by-hand recovery in
# Storage Management. Same choice _installNodeWebCert() makes when the master
# declines to issue: say plainly what failed, then carry on.
#
# $1 status, $2 response body, $3 what the caller was trying to do.
_reportNodePostFailure() {
    local status="${1:-000}" body="$2" what="$3"
    echo "Failed"
    echo " * ${WEB_url_proto}://${NET_fog_server_ip}${WEB_root}maintenance/create_update_node.php"
    case $status in
        000)
            # curl's own placeholder when no HTTP response arrived at all --
            # refused, timed out, TLS handshake failed. Not an interception.
            echo "   could not be reached, so ${what}."
            ;;
        *)
            echo "   answered HTTP ${status}, so ${what}."
            ;;
    esac
    case $status in
        3*)
            echo " * A redirect here usually means this node's own web tier cannot"
            echo "   reach the master's database and is bouncing every request to"
            echo "   the schema page -- check for SELinux denials with:"
            echo "     ausearch -m avc -ts recent"
            ;;
    esac
    if [[ $body == *'<'* ]]; then
        echo " * The reply was markup, not this server's answer, so something on"
        echo "   the network answered in its place. A filtering proxy in front of"
        echo "   ${NET_fog_server_ip} is the usual cause; exempt this server from it."
    fi
    echo " * Fix the cause and re-run this installer, or set it by hand under"
    echo "   Storage Management in the web UI."
}
registerStorageNode() {
    # GH-529: this defaulted to "/" while installfog.sh defaults to "/fog/", so
    # the two disagreed about where the app lives whenever webroot arrived
    # unset. Every fallback in this file now matches the installer's.
    [[ -z ${WEB_root} ]] && WEB_root="/fog/"
    dots "Checking if this node is registered"
    # -s: without it curl draws its progress meter straight into the installer's
    # own output, so this step used to print two lines of transfer statistics in
    # the middle of the dotted "Checking if this node is registered....." line.
    #
    # -k stays here, and in the calls below, ON PURPOSE. Every other -k in
    # this installer has been removed; these four -- two in this function, one
    # in updateStorageNodeCredentials, one in _requestNodeCert -- are the
    # genuine chicken-and-egg. On a fresh storage node installfog.sh runs
    # registerStorageNode -> updateStorageNodeCredentials -> _installNodeWebCert
    # -> _installCATrustAnchor in that order, so at this moment the node is
    # serving a certificate nothing has issued yet and holds no anchor for
    # anything: verification cannot succeed, because the very thing that makes
    # it possible is what registering is a precondition of. Using
    # _resolveSelfCacert here would resolve to nothing and then hard-fail.
    #
    # What that costs is bounded and worth stating: an attacker on the path
    # between this node and its own web tier sees the node's storage
    # credentials. It does NOT see the database password -- that never travels
    # this way -- and _requestNodeCert below is separately protected by an HMAC
    # over ${DB_password}, which is the real control on the node<->master trust
    # bootstrap. Closing this properly needs the master to hand a node its
    # anchor out of band, which is a design change, not a flag change.
    storageNodeExists=$(curl -s --noproxy '*' -X POST -d "ip=${NET_fog_server_ip}" -d "fogverified" -kL ${WEB_url_proto}://${NET_fog_server_ip}${WEB_root}maintenance/check_node_exists.php -o -)
    echo "Done"
    if [[ $storageNodeExists != exists ]]; then
        [[ -z $maxClients ]] && maxClients=10
        # See _nodeRegistrationName: registering under a hostname rather than an
        # address is what lets the master put a usable DNS name in this node's
        # certificate. The master still has the last word -- it keeps the address
        # as the Name if this one is unusable or already taken.
        nodeRegName=$(_nodeRegistrationName)
        dots "Node being registered as ${nodeRegName}"
        # A status check and a body check, neither of which this call used to
        # have. It DID have -L, and -L is why the check it already carried
        # could not do its job: curl reports %{http_code} for the LAST transfer
        # it made, so following a 308 to the schema page turned the very
        # failure the check was written for into a green 200. Verified against
        # a local 308 -> 200 server: with -L curl reports 200, without it 308.
        #
        # There is no legitimate redirect to lose by dropping it. The URL is
        # built from ${WEB_url_proto} and ${WEB_root}, both of which this
        # installer set itself, so a 3xx here is always the pathology and never
        # the route. See _reportNodePostFailure for the three of them.
        #
        # The POST field names here are create_update_node.php's own -- they map
        # to storageNode DB columns and are NOT .fogsettings keys. So `sslpath=`,
        # `interface=` and `webroot=` keep their historic spellings even though
        # the VALUES now come from ${PKI_client_cert_dir}, ${NET_interface} and
        # ${WEB_root}. Renaming the field names to match the settings would make
        # the master silently drop them.
        regbody=$(curl -s --noproxy '*' -k -w '\n%{http_code}' -X POST -d "newNode" -d "name=$(echo -n $nodeRegName|base64)" -d "path=$(echo -n ${STORAGE_image_share_path}|base64)" -d "ftppath=$(echo -n ${STORAGE_image_share_path}|base64)" -d "snapinpath=$(echo -n $snapindir|base64)" -d "sslpath=$(echo -n ${PKI_client_cert_dir}|base64)" -d "ip=$(echo -n ${NET_fog_server_ip}|base64)" -d "maxClients=$(echo -n $maxClients|base64)" -d "user=$(echo -n ${SVC_user}|base64)" --data-urlencode "pass=$(echo -n ${SVC_password}|base64)" -d "interface=$(echo -n ${NET_interface}|base64)" -d "bandwidth=1" -d "webroot=$(echo -n ${WEB_root}|base64)" -d "fogverified" ${WEB_url_proto}://${NET_fog_server_ip}${WEB_root}maintenance/create_update_node.php)
        regstatus=${regbody##*$'\n'}
        regbody=${regbody%$'\n'*}
        case $regstatus in
            2*)
                if [[ $regbody == *'<'* ]]; then
                    _reportNodePostFailure "$regstatus" "$regbody" \
                        "this node did not register itself with the master and will not appear in Storage Management"
                else
                    echo "Done"
                fi
                ;;
            *)
                _reportNodePostFailure "$regstatus" "$regbody" \
                    "this node did not register itself with the master and will not appear in Storage Management"
                ;;
        esac
    else
        echo " * Node is registered"
    fi
}
updateStorageNodeCredentials() {
    [[ -z ${WEB_root} ]] && WEB_root="/fog/"   # see registerStorageNode, GH-529
    dots "Ensuring node username and passwords match"
    # -k on purpose -- see registerStorageNode. This is called from the node
    # path before any anchor exists, and from the master path after one does;
    # the shared function has to work in the earlier of the two.
    # GH-575, and the half of that report the fix for registerStorageNode above
    # never reached even though the two share an endpoint. This call had no -o,
    # so whatever answered was written STRAIGHT to the installer's stdout, in
    # the middle of the dotted line -- which is why the reporter's console read
    #
    #   Node being registered.....................<!doctype html>
    #
    # followed by a proxy's block page. Then it echoed "Done" regardless,
    # because nothing looked at the status or at what came back.
    credbody=$(curl -s --noproxy '*' -k -w '\n%{http_code}' -X POST -d "nodePass" -d "ip=$(echo -n ${NET_fog_server_ip}|base64)" -d "user=$(echo -n ${SVC_user}|base64)" --data-urlencode "pass=$(echo -n ${SVC_password}|base64)" -d "fogverified" ${WEB_url_proto}://${NET_fog_server_ip}${WEB_root}maintenance/create_update_node.php)
    credstatus=${credbody##*$'\n'}
    credbody=${credbody%$'\n'*}
    case $credstatus in
        2*)
            if [[ $credbody == *'<'* ]]; then
                _reportNodePostFailure "$credstatus" "$credbody" \
                    "this node's storage credentials were not written to the master"
            else
                echo "Done"
            fi
            ;;
        *)
            _reportNodePostFailure "$credstatus" "$credbody" \
                "this node's storage credentials were not written to the master"
            ;;
    esac
}
# Mirrors fog_git_path/fog_update_channel/extraServerNames/servicelogs into
# globalSettings so the GUI can show them without SSH. Like fogprogramdir's
# mirror into /etc/fog/fog.conf (GH-850), these are RECORDS, not controls:
# .fogsettings stays the source of truth, and the next installfog.sh/
# updatefog.sh run overwrites whatever an admin may have hand-edited here
# through the generic Settings tab.
# ADR 0023 Decision 7: a bounded retention default is applied to NEW installs
# and never silently to an upgrade.
#
# It lives here rather than in commons/schema.php because a schema step cannot
# tell the two apart -- it runs identically on both -- which is what step 347's
# own comment says. The installer can: .fogsettings either existed before this
# run or it did not.
#
# Two conditions, not one. $priorInstall alone would still fire on a re-install
# over a database that has been collecting login records for years, which is
# exactly the "nasty surprise" the decision rules out: the administrator never
# chose to hold this data OR to delete it, and some of them are legally
# required to retain it. An empty userTracking table is the second half, and
# together they mean nothing can be deleted by this default that anybody had.
#
# The UPDATE is conditioned on the current value being '0' as well, so a
# re-run cannot walk over a window an admin has since chosen.
#
# 365 days is a judgment, not a derived figure: long enough to answer "who was
# on this machine last year", short enough that the table does not grow without
# bound, and a round number to reason about. Upgrades stay at 0 and get a
# dashboard notice instead -- see DashboardPage::_userTrackingRetentionNotice().
applyNewInstallDefaults() {
    [[ $priorInstall -eq 1 ]] && return 0
    local trackedRows
    trackedRows=$(mysql $sqloptionsuser --password="${DB_password}" \
        --skip-column-names --batch \
        --execute="SELECT COUNT(*) FROM userTracking" ${DB_name} 2>>$error_log)
    [[ -z $trackedRows ]] && return 0
    [[ $trackedRows -ne 0 ]] && return 0
    dots "Setting the new-install retention window for host login records"
    mysql $sqloptionsuser --password="${DB_password}" --execute="UPDATE globalSettings SET settingValue='365' WHERE settingKey='FOG_USERTRACKING_RETENTION_DAYS' AND settingValue='0'" ${DB_name} >>$error_log 2>&1
    errorStat $?
}
recordGitUpdateSettings() {
    dots "Recording fog_git_path/update channel/extra server names"
    mysql $sqloptionsuser --password="${DB_password}" --execute="INSERT INTO globalSettings (settingKey, settingDesc, settingValue, settingCategory) VALUES ('FOG_GIT_PATH', 'Filesystem path of the FOG git checkout on this server. Recorded automatically by installfog.sh/updatefog.sh -- editing it here has no effect on the next update.', \"${FOG_git_path}\", 'FOG Update') ON DUPLICATE KEY UPDATE settingValue=\"${FOG_git_path}\"" ${DB_name} >>$error_log 2>&1
    # settingDesc is refreshed too, unlike its two neighbors. The channel
    # vocabulary changed (GH-1279), and ON DUPLICATE KEY UPDATE touching only
    # settingValue would leave every server installed before that change showing
    # "stable, staging, or dev" in the FOG Settings UI forever -- which is the
    # documentation contradiction the rename exists to end, preserved in the one
    # place an admin is most likely to read it.
    mysql $sqloptionsuser --password="${DB_password}" --execute="INSERT INTO globalSettings (settingKey, settingDesc, settingValue, settingCategory) VALUES ('FOG_UPDATE_CHANNEL', 'Update channel this server tracks: stable, patches, or beta.', \"${FOG_update_channel}\", 'FOG Update') ON DUPLICATE KEY UPDATE settingDesc=VALUES(settingDesc), settingValue=\"${FOG_update_channel}\"" ${DB_name} >>$error_log 2>&1
    mysql $sqloptionsuser --password="${DB_password}" --execute="INSERT INTO globalSettings (settingKey, settingDesc, settingValue, settingCategory) VALUES ('FOG_EXTRA_SERVER_NAMES', 'Extra vhost/certificate name(s) this server answers to, beyond the primary hostname and detected IPs. Set via --extra-server-name -- editing it here has no effect on the next update.', \"${PKI_san_dns_names}\", 'FOG Update') ON DUPLICATE KEY UPDATE settingValue=\"${PKI_san_dns_names}\"" ${DB_name} >>$error_log 2>&1
    # SERVICE_LOG_PATH used to be an independent control, and nothing kept it
    # in step with where the install actually put its logs. Relocating
    # $fogprogramdir (GH-850) moved $servicelogs, FOG_LOG_DIR and the
    # /var/log/fog link with it and left this row saying /opt/fog/log -- so the
    # daemons wrote to one directory while the log viewer read another, with no
    # error anywhere. Recording it makes the two agree by construction. The
    # daemons take FOG_LOG_DIR now, so this really is a record.
    mysql $sqloptionsuser --password="${DB_password}" --execute="INSERT INTO globalSettings (settingKey, settingDesc, settingValue, settingCategory) VALUES ('SERVICE_LOG_PATH', 'Where the linux side fog services write their logs. Recorded automatically by installfog.sh from the install path -- editing it here has no effect. To move the logs, re-run the installer with a different base path.', \"${servicelogs%/}/\", 'FOG Linux Service Logs') ON DUPLICATE KEY UPDATE settingValue=\"${servicelogs%/}/\", settingDesc=VALUES(settingDesc)" ${DB_name} >>$error_log 2>&1
    errorStat $?
}
# Keeps FOG_WEB_HOST to something the netboot certificate can prove.
#
# A boot is two hops with two host sources. default.ipxe names the server for
# the fetch of boot.php; IpxeBootMenu builds everything after it -- the iPXE menu,
# the kernel's web= argument, the Secure Boot MOK.der and mmx64.efi -- from this
# row. The row is seeded from ${NET_fog_server_ip} on a fresh schema deploy and was then
# never written again, so a fresh public-cert install pointed all of those at
# https://<address>/ and nothing compared the two. Recording it makes them agree
# by construction, the same argument as SERVICE_LOG_PATH above.
#
# Under HTTPS netboot ONLY, and that guard is load-bearing. On a plain-HTTP
# install FOG_WEB_HOST is a name plenty of admins set deliberately and no
# certificate has to match it, so rewriting it there would be a regression
# dressed as a fix.
#
# The same argument applies under HTTPS to a value the certificate DOES carry,
# which is why this corrects rather than overwrites -- see the check below. An
# address is a legitimate answer there: iPXE verifies one against an iPAddress
# SAN exactly as it verifies a name, so "netboot needs a name" was never true.
# _resolveNetbootHost() has the detail.
recordNetbootWebHost() {
    local currentwebhost=""
    [[ ${BOOT_url_proto} == https ]] || return 0
    # An existing value the served certificate ALREADY serves is left alone.
    #
    # This row is not only netboot's. It is the canonical address of the
    # server: ClientManagement hands it to every FOG client, FOGService,
    # PingHosts and FOGURLRequests identify themselves by it, and a plugin
    # building a browser-facing absolute URL -- OIDC's start, callback and
    # post-logout among them -- resolves against it. Overwriting it therefore
    # moved an admin's whole install onto whichever name the certificate
    # happened to lead with, silently, on every run. It is how a server
    # deliberately addressed as https://<ip>/ ended up bouncing its
    # administrators to a DNS name at sign-in.
    #
    # What this function is FOR is narrower: making sure the boot URLs iPXE
    # fetches after boot.php name something the certificate can prove. A value
    # that already satisfies that needs no correction, whether it is a name or
    # an address -- so check it, and only overwrite when it fails.
    #
    # Fail-safe in the direction that matters: a value that cannot be read (no
    # globalSettings yet on a first install, a query that errors) is empty, so
    # this falls through and records the certificate name exactly as before.
    #
    # The keep-or-correct decision itself lives in _resolveNetbootHost, not
    # here. It used to be here, and that reopened the defect ADR 0018 closed:
    # configureDefaultiPXEfile had already taken the certificate's leading
    # name for default.ipxe, then this function kept the row as the address the
    # certificate also carried, and the two hops of a boot disagreed again --
    # https://<name>/ for boot.php, https://<address>/ for everything after it.
    # On a PXE segment with no DNS for that name, iPXE stopped at the first hop
    # with "DNS name does not exist" while every URL after it would have worked.
    # So the resolver asks the row first and both callers read its one answer.
    currentwebhost=$(_currentWebHostRow)
    _resolveNetbootHost || return 1
    [[ -n $netboothost ]] || return 0
    if [[ $netboothost == "$currentwebhost" ]]; then
        dots "Keeping FOG_WEB_HOST as $currentwebhost"
        errorStat 0
        echo "   The served certificate carries it, so netboot can prove it."
        return 0
    fi
    dots "Pointing FOG_WEB_HOST at the netboot certificate name"
    mysql $sqloptionsuser --password="${DB_password}" --execute="INSERT INTO globalSettings (settingKey, settingDesc, settingValue, settingCategory) VALUES ('FOG_WEB_HOST', 'This setting defines the hostname or ip address of the web server used with fog. Under HTTPS netboot it must be a name or address the served certificate carries, because every boot URL iPXE fetches after boot.php is built from it. An edit the certificate vouches for is kept; one it does not is replaced by the certificate name on the next install.', \"$netboothost\", 'Web Server') ON DUPLICATE KEY UPDATE settingValue=\"$netboothost\", settingDesc=VALUES(settingDesc)" ${DB_name} >>$error_log 2>&1
    errorStat $?
    echo "   FOG_WEB_HOST is now $netboothost. Every boot URL after boot.php is"
    echo "   built from it, and HTTPS netboot needs them to match the certificate."
    # Say what else just moved. Anything a plugin derives from this row derives
    # a NEW value from here on, and the one that fails hardest is an external
    # identity provider: a redirect URI is computed from FOG_WEB_HOST on every
    # request and never stored, so it is now a string the provider has not been
    # told about -- and a provider rejects an unregistered redirect URI outright.
    # Sign-in breaks at the next attempt with nothing on this server explaining
    # why, which is a bad thing to learn from a locked-out admin rather than
    # from the install that caused it.
    if [[ -n $currentwebhost && $currentwebhost != "$netboothost" ]]; then
        echo
        echo "   It was $currentwebhost. Anything registered elsewhere against that"
        echo "   value has to be updated to match -- an external identity provider's"
        echo "   redirect URI in particular, which FOG derives from this setting and"
        echo "   a provider refuses if it was not registered."
        echo
    fi
}
backupDB() {
    # ---------------------------------------------------------
    # External Unprivileged Database Implementation
    # Skip database backup for external databases
    # ---------------------------------------------------------
    if [[ ${DB_external} == yes ]]; then
        echo " * Skipping database backup (External Database Mode)"
        return 0
    fi
    # ---------------------------------------------------------
    dots "Backing up database"
    # GH-314: the status checked below used to be whichever command ran last,
    # which made this claim success in two ways it should not have. With no
    # prior install there is no BACKUP directory, so the `if` was skipped and
    # the status read was the failed directory test. And when the curl DID run
    # and failed, the status read was jq's -- jq exits 0 on empty input, having
    # written a zero-byte file. That is the worse half: an upgrade would print
    # "Backing up database....Done" over an empty pre-upgrade dump.
    #
    # dbbackupstat is only set where a backup was actually attempted, so a fresh
    # install skips the step instead of reporting a failure it did not have.
    local dbbackupstat=0
    local dbbackupfile=""
    # Declared here, not where it is set: the block that sets it is skipped
    # entirely on a fresh install, and the prompt at the bottom reads it.
    local dbwhy=""
    # Ask the database whether there is anything to dump, rather than asking
    # the filesystem whether configureHttpd happened to leave a
    # fog_web_<ver>.BACKUP behind. That directory was only ever a proxy for
    # "this is an upgrade", and it is a broken one: configureHttpd removes
    # ${WEB_docroot}fog when it is a SYMLINK and then tests `-d $webdirdest` --
    # the same path -- to decide whether to make the backup, so on any
    # install whose web root is a symlink the directory never appears and the
    # pre-upgrade dump was silently skipped on every run.
    #
    # SHOW TABLES is also the honest question. The dump has nothing to do with
    # the web tree, and a leftover .fogsettings pointing at a database that
    # does not exist yet would make an $doupdate-based gate report a failure
    # it did not have. configureMySql has run by here, so $sqloptionsuser and
    # ${DB_password} are settled; a fresh install has no tables and still skips.
    local dbhastables=""
    dbhastables=$(mysql $sqloptionsuser --password="${DB_password}" --skip-column-names --execute="SHOW TABLES" ${DB_name} 2>>$error_log | head -n 1)
    if [[ -n $dbhastables ]]; then
        [[ ! -d ${DB_backup_path}/fogDBbackups ]] && mkdir -p ${DB_backup_path}/fogDBbackups >>$error_log 2>&1
        local selfName=$(_servedCertName)
        url="${WEB_url_proto}://${selfName}${WEB_root}maintenance/backup_db.php"
        # %H, not %I: %I is the 12-hour clock with no AM/PM marker, so an
        # update run at 05:57 and one at 17:57 on the same day produced the
        # same filename and the second silently overwrote the first.
        dbbackupfile="${DB_backup_path}/fogDBbackups/fog_sql_${version}_$(date +"%Y%m%d_%H%M%S").sql"
        # --max-redirs 0 rather than -L, and the status captured rather than
        # left to -f. -f fails only on 4xx and 5xx, so a REDIRECT used to
        # reach the guards below as a clean exit 0 with an empty body, and
        # nothing anywhere named it: a 3xx is not an error to the web server
        # either, so it lands in access_log and there is nothing to grep for.
        # That is how GH-1147 presented -- backup_db.php answering
        # "308 -> ?node=schema" because the schema was stale, and this step
        # able to say only "Failed". Following the redirect instead would
        # trade an empty body for an HTML one that jq chokes on just as
        # silently; an unfollowed redirect is never a valid dump, so it is
        # named as its own failure.
        # Body to a temporary file rather than straight down a pipe to jq, so
        # that curl's status, the HTTP status and jq's status are three
        # separate answerable questions. Down a pipe only the first and last
        # are available, and PIPESTATUS cannot distinguish the case that
        # actually happened below.
        local dbraw="${dbbackupfile}.raw"
        local dbcurlerr="${dbbackupfile}.curlerr"
        local dbhttpcode=""
        local dbcurlstat=0
        local dbjqstat=0
        # Verified, not -k: this is an HTTPS call to this server, and
        # _resolveSelfCacert names the anchor for the chain it is serving.
        _resolveSelfCacert
        # --noproxy '*' because this is a call to THIS server. With http_proxy
        # or https_proxy set in the installer's environment -- ordinary on a
        # server behind corporate egress filtering -- curl sends even a
        # self-addressed request to the proxy, which either cannot route back to
        # the FOG server or refuses to CONNECT to it. Observed as
        # `curl: (56) CONNECT tunnel failed, response 502`, i.e. the backup step
        # failing for a reason that has nothing to do with the database. The two
        # other self-calls in this file (the serves-FOG probe and the schema
        # POST) already pass it; this one was missed.
        #
        # -sS, not -s. Plain -s suppresses curl's error message as well as the
        # progress meter, so $dbcurlerr came back EMPTY and the "curl exited N"
        # branch below reported a bare exit code with no reason -- defeating the
        # diagnostics the rest of this block exists to produce.
        dbhttpcode=$(curl -sS --noproxy '*' "${selfCacertOpts[@]}" --max-redirs 0 -w '%{http_code}' -o "$dbraw" "$url" 2>"$dbcurlerr")
        dbcurlstat=$?
        # Only when curl actually produced a body. Running jq unconditionally
        # meant that on any curl failure the redirection `< "$dbraw"` failed
        # too, and bash printed "No such file or directory" to the installer's
        # stderr -- landing in the middle of the progress line, before the
        # guards below had a chance to say what really happened.
        if [[ -f $dbraw ]]; then
            jq -r '. | ._content' < "$dbraw" > "$dbbackupfile" 2>>"$dbcurlerr"
            dbjqstat=$?
        else
            dbjqstat=1
        fi
        # Ordered most specific first, so the message names the earliest
        # thing that went wrong rather than its downstream effect. A redirect
        # is called out on its own: -f fails only on 4xx and 5xx, so a 3xx
        # used to arrive here as a clean exit 0 with an empty body, and
        # nothing anywhere named it -- a 3xx is not an error to the web
        # server either, so it lands in access_log with nothing to grep for.
        # That is exactly how GH-1147 presented: backup_db.php answering
        # "308 -> ?node=schema" because the schema was stale, and this step
        # able to say only "Failed".
        #
        # --max-redirs 0 rather than -L on purpose. Following the bounce
        # would trade an empty body for an HTML one that jq chokes on just as
        # silently; an unfollowed redirect is never a valid dump, so it is
        # named as its own failure.
        #
        # A dump that is empty or the literal "null" is jq faithfully
        # reporting that the response had no _content, which is a different
        # fault from jq failing to parse at all -- so they read differently.
        dbwhy=""
        if [[ $dbcurlstat -ne 0 ]]; then
            dbwhy="curl exited $dbcurlstat requesting $url: $(head -c 200 "$dbcurlerr" 2>/dev/null)"
        elif [[ $dbhttpcode -ge 300 ]]; then
            dbwhy="HTTP $dbhttpcode from $url -- a redirect or an error, not a dump"
        elif [[ $dbjqstat -ne 0 ]]; then
            dbwhy="HTTP $dbhttpcode but the response is not the expected JSON: $(head -c 200 "$dbraw" 2>/dev/null)"
        elif [[ ! -s $dbbackupfile ]]; then
            dbwhy="HTTP $dbhttpcode but the dump is empty -- no ._content in the response"
        elif [[ $(head -c 4 "$dbbackupfile" 2>/dev/null) == null ]]; then
            dbwhy="HTTP $dbhttpcode but ._content was null"
        fi
        if [[ -n $dbwhy ]]; then
            dbbackupstat=1
            # Written only on the failure path; a successful backup stays as
            # quiet as it has always been.
            echo "Database backup failed: $dbwhy" >>$error_log 2>&1
        fi
        rm -f "$dbraw" "$dbcurlerr" >/dev/null 2>&1
    fi
    if [[ -z $dbbackupfile ]]; then
        # No prior install to back up. Saying "Done" over a step that never ran
        # is the same misreport this function was just fixed for.
        echo "Skipped"
    elif [[ $dbbackupstat -ne 0 ]]; then
        echo "Failed"
        if [[ -z $autoaccept ]]; then
            echo
            echo "    We were not able to backup the current database!"
            # The reason, on screen and not only in the log. This prompt asks
            # an administrator to approve continuing an upgrade with no dump
            # to roll back to; it should say what went wrong before it asks.
            [[ -n $dbwhy ]] && echo "    Reason: $dbwhy"
            echo
            echo "    Proceeding means this upgrade has no pre-upgrade dump to"
            echo "    restore from. Press [Enter] to proceed anyway, or Ctrl+C"
            echo "    to stop the installer."
            read
        fi
    else
        # Published for offerRevert(). A failed install AFTER updateDB() cannot
        # be undone by moving the checkout back -- the schema has already gone
        # forward -- and this is the only thing that can undo it. bin/revertfog.sh
        # deliberately refuses a 1.6 dump, so for an intra-1.6 failure a manual
        # restore of this file is the way back, and the message has to be able
        # to name it.
        fogPreUpgradeDump="$dbbackupfile"
        echo "Done"
    fi
}
# The schema version before updateDB() runs, so offerRevert() can tell a
# failure that migrated the database from one that did not. Called from
# installfog.sh immediately before the migration; a value here that differs
# from the one in the database at exit means the schema moved.
recordPreUpgradeSchema() {
    fogPreUpgradeSchema=$(schemaVersionInDB 2>/dev/null)
    return 0
}
# Prove the web tier actually renders a page before we trust anything that
# talks to it. A PHP fatal in the boot chain returns an empty (or truncated)
# 500, which reads as a blank white page in a browser and which every other
# check in this installer happily treats as success -- that is how an install
# can print "Setup complete" over a completely dead site.
# Refs https://forums.fogproject.org/topic/18204/
checkWebTier() {
    dots "Checking web server serves FOG"
    # No token on this probe. It is a pure liveness check -- 'schema' is an
    # unauthenticated node and a plain GET renders regardless -- and a token in
    # a query string lands in the web server access log and, on failure, in the
    # installer's tee'd stdout. The deploy itself uses the header channel.
    local selfName=$(_servedCertName)
    local probeUrl="${WEB_url_proto}://${selfName}${WEB_root}management/index.php?node=schema"
    local probeBody=$(mktemp)
    # We care whether bytes came back at all, not just about the status code,
    # because a pre-output fatal loses exactly that. %{http_code} is captured
    # alongside so a 500 can be named as a 500 rather than as "curl exit 22".
    _resolveSelfCacert
    local probeCode=""
    probeCode=$(curl -sS "${selfCacertOpts[@]}" --noproxy '*' -m 30 -fL -w '%{http_code}' -o "$probeBody" "$probeUrl" 2>>$error_log)
    local probeStat=$?
    local probeSize=$(stat -c %s "$probeBody" 2>/dev/null)
    [[ -z $probeSize ]] && probeSize=0
    rm -f "$probeBody"
    if [[ $probeStat -eq 0 && $probeSize -gt 0 ]]; then
        echo "Done"
        return 0
    fi
    # Say which failure this was. Every outcome used to print the same "almost
    # always a PHP fatal" advice, including a TLS handshake that was rejected
    # before any response existed to be empty -- which reads as a dead site and
    # sends an administrator to the PHP error log for a trust problem.
    local reason="" advice="plain"
    case $probeStat in
        0)
            reason="the server answered ${probeCode:-200} with an empty body"
            advice="php"
            ;;
        22)
            reason="the server answered HTTP ${probeCode:-4xx/5xx}"
            advice="php"
            ;;
        35|51|60|77)
            reason="TLS verification failed (curl ${probeStat})"
            advice="tls"
            ;;
        6)
            reason="the name ${selfName} did not resolve from this server"
            ;;
        7)
            reason="the connection to ${selfName} was refused"
            ;;
        28)
            reason="the request timed out after 30s"
            ;;
        *)
            reason="curl exited ${probeStat}"
            ;;
    esac
    # A rejected certificate says nothing about whether the web tier renders.
    # Prove that separately before deciding this is fatal: if the same URL
    # answers with content when verification is skipped, the site is up and
    # what failed is trust, which must not abort an install that was otherwise
    # fine. The retry is only ever a DIAGNOSTIC -- it carries no token, and the
    # schema deploy in updateDB still refuses to degrade this way.
    if [[ $advice == tls ]]; then
        local retryBody=$(mktemp)
        curl -sS -k --noproxy '*' -m 30 -fL -o "$retryBody" "$probeUrl" >>$error_log 2>&1
        local retryStat=$?
        local retrySize=$(stat -c %s "$retryBody" 2>/dev/null)
        [[ -z $retrySize ]] && retrySize=0
        rm -f "$retryBody"
        if [[ $retryStat -eq 0 && $retrySize -gt 0 ]]; then
            echo "Warning"
            echo
            echo "   The web server IS serving FOG at:"
            echo "     $probeUrl"
            echo "   but this host cannot verify the certificate it presents."
            echo
            echo "   ${reason}. The page rendered when verification was skipped,"
            echo "   so this is a trust problem and not a broken site -- the"
            echo "   install continues."
            echo
            echo "   Likely causes:"
            echo "     - the certificate is managed outside FOG (acme.sh, certbot)"
            echo "       and FOG has not been pointed at it: make"
            echo "       ${PKI_web_vhost_cert:-the web vhost cert path} resolve to your"
            echo "       certificate (a symlink is enough)"
            echo "     - the served chain does not terminate in the anchor FOG"
            echo "       resolved (${PKI_web_trust_chain:-the vhost})"
            echo
            echo "   Note the schema deploy verifies strictly and will NOT"
            echo "   continue past this -- it carries an install token."
            echo
            return 0
        fi
    fi
    echo "Failed!"
    echo
    echo "   The web server did not return a usable page for:"
    echo "     $probeUrl"
    echo "   (curl exit ${probeStat}, ${probeSize} bytes of body)"
    echo "   Reason: ${reason}."
    echo
    if [[ $advice == php ]]; then
        echo "   An empty or truncated response with a 500 is almost always a PHP"
        echo "   fatal in the FOG boot chain rather than a database problem. In a"
        echo "   browser this looks like a blank white page. Check your web"
        echo "   server's error log."
        echo "   PHP in use: $(php -v 2>/dev/null | head -1)"
    elif [[ $advice == tls ]]; then
        echo "   The certificate was rejected AND the page did not render with"
        echo "   verification skipped, so the web tier is not answering usefully"
        echo "   on this address either. Check the web server is running and that"
        echo "   ${selfName} resolves to this server from this server."
    else
        echo "   Check the web server is running and reachable at ${selfName}"
        echo "   from this host. Full error in $error_log"
    fi
    echo
    [[ -z $exitFail ]] && exit 1
    return 1
}
# Read the schema version straight out of MySQL. Echoes the number, or nothing
# when the probe cannot run (external database mode, credentials we do not
# hold, table not created yet). Callers must treat empty as "unknown" and never
# as zero.
schemaVersionInDB() {
    [[ ${DB_external} == yes ]] && return 0
    [[ -z $sqloptionsuser ]] && return 0
    mysql $sqloptionsuser --password="${DB_password}" -N -B --execute="SELECT vValue FROM \`${DB_name}\`.\`schemaVersion\` WHERE vID=1" 2>/dev/null | tail -1
}
# How many FOG users exist, i.e. is this an established install or a fresh one.
# Echoes the count, or NOTHING when the probe cannot run. Empty means unknown
# and must not be read as zero: guessing "fresh" would print a live token for
# an established install, and guessing "established" would leave a genuinely
# fresh install with no way to bootstrap. Callers show both instructions.
fogUserCount() {
    if [[ ${DB_external} == yes ]]; then
        [[ -z ${DB_host} || -z ${DB_user} ]] && return 0
        mysql --host="${DB_host}" --user="${DB_user}" --password="${DB_password}" $mysqlsslopt -N -B --execute="SELECT COUNT(*) FROM \`${DB_name}\`.\`users\`" 2>/dev/null | tail -1
        return 0
    fi
    [[ -z $sqloptionsuser ]] && return 0
    mysql $sqloptionsuser --password="${DB_password}" -N -B --execute="SELECT COUNT(*) FROM \`${DB_name}\`.\`users\`" 2>/dev/null | tail -1
}
# Confirm the deploy actually landed in the database. Neither update path used
# to prove anything: the automatic branch only checked curl's exit status, and
# the manual branch accepted any keypress -- so a failed schema update still
# marched on to "Setup complete".
verifySchemaDeploy() {
    # Compared against the STEP COUNT, not FOG_SCHEMA.
    #
    # vValue is a count of applied elements of $this->schema -- the updater
    # writes $version + 1 and stops at the last index -- so it tops out at the
    # number of steps in commons/schema.php. FOG_SCHEMA is a deliberately
    # higher coarse gate that decides whether to SEND an admin to the updater;
    # it has sat exactly 35 above the element count since at least 2024, on
    # this branch and on dev-branch alike (279 - 244).
    #
    # Comparing the two therefore could never pass. A correct, fully updated
    # fresh install lands on the element count and was told the release
    # "requires" 35 more, then the installer exited 1. The only value that ever
    # satisfied it was a vValue sitting at or above FOG_SCHEMA -- which is what
    # a hand-set value or a 1.5 carried count looks like, and which is itself
    # the "permanently up to date, never runs another indexed step" state that
    # Schema::seedRequiredRows() exists to repair.
    #
    # count($this->schema) <= mySchema is the exact test the updater uses to
    # decide it has nothing left to do, so it is the right thing to verify.
    local expected=$(grep -c '^\$this->schema\[\] = ' $webdirdest/commons/schema.php 2>/dev/null)
    # A zero count means the pattern stopped matching the file's formatting,
    # not that there are no steps. Treat it as unknown and skip verification,
    # the same rule schemaVersionInDB() follows -- asserting a bogus threshold
    # would either fail every install or pass every broken one.
    [[ -z $expected || $expected -lt 1 ]] && expected=""
    local deployed=$(schemaVersionInDB)
    if [[ -z $expected || -z $deployed ]]; then
        echo " * Skipping schema verification (unable to read the schema version)"
        return 0
    fi
    dots "Verifying database schema"
    if [[ $deployed -ge $expected ]]; then
        echo "Done"
        return 0
    fi
    echo "Failed!"
    echo
    echo "   The database schema is still at version ${deployed}; this release"
    echo "   requires ${expected}. The update did not complete, so FOG will not"
    echo "   work correctly."
    echo
    echo "   Re-run this installer once the cause is resolved."
    echo
    [[ -z $exitFail ]] && exit 1
    return 1
}
updateDB() {
    # Every URL below -- the POST, the failure messages and the browser
    # instructions -- addresses the server by the name its certificate
    # carries. Dialling ${NET_fog_server_ip} verified only by luck: FOG's own leaf
    # happens to carry IP SANs, and a leaf from a public CA cannot, because
    # no public CA issues for an address.
    local selfName=$(_servedCertName)
    # This substitution has to happen on BOTH paths. It used to sit inside the
    # [Yy] branch, and dbupdate is set in exactly one place (bin/installfog.sh,
    # under -y), so every interactive install baked the literal '/images/'
    # default into commons/schema.php instead of ${STORAGE_image_share_path}.
    local replace='s/[]"\/$&*.^|[]/\\&/g'
    local escstorageLocation=$(echo ${STORAGE_image_share_path} | sed -e $replace)
    sed -i -e "s/'\/images\/'/'$escstorageLocation'/g" $webdirdest/commons/schema.php
    # Same root cause, the other half: with dbupdate unset every interactive
    # install fell through to the manual browser path, which verifies nothing
    # and hands the install token out on stdout. Default to the automatic path
    # and make opting out deliberate. backupDB has already run by this point,
    # so the historical reason to pause here is covered.
    if [[ -z $dbupdate ]]; then
        if [[ -n $autoaccept || ! -t 0 ]]; then
            dbupdate="yes"
        else
            echo
            read -p " * Install/update the FOG database schema now? (Y/n) " dbupdate
            [[ -z $dbupdate ]] && dbupdate="yes"
        fi
    fi
    case $dbupdate in
        [Yy]|[Yy][Ee][Ss])
            dots "Updating Database"
            # Verified. This request carries X-Fog-Install-Token, which
            # grants a schema deploy on a server that has no users yet; -k
            # handed that to whoever answered on ${NET_fog_server_ip}.
            _resolveSelfCacert
            curl -X POST -H "X-Fog-Install-Token: ${installToken}" -d "schemaupdate=1" --noproxy '*' "${selfCacertOpts[@]}" -fsL ${WEB_url_proto}://${selfName}${WEB_root}management/index.php?node=schema -o - >>$error_log 2>&1
            local schemarc=$?
            # errorStat tails $error_log, so curl's own "SSL certificate
            # problem" line is already visible -- but it does not say what to
            # do about it, and this is the one place where verifying instead
            # of passing -k can stop an upgrade that used to finish. Name the
            # two installs it can happen on before handing over.
            case $schemarc in
                35|51|60|77)
                    echo "Failed!"
                    echo
                    echo " * TLS verification failed talking to this server's own web tier at"
                    echo "   ${WEB_url_proto}://${selfName}${WEB_root} -- so the schema was NOT deployed."
                    echo " * This step used to skip verification, which handed the schema"
                    echo "   install token to whatever answered on that address. It no longer"
                    echo "   does, so a certificate this host cannot verify now stops here."
                    echo " * The address is no longer the likely cause -- this call now"
                    echo "   dials ${selfName}, taken from the served certificate's own CN,"
                    echo "   so it is a name that certificate covers by construction."
                    echo " * Two causes remain, both fixable:"
                    echo "     - the served chain does not terminate in the anchor FOG"
                    echo "       resolved (${PKI_web_trust_chain:-the vhost}), i.e. the certificate was"
                    echo "       replaced without telling the installer -- re-run with"
                    echo "       --external-ca, or point ${PKI_web_vhost_cert:-the vhost cert path}"
                    echo "       at the certificate you are actually serving"
                    echo "     - ${selfName} does not resolve to this server FROM this server"
                    echo "       (check /etc/hosts and this host's resolver, not just DNS)"
                    echo " * Full error in $error_log"
                    echo
                    tail -n 5 $error_log
                    # Exit rather than fall through to errorStat: the schema
                    # not deploying has always been fatal here, and errorStat
                    # would reprint a generic banner over a message that has
                    # already said more than it can.
                    exit $schemarc
                    ;;
            esac
            errorStat $schemarc
            ;;
        *)
            echo
            echo " * You still need to install/update your database schema."
            echo " * This can be done by opening a web browser and going to:"
            echo
            # On an established install the URL token is refused (it is gated on
            # there being no users yet) and is not needed -- logging in as an
            # admin is the credential. Only a fresh install gets a secret on
            # screen. Failing toward the tokenized URL when the probe cannot run
            # is safe: it still requires possession of the token.
            local userCount=$(fogUserCount)
            if [[ -z $userCount || $userCount -gt 0 ]]; then
                echo "   ${WEB_url_proto}://${selfName}${WEB_root}management/index.php?node=schema"
                echo
                echo " * Log in as a FOG administrator there, then click"
                echo "   Install/Update now."
                echo
                # The login above is not always usable on an upgrade: it reads
                # the schema this deploy is about to create, so a model old
                # enough can fail it outright (GH-927). The token channel now
                # covers that case, but the secret is deliberately NOT echoed
                # here -- this text lands in the install log, and users paste
                # those into forum threads verbatim. Point at the file instead;
                # anyone who can read it is already root.
                echo " * If you cannot log in there, the schema can be deployed"
                echo "   directly using the token in:"
                echo "     ${webdirdest}commons/config.class.php"
                echo "   (the FOG_SCHEMA_INSTALL_TOKEN line), with:"
                echo "     curl -X POST -H \"X-Fog-Install-Token: <token>\" \\"
                echo "       -d \"schemaupdate=1\" \\"
                echo "       \"${WEB_url_proto}://${selfName}${WEB_root}management/index.php?node=schema\""
            fi
            if [[ -z $userCount || $userCount -eq 0 ]]; then
                # Only a userless install can use the token, and only in a URL
                # that has to be typed once. Shown alongside the login
                # instruction when the user probe could not run, so we neither
                # publish a secret needlessly nor strand a fresh install.
                [[ -z $userCount ]] && echo " * If this is a brand new install with no FOG users yet, use:"
                echo "   ${WEB_url_proto}://${selfName}${WEB_root}management/index.php?node=schema&fogtoken=${installToken}"
            fi
            echo
            read -p " * Press [Enter] key when database is updated/installed."
            echo
            ;;
    esac
    verifySchemaDeploy
    # ---------------------------------------------------------
    # External Unprivileged Database Implementation
    # Bypass DB user management (fogstorage) requiring root GRANT
    # ---------------------------------------------------------
    if [[ ${DB_external} == yes ]]; then
        echo " * Skipping fogstorage DB user management (External Database Mode)"
        # Return cleanly, skipping the GRANT/ALTER commands below
        return 0
    fi
    # ---------------------------------------------------------
    dots "Update fogstorage database password"
    mysql $sqloptionsuser --password="${DB_password}" --execute="INSERT INTO globalSettings (settingKey, settingDesc, settingValue, settingCategory) VALUES ('FOG_STORAGENODE_MYSQLPASS', 'This setting defines the password the storage nodes should use to connect to the fog server.', \"$snmysqlstoragepass\", 'FOG Storage Nodes') ON DUPLICATE KEY UPDATE settingValue=\"$snmysqlstoragepass\"" ${DB_name} >>$error_log 2>&1
    errorStat $?
    dots "Granting access to fogstorage database user"
    # The probe writes a throwaway row to find out whether fogstorage still
    # holds INSERT; a failure here is read as "the grants need redoing", which
    # is what sends the installer off to ask for the database root password.
    #
    # NAME THE COLUMNS. This probe has now broken twice, both times because it
    # was a positional INSERT against a table somebody else changed, and both
    # times the symptom was identical and misleading: an upgrade demanding a
    # database root password on a server whose grants were perfectly correct.
    #
    #   schema 336 made taskID the int(11) it always held. The marker was the
    #     literal '999test' in that column, and under STRICT_TRANS_TABLES --
    #     the MariaDB default -- a non-numeric string there is error 1265, not
    #     a warning. Fixed by moving the marker to createdBy, which left the
    #     positional list in place;
    #   schema 338 added logType and logText, taking the table from six
    #     columns to eight, so the six-value list became error 1136, "Column
    #     count doesn't match value count".
    #
    # A named column list cannot break that way: a column added later takes
    # its default and this INSERT does not care. That -- not the marker's
    # position -- is the actual fix, and it is why the previous repair did not
    # hold.
    #
    # id is AUTO_INCREMENT so it is omitted. taskID 0 points at no task, which
    # is what a throwaway row should. The marker lives in createdBy (varchar
    # 30) and the DELETE below keys on it.
    mysql ${host} -s --user=fogstorage --password="${snmysqlstoragepass}" --execute="INSERT INTO ${DB_name}.taskLog (taskID, taskStateID, ip, createTime, createdBy) VALUES (0, 3, '127.0.0.1', NOW(), 'fog-install-probe');" >/dev/null 2>&1
    connect_as_fogstorage=$?
    if [[ $connect_as_fogstorage -eq 0 ]]; then
        mysql $sqloptionsuser --password="${DB_password}" --execute="DELETE FROM ${DB_name}.taskLog WHERE createdBy='fog-install-probe' AND ip='127.0.0.1';" >/dev/null 2>&1
        echo "Skipped"
        return
    fi

    # we still need to grant access for the fogstorage DB user
    # and therefore need root DB access
    mysql $sqloptionsroot --password="${snmysqlrootpass}" --execute="quit" >>$error_log 2>&1
    if [[ $? -ne 0 ]]; then
        echo
        echo "   To improve the overall security the installer will restrict"
        echo "   permissions for the *fogstorage* database user."
        echo "   Please provide the database *root* user password. Be asured"
        echo "   that this password will only be used while the FOG installer"
        echo -n "   is running and won't be stored anywhere: "
        read -rs snmysqlrootpass
        echo
        echo
        mysql $sqloptionsroot --password="${snmysqlrootpass}" --execute="quit" >/dev/null 2>&1
        if [[ $? -ne 0 ]]; then
            echo "   Unable to connect to the database using the given password!"
            echo -n "   Try again: "
            read -rs snmysqlrootpass
            mysql $sqloptionsroot --password="${snmysqlrootpass}" --execute="quit" >/dev/null 2>&1
            if [[ $? -ne 0 ]]; then
                echo
                echo "   Failed! Terminating installer now."
                exit 1
            fi
        fi
    fi
    # Same scratch directory as everything else. This one does not cd, so the
    # old code failed differently -- the heredoc write failed, then mysql was
    # handed a missing file -- but from the same cause, and with the mkdir error
    # discarded just the same.
    local sqltmp=""
    if ! sqltmp="$(_installerTmpDir)"; then
        echo "Failed"
        echo " * Could not prepare the installer's scratch directory."
        return 1
    fi
    cat >"${sqltmp}/fog-db-grant-fogstorage-access.sql" <<EOF
SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='ANSI' ;
GRANT SELECT ON ${DB_name}.* TO 'fogstorage'@'%' ;
GRANT INSERT,UPDATE ON ${DB_name}.hosts TO 'fogstorage'@'%' ;
GRANT INSERT,UPDATE ON ${DB_name}.inventory TO 'fogstorage'@'%' ;
GRANT INSERT,UPDATE ON ${DB_name}.multicastSessions TO 'fogstorage'@'%' ;
GRANT INSERT,UPDATE ON ${DB_name}.multicastSessionsAssoc TO 'fogstorage'@'%' ;
GRANT INSERT,UPDATE ON ${DB_name}.nfsGroupMembers TO 'fogstorage'@'%' ;
GRANT INSERT,UPDATE ON ${DB_name}.tasks TO 'fogstorage'@'%' ;
GRANT INSERT,UPDATE ON ${DB_name}.taskStates TO 'fogstorage'@'%' ;
GRANT INSERT,UPDATE ON ${DB_name}.taskLog TO 'fogstorage'@'%' ;
GRANT INSERT,UPDATE ON ${DB_name}.snapinTasks TO 'fogstorage'@'%' ;
GRANT INSERT,UPDATE ON ${DB_name}.snapinJobs TO 'fogstorage'@'%' ;
FLUSH PRIVILEGES ;
SET SQL_MODE=@OLD_SQL_MODE ;
EOF
    mysql $sqloptionsroot --password="${snmysqlrootpass}" <"${sqltmp}/fog-db-grant-fogstorage-access.sql" >>$error_log 2>&1
    errorStat $?
}
validip() {
    local ip=$1
    local stat=1
    if [[ $ip =~ ^[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}$ ]]; then
        OIFS=$IFS
        IFS='.'
        ip=($ip)
        IFS=$OIFS
        [[ ${ip[0]} -le 255 && ${ip[1]} -le 255 && ${ip[2]} -le 255 && ${ip[3]} -le 255 ]]
        stat=$?
    fi
    echo $stat
}
# Same calling convention as validip(): echo 0/1, checked via
# [[ $(validhostname "$x") -ne 0 ]]. RFC-1123-ish: dot-separated labels of
# alphanumerics/hyphens, no leading/trailing hyphen per label. Needed because
# --hostname/--extra-server-name are the first NON-interactive entry point for
# this value -- the interactive prompt in lib/common/newinput.sh has never
# validated what an admin types, but a CLI flag's value reaches a vhost config
# and an OpenSSL CSR config file unchecked, so it must be checked before either.
validhostname() {
    local h=$1
    [[ $h =~ ^[a-zA-Z0-9]([a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?(\.[a-zA-Z0-9]([a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)*$ ]]
    echo $?
}
getCidr() {
    local cidr
    cidr=$(ip -f inet -o addr | grep $1 | awk -F'[ /]+' '/global/ {print $5}' | head -n2 | tail -n1)
    echo $cidr
}
mask2cidr() {
    local NET_subnet_mask=$1
    nbits=0
    OIFS=$IFS
    IFS='.'
    for dec in ${NET_subnet_mask}; do
        case $dec in
            255)
                let nbits+=8
                ;;
            254)
                let nbits+=7
                break
                ;;
            252)
                let nbits+=6
                break
                ;;
            248)
                let nbits+=5
                break
                ;;
            240)
                let nbits+=4
                break
                ;;
            224)
                let nbits+=3
                break
                ;;
            192)
                let nbits+=2
                break
                ;;
            128)
                let nbits+=1
                break
                ;;
            0)
                ;;
            *)
                echo "Error: $dec is not recognized"
                exit 1
                ;;
        esac
    done
    IFS=$OIFS
    echo "$nbits"
}
cidr2mask() {
    local i=""
    local mask=""
    local full_octets=$(($1/8))
    local partial_octet=$(($1%8))
    for ((i=0;i<4;i+=1)); do
        if [[ $i -lt $full_octets ]]; then
            mask+=255
        elif [[ $i -eq $full_octets ]]; then
            mask+=$((256 - 2**(8-$partial_octet)))
        else
            mask+=0
        fi
        test $i -lt 3 && mask+=.
    done
    echo $mask
}
mask2network() {
    local OIFS=$IFS
    IFS='.'
    read -r i1 i2 i3 i4 <<< "$1"
    read -r m1 m2 m3 m4 <<< "$2"
    IFS=$OIFS
    printf "%d.%d.%d.%d\n"  "$((i1 & m1))" "$((i2 & m2))" "$((i3 & m3))" "$((i4 & m4))"
}
# GH-667: everything in here used to be printed on STDOUT, including the
# failures. The sole consumers assign it through $( ), so an error message
# became the value -- "DHCP_range_end=Invalid IP Passed" -- and got written verbatim
# into dhcpd.conf, which is precisely the "pasted into the dhcpd.conf file and
# causes the dhcp service to fail" in that report. Diagnostics go to stderr so
# they cannot be mistaken for data, and the callers now check what they got.
mask2broadcast() {
    # Broadcast is the network with every host bit set: net | ~mask, per octet.
    # Used as the fallback when an interface carries no brd flag at all.
    local OIFS=$IFS
    IFS='.'
    read -r n1 n2 n3 n4 <<< "$1"
    read -r m1 m2 m3 m4 <<< "$2"
    IFS=$OIFS
    printf "%d.%d.%d.%d\n" \
        "$((n1 | (255 - m1)))" "$((n2 | (255 - m2)))" \
        "$((n3 | (255 - m3)))" "$((n4 | (255 - m4)))"
}
interface2broadcast() {
    local NET_interface=$1
    if [[ -z ${NET_interface} ]]; then
        echo "No interface passed" >&2
        return 1
    fi
    # One address per line means one brd per line, so an interface carrying a
    # second address returned two. Take the first, matching the ${NET_fog_server_ip} /
    # ${PKI_san_ip_addresses} contract from GH-954. Empty is a legitimate answer -- a /32
    # or a point-to-point link has no broadcast -- and the caller falls back.
    # awk rather than `grep -oP 'brd \K\S+'`: busybox grep has no -P at all,
    # so on Alpine that printed grep's whole usage screen into the middle of
    # the install and returned nothing -- the same trap as the -E/-P note in
    # installPackages. See #863.
    ip -4 addr show ${NET_interface} \
        | awk '{for (i = 1; i < NF; i++) if ($i == "brd") { print $(i + 1); exit }}'
}
subtract1fromAddress() {
    local ip=$1
    if [[ -z $ip ]]; then
        echo "No IP Passed" >&2
        return 1
    fi
    if [[ ! $(validip $ip) -eq 0 ]]; then
        echo "Invalid IP Passed" >&2
        return 1
    fi
    local oIFS=$IFS
    IFS='.'
    read ip1 ip2 ip3 ip4 <<< "$ip"
    IFS=$oIFS
    if [[ $ip4 -gt 0 ]]; then
        let ip4-=1
    elif [[ $ip3 -gt 0 ]]; then
        let ip3-=1
        ip4=255
    elif [[ $ip2 -gt 0 ]]; then
        let ip2-=1
        ip3=255
        ip4=255
    elif [[ $ip1 -gt 0 ]]; then
        let ip1-=1
        ip2=255
        ip3=255
        ip4=255
    else
        echo "Invalid IP ranges were passed" >&2
        echo ${ip1}.${ip2}.${ip3}.${ip4}
        return 2
    fi
    echo ${ip1}.${ip2}.${ip3}.${ip4}
}
subtractFromAddress() {
    local NET_fog_server_ip="$1"
    local decreaseby=$2
    local maxOctetValue=256
    local octet1=""
    local octet2=""
    local octet3=""
    local octet4=""
    local oIFS=$IFS
    IFS='.' read octet1 octet2 octet3 octet4 <<< "${NET_fog_server_ip}"
    IFS=$oIFS
    let octet4-=$decreaseby
    if [[ $octet4 -lt $maxOctetValue && $octet4 -ge 0 ]]; then
        printf "%d.%d.%d.%d\n" $octet1 $octet2 $octet3 $octet4 | sed 's/-//g'
        return 0
    fi
    octet4=$(echo $octet4 | sed 's/-//g')
    numRollOver=$((octet4 / maxOctetValue))
    let octet4-=$((numRollOver * maxOctetValue))
    let octet3-=$numRollOver
    if [[ $octet3 -lt $maxOctetValue && $octet3 -ge 0 ]]; then
        printf "%d.%d.%d.%d\n" $octet1 $octet2 $octet3 $octet4 | sed 's/-//g'
        return 0
    fi
    numRollOver=$((octet3 / maxOctetValue))
    let octet3-=$((numRollOver * maxOctetValue))
    let octet2-=$numRollOver
    if [[ $octet2 -lt $maxOctetValue && $octet2 -ge 0 ]]; then
        printf "%d.%d.%d.%d\n" $octet1 $octet2 $octet3 $octet4 | sed 's/-//g'
        return 0
    fi
    numRollOver=$((octet2 / maxOctetValue))
    let octet2-=$((numRollOver * maxOctetValue))
    let octet1-=$numRollOver
    if [[ $octet1 -lt $maxOctetValue && $octet1 -ge 0 ]]; then
        printf "%d.%d.%d.%d\n" $octet1 $octet2 $octet3 $octet4 | sed 's/-//g'
        return 0
    fi
    return 1
}
addToAddress() {
    local NET_fog_server_ip="$1"
    local increaseby=$2
    local maxOctetValue=256
    local octet1=""
    local octet2=""
    local octet3=""
    local octet4=""
    local oIFS=$IFS
    IFS='.' read octet1 octet2 octet3 octet4 <<< "${NET_fog_server_ip}"
    IFS=$oIFS
    let octet4+=$increaseby
    if [[ $octet4 -lt $maxOctetValue && $octet4 -ge 0 ]]; then
        printf "%d.%d.%d.%d\n" $octet1 $octet2 $octet3 $octet4
        return 0
    fi
    numRollOver=$((octet4 / maxOctetValue))
    let octet4-=$((numRollOver * maxOctetValue))
    let octet3+=$numRollOver
    if [[ $octet3 -lt $maxOctetValue && $octet3 -ge 0 ]]; then
        printf "%d.%d.%d.%d\n" $octet1 $octet2 $octet3 $octet4
        return 0
    fi
    numRollOver=$((octet3 / maxOctetValue))
    let octet3-=$((numRollOver * maxOctetValue))
    let octet2+=$numRollOver
    if [[ $octet2 -lt $maxOctetValue && $octet2 -ge 0 ]]; then
        printf "%d.%d.%d.%d\n" $octet1 $octet2 $octet3 $octet4
        return 0
    fi
    numRollOver=$((octet2 / maxOctetValue))
    let octet2-=$((numRollOver * maxOctetValue))
    let octet1+=$numRollOver
    if [[ $octet1 -lt $maxOctetValue && $octet1 -ge 0 ]]; then
        printf "%d.%d.%d.%d\n" $octet1 $octet2 $octet3 $octet4
        return 0
    fi
    return 1
}
getAllNetworkInterfaces() {
    gatewayif=$(ip -4 route show | grep "^default via" | awk '{print $5}')
    if [[ -z ${gatewayif} ]]; then
        interfaces="$(ip -4 link | grep -v LOOPBACK | grep UP | awk -F': |@' '{print $2}' | tr '\n' ' ')"
    else
        interfaces="$gatewayif $(ip -4 link | grep -v LOOPBACK | grep UP | awk -F': |@' '{print $2}' | tr '\n' ' ' | sed 's/${gatewayif}//g')"
    fi
    echo -n $interfaces
}
listPackages() {
    if [[ $listPackages != 1 ]]; then
        return
    fi
    . ../lib/common/config.sh
    [[ -z $fogpriorconfig ]] && fogpriorconfig="$fogprogramdir/.fogsettings"
    if [[ -f $fogpriorconfig ]]; then
        . "$fogpriorconfig"
        case ${FOG_os_id} in
            1)
                FOG_os_name="Redhat"
                . ../lib/redhat/config.sh
                ;;
            2)
                FOG_os_name="Debian"
                . ../lib/ubuntu/config.sh
                ;;
            3)
                # GH-447: osid 3 means Alpine here, but it meant Arch on the
                # 1.5 line. An Arch box upgrading from 1.5 carries FOG_os_id=3 in
                # .fogsettings and would silently be configured as Alpine --
                # wrong package manager, wrong init system, wrong web server.
                # Catch it by what the machine actually is and move it to 4.
                if [[ $linuxReleaseName_lower == *arch* || $linuxReleaseName_lower == *manjaro* ]]; then
                    echo " * Recording this Arch install as osid 4 (it was 3 on FOG 1.5)"
                    FOG_os_id=4
                    FOG_os_name="Arch"
                    . ../lib/arch/config.sh
                else
                    FOG_os_name="Alpine"
                    . ../lib/alpine/config.sh
                fi
                ;;
            4)
                FOG_os_name="Arch"
                . ../lib/arch/config.sh
                ;;
        esac
    else
        case $linuxReleaseName_lower in
            *fedora*|*red*hat*|*centos*|*mageia*|*alma*|*rocky*)
                FOG_os_id=1
                FOG_os_name="Redhat"
                . ../lib/redhat/config.sh
                ;;
            *ubuntu*|*bian*|*mint*)
                FOG_os_id=2
                FOG_os_name="Debian"
                . ../lib/ubuntu/config.sh
                ;;
            *alpine*)
                FOG_os_id=3
                FOG_os_name="Alpine"
                . ../lib/alpine/config.sh
                ;;
            *arch*|*manjaro*)
                FOG_os_id=4
                FOG_os_name="Arch"
                . ../lib/arch/config.sh
                ;;
            *)
                echo "Could not define OS"
                exit 1
                ;;
        esac
    fi
    if [[ $ignorehtmldoc -eq 1 ]]; then
        [[ -z $newpackagelist ]] && newpackagelist=""
        for z in ${FOG_packages}; do
            [[ -$z != htmldoc ]] && newpackagelist="$newpackagelist $z"
        done
        FOG_packages=$(echo $newpackagelist)
    fi
    if [[ ${DHCP_enabled} != yes ]]; then
        [[ -z $newpackagelist ]] && newpackagelist=""
        for z in ${FOG_packages}; do
            [[ -$z != $dhcpname ]] && newpackagelist="$newpackagelist $z"
        done
        FOG_packages=$(echo $newpackagelist)
    fi
    case ${FOG_install_type} in
        [Ss])
            FOG_packages=$(echo ${FOG_packages} | sed -e 's/[-a-zA-Z]*dhcp[-a-zA-Z]*//g')
            ;;
    esac
    # zip is the WRITER and is a separate package from unzip on every distro
    # here -- _publishLocalBootFiles() builds the local ESP boot archives with
    # it. Named "zip" identically on apt/dnf/pacman/apk, so it needs no
    # per-distro alternatives list.
    FOG_packages="${FOG_packages} jq unzip zip attr ${WEB_server_engine}"
    case ${FOG_os_id} in
        1)
            FOG_packages="${FOG_packages} php-bcmath bc"
            if [[ ${FOG_install_lang} == yes ]]; then
                FOG_packages="${FOG_packages} php-intl"
                for i in fr de eu es pt zh en ja; do
                    FOG_packages="${FOG_packages} glibc-langpack-${i}"
                done
            fi
            FOG_packages="${FOG_packages// mod_fastcgi/}"
            FOG_packages="${FOG_packages// mod_evasive/}"
            FOG_packages="${FOG_packages// php-mcrypt/}"
            case $linuxReleaseName_lower in
                *fedora*)
                    FOG_packages="${FOG_packages} php-json"
                    FOG_packages="${FOG_packages// mysql / mariadb }"
                    FOG_packages="${FOG_packages// mysql-server / mariadb-server }"
                    FOG_packages="${FOG_packages// dhcp / dhcp-server }"
            esac
            ;;
        2)
            if [[ ${WEB_server_engine} == "apache2" ]]; then
                FOG_packages="${FOG_packages// libapache2-mod-fastcgi/}"
                FOG_packages="${FOG_packages// libapache2-mod-evasive/}"
            fi
            FOG_packages="${FOG_packages// xinetd/}"
            FOG_packages="${FOG_packages// php-gettext/}"
            FOG_packages="${FOG_packages// php-php-gettext/}"
            if [[ ${FOG_install_lang} == yes ]]; then
                FOG_packages="${FOG_packages} php-intl"
                if [[ ${FOG_install_lang} == yes ]]; then
                    for i in fr de eu es pt zh-hans en ja; do
                        FOG_packages="${FOG_packages} language-pack-${i}"
                    done
                fi
            fi
            case $linuxReleaseName_lower in
                *ubuntu*|*mint*)
                    if [[ $OSVersion -gt 17 ]]; then
                        FOG_packages="${FOG_packages// libcurl3 / libcurl4 }"
                    fi
                    if [[ $OSVersion -gt 22 ]]; then
                        FOG_packages="${FOG_packages// libcurl4 / libcurl4t64 }"
                    fi
            esac
            ;;
        *bian*)
            if [[ $OSVersion -ge 10 ]]; then
                FOG_packages="${FOG_packages// libcurl3 / libcurl4 }"
                FOG_packages="${FOG_packages// mysql-client / mariadb-client}"
                FOG_packages="${FOG_packages// mysql-server / mariadb-server}"
            fi
            if [[ $OSVersion -ge 13 ]]; then
                FOG_packages="${FOG_packages// libcurl4 / libcurl4t64 }"
            fi
            ;;
    esac
    FOG_packages=$(echo ${FOG_packages[@]} | tr ' ' '\n' | sort -u | tr '\n' ' ')
    echo ${FOG_packages};
    exit 0;
}
# One bounded reachability probe against a single host. Returns curl's exit
# status so the caller can name the cause without running three separate tests
# to find it out.
#
# Both bounds matter. Without --connect-timeout, curl inherits libcurl's 300
# second default, which is exactly what a firewall that DROPs rather than
# REJECTs outbound traffic costs -- per host, per address family. Without
# --max-time, a connection that opens and then stalls never returns at all.
#
# Deliberately no -k. A proxy presenting its own CA passes an unverified probe
# and then fails the git clone that follows, and predicting that clone is the
# entire point of the check. Equally deliberately no -f: a host that answers 404
# at "/" is still a reachable host, and reachability is what is being measured.
inetProbe() {
    local host="$1"
    if command -v curl >/dev/null 2>&1; then
        curl -sS --connect-timeout $inetConnectTimeout --max-time $inetMaxTime \
            -o /dev/null "https://${host}/" >>$error_log 2>&1
        return $?
    fi
    # curl is in every distro's package list, but installPackages has not run
    # yet at this point, so a minimal image can legitimately reach here without
    # it. bash's own /dev/tcp keeps the fallback dependency free. It sees only
    # the TCP handshake -- not TLS, and not a proxy -- so it reports the generic
    # connect failure (7) rather than claiming to know more than it does.
    # Bounded by the connect timeout rather than the total: a handshake is all
    # this does, so there is no transfer phase for $inetMaxTime to govern.
    timeout $inetConnectTimeout bash -c "exec 3<>/dev/tcp/${host}/443" >>$error_log 2>&1
    [[ $? -eq 0 ]] && return 0
    return 7
}
# Probe the hosts this install is actually going to pull from, and record the
# answer somewhere the code that downloads can read it.
#
# This used to test DNS, then plain HTTP, then HTTPS, against httpbin.org,
# neverssl.com, github.com and fogproject.org -- none of them with a timeout,
# and none of them a host FOG needs. Worse, it opened by running
# `$packageinstaller curl`, so a connectivity check's first act was a package
# transaction that needed the very connectivity it was about to test: metadata
# refresh against every configured mirror, unbounded on Debian/Ubuntu whenever
# unattended-upgrades holds the dpkg lock (apt has no lock timeout), and on Arch
# a full system upgrade, because $packageinstaller there is `pacman -Syu`. All
# of it redirected to the error log, so the screen showed "Testing internet
# connection" and nothing else for minutes at a time.
#
# Nothing read the result either. dns_ok/http_ok/https_ok were set and never
# looked at, both failure paths returned rather than exited, and the caller
# ignored the status -- so the install proceeded identically either way and the
# stall bought a message and nothing more.
#
# What the install genuinely needs from the internet is the distro's package
# repositories -- which installPackages reports on for itself -- and the hosts
# behind $ipxegit/$ipxeurl and $pluginsgit/$pluginsurl, for the iPXE sources,
# the iPXE and Secure Boot release assets, and the plugins. Those are what is
# probed, so pointing any of them at an internal mirror tests the mirror instead
# of github.com rather than as well as it. One HTTPS request per host settles
# DNS, TCP and TLS together, and curl's exit status says which of the three
# failed, so the old three-stage ladder is not needed to produce a specific
# message.
#
# Failure stays non-fatal, as before: offline installs are supported and
# documented (pre-placed iPXE sources, a pre-placed release tarball, pre-placed
# plugin directories), so the output is advice plus $internet_ok, not an exit.
# $internet_ok is what fetchipxeasset and prepipxe read to avoid re-attempting
# a fetch that has already been shown to be unreachable.
checkInternetConnection() {
    dots "Testing internet connection"
    internet_ok=0
    local url host rc failhost="" failrc=0
    # Deduplicated because $ipxeurl is derived from $ipxegit and $pluginsurl
    # from $pluginsgit, so the stock configuration is one host probed once
    # rather than github.com probed four times.
    local hosts=$(
        for url in "$ipxegit" "$ipxeurl" "$pluginsgit" "$pluginsurl"; do
            host="${url#*://}"
            echo "${host%%/*}"
        done | grep . | sort -u
    )
    for host in $hosts; do
        echo -n "Testing connection to ${host}... " >> $error_log
        inetProbe "$host"
        rc=$?
        if [[ $rc -eq 0 ]]; then
            echo "OK" >> $error_log
            continue
        fi
        echo "Failed (curl exit ${rc})" >> $error_log
        failhost="$host"
        failrc=$rc
    done
    if [[ -z $failhost ]]; then
        internet_ok=1
        echo "Done"
        return 0
    fi
    echo "Failed"
    echo
    case $failrc in
        6)
            echo "Could not resolve ${failhost}. Check the contents of /etc/resolv.conf," | tee -a $error_log
            echo "and on RHEL, CentOS, Fedora or another RH variant also the DNS settings" | tee -a $error_log
            echo "on the connection itself (nmcli con show <name> | grep ipv4.dns)." | tee -a $error_log
            ;;
        # 7 and 28 share a message on purpose. A firewall that DROPs outbound
        # traffic -- the usual cause on an isolated or corporate network --
        # produces 28 (the connect timeout expiring), not 7; 7 is what an
        # explicit REJECT or "network unreachable" gives. Naming only $inetMaxTime
        # against a 28 would report the wrong bound, since it is almost always
        # $inetConnectTimeout that fired.
        7|28)
            echo "Could not reach ${failhost} on port 443 within ${inetConnectTimeout}s to connect" | tee -a $error_log
            echo "or ${inetMaxTime}s in total. A firewall that drops outbound traffic rather than" | tee -a $error_log
            echo "rejecting it looks exactly like this." | tee -a $error_log
            ;;
        35|60|77)
            echo "TLS to ${failhost} failed. If a proxy or filter is intercepting HTTPS," | tee -a $error_log
            echo "its CA has to be trusted by this machine -- git and curl will both fail" | tee -a $error_log
            echo "the same way until it is." | tee -a $error_log
            ;;
        *)
            echo "Could not reach ${failhost} (curl exit ${failrc})." | tee -a $error_log
            ;;
    esac
    echo
    echo "The install will continue. FOG needs ${failhost} for the iPXE sources," | tee -a $error_log
    echo "the iPXE release binaries and the bundled plugins, so those steps are the" | tee -a $error_log
    echo "ones expected to fail." | tee -a $error_log
    echo "If you are using a proxy server, please export http_proxy and https_proxy or use .curlrc" | tee -a $error_log
    echo "For a deliberate offline install, pre-place those sources -- each download" | tee -a $error_log
    echo "step below prints the exact path it looks in." | tee -a $error_log
    echo
    return 0
}
join() {
    local IFS="$1"
    shift
    echo "$*"
}
# GH-1580: the other half of backupReports(). This function existed but had no
# call site anywhere, so configureHttpd()'s rm -rf $webdirdest took an admin's
# own reports on every install and upgrade -- the backup was written to
# ../rpttmp and then never read.
#
# Never fatal. An absent or empty ../rpttmp is the ordinary fresh-install case
# rather than a failure, and aborting an otherwise good install over a report
# that was not there is a worse outcome than the one this exists to prevent.
restoreReports() {
    local warn=0
    [[ -d ../rpttmp ]] || return 0
    # find, not a glob: `cp -a ../rpttmp/*` passes the unexpanded pattern
    # through to cp when the directory is empty, which fails.
    [[ -n $(find ../rpttmp -mindepth 1 -print -quit 2>/dev/null) ]] || return 0
    dots "Restoring user reports"
    # mkdir, not a test for the directory. The original guard required
    # management/reports/ to already exist in the rebuilt tree -- and it never
    # does: packages/web/management ships no reports/ directory, the web root
    # is rebuilt from that source tree by configureHttpd, and the directory is
    # created at runtime by the reporting code. So the guard was false on every
    # ordinary upgrade and this function returned having restored nothing,
    # which made the GH-1580 fix a no-op on exactly the path it was written
    # for. The test asserted that the missing-directory case was a silent
    # success, so the suite agreed with it.
    mkdir -p "$webdirdest/management/reports" >>$error_log 2>&1 || { warn=1; }
    # /. so dotfiles come along and the copy lands INSIDE the target rather
    # than creating ../rpttmp underneath it.
    cp -a ../rpttmp/. $webdirdest/management/reports/ >>$error_log 2>&1 || warn=1
    # Cleared on the way out, for the same reason it is cleared on the way in.
    [[ $warn -eq 0 ]] && rm -rf ../rpttmp >>$error_log 2>&1
    [[ $warn -ne 0 ]] && echo -n "(some reports could not be restored) "
    errorStat 0
}
installFOGServices() {
    dots "Setting up FOG Services"
    mkdir -p $servicedst
    cp -Rf $servicesrc/* $servicedst/
    chmod +x -R $servicedst/
    mkdir -p $servicelogs
    errorStat $?
    # ADR 0010: FOGPluginRunner runs as the web user, so it cannot write into
    # $servicelogs, which is root's. It gets its own subdirectory instead of
    # group-write on the shared one -- log rotation renames and unlinks, so
    # shared write would let plugin code delete the root daemons' logs.
    dots "Creating FOG plugin runner log directory"
    mkdir -p $servicelogs/plugins >>$error_log 2>&1
    chown ${apacheuser}:${apacheuser} $servicelogs/plugins >>$error_log 2>&1
    errorStat $?
    # Outside the dots/errorStat pair, like every other caller: setSELinuxContext
    # prints its own "Setting SELinux context" line, so calling it between them
    # ran that line into ours ("...directory.... * Setting SELinux context...OK"
    # with our OK stranded on the next line) AND left errorStat reporting the
    # labeling result instead of whether the directory was created -- a failed
    # mkdir or chown here would have printed OK.
    setSELinuxContext "$servicelogs/plugins" httpd_sys_rw_content_t
    # FOGRetentionRunner is the second non-root daemon and needs the same
    # thing for the same reason -- rotation renames and unlinks, so it needs
    # write on the DIRECTORY, and $servicelogs itself is root's.
    #
    # Its own directory rather than sharing plugins/. There is no privilege
    # boundary between the two to defend (both run as $apacheuser), but a
    # retention log filed under plugins/ would reintroduce exactly the
    # "this must be a plugin thing" confusion that giving retention its own
    # daemon exists to remove.
    dots "Creating FOG retention runner log directory"
    mkdir -p $servicelogs/retention >>$error_log 2>&1
    chown ${apacheuser}:${apacheuser} $servicelogs/retention >>$error_log 2>&1
    errorStat $?
    # Outside the dots/errorStat pair, like every other caller -- see the note
    # on the plugins directory above.
    setSELinuxContext "$servicelogs/retention" httpd_sys_rw_content_t
    # Where the web tier records what FOS told it (fogproject#1206). Its own
    # subdirectory for the same reason the plugin runner's is: the writer is
    # the web user, rotation renames and unlinks, and $servicelogs itself is
    # root's -- the eight daemons' logs live there and nothing running as the
    # web user should be able to unlink them.
    dots "Creating FOS report log directory"
    mkdir -p $servicelogs/fos >>$error_log 2>&1
    chown ${apacheuser}:${apacheuser} $servicelogs/fos >>$error_log 2>&1
    errorStat $?
    # Outside the dots/errorStat pair, like every other caller. The _rw_ label
    # is not optional here: GH-964, /opt/fog inherits usr_t, httpd_t may READ
    # usr_t but not write it, so without this the directory exists, looks
    # right, and every report is dropped with nothing but an AVC to say so.
    setSELinuxContext "$servicelogs/fos" httpd_sys_rw_content_t
    # Where FOGBase::logFault() records database writes that did not land.
    # Its own subdirectory for the same reason the two above have theirs.
    #
    # Unlike those two, BOTH tiers write here -- the web user, and root for
    # the eight daemons -- so logFault() writes faults-web.log and
    # faults-service.log rather than one shared file, whose owner would be
    # whichever tier hit a failed write first. The directory is the web
    # user's; root writes into it regardless of mode.
    dots "Creating FOG fault log directory"
    mkdir -p $servicelogs/faults >>$error_log 2>&1
    chown ${apacheuser}:${apacheuser} $servicelogs/faults >>$error_log 2>&1
    # 0750, not the 0755 the other log directories carry. A fault line names
    # the class, the table and the shape of the statement that failed, which
    # is more than any local account needs; #1261 already cut the bound
    # values out of it, and this stops the rest being world-readable. The web
    # user owns the directory and root ignores the mode, so both writers are
    # unaffected.
    chmod 0750 $servicelogs/faults >>$error_log 2>&1
    errorStat $?
    # Outside the dots/errorStat pair, like every other caller, and the _rw_
    # label is as load-bearing here as it is for fos above (GH-964).
    setSELinuxContext "$servicelogs/faults" httpd_sys_rw_content_t
    # servicemaster.log is where service_lib.php writes every daemon's start,
    # stop and fatal lines, and where PHP's own error_log is pointed. The
    # runner has to be able to append to it or its supervisor lines silently
    # divert to journald while the other eight keep landing here -- one log
    # with a hole in it is worse than either alternative. Group write only:
    # root still owns it, and the file is appended to, never rotated, so no
    # directory write is implied.
    dots "Setting FOG service master log ownership"
    touch $servicelogs/servicemaster.log >>$error_log 2>&1
    chown root:${apacheuser} $servicelogs/servicemaster.log >>$error_log 2>&1
    chmod 660 $servicelogs/servicemaster.log >>$error_log 2>&1
    errorStat $?
    dots "Creating FOG cache directory"
    mkdir -p $fogprogramdir/cache >>$error_log 2>&1
    # The settings-cache flush signal is written by the web tier AND by the CLI
    # daemons. The php-fpm worker does not always run as $apacheuser (e.g. on
    # RedHat/nginx the packaged pool keeps user=apache), so an $apacheuser-owned
    # dir is not reliably writable by the process that needs to touch the signal.
    # The file holds no secrets, so use /tmp-style sticky world-writable perms.
    chown ${SVC_user}:${apacheuser} $fogprogramdir/cache >>$error_log 2>&1
    chmod 1777 $fogprogramdir/cache >>$error_log 2>&1
    errorStat $?
    # GH-964: /opt/fog inherits usr_t, and httpd_t may READ usr_t but not write
    # it. The lab's audit log carried 74,406 httpd_t->usr_t:file denials and
    # every one of them was a write. Reads being allowed is what hides it:
    # nothing fails until something tries to write, so the install looks clean
    # and the settings-cache flush silently never happens.
    #
    # Labeled where the directory is created rather than in a sweep at the end,
    # so a relocated $fogprogramdir (GH-850) is labeled wherever it landed.
    setSELinuxContext "$fogprogramdir/cache" httpd_sys_rw_content_t
    # FOG's own PHP session store (FOG_SESSION_DIR in commons/init.php, which
    # points session.save_path here at runtime). FOG used to share the distro's
    # session directory, where session.gc_maxlifetime is 1440 -- 24 minutes on
    # every distro we support -- so PHP reaped the session file long before
    # FOG_INACTIVITY_TIMEOUT said to, and the user was silently bounced to the
    # login page. gc_maxlifetime applies to the whole save_path, so FOG cannot
    # raise it without imposing its retention on every other PHP application on
    # the box. Hence a private directory.
    dots "Creating FOG session directory"
    mkdir -p $fogprogramdir/sessions >>$error_log 2>&1
    # 0700 and owned by the pool user, NOT the sticky 1777 that cache uses.
    # A session file IS an authentication token: anything that can read this
    # directory can resume an admin session. The php-fpm pool is pinned to
    # $apacheuser by createSSLCA() (which also emits the vhost, and rewrites
    # user=/group= in the pool file) -- the same variable used here, so the two
    # agree whichever order they run in. That pin is what makes a single-owner
    # 0700 directory safe here where it would not have been for the cache.
    chown ${apacheuser}:${apacheuser} $fogprogramdir/sessions >>$error_log 2>&1
    chmod 0700 $fogprogramdir/sessions >>$error_log 2>&1
    errorStat $?
    # Same GH-964 reasoning as the cache directory above: /opt/fog inherits
    # usr_t and httpd_t may read but not write it. Unlabeled, PHP cannot write
    # a session file on an enforcing host -- which does not degrade, it means
    # nobody can log in at all, with only an AVC denial to say so.
    setSELinuxContext "$fogprogramdir/sessions" httpd_sys_rw_content_t
    # The external plugin root (ADR 0009). Created here so a fresh install has
    # somewhere to put a third-party plugin; without it an admin has to guess
    # the path and mkdir it as root first. Empty is the normal state and the
    # web tier treats a missing or empty directory as "no external plugins".
    #
    # Deliberately NOT under $webdirdest: configureHttpd() does
    # `rm -rf $webdirdest`, so anything an admin installed there would be
    # deleted by the next upgrade. Living here it survives by construction.
    dots "Creating FOG plugin directory"
    mkdir -p $fogprogramdir/plugins >>$error_log 2>&1
    # An admin who has turned on UI plugin uploads gave this directory to the
    # web user on purpose (bin/fog-plugin-uploads.sh). Re-running the installer
    # must not quietly take that back: it would leave the setting saying
    # uploads are on while every upload failed. So ownership is only asserted
    # when the directory is still root's -- which covers a fresh install and
    # any server that never opted in.
    local pluginowner="$(stat -c '%U' $fogprogramdir/plugins 2>/dev/null)"
    local plugincontext="httpd_sys_content_t"
    if [[ $pluginowner == root || -z $pluginowner ]]; then
        # root-owned and read-only to the web tier ON PURPOSE. PHP autoloads
        # code from this directory, so write access here is equivalent to write
        # access to the FOG code tree -- a web-writable plugin root turns any
        # file-write bug into remote code execution. That is why enabling the
        # upload flow takes a deliberate root command and is not the default.
        chown root:root $fogprogramdir/plugins >>$error_log 2>&1
        chmod 0755 $fogprogramdir/plugins >>$error_log 2>&1
    else
        # Uploads are enabled here, so the web tier writes as well as reads and
        # needs the _rw_ label. Relabeling to the read-only type would break
        # uploads on an enforcing host with nothing but an AVC denial to say so.
        plugincontext="httpd_sys_rw_content_t"
    fi
    errorStat $?
    # Outside the dots/errorStat pair above: setSELinuxContext prints its own
    # "Setting SELinux context" line, so calling it between them interleaved
    # the two and left errorStat reporting the labeling result rather than
    # whether the directory was created. Matches the cache block above.
    #
    # httpd_sys_content_t by default, not the _rw_ variant the cache uses: the
    # web tier only reads here unless uploads have been enabled. See the GH-964
    # note above for why /opt/fog's inherited usr_t is not left alone.
    setSELinuxContext "$fogprogramdir/plugins" "$plugincontext"
}
configureUDPCast() {
    dots "Setting up UDPCast"
    local cur="$(pwd)" udptmp=""
    # Same scratch directory, same reasons -- see _installerTmpDir(). This one
    # would fail in exactly the way downloadfiles() did, just further down the
    # install, and its mkdir error was going to /dev/null too.
    if ! udptmp="$(_installerTmpDir)"; then
        echo "Failed"
        echo " * Could not prepare the installer's scratch directory."
        return 1
    fi
    cd "$udptmp" || { echo "Failed"; return 1; }
    rm -rf "$udpcastout"
    tar xzf "$udpcastsrc" >>$error_log 2>&1
    # Guarded because everything after it -- configure, make, make install --
    # would otherwise run in the scratch directory instead of the unpacked
    # source, each failing separately and none of them saying why.
    if ! cd "$udpcastout"; then
        echo "Failed"
        echo " * ${udpcastsrc} did not unpack to ${udpcastout}. See ${error_log}."
        cd "$cur"
        return 1
    fi
    grep -q 'BCM[0-9][0-9][0-9][0-9]' /proc/cpuinfo >>$error_log 2>&1
    if [[ $? -eq 0 ]]; then
        # Bounded, and the retry count cut right down. wget defaults to
        # --tries=20 with no connect timeout at all, so on a Pi that cannot
        # reach savannah this sat here for twenty full SYN retry cycles, twice,
        # silently. Both files are a few KB, so a 30 second read timeout cannot
        # cut a legitimate transfer short.
        wget -qO config.guess --connect-timeout=$inetConnectTimeout --read-timeout=30 --tries=2 \
            "https://git.savannah.gnu.org/gitweb/?p=config.git;a=blob_plain;f=config.guess" >>$error_log 2>&1
        wget -qO config.sub --connect-timeout=$inetConnectTimeout --read-timeout=30 --tries=2 \
            "https://git.savannah.gnu.org/gitweb/?p=config.git;a=blob_plain;f=config.sub" >>$error_log 2>&1
        chmod +x config.guess config.sub >>$error_log 2>&1
    fi
    errorStat $?
    dots "Configuring UDPCast"
    ./configure >>$error_log 2>&1
    errorStat $?
    dots "Building UDPCast"
    make >>$error_log 2>&1
    errorStat $?
    dots "Installing UDPCast"
    make install >>$error_log 2>&1
    errorStat $?
    if [[ -f "/usr/local/sbin/udp-sender" ]]; then
        if [[ ! -f "/usr/sbin/udp-sender" ]]; then
            ln -sf "/usr/local/sbin/udp-sender" "/usr/sbin/udp-sender"
        fi
    elif [[ -f "/usr/sbin/udp-sender" ]]; then
        if [[ ! -f "/usr/local/sbin/udp-sender" ]]; then
            ln -sf "/usr/sbin/udp-sender" "/usr/local/sbin/udp-sender"
        fi
    fi
    cd "$cur"
}
configureFTP() {
    dots "Setting up and starting VSFTP Server"
    if [[ -f $ftpxinetd ]]; then
        mv $ftpxinetd ${ftpxinetd}.fogbackup
    fi
    vsftp=$(vsftpd -version 0>&1 | awk -F'version ' '{print $2}')
    vsvermaj=$(echo $vsftp | awk -F. '{print $1}')
    vsverbug=$(echo $vsftp | awk -F. '{print $3}')
    seccompsand=""
    allow_writeable_chroot=""
    if [[ $vsvermaj -gt 3 ]] || [[ $vsvermaj -eq 3 && $vsverbug -ge 2 ]]; then
        seccompsand="seccomp_sandbox=NO"
    fi
    mv -fv "${ftpconfig}" "${ftpconfig}.${timestamp}" >>$error_log 2>&1
    # GH-964 sibling: pin the passive data range. Without this vsftpd draws
    # from the ephemeral range, which cannot be opened in a firewall without
    # the nf_conntrack_ftp helper -- and modern kernels stopped auto-assigning
    # helpers, so relying on one is relying on something that is off by
    # default. Pinning the range is what lets configureFirewall() open exactly
    # the ports FTP will actually use. The bounds live in lib/common/config.sh
    # next to the multicast window so the daemon config and the firewall rules
    # cannot drift apart.
    echo -e  "max_per_ip=200\nanonymous_enable=NO\nlocal_enable=YES\nwrite_enable=YES\nlocal_umask=022\ndirmessage_enable=YES\nxferlog_enable=YES\nconnect_from_port_20=YES\nxferlog_std_format=YES\nlisten=YES\npam_service_name=vsftpd\nuserlist_enable=NO\nchmod_enable=YES\npasv_min_port=${ftppasvmin}\npasv_max_port=${ftppasvmax}\n$seccompsand" > "$ftpconfig"
    diffconfig "${ftpconfig}"
    case $systemctl in
        yes)
            systemctl is-enabled --quiet vsftpd && true || systemctl enable vsftpd >>$error_log 2>&1
            systemctl is-active --quiet vsftpd && systemctl stop vsftpd >>$error_log 2>&1 || true
            systemctl is-active --quiet vsftpd && true || systemctl start vsftpd >>$error_log 2>&1
            systemctl status vsftpd >>$error_log 2>&1
            ;;
        *)
            case ${FOG_os_id} in
                2)
                    sysv-rc-conf vsftpd on >>$error_log 2>&1
                    service vsftpd stop >>$error_log 2>&1
                    service vsftpd start >>$error_log 2>&1
                    service vsftpd status >>$error_log 2>&1
                    ;;
                3)
                    # Alpine fell through to the chkconfig arm below, which
                    # does not exist there. FTP is not optional -- it is how
                    # the client uploads a capture and how storage nodes
                    # replicate -- so the step reported OK and imaging failed
                    # later, a long way from the cause. See #863.
                    rc-update add vsftpd default >>$error_log 2>&1
                    rc-service vsftpd stop >>$error_log 2>&1
                    rc-service vsftpd start >>$error_log 2>&1
                    rc-service vsftpd status >>$error_log 2>&1
                    ;;
                *)
                    chkconfig vsftpd on >>$error_log 2>&1
                    service vsftpd stop >>$error_log 2>&1
                    service vsftpd start >>$error_log 2>&1
                    service vsftpd status >>$error_log 2>&1
                    ;;
            esac
            ;;
    esac
    errorStat $?
}
configureDefaultiPXEfile() {
    dots 'Configuring default iPXE file'
    [[ -z ${WEB_root} ]] && WEB_root='/fog/'   # see registerStorageNode, GH-529
    # Netboot gets its own protocol -- see _resolveNetbootProto. Everything
    # downstream follows from this one URL: boot.php derives the menu's kernel
    # and init URLs from the protocol the request arrived on
    # (FOGBase::${WEB_url_proto} reads $_SERVER['HTTPS']), so chaining over HTTP here
    # makes the whole boot sequence HTTP with no PHP change.
    _resolveNetbootProto
    # HTTPS netboot has to address this server by a name its CERTIFICATE
    # carries -- not by IP, and not merely by "a name".
    #
    # A certificate is issued to a name. Public CAs will not issue for a private
    # IP at all, and even where the chain itself validates, iPXE still fails the
    # handshake on a name mismatch -- so an https:// URL built from ${NET_fog_server_ip}
    # cannot work, whatever the certificate is. HTTP does not care, which is why
    # this has never mattered before.
    #
    # ${NET_hostname} is NOT good enough, which is the bug this replaces. It is a
    # short label on plenty of servers and validhostname() accepts one; that is
    # harmless against a FOG-issued leaf, because _defaultServerNames() puts the
    # short form in the SAN list, and impossible against a publicly-issued one.
    # _resolveNetbootHost asks the certificate instead, and is shared with
    # recordNetbootWebHost so the two hops of a boot cannot name different hosts.
    local nbhost="${NET_fog_server_ip}"
    if [[ ${BOOT_url_proto} == https ]]; then
        _resolveNetbootHost || return 1
        nbhost="$netboothost"
    fi
    # param manufacturer took ${product} for years, so every ipxeTable row
    # recorded the model twice and the vendor never once. iPXE exposes the two
    # as separate SMBIOS settings.
    #
    # macboot is ${netX/mac}, iPXE's alias for the device it booted from. It is
    # NOT a replacement for mac0: netX is a pointer at one of net0..netN, so
    # swapping it in would drop net0 from the set on a machine that booted off
    # net1. boot.php unions every mac* field and array_unique()s the result, so
    # sending both costs nothing when they are the same NIC and guarantees the
    # booting NIC is present however many NICs the box has. It sits above the
    # net1..net7 chain because that chain short-circuits to :bootme on the first
    # absent interface, which on a single-NIC machine is net1.
    #
    # The enumeration used to stop at net2. Anything past three NICs was
    # invisible to the host lookup, so a machine registered under only its
    # fourth NIC could not be found at all.
    # secureboot/setupmode are ${efi/SecureBoot} and ${efi/SetupMode}, the two
    # EFI variables that say whether this machine is enforcing Secure Boot and
    # whether its platform key has been cleared. iPXE exposes every EFI
    # variable as a setting under the "efi" scope (interface/efi/efi_settings.c),
    # so this needs no fog-ipxe change and no FOS change -- it is a read of
    # something already there.
    #
    # SAFE ON LEGACY BIOS, and that was measured rather than assumed. The efi
    # settings block is only registered on EFI builds (config/settings.h gates
    # EFI_SETTINGS on PLATFORM_efi), and iPXE substitutes an empty string for a
    # setting it cannot resolve rather than erroring, so a pcbios machine sends
    # both params empty and the chain is unaffected. Verified 2026-08-28 by
    # running FOG's own ipxe.lkrn under SeaBIOS through this exact script.
    #
    # BOTH are sent, because SecureBoot alone cannot tell the two enrollment
    # routes apart. A machine with Secure Boot merely switched off still has a
    # platform key and still refuses a db write; only Setup Mode accepts one.
    # That is the difference between an enrollment that completes unattended and
    # one that needs a human at the MokManager screen.
    #
    # An older default.ipxe sends neither param, and that is deliberately
    # distinguishable from sending them empty: PHP sees NULL for an absent
    # param and '' for a present-but-empty one, so a server that has not
    # re-run the installer reads as "never reported" rather than as "legacy
    # BIOS". This function runs on every install, so it clears itself.
    #
    # Advisory only. boot.php is unauthenticated, so what arrives here is
    # attacker-controlled: it drives targeting, filtering and display, and
    # nothing else. See ADR 0029.
    echo -e "#!ipxe\nset arch \${buildarch}\niseq \${arch} i386 && cpuid --ext 29 && set arch x86_64 ||\nparams\nparam mac0 \${net0/mac}\nparam arch \${arch}\nparam platform \${platform}\nparam product \${product}\nparam manufacturer \${manufacturer}\nparam ipxever \${version}\nparam filename \${filename}\nparam sysuuid \${uuid}\nparam sysserial \${serial}\nparam mbserial \${board-serial}\nparam caseasset \${asset}\nparam secureboot \${efi/SecureBoot}\nparam setupmode \${efi/SetupMode}\nisset \${netX/mac} && param macboot \${netX/mac} ||\nisset \${net1/mac} && param mac1 \${net1/mac} || goto bootme\nisset \${net2/mac} && param mac2 \${net2/mac} || goto bootme\nisset \${net3/mac} && param mac3 \${net3/mac} || goto bootme\nisset \${net4/mac} && param mac4 \${net4/mac} || goto bootme\nisset \${net5/mac} && param mac5 \${net5/mac} || goto bootme\nisset \${net6/mac} && param mac6 \${net6/mac} || goto bootme\nisset \${net7/mac} && param mac7 \${net7/mac} || goto bootme\n:bootme\nchain ${BOOT_url_proto}://${nbhost}${WEB_root}service/ipxe/boot.php##params" > "$tftpdirdst/default.ipxe"
    errorStat $?
}
prepareiPXEsource() {
    # Put the fog-ipxe checkout at $buildipxesrc ($fogprogramdir/ipxe) and pin
    # it to the same tag the binaries were downloaded from, so a locally built
    # binary and a downloaded one are the same build. Cloning an unpinned
    # default branch here would put us straight back to "two people install on
    # the same day and get different binaries", which is exactly what pinning
    # IPXEVER fixed upstream-side. See GH-957, GH-959.
    #
    # An existing checkout is reused rather than replaced. That is what makes
    # an offline install possible: pre-place this directory (and its build/
    # subdirectory of upstream clones) and nothing here needs the network.
    dots "Preparing iPXE build sources"
    if [[ -d $buildipxesrc/.git ]]; then
        # git has no connect timeout of its own, so an unreachable host stalls
        # here for as long as the kernel retries the SYN. The fetch is only ever
        # an update to a checkout that already works, so when the host is known
        # to be unreachable, skip straight to using what is on disk -- the same
        # outcome the failed-checkout branch below produces, minus the wait.
        if [[ $internet_ok -ne 1 ]]; then
            echo "Skipped (using existing checkout)"
            return 0
        fi
        git -C "$buildipxesrc" fetch --tags --force "$ipxegit" >>$error_log 2>&1
        if ! git -C "$buildipxesrc" checkout -q "$ipxeVer" >>$error_log 2>&1; then
            # Offline, or the tag does not exist yet. A usable checkout is
            # still a usable checkout -- do not fail an entire install over a
            # fetch that was only ever an update.
            echo "Skipped (using existing checkout)"
            return 0
        fi
        errorStat 0
        return 0
    fi
    if [[ -x $buildipxesrc/buildipxe.sh ]]; then
        # Pre-placed as a plain directory rather than a clone -- the documented
        # offline path. Leave it entirely alone.
        echo "Skipped (pre-placed sources)"
        return 0
    fi
    if ! git clone -q --branch "$ipxeVer" "$ipxegit" "$buildipxesrc" >>$error_log 2>&1; then
        # Guidance first: errorStat exits before returning unless $exitFail is
        # set, so anything printed after it is never seen.
        echo "Failed!"
        echo " * Could not obtain iPXE sources ($ipxeVer) from $ipxegit"
        echo " * For an offline install, place a fog-ipxe checkout at $buildipxesrc"
        [[ -z $exitFail ]] && exit 1
        return 1
    fi
    errorStat 0
}
fetchipxeasset() {
    # Download one fog-ipxe release tarball, verify its checksum, and unpack it
    # into the staging tree. Callers own the dots/messaging and decide whether a
    # failure is fatal, because the two assets differ exactly there: without the
    # binaries nothing PXE boots at all, while without the Secure Boot set every
    # client that boots today still boots.
    #
    # Retries by re-running sha256sum -c rather than trusting curl's exit code,
    # so a truncated or corrupted download is caught and refetched rather than
    # unpacked. --fail makes an HTTP error a failed fetch instead of an error
    # page written into the tarball, which the checksum then rejected ten times
    # over before giving up.
    local tarball="$1"
    local dest="$2"
    local url="${ipxeurl}/${ipxeVer}/${tarball}"
    # This one already logged its mkdir error, so it was the least broken of the
    # three -- but it was still resolving ../tmp/ against the ambient cwd. Routed
    # through the same helper so there is one definition of where scratch space is.
    local tmpdir=""
    tmpdir="$(_installerTmpDir)" || return 1
    local cwd="$(pwd)"
    cd "$tmpdir" || return 1
    local checksum=1
    local cnt=0
    # Ten rounds of two timeout-less curls is the most expensive stall in the
    # whole installer: on a network that drops outbound traffic each of those
    # twenty connects sat at libcurl's 300 second default before returning, all
    # of it silent under one "Downloading iPXE binaries" line. When
    # checkInternetConnection has already established the host is unreachable
    # there is nothing to retry FOR, so make the one attempt and report.
    local tries=10
    [[ $internet_ok -ne 1 ]] && tries=1
    while [[ $checksum -ne 0 && $cnt -lt $tries ]]; do
        [[ -f ${tarball}.sha256 ]] && sha256sum -c ${tarball}.sha256 >>$error_log 2>&1
        checksum=$?
        if [[ $checksum -ne 0 ]]; then
            # --connect-timeout bounds an unreachable host; --speed-time/-limit
            # bounds a connection that opens and then stalls. --max-time is
            # deliberately NOT used here -- these are multi-megabyte tarballs
            # and a slow but working link must be allowed to finish.
            #
            # No -k. This is an ordinary internet download from a host with a
            # perfectly good certificate, and the sha256 below does not save
            # us -- it is fetched over the same unverified connection, so
            # whoever could substitute the tarball could substitute the hash
            # with it. checkInternetConnection() already explains a TLS
            # failure here as an untrusted intercepting proxy.
            curl --silent -fOL --connect-timeout $inetConnectTimeout \
                --speed-time 30 --speed-limit 1024 "$url" >>$error_log 2>&1
            curl --silent -fOL --connect-timeout $inetConnectTimeout \
                --speed-time 30 --speed-limit 1024 "${url}.sha256" >>$error_log 2>&1
        fi
        let cnt+=1
    done
    if [[ $checksum -ne 0 ]]; then
        cd $cwd
        return 1
    fi
    mkdir -p "$dest" >>$error_log 2>&1
    tar -xzf "$tarball" -C "$dest" >>$error_log 2>&1
    local stat=$?
    cd $cwd
    return $stat
}
# The CA that gets compiled into a locally built iPXE: ${PKI_web_ca_cert}, the
# Web intermediate itself. buildipxe.sh takes it as CERT=/TRUST=, its only
# per-site input.
#
# The Web CA and NOT ${PKI_web_trust_chain}, which is what this used to prefer.
# The chain is the web zone's trust PATH -- intermediate plus the root anchoring
# it -- and embedding the whole bundle gives iPXE strictly more trust than the
# job needs: it makes iPXE trust the FOG root, and therefore anything the root
# ever signs, when all it has to validate is boot.php's leaf. The intermediate
# is name-constrained and serverAuth-only (ADR 0016, which is enforceable
# precisely because iPXE is a verifier FOG can patch), so pinning that one
# certificate is both narrower and sufficient.
#
# It is also what makes bring-your-own-CA work here. An admin whose Web CA is
# their own intermediate has no FOG root above it, so there was nothing sensible
# for the chain to contain -- the old preference either embedded a bundle whose
# root FOG does not own, or fell through to this same value anyway.
#
# Expect ONE forced rebuild per server on upgrade: _ipxeBuildStampValue() hashes
# this file into the stamp's ca= field, so the stamp no longer matches and
# _needsLocalIpxeBuild() schedules exactly one rebuild. That is correct rather
# than unfortunate -- the binary on disk really does embed different bytes than
# the ones this now asks for.
_resolveIpxeTrust() {
    ipxetrust="${PKI_web_ca_cert}"
}
# What a built tree is built FROM: the iPXE release tag, and the exact bytes of
# the CA that got compiled into it.
#
# The CA half is not decoration. TRUST=/CERT= bakes the certificate into the
# binary, so --recreate-ca, switching to an external CA, or rotating the
# intermediate all leave a binary trusting a CA that no longer signs anything --
# at an unchanged iPXE tag. A version-only check would skip that rebuild, and
# the failure lands at a PXE client as a TLS error with nothing on the server to
# connect it to the cause.
#
# The third field, bin=, is the sha256 of snponly.efi AS IT SITS IN THE STAGING
# TREE, and it is what turns the stamp from an intention into a fact. Without
# it the stamp said "a build was run for this tag and this CA" and nothing
# more: downloadipxe() unpacks the published tarball over the staging tree on
# every run, so the run AFTER a build found the published, CA-less binaries
# waiting there, matched the stamp on tag and CA alone, skipped the rebuild,
# and copied the published binaries to the TFTP root. Every HTTPS netboot then
# died at boot.php with iPXE's "Permission denied" out of x509.c -- no trusted
# root to build a path to -- while the install printed nothing at all about the
# build having been skipped. Comparing the staged bytes is also what lets
# downloadipxe() decide whether unpacking is safe.
_ipxeBuildStampValue() {
    local sum="" bin="" staged
    [[ -n $ipxetrust && -f $ipxetrust ]] && \
        sum=$(sha256sum "$ipxetrust" 2>/dev/null | cut -d' ' -f1)
    staged="$(readlink -f "$tftpdirsrc" 2>/dev/null)/snponly.efi"
    [[ -s $staged ]] && bin=$(sha256sum "$staged" 2>/dev/null | cut -d' ' -f1)
    printf 'ipxe=%s ca=%s bin=%s' "${ipxeVer:-unknown}" "${sum:-none}" "${bin:-none}"
}
# Does this server compile its own iPXE?
#
# One predicate, replacing three separate `${WEB_url_proto} == https` tests that had
# quietly become three different questions wearing the same clothes: whether to
# download the release asset, whether to stage Secure Boot binaries, and
# whether to compile. Only the last one is about compiling.
#
# The trade the old test encoded is real but much narrower than it looked. iPXE
# validates TLS strictly and cannot be told to trust a private CA, so serving
# boot.php over HTTPS from a FOG-PKI certificate needs the CA compiled in
# (TRUST=) -- and a locally rebuilt binary is not upstream's SIGNED one, so it
# costs the Secure Boot shim and makes onboarding harder, not easier. What was
# wrong was inferring that from "the web UI uses HTTPS", which says nothing
# about the netboot transport and nothing about what the certificate chains to.
#
# So: the build happens iff the admin asked for it. A public certificate with
# HTTPS netboot never builds -- iPXE's crosscert path validates it already.
#
# And then only when the result would actually differ. buildipxe.sh does
# `git clean -fd && git reset --hard && touch crypto/rootcert.c`, so every
# invocation is a cold rebuild of eight make passes -- 10-25 minutes, on every
# install AND every update, to reproduce bytes that are usually identical.
#
# Equality against a stamp, not an ordering comparison, the same shape
# bin/fetch-plugins.sh uses for the plugin tree. Deliberately NOT its other
# clause though: there, a populated directory with no stamp means "someone else
# put this here, leave it alone". Here the same state means an install that
# predates the stamp, whose binaries came from the old always-rebuild flow --
# so a missing stamp has to mean rebuild, or the first run after this lands
# would skip the very build it exists to schedule.
_needsLocalIpxeBuild() {
    [[ ${BOOT_rebuild_ipxe_with_my_ca} == yes ]] || return 1
    _resolveIpxeTrust
    local stamp="${tftpdirdst%/}/.fog-ipxe-build"
    local want
    want="$(_ipxeBuildStampValue)"
    [[ -f $stamp && -n $want && "$(cat "$stamp" 2>/dev/null)" == "$want" ]] && return 1
    return 0
}
# Keep a pristine copy of the published binaries when we are about to replace
# them with locally built ones.
#
# The rebuild writes into the same staging tree the download unpacked into, so
# without this the stock binaries are simply gone -- and an admin who wanted to
# compare, or to fall back, had no copy and no way to get one except re-running
# the installer with the rebuild off.
#
# Copied inside $tftpdirsrc so the normal copy loop carries it to $tftpdirdst
# with everything else, and so it inherits the same ownership, SELinux labeling
# and signing sweep. No separate path to keep in step.
#
# secureboot/ is excluded, and that exclusion is load-bearing rather than tidy:
# _signLocalIpxe() prunes exactly "$tftproot/secureboot" and nothing deeper, so
# a copy of it under stock/ would fall outside the prune and FOG would add its
# own signature to Microsoft's and iPXE's signed shim and loader -- the two
# stages the whole Secure Boot chain hangs off.
_preserveStockIpxe() {
    local src="${tftpdirsrc%/}" entry base
    [[ -d $src ]] || return 0
    dots "Preserving stock iPXE binaries"
    rm -rf "${src}/stock" >>$error_log 2>&1
    mkdir -p "${src}/stock" >>$error_log 2>&1
    for entry in "$src"/*; do
        [[ -e $entry ]] || continue
        base=$(basename "$entry")
        case $base in
            stock|secureboot) continue ;;
        esac
        cp -a "$entry" "${src}/stock/" >>$error_log 2>&1
    done
    errorStat $?
}
# Which BIOS boot file DHCP should hand out, given --boot-delay.
#
# EFI takes its delay from autoexec.ipxe (_applyBootDelay). BIOS cannot: there
# is no efi_autoexec_load() on that platform, so its script is still compiled in
# and the delay still needs a separate binary. 10secdelay/ is that binary, and
# it is exactly ten seconds -- no build exists for any other value. So every
# non-zero delay maps to the same file, and the install says so rather than
# letting --boot-delay 7 look like it did something here.
_biosBootFile() {
    if [[ ${BOOT_dhcp_delay_seconds:-0} -gt 0 ]]; then
        echo "10secdelay/undionly.kkpxe"
    else
        echo "undionly.kkpxe"
    fi
}
# Is the signed Secure Boot chain actually staged?
#
# downloadipxesecureboot is deliberately non-fatal, so an install whose fetch
# failed has no $tftpdirsrc/secureboot at all. The generated DHCP config names
# the shim by default, and naming a file TFTP cannot serve breaks every UEFI
# client on the network -- so that default has to be conditional on the file
# being there.
#
# Reads the STAGING tree, not $tftpdirdst: configureDHCP runs before
# configureTFTPandPXE's copy loop, so the destination is still empty on a first
# install (or still holds the previous install's files) at the point this is
# asked. installfog.sh calls downloadipxesecureboot ahead of configureDHCP
# precisely so this has an answer -- see the comment at that call site.
#
# readlink -f for the same reason downloadipxesecureboot does it: $tftpdirsrc is
# the relative ../packages/tftp.
_sbChainStaged() {
    local src="$(readlink -f "$tftpdirsrc" 2>/dev/null)"
    [[ -n $src && -f "${src}/secureboot/snponly-shimx64.efi" ]]
}
# Which UEFI boot file DHCP should hand out, per architecture.
#
# The signed chain is the default for every x86-64 and arm64 UEFI client, not an
# opt-in for the Secure Boot ones. This used to be emitted commented out, on the
# reasoning that option 93 carries the client architecture and nothing else, so
# a DHCP request cannot say whether Secure Boot is on and a site therefore has
# to opt specific machines in.
#
# The premise is true and the conclusion does not follow. shim is an ordinary
# UEFI application that happens to carry a Microsoft signature: with Secure Boot
# on the firmware verifies it and it verifies what it loads next; with Secure
# Boot off the firmware verifies nothing and shim enforces nothing. It simply
# boots. So the signed chain is a SUPERSET -- it covers the machines that need it
# and costs the others nothing -- and there is nothing to detect.
#
# Why the boot file names the shim and not the loader: ipxe/shim carries a
# fork-only patch (automatic_next_path(), ipxe/shim 1b02ba2c) that strips a
# "-shim[arch]" infix from the path it was ITSELF fetched from and loads that,
# out of the same directory. So snponly-shimx64.efi fetches snponly.efi and
# ipxe-shimx64.efi fetches ipxe.efi -- from one signed binary staged under both
# names. Do not conclude from `strings` that the loader is hardcoded to
# ipxe.efi; that is only the DEFAULT_LOADER the patch hooks. Over TFTP the
# device path carries the filename, so the rename resolves correctly (observed);
# off an ESP it does not, which is a separate problem handled elsewhere.
#
# If this chain loads but the network never comes up, the firmware's own UEFI
# SNP is at fault -- switch the name to secureboot/ipxe-shimx64.efi for iPXE's
# built-in drivers. That is a DHCP-only change with nothing renamed
# server-side, and it is what the commented fallback in the generated config
# points at.
#
# The fallback names are not "unsigned" binaries -- _signLocalIpxe() signs every
# *.efi in the TFTP root with this server's own leaf. The difference is which
# trust root the client must already hold: the secureboot/ pair starts from
# Microsoft's signature and loads on a machine that has never met this server,
# while snponly.efi needs THIS server's certificate enrolled first. That is the
# real reason the shim chain is the better default, and it is stronger than
# "signed vs not".
#
# No 32-bit case on purpose: there is no Microsoft-signed ia32 shim to start a
# chain from and no 32-bit MokManager to enroll one with, so i386-efi clients must
# have Secure Boot disabled to netboot at all.
#
# --boot-delay needs no handling here either. EFI takes its delay from
# autoexec.ipxe (_applyBootDelay), so unlike _biosBootFile there is no second
# binary to point at.
_uefiBootFile() {
    case "$1" in
        arm64)
            if _sbChainStaged; then
                echo "secureboot/arm64-efi/snponly-shimaa64.efi"
            else
                echo "arm64-efi/snponly.efi"
            fi
            ;;
        *)
            if _sbChainStaged; then
                echo "secureboot/snponly-shimx64.efi"
            else
                echo "snponly.efi"
            fi
            ;;
    esac
}
# The pre-DHCP delay stanza, in the one form every copy of autoexec.ipxe uses.
#
# Some switches take several seconds to bring a port out of STP listening or out
# of powersave, and iPXE's first DHCP attempt goes out before that. FOG's answer
# used to be a second set of compiled binaries (10secdelay/); on EFI it is two
# lines of text, which is what makes the commented arm below possible at all --
# an admin who hits this at 2am uncomments one line instead of reinstalling the
# server, and there is nothing to rebuild.
#
# ONE generator for both surfaces: the server's own TFTP copy via
# _applyBootDelay(), and the ESP archives via _espAutoexecScript(). They used to
# be two separate bodies of text kept in step by a comment asking future editors
# to keep them in step, and they had already drifted -- the ESP copy shipped the
# commented arm and the TFTP copy shipped nothing at all, so a site that read the
# USB stick and then the netboot script found two different scripts. The note in
# _espAutoexecScript() already described the commented arm as what the TFTP copy
# did; it does now.
#
# Both arms carry the same sentinels, which is what makes _applyBootDelay()'s
# rewrite idempotent in both directions -- raising, lowering and clearing the
# delay all replace exactly one block. The cost is that an uncommented edit
# INSIDE the block is reverted by the next install, so the commented arm says so
# and names --boot-delay as the way to make it stick. An admin's own sleep
# written outside the block is never touched.
#
# The delay cannot be a UI setting, before anyone tries: it has to run before
# DHCP, and the web UI is only reachable after DHCP has succeeded.
_bootDelayBlock() {
    local delay="${BOOT_dhcp_delay_seconds:-0}"
    if [[ $delay -gt 0 ]]; then
        cat <<BOOTDELAY
# FOG-BOOT-DELAY-BEGIN  (installfog.sh --boot-delay; do not edit by hand)
echo Sleeping ${delay} seconds to wait for STP/Powersave to switchoff and on
sleep ${delay}
# FOG-BOOT-DELAY-END
BOOTDELAY
    else
        cat <<'BOOTNODELAY'
# FOG-BOOT-DELAY-BEGIN  (no pre-DHCP delay configured)
# If your switch runs STP or port power-save and the link is not up by the time
# iPXE first asks for DHCP, uncomment the two lines below. That fixes this copy
# now, with nothing to rebuild. installfog.sh rewrites this whole block on every
# run, so make it permanent by reinstalling with --boot-delay <seconds>, which
# writes it live into every copy of this script and points legacy BIOS clients at
# the 10-second build at the same time.
#echo Sleeping 10 seconds to wait for STP/Powersave to switchoff and on
#sleep 10
# FOG-BOOT-DELAY-END
BOOTNODELAY
    fi
}
# Write the pre-DHCP delay stanza into the TFTP copy of autoexec.ipxe.
#
# ALWAYS written, whether or not --boot-delay was given: _bootDelayBlock()
# returns the commented arm when it was not, so the netboot script carries the
# same self-documenting escape hatch the ESP archives have always carried. It
# used to be written only when a delay was set, which left an admin diagnosing a
# 2am STP problem reading a file that says nothing about the sleep it needs.
#
# Bracketed by sentinel comments rather than matched on the sleep line. An
# admin may have added their own sleep for their own reason, and a bare
# /^sleep /d would silently eat it. The sentinels also make the option
# idempotent in both directions: the block is removed and rewritten every run,
# so lowering the delay or clearing it works exactly like raising it.
#
# Written in place with a redirect rather than sed -i or a temp-and-mv, because
# by the time this runs the file may already be hard-linked into i386-efi/,
# arm64-efi/ and the secureboot tree. A rename would replace the inode and
# leave those links pointing at the old content -- exactly the drift the links
# exist to prevent.
#
# Legacy BIOS cannot be served this way: it has no efi_autoexec_load(), so its
# script is still compiled in and 10secdelay/ still holds a BIOS build.
# configureDHCP points BIOS clients there when a delay is set.
_applyBootDelay() {
    local script="${tftpdirdst%/}/autoexec.ipxe"
    [[ -f $script ]] || return 0
    local delay="${BOOT_dhcp_delay_seconds:-0}"
    local block
    block="$(_bootDelayBlock)"
    local tmp
    tmp=$(mktemp) || return 0
    awk -v block="$block" '
        # Prefix match, not anchored: the BEGIN line carries a trailing note
        # that differs between the two arms, so a $-anchored pattern never
        # matches what this same function writes and the blocks stack on every
        # run instead of being replaced.
        /^# FOG-BOOT-DELAY-BEGIN/ { skip = 1 }
        skip { if ( $0 ~ /^# FOG-BOOT-DELAY-END/ ) skip = 0; next }
        { print }
        # After the first line this actually PRINTS, not NR == 1: if a previous
        # run ever left a sentinel block at the very top, line 1 is consumed by
        # the skip rule above and an NR test would drop the block entirely.
        !inserted { print block; inserted = 1 }
    ' "$script" > "$tmp" 2>>$error_log
    # Never truncate the real script on a failed rewrite -- an empty
    # autoexec.ipxe is a server that netboots nothing.
    if [[ -s $tmp ]]; then
        cat "$tmp" > "$script" 2>>$error_log
    fi
    rm -f "$tmp" >>$error_log 2>&1
    if [[ $delay -gt 0 && $delay -ne 10 ]]; then
        echo " * NOTE: --boot-delay $delay applies to EFI clients, which read"
        echo "   the sleep from autoexec.ipxe. Legacy BIOS embeds its script, so"
        echo "   DHCP points it at 10secdelay/, which is exactly 10 seconds."
    fi
}
# Remove the EFI artifacts that v2.0.0-fog.8 stopped shipping.
#
# Two trees are stale after the EMBED-less change, and _copyIpxeTree() cannot
# clear either of them -- it only ever writes, so anything a release stops
# shipping stays on disk forever, getting quietly older with each upgrade while
# looking maintained.
#
#   autoexec/            a duplicate set of EMBED-less binaries, opted into by
#                        pointing DHCP at autoexec/<file>. The TFTP root IS that
#                        build now, so the directory is a stale copy of it.
#
#   10secdelay/*.efi     the real hazard. Those are EMBED-marked binaries, and
#   10secdelay/{i386,arm64}-efi/
#                        the root now carries an autoexec.ipxe.
#                        efi_autoexec_network() falls back to /autoexec.ipxe
#                        when the binary's own directory has none -- and
#                        10secdelay/ has none -- so one of these downloads the
#                        script, never runs it (first_image() returns its
#                        embedded one), nothing unregisters it, and
#                        initrd_load_all() concatenates 2 KB of iPXE script
#                        ahead of init.xz. The client panics with
#
#                            VFS: Unable to mount root fs on "/dev/ram0"
#
#                        (forums #18213). Leaving them is not "stale but
#                        harmless"; it is an upgrade that breaks a path which
#                        worked before the upgrade.
#
# The BIOS files in 10secdelay/ stay. They are why the directory still exists:
# legacy BIOS has no efi_autoexec_load(), so its delay is still a separate
# build rather than a --boot-delay edit to autoexec.ipxe.
#
# stock/ gets the same treatment. _preserveStockIpxe() rebuilds its source copy
# every run, but the copy already in $tftpdirdst is only ever added to.
#
# A site whose DHCP still names one of these has to be told, because TFTP
# answers a missing file with an error the client renders as a generic PXE
# failure with no clue in it. Warn and name the replacement rather than failing
# the install: the DHCP server handing out that name is frequently not this
# machine, so this run cannot fix it and should not block on it.
#
# An admin who deliberately placed their own .efi under 10secdelay/ loses it.
# That is accepted: every EFI binary that can legitimately live there now is one
# that would panic its client, and a file that cannot be booted safely is not
# worth preserving over one that can.
_retireStaleEfiPaths() {
    # Never let an unset tftpdirdst turn any of this into rm -rf /autoexec.
    [[ -n $tftpdirdst ]] || return 0
    local root="${tftpdirdst%/}" d base touched=0
    for d in "$root/autoexec" "$root/stock/autoexec"; do
        [[ -d $d ]] || continue
        touched=1
        rm -rf "$d" >>$error_log 2>&1
    done
    for base in "$root/10secdelay" "$root/stock/10secdelay"; do
        [[ -d $base ]] || continue
        for d in "$base/i386-efi" "$base/arm64-efi"; do
            [[ -d $d ]] || continue
            touched=1
            rm -rf "$d" >>$error_log 2>&1
        done
        # -maxdepth 1 so the subdirectory sweep above owns those, and this only
        # ever sees the loose files beside the BIOS builds.
        if [[ -n $(find "$base" -maxdepth 1 -type f -name '*.efi' -print -quit 2>/dev/null) ]]; then
            touched=1
            find "$base" -maxdepth 1 -type f -name '*.efi' -delete >>$error_log 2>&1
        fi
    done
    [[ $touched -eq 1 ]] || return 0
    dots "Removing iPXE paths retired in v2.0.0-fog.8"
    echo "Done"
    echo " * autoexec/ is gone: every EFI binary in the TFTP root reads"
    echo "   autoexec.ipxe now, so the duplicate tree served no purpose."
    echo " * 10secdelay/ keeps its BIOS builds and has lost its EFI ones. On"
    echo "   EFI the delay is installfog.sh --boot-delay, which writes a sleep"
    echo "   into autoexec.ipxe; an EMBED-marked .efi sitting next to a root"
    echo "   autoexec.ipxe panics the client it boots."
    echo " * If any DHCP server hands out a boot filename starting \"autoexec/\","
    echo "   drop that prefix -- autoexec/snponly.efi becomes snponly.efi. If one"
    echo "   names 10secdelay/<something>.efi, point it at the same file without"
    echo "   the 10secdelay/ prefix and set --boot-delay instead."
}
# Copy the staged tree into place WITHOUT destroying an admin's own binaries.
#
# The historic loop was `find -type f -exec cp -Rfv {} $tftpdirdst/{}`, which
# overwrites unconditionally. A file whose name is not in the staging tree
# survives -- nothing deletes it -- but any of the ~55 names FOG does ship was
# destroyed on every single run. That is the whole set an admin is most likely
# to have replaced: snponly.efi, ipxe.efi, undionly.kkpxe.
#
# The customization machinery that would have protected it covers only the WEB
# tree; both backupPreservedCustomizations and restorePreservedCustomizations
# hardcode $webdirdest/service/ipxe. The TFTP tree was assumed safe "by
# construction", which holds for new names and is false for colliding ones.
#
# So: record the checksum of every file FOG writes here, and skip a destination
# whose current checksum no longer matches what FOG last wrote.
#
# A sidecar manifest rather than the fogsum XATTR the kernel path uses, and the
# difference matters. That mechanism no-ops entirely when the `attr` binary is
# absent or the filesystem does not carry extended attributes -- it degrades to
# "unknown", which is correctly treated as "not modified". For kernels that is
# survivable because they are backed up regardless. Here there is no backup, so
# a silent degradation means silently overwriting the admin's binary while
# reporting that it is protected. A TFTP root is also exactly the sort of path
# that ends up on a mount without xattr support.
#
# The manifest lists only checksums of public boot binaries, so serving it over
# TFTP alongside them gives nothing away.
#
# Protection begins with the FIRST run after this lands: before that there is no
# manifest, nothing can be compared, and a file with no entry is treated as
# FOG's -- the same "unknown is not modified" rule the kernel path uses, and the
# only choice that lets an ordinary upgrade still update anything.
_copyIpxeTree() {
    local src="${tftpdirsrc%/}" dst="${tftpdirdst%/}"
    local manifest="${dst}/.fog-ipxe-manifest"
    local rel target sum recorded have
    declare -A fogsums=()
    ipxeSkipped=""
    [[ -d $src ]] || return 0
    if [[ -f $manifest ]]; then
        while IFS='|' read -r sum rel; do
            [[ -n $sum && -n $rel ]] && fogsums["$rel"]="$sum"
        done < "$manifest"
    fi
    while IFS= read -r rel; do
        rel="${rel#./}"
        [[ -z $rel || $rel == "." ]] && continue
        mkdir -p "${dst}/${rel}" >>$error_log 2>&1
    done < <(cd "$src" && find . -type d 2>>$error_log)
    local staging="${manifest}.new"
    : > "$staging" 2>>$error_log
    while IFS= read -r rel; do
        rel="${rel#./}"
        [[ -z $rel ]] && continue
        # Never manage our own bookkeeping.
        case $rel in
            .fog-ipxe-manifest|.fog-ipxe-manifest.new|.fog-ipxe-build) continue ;;
        esac
        target="${dst}/${rel}"
        recorded="${fogsums[$rel]:-}"
        if [[ -f $target && -n $recorded ]]; then
            have=$(sha256sum "$target" 2>/dev/null | cut -d' ' -f1)
            if [[ -n $have && $have != "$recorded" ]]; then
                ipxeSkipped="${ipxeSkipped}${ipxeSkipped:+ }${rel}"
                # Carry the ORIGINAL sum forward, not the admin's. Recording
                # theirs would make the file match on the next run and be
                # quietly overwritten then instead.
                printf '%s|%s\n' "$recorded" "$rel" >> "$staging" 2>>$error_log
                continue
            fi
        fi
        cp -f "${src}/${rel}" "$target" >>$error_log 2>&1 || continue
        sum=$(sha256sum "$target" 2>/dev/null | cut -d' ' -f1)
        [[ -n $sum ]] && printf '%s|%s\n' "$sum" "$rel" >> "$staging" 2>>$error_log
    done < <(cd "$src" && find . -type f 2>>$error_log)
    mv -f "$staging" "$manifest" >>$error_log 2>&1
    return 0
}
downloadipxe() {
    # iPXE binaries used to be 70 files committed to this repository. They are
    # now a release asset, verified and unpacked into the same staging tree the
    # copy loop in configureTFTPandPXE reads, so everything downstream is
    # unchanged. One tarball rather than 85 loose assets: release assets cannot
    # hold directories, and this tree has i386-efi/, arm64-efi/ and autoexec/
    # subdirectories worth keeping. See GH-959.
    local tarball="fog-ipxe-${ipxeVer}.tar.gz"
    local dest=$(readlink -f $tftpdirsrc)
    # Never unpack the published binaries over a local build.
    #
    # --rebuild-ipxe-with-my-ca compiles this server's CA into the binary, the
    # only thing that lets iPXE fetch boot.php over TLS from a private CA. That
    # build writes into this same staging tree, so unpacking here first destroys
    # it -- and because the old stamp did not describe the staged bytes,
    # _needsLocalIpxeBuild() then read its own stamp as "already built", skipped
    # the rebuild, and let the published binaries through to the TFTP root. The
    # first install worked and every install after it silently broke netboot.
    #
    # "No build needed" is now exactly the condition under which unpacking must
    # not happen, because it can only be true when the built binaries are still
    # sitting here. Every other state -- a new release, a changed CA, a staging
    # tree someone emptied -- fails that test and unpacks as it always did.
    if [[ ${BOOT_rebuild_ipxe_with_my_ca} == yes ]] && ! _needsLocalIpxeBuild; then
        dots "Downloading iPXE binaries (${ipxeVer})"
        echo "Kept local build"
        return 0
    fi
    dots "Downloading iPXE binaries (${ipxeVer})"
    # Downloaded on EVERY install, including one that is about to rebuild.
    #
    # This used to skip whenever httpproto was https, on the reasoning that a
    # rebuild overwrites these anyway. Two things were wrong with that. The
    # obvious one: httpproto had nothing to do with whether a rebuild happens.
    # The one that mattered more: it meant a rebuilding server never had a
    # pristine copy of the published binaries at all, so there was nothing to
    # preserve into stock/ and no way back to a stock binary short of
    # re-running the installer with the rebuild turned off.
    if ! fetchipxeasset "$tarball" "$dest"; then
        # Guidance first: errorStat exits before returning unless $exitFail is
        # set, so anything printed after it is never seen.
        echo "Failed!"
        echo " * Could not download $tarball from ${ipxeurl}/${ipxeVer}/"
        echo " * For an offline install, place the extracted binaries in $dest"
        [[ -z $exitFail ]] && exit 1
        return 1
    fi
    errorStat 0
}
downloadplugins() {
    # The bundled plugins are a release of FOGProject/fog-plugins now, not a
    # tree in this repository (ADR 0009). Fetched into packages/web/lib/plugins
    # BEFORE configureHttpd lays the web package, because that is the copy the
    # web root is made from -- fetching afterward would put them somewhere
    # nothing reads and the next upgrade's rm -rf would take them anyway.
    #
    # The work lives in bin/fetch-plugins.sh rather than here so a developer
    # with a fresh clone can populate their tree without running an install.
    # This wrapper only supplies the installer's messaging and its idea of what
    # is fatal.
    dots "Downloading plugins (${pluginsVer})"
    if [[ ! -x ../bin/fetch-plugins.sh ]]; then
        # Not fatal. An install from a release tarball has the plugins already
        # unpacked in the tree and no reason to carry the fetcher.
        echo "Skipped (no fetcher)"
        return 0
    fi
    if ! pluginsgit="$pluginsgit" pluginsurl="$pluginsurl" pluginsVer="$pluginsVer" \
        inetConnectTimeout="$inetConnectTimeout" \
        ../bin/fetch-plugins.sh --quiet >>$error_log 2>&1
    then
        # Guidance first: errorStat exits before returning unless $exitFail is
        # set, so anything printed after it is never seen.
        echo "Failed!"
        echo " * Could not install the plugins (${pluginsVer}) from $pluginsgit"
        echo " * For an offline install, place the plugin directories in"
        echo " *   packages/web/lib/plugins/ and re-run"
        # The fetcher fails for two unrelated reasons -- it could not reach a
        # verified release, or it could not write the tree -- and only the
        # first is what the guidance above describes. Both write their reason
        # to stderr, which --quiet does not touch, so point at the log rather
        # than guessing which one happened.
        echo " * Full error in $error_log"
        [[ -z $exitFail ]] && exit 1
        return 1
    fi
    errorStat 0
}
downloadipxesecureboot() {
    # The Secure Boot chain needs binaries FOG cannot build, because they are
    # signed by keys FOG does not hold: Microsoft's, for the shim, and iPXE's,
    # for the loader it chains to. fog-ipxe republishes upstream's byte for
    # byte, hash- and signer-verified at release time, as a second asset. This
    # is what makes the chain available without every install reaching out to
    # two third-party release URLs unsupervised.
    #
    # It unpacks into the same staging tree as everything else, so it lands at
    # $tftpdirdst/secureboot/ through the copy loop already in
    # configureTFTPandPXE. Staged unconditionally and served to nobody: a client
    # only ever sees it if DHCP is pointed at secureboot/snponly-shimx64.efi,
    # so the cost of having it present is a few MB and the cost of NOT having it
    # is an admin hand-assembling a signed boot chain from two upstream projects.
    # See GH-960.
    local tarball="fog-ipxe-secureboot-${ipxeVer}.tar.gz"
    local dest=$(readlink -f $tftpdirsrc)
    dots "Downloading iPXE Secure Boot binaries (${ipxeVer})"
    # Staged in EVERY mode. This used to skip whenever httpproto was https,
    # under the heading "Secure Boot and HTTPS are mutually exclusive here" --
    # the reasoning being that upstream's generic signed binaries cannot carry
    # this server's CA, and a signed binary cannot be rebuilt without
    # invalidating the signature.
    #
    # The premise is true and the conclusion does not follow, which testing has
    # now established both ways:
    #
    #   * Upstream iPXE defines CROSSCERT unconditionally in config/crypto.h,
    #     and FOG's overlay replaces only general.h/settings.h/console.h. So
    #     upstream's signed binaries DO validate a publicly-issued certificate
    #     at boot, with no rebuild and no embedded CA. Confirmed in production
    #     against a Let's Encrypt vhost.
    #   * And where the certificate is private, netboot simply stays on HTTP --
    #     which has nothing to do with whether a Secure Boot chain is available
    #     for the machines that need one.
    #
    # The practical effect of the old gate was that every -S install staged no
    # Secure Boot binaries at all, so the feature was missing precisely on the
    # servers whose admins had gone furthest out of their way to configure TLS.
    # See #1116 finding 1.
    # Deliberately NOT fatal, unlike downloadipxe above. A missing Secure Boot
    # set costs nothing to any client that boots today, so failing the whole
    # install over it would be a regression for every site that does not use it.
    if ! fetchipxeasset "$tarball" "$dest"; then
        echo "Skipped (unavailable)"
        echo " * Could not download $tarball from ${ipxeurl}/${ipxeVer}/"
        echo " * Secure Boot clients will not have a signed chain to boot; every"
        echo " *   other client is unaffected. Re-run the installer to retry."
        # Said out loud because it changes what the DHCP config written a moment
        # later contains. UEFI clients normally get the signed shim; with nothing
        # staged, _uefiBootFile falls back to the unsigned names so the config
        # cannot name a file TFTP has no copy of. That is the safe outcome, but
        # it is silent otherwise -- an admin who expected Secure Boot to work
        # would find the old boot file in a config they did not edit.
        echo " * Any DHCP configuration written by this run therefore names the"
        echo " *   TFTP-root UEFI boot files, not secureboot/snponly-shimx64.efi."
        echo " *   Those are signed with this server's own key, so a Secure Boot"
        echo " *   client needs that certificate enrolled before it will load one."
        return 0
    fi
    errorStat 0
}
configureTFTPandPXE() {
    # Fills $tftpdirsrc, which is now a staging directory rather than tracked
    # build output, so this has to happen before anything reads from it.
    #
    # downloadipxesecureboot is NOT called here. It runs earlier, from
    # installfog.sh, because configureDHCP has to know whether the signed chain
    # is staged before it writes a boot filename -- see _sbChainStaged. Both
    # assets untar additively into the same staging tree (fetchipxeasset only
    # does mkdir -p + tar -xzf -C, it never clears the destination), so their
    # relative order does not matter and nothing reads the tree until the copy
    # loop below.
    downloadipxe || return 1
    [[ -d ${tftpdirdst}.prev ]] && rm -rf ${tftpdirdst}.prev >>$error_log 2>&1
    [[ ! -d ${tftpdirdst} ]] && mkdir -p $tftpdirdst >>$error_log 2>&1
    [[ -e ${tftpdirdst}.fogbackup ]] && rm -rf ${tftpdirdst}.fogbackup >>$error_log 2>&1
    [[ -d $tftpdirdst && ! -d ${tftpdirdst}.prev ]] && mkdir -p ${tftpdirdst}.prev >>$error_log 2>&1
    [[ -d ${tftpdirdst}.prev ]] && cp -Rf $tftpdirdst/* ${tftpdirdst}.prev/ >>$error_log 2>&1
    PKI_client_cert_dir=${PKI_client_cert_dir//\/$}
    if _needsLocalIpxeBuild; then
        # The one case a release asset cannot serve: CERT=/TRUST= bake this
        # server's CA into the binary so iPXE can fetch boot.php over TLS,
        # which makes it a per-server artifact by definition. Everything else
        # about the build is identical everywhere, which is why every other
        # install just downloads. See GH-959.
        # Said before the 10-25 minutes start, not after. A rebuilt binary is
        # not upstream's SIGNED one, so it cannot be the first stage of a
        # Secure Boot chain -- the shim will only load what its embedded
        # certificate vouches for. The chain has to hand off to a MOK-signed
        # FOG build instead, which means enrolling the MOK on each machine
        # FIRST. Rebuilding therefore makes Secure Boot onboarding harder, not
        # easier, and an existing HTTPS install is usually better off moving
        # away from it.
        echo
        echo " * Rebuilding iPXE with your CA embedded (--rebuild-ipxe-with-my-ca)."
        echo "   This takes 10-25 minutes and has no warm path."
        echo "   The result is NOT upstream's signed binary, so Secure Boot"
        echo "   machines must have this server's MOK enrolled before they can"
        echo "   netboot at all. If your web certificate chains to a PUBLIC"
        echo "   root, you do not need this -- use --public-web-cert instead."
        echo
        prepareiPXEsource || return 1
        # Before the build, while the staging tree still holds what the release
        # asset unpacked. Afterward these bytes no longer exist anywhere.
        _preserveStockIpxe
        dots "Compiling iPXE binaries trusting your SSL certificate"
        _resolveIpxeTrust
        # Second argument is the output directory: build straight into the
        # staging tree the copy loop below already reads, so a locally built
        # binary lands exactly where a downloaded one would.
        "${buildipxesrc}/buildipxe.sh" "${ipxetrust}" "$(readlink -f $tftpdirsrc)" >>$workingdir/error_logs/fog_ipxe-build_${version}.log 2>&1
        local buildstat=$?
        local ipxebuildlog="$workingdir/error_logs/fog_ipxe-build_${version}.log"
        # errorStat tails $error_log, and this build does not write there -- its
        # output goes to the file above. Tailing the wrong log printed five lines
        # of unrelated noise from earlier steps (a DB backup line, the HTML body
        # of the schema POST) and threw away the exit status, which is the one
        # value that identifies the failure: buildipxe.sh returns a distinct
        # status per stage -- 39/41 upstream checkout and patching, 40/48 BIOS,
        # 79/80/91/95 x86 EFI, 82/93/97 the arm64 cross-compile. Report both, so
        # a failed build can be diagnosed from what the installer prints instead
        # of from a file nobody is told to look at.
        if [[ $buildstat -ne 0 ]]; then
            echo "Failed! (buildipxe.sh exit $buildstat)"
            if [[ -z $exitFail ]]; then
                echo
                echo " * The iPXE build writes its own log, separate from $error_log."
                echo " * Full build output: $ipxebuildlog"
                echo " * Please include that file, and the exit status above, when"
                echo "   reporting this."
                echo
                tail -n 20 "$ipxebuildlog"
                exit $buildstat
            fi
        else
            errorStat 0
        fi
        # Recorded only on success, and only after the copy loop below has
        # actually put the result in place -- a stamp written for a build that
        # failed would suppress every retry.
        [[ $buildstat -eq 0 ]] && ipxeBuildStampPending="$(_ipxeBuildStampValue)"
        cd $workingdir
    fi
    _copyIpxeTree
    if [[ -n $ipxeBuildStampPending ]]; then
        printf '%s' "$ipxeBuildStampPending" > "${tftpdirdst%/}/.fog-ipxe-build" 2>>$error_log
        ipxeBuildStampPending=""
    fi
    # Named, not counted. "3 files preserved" tells an admin nothing they can
    # act on; declining to update snponly.efi is something they need to know
    # about by name, because it means their replacement is now the one every
    # PXE client gets and FOG's newer copy is not being installed.
    if [[ -n $ipxeSkipped ]]; then
        local skipped
        echo " * Kept your own copies of these iPXE files (not overwritten):"
        for skipped in $ipxeSkipped; do
            echo "     ${skipped}"
        done
        echo "   Delete one to have FOG's version installed on the next run."
    fi
    # autoexec.ipxe is now THE boot script for every EFI binary FOG ships, and
    # the TFTP root is the primary copy.
    #
    # Since fog-ipxe v2.0.0-fog.8 no EFI target is built with EMBED=, so each
    # one downloads autoexec.ipxe and EXECUTES it. That inverts the rule this
    # block used to enforce. Previously the root held EMBED-marked binaries,
    # which download the script and then never run it -- first_image() returns
    # the embedded one ahead of it, nothing unregisters it, and at "boot"
    # initrd_load_all() concatenates 2 KB of iPXE script ahead of init.xz, so
    # the kernel finds no compression magic and panics with
    #
    #     VFS: Unable to mount root fs on "/dev/ram0" or unknown-block(1,0)
    #
    # (forums #18213). An EMBED-less binary executes the script instead, and
    # image_exec() unregisters it for the duration, so it is gone before boot
    # runs. Removing EMBED from every EFI target is what makes a root
    # autoexec.ipxe safe -- and necessary, because efi_autoexec_network() falls
    # back to /autoexec.ipxe when the binary's own directory has none.
    #
    # Legacy BIOS still embeds its script and is unaffected: there is no
    # efi_autoexec_load() on that platform, so a root autoexec.ipxe is a file it
    # never asks for.
    #
    # Hard link, not copy: every path is meant to be one script, and a link
    # keeps them from drifting -- an admin who edits the boot logic should not
    # have to know how many copies exist. Not a symlink, because some TFTP
    # daemons refuse to follow those while a hard link is indistinguishable from
    # a regular file to every daemon.
    #
    # Relinked unconditionally on every run: the copy loop truncates in place
    # and the link usually survives, but that is cp's behavior rather than a
    # guarantee, and an editor that writes-and-renames breaks it. ln -f is
    # idempotent.
    # Before the links, so every path picks the delay up from one rewrite.
    _applyBootDelay
    if [[ -f $tftpdirdst/autoexec.ipxe ]]; then
        local autoexecpath
        for autoexecpath in \
            $tftpdirdst/i386-efi/autoexec.ipxe \
            $tftpdirdst/arm64-efi/autoexec.ipxe \
            $tftpdirdst/secureboot/autoexec.ipxe \
            $tftpdirdst/secureboot/arm64-efi/autoexec.ipxe; do
            # Skip rather than create: the secureboot directories only exist if
            # that asset was staged, and an autoexec.ipxe with no binary beside
            # it serves no one.
            [[ -d $(dirname $autoexecpath) ]] || continue
            ln -f $tftpdirdst/autoexec.ipxe $autoexecpath >>$error_log 2>&1
        done
    fi
    # _copyIpxeTree() hashed autoexec.ipxe as it laid it down; _applyBootDelay
    # then rewrote it, and the links above propagated that to every other path.
    # Without re-stamping, the next run compares the delayed file against the
    # pristine sum, reads the difference as "the admin replaced this", and stops
    # updating the boot script for good -- the same trap _signLocalIpxe() hits
    # and for the same reason. Naming a path the manifest does not carry is
    # harmless: only existing lines are rewritten.
    _restampIpxeManifest "${tftpdirdst%/}" \
        autoexec.ipxe \
        i386-efi/autoexec.ipxe \
        arm64-efi/autoexec.ipxe \
        secureboot/autoexec.ipxe \
        secureboot/arm64-efi/autoexec.ipxe
    _retireStaleEfiPaths
    chown -R ${SVC_user} $tftpdirdst >>$error_log 2>&1
    chown -R ${SVC_user} $webdirdest/service/ipxe >>$error_log 2>&1
    find $tftpdirdst -type d -exec chmod 755 {} \; >>$error_log 2>&1
    # management/logs is pruned: this runs AFTER configureHttpd() on a full
    # install, and a flat 755 here would strip the setgid bit that makes a
    # root daemon's log file carry the web group. Losing it denies the web
    # user today's log for the life of the install.
    #
    # ${webdirdest%/} rather than $webdirdest: the variable always carries a
    # trailing slash, find renders children under the start point verbatim,
    # and "<dir>//management/logs" matches nothing -- so the prune would
    # silently do nothing and the setgid bit would be stripped anyway.
    find $webdirdest -type d -path "${webdirdest%/}/management/logs" -prune -o \
        -type d -exec chmod 755 {} \; >>$error_log 2>&1
    find $tftpdirdst ! -type d -exec chmod 655 {} \; >>$error_log 2>&1
    configureDefaultiPXEfile
    # GH-963: must come AFTER every file is in place, configureDefaultiPXEfile
    # included. restorecon only fixes what already exists, so anything written
    # after this call keeps whatever label it inherited.
    #
    # tftpd_t is permitted to read tftpdir_t, tftpdir_rw_t and var_t. The first
    # two are what the distro packages use; var_t covers Arch's /srv/tftp and
    # Alpine's /var/tftpboot, which resolve there and work as-is.
    setSELinuxContext "$tftpdirdst" tftpdir_t tftpdir_rw_t var_t
    dots 'Setting up and starting TFTP Server'
    case $systemctl in
        yes)
            # make sure xinetd is off for all systemd distros as we don't use it anymore
            systemctl is-enabled --quiet xinetd 2>/dev/null && systemctl disable xinetd >>$error_log 2>&1 || true
            systemctl is-active --quiet xinetd && systemctl stop xinetd >>$error_log 2>&1 || true
            if [[ -f /etc/xinetd.d/tftp ]]; then
                rm -f /etc/xinetd.d/tftp
            fi
            if [[ ${FOG_os_id} -eq 2 && -f $tftpconfigupstartdefaults ]]; then
                mv -fv "$tftpconfigupstartdefaults" "${tftpconfigupstartdefaults}.${timestamp}" >>$error_log 2>&1
                echo -e "# /etc/default/tftpd-hpa\n# FOG Modified version\nTFTP_USERNAME=\"root\"\nTFTP_DIRECTORY=\"/tftpboot\"\nTFTP_ADDRESS=\":69\"\nTFTP_OPTIONS=\"${BOOT_tftp_options:+${BOOT_tftp_options} }-s\"" > "$tftpconfigupstartdefaults"
                diffconfig "$tftpconfigupstartdefaults"
                systemctl is-enabled --quiet tftpd-hpa && true || systemctl enable tftpd-hpa >>$error_log 2>&1
                systemctl is-active --quiet tftpd-hpa && systemctl stop tftpd-hpa >>$error_log 2>&1 || true
                systemctl is-active --quiet tftpd-hpa && true || systemctl start tftpd-hpa >>$error_log 2>&1
                systemctl status tftpd-hpa >>$error_log 2>&1
            else
                if [[ -f /etc/systemd/system/fog-tftp.service ]]; then
                    mv -fv /etc/systemd/system/fog-tftp.service "/etc/systemd/system/fog-tftp.service.${timestamp}" >>$error_log 2>&1
                fi
                echo -e "[Unit]\nDescription=Tftp Server\nRequires=fog-tftp.socket\nDocumentation=man:in.tftpd\n\n[Service]\nExecStart=/usr/sbin/in.tftpd ${BOOT_tftp_options:+${BOOT_tftp_options} }-s ${tftpdirdst}\nStandardInput=socket\n\n[Install]\nAlso=fog-tftp.socket" > /etc/systemd/system/fog-tftp.service
                diffconfig "/etc/systemd/system/fog-tftp.service"
                find /usr/lib/systemd/system -maxdepth 1 \( -name "tftp.socket" -o -name "tftpd.socket" \) -exec cp -v {} /etc/systemd/system/fog-tftp.socket \; -quit >>$error_log 2>&1
                systemctl daemon-reload
                systemctl is-enabled --quiet fog-tftp.socket && true || systemctl enable fog-tftp.socket >>$error_log 2>&1
                systemctl is-active --quiet fog-tftp.socket && systemctl stop fog-tftp.socket >>$error_log 2>&1 || true
                systemctl is-active --quiet fog-tftp.socket && true || systemctl start fog-tftp.socket >>$error_log 2>&1
                systemctl status fog-tftp.socket >>$error_log 2>&1
            fi
            ;;
        *)
            if [[ ${FOG_os_id} -eq 2 && -f $tftpconfigupstartdefaults ]]; then
                mv -fv "$tftpconfigupstartdefaults" "${tftpconfigupstartdefaults}.${timestamp}" >>$error_log 2>&1
                echo -e "# /etc/default/tftpd-hpa\n# FOG Modified version\nTFTP_USERNAME=\"root\"\nTFTP_DIRECTORY=\"/tftpboot\"\nTFTP_ADDRESS=\":69\"\nTFTP_OPTIONS=\"${BOOT_tftp_options:+${BOOT_tftp_options} }-s\"" > "$tftpconfigupstartdefaults"
                diffconfig "$tftpconfigupstartdefaults"
                sysv-rc-conf xinetd off >>$error_log 2>&1
                service xinetd stop >>$error_log 2>&1
                sysv-rc-conf tftpd-hpa on >>$error_log 2>&1
                service tftpd-hpa stop >>$error_log 2>&1
                service tftpd-hpa start >>$error_log 2>&1
            elif [[ ${FOG_os_id} -eq 2 ]]; then
                sysv-rc-conf xinetd on >>$error_log 2>&1
                $initdpath/xinetd stop >>$error_log 2>&1
                $initdpath/xinetd start >>$error_log 2>&1
            elif [[ ${FOG_os_id} -eq 3 ]]; then
                # rc-update, not just start: without it TFTP is running when
                # the install finishes and gone after the first reboot, so PXE
                # stops at "PXE-E32: TFTP open timeout" on a server nobody has
                # touched since it worked. See #863.
                rc-update add in.tftpd default >>$error_log 2>&1
                $initdpath/in.tftpd stop >>$error_log 2>&1
                $initdpath/in.tftpd start >>$error_log 2>&1
            else
                chkconfig xinetd on >>$error_log 2>&1
                service xinetd stop >>$error_log 2>&1
                service xinetd start >>$error_log 2>&1
                service xinetd status >>$error_log 2>&1
            fi
            ;;
    esac
    errorStat $?
}
configureMinHttpd() {
    configureHttpd
    echo "<?php" > "$webdirdest/management/index.php"
    echo "/**" >> "$webdirdest/management/index.php"
    echo " * The main index presenter" >> "$webdirdest/management/index.php"
    echo " *" >> "$webdirdest/management/index.php"
    echo " * PHP version 5" >> "$webdirdest/management/index.php"
    echo " *" >> "$webdirdest/management/index.php"
    echo " * @category Index_Page" >> "$webdirdest/management/index.php"
    echo " * @package  FOGProject" >> "$webdirdest/management/index.php"
    echo " * @author   Tom Elliott <tommygunsster@gmail.com>" >> "$webdirdest/management/index.php"
    echo " * @license  http://opensource.org/licenses/gpl-3.0 GPLv3" >> "$webdirdest/management/index.php"
    echo " * @link     https://fogproject.org" >> "$webdirdest/management/index.php"
    echo " */" >> "$webdirdest/management/index.php"
    echo "/**" >> "$webdirdest/management/index.php"
    echo " * The main index presenter" >> "$webdirdest/management/index.php"
    echo " *" >> "$webdirdest/management/index.php"
    echo " * @category Index_Page" >> "$webdirdest/management/index.php"
    echo " * @package  FOGProject" >> "$webdirdest/management/index.php"
    echo " * @author   Tom Elliott <tommygunsster@gmail.com>" >> "$webdirdest/management/index.php"
    echo " * @license  http://opensource.org/licenses/gpl-3.0 GPLv3" >> "$webdirdest/management/index.php"
    echo " * @link     https://fogproject.org" >> "$webdirdest/management/index.php"
    echo " */" >> "$webdirdest/management/index.php"
    echo "require '../commons/base.inc.php';" >> "$webdirdest/management/index.php"
    echo "require '../commons/text.php';" >> "$webdirdest/management/index.php"
    echo "ob_start();" >> "$webdirdest/management/index.php"
    echo "FOGCore::getClass('FOGPageManager')->render();" >> "$webdirdest/management/index.php"
    echo "ob_end_clean();" >> "$webdirdest/management/index.php"
    echo "die(_('This is a storage node, please do not access the web ui here!'));" >> "$webdirdest/management/index.php"
}
addOndrejRepo() {
    find /etc/apt/sources.list.d/ -name '*ondrej*' -exec rm -rf {} \; >>$error_log 2>&1
    DEBIAN_FRONTEND=noninteractive $packageinstaller python-software-properties >>$error_log 2>&1
    DEBIAN_FRONTEND=noninteractive $packageinstaller software-properties-common >>$error_log 2>&1
    DEBIAN_FRONTEND=noninteractive $packageinstaller ntpdate >>$error_log 2>&1
    ntpdate pool.ntp.org >>$error_log 2>&1
    locale-gen 'en_US.UTF-8' >>$error_log 2>&1
    LANG='en_US.UTF-8' LC_ALL='en_US.UTF-8' add-apt-repository -y ppa:ondrej/php >>$error_log 2>&1
    [[ ${WEB_server_engine} == "apache2" ]] && LANG='en_US.UTF-8' LC_ALL='en_US.UTF-8' add-apt-repository -y ppa:ondrej/apache2 >>$error_log 2>&1
}
resolveDHCPEngine() {
    # Decide between Kea and ISC-DHCP for the optional FOG-hosted DHCP service.
    # Only relevant when FOG is actually building DHCP and the ISC package is
    # still in the install set (the storage-node and DHCP_enabled=0 paths strip it in
    # doOSSpecificIncludes before we ever get here). Must run after repo setup
    # so the Kea availability probe sees enabled repos (e.g. EPEL on RHEL).
    [[ -z $keaconfig ]] && keaconfig="/etc/kea/kea-dhcp4.conf"
    [[ ${DHCP_enabled} == yes ]] || return 0
    local iscpkg="$dhcpname"
    [[ -n $iscpkg && ${FOG_packages} == *"$iscpkg"* ]] || return 0
    # Honor an explicit/persisted choice; an existing install is never switched.
    DHCP_engine="${DHCP_engine,,}"
    if [[ -z ${DHCP_engine} ]]; then
        if pkgIsInstalled "$iscpkg"; then
            # A prior ISC install is left on ISC unless the admin opts in.
            DHCP_engine="isc"
        elif [[ -n $keapackage ]] && pkgIsAvailable "$keapackage"; then
            DHCP_engine="kea"
        else
            DHCP_engine="isc"
        fi
    fi
    if [[ ${DHCP_engine} == kea ]]; then
        if [[ -z $keapackage || -z $keaservice ]]; then
            echo " * Kea requested but not available for this OS; using ISC-DHCP"
            DHCP_engine="isc"
        else
            FOG_packages="${FOG_packages//$iscpkg/$keapackage}"
            dhcpname="$keapackage"
            DHCP_service_name="$keaservice"
            dhcpconfig="$keaconfig"
            dhcpconfigother=""
        fi
    fi
}
# Bulk package-state helpers.
#
# installPackages used to answer "is it installed?" and "does it exist?" with
# one subprocess per package -- ~80 packages x ($packageQuery + $packagelist),
# and on RHEL every `dnf list` reloads and reparses the full repo metadata, so
# the probing alone cost minutes before a single package was installed. Ask
# each question once for the whole system instead and answer the per-package
# questions from memory.
#
# These are shell functions the per-distro configs define, not the eval'd
# command strings the older $package* vars use, because they are pipelines: a
# pipeline eval'd out of a variable is exactly what silently broke Alpine's
# packageupdater ("apk update && apk upgrade" -- the && is not re-parsed as a
# control operator after expansion, so apk got it as a literal argument).
#
# $packageQuery/$packagelist stay for the handful of callers that run BEFORE
# the metadata refresh (the EPEL/Remi repo bootstrap in installPackages); a
# cached set would answer those from a repo list that does not exist yet.
declare -A pkgInstalledSet=()
declare -A pkgAvailableSet=()
pkgAvailableKnown=0
# Defaults for a distro config that has not defined the bulk primitives. This
# file is sourced before doOSSpecificIncludes, so a config that does define
# them overrides these. Degrading rather than erroring is deliberate: an empty
# installed set just means every package gets queued (package managers are
# idempotent), and an empty available set routes pkgIsAvailable back to the
# per-package probe.
pkgQueryAll() { :; }
pkgListAll() { :; }
loadInstalledSet() {
    local name
    pkgInstalledSet=()
    while read -r name; do
        [[ -n $name ]] && pkgInstalledSet[$name]=1
    done < <(pkgQueryAll 2>>$error_log)
}
loadAvailableSet() {
    local name
    pkgAvailableSet=()
    pkgAvailableKnown=0
    while read -r name; do
        [[ -n $name ]] && pkgAvailableSet[$name]=1
    done < <(pkgListAll 2>>$error_log)
    # An empty set means the bulk primitive is unusable here -- an old yum with
    # no repoquery, a repo that failed to sync. Leaving pkgAvailableKnown at 0
    # sends pkgIsAvailable back to the per-package probe, rather than having it
    # conclude that every package in the list is missing.
    [[ ${#pkgAvailableSet[@]} -gt 0 ]] && pkgAvailableKnown=1
    # An unusable bulk primitive is a fallback, not an installer failure.
    return 0
}
loadPackageSets() {
    loadInstalledSet
    loadAvailableSet
}
pkgIsInstalled() {
    [[ -n ${pkgInstalledSet[$1]} ]]
}
pkgIsAvailable() {
    if [[ $pkgAvailableKnown -eq 1 ]]; then
        [[ -n ${pkgAvailableSet[$1]} ]]
        return $?
    fi
    local x="$1"
    eval $packagelist "$x" >>$error_log 2>&1
}
# Echo the first name the repos actually carry / that is already on the box.
# Nothing echoed and non-zero returned when none of them match.
pkgFirstAvailable() {
    local p
    for p in "$@"; do
        pkgIsAvailable "$p" && { echo "$p"; return 0; }
    done
    return 1
}
pkgFirstInstalled() {
    local p
    for p in "$@"; do
        pkgIsInstalled "$p" && { echo "$p"; return 0; }
    done
    return 1
}
installPackages() {
    [[ ${FOG_install_lang} == yes ]] && FOG_packages="${FOG_packages} gettext"
    FOG_packages="${FOG_packages} jq"
    FOG_packages="${FOG_packages} unzip"
    FOG_packages="${FOG_packages} attr"
    # Secure Boot kernel signing is on by default, so sbsign/sbverify are a
    # baseline requirement rather than something the admin installs first.
    # The name splits by distro (sbsigntool on Debian/Alpine, sbsigntools on
    # RHEL/Arch), resolved in the alternatives case below. Where neither
    # exists the package loop skips it with "(Does not exist)" and
    # _resignKernels degrades to its existing warning.
    FOG_packages="${FOG_packages} sbsigntool"
    # efitools builds the signed PK/KEK/db variable updates that the automatic
    # Secure Boot enrollment path writes on the client (_publishSecureBootAuthVars).
    # Same baseline reasoning as sbsigntool: the feature is on by default, so the
    # tooling it needs is not something the admin should have to know to install
    # first. Named "efitools" on every distro that packages it, so it needs no
    # alternatives entry -- but RHEL/Rocky/Alma/CentOS Stream 9 package NONE:
    # confirmed absent from EPEL9, only present in those distros' build-only
    # "devel" repos. There the package loop skips it cleanly ("Does not
    # exist") and _ensureEfitools() builds it from source as a last resort,
    # right before _publishSecureBootAuthVars needs it.
    FOG_packages="${FOG_packages} efitools"
    FOG_packages="${FOG_packages} ${WEB_server_engine}"
    # -E, not -P: busybox grep has no -P at all, so on Alpine this printed a
    # full usage screen to the console and the sftp adjustment never ran. \s
    # and (?:...) are PCRE-only, hence [[:space:]] and a plain group. stderr is
    # dropped because a host with no sshd_config is normal, not an error.
    str=$(grep -E "Subsystem[[:space:]]+sftp[[:space:]]+/usr/(lib|libexec)/openssh/sftp-server" /etc/ssh/sshd_config 2>/dev/null)
    if [[ $? -eq 0 ]]; then
        dots "Adjusting sftp for ssh"
        sed -i -e "s#$str#Subsystem\tsftp\tinternal-sftp#g" /etc/ssh/sshd_config >>$error_log 2>&1
        systemctl enable sshd >/dev/null 2>&1
        systemctl restart sshd >/dev/null 2>&1
        echo "Done"
    fi
    dots "Adjusting repository (can take a long time for cleanup)"
    case ${FOG_os_id} in
        1)
            FOG_packages="${FOG_packages} php-bcmath bc"
            if [[ ${FOG_install_lang} == yes ]]; then
                FOG_packages="${FOG_packages} php-intl"
                for i in fr de eu es pt zh en ja; do
                    FOG_packages="${FOG_packages} glibc-langpack-${i}";
                done
            fi
            FOG_packages="${FOG_packages// mod_fastcgi/}"
            FOG_packages="${FOG_packages// mod_evasive/}"
            FOG_packages="${FOG_packages// php-mcrypt/}"
            FOG_packages="${FOG_packages} php-pecl-ssh2"
            case $linuxReleaseName_lower in
                *fedora*)
                    FOG_packages="${FOG_packages} php-json"
                    FOG_packages="${FOG_packages// mysql / mariadb }" >>$error_log 2>&1
                    FOG_packages="${FOG_packages// mysql-server / mariadb-server }" >>$error_log 2>&1
                    FOG_packages="${FOG_packages// dhcp / dhcp-server }" >>$error_log 2>&1
                    ;;
                *)
                    x="epel-release"
                    eval $packageQuery >>$error_log 2>&1
                    if [[ ! $? -eq 0 ]]; then
                        y="https://dl.fedoraproject.org/pub/epel/epel-release-latest-${OSVersion}.noarch.rpm"
                        $packageinstaller $y >>$error_log 2>&1
                        errorStat $? "skipOk"
                    fi
                    y="https://rpms.remirepo.net/enterprise/remi-release-${OSVersion}.rpm"
                    x="$(basename $y | awk -F[.] '{print $1}')*"
                    eval $packageQuery >>$error_log 2>&1
                    if [[ ! $? -eq 0 ]]; then
                        rpm -Uvh $y >>$error_log 2>&1
                        errorStat $? "skipOk"
                    fi
                    rpm --import "https://rpms.remirepo.net/enterprise/${OSVersion}/RPM-GPG-KEY-remi" >>$error_log 2>&1
                    errorStat $? "skipOk"
                    if [[ -n $repoenable ]]; then
                        if [[ $OSVersion -le 7 ]]; then
                            $repoenable epel >>$error_log 2>&1 || true
                            $repoenable remi >>$error_log 2>&1 || true
                            $repoenable remi-php72 >>$error_log 2>&1 || true
                        fi
                    fi
                    ;;
            esac
            ;;
        2)
            if [[ ${WEB_server_engine} == "apache2" ]]; then
                FOG_packages="${FOG_packages// libapache2-mod-fastcgi/}"
                FOG_packages="${FOG_packages// libapache2-mod-evasive/}"
            fi
            FOG_packages="${FOG_packages// xinetd/}"
            FOG_packages="${FOG_packages// php-gettext/}"
            FOG_packages="${FOG_packages// php-php-gettext/}"
            FOG_packages="${FOG_packages} php-bcmath bc"
            FOG_packages="${FOG_packages} php-ssh2"
            if [[ ${FOG_install_lang} == yes ]]; then
                FOG_packages="${FOG_packages} php-intl"
            fi
            case $linuxReleaseName_lower in
                *ubuntu*|*mint*)
                    if [[ ${FOG_install_lang} == yes ]]; then
                        for i in fr de eu es pt zh-hans en ja; do
                            FOG_packages="${FOG_packages} language-pack-${i}";
                        done
                    fi
                    if [[ $OSVersion -gt 17 ]]; then
                        FOG_packages="${FOG_packages// libcurl3 / libcurl4 }">>$error_log 2>&1
                    fi
                    if [[ $OSVersion -gt 22 ]]; then
                        FOG_packages="${FOG_packages// libcurl4 / libcurl4t64 }">>$error_log 2>&1
                    fi
                    if [[ $linuxReleaseName_lower == +(*ubuntu*) && $OSVersion -ge 18 ]]; then
                        # Fix missing universe section for Ubuntu 18.04 LIVE
                        LANG='en_US.UTF-8' LC_ALL='en_US.UTF-8' add-apt-repository -y universe >>$error_log 2>&1
                        # check to see if we still have packages from deb.sury.org (a.k.a ondrej) installed and try to clean it up
                        dpkg -l | grep -q "deb\.sury\.org"
                        if [[ $? -eq 0 ]]; then
                            # make sure we have ondrej repos enabled to be able to use ppa-purge
                            addOndrejRepo
                            # use ppa-purge to not just remove the repo but also downgrade packages to Ubuntu original versions
                            DEBIAN_FRONTEND=noninteractive apt-get install -yq ppa-purge >>$error_log 2>&1
                            [[ ${WEB_server_engine} == "apache2" ]] && ppa-purge -y ppa:ondrej/apache2 >>$error_log 2>&1
                            # for php we want to purge all packages first as we don't want ppa-purge to try downgrading those
                            DEBIAN_FRONTEND=noninteractive apt-get purge -yq 'php5*' 'php7*' 'libapache*' >>$error_log 2>&1
                            ppa-purge -y ppa:ondrej/php >>$error_log 2>&1
                            DEBIAN_FRONTEND=noninteractive apt-get purge -yq ppa-purge >>$error_log 2>&1
                        fi
                    else
                        addOndrejRepo
                    fi
                    ;;
                *bian*)
                    if [[ $OSVersion -ge 10 ]]; then
                        FOG_packages="${FOG_packages// libcurl3 / libcurl4 }">>$error_log 2>&1
                        FOG_packages="${FOG_packages// mysql-client / mariadb-client }">>$error_log 2>&1
                        FOG_packages="${FOG_packages// mysql-server / mariadb-server }">>$error_log 2>&1
                    fi
                    if [[ $OSVersion -ge 13 ]]; then
                        FOG_packages="${FOG_packages// libcurl4 / libcurl4t64 }">>$error_log 2>&1
                    fi
                    ;;
            esac
            ;;
        3)
            # Alpine has no unversioned "php-ssh2" -- the extension is
            # php<major><minor>-pecl-ssh2, matching the php${php_apk} module
            # names lib/alpine/config.sh already builds. The old name resolved
            # to "(Does not exist)" on every run, so Alpine silently shipped
            # without the ssh2 extension FOG needs for storage node access.
            FOG_packages="${FOG_packages} php${php_apk}-pecl-ssh2"
            sed -i '/\/v3\.15\/community$/s/^#[[:space:]]*//' /etc/apk/repositories
            ;;
    esac
    errorStat $?
    dots "Preparing Package Manager"
    $packmanUpdate >>$error_log 2>&1
    if [[ ${FOG_os_id} -eq 2 ]]; then
        if [[ $? != 0 ]] && [[ $linuxReleaseName_lower == +(*ubuntu*|*mint*) ]]; then
            cp /etc/apt/sources.list /etc/apt/sources.list.original_fog_$(date +%s)
            sed -i -e 's/\/\/*archive.ubuntu.com\|\/\/*security.ubuntu.com/\/\/old-releases.ubuntu.com/g' /etc/apt/sources.list
            $packmanUpdate >>$error_log 2>&1
            if [[ $? != 0 ]]; then
                cp -f /etc/apt/sources.list.original_fog /etc/apt/sources.list >>$error_log 2>&1
                rm -f /etc/apt/sources.list.original_fog >>$error_log 2>&1
                false
            fi
        fi
    fi
    errorStat $?
    # Read the installed and available sets once, up front. Everything below --
    # the mysql/php variant picking, the "already installed" and "does not
    # exist" decisions, and resolveDHCPEngine's two probes -- is answered from
    # these two arrays instead of spawning a package-manager query per name.
    dots "Reading package state"
    loadPackageSets
    errorStat $?
    resolveDHCPEngine
    FOG_packages=$(echo ${FOG_packages[@]} | tr ' ' '\n' | sort -u | tr '\n' ' ')
    echo -e " * Packages to be installed:\n\n\t${FOG_packages}\n\n"
    newPackList=""
    local toInstall=""
    local altpkg=""
    for x in ${FOG_packages}; do
        case $x in
            mysql|mariadb|mariadb-client|MariaDB-client)
                # Prefer whatever is already on the box, so a MySQL host is not
                # handed a MariaDB client (or the reverse) just because that is
                # what the repos list first.
                installed_sqlclient=$(pkgFirstInstalled $sqlclientlist)
                available_sqlclient=$(pkgFirstAvailable $sqlclientlist)
                [[ -n $installed_sqlclient ]] && x=$installed_sqlclient || x=$available_sqlclient
                ;;
            mysql-server|mariadb-server|MariaDB-server)
                installed_sqlserver=$(pkgFirstInstalled $sqlserverlist)
                available_sqlserver=$(pkgFirstAvailable $sqlserverlist)
                [[ -n $installed_sqlserver ]] && x=$installed_sqlserver || x=$available_sqlserver
                ;;
            php-json)
                altpkg=$(pkgFirstAvailable php-json php-common)
                [[ -n $altpkg ]] && x=$altpkg
                ;;
            sbsigntool)
                # Debian/Ubuntu/Alpine call it sbsigntool; Fedora/RHEL/Arch
                # call it sbsigntools. Leaving x alone when neither resolves
                # lets the pkgIsAvailable check below skip it cleanly.
                altpkg=$(pkgFirstAvailable sbsigntool sbsigntools)
                [[ -n $altpkg ]] && x=$altpkg
                ;;
            php-mysql*)
                altpkg=$(pkgFirstAvailable php-mysqlnd php-mysql)
                [[ -n $altpkg ]] && x=$altpkg
                # Only add mysqli package for osid 3 for better integration.
                # This used to end in a `break`, which -- sitting in a case and
                # not a loop -- would have broken out of the whole package loop
                # and abandoned every package after this one.
                [[ ${FOG_os_id} -eq 3 ]] && pkgIsAvailable php-mysqli && x="php-mysqli"
                ;;
        esac
        # None of the alternatives resolved; there is nothing to install.
        [[ -z $x ]] && continue
        [[ ${FOG_os_id} == 2 && -z ${DHCP_service_name} && $x == +(*'dhcp'*) ]] && DHCP_service_name=$x
        if pkgIsInstalled "$x"; then
            dots "Skipping package:   $x"
            echo "(Already Installed)"
            newPackList="$newPackList $x"
            continue
        fi
        if ! pkgIsAvailable "$x"; then
            dots "Skipping package:   $x"
            echo "(Does not exist)"
            continue
        fi
        newPackList="$newPackList $x"
        dots "Pending package:    $x"
        echo "(Queued)"
        [[ -z $toInstall ]] && toInstall="$x" || toInstall="$toInstall $x"
    done
    FOG_packages=$newPackList
    FOG_packages=$(echo ${FOG_packages[@]} | tr ' ' '\n' | sort -u | tr '\n' ' ')
    if [[ -n $toInstall ]]; then
        toInstall=$(echo ${toInstall[@]} | tr ' ' '\n' | sort -u | tr '\n' ' ')
        # One transaction rather than one per package: the dependency solve, the
        # rpm/dpkg run and the trigger pass all happen once instead of ~80
        # times. On Arch it also stops us running a partial-upgrade `-Syu` once
        # per package, which is the thing Arch tells you never to do.
        dots "Installing $(echo $toInstall | wc -w) packages"
        DEBIAN_FRONTEND=noninteractive $packageinstaller $toInstall >>$error_log 2>&1
        if [[ $? -eq 0 ]]; then
            echo "OK"
        else
            # A batch is all-or-nothing and the log does not say which name
            # poisoned it, so fall back to the old one-at-a-time pass. Slower,
            # but it names the package that actually failed.
            echo "Failed! (retrying individually)"
            local failedPack=""
            for x in $toInstall; do
                dots "Installing package: $x"
                DEBIAN_FRONTEND=noninteractive $packageinstaller $x >>$error_log 2>&1
                if [[ $? -eq 0 ]]; then
                    echo "OK"
                else
                    echo "Failed!"
                    [[ -z $failedPack ]] && failedPack="$x" || failedPack="$failedPack $x"
                fi
            done
            [[ -n $failedPack ]] && echo -e " * Packages that could not be installed:\n\n\t$failedPack\n"
        fi
    fi
    dots "Updating packages as needed"
    DEBIAN_FRONTEND=noninteractive $packageupdater ${FOG_packages} >>$error_log 2>&1
    if [[ $? -eq 0 ]]; then
        echo "OK"
    else
        # Non-fatal -- everything FOG needs is installed by this point, and the
        # upgrade pass is best-effort. It used to echo "OK" unconditionally,
        # which hid the failure completely (notably on Alpine, where the
        # updater command could not run at all).
        echo "Failed! (non-fatal, see $error_log)"
    fi
    # Alpine ships no unversioned `php` binary -- it is php83/php84/... tracking
    # $php_apk -- so this printed "command not found" and left ${WEB_php_version} empty,
    # which then got persisted into .fogsettings as a managed key.
    local phpbin="php"
    [[ -n $php_apk ]] && command -v "php${php_apk}" >/dev/null 2>&1 && phpbin="php${php_apk}"
    export WEB_php_version=$($phpbin -i | grep "PHP Version" | head -1 | cut -d' ' -f 4 | cut -d'.' -f1-2)
    [[ -z ${phpfpm} ]] && export phpfpm="php${WEB_php_version}-fpm"
    [[ -z ${phpini} ]] && export phpini="/etc/php/${WEB_php_version}/fpm/php.ini"
}
confirmPackageInstallation() {
    # Re-read the installed set -- installPackages changed it -- then check
    # every name against that one snapshot instead of running a query per
    # package all over again.
    loadInstalledSet
    for x in ${FOG_packages}; do
        dots "Checking package: $x"
        pkgIsInstalled "$x"
        errorStat $?
    done
}
installSELinuxModule() {
    # GH-964: FOG's web tier needs outbound HTTP, FTP and SSH. httpd_t gets
    # none of them by default, and the boolean everyone reaches for --
    # httpd_can_network_connect -- grants name_connect on the `port_type`
    # ATTRIBUTE, i.e. every port type in the policy. Three ports wanted,
    # several hundred granted, permanently, to the daemon serving the web UI.
    #
    # ssh_port_t has no narrow boolean at any width, so there is no
    # combination of setsebool calls that covers FOG without the blanket one.
    # A three-rule policy module does, and FOG owns it. See packages/selinux.
    #
    # Built from source rather than shipped as a .pp, because a compiled
    # module is tied to the policy version of the machine that built it and
    # refuses to load on an older one. Compiling against the target's own
    # policy is what makes one source file work across Fedora, RHEL, Rocky
    # and Alma.
    #
    # checkmodule and semodule_package come from checkpolicy and
    # policycoreutils, so this needs nothing beyond what a host already has
    # to have for semanage to exist. fog.te is deliberately written without
    # refpolicy macros so that selinux-policy-devel -- which is not installed
    # by default on any distro FOG supports -- is not required.
    local src="../packages/selinux/fog.te"
    local workdir
    command -v selinuxenabled >>$error_log 2>&1 || return 0
    selinuxenabled || return 0
    [[ -f $src ]] || return 0
    dots "Installing FOG SELinux policy module"
    if ! command -v semodule >>$error_log 2>&1 \
        || ! command -v checkmodule >>$error_log 2>&1 \
        || ! command -v semodule_package >>$error_log 2>&1; then
        echo "Skipped (no policy tooling)"
        echo " * FOG's web tier needs outbound HTTP/FTP/SSH, which SELinux"
        echo " *   denies by default. Under enforcing this presents as storage"
        echo " *   node operations failing with nothing in FOG's own logs."
        echo " * Install checkpolicy and policycoreutils, then re-run the"
        echo " *   installer."
        return 0
    fi
    # Deliberately rebuilt and reinstalled every run rather than skipped when
    # `semodule -l` already lists fog. Skipping would mean a later version of
    # fog.te never reaches a server that has the old one -- the same
    # silently-not-upgraded failure the kernel re-signing exists to prevent.
    # semodule -i is idempotent and this costs a couple of seconds on an
    # operation nobody runs in a loop.
    workdir=$(mktemp -d) || { echo "Failed"; return 0; }
    # checkmodule writes its outputs beside its input, so build in the temp
    # directory rather than dropping fog.mod/fog.pp into the source tree.
    cp -f "$src" "$workdir/fog.te" >>$error_log 2>&1
    if ! (cd "$workdir" && checkmodule -M -m -o fog.mod fog.te \
        && semodule_package -o fog.pp -m fog.mod) >>$error_log 2>&1 \
        || ! semodule -i "$workdir/fog.pp" >>$error_log 2>&1; then
        rm -rf "$workdir"
        # Not fatal, for the same reason downloadipxesecureboot is not: an
        # otherwise good install should not abort over a policy module, and
        # on a permissive host none of this matters anyway.
        echo "Failed"
        echo " * Could not build or load the FOG SELinux module. See $error_log."
        echo " * Under SELinux enforcing, storage node operations over HTTP,"
        echo " *   FTP and SSH will be denied until this is resolved."
        echo " * The fog_share_t label steps below will also fail, because"
        echo " *   semanage cannot register a type the policy does not know."
        return 0
    fi
    rm -rf "$workdir"
    errorStat 0
}
setSELinuxContext() {
    # setSELinuxContext <directory> <type-to-register> <acceptable-type>...
    #
    # GH-963: the installer builds its directories with mkdir/cp, so they
    # inherit the parent's SELinux label instead of the one policy intends.
    # /tftpboot came out default_t, and the confined TFTP daemon has no rule
    # permitting tftpd_t to read that type -- so on an enforcing host every PXE
    # boot failed as a bare "file not found", with nothing in FOG's own logs and
    # only an AVC denial in the audit log to explain it. FOG's answer until now
    # was checkSELinux() offering to switch SELinux off machine-wide, which is a
    # very large hammer for one mislabeled directory.
    #
    # Policy almost always already knows the correct label -- FOG simply never
    # asked for it. So ask (restorecon), and only register a rule when policy
    # has no usable answer, which happens when the admin has relocated the
    # directory out of the packaged path.
    local dir="$1" register="$2"
    shift 2
    # $register is by definition acceptable -- it is what we are aiming for.
    local acceptable=" $register $* "
    # No SELinux at all -- Debian/Ubuntu/Arch/Alpine as shipped, or explicitly
    # disabled. Silent: this is the common case and not a problem.
    command -v selinuxenabled >>$error_log 2>&1 || return 0
    selinuxenabled || return 0
    [[ -d $dir ]] || return 0
    # No path in the label: dots() pads to a fixed 60 columns, and a relocated
    # $tftpdirdst -- the very case this branch exists for -- can overflow it and
    # collide with the OK/Failed. The failure output below names the path.
    dots "Setting SELinux context"
    if ! command -v restorecon >>$error_log 2>&1; then
        echo "Skipped (no restorecon)"
        return 0
    fi
    # matchpathcon reads the same file_contexts restorecon will, so this
    # predicts exactly what restorecon is about to do rather than guessing.
    local willbe=$(matchpathcon -n "$dir" 2>>$error_log | cut -d: -f3)
    if [[ $acceptable != *" $willbe "* ]]; then
        if command -v semanage >>$error_log 2>&1; then
            # A rule, not a one-off label, so the context is a property of the
            # path: it survives `restorecon /` and a full filesystem relabel,
            # which would otherwise silently undo the fix months later. -a
            # fails if a rule is already there, hence the -m fallback.
            semanage fcontext -a -t "$register" "${dir}(/.*)?" >>$error_log 2>&1 ||
                semanage fcontext -m -t "$register" "${dir}(/.*)?" >>$error_log 2>&1
        else
            # semanage lives in policycoreutils-python-utils, which is not
            # always installed. chcon applies the label now but has no rule
            # behind it, so a relabel reverts it. Better than leaving the daemon
            # unable to read anything -- but say so rather than imply it is done.
            # GH-964: check the post-condition rather than assuming, for the
            # same reason the restorecon path does. This branch used to echo
            # OK unconditionally, which was survivable when $register was
            # always a stock type; now that FOG registers fog_share_t, a
            # module that failed to load makes chcon fail outright and
            # reporting OK would hide it.
            chcon -R -t "$register" "$dir" >>$error_log 2>&1
            local chconis=$(ls -Zd "$dir" 2>>$error_log | awk '{print $1}' | cut -d: -f3)
            if [[ $acceptable == *" $chconis "* ]]; then
                echo "OK"
                echo " * Labeled with chcon only -- a filesystem relabel will undo this."
                echo " * Install policycoreutils-python-utils and re-run to make it permanent."
                return 0
            fi
            echo "Failed!"
            echo " * Could not label $dir as '$register' (it is '$chconis')."
            echo " * If '$register' is a FOG type, the policy module did not load."
            echo " * Install policycoreutils-python-utils and re-run the installer."
            return 0
        fi
    fi
    restorecon -RF "$dir" >>$error_log 2>&1
    # Check the post-condition rather than the exit code. restorecon returns 0
    # when it has no rule to apply, which is indistinguishable from success and
    # would report a still-unreadable directory as done -- the exact silence
    # that let GH-963 sit unnoticed. Ask what the label actually IS now.
    local nowis=$(ls -Zd "$dir" 2>>$error_log | awk '{print $1}' | cut -d: -f3)
    if [[ $acceptable == *" $nowis "* ]]; then
        errorStat 0
        return 0
    fi
    # Not fatal: on a permissive or disabled host this costs nothing, and an
    # install that otherwise succeeded should not be aborted over a label. But
    # it must be loud, because the symptom it causes has no other explanation.
    echo "Failed!"
    echo " * $dir is labeled '$nowis', which the daemon using it cannot read."
    echo " * Under SELinux enforcing this presents as a bare file-not-found at"
    echo " *   the client with nothing in FOG's logs -- check for AVC denials:"
    echo " *   ausearch -m avc -ts recent"
    echo " * Fix by hand with: restorecon -RFv $dir"
    return 0
}
checkSELinux() {
    command -v sestatus >>$error_log 2>&1
    exitcode=$?
    [[ $exitcode -ne 0 ]] && return
    currentmode=$(LANG=C sestatus | grep "^Current mode" | awk '{print $3}')
    configmode=$(LANG=C sestatus | grep "^Mode from config file" | awk '{print $5}')
    [[ "x$currentmode" != "xenforcing" && "x$configmode" != "xenforcing" ]] && return
    # GH-964 step 5. This prompt used to recommend permissive and default to
    # YES, which meant every unattended (-y) install switched SELinux off
    # machine-wide without anyone deciding to. That was defensible only while
    # FOG genuinely did not work enforcing. It now does:
    #
    #   GH-963       $tftpdirdst is labeled, so PXE boots
    #   GH-966/967   $fogprogramdir/cache, $snapindir and ${STORAGE_image_share_path} are
    #                labeled, so the web tier can write
    #   GH-968/969   fog_share_t, so vsftpd can read and write image and
    #                snapin storage as well as the web tier
    #   GH-966/967   packages/selinux/fog.te, so httpd_t may reach its own
    #                API, its nodes' FTP, and SSH
    #
    # and capture, deploy and replication have all been run on an enforcing
    # host. NFS never needed anything: its data path is in-kernel as kernel_t,
    # which has unconditional access to the file_type attribute.
    #
    # So the recommendation is inverted. Note this does NOT need to remember
    # the answer the way configureFirewall() does -- if the admin chooses
    # permissive, /etc/selinux/config says so, and the early return above
    # means this function never asks again. The system state is the memory.
    echo " * SELinux is enabled and enforcing on your system."
    echo " * FOG supports this. The installer labels its directories, installs"
    echo " * a small policy module for the ports the web tier needs, and has"
    echo " * been tested capturing, deploying and replicating under enforcing."
    echo " * Leaving it on is recommended and is now the default."
    echo " * If you hit trouble later, SELinux denials never appear in FOG's"
    echo " * own logs -- check for them with: ausearch -m avc -ts recent"
    echo -n " * Set SELinux permissive anyway? (y/N) "
    sedisable=""
    while [[ -z $sedisable ]]; do
        # Unattended installs keep enforcing. This is the half of GH-964 that
        # mattered most: -y used to answer "Y" here, so an admin who never saw
        # a prompt ended up with SELinux off across the whole machine.
        [[ -n $autoaccept ]] && sedisable="N" || read -r sedisable
        case $sedisable in
            [Yy]|[Yy][Ee][Ss])
                sedisable="Y"
                setenforce 0
                sed -i 's/^SELINUX=.*$/SELINUX=permissive/' /etc/selinux/config
                echo -e " * SELinux set permissive -- proceeding with installation...\n"
                ;;
            [Nn]|[Nn][Oo]|"")
                sedisable="N"
                echo "N"
                echo -e " * Leaving SELinux enforcing.\n"
                ;;
            *)
                sedisable=""
                echo " * Invalid input, please try again!"
                ;;
        esac
    done
}
configureFirewall() {
    # GH-964 sibling: FOG used to have exactly one answer to a running
    # firewall -- offer to switch it off. Worse, that offer was skipped
    # entirely under -y (fwdisable was hardcoded to "N"), so an unattended
    # install left the firewall in whatever state the box happened to be in
    # and every FOG service silently unreachable. Neither half was a
    # decision anyone made on purpose; it is the same shape as the SELinux
    # problem, where the fix was "configure it" rather than "turn it off".
    #
    # So: configure by default, in BOTH the attended and the unattended
    # path, and keep disabling as an explicit choice rather than the only
    # one.
    #
    # Runs late, unlike the checkFirewall() it replaces, for two reasons
    # that both used to be broken:
    #   - .fogsettings is not sourced until after the old call site, so a
    #     remembered choice could not be honored. An admin who said "leave
    #     my firewall alone" got re-asked, or under -y re-ignored, on every
    #     single upgrade.
    #   - the port set depends on what was actually installed (${DHCP_enabled},
    #     ${BOOT_external_tftp_server}, ${WEB_url_proto}, ${FOG_install_type}), none of which is settled
    #     at the old call site.
    local action="${SVC_firewall_control}"
    local backend="" fwrunning=0

    # Detect. firewalld and ufw are asked directly; bare iptables is inferred
    # from a ruleset that is not the empty default (3 chains, 5 header lines,
    # all policies ACCEPT == untouched).
    if command -v firewall-cmd >>$error_log 2>&1 && [[ "x$(firewall-cmd --state 2>&1)" == "xrunning" ]]; then
        backend="firewalld"
        fwrunning=1
    elif command -v ufw >>$error_log 2>&1 && ufw status 2>/dev/null | grep -qi "^Status: active"; then
        backend="ufw"
        fwrunning=1
    elif command -v iptables >>$error_log 2>&1; then
        local rulesnum=$(iptables -L -n 2>/dev/null | wc -l)
        local policy=$(iptables -L -n 2>/dev/null | grep "^Chain" | grep -v "ACCEPT" -c)
        if [[ $rulesnum -ne 8 || $policy -ne 0 ]]; then
            backend="iptables"
            fwrunning=1
        fi
    fi
    [[ $fwrunning -ne 1 ]] && return 0

    # Decide. A remembered choice wins, then -y, then ask.
    if [[ -z $action ]]; then
        if [[ -n $autoaccept ]]; then
            action="configure"
        else
            echo
            echo " * A local firewall ($backend) is running. FOG needs a number of"
            echo " * ports open to serve PXE clients, storage nodes and the web UI."
            echo
            echo " *   1) Open the ports FOG needs (recommended)"
            echo " *   2) Disable the firewall entirely"
            echo " *   3) Leave it alone -- I will configure it myself"
            echo
            while [[ -z $action ]]; do
                echo -n " * Which would you like? (1/2/3) "
                read -r fwanswer
                case $fwanswer in
                    1|"") action="configure" ;;
                    2) action="disable" ;;
                    3) action="skip" ;;
                    *) echo " * Invalid input, please try again!" ;;
                esac
            done
        fi
    fi
    # Remembered so the next upgrade does not re-ask, and more importantly so
    # an admin who said "leave it alone" is not overridden by a later -y run.
    SVC_firewall_control="$action"

    case $action in
        skip)
            dots "Configuring firewall"
            echo "Skipped (by request)"
            echo " * FOG will not be reachable until these are open:"
            _firewallPortList | while read -r p d; do echo " *   $p  $d"; done
            return 0
            ;;
        disable)
            dots "Disabling firewall"
            ufw stop >/dev/null 2>&1
            ufw disable >/dev/null 2>&1
            local svc
            for svc in ufw firewalld iptables; do
                systemctl is-active --quiet $svc && systemctl stop $svc >/dev/null 2>&1 || true
                systemctl is-enabled --quiet $svc 2>/dev/null && systemctl disable $svc >/dev/null 2>&1 || true
            done
            errorStat 0
            return 0
            ;;
    esac

    dots "Configuring firewall ($backend)"
    case $backend in
        firewalld) _configureFirewalld ;;
        ufw)       _configureUfw ;;
        iptables)  _reportIptables; return 0 ;;
    esac
}
_firewallPortList() {
    # Emits "<port>/<proto> <description>", one per line, for exactly the
    # services this install actually stood up. Single source of truth: the
    # firewalld path, the ufw path, the iptables instructions and the
    # "here is what you still need to open" message all read this.
    echo "80/tcp HTTP (web UI, client check-in, iPXE boot)"
    # Unconditional: both web servers emit their :443 vhost in BOTH arms, so
    # 443 is listening on every install whatever httpproto says. Gating this on
    # httpproto told admins to leave closed a port their server was serving on.
    echo "443/tcp HTTPS (web UI, client check-in)"
    [[ ${BOOT_external_tftp_server} != yes ]] && echo "69/udp TFTP (PXE boot)"
    echo "21/tcp FTP (image/snapin replication, node operations)"
    # Passive data. vsftpd is pinned to this range by configureFTP() for
    # exactly this reason -- see the comment there.
    echo "${ftppasvmin}-${ftppasvmax}/tcp FTP passive data"
    # Unconditional: configureNFS() runs on BOTH the full-server and the
    # storage-node path, and a storage node exists precisely to serve images
    # over NFS. ${STORAGE_rebuild_nfs_exports} only controls whether the installer overwrites an
    # existing exports file -- it does not mean NFS is absent, so gating on it
    # here would leave every "keep my exports" install unable to image.
    echo "2049/tcp NFS (image capture/deploy)"
    echo "111/tcp RPC portmapper (NFS)"
    echo "111/udp RPC portmapper (NFS)"
    # configureNFS() pins mountd here; without that pin this port would be
    # random per boot and could not be firewalled at all.
    echo "20048/tcp NFS mountd"
    echo "20048/udp NFS mountd"
    [[ ${DHCP_enabled} == yes ]] && echo "67/udp DHCP (FOG is your DHCP server)"
    # udpcast multicast. UDPCAST_STARTINGPORT is the base and each concurrent
    # session consumes two ports, so the window is base .. base + 2*sessions.
    echo "${mcastportmin}-${mcastportmax}/udp Multicast (udpcast)"
}
_configureFirewalld() {
    local failed=0 p d
    # Named services rather than bare ports wherever one exists: a firewalld
    # service definition carries its conntrack helper with it, which is what
    # makes TFTP work at all. TFTP replies come from an ephemeral source port,
    # so a bare "--add-port=69/udp" opens the request and drops every packet
    # of the actual transfer. Modern kernels no longer auto-assign helpers,
    # so this is not something the box does for us.
    local svc
    for svc in http tftp ftp nfs mountd rpc-bind dhcp; do
        case $svc in
            tftp)     [[ ${BOOT_external_tftp_server} == yes ]] && continue ;;
            dhcp)     [[ ${DHCP_enabled} != yes ]] && continue ;;
        esac
        firewall-cmd --permanent --add-service=$svc >>$error_log 2>&1 || failed=1
    done
    # See _firewallPortList: 443 listens on every install.
    firewall-cmd --permanent --add-service=https >>$error_log 2>&1 || failed=1
    # No named service for these two.
    firewall-cmd --permanent --add-port=${ftppasvmin}-${ftppasvmax}/tcp >>$error_log 2>&1 || failed=1
    firewall-cmd --permanent --add-port=${mcastportmin}-${mcastportmax}/udp >>$error_log 2>&1 || failed=1
    firewall-cmd --reload >>$error_log 2>&1 || failed=1
    if [[ $failed -ne 0 ]]; then
        echo "Failed"
        echo " * Could not apply all firewall rules. See $error_log."
        _firewallAdvice
        return 0
    fi
    errorStat 0
    _firewallSummary "$(firewall-cmd --get-default-zone 2>/dev/null)"
}
_configureUfw() {
    local failed=0 p d
    # ufw has no service definitions and no helper handling, so everything is
    # an explicit port. TFTP's data transfer relies on nf_conntrack_tftp being
    # loaded -- ufw's stock before.rules already accepts RELATED,ESTABLISHED,
    # so the helper is the only missing piece. Persisted, because a reboot
    # without it breaks PXE and nothing says why.
    if [[ ${BOOT_external_tftp_server} != yes ]]; then
        echo "nf_conntrack_tftp" > /etc/modules-load.d/fog-conntrack.conf 2>>$error_log
        modprobe nf_conntrack_tftp >>$error_log 2>&1 || true
    fi
    while read -r p d; do
        ufw allow "$p" >>$error_log 2>&1 || failed=1
    done < <(_firewallPortList)
    if [[ $failed -ne 0 ]]; then
        echo "Failed"
        echo " * Could not apply all firewall rules. See $error_log."
        _firewallAdvice
        return 0
    fi
    errorStat 0
    _firewallSummary "ufw"
}
_reportIptables() {
    # Deliberately not applied. Persisting iptables rules is distro-specific
    # (the iptables-save target differs per distro and per package), and
    # inserting into a ruleset FOG did not create risks either landing after
    # an existing REJECT -- doing nothing, silently -- or breaking rules that
    # were working. Printing exactly what to run is more honest than a
    # half-working attempt, and the docs carry the persistence step.
    echo "Skipped (raw iptables)"
    echo " * FOG will not configure a raw iptables ruleset automatically --"
    echo " *   rule ordering and persistence are too distro-specific to do"
    echo " *   safely against a ruleset FOG did not create."
    echo " * Run these, then persist them the way your distro expects:"
    local p d proto port
    while read -r p d; do
        proto="${p##*/}"
        port="${p%/*}"
        echo " *   iptables -I INPUT -p $proto --dport ${port/-/:} -j ACCEPT"
    done < <(_firewallPortList)
    if [[ ${BOOT_external_tftp_server} != yes ]]; then
        echo " * TFTP also needs the conntrack helper, or PXE transfers stall:"
        echo " *   modprobe nf_conntrack_tftp"
    fi
    echo " * Full walkthrough: https://docs.fogproject.org/en/latest/kb/how-tos/firewall/"
}
_firewallSummary() {
    local where="$1"
    echo " * Opened the following in '${where}':"
    local p d
    while read -r p d; do
        echo " *   ${p}  ${d}"
    done < <(_firewallPortList)
    _firewallAdvice
}
_firewallAdvice() {
    # Said every time, including on success. Opening these on the default zone
    # is the only thing that works when PXE clients and storage nodes sit on
    # networks the installer cannot know about -- but it IS wider than many
    # sites want, and an admin who is never told cannot narrow it.
    echo " * These are open on all interfaces the default zone covers. To"
    echo " *   restrict them to your imaging subnet, see:"
    echo " *   https://docs.fogproject.org/en/latest/kb/how-tos/firewall/"
    echo " * FOG does NOT open the database port. If you run remote storage"
    echo " *   nodes against this server's database, open 3306/tcp to those"
    echo " *   nodes specifically -- not to everything."
}
displayOSChoices() {
    blFirst=1
    while [[ -z ${FOG_os_id} ]]; do
        if [[ ${FOG_installed} -eq 1 && $blFirst -eq 1 ]]; then
            blFirst=0
        else
            FOG_os_id=$strSuggestedOS
            if [[ -z $autoaccept && ! -z ${FOG_os_id} ]]; then
                echo "  What version of Linux would you like to run the installation for?"
                echo
                echo "          1) Redhat Based Linux (Redhat, Alma, Rocky, CentOS, Mageia)"
                echo "          2) Debian Based Linux (Debian, Ubuntu, Kubuntu, Edubuntu)"
                # An Alpine install now completes end to end, and every
                # service it configures -- MariaDB, nginx, php-fpm, TFTP, FTP,
                # rpcbind, NFS, Kea and FOG's own nine daemons -- is enrolled
                # with rc-update and comes back after a reboot (#863). Before
                # that it could not install at all: the MariaDB SERVER was
                # never selected, and four of those services fell through to
                # chkconfig/service arms Alpine does not have while the
                # installer reported OK for every one of them.
                #
                # It keeps the experimental label on coverage, not on known
                # breakage. Alpine is musl and BusyBox where every other
                # supported host is glibc and GNU coreutils, which is a
                # genuinely different failure surface, and it has been
                # exercised on one release on one machine.
                echo "          3) Alpine Linux (experimental)"
                echo "          4) Arch Based Linux (Arch, Manjaro)"
                echo
                echo -n "  Choice: [$strSuggestedOS] "
                # Into the key the case below tests. This read used to name the
                # pre-1.6 variable while the case tested the renamed one, so the
                # answer landed nowhere: FOG_os_id is already $strSuggestedOS by
                # this point (set at the top of this loop), the case matched
                # that, and the loop broke -- so the prompt accepted a choice and
                # then always installed for the SUGGESTED distro. Pressing Enter
                # still takes the suggestion, through the "" branch below.
                read FOG_os_id
                case ${FOG_os_id} in
                    "")
                        FOG_os_id=$strSuggestedOS
                        break
                        ;;
                    1|2|3|4)
                        break
                        ;;
                    *)
                        echo "  Invalid input, please try again."
                        FOG_os_id=""
                        ;;
                esac
            fi
        fi
    done
    doOSSpecificIncludes
}
# --- GH-1120 key migration --------------------------------------------------
#
# Lives here, next to its first consumer, because THREE entry points source
# .fogsettings and then read a renamed key: installfog.sh, updatefog.sh and
# restorekernel.sh. All three source this file first, so all three can call
# this the moment .fogsettings has been read -- which is the ordering the whole
# thing turns on. It was inline in installfog.sh and ran after that script's
# `case $doupdate` statement, i.e. after doOSSpecificIncludes had already read
# FOG_os_id, and the other two scripts had no migration at all.
migrateDeprecatedKeys() {
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
    # Modeled on the httpproto/httpsRedirect seeding immediately below, which is
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
}
doOSSpecificIncludes() {
    echo
    case ${FOG_os_id} in
        1)
            echo -e "\n\n  Starting Redhat based Installation\n\n"
            FOG_os_name="Redhat"
            . ../lib/redhat/config.sh
            ;;
        2)
            echo -e "\n\n  Starting Debian based Installation\n\n"
            FOG_os_name="Debian"
            . ../lib/ubuntu/config.sh
            ;;
        3)
            echo -e "\n\n  Starting Alpine Installation\n\n"
            FOG_os_name="Alpine"
            . ../lib/alpine/config.sh
            systemctl="no"
            ;;
        4)
            # Arch is systemd, so unlike Alpine it rides the same service
            # handling as Redhat and Debian and needs no override here.
            echo -e "\n\n  Starting Arch based Installation\n\n"
            FOG_os_name="Arch"
            . ../lib/arch/config.sh
            ;;
        *)
            echo -e "  Sorry, answer not recognized\n\n"
            sleep 2
            FOG_os_id=""
            ;;
    esac
    currentdir=$(pwd)
    # Both variables are tested for non-emptiness FIRST, and that is the whole
    # point rather than defensive noise: in a glob, `*$webdirdest*` with an
    # empty $webdirdest is `**`, which matches EVERY path. So whenever this
    # function reaches here without having sourced a distro config -- the `*)`
    # arm above blanks the id and returns rather than exiting -- the old form
    # refused to run from any directory at all, and said so in a message about
    # the install layout that had nothing to do with the real problem.
    #
    # That is exactly what GH-1120 produced: a pre-rename .fogsettings left
    # FOG_os_id unset, the `*)` arm fired, and the admin got "Sorry, answer not
    # recognized" followed by "Please change installation directory" about a
    # path that was perfectly fine. The key migration fixed the trigger; this
    # fixes the amplifier, which was never specific to that bug.
    if { [[ -n $webdirdest ]] && [[ $currentdir == *"$webdirdest"* ]]; } \
        || { [[ -n $tftpdirdst ]] && [[ $currentdir == *"$tftpdirdst"* ]]; }; then
        echo "Please change installation directory."
        echo "Running from here will fail."
        echo "You are in $currentdir which is a folder that will"
        echo "be moved during installation."
        exit 1
    fi
}
errorStat() {
    local status=$1
    local skipOk=$2
    if [[ $status != 0 ]]; then
        echo "Failed!"
        if [[ -z $exitFail ]]; then
            echo
            echo "!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!"
            echo "!! The installer was not able to run all the way to the end as   !!"
            echo "!! something has caused it to fail. The following few lines are  !!"
            echo "!! from the error log file which might help us figure out what's !!"
            echo "!! wrong. Please add this information when reporting an error.   !!"
            echo "!! As well you might want to take a look at the full error log   !!"
            echo "!! in $error_log !!"
            echo "!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!"
            echo
            tail -n 5 $error_log
            exit $status
        fi
        # $exitFail is set, so the caller handles this failure itself rather
        # than the process ending here. RETURN -- do not fall through: the
        # line below prints "OK", and reaching it after "Failed!" reported
        # both outcomes for the same step. All four scripts that source this
        # set exitFail (installfog.sh, updatefog.sh, restorekernel.sh,
        # revertfog.sh), so every failed step in any of them showed it.
        return $status
    fi
    [[ -z $skipOk ]] && echo "OK"
    # Explicit, because the line above is a && whose test is FALSE whenever
    # skipOk was passed -- which used to make this function return 1 for a
    # step that succeeded. Nothing reads the value today; this keeps it from
    # being a trap for the first caller that does.
    return 0
}
stopInitScript() {
    for serviceItem in $serviceList; do
        dots "Stopping $serviceItem Service"
        if [ "$systemctl" == "yes" ]; then
            systemctl is-active --quiet $serviceItem && systemctl stop $serviceItem >>$error_log 2>&1 || true
        else
            [[ ! -x $initdpath/$serviceItem ]] && echo "OK" && continue
            $initdpath/$serviceItem status >/dev/null 2>&1 && $initdpath/$serviceItem stop >>$error_log 2>&1
        fi
        echo "OK"
    done
}
startInitScript() {
    for serviceItem in $serviceList; do
        dots "Starting $serviceItem Service"
        if [[ $systemctl == yes ]]; then
            systemctl is-active --quiet $serviceItem && true || systemctl start $serviceItem >>$error_log 2>&1
        else
            [[ ! -x $initdpath/$serviceItem ]] && continue
            $initdpath/$serviceItem status >/dev/null 2>&1 || $initdpath/$serviceItem start >>$error_log 2>&1
        fi
        errorStat $?
    done
}
enableInitScript() {
    for serviceItem in $serviceList; do
        case $systemctl in
            yes)
                dots "Setting permissions on $serviceItem script"
                chmod 644 $initdpath/$serviceItem >>$error_log 2>&1
                errorStat $?
                dots "Enabling $serviceItem Service"
                systemctl is-enabled --quiet $serviceItem && true || systemctl enable $serviceItem >>$error_log 2>&1
                if [[ ! $? -eq 0 && ${FOG_os_id} -eq 2 ]]; then
                    update-rc.d $(echo $serviceItem | sed -e 's/[.]service//g') enable 2 >>$error_log 2>&1
                    update-rc.d $(echo $serviceItem | sed -e 's/[.]service//g') enable 3 >>$error_log 2>&1
                    update-rc.d $(echo $serviceItem | sed -e 's/[.]service//g') enable 4 >>$error_log 2>&1
                    update-rc.d $(echo $serviceItem | sed -e 's/[.]service//g') enable 5 >>$error_log 2>&1
                fi
                ;;
            *)
                dots "Setting $serviceItem script executable"
                chmod +x $initdpath/$serviceItem >>$error_log 2>&1
                errorStat $?
                case ${FOG_os_id} in
                    1)
                        dots "Enabling $serviceItem Service"
                        chkconfig $serviceItem on >>$error_log 2>&1
                        ;;
                    2)
                        dots "Enabling $serviceItem Service"
                        sysv-rc-conf $serviceItem off >>$error_log 2>&1
                        sysv-rc-conf $serviceItem on >>$error_log 2>&1
                        case $linuxReleaseName_lower in
                            *ubuntu*|*mint*)
                                /usr/lib/insserv/insserv -r $initdpath/$serviceItem >>$error_log 2>&1
                                /usr/lib/insserv/insserv -d $initdpath/$serviceItem >>$error_log 2>&1
                                ;;
                            *)
                                insserv -r $initdpath/$serviceItem >>$error_log 2>&1
                                insserv -d $initdpath/$serviceItem >>$error_log 2>&1
                                ;;
                        esac
                        ;;
                    3)
                        # Alpine is OpenRC: neither chkconfig nor sysv-rc-conf
                        # exists, so this case matched nothing at all. An
                        # unmatched `case` exits 0 and the errorStat below then
                        # printed OK -- so the install reported nine daemons as
                        # enabled while none of them had been. They were only
                        # ever started by hand from startInitScript, and the
                        # server came back from a reboot serving the web UI
                        # with no scheduler, replicator or multicast. See #863.
                        #
                        # The runlevel is named rather than left to default to
                        # the current one: an install run from a rescue or
                        # single-user shell would otherwise enroll the daemons
                        # in a runlevel that never comes up on boot.
                        dots "Enabling $serviceItem Service"
                        rc-update add $serviceItem default >>$error_log 2>&1
                        ;;
                esac
                ;;
        esac
        errorStat $?
    done
}
installInitScript() {
    dots "Installing FOG System Scripts"
    cp -f $initdsrc/* $initdpath/ >>$error_log 2>&1
    local cpstat=$? unitfile
    # GH-850: the shipped unit and init scripts hard-code /opt/fog/service in
    # their ExecStart/DAEMON/command lines and used to be copied verbatim, so a
    # non-default $servicedst installed cleanly and then every daemon failed to
    # start on a path that did not exist. This was already wrong before the base
    # path became configurable -- $servicedst has always been settable on its own.
    #
    # Rewrite the installed copies, never the sources: cp -f above restores the
    # /opt/fog original on every run, so the substitution stays idempotent and a
    # later re-run with a different path cannot compound.
    if [[ ${servicedst%/} != /opt/fog/service ]]; then
        for unitfile in $initdsrc/*; do
            sed -i "s|/opt/fog/service|${servicedst%/}|g" \
                "$initdpath/$(basename $unitfile)" >>$error_log 2>&1
        done
    fi
    # ADR 0010: FOGPluginRunner and FOGRetentionRunner are the two daemons that
    # do NOT run as root. The plugin runner executes third-party plugin code,
    # which runs as the web user everywhere else; the retention runner needs a
    # database connection and nothing else. Their shipped unit/init scripts
    # carry the literal FOGWEBUSER, rewritten here to the real account in the
    # INSTALLED copy only, on the same "cp -f restores the source every run"
    # reasoning as the path substitution above.
    #
    # The loop is over every unit file rather than those two by name, which is
    # why adding the second one needed no change here.
    #
    # Unconditional, unlike that one. A placeholder left in place is not a
    # cosmetic default: systemd refuses to start a unit whose User= does not
    # resolve, which is the intended failure -- loud, rather than quietly
    # running plugin code, or a sweep that issues DELETEs, as root.
    for unitfile in $initdsrc/*; do
        sed -i "s|FOGWEBUSER|${apacheuser}|g" \
            "$initdpath/$(basename $unitfile)" >>$error_log 2>&1
    done
    # Alpine's OpenRC scripts name FOGPHPBIN as the interpreter; resolve it to
    # the php binary this host actually has. Alpine ships no unversioned "php"
    # -- both the package and the executable are php8x -- so the daemons'
    # own "#!/usr/bin/php -q" shebang cannot work there, and the systemd
    # units' "/usr/bin/env php" would not either. Same "rewrite the INSTALLED
    # copy, never the source" reasoning as the two substitutions above.
    # See #863.
    if [[ ${FOG_os_id} -eq 3 ]]; then
        local fogphpbin
        fogphpbin=$(command -v "php${php_apk}" 2>/dev/null) \
            || fogphpbin=$(command -v php 2>/dev/null)
        for unitfile in $initdsrc/*; do
            sed -i "s|FOGPHPBIN|${fogphpbin}|g" \
                "$initdpath/$(basename $unitfile)" >>$error_log 2>&1
        done
    fi
    # Guarded: on Alpine and other non-systemd hosts systemctl does not exist,
    # and the old `cp && systemctl daemon-reload` chain made errorStat report
    # this step as Failed purely because the reload could not run.
    [[ $systemctl == yes ]] && systemctl daemon-reload >>$error_log 2>&1
    errorStat $cpstat
    echo
    echo
    echo " * Configuring FOG System Services"
    echo
    echo
    enableInitScript
}
configureMySql() {
    # ---------------------------------------------------------
    # External Unprivileged Database Implementation
    # ---------------------------------------------------------
    if [[ ${DB_external} == yes ]]; then
        dots "Verifying external database connection"

        # Test connection and ensure the database exists and is accessible.
        # The database name is ${DB_name}; this read $snmysqldb, which is
        # never assigned anywhere, so the statement was always `USE ;` -- a
        # syntax error. The check could only ever fail, which made every
        # DB_external=1 install exit 1 here, and the error below named an
        # empty database.
        mysql -h "${DB_host}" -u "${DB_user}" -p"${DB_password}" -e "USE ${DB_name};" >/dev/null 2>&1
        local externalok=$?
        # GH-685: an external master without TLS is refused outright by a modern
        # MariaDB client. See detectMysqlSslOption.
        if [[ $externalok -ne 0 ]] && detectMysqlSslOption -h "${DB_host}" -u "${DB_user}" -p"${DB_password}"; then
            mysql -h "${DB_host}" -u "${DB_user}" -p"${DB_password}" $mysqlsslopt -e "USE ${DB_name};" >/dev/null 2>&1
            externalok=$?
        fi

        if [[ $externalok -ne 0 ]]; then
            echo "Failed!"
            echo " * Error: Cannot connect to the external database '${DB_name}' at '${DB_host}'."
            echo " * Please verify your credentials in $fogprogramdir/.fogsettings and ensure the DB exists."
            exit 1
        fi

        echo "OK"

        # Return early to skip local DB configuration, preserving existing logic
        return 0
    fi
    # ---------------------------------------------------------
    stopInitScript
    dots "Setting up and starting MySQL"
    # Resolve exactly one unit name.
    #
    # `grep -o` prints one line per match, and `systemctl list-unit-files` lists
    # the mysql/mysqld alias symlinks alongside the real unit -- so the fallback
    # yielded a three-line $dbservice (it does on a stock Fedora box). Unquoted,
    # that word-split into `systemctl enable|stop|start` as three arguments, two
    # of them alias names rather than the real unit. The fallback is the
    # fresh-install path: the primary lookup only sees units already running, and
    # RedHat-family packages do not auto-start the DB.
    # Guarded on $systemctl: these two ran unconditionally, so every Alpine
    # install printed "systemctl: command not found" twice onto the console in
    # the middle of the database step. Harmless in itself -- $dbservice is
    # overwritten just below for osid 3 -- but it is the first thing an
    # operator sees go wrong, and it goes to the terminal rather than the error
    # log. See #863.
    dbunits=""
    if [[ $systemctl == yes ]]; then
        dbunits=$(systemctl list-units | grep -o -e "mariadb\.service" -e "mysqld\.service" -e "mysql\.service" | tr -d '@')
        [[ -z $dbunits ]] && dbunits=$(systemctl list-unit-files | grep -v bad | grep -o -e "mariadb\.service" -e "mysqld\.service" -e "mysql\.service" | tr -d '@')
    fi
    # Preference is explicit because grep cannot express it -- `-e` order does not
    # rank matches, it just reports whichever appeared first in the input. Real
    # unit first, aliases after.
    dbservice=""
    for dbcandidate in mariadb.service mysqld.service mysql.service; do
        if grep -qFx "$dbcandidate" <<<"$dbunits"; then
            dbservice=$dbcandidate
            break
        fi
    done
    # Switchout dbservice for alpine
    [[ ${FOG_os_id} -eq 3 ]] && dbservice=$(rc-service -l | grep mariadb | head -1)
    for mysqlconf in $(grep -rl '.*skip-networking' /etc | grep -v init.d); do
        sed -i '/.*skip-networking/ s/^#*/#/' -i $mysqlconf >>$error_log 2>&1
    done
    for mysqlconf in `grep -rl '.*bind-address.*=.*127.0.0.1' /etc | grep -v init.d`; do
        sed -e '/.*bind-address.*=.*127.0.0.1/ s/^#*/#/' -i $mysqlconf >>$error_log 2>&1
    done
    if [[ $systemctl == yes ]]; then
        # Arch leaves the data directory to the admin -- its mariadb package
        # runs no equivalent of Debian's or RedHat's post-install setup. Start
        # the service without doing this and it aborts with "Can't open and
        # lock privilege tables: Table 'mysql.db' doesn't exist". The 1.5 line
        # had this under osid 3, when 3 meant Arch; it was dropped rather than
        # renumbered when 3 became Alpine. mariadb-install-db is the current
        # name, mysql_install_db the compatibility alias for older releases.
        if [[ ${FOG_os_id} -eq 4 && ! -f /var/lib/mysql/mysql/db.MAD && ! -f /var/lib/mysql/mysql/db.frm ]]; then
            dots "Initializing the MariaDB data directory"
            mkdir -p /var/lib/mysql >>$error_log 2>&1
            chown -R mysql:mysql /var/lib/mysql >>$error_log 2>&1
            if command -v mariadb-install-db >/dev/null 2>&1; then
                mariadb-install-db --user=mysql --basedir=/usr --datadir=/var/lib/mysql >>$error_log 2>&1
            else
                mysql_install_db --user=mysql --basedir=/usr --datadir=/var/lib/mysql >>$error_log 2>&1
            fi
            errorStat $?
        fi
        systemctl is-enabled --quiet "$dbservice" || systemctl enable "$dbservice" >>$error_log 2>&1
        systemctl is-active --quiet "$dbservice" && systemctl stop "$dbservice" >>$error_log 2>&1
        systemctl start "$dbservice" >>$error_log 2>&1
    else
        case ${FOG_os_id} in
            1)
                chkconfig mysqld on >>$error_log 2>&1
                service mysqld start >>$error_log 2>&1
                ;;
            2)
                sysv-rc-conf mysql on >>$error_log 2>&1
                service mysql start >>$error_log 2>&1
                ;;
            3)
                rc-update add mariadb default >>$error_log 2>&1
                service mariadb setup >>$error_log 2>&1
                service mariadb start >>$error_log 2>&1
                ;;
        esac
    fi
    errorStat $?
    dots "Testing connection to database"
    # if someone still has DB user root set in .fogsettings we want to change that
    [[ "x${DB_user}" == "xroot" ]] && DB_user='fogmaster'
    [[ -z ${DB_password} ]] && DB_password=$(generatePassword 20)
    [[ -n ${DB_host} ]] && host="--host=${DB_host}"
    sqloptionsroot="${host} --user=root"
    sqloptionsuser="${host} -s --user=${DB_user}"
    # GH-685: a TLS-insisting client breaks every statement below, not just the
    # first, so settle the question once and bake the answer into the shared
    # option strings. Only TCP connections negotiate TLS -- an empty
    # ${DB_host} means the local socket, where the question cannot arise.
    if [[ -n ${DB_host} ]] \
        && ! mysql $sqloptionsuser --password="${DB_password}" --execute="quit" >/dev/null 2>&1 \
        && detectMysqlSslOption $sqloptionsuser --password="${DB_password}"; then
        host="${host} ${mysqlsslopt}"
        sqloptionsroot="${sqloptionsroot} ${mysqlsslopt}"
        sqloptionsuser="${sqloptionsuser} ${mysqlsslopt}"
    fi
    mysqladmin $host ping >/dev/null 2>&1 || mysqladmin $host ping >/dev/null 2>&1 || mysqladmin $host ping >/dev/null 2>&1
    errorStat $?

    dots "Setting up MySQL user and database"
    mysql $sqloptionsroot --execute="quit" >/dev/null 2>&1
    connect_as_root=$?
    if [[ $connect_as_root -eq 0 ]]; then
        # Try to detect if we can login to the database as root without a password
        # as there are many legacy installs with empty root password and we want to
        # make things more secure. Since MariaDB 10.1 the authentication plugin
        # called unix_socket is used by default for the DB root account and we want
        # to check if that is the case here first. In case it is a root login with
        # empty or without password is also possible but unix_socket makes it way
        # more secure and if it's set to unix_socket we don't mess with it!
        # MariaDB 10.4 introduced a new table called mysql.global_priv to keep the
        # login information. While mysql.user still exists mysql.global_priv is now
        # in charge. So we need to check that first.
        mysqlrootauth=$(mysql $sqloptionsroot --database=mysql --execute="SELECT * FROM global_priv WHERE Host='localhost' AND User='root' AND Priv LIKE '%unix_socket%'" 2>/dev/null)
        [[ -z $mysqlrootauth ]] && mysqlrootauth=$(mysql $sqloptionsroot --database=mysql --execute="SELECT Host,User,plugin FROM user WHERE Host='localhost' AND User='root' AND plugin='unix_socket'" 2>/dev/null)
        if [[ -z $mysqlrootauth && -z $autoaccept ]]; then
            echo
            echo "   The installer detected a blank database *root* password. This"
            echo "   is very common on a new install or if you upgrade from any"
            echo "   version of FOG before 1.5.8. To improve overall security we ask"
            echo "   you to supply an appropriate database *root* password now."
            echo
            echo "   NOTICE: Make sure you choose a good password but also one"
            echo "   you can remember or use a password manager to store it."
            echo "   The installer won't store the given password in any place"
            echo "   and it will be lost right after the installer finishes!"
            echo
            echo -n "   Please enter a new database *root* password to be set: "
            read -rs snmysqlrootpass
            echo
            echo
            if [[ -z $snmysqlrootpass ]]; then
                snmysqlrootpass=$(generatePassword 20)
                echo
                echo "   We don't accept a blank database *root* password anymore and"
                echo "   will generate a password for you to use. Please make sure"
                echo "   you save the following password in an appropriate place as"
                echo "   the installer won't store it for you."
                echo
                echo "   Database root password: $snmysqlrootpass"
                echo
                echo "   Press [Enter] to procede..."
                read -rs procede
                echo
                echo
            fi
            # WARN: Since MariaDB 10.3 (maybe earlier) setting a password when auth plugin is
            # set to unix_socket will actually switch to auth plugin mysql_native_password
            # automatically which was not the case in MariaDB 10.1 and is causing trouble.
            # So instead of SET PASSWORD we now use mysqladmin as it does not alter the
            # MariaDB auth plugin used.
            mysqladmin $sqloptionsroot password "${snmysqlrootpass}" >>$error_log 2>&1
        fi
        snmysqlstoragepass=$(mysql -s $sqloptionsroot --password="${snmysqlrootpass}" --execute="SELECT settingValue FROM globalSettings WHERE settingKey LIKE '%FOG_STORAGENODE_MYSQLPASS%'" ${DB_name} 2>/dev/null | tail -1)
    else
        snmysqlstoragepass=$(mysql $sqloptionsuser --password="${DB_password}" --execute="SELECT settingValue FROM globalSettings WHERE settingKey LIKE '%FOG_STORAGENODE_MYSQLPASS%'" ${DB_name} 2>/dev/null | tail -1)
    fi
    mysql $sqloptionsuser --password="${DB_password}" --execute="quit" >/dev/null 2>&1
    connect_as_fogmaster=$?
    mysql ${host} -s --user=fogstorage --password="${snmysqlstoragepass}" --execute="quit" >/dev/null 2>&1
    connect_as_fogstorage=$?
    if [[ $connect_as_fogmaster -eq 0 && $connect_as_fogstorage -eq 0 ]]; then
        echo "Skipped"
        return
    fi

    # If we reach this point it's clear that this install is not setup with
    # unpriviledged DB users yet and we need to have root DB access now.
    if [[ $connect_as_root -ne 0 ]]; then
        echo
        echo "   To improve the overall security the installer will create an"
        echo "   unpriviledged database user account for FOG's database access."
        echo "   Please provide the database *root* user password. Be asured"
        echo "   that this password will only be used while the FOG installer"
        echo -n "   is running and won't be stored anywhere: "
        read -rs snmysqlrootpass
        echo
        echo
        mysql $sqloptionsroot --password="${snmysqlrootpass}" --execute="quit" >/dev/null 2>&1
        if [[ $? -ne 0 ]]; then
            echo "   Unable to connect to the database using the given password!"
            echo -n "   Try again: "
            read -rs snmysqlrootpass
            mysql $sqloptionsroot --password="${snmysqlrootpass}" --execute="quit" >/dev/null 2>&1
            if [[ $? -ne 0 ]]; then
                echo
                echo "   Failed! Terminating installer now."
                exit 1
            fi
        fi
    fi

    snmysqlstoragepass=$(mysql -s $sqloptionsroot --password="${snmysqlrootpass}" --execute="SELECT settingValue FROM globalSettings WHERE settingKey LIKE '%FOG_STORAGENODE_MYSQLPASS%'" ${DB_name} 2>/dev/null | tail -1)
    # generate a new fogstorage password if it doesn't exist yet or if it's old style fs0123456789
    if [[ -z $snmysqlstoragepass ]]; then
        snmysqlstoragepass=$(generatePassword 20)
    elif [[ -n $(echo $snmysqlstoragepass | grep "^fs[0-9][0-9]*$") ]]; then
        snmysqlstoragepass=$(generatePassword 20)
        echo
        echo "   The current *fogstorage* database password does not meet high"
        echo "   security standards. We will generate a new password and update"
        echo "   all the settings on this FOG server for you. Please take note"
        echo "   of the following credentials that you need to manually update"
        echo "   on all your storage nodes' $fogprogramdir/.fogsettings configuration"
        echo "   files and re-run (!) the FOG installer:"
        echo "   DB_user='fogstorage'"
        echo "   DB_password='${snmysqlstoragepass}'"
        echo
        if [[ -z $autoaccept ]]; then
            echo "   Press [Enter] to proceed after you noted down the credentials."
            read
        fi
    fi
    # As above: no cd here, so the old failure was a heredoc write into a missing
    # directory followed by mysql being handed a file that was never created.
    local sqltmp=""
    if ! sqltmp="$(_installerTmpDir)"; then
        echo "Failed"
        echo " * Could not prepare the installer's scratch directory."
        return 1
    fi
    cat >"${sqltmp}/fog-db-and-user-setup.sql" <<EOF
SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='ANSI' ;
DELETE FROM mysql.user WHERE User='' ;
DELETE FROM mysql.user WHERE User='root' AND Host NOT IN ('localhost', '127.0.0.1', '::1') ;
DROP DATABASE IF EXISTS test ;
DELETE FROM mysql.db WHERE Db='test' OR Db='test\\_%' ;
CREATE DATABASE IF NOT EXISTS ${DB_name} ;
USE ${DB_name} ;
DROP PROCEDURE IF EXISTS ${DB_name}.create_user_if_not_exists ;
DELIMITER $$
CREATE PROCEDURE ${DB_name}.create_user_if_not_exists()
BEGIN
  DECLARE masteruser BIGINT DEFAULT 0 ;
  DECLARE storageuser BIGINT DEFAULT 0 ;

  SELECT COUNT(*) INTO masteruser FROM mysql.user
    WHERE User = '${DB_user}' and  Host = '${DB_host}' ;
  IF masteruser > 0 THEN
    DROP USER '${DB_user}'@'${DB_host}';
  END IF ;
  CREATE USER '${DB_user}'@'${DB_host}' IDENTIFIED BY '${DB_password}' ;
  GRANT ALL PRIVILEGES ON ${DB_name}.* TO '${DB_user}'@'${DB_host}' ;

  SELECT COUNT(*) INTO storageuser FROM mysql.user
    WHERE User = 'fogstorage' and  Host = '%' ;
  IF storageuser > 0 THEN
    DROP USER 'fogstorage'@'%';
  END IF ;
  CREATE USER 'fogstorage'@'%' IDENTIFIED BY '${snmysqlstoragepass}' ;
END ;$$
DELIMITER ;
CALL ${DB_name}.create_user_if_not_exists() ;
DROP PROCEDURE IF EXISTS ${DB_name}.create_user_if_not_exists ;
FLUSH PRIVILEGES ;
SET SQL_MODE=@OLD_SQL_MODE ;
EOF
    mysql $sqloptionsroot --password="${snmysqlrootpass}" <"${sqltmp}/fog-db-and-user-setup.sql" >>$error_log 2>&1
    errorStat $?
}
configureFOGService() {
    [[ ! -d $servicedst ]] && mkdir -p $servicedst >>$error_log 2>&1
    [[ ! -d $servicedst/etc ]] && mkdir -p $servicedst/etc >>$error_log 2>&1
    echo "<?php define('WEBROOT','${webdirdest}');" > $servicedst/etc/config.php
    startInitScript
}
configureNFS() {
    dots "Setting up NFS configuration file"
    if [[ -f "/etc/nfs.conf" ]]; then
        # Fix all set port=20048 back to default values
        sed -i '/^port=20048/ {s/^port=20048/# port=0/}' /etc/nfs.conf >>$error_log 2>&1
    fi
    # set port in nfs.conf.d directory
    if [[ -f "/etc/nfs.conf" && ! -d "/etc/nfs.conf.d/" ]]; then
        mkdir /etc/nfs.conf.d
    elif [[ -f "/usr/etc/nfs.conf" && ! -d "/usr/etc/nfs.conf.d/" ]]; then
        mkdir /usr/etc/nfs.conf.d
    fi
    if [[ -f "/etc/nfs.conf" && ! -f "/etc/nfs.conf.d/fog-nfs.conf" ]]; then
        cat > /etc/nfs.conf.d/fog-nfs.conf <<EOF
[mountd]
port=20048
EOF
    elif [[ -f "/usr/etc/nfs.conf" && ! -f "/usr/etc/nfs.conf.d/fog-nfs.conf" ]]; then
        cat > /usr/etc/nfs.conf.d/fog-nfs.conf <<EOF
[mountd]
port=20048
EOF
    fi
    errorStat $?
    dots "Setting up exports file"
    if [[ ${STORAGE_rebuild_nfs_exports} != yes ]]; then
        echo "Skipped"
        if [[ -f "$nfsconfig" ]] && grep -q "no_root_squash" "$nfsconfig"; then
            echo
            echo "  ** WARNING: ${nfsconfig} still exports with no_root_squash."
            echo "  ** Captures land as root, so moving the image out of"
            echo "  ** ${STORAGE_image_share_path}/dev fails with '550 Rename failed'."
            echo "  ** Replace the ${STORAGE_image_share_path}/dev export options with:"
            echo "  **   all_squash,anonuid=$(id -u ${SVC_user}),anongid=$(id -g ${SVC_user})"
            echo
        fi
    else
        mv -fv "${nfsconfig}" "${nfsconfig}.${timestamp}" >>$error_log 2>&1
        userId=$(id -u ${SVC_user})
        groupId=$(id -g ${SVC_user})
        echo -e "${STORAGE_image_share_path} *(ro,sync,no_wdelay,subtree_check,insecure_locks,all_squash,anonuid=${userId},anongid=${groupId},fsid=0)\n${STORAGE_image_share_path}/dev *(rw,async,no_wdelay,subtree_check,all_squash,anonuid=${userId},anongid=${groupId},fsid=1)" > "$nfsconfig"
        diffconfig "${nfsconfig}"
        errorStat $?
        dots "Setting up and starting RPCBind"
        if [[ $systemctl == yes ]]; then
            systemctl is-enabled --quiet rpcbind && true || systemctl enable rpcbind.service >>$error_log 2>&1
            systemctl is-active --quiet rpcbind && systemctl stop rpcbind.service >>$error_log 2>&1 || true
            systemctl is-active --quiet rpcbind && true || systemctl start rpcbind.service >>$error_log 2>&1
            systemctl status rpcbind.service >>$error_log 2>&1
        else
            case ${FOG_os_id} in
                1)
                    chkconfig rpcbind on >>$error_log 2>&1
                    $initdpath/rpcbind stop >>$error_log 2>&1
                    $initdpath/rpcbind start >>$error_log 2>&1
                    $initdpath/rpcbind status >>$error_log 2>&1
                    ;;
                3)
                    # This case had a single arm, so Alpine matched nothing and
                    # the errorStat that follows printed OK for a step that did
                    # not run. NFS is what FOS mounts to read an image, so the
                    # visible symptom was a deploy failing at mount time on a
                    # server whose install said RPCBind was started. See #863.
                    rc-update add rpcbind default >>$error_log 2>&1
                    rc-service rpcbind stop >>$error_log 2>&1
                    rc-service rpcbind start >>$error_log 2>&1
                    rc-service rpcbind status >>$error_log 2>&1
                    ;;
            esac
        fi
        errorStat $?
        dots "Setting up and starting NFS Server"
        for nfsItem in $nfsservice; do
            if [[ $systemctl == yes ]]; then
                systemctl is-enabled --quiet $nfsItem && true || systemctl enable $nfsItem >>$error_log 2>&1
                systemctl is-active --quiet $nfsItem && systemctl stop $nfsItem >>$error_log 2>&1 || true
                systemctl is-active --quiet $nfsItem && true || systemctl start $nfsItem >>$error_log 2>&1
                systemctl status $nfsItem >>$error_log 2>&1
            else
                case ${FOG_os_id} in
                    1)
                        chkconfig $nfsItem on >>$error_log 2>&1
                        $initdpath/$nfsItem stop >>$error_log 2>&1
                        $initdpath/$nfsItem start >>$error_log 2>&1
                        $initdpath/$nfsItem status >>$error_log 2>&1
                        ;;
                    2)
                        sysv-rc-conf $nfsItem on >>$error_log 2>&1
                        $initdpath/nfs-kernel-server stop >>$error_log 2>&1
                        $initdpath/nfs-kernel-server start >>$error_log 2>&1
                        ;;
                    3)
                        # $nfsservice is a candidate list and the loop breaks on
                        # the first name that works; Alpine's nfs-utils-openrc
                        # calls it "nfs", which is the last of the three. No
                        # osid 3 arm meant no candidate ever ran. See #863.
                        [[ ! -x $initdpath/$nfsItem ]] && continue
                        rc-update add $nfsItem default >>$error_log 2>&1
                        rc-service $nfsItem stop >>$error_log 2>&1
                        rc-service $nfsItem start >>$error_log 2>&1
                        rc-service $nfsItem status >>$error_log 2>&1
                        ;;
                esac
            fi
            [[ $? -eq 0 ]] && break
        done
        errorStat $?
    fi
}
configureSnapins() {
    dots "Setting up FOG Snapins"
    mkdir -p $snapindir >>$error_log 2>&1
    if [[ -d $snapindir ]]; then
        # ${PKI_client_cert_dir} lives under $snapindir, so these two lines used to hand the
        # CA private key to the web user at mode 775 -- and, running AFTER
        # createSSLCA in the install sequence, they undid whatever permissions
        # certificate creation had just set. Pruning that subtree is what makes
        # the key isolation in _hardenPkiPermissions actually survive a run.
        #
        # -path, not -name: ${PKI_client_cert_dir} is an absolute path, and prune has to
        # match the directory itself for its contents to be skipped.
        _resolveSslPath
        find "$snapindir" -path "${PKI_client_cert_dir}" -prune -o -exec chmod 775 {} + >>$error_log 2>&1
        find "$snapindir" -path "${PKI_client_cert_dir}" -prune -o -exec chown ${SVC_user}:$apacheuser {} + >>$error_log 2>&1
    fi
    errorStat $?
    # GH-964: same usr_t problem as the cache directory, and here the write is
    # the whole point -- uploading a snapin through the web UI is a write into
    # this directory by httpd_t. Read-only labels would let the list render and
    # fail only on upload.
    #
    # fog_share_t rather than httpd_sys_rw_content_t because snapins replicate
    # between storage nodes over FTP (FOGService::replicateItems runs lftp), so
    # the RECEIVING node's vsftpd -- ftpd_t -- writes here too. ftpd_t gets
    # nothing at all on httpd_sys_rw_content_t unless ftpd_full_access is on,
    # and that boolean grants ftpd_t every file type on the box. See
    # packages/selinux/fog.te.
    setSELinuxContext "$snapindir" fog_share_t
}
# Install the node-certificate signing helper and the sudoers rule that lets
# the web tier reach it.
#
# Master only. A storage node has no CA to sign with, and installing the rule
# there would grant its web user a sudo entry for a script that can only fail.
#
# Deliberately the same shape as _installSecureBootSigner: a root-only config
# holding the paths, a staging directory the web user owns, and a validated
# sudoers drop-in. The web user learns nothing about where the keys live and
# cannot rewrite these paths to point somewhere else.
_installNodeCertSigner() {
    local bindir="${fogprogramdir}/bin"
    local helper="${bindir}/fog-sign-node-cert"
    local conf="${fogprogramdir}/.fog-pki"
    local stagedir="${fogprogramdir}/nodecert-staging"
    local sudoersfile="/etc/sudoers.d/fog-pki"

    # Guarded here as well as at the call site. A storage node reaching this
    # would install a sudo rule for a helper with no CA behind it, and the call
    # site is exactly the kind of thing a later refactor moves.
    #
    # No Web CA means nothing to issue from either -- a server whose root could
    # not anchor an intermediate, or an install that has not got that far.
    # Remove any rule a previous run installed rather than leaving a sudo entry
    # for a helper that cannot work.
    if [[ ${FOG_install_type} == [Ss] ]] || \
       [[ -z ${PKI_web_ca_cert} || ! -f ${PKI_web_ca_cert} || ${PKI_web_ca_cert} == "${PKI_root_ca_cert}" ]]; then
        rm -f "$helper" "$conf" "$sudoersfile" >>$error_log 2>&1
        return 0
    fi

    dots "Installing node certificate signing helper"
    mkdir -p "$bindir" >>$error_log 2>&1
    install -o root -g root -m 0755 ../packages/pki/fog-sign-node-cert "$helper" >>$error_log 2>&1 || {
        echo "Failed"
        return 0
    }
    # Point the helper at this install's config. It takes no path arguments on
    # purpose -- that is what stops a compromised web server naming its own CA
    # key -- so the location has to be baked in here. Quoted: $fogprogramdir
    # may contain a space, and CONF=/a/fog custom/x assigns "/a/fog" and then
    # tries to RUN "custom/x", which bash -n does not catch.
    sed -i "s|^CONF=.*|CONF=\"${conf}\"|" "$helper" >>$error_log 2>&1
    if ! grep -qxF "CONF=\"${conf}\"" "$helper"; then
        echo "Failed"
        echo " * Could not set the config path in $helper."
        return 0
    fi
    # The Secure Boot pair is written only when that CA actually exists, the
    # same way the Web pair above is gated by this function's early return.
    # Without the guard these were emitted unconditionally, so a server that
    # never minted a Secure Boot CA -- one running an admin-supplied MOK, or one
    # whose root cannot anchor an intermediate -- still advertised signing
    # issuance through sudo and answered every request with a 500 from the
    # helper. Naming a file that is not there is not a capability.
    local sbca="$(_pkiZoneDir secureboot)/ca/.fogSBCA.pem"
    {
        echo "PKI_WEB_CA_CERT=${PKI_web_ca_cert}"
        echo "PKI_WEB_CA_KEY=${PKI_web_ca_key}"
        echo "PKI_ROOT_CERT=${PKI_root_ca_cert}"
        # What the web zone is actually anchored by, and the chain a node should
        # serve beneath its leaf. Both differ from PKI_ROOT_CERT as soon as the
        # admin supplies their own Web CA: the FOG root never signed that
        # intermediate, so it can neither verify what this helper issues nor
        # belong in what the node serves. .trustAnchor.pem carries the FOG root
        # AND an imported root where there is one, so it is correct either way.
        echo "PKI_WEB_ANCHOR=$(_pkiZoneDir web)/ca/.trustAnchor.pem"
        echo "PKI_WEB_CHAIN=${PKI_web_trust_chain}"
        if [[ -f $sbca ]]; then
            echo "PKI_SB_CA_CERT=${sbca}"
            echo "PKI_SB_CA_KEY=$(_pkiZoneDir secureboot)/ca/.fogSBCA.key"
        fi
        # The agent zone, when it exists (createAgentIntermediateCA runs
        # from createSSLCA, so a master that has run this installer once has
        # it). Same gate as the Secure Boot pair: a path that is not there is
        # not a capability.
        if [[ -f "$(_pkiZoneDir agent)/ca/.fogAgentCA.pem" ]]; then
            echo "PKI_AGENT_CA_CERT=$(_pkiZoneDir agent)/ca/.fogAgentCA.pem"
            echo "PKI_AGENT_CA_KEY=$(_pkiZoneDir agent)/ca/.fogAgentCA.key"
        fi
        echo "PKI_STAGING=${stagedir}"
    } > "$conf"
    chown root:root "$conf" >>$error_log 2>&1
    chmod 0600 "$conf" >>$error_log 2>&1

    # The web user owns only the staging directory: it writes the request there
    # and reads the signed result back, and can reach nothing else.
    mkdir -p "$stagedir" >>$error_log 2>&1
    chown "${apacheuser}":"${apacheuser}" "$stagedir" >>$error_log 2>&1
    chmod 0750 "$stagedir" >>$error_log 2>&1

    # Validate before installing: a malformed sudoers drop-in breaks sudo for
    # the whole machine, which is far worse than no node certificate issuance.
    echo "${apacheuser} ALL=(root) NOPASSWD: ${helper}" > "${sudoersfile}.tmp"
    chmod 0440 "${sudoersfile}.tmp" >>$error_log 2>&1
    if visudo -cqf "${sudoersfile}.tmp" >>$error_log 2>&1; then
        mv -f "${sudoersfile}.tmp" "$sudoersfile" >>$error_log 2>&1
        chown root:root "$sudoersfile" >>$error_log 2>&1
        echo "Done"
    else
        rm -f "${sudoersfile}.tmp" >>$error_log 2>&1
        echo "Failed"
        echo " * Refusing to install an invalid sudoers rule; storage nodes will"
        echo "   keep generating their own self-signed certificates."
    fi
    # Said here, where the admin is the only one who can act on it, rather than
    # left for a node to discover as a 500 during its own install. Web issuance
    # is unaffected -- this server simply cannot sign kernels for a node.
    if [[ ! -f $sbca ]]; then
        echo " * No Secure Boot CA on this server, so storage nodes can be"
        echo "   issued web certificates but not code-signing ones."
    fi
    # After this function's own Done/Failed, never before. setSELinuxContext
    # prints its own dots()/OK pair, so calling it between our dots() and our
    # terminator left "Installing node certificate signing helper......" with
    # nothing closing it -- the SELinux line ran on into it and our "Done"
    # landed alone on the next line. Every other caller labels after its
    # errorStat for the same reason.
    setSELinuxContext "$stagedir" fog_share_t
}
# Install the PKI administration helper and the sudoers rule that lets the web
# tier reach it.
#
# Master only, and for a stronger reason than _installNodeCertSigner's: a
# storage node does not serve the management UI at all -- configureMinHttpd
# stubs out management/index.php -- so the Certificates page that drives this
# does not exist there. Installing the rule would grant a node's web user a
# sudo entry nothing can call.
#
# Same shape as _installNodeCertSigner and _installSecureBootSigner: a
# root-only config holding every path, a staging directory the web user owns,
# and a validated sudoers drop-in. The web user learns nothing about where the
# keys live and cannot rewrite these paths to point somewhere else.
#
# What makes this one different, and why the helper is a script rather than a
# sudo rule on some existing tool: one of its verbs writes .fogsettings, which
# root SOURCES AS SHELL on the next installer run. A helper that wrote
# arbitrary key=value pairs there would be a root shell with extra steps. The
# helper therefore takes a key from a three-entry allowlist and a value
# matching ^(yes|no)$, and tests/pki-admin-helper.test.sh holds it to that.
_installPkiAdminHelper() {
    local bindir="${fogprogramdir}/bin"
    local helper="${bindir}/fog-pki-admin"
    local conf="${fogprogramdir}/.fog-pki-admin"
    local stagedir="${fogprogramdir}/pkiadmin-staging"
    local sudoersfile="/etc/sudoers.d/fog-pki-admin"
    local webdir sbca

    # Guarded here as well as at the call site, for the reason
    # _installNodeCertSigner states: the call site is exactly the kind of thing
    # a later refactor moves. Remove any rule a previous run installed rather
    # than leaving a sudo entry for a helper nothing can reach -- a server
    # converted from master to node is the case that matters.
    if [[ ${FOG_install_type} == [Ss] ]]; then
        rm -f "$helper" "$conf" "$sudoersfile" >>$error_log 2>&1
        return 0
    fi

    dots "Installing the certificate management helper"
    mkdir -p "$bindir" >>$error_log 2>&1
    install -o root -g root -m 0755 ../packages/pki/fog-pki-admin "$helper" >>$error_log 2>&1 || {
        echo "Failed"
        return 0
    }
    # Point the helper at this install's config. It takes no path arguments on
    # purpose -- that is what stops a compromised web server naming its own CA
    # key or its own .fogsettings -- so the location has to be baked in here.
    # Quoted: $fogprogramdir may contain a space, and CONF=/a/fog custom/x
    # assigns "/a/fog" and then tries to RUN "custom/x", which bash -n does not
    # catch.
    sed -i "s|^CONF=.*|CONF=\"${conf}\"|" "$helper" >>$error_log 2>&1
    if ! grep -qxF "CONF=\"${conf}\"" "$helper"; then
        echo "Failed"
        echo " * Could not set the config path in $helper."
        return 0
    fi

    webdir="$(_pkiZoneDir web)"
    sbca="$(_pkiZoneDir secureboot)/ca/.fogSBCA.pem"
    {
        # Every path below names a PUBLIC certificate, except the two key
        # paths, which the helper only ever tests for EXISTENCE -- "is the
        # root key still on this server" is the one question the Certificates
        # page has always answered, and answering it needs the path, not the
        # contents.
        echo "PKI_ROOT_CERT=${PKI_root_ca_cert}"
        echo "PKI_ROOT_KEY=${PKI_root_ca_key}"
        echo "PKI_WEB_CA_KEY=${PKI_web_ca_key}"
        echo "PKI_WEB_VHOST_KEY=${PKI_web_vhost_key}"
        echo "PKI_CLIENT_KEY=${PKI_client_encrypt_key}"
        echo "PKI_WEB_CA_CERT=${PKI_web_ca_cert}"
        echo "PKI_WEB_CHAIN=${PKI_web_trust_chain}"
        echo "PKI_WEB_ANCHOR=${webdir}/ca/.trustAnchor.pem"
        echo "PKI_WEB_VHOST_CERT=${PKI_web_vhost_cert}"
        # The zone directory, so the helper can answer "is this leaf managed
        # outside FOG" exactly as _externallyManagedLeaf() does. GH-1120
        # retired the acmeLeaf key precisely because a persisted flag and the
        # filesystem could disagree; deriving it in two places from the same
        # test keeps that property.
        echo "PKI_WEB_ZONE_DIR=${webdir}"
        # The canonical home for an imported root, whichever route it came
        # in by -- the page's import-root writes here, and since GH-1683 so
        # does --ca-root, which used to record its own source path and so
        # named a temp file that was gone by the next run.
        echo "PKI_EXTERNAL_ROOT=${webdir}/ca/.externalRoot.pem"
        # Where a certificate you brought lives, and where the intermediates
        # that come with one are recorded. adopt-custom-leaf takes NO argument
        # at all -- it reads this directory out of the config rather than being
        # handed a path, which is stricter than ADR 0036 asks for rather than an
        # exception to it.
        echo "PKI_CUSTOM_DIR=$(_customPkiDir)"
        echo "PKI_WEB_EXTERNAL_CHAIN=${webdir}/leaf/.externalChain.pem"
        # The web engine, so the helper can reload it after installing a
        # certificate. This is the one change it makes that takes effect before
        # the next installer run; see ADR 0036's 2026-09-02 amendment for why
        # certificate material differs from the yes/no preferences.
        echo "WEB_ENGINE=${WEB_server_engine}"
        echo "PKI_CLIENT_CERT=${PKI_client_encrypt_cert}"
        if [[ -f $sbca ]]; then
            echo "PKI_SB_CA_CERT=${sbca}"
            echo "PKI_SB_CA_KEY=$(_pkiZoneDir secureboot)/ca/.fogSBCA.key"
        fi
        echo "PKI_SETTINGS=${fogprogramdir}/.fogsettings"
        # The agent zone, when it exists (createAgentIntermediateCA runs
        # from createSSLCA, so a master that has run this installer once has
        # it). Same gate as the Secure Boot pair: a path that is not there is
        # not a capability.
        if [[ -f "$(_pkiZoneDir agent)/ca/.fogAgentCA.pem" ]]; then
            echo "PKI_AGENT_CA_CERT=$(_pkiZoneDir agent)/ca/.fogAgentCA.pem"
            echo "PKI_AGENT_CA_KEY=$(_pkiZoneDir agent)/ca/.fogAgentCA.key"
        fi
        echo "PKI_STAGING=${stagedir}"
    } > "$conf"
    chown root:root "$conf" >>$error_log 2>&1
    chmod 0600 "$conf" >>$error_log 2>&1

    # The web user owns only the staging directory: it writes an upload there
    # and reads an exported certificate back, and can reach nothing else.
    mkdir -p "$stagedir" >>$error_log 2>&1
    chown "${apacheuser}":"${apacheuser}" "$stagedir" >>$error_log 2>&1
    chmod 0750 "$stagedir" >>$error_log 2>&1

    # Validate before installing: a malformed sudoers drop-in breaks sudo for
    # the whole machine, which is far worse than a Certificates page that can
    # only read.
    echo "${apacheuser} ALL=(root) NOPASSWD: ${helper}" > "${sudoersfile}.tmp"
    chmod 0440 "${sudoersfile}.tmp" >>$error_log 2>&1
    if visudo -cqf "${sudoersfile}.tmp" >>$error_log 2>&1; then
        mv -f "${sudoersfile}.tmp" "$sudoersfile" >>$error_log 2>&1
        chown root:root "$sudoersfile" >>$error_log 2>&1
        echo "Done"
    else
        rm -f "${sudoersfile}.tmp" >>$error_log 2>&1
        echo "Failed"
        echo " * Refusing to install an invalid sudoers rule; the Certificates"
        echo "   page will show the chain but will not be able to change it."
    fi
    # After this function own Done/Failed, never before -- setSELinuxContext
    # prints its own dots()/OK pair and would run into an unterminated line.
    setSELinuxContext "$stagedir" fog_share_t
}
# Ask the master for a certificate, as a storage node.
#
# Returns non-zero on any failure, and every caller treats that as "carry on
# with what this node did before". That fallback is not politeness: a node
# install must not break against a master that has not been updated yet, and
# during a staged rollout that is the normal case rather than the exception.
#
# Authentication is an HMAC over the request keyed with the fogstorage
# password, which this node already holds because it is how it reaches the
# master's database. The secret never crosses the wire. TLS verification is off
# for the same reason it has to be: this runs before the node has a certificate
# anything would trust, which is what it is here to fix.
_requestNodeCert() {
    local type="$1" keyout="$2" pemout="$3" chainout="$4"
    local csr b64 mac resp tmpdir st=0

    [[ -z ${DB_host} || -z ${DB_password} ]] && return 1
    [[ ${DB_host} == localhost || ${DB_host} == 127.0.0.1 ]] && return 1
    command -v curl >/dev/null 2>&1 || return 1
    command -v jq >/dev/null 2>&1 || return 1

    tmpdir=$(mktemp -d) || return 1
    csr="${tmpdir}/node.csr"
    # A fresh keypair each time. The node has no certificate to preserve
    # compatibility with -- unlike the communication key, nothing has pinned
    # this one.
    openssl genrsa -out "${tmpdir}/node.key" 4096 >>$error_log 2>&1 || st=1
    # The subject is a formality: the master ignores the names in this request
    # entirely and issues for what its own record of this node says. Sending
    # them anyway keeps the CSR well-formed and readable in a log.
    openssl req -new -sha256 -key "${tmpdir}/node.key" -out "$csr" \
        -subj "/CN=${NET_hostname:-${NET_fog_server_ip}}" >>$error_log 2>&1 || st=1
    if [[ $st -ne 0 ]]; then
        rm -rf "$tmpdir" >>$error_log 2>&1
        return 1
    fi
    # openssl base64 -A rather than base64 -w0: busybox base64 has no -w.
    b64=$(openssl base64 -A -in "$csr" 2>>$error_log)
    # Exactly the bytes the endpoint hashes: type, LF, the base64 body, and
    # nothing after it. printf, not echo, because echo appends a newline and
    # the two sides would then disagree by one byte and every request would be
    # rejected as a bad signature.
    mac=$(printf '%s\n%s' "$type" "$b64" \
        | openssl dgst -sha256 -hmac "${DB_password}" -hex 2>>$error_log \
        | awk '{print $NF}')
    # -k on purpose. This is the node<->master bootstrap: a node that has
    # never been issued a certificate has no anchor for the master either. The
    # control on this request is not TLS but the HMAC computed just above, keyed
    # on ${DB_password} -- a secret only a genuine node of this master holds -- and
    # what comes back is a certificate chain the caller validates on its own
    # terms. See registerStorageNode for the full reasoning.
    resp=$(curl -sS --noproxy '*' -k -X POST \
        -d "type=${type}" \
        -d "hmac=${mac}" \
        --data-urlencode "csr=${b64}" \
        "${WEB_url_proto:-http}://${DB_host}${WEB_root}service/nodecert.php" 2>>$error_log)

    if [[ -z $resp ]] || ! echo "$resp" | jq -e '.leaf' >/dev/null 2>&1; then
        # Surface the master's own explanation when it gave one -- "no storage
        # node is registered at this address" is a different problem from "the
        # master is too old", and they need different fixes.
        local why
        why=$(echo "$resp" | jq -r '.error // empty' 2>/dev/null)
        [[ -n $why ]] && echo " * The master declined to issue a ${type} certificate: ${why}"
        rm -rf "$tmpdir" >>$error_log 2>&1
        return 1
    fi
    echo "$resp" | jq -r '.leaf'  > "$pemout" 2>>$error_log || st=1
    echo "$resp" | jq -r '.chain' > "$chainout" 2>>$error_log || st=1
    cp -f "${tmpdir}/node.key" "$keyout" >>$error_log 2>&1 || st=1
    rm -rf "$tmpdir" >>$error_log 2>&1
    [[ $st -ne 0 ]] && return 1
    # A truncated or empty body passes the jq check above but produces a file
    # openssl cannot read, so confirm the certificate before the vhost is
    # pointed at it.
    openssl x509 -in "$pemout" -noout >/dev/null 2>&1 || return 1
    chmod 0600 "$keyout" >>$error_log 2>&1
    chmod 0644 "$pemout" "$chainout" >>$error_log 2>&1
    return 0
}
# Replace this node's self-signed web certificate with one the master issued.
#
# Runs LATE, after registerStorageNode, and that ordering is the whole reason
# this is a separate step rather than part of createSSLCA. The master will only
# issue to a node it already knows about, and a node registers itself at the
# very end of its own install -- so asking from inside createSSLCA meant every
# first install was refused, fell back to self-signed, and only picked up a
# real certificate on some later run.
#
# It writes to the paths the vhost was already given, so nothing has to be
# rewritten and the certificate takes effect on a reload rather than a
# reinstall.
_installNodeWebCert() {
    [[ ${FOG_install_type} == [Ss] ]] || return 0
    [[ -n ${PKI_web_vhost_key} && -n ${PKI_web_vhost_cert} ]] || return 0

    local chain="$(_pkiZoneDir web)/ca/.nodeChain.pem"
    # Already issued and still good: nothing to do. Without this, every upgrade
    # would mint a new keypair and a new certificate for no reason.
    if [[ -f $chain && -f ${PKI_web_vhost_cert} ]] && \
        openssl verify -CAfile "$chain" "${PKI_web_vhost_cert}" >>$error_log 2>&1; then
        # Still point ${PKI_web_trust_chain} at it. writeUpdateFile runs BEFORE this
        # function on a node (installfog.sh), so the assignment in the success
        # branch below is never persisted -- which means on every LATER run
        # ${PKI_web_trust_chain} arrives from .fogsettings still naming the node's own
        # self-signed CA, and _resolveTrustAnchor would anchor out of the wrong
        # file on exactly the runs that take this early return.
        PKI_web_trust_chain="$chain"
        return 0
    fi
    dots "Requesting a web certificate from the master"
    if _requestNodeCert web "${PKI_web_vhost_key}" "${PKI_web_vhost_cert}" "$chain"; then
        PKI_web_trust_chain="$chain"
        echo "Done"
        # The vhost already points at these paths, so a reload is all that is
        # needed -- and is needed, or the node keeps serving the old
        # certificate from memory until something else restarts it.
        systemctl reload "${WEB_server_engine}" >>$error_log 2>&1 || \
            systemctl restart "${WEB_server_engine}" >>$error_log 2>&1
    else
        echo "Skipped"
        echo " * This node keeps its own self-signed certificate, which is what"
        echo "   storage nodes have always used and works exactly as before. It"
        echo "   just means the certificate is trusted only where it has been"
        echo "   installed by hand."
        echo " * Update the master to this version and re-run the installer here"
        echo "   to have it issued from the FOG Web CA instead."
    fi
}
# Where this host keeps admin-supplied trust anchors, and what re-reads them.
#
# Chosen by what the box actually has rather than by ${FOG_os_id}. Derivatives
# disagree with their parent often enough, and ${FOG_os_id} is specifically untrustworthy
# for this: it means Alpine on this branch but meant Arch on 1.5 (GH-447), so a
# server upgraded from that line carries a value that would pick the wrong store.
# The layouts are mutually exclusive in practice -- a box has p11-kit's tree or
# Debian's, not both -- so first match wins.
#
# Sets $caTrustDir/$caTrustCmd. Returns 1 when the host has no store to write
# to, which is a real state (a minimal container without ca-certificates) and
# not an error.
_caTrustLayout() {
    if [[ -d /etc/pki/ca-trust/source/anchors ]] && command -v update-ca-trust >>$error_log 2>&1; then
        # RHEL/Fedora/Rocky/Alma
        caTrustDir="/etc/pki/ca-trust/source/anchors"
        caTrustCmd="update-ca-trust extract"
    elif [[ -d /etc/ca-certificates/trust-source/anchors ]] && command -v trust >>$error_log 2>&1; then
        # Arch
        caTrustDir="/etc/ca-certificates/trust-source/anchors"
        caTrustCmd="trust extract-compat"
    elif [[ -d /usr/local/share/ca-certificates ]] && command -v update-ca-certificates >>$error_log 2>&1; then
        # Debian/Ubuntu/Alpine
        caTrustDir="/usr/local/share/ca-certificates"
        caTrustCmd="update-ca-certificates"
    else
        return 1
    fi
    return 0
}
# Split a PEM bundle into one file per certificate, $2/c1.pem, c2.pem, ...
# Returns 1 when the source yielded no certificate at all.
#
# openssl cannot do this itself. `openssl x509` reads only the FIRST certificate
# in a bundle and silently ignores the rest -- it does not warn and it does not
# fail, which is exactly how a multi-certificate anchor gets truncated to one
# certificate with nothing in any log to say so.
_splitPemBundle() {
    local src="$1" dir="$2" f found=1
    [[ -n $src && -f $src && -n $dir && -d $dir ]] || return 1
    awk -v d="$dir" '/-----BEGIN CERTIFICATE-----/{n++} n{print > (d "/c" n ".pem")}' \
        "$src" 2>>$error_log
    for f in "$dir"/c*.pem; do
        [[ -f $f ]] && { found=0; break; }
    done
    return $found
}
# Print the self-signed (root) certificate out of a PEM bundle. Returns 1 when
# the bundle has none, which is a normal state -- a chain the master sent
# without a root -- and not an error.
#
# Selected by subject == issuer, never by position. The writers of these bundles
# do NOT agree on order: createWebIntermediateCA and fog-sign-node-cert both
# write issuer-first with the root appended, while validateExternalCA writes the
# root FIRST. A "last certificate in the file" rule therefore picks the root on
# a FOG-generated CA and the INTERMEDIATE on an external one -- which is how a
# storage node of an external-CA master came to anchor an intermediate.
# _writeWebChainFiles selects the same way, for the same reason.
_rootFromChain() {
    local bundle="$1" tmpd f subj issuer st=1
    [[ -n $bundle && -f $bundle ]] || return 1
    tmpd=$(mktemp -d) || return 1
    if _splitPemBundle "$bundle" "$tmpd"; then
        for f in "$tmpd"/c*.pem; do
            [[ -f $f ]] || continue
            subj=$(openssl x509 -in "$f" -noout -subject 2>/dev/null)
            issuer=$(openssl x509 -in "$f" -noout -issuer 2>/dev/null)
            [[ -z $subj ]] && continue
            # -subject prints "subject=..." and -issuer "issuer=...", so compare
            # the values rather than the whole line.
            if [[ ${subj#subject=} == "${issuer#issuer=}" ]]; then
                cat "$f"
                st=0
                break
            fi
        done
    fi
    rm -rf "$tmpd" >>$error_log 2>&1
    return $st
}
# Which certificates this box should anchor. Sets $trustAnchorPem to a bundle;
# returns 1 when there is nothing to anchor yet.
#
# Two certificates can matter here and they are not always the same one, which
# is what this used to get wrong. It anchored ${PKI_root_ca_cert} on a master, full stop.
# That is right only while FOG issues the web certificate itself:
#
#   * ${PKI_root_ca_cert} is FOG's own root. It signs the client-communication leaf and
#     every storage-node certificate whatever the vhost is serving, and it is
#     what ca.cert.der publishes and fog-client pins.
#   * The root the SERVED chain terminates in is a different certificate as soon
#     as --external-ca/--web-ca-root is in play, because validateExternalCA
#     deliberately never touches ${PKI_root_ca_cert} (see its comment). So the store held
#     FOG's root while the vhost served the admin's chain, and every HTTPS call
#     made on this server to this server still failed to verify -- the exact
#     failure this whole mechanism exists to remove.
#
# Anchoring both, deduplicated, is correct in every combination: on a FOG-issued
# install they are the same certificate and the bundle collapses to one, and on
# an external-CA install both are genuinely needed.
#
# One code path for master and node now. A node has no root of its own, so
# ${PKI_root_ca_cert} simply is not there and the chain supplies everything; the branch
# only ever existed because the master case skipped the chain entirely.
_resolveTrustAnchor() {
    trustAnchorPem=""
    local out="$(_pkiZoneDir web)/ca/.trustAnchor.pem"
    local chainroot fp seen=""
    # The chain normally lands in this directory, so it normally exists -- but
    # ${PKI_web_trust_chain} is admin-overridable and can point anywhere, and a failed
    # redirect here would look exactly like "nothing to anchor".
    mkdir -p "$(dirname "$out")" >>$error_log 2>&1
    : > "$out" 2>>$error_log || return 1

    if [[ -n ${PKI_root_ca_cert} && -f ${PKI_root_ca_cert} ]]; then
        fp=$(openssl x509 -in "${PKI_root_ca_cert}" -noout -fingerprint -sha256 2>/dev/null)
        if [[ -n $fp ]]; then
            cat "${PKI_root_ca_cert}" >> "$out" 2>>$error_log
            # Accumulates now that there are three possible sources rather than
            # two, newline-separated so no two fingerprints can abut and
            # manufacture a match that is in neither.
            seen="${fp}"$'\n'
        fi
    fi
    if [[ -n ${PKI_web_trust_chain} && -f ${PKI_web_trust_chain} ]]; then
        chainroot=$(_rootFromChain "${PKI_web_trust_chain}")
        if [[ -n $chainroot ]]; then
            fp=$(printf '%s\n' "$chainroot" \
                | openssl x509 -noout -fingerprint -sha256 2>/dev/null)
            # Fingerprint, not a path comparison: on a FOG-issued install these
            # are the same certificate reached by two different routes, and
            # comparing filenames would append it twice.
            if [[ -n $fp && $seen != *"$fp"* ]]; then
                printf '%s\n' "$chainroot" >> "$out" 2>>$error_log
                seen="${seen}${fp}"$'\n'
            fi
        fi
    fi
    # A root imported on its own, with no matching intermediate and key.
    #
    # ${PKI_web_external_root_cert} used to reach this bundle only via the chain
    # file, which is written by validateExternalCA -- and that runs only when
    # all THREE of --ca-cert/--ca-key/--ca-root were supplied. "Trust our
    # corporate root" is the narrower ask: no certificate is issued from it,
    # nothing chains to it, it is simply a root this box should accept. The
    # Certificates page writes exactly that case (GH-1121), and without this
    # the next installer run rebuilt the anchor without it and silently undid
    # the import.
    #
    # Additive and idempotent everywhere else: on a full --external-ca install
    # this certificate is already in the chain above, so the fingerprint check
    # collapses it. The -f guard is belt and braces now rather than
    # load-bearing: validateExternalCA used to persist the admin's SOURCE
    # path, routinely a temp file gone by the next run, so this silently
    # anchored nothing on most external-CA servers (GH-1683). Both import
    # routes now record the canonical copy, and the guard stays only for a
    # file an admin has since deleted by hand.
    if [[ -n ${PKI_web_external_root_cert} && -f ${PKI_web_external_root_cert} ]]; then
        local tmpd extroot
        tmpd=$(mktemp -d 2>>$error_log)
        if [[ -n $tmpd ]] && _splitPemBundle "${PKI_web_external_root_cert}" "$tmpd"; then
            for extroot in "$tmpd"/c*.pem; do
                [[ -f $extroot ]] || continue
                # Self-signed only. Anchoring an intermediate would trust it as
                # a root, widening what this box accepts -- see the same rule in
                # packages/pki/fog-pki-admin's import-root.
                [[ $(openssl x509 -in "$extroot" -noout -subject 2>/dev/null | cut -d= -f2-) \
                    == "$(openssl x509 -in "$extroot" -noout -issuer 2>/dev/null | cut -d= -f2-)" ]] || continue
                fp=$(openssl x509 -in "$extroot" -noout -fingerprint -sha256 2>/dev/null)
                [[ -n $fp && $seen != *"$fp"* ]] || continue
                cat "$extroot" >> "$out" 2>>$error_log
                seen="${seen}${fp}"$'\n'
            done
        fi
        [[ -n $tmpd ]] && rm -rf "$tmpd" >>$error_log 2>&1
    fi
    [[ -s $out ]] || return 1
    trustAnchorPem="$out"
    return 0
}
# The name this server's own certificate says it is.
#
# Every HTTPS call the installer makes to itself has to address the server by a
# name the SERVED leaf actually covers. Anchoring correctly is not enough: a
# chain that verifies against the right root is still rejected when the address
# dialled is not in the certificate, and that is one of the two halves
# _resolveSelfCacert() below depends on.
#
# So ask the certificate rather than guessing. One rule covers both cases a FOG
# server can be in: a FOG-issued leaf carries CN=${NET_hostname} (_createWebLeaf sets
# exactly that), and a leaf from a public CA carries whatever the admin had
# issued. Neither needs a new setting to describe it.
#
# The VHOST is consulted first, and that ordering is the point. On an install
# whose certificate is managed outside FOG -- acme.sh, certbot, a corporate
# issuance process -- ${PKI_web_vhost_cert} still names FOG's own leaf while the web
# server serves somebody else's, so reading FOG's copy answers confidently with
# a name that is not being presented to anybody.
#
# Falling back to ${NET_hostname} converges rather than guesses: _createWebLeaf sets
# the leaf's CN FROM ${NET_hostname}, so on a fresh install -- where configureHttpd
# has not written a leaf yet -- ${NET_hostname} is the same answer one step early.
# ${NET_fog_server_ip} is last and is only ever right for a FOG-issued certificate, since
# no public CA will issue for an address at all.
# The certificate file the browser is actually served, or empty.
#
# Split out of _servedCertName() so that anything else asking a question about
# the SERVED certificate -- rather than about its commonName -- resolves the
# same file by the same precedence, instead of re-listing the candidates and
# drifting out of step with it.
#
# First existing candidate wins outright. The loop this replaced went on to the
# next candidate when a file's commonName would not parse, which could report a
# name off a certificate the browser is not being served -- worse than reporting
# none, because a URL printed from it looks authoritative and warns anyway.
_servedCertPath() {
    local cert candidate
    local -a candidates=()
    candidate=$(_vhostCertPath)
    [[ -n $candidate ]] && candidates+=("$candidate")
    candidates+=("${PKI_web_vhost_cert}" "$sslfullchain")
    for cert in "${candidates[@]}"; do
        [[ -n $cert && -f $cert ]] && { echo "$cert"; return 0; }
    done
    return 1
}
_servedCertName() {
    local cert cn
    cert=$(_servedCertPath)
    if [[ -n $cert ]]; then
        # -nameopt multiline rather than parsing the one-line form: the compact
        # subject is rendered differently across openssl versions (leading
        # slash, spaces around '='), and a CN containing a comma cannot be
        # split out of it reliably at all.
        cn=$(openssl x509 -noout -subject -nameopt multiline -in "$cert" 2>/dev/null \
            | awk -F' = ' '/commonName/{print $2; exit}')
        [[ -n $cn ]] && { echo "$cn"; return 0; }
    fi
    [[ -n ${NET_hostname} ]] && { echo "${NET_hostname}"; return 0; }
    echo "${NET_fog_server_ip}"
}
# The management-portal URLs to print when the install finishes.
#
# This used to be one line naming ${NET_fog_server_ip}, which on an HTTPS
# install is the one address guaranteed to make the browser complain: the
# certificate is issued for a NAME (see _certLeafName), so reaching the portal
# by address is a name mismatch every time. The admin's first contact with their
# new server was a security warning, with nothing on screen to say the name that
# would have worked.
#
# So print both, name first by default. The address still has to be here -- DNS
# may not have caught up yet, and it is the only thing that works on a server
# with no usable name at all -- but as the fallback it is, not as the headline.
#
# ${WEB_url_primary} overrides which one leads, because "the name is the better
# headline" is true of the general case and not of every network. On a server
# whose address is what every machine already uses, and whose name resolves for
# a subset of them, name-first buries the URL that actually works. It is a
# .fogsettings key with no flag and no prompt on purpose: an admin who wants it
# is editing that file anyway, and it changes nothing but two lines of output.
#
# What it does NOT do is change what the explanation says. Ordering is a
# preference; whether the address is a name mismatch is a fact about the
# certificate, read below from the certificate. Setting WEB_url_primary=address
# on a names-only leaf therefore puts the address first AND says plainly that it
# will warn -- rather than silently recommending a URL that does not work
# cleanly.
#
# The name comes from _servedCertName, i.e. the commonName of the certificate
# actually on disk, rather than from ${NET_hostname}. Those differ on exactly
# the installs where it matters: an externally-issued or publicly-trusted leaf
# carries only the names its issuer was asked for, and --public-web-cert is a
# supported path. Printing a name the certificate does not carry would send the
# admin to a URL that warns just as loudly as the address, while implying it
# would not.
_managementUrls() {
    local name="" ip="${NET_fog_server_ip}" cert="" ipcovered=no
    name=$(_servedCertName)
    # _servedCertName's own last resort IS the address, so it can hand back the
    # very thing the fallback line prints. Suppress the name line rather than
    # printing the same URL twice with different captions.
    [[ -z $name || $name == "$ip" || $(validip "$name") -eq 0 ]] && name=""

    # Does the served certificate cover the ADDRESS as well as the name? A
    # FOG-issued leaf does, by construction -- configureHttpd writes an IP SAN
    # for every address in ${PKI_san_ip_addresses} -- so on the ordinary install
    # reaching the portal by address is not a name mismatch at all, and the text
    # below must not claim it is. That claim was unconditional until GH-1488 and
    # was wrong on every default install: what the browser objects to there is
    # FOG's CA being untrusted, which both URLs get equally.
    #
    # A publicly-issued or ACME leaf carries names and no address, since no
    # public CA will issue for an address. That is the install the mismatch
    # warning was written for and still the one it is right for.
    cert=$(_servedCertPath)
    [[ ${WEB_url_proto} == https && -n $cert ]] \
        && _certServesAddress "$cert" "$ip" && ipcovered=yes

    echo "   This can be done by opening a web browser and going to:"
    echo
    if [[ -n $name && ${WEB_url_primary} == address ]]; then
        echo "   ${WEB_url_proto}://${ip}${WEB_root}management"
        echo "   ${WEB_url_proto}://${name}${WEB_root}management"
    else
        [[ -n $name ]] && echo "   ${WEB_url_proto}://${name}${WEB_root}management"
        echo "   ${WEB_url_proto}://${ip}${WEB_root}management"
    fi
    # The blank line belongs to the explanation, so it is emitted per branch
    # rather than up front -- an HTTP install with no name has nothing to
    # explain, and an unconditional echo there just orphans a blank line.
    if [[ ${WEB_url_proto} == https ]]; then
        echo
        if [[ -z $name ]]; then
            echo "   This server has no name in its certificate, only the address, so"
            echo "   the browser will warn about the certificate every time. Give it a"
            echo "   resolvable name with --hostname and re-run to fix that."
        elif [[ $ipcovered == yes ]]; then
            # Deliberately "neither is a name mismatch" and not "will not warn":
            # a FOG-issued leaf chains to FOG's own CA, which no browser trusts
            # until someone imports it, and that warning lands on both URLs.
            echo "   Either works -- the certificate covers the address as well as the"
            echo "   name, so neither is a name mismatch. The address needs no DNS; the"
            echo "   name it is issued for is ${name}."
        elif [[ ${WEB_url_primary} == address ]]; then
            # WEB_url_primary moved the address to the top and this certificate
            # does not cover it. Say so rather than leaving a recommendation
            # that warns: the setting orders the URLs, it does not make one work.
            echo "   The address is first because WEB_url_primary=address in"
            echo "   .fogsettings, but this certificate carries names only -- so reaching"
            echo "   the server by address is a name mismatch and the browser will say"
            echo "   so. The name it is issued for is ${name}."
        else
            echo "   Use the first one. It is the name this server's certificate is"
            echo "   issued for, so it is the only one that will not warn. The address"
            echo "   works too -- useful before DNS catches up -- but the browser will"
            echo "   object that the certificate names ${name} instead."
        fi
    elif [[ -n $name ]]; then
        echo
        echo "   Either works. The address needs no DNS; the name is ${name}."
    fi
}
# The DNS names a certificate carries as subjectAltName entries.
#
# Echoes one per line, nothing at all when the certificate has no
# subjectAltName extension -- and that difference is load-bearing, because it is
# what decides whether the commonName counts as a host name (see
# _certServesName below).
#
# -text and awk rather than `openssl x509 -ext subjectAltName`: -ext arrived in
# OpenSSL 1.1.1 and this has to work wherever the installer runs. The
# continuation match is not decoration either -- openssl wraps a long SAN list
# across lines, and reading only the first would silently drop names.
_certDnsNames() {
    local cert="$1"
    [[ -n $cert && -f $cert ]] || return 0
    openssl x509 -noout -text -in "$cert" 2>/dev/null \
        | awk '/X509v3 Subject Alternative Name/ { grab = 1; next }
               grab && /^[[:space:]]+(DNS|IP|IP Address|email|URI|DirName|othername|Registered ID):/ { print; next }
               grab { exit }' \
        | tr ',' '\n' \
        | sed -n 's/^[[:space:]]*DNS:[[:space:]]*//p'
}
# Does the certificate at $1 carry the IP address in $2 as a subjectAltName?
#
# Addresses are matched literally, never by the name rules in _certServesName:
# there is no wildcard form for an address, and no commonName fallback either --
# an address in a CN is not an iPAddress SAN and no TLS client accepts it as
# one. So the only question is whether the exact string appears in the SAN list.
#
# Same -text/awk extraction as _certDnsNames, for the same reason: `openssl
# x509 -ext subjectAltName` needs OpenSSL 1.1.1 and this has to run wherever the
# installer does. openssl renders these as "IP Address:10.0.0.1"; IPv6 comes out
# in openssl's own normalized form, so a literal comparison only holds for a
# value that came from the same place -- which ${NET_fog_server_ip} does, having
# been written into the SAN list by configureHttpd from ${PKI_san_ip_addresses}.
_certServesAddress() {
    local cert="$1" ip="$2" n
    [[ -n $cert && -f $cert && -n $ip ]] || return 1
    while IFS= read -r n; do
        [[ -n $n && $n == "$ip" ]] && return 0
    done < <(openssl x509 -noout -text -in "$cert" 2>/dev/null \
        | awk '/X509v3 Subject Alternative Name/ { grab = 1; next }
               grab && /^[[:space:]]+(DNS|IP|IP Address|email|URI|DirName|othername|Registered ID):/ { print; next }
               grab { exit }' \
        | tr ',' '\n' \
        | sed -n 's/^[[:space:]]*IP Address:[[:space:]]*//p')
    return 1
}
# Does the certificate at $1 serve the name in $2?
#
# By iPXE's rule, not OpenSSL's. Per docs/adr/0016, iPXE's x509_check_name()
# accepts a commonName as a host name ONLY when the certificate carries no
# subjectAltName at all. Mirroring that exactly matters: a check laxer than the
# validator we are trying to satisfy is worse than no check, because it blesses
# an install that completes cleanly and then cannot boot anything.
#
# IP SANs are deliberately ignored. They cannot help a URL built from a name,
# and iPXE matches addresses and names separately anyway.
#
# Echoes "exact" or "wildcard" and returns 0 on a match; echoes nothing and
# returns 1 otherwise. The two are distinguished because whether iPXE honors a
# wildcard SAN is UNVERIFIED -- fog-ipxe is an overlay and carries no upstream
# crypto/x509.c to read -- so the caller reports a wildcard match rather than
# trusting it silently.
_certServesName() {
    local cert="$1" name="$2" sans cn n bare
    [[ -n $cert && -f $cert && -n $name ]] || return 1
    name=$(echo "$name" | tr '[:upper:]' '[:lower:]')
    sans=$(_certDnsNames "$cert")
    if [[ -z $sans ]]; then
        cn=$(openssl x509 -noout -subject -nameopt multiline -in "$cert" 2>/dev/null \
            | awk -F' = ' '/commonName/{print $2; exit}' \
            | tr '[:upper:]' '[:lower:]')
        [[ -n $cn && $cn == "$name" ]] && { echo exact; return 0; }
        return 1
    fi
    # Exact matches first, so one anywhere in the list beats a wildcard that
    # also happens to cover the name.
    while IFS= read -r n; do
        n=$(echo "$n" | tr '[:upper:]' '[:lower:]')
        [[ -n $n && $n == "$name" ]] && { echo exact; return 0; }
    done <<< "$sans"
    while IFS= read -r n; do
        n=$(echo "$n" | tr '[:upper:]' '[:lower:]')
        [[ $n == '*.'* ]] || continue
        # One label, and a real one. Comparing what is left after the first dot
        # rather than glob-matching '*.example.org' is what keeps this from
        # accepting deep.sub.example.org, which a glob would.
        bare="${n#\*.}"
        [[ $name == *.* && ${name#*.} == "$bare" ]] && { echo wildcard; return 0; }
    done <<< "$sans"
    return 1
}
# Does the SERVED certificate vouch for $1, whether it is a name or an address?
#
# The two are separate questions to a TLS client and so they are separate
# functions here -- an address is matched against iPAddress SANs only and a
# name against dNSName (or a lone commonName), with no crossover in either
# direction. This is the one place that does not care which kind it was handed,
# because its caller is asking about a host an admin chose rather than about a
# name it derived.
_servedCertServes() {
    local host="$1" cert
    [[ -n $host ]] || return 1
    cert=$(_vhostCertPath)
    [[ -n $cert && -f $cert ]] || cert="${PKI_web_vhost_cert}"
    [[ -n $cert && -f $cert ]] || cert="$sslfullchain"
    [[ -n $cert && -f $cert ]] || return 1
    if [[ $(validip "$host") -eq 0 ]]; then
        _certServesAddress "$cert" "$host"
        return $?
    fi
    _certServesName "$cert" "$host" >/dev/null
}
# The single name HTTPS netboot addresses this server by. Sets $netboothost.
#
# Not local, on purpose. A boot is two hops with two host sources: default.ipxe
# names the server for the fetch of boot.php, and IpxeBootMenu builds every URL
# after it from the FOG_WEB_HOST row. Those two used to be ${NET_hostname} and a DB
# setting with nothing comparing them, which is the defect this fixes -- so
# configureDefaultiPXEfile and recordNetbootWebHost read one variable and cannot
# disagree.
#
# Idempotent and silent on a second call, because the recorder asks after
# configureDefaultiPXEfile already has.
#
# Why the certificate and not ${NET_hostname}: ${NET_hostname} is a short label on plenty of
# servers, and validhostname() accepts one. That is harmless on a FOG-issued
# leaf -- _defaultServerNames() puts the short form in the SAN list -- and
# cannot work on a publicly-issued one, which carries only the names its issuer
# was asked for. publicWebCert is one of exactly two triggers for HTTPS netboot,
# so the short-name case is not an edge case here; it is half the population.
# The FOG_WEB_HOST row as stored, or empty when it is unset, NULL, or cannot be
# read (no schema yet on a first install, a query that errors). Empty is the
# fail-safe answer: every caller falls through to the certificate name on it.
_currentWebHostRow() {
    local value
    value=$(mysql $sqloptionsuser --password="${DB_password}" -N -B \
        --execute="SELECT settingValue FROM globalSettings WHERE settingKey='FOG_WEB_HOST'" \
        ${DB_name} 2>>$error_log)
    value="${value#"${value%%[![:space:]]*}"}"
    value="${value%"${value##*[![:space:]]}"}"
    [[ $value == NULL ]] && value=""
    echo "$value"
}
_resolveNetbootHost() {
    local cert match="" reason="" currentwebhost=""
    [[ -n $netboothost ]] && return 0
    # An existing FOG_WEB_HOST the served certificate already carries is THE
    # netboot host, name or address, and the certificate's own name is only
    # the fallback. The row is the server's canonical address -- an admin who
    # set it to the IP did so on purpose, usually because the PXE segment has
    # no DNS for the name -- and recordNetbootWebHost keeps such a value. If
    # this resolver did not honor the same value, default.ipxe would chain to
    # the certificate name while every URL after boot.php used the row, which
    # is exactly the two-source split ADR 0018 exists to remove. On a storage
    # node the row belongs to the master and the node's certificate will not
    # carry it, so the check falls through to the node's own name.
    currentwebhost=$(_currentWebHostRow)
    if [[ -n $currentwebhost ]] && _servedCertServes "$currentwebhost"; then
        netboothost="$currentwebhost"
        return 0
    fi
    netboothost=$(_servedCertName)
    cert=$(_vhostCertPath)
    [[ -n $cert && -f $cert ]] || cert="${PKI_web_vhost_cert}"
    [[ -n $cert && -f $cert ]] || cert="$sslfullchain"
    [[ -n $cert && -f $cert ]] || cert=""
    # validip echoes 0 for a valid IPv4 literal, 1 otherwise.
    #
    # An address is allowed, but only when the certificate carries it as an
    # iPAddress SAN. It used to be refused outright, on the belief that HTTPS
    # netboot needs a NAME -- that is not what iPXE does. x509_check_name()
    # walks the subjectAltName list and dispatches X509_GENERAL_NAME_IP to
    # x509_check_ipaddress(), which parses the requested host with sock_aton()
    # and compares the binary address against the SAN. An IP literal verifies
    # exactly as a name does, provided the SAN is there; what it cannot do is
    # match a commonName, which is why _certServesAddress() has no CN
    # fallback.
    #
    # The blanket refusal cost more than a missing feature. FOG_WEB_HOST is
    # this row's other job -- the canonical address of the server, read by
    # ClientManagement, the services and every browser-facing absolute URL a
    # plugin builds -- so refusing an address here forced an install that is
    # addressed by IP onto a name for everything else too.
    if [[ -z $netboothost ]]; then
        reason="address"
    elif [[ $(validip "$netboothost") -eq 0 ]]; then
        if [[ -z $cert ]] || ! _certServesAddress "$cert" "$netboothost"; then
            reason="addressnotincert"
        fi
    elif [[ -n $cert ]] && ! match=$(_certServesName "$cert" "$netboothost"); then
        reason="mismatch"
    fi
    if [[ -n $reason ]]; then
        echo "Failed"
        echo
        echo " ##################################################################"
        echo " # HTTPS netboot has to address this server by a name its          #"
        echo " # certificate carries. iPXE has no --insecure and fails the       #"
        echo " # handshake on a name mismatch, so every PXE client would stop    #"
        echo " # before it fetched anything.                                    #"
        echo " ##################################################################"
        echo
        if [[ $reason == address ]]; then
            echo "   This server has no name to use, only the address ${netboothost:-${NET_fog_server_ip}}."
            echo
            echo "   Set a resolvable name with --hostname, or put netboot back on"
            echo "   HTTP with --netboot-proto http."
        elif [[ $reason == addressnotincert ]]; then
            echo "   Resolved address: $netboothost"
            echo "   Certificate:     $cert"
            echo "   It carries:      $(_certDnsNames "$cert" | tr '\n' ' ')"
            echo
            echo "   An address has to appear as an iPAddress SAN -- iPXE compares"
            echo "   the binary address and will not read it out of a commonName or"
            echo "   a DNS entry. Re-issue the certificate with $netboothost in its"
            echo "   SAN list, address this server by a name instead, or put netboot"
            echo "   back on HTTP with --netboot-proto http."
        else
            echo "   Resolved name: $netboothost"
            echo "   Certificate:   $cert"
            echo "   It carries:    $(_certDnsNames "$cert" | tr '\n' ' ')"
            echo
            echo "   Re-issue the certificate for $netboothost, or put netboot back"
            echo "   on HTTP with --netboot-proto http."
            echo
            # Deliberately NOT offered above: --extra-server-name. It only feeds
            # FOG's own SAN list, and _createWebLeaf() returns early on an
            # externally-managed or publicly-trusted leaf, so it cannot change one issued
            # outside FOG -- which is the install this branch mostly fires on.
            echo "   Note: --extra-server-name cannot help here. It only affects a"
            echo "   leaf FOG issues, and this certificate was issued elsewhere."
        fi
        echo
        # Fatal on purpose, and before anything is written: an install that
        # completes having laid down an unbootable default.ipxe is worse than one
        # that stops and says why.
        [[ -z $exitFail ]] && exit 1
        return 1
    fi
    if [[ $match == wildcard ]]; then
        echo
        echo "   Note: $netboothost matches only a WILDCARD name in the served"
        echo "   certificate. Whether iPXE honors a wildcard SAN is unverified,"
        echo "   so if netboot stops at the TLS handshake, re-issue with"
        echo "   $netboothost as an explicit name."
        echo
    fi
    return 0
}
# The --cacert arguments for an HTTPS call this server makes to ITSELF.
#
# Sets $selfCacertOpts, which callers splice in as "${selfCacertOpts[@]}". Empty
# when there is nothing to anchor, or when the install is serving plain HTTP, in
# which case curl verifies against the system store and the call is unchanged.
#
# Why a helper and not just the system store: _installCATrustAnchor() writes the
# anchor there, but it runs at installfog.sh:1175 -- AFTER configureHttpd,
# backupDB and updateDB, which are the calls that need it. On a fresh install
# the store therefore does not know FOG's CA yet at the moment those three fire.
# The anchor FILE exists by then (configureHttpd has just written the chain), so
# naming it explicitly is correct whatever the store happens to contain, and
# stays correct on an --external-ca install where the served chain terminates in
# the admin's root rather than FOG's.
#
# --cacert REPLACES curl's default bundle rather than adding to it, and these
# calls address the server by ${NET_fog_server_ip}, so both halves have to hold: the
# served chain must terminate in the anchor this resolves, and the leaf must
# cover that address. FOG-issued certificates satisfy both by construction. An
# admin who hand-replaced the certificate without telling the installer has
# satisfied neither, and updateDB says so rather than failing blankly.
#
# These calls used to pass -k. That mattered more than it looks: the schema
# update carries X-Fog-Install-Token, a secret that grants a schema deploy on a
# server with no users yet, and it was being handed to whoever answered.
_resolveSelfCacert() {
    selfCacertOpts=()
    [[ ${WEB_url_proto} == https ]] || return 0
    # A leaf issued outside FOG chains to a PUBLIC root the system store
    # already holds. --cacert REPLACES that store rather than adding to it, so
    # naming FOG's own root here does not add trust -- it removes the only
    # trust that would have worked, and turns a verifiable ACME certificate
    # into an unverifiable one. Leave curl's default bundle in place.
    { _externallyManagedLeaf || [[ ${PKI_web_cert_publicly_trusted} == yes ]]; } && return 0
    _resolveTrustAnchor >>$error_log 2>&1 || return 0
    [[ -s $trustAnchorPem ]] || return 0
    selfCacertOpts=(--cacert "$trustAnchorPem")
}
# Anchor this server's own CA in this server's own system trust store.
#
# FOG's PKI already reaches every consumer it can address directly: fog-client
# pins ca.cert.der, iPXE gets the CA compiled into the binary at build time,
# Secure Boot enrolls a MOK. The host's own TLS clients were the gap. curl,
# wget, PHP's stream wrapper and the node-to-master status calls all read the
# system store, and nothing had ever told that store about the CA this
# installer mints -- so on the FOG server itself every HTTPS call to the FOG
# server itself failed to verify, and the ones inside FOG that have no way to
# pass a --cacert had no route to working verification at all.
#
# Explicitly NOT a fix for the admin's browser, which is the thing people
# actually notice. Firefox carries its own NSS store and Chrome reads a
# per-user one, so neither consults what this writes -- and the browser is
# usually on a different machine entirely. That import stays manual.
_installCATrustAnchor() {
    local anchor st=0
    # Unconditional since GH-1120, which retired $catrust and --no-ca-trust.
    # Declining this left the server unable to verify its own certificate --
    # every internal caller that cannot pass --cacert included -- and the
    # failures surfaced far from the flag that caused them.
    _resolveTrustAnchor || return 0
    anchor="$trustAnchorPem"

    dots "Trusting the FOG CA on this server"
    if ! _caTrustLayout; then
        echo "Skipped"
        echo " * No system trust store was found on this host, so nothing was"
        echo "   changed. HTTPS calls made ON this server to this server will"
        echo "   still need the CA passed to them explicitly."
        return 0
    fi
    # Re-encoded through openssl rather than copied, for two reasons. The file
    # has to be PEM under a .crt name whatever the source was -- Debian's
    # update-ca-certificates reads *.crt and nothing else -- and parsing it
    # here rejects a malformed anchor at the point it can be attributed,
    # instead of at refresh time where the store blames some other certificate.
    #
    # Per certificate, not `openssl x509 -in "$anchor"` over the whole file:
    # that reads only the FIRST certificate of a bundle and discards the rest
    # silently. $trustAnchorPem carries two certificates on an external-CA
    # install, and the single-shot form would have written FOG's root and
    # dropped the admin's -- reintroducing the bug _resolveTrustAnchor was just
    # changed to fix, with nothing to show for it in any log.
    #
    # Staged through a temp file and moved into place only once openssl is
    # happy. Whether a failed `openssl x509 -out` leaves an empty file behind
    # varies by version, and a zero-byte .crt sitting in the anchor directory
    # would make every later trust-store refresh on this box complain about a
    # certificate that was never valid. mv within the same directory is atomic,
    # so a re-run also cannot be caught halfway.
    #
    # A fixed destination filename, so --recreate-ca overwrites the previous
    # anchor rather than leaving the retired CA trusted alongside its
    # replacement. That is the whole reason this is not named after the CA's
    # fingerprint or its date.
    local staged="${caTrustDir}/.fog-server-ca.crt.$$"
    local tmpd f
    tmpd=$(mktemp -d) || return 0
    : > "$staged" 2>>$error_log || st=1
    if [[ $st -eq 0 ]] && _splitPemBundle "$anchor" "$tmpd"; then
        for f in "$tmpd"/c*.pem; do
            [[ -f $f ]] || continue
            openssl x509 -in "$f" >> "$staged" 2>>$error_log || st=1
        done
    else
        st=1
    fi
    rm -rf "$tmpd" >>$error_log 2>&1
    if [[ $st -eq 0 && -s $staged ]]; then
        chmod 0644 "$staged" >>$error_log 2>&1
        mv -f "$staged" "${caTrustDir}/fog-server-ca.crt" >>$error_log 2>&1 || st=1
        [[ $st -eq 0 ]] && { $caTrustCmd >>$error_log 2>&1 || st=1; }
    else
        st=1
    fi
    rm -f "$staged" >>$error_log 2>&1
    if [[ $st -ne 0 ]]; then
        # Deliberately not errorStat: that aborts the install, and a server
        # whose trust store could not be updated is still a working server.
        echo "Failed"
        echo " * Could not update the system trust store at ${caTrustDir}."
        echo "   The install is otherwise fine -- HTTPS calls made on this"
        echo "   server will need the CA passed to them explicitly."
        return 0
    fi
    echo "Done"
}
# Put the PKI private keys back under root's control, and keep them there.
#
# Called AFTER configureSnapins, which is the whole point. The historic layout
# left every one of these files at 775 ${SVC_user}:$apacheuser, because ${PKI_client_cert_dir}
# sits under $snapindir and configureSnapins chowned that tree wholesale --
# meaning a PHP remote code execution read the CA private key. Setting the
# permissions inside createSSLCA instead does nothing: it runs earlier in the
# same install and configureSnapins simply overwrites the result.
#
# "Pseudo-offline", not offline. Nothing here stops root, or anyone who can
# become root, from reading these keys. It stops the web tier, which is the
# part of this server exposed to the network. Moving a key to a vault is a
# separate and better step, and $fogprogramdir/bin/fog-offline-ca-key exists
# for it.
#
# Pad a line to the fixed width of the surrounding '###' box. The prose lines
# carry their own closing '#', but the lines holding a path cannot: ${PKI_client_cert_dir}
# and $fogprogramdir are both relocatable (GH-850), so the padding has to be
# computed. An over-long path runs past the border rather than being cut --
# a path the admin has to type is worth more than a straight edge.
_pkiBoxLine() {
    printf "  #%-65s#\n" "$1"
}
_hardenPkiPermissions() {
    dots "Restricting private key access"
    local sbca="$(_pkiZoneDir secureboot)/ca/.fogSBCA.key"
    local f

    _resolveSslPath
    # 0400 root:root: the offline-able keys. Nothing on a running server needs
    # to read either -- the intermediates beneath them are already issued, and
    # both are touched again only to issue a NEW one.
    for f in "${PKI_root_ca_key}" "$sbca"; do
        [[ -z $f || ! -f $f ]] && continue
        chown root:root "$f" >>$error_log 2>&1
        chmod 0400 "$f" >>$error_log 2>&1
    done
    # 0600 root:root: online, but only ever used by root-run code -- the
    # installer, and the node-signing helper invoked through sudo.
    if [[ -n ${PKI_web_ca_key} && -f ${PKI_web_ca_key} ]]; then
        chown root:root "${PKI_web_ca_key}" >>$error_log 2>&1
        chmod 0600 "${PKI_web_ca_key}" >>$error_log 2>&1
    fi
    # The web leaf's key, unless the admin manages it themselves. An ACME
    # renewal writes ${PKI_web_vhost_key} as whatever user its hook runs as, so locking
    # it to root would break the next renewal rather than this run.
    #
    # Deliberately narrower than it looks: this exempts THIS key only. The Web
    # CA key above is FOG's whatever the leaf's provenance, and an earlier
    # version of this loop skipped both, leaving a CA key at 775 on exactly the
    # servers whose admins had thought hardest about certificates.
    # PKI_web_cert_publicly_trusted joins the externally-managed test here for
    # the same reason it does in _createWebLeaf: a publicly-issued leaf came
    # from outside FOG too, and its renewal writes this key as whatever user
    # that process runs as.
    if ! _externallyManagedLeaf \
        && [[ ${PKI_web_cert_publicly_trusted} != yes && -n ${PKI_web_vhost_key} && -f ${PKI_web_vhost_key} ]]; then
        chown root:root "${PKI_web_vhost_key}" >>$error_log 2>&1
        chmod 0600 "${PKI_web_vhost_key}" >>$error_log 2>&1
    fi
    # 0640 root:$apacheuser: the ONE key the web tier must read.
    # FOGBase::certDecrypt() opens it on every fog-client authorize(), so a
    # stricter mode here does not harden anything -- it stops every client on
    # the server from authenticating, with "Private key not readable" as the
    # only clue.
    # The REAL file, not the canonical name. chown/chmod follow symlinks, so
    # going through the compat link would work by accident -- but it silently
    # does nothing at all on the run before the link exists, and names the wrong
    # thing in the error log when it fails.
    #
    # This now retargets an admin's own file when --client-cert/--client-key
    # relocated the keypair, and that is deliberate rather than incidental.
    # certDecrypt() opens this key on every fog-client authorize(), so it HAS to
    # be readable by the web user; the web leaf's key is exempted from hardening
    # because an ACME client owns and rewrites it, and nothing owns this one.
    if [[ -f ${PKI_client_encrypt_key} ]]; then
        chown root:${apacheuser} "${PKI_client_encrypt_key}" >>$error_log 2>&1
        chmod 0640 "${PKI_client_encrypt_key}" >>$error_log 2>&1
    fi
    errorStat $?
    # A chown on the file cannot fix an unreadable PARENT, so a relocated key
    # under, say, /root/ still leaves every client failing to authenticate with
    # "Private key not readable" as the only clue -- per client, and naming
    # nothing. Ask the question the web tier will ask, as the user it will ask
    # it as, and say so here where somebody is watching.
    #
    # runuser then su, the same fallback _keaValidate() uses, and NOT sudo: sudo
    # needs a sudoers rule for root -> ${apacheuser} that a hardened box may not
    # have, and a warning that fires because the TEST could not run is worse
    # than no warning at all. When neither tool is present the check is skipped
    # rather than guessed.
    if [[ -f ${PKI_client_encrypt_key} ]]; then
        commReadable=""
        if command -v runuser >/dev/null 2>&1; then
            runuser -u "${apacheuser}" -- test -r "${PKI_client_encrypt_key}" \
                >>$error_log 2>&1 && commReadable=yes || commReadable=no
        elif command -v su >/dev/null 2>&1; then
            su -s /bin/sh -c "test -r '${PKI_client_encrypt_key}'" "${apacheuser}" \
                >>$error_log 2>&1 && commReadable=yes || commReadable=no
        fi
        if [[ $commReadable == no ]]; then
            echo " * The web server cannot read the client communication key:"
            echo "     ${PKI_client_encrypt_key}"
            echo "   Every fog-client will fail to authenticate until it can."
            echo "   Check that ${apacheuser} can traverse each parent directory."
        fi
        unset commReadable
    fi
    # configureSnapins now prunes ${PKI_client_cert_dir}, so its recursive relabel no longer
    # reaches here either. Re-assert it, or SELinux denies the web tier the
    # read the mode above just granted.
    #
    # After errorStat, never before: setSELinuxContext prints its own
    # dots()/OK pair, so calling it inside this function's dots window left
    # "Restricting private key access......" with nothing closing it and our
    # OK stranded on the following line.
    setSELinuxContext "${PKI_client_cert_dir}" fog_share_t
    mkdir -p "${fogprogramdir}/bin" >>$error_log 2>&1
    install -o root -g root -m 0755 ../packages/pki/fog-offline-ca-key \
        "${fogprogramdir}/bin/fog-offline-ca-key" >>$error_log 2>&1
    mkdir -p "$(_pkiRootDir)" >>$error_log 2>&1
    install -o root -g root -m 0755 ../packages/pki/renewal-helper \
        "$(_pkiRootDir)/renewal-helper" >>$error_log 2>&1
    # A storage node does not hold the fleet's root CA -- whatever CA it
    # minted (or was issued) is local to itself, so "restore it to issue a
    # certificate for a new storage node" is nonsense advice on the node
    # itself.
    case ${FOG_install_type} in
        [Ss]) ;;
        *)
            if [[ -f ${PKI_root_ca_key} || -f $sbca ]]; then
                echo
                echo "  ###################################################################"
                if [[ -f ${PKI_root_ca_key} ]]; then
                    echo "  # The CA private key for this server is on this server, readable  #"
                    echo "  # only by root:                                                   #"
                    _pkiBoxLine "   ${PKI_root_ca_key}"
                    echo "  #                                                                 #"
                    echo "  # That protects it from a compromise of the web application, but  #"
                    echo "  # not from a compromise of the machine. To move it to a vault:    #"
                    _pkiBoxLine "   ${fogprogramdir}/bin/fog-offline-ca-key /mnt/vault"
                    echo "  #                                                                 #"
                    echo "  # Day to day nothing needs it. Restore it only to issue a new     #"
                    echo "  # intermediate, or a certificate for a new storage node.          #"
                fi
                if [[ -f $sbca ]]; then
                    [[ -f ${PKI_root_ca_key} ]] && echo "  #                                                                 #"
                    echo "  # The Secure Boot CA private key is also on this server,          #"
                    echo "  # readable only by root:                                          #"
                    _pkiBoxLine "   ${sbca}"
                    echo "  #                                                                 #"
                    echo "  # Restore it to issue a new Secure Boot intermediate, or a        #"
                    echo "  # new signing leaf. To move it to a vault:                        #"
                    _pkiBoxLine "   ${fogprogramdir}/bin/fog-offline-ca-key /mnt/vault --zone secureboot"
                fi
                echo "  ###################################################################"
                echo
            fi
            ;;
    esac
}
configureUsers() {
    userexists=0
    [[ -z ${SVC_user} || "x${SVC_user}" == "xfog" ]] && SVC_user='fogproject'
    dots "Setting up ${SVC_user} user"
    getent passwd ${SVC_user} > /dev/null
    if [[ $? -eq 0 ]]; then
        if [[ ! -f "$fogprogramdir/.fogsettings" && ! -x /home/${SVC_user}/warnfogaccount.sh ]]; then
            echo "Already exists"
            echo
            echo "The account \"${SVC_user}\" already exists but this seems to be a"
            echo "fresh install. We highly recommend to NOT create this account"
            echo "as it is supposed to be a system account. It is not meant to be"
            echo "used to login and work on the server!"
            echo
            echo "Please remove the account \"${SVC_user}\" manually before running"
            echo "the installer again. Run: userdel ${SVC_user}"
            echo
            exit 1
        fi
        echo "Skipped"
    else
        # Debian's adduser and RedHat's (a symlink to useradd) both take these
        # long options, so they keep the path they always had. Two platforms
        # cannot: Arch ships no adduser whatsoever, and Alpine's is the busybox
        # applet, which has no --system at all -- so this call failed outright
        # on both. Fall through to useradd, which shadow provides everywhere
        # (it is in Alpine's package list for exactly this reason). --home-dir
        # does not create the directory the way adduser would, but the mkdir
        # below already did that unconditionally.
        if command -v adduser >/dev/null 2>&1 && adduser --help 2>&1 | grep -q -- '--system'; then
            adduser --system --shell /bin/bash --home /home/${SVC_user} ${SVC_user} >>$error_log 2>&1
        else
            useradd --system --shell /bin/bash --home-dir /home/${SVC_user} ${SVC_user} >>$error_log 2>&1
        fi
        retVal=$?
        [[ $retVal -eq 0 ]] && groupadd -f --system ${SVC_user} >>$error_log 2>&1 || errorStat $?
        retVal=$?
        [[ $retVal -eq 0 ]] && usermod -g ${SVC_user} -G ${SVC_user} ${SVC_user} >>$error_log 2>&1 || errorStat $?
        retVal=$?
        [[ $retVal -eq 0 ]] && mkdir -p /home/${SVC_user} >>$error_log 2>&1 || errorStat $?
        retVal=$?
        [[ $retVal -eq 0 ]] && touch /home/${SVC_user}/.bashrc >>$error_log 2>&1 || errorStat $?
        retVal=$?
        [[ $retVal -eq 0 ]] && chown ${SVC_user}:${SVC_user} /home/${SVC_user} >>$error_log 2>&1 || errorStat $?
        errorStat $?
    fi
    dots "Locking ${SVC_user} as a system account"
    if [[ ${FOG_os_id} -ne 3 ]]; then
        chsh -s /bin/bash ${SVC_user} >>$error_log 2>&1
    else
        sed -i -e "s|^\(${SVC_user}.*:\)[^:]*$|\1/bin/bash|g" /etc/passwd >>$error_log 2>&1
    fi
    textmessage="You seem to be using the '${SVC_user}' system account to logon and work \non your FOG Server system.\n\nIt's NOT recommended to use this account! Please create a new\naccount for administrative tasks.\n\nIf you re-run the installer it would reset the '${SVC_user}' account\npassword and therefore lock you out of the system!\n\nTake care,\nyour FOGProject team"
    grep -q "#exit 1" /home/${SVC_user}/.bashrc >/dev/null 2>&1 || cat >>/home/${SVC_user}/.bashrc <<EOF
echo -e "$textmessage"
#exit 1
EOF
    mkdir -p /home/${SVC_user}/.config/autostart/
    cat >/home/${SVC_user}/.config/autostart/warnfogaccount.desktop <<EOF
[Desktop Entry]
Type=Application
Name=Warn users to not use the ${SVC_user} account
Exec=/home/${SVC_user}/warnfogaccount.sh
Comment=Warn users who use the ${SVC_user} system account to log on
EOF
    chown -R ${SVC_user}:${SVC_user} /home/${SVC_user}/.config/
    cat >/home/${SVC_user}/warnfogaccount.sh <<EOF
#!/bin/bash
title="FOG System Account"
text="$textmessage"
z=\$(which zenity)
x=\$(which xmessage)
n=\$(which notify-send)
if [[ -x "\$z" ]]; then
    \$z --error --width=480 --text="\$text" --title="\$title"
elif [[ -x "\$x" ]]; then
    echo -e "\$text" | \$x -center -file -
else
    \$n -u critical "\$title" "\$(echo \$text | sed -e 's/ \\n/ /g')"
fi
EOF
    chmod 755 /home/${SVC_user}/warnfogaccount.sh
    chown ${SVC_user}:${SVC_user} /home/${SVC_user}/warnfogaccount.sh
    errorStat $?
    dots "Setting up ${SVC_user} password"
    if [[ -z ${SVC_password} ]]; then
        # if we don't have a password from .fogsettings we check config.class.php as well
        #
        # Reads the tree ALREADY INSTALLED, which on an upgrade is whatever the
        # previous release laid down -- so both locations have to be tried, the
        # same way utils.sh does for System.php. Config is generated into
        # commons/ now, beside fogpaths.php, the installer's other generated
        # runtime file; it used to go to lib/fog/, a directory that existed for
        # nothing else once core moved to src/.
        configsrc=${webdirdest}commons/config.class.php
        [[ ! -r $configsrc ]] && configsrc=${webdirdest}lib/fog/config.class.php
        if [[ -r $configsrc ]]; then
            # extract password from old style config
            SVC_password=$(awk -F '"' -e '/TFTP_FTP_PASSWORD/,/);/{print $2}' $configsrc | grep -v "^$")
            # if that didn't get us the password we try again new style
            [[ -z ${SVC_password} ]] && SVC_password=$(awk -F "'" -e '/TFTP_FTP_PASSWORD/{print $4}' $configsrc)
        fi
        unset configsrc
    fi
    checkPasswordChars "${SVC_password}"
    cnt=0
    ret=999
    while [[ $ret -ne 0 && $cnt -lt 10  ]]; do
        [[ -z ${SVC_password} || $ret -ne 999 ]] && SVC_password=$(generatePassword 20)
        echo -e "${SVC_password}\n${SVC_password}" | passwd ${SVC_user} >>$error_log 2>&1
        ret=$?
        let cnt+=1
    done
    errorStat $ret
    unset cnt
    unset ret
}
installUtilities() {
    dots "Installing FOG utilities"
    # GH-314: the utility scripts only ever existed inside the git checkout you
    # happened to install from, so there was no stable path to call them by --
    # FOGBackup in particular. Delete that checkout, move it, or clone a second
    # one and any cron or runbook referencing it breaks. reporting/report.sh
    # already got copied to $fogprogramdir; this does the same for the rest.
    #
    # lib/ comes along because the utils source lib/common/utils.sh, which
    # sources lib/common/functions.sh. Keeping both trees in their original
    # relative positions is what makes that sourcing keep working -- see the
    # BASH_SOURCE resolution at the top of those scripts.
    #
    # Removed first rather than copied over: these are installer-owned trees, so
    # a file dropped from a later release should not survive as a stale copy.
    local st=0
    rm -rf "$fogprogramdir/utils" "$fogprogramdir/lib" >>$error_log 2>&1
    mkdir -p "$fogprogramdir/utils" "$fogprogramdir/lib" >>$error_log 2>&1 || st=1
    cp -a "$workingdir/../utils/." "$fogprogramdir/utils/" >>$error_log 2>&1 || st=1
    cp -a "$workingdir/../lib/." "$fogprogramdir/lib/" >>$error_log 2>&1 || st=1
    find "$fogprogramdir/utils" "$fogprogramdir/lib" -type f -name '*.sh' \
        -exec chmod 755 {} \; >>$error_log 2>&1 || st=1
    errorStat $st
}
linkOptFogDir() {
    # GH-850: guard on `! -e` rather than the old `! -h`, and go through
    # linkIfAbsent. `! -h` is false for a *dangling* symlink, so a stale
    # /var/log/fog was never repaired; and where /var/log/fog already existed as
    # a real directory the bare `ln -s` did not fail, it created
    # /var/log/fog/log inside it. `! -e` is true for a dangling link (it follows
    # the link) and false for a real directory, which is what we want in both.
    # A symlink pointing at the WRONG place is repaired here, which the guard
    # below cannot do: `! -e` is false for a link that resolves, and
    # linkIfAbsent() deliberately leaves a resolving link alone. So an install
    # that later moved $servicelogs kept /var/log/fog aimed at the old
    # directory forever -- and the log viewer reads through that link
    # (FOGLogPaths::FOG_LINK), so it went on showing the previous location's
    # files with no indication anything was stale.
    #
    # Only ever replaces a symlink. A real directory is still left alone, for
    # the GH-850 reason below.
    if [[ -L /var/log/fog && "$(readlink /var/log/fog)" != "${servicelogs%/}" ]]; then
        dots "Repointing FOG log link at $servicelogs"
        rm -f /var/log/fog >>$error_log 2>&1
        ln -s "${servicelogs%/}" /var/log/fog >>$error_log 2>&1
        errorStat $?
    fi
    if [[ ! -e /var/log/fog ]]; then
        dots "Linking FOG Logs to Linux Logs"
        linkIfAbsent "$servicelogs" /var/log/fog
        errorStat $?
    fi
    # GH-850: /etc/fog is a real directory now, so it can hold fog.conf -- the
    # pointer that tells the next installer run where this install lives. It
    # used to be a symlink to $servicedst/etc, which would have put that pointer
    # inside the very tree it exists to locate.
    #
    # Converting it is functionally inert: nothing in FOG reads through
    # /etc/fog. All eight daemons resolve the service config relative to their
    # own location (`dirname(realpath(__FILE__)).'/../etc/config.php'`), never
    # via /etc. The symlink was only ever an admin convenience, so config.php is
    # re-linked inside the new directory to keep the path admins know working.
    if [[ -L /etc/fog ]]; then
        dots "Converting /etc/fog to a real directory"
        rm -f /etc/fog >>$error_log 2>&1
        mkdir -p /etc/fog >>$error_log 2>&1
        errorStat $?
    fi
    [[ ! -d /etc/fog ]] && mkdir -p /etc/fog >>$error_log 2>&1
    dots "Linking FOG Service config /etc"
    linkIfAbsent "$servicedst/etc/config.php" /etc/fog/config.php
    errorStat $?
    dots "Recording FOG base path"
    # Sourced by the installer before lib/common/config.sh on the next run, so a
    # non-default base path survives an upgrade without having to be re-supplied
    # on the command line. Deliberately holds fogprogramdir and nothing else --
    # every other setting stays in $fogprogramdir/.fogsettings.
    {
        echo "## Written by the FOG installer -- DO NOT EDIT."
        echo "## Records where this FOG install lives so the installer can find"
        echo "## it again. To move it, re-run the installer with --fogprogramdir."
        echo "fogprogramdir='${fogprogramdir%/}'"
    } > /etc/fog/fog.conf 2>>$error_log
    errorStat $?
    local element=${WEB_server_engine}
    chmod -R 755 /var/log/$element >>$error_log 2>&1
    for i in $(find /var/log/ -type d -name 'php*fpm*' 2>>$error_log); do
        chmod -R 755 $i >>$error_log 2>&1
    done
    for i in $(find /var/log/ -type f -name 'php*fpm*' 2>>$error_log); do
        chmod -R 755 $i >>$error_log 2>&1
    done
}
configureStorage() {
    dots "Setting up storage"
    [[ ! -d ${STORAGE_image_share_path} ]] && mkdir ${STORAGE_image_share_path} >>$error_log 2>&1
    [[ ! -f ${STORAGE_image_share_path}/.mntcheck ]] && touch ${STORAGE_image_share_path}/.mntcheck >>$error_log 2>&1
    [[ ! -d ${STORAGE_image_share_path}/postdownloadscripts ]] && mkdir ${STORAGE_image_share_path}/postdownloadscripts >>$error_log 2>&1
    if [[ ! -f ${STORAGE_image_share_path}/postdownloadscripts/fog.postdownload ]]; then
        echo "#!/bin/bash" >"${STORAGE_image_share_path}/postdownloadscripts/fog.postdownload"
        echo "## This file serves as a starting point to call your custom postimaging scripts." >>"${STORAGE_image_share_path}/postdownloadscripts/fog.postdownload"
        echo "## <SCRIPTNAME> should be changed to the script you're planning to use." >>"${STORAGE_image_share_path}/postdownloadscripts/fog.postdownload"
        echo "## Syntax of post download scripts are" >>"${STORAGE_image_share_path}/postdownloadscripts/fog.postdownload"
        echo "#. \${postdownpath}<SCRIPTNAME>" >> "${STORAGE_image_share_path}/postdownloadscripts/fog.postdownload"
    fi
    [[ ! -d $storageLocationCapture ]] && mkdir $storageLocationCapture >>$error_log 2>&1
    [[ ! -f $storageLocationCapture/.mntcheck ]] && touch $storageLocationCapture/.mntcheck >>$error_log 2>&1
    [[ ! -d $storageLocationCapture/postinitscripts ]] && mkdir $storageLocationCapture/postinitscripts >>$error_log 2>&1
    if [[ ! -f $storageLocationCapture/postinitscripts/fog.postinit ]]; then
        echo "#!/bin/bash" >"$storageLocationCapture/postinitscripts/fog.postinit"
        echo "## This file serves as a starting point to call your custom pre-imaging/post init loading scripts." >>"$storageLocationCapture/postinitscripts/fog.postinit"
        echo "## <SCRIPTNAME> should be changed to the script you're planning to use." >>"$storageLocationCapture/postinitscripts/fog.postinit"
        echo "## Syntax of post init scripts are" >>"$storageLocationCapture/postinitscripts/fog.postinit"
        echo "#. \${postinitpath}<SCRIPTNAME>" >>"$storageLocationCapture/postinitscripts/fog.postinit"
    else
        (head -1 "$storageLocationCapture/postinitscripts/fog.postinit" | grep -q '^#!/bin/bash') || sed -i '1i#!/bin/bash' "$storageLocationCapture/postinitscripts/fog.postinit" >/dev/null 2>&1
    fi
    chmod -R 775 ${STORAGE_image_share_path} $storageLocationCapture >>$error_log 2>&1
    chown -R $(id -u ${SVC_user}):$(id -g ${SVC_user}) ${STORAGE_image_share_path} $storageLocationCapture >>$error_log 2>&1
    errorStat $?
    # GH-964: on the lab this directory was unlabeled_t, which is worse than
    # merely wrong -- httpd_t gets getattr/open/search on it and nothing else,
    # so the web UI could not list an image directory, and unlabeled_t masks
    # whatever the real denial would have been.
    #
    # fog_share_t, FOG's own type, rather than a stock one. Two confined
    # daemons need this directory read-write: the web tier (httpd_t) and
    # vsftpd (ftpd_t), the latter as the receiving side of a replication run.
    # No stock type gives both without a blanket grant --
    # httpd_sys_rw_content_t needs ftpd_full_access (every file type on the
    # box) and public_content_rw_t needs the global *_anon_write booleans.
    # See packages/selinux/fog.te for the full reasoning.
    #
    # NFS is deliberately NOT a consideration, and needs no rule: the data
    # path is in-kernel as kernel_t, which has unconditional read+write on the
    # `file_type` attribute that fog_share_t carries. The lab's audit log
    # agrees -- zero nfsd_t denials across months of imaging.
    #
    # $storageLocationCapture is labeled separately because .fogsettings can
    # relocate it out from under ${STORAGE_image_share_path}, in which case the recursive
    # fcontext registered for ${STORAGE_image_share_path} would not cover it.
    setSELinuxContext "${STORAGE_image_share_path}" fog_share_t
    setSELinuxContext "$storageLocationCapture" fog_share_t
}
clearScreen() {
    clear
}
# One boolean encoding for .fogsettings: yes / no.
#
# Before this there were three, and the flag layer mixed them inside a single
# variable -- sDHCP_enabled was assigned "Y" and then 1 on the very next line,
# and sBOOT_external_tftp_server was assigned the string "true", which nothing
# tested for. The keys divided up as yes/no (three keys), 1/0 (seven) and Y/N
# (one), so which literal a test had to use was a per-key fact nobody could
# carry, and getting it wrong is silent: `[[ $x == 0 ]]` against "N" is simply
# false, and `[[ "N" -eq 1 ]]` evaluates "N" as an arithmetic expression --
# an unset variable named N, so zero -- rather than erroring. That pair is
# exactly how DHCP_enabled="N" satisfied neither the enabled test nor the
# disabled one (GH-1120 follow-up).
#
# Anything a human might reasonably have typed maps in, so hand-edited files
# keep working; anything else is left ALONE rather than guessed at, because
# silently turning a typo into "no" is how a deliberate setting disappears.
# Empty stays empty -- callers distinguish "unset" from "no" (see the
# `[[ -z ${DHCP_enabled} ]]` prompt loops), and collapsing that here would make
# every prompt stop firing.
_normalizeBool() {
    case "${1,,}" in
        yes|y|1|true|on|enabled)   echo "yes" ;;
        no|n|0|false|off|disabled) echo "no" ;;
        *)                         echo "$1" ;;
    esac
}
# The keys _normalizeBool applies to, and the only ones it may.
#
# FOG_installed is deliberately absent: settingLine() writes it unquoted and
# numeric to preserve the historical file format, bin/updatefog.sh reads it, and
# it records install STATE rather than a preference. SVC_firewall_control
# (configure/disable/skip) and FOG_install_type (N/S) are absent because they
# are not booleans at all -- folding either to yes/no would destroy an answer.
_booleanSettingKeys() {
    echo BOOT_external_tftp_server BOOT_rebuild_ipxe_with_my_ca \
         BOOT_url_proto_forced DB_external DHCP_enabled FOG_copy_back_old \
         FOG_install_lang FOG_send_reports PKI_sb_enabled \
         PKI_web_cert_publicly_trusted STORAGE_rebuild_nfs_exports \
         WEB_https_redirect
}
# Normalize in place, every run.
#
# NOT a one-shot migration keyed on a version marker: .fogsettings is a file
# admins edit, so an old encoding can arrive at any time, not just on the
# upgrade that renamed things. Running every time makes this idempotent and
# self-repairing, and means writeUpdateFile() only ever sees yes/no -- so the
# migration stays a copy rather than becoming a translation.
_normalizeBooleanSettings() {
    local key
    for key in $(_booleanSettingKeys); do
        [[ -n ${!key} ]] || continue
        printf -v "$key" '%s' "$(_normalizeBool "${!key}")"
    done
}
writeUpdateFile() {
    PKI_client_cert_dir="${PKI_client_cert_dir//\/$}"
    tmpDte=$(date +%c)
    _normalizeBooleanSettings
    [[ -z ${FOG_copy_back_old} ]] && FOG_copy_back_old="no"

    # GH-632: this assumed $fogprogramdir already existed. On a pristine system
    # it does not -- nothing creates it until `mkdir -p $fogprogramdir/cache`
    # much later -- so writing here before that point silently produced NOTHING:
    # the redirection fails, the function returns 0 anyway, and the caller has
    # no way to tell. That only ever mattered because the sole call site was at
    # the very end of the install; it is a trap for any earlier one.
    mkdir -p "$fogprogramdir" >>$error_log 2>&1

    # $fogprogramdir is the live control variable and is NOT renamed: it comes
    # from --fogprogramdir or /etc/fog/fog.conf, which exists precisely because
    # .fogsettings lives at $fogprogramdir/.fogsettings and so cannot locate
    # itself. FOG_program_dir is the RECORD of it, and settingLine() resolves
    # values by indirect expansion (${!key}), so the record needs the value put
    # under its own name here or the emitted line would be empty.
    FOG_program_dir="$fogprogramdir"

    # Same shape, for the same reason: canonical-path RECORDS, now a pure
    # function of the client PKI zone rather than of ${PKI_client_cert_dir}.
    # Derived here as well as in _createCommLeaf() so they are still recorded on
    # an install that never reached it -- settingLine() resolves values by
    # ${!key}, so an unset key emits an empty line, which #1121 would read as
    # "no client cert configured".
    if [[ -n $fogprogramdir ]]; then
        # Where the zone tree physically is. Recorded rather than left implicit
        # so the utils scripts -- renewal-helper, fog-offline-ca-key,
        # fog-mint-web-ca -- read it from here instead of each carrying its own
        # copy of the default, and so an install that predates the move keeps
        # working off its recorded (empty -> compat symlink) value.
        PKI_root_dir="$(_pkiRootDir)"
        # Idempotent, and it PRESERVES a relocated value rather than
        # overwriting it: _customPkiDir() echoes $PKI_custom_dir when that is
        # already set, and only falls back to the default when it is not.
        PKI_custom_dir="$(_customPkiDir)"
        # Through _clientLeafTarget(), NOT straight to the zone path, for the
        # same reason as the two above: --client-cert/--client-key name the
        # admin's own comm keypair, and assigning the zone path here recorded
        # FOG's file over theirs. The next run sourced that, and the relocation
        # was gone with nothing reported -- while installfog.sh's own flag
        # gating already assumes these keys persist across runs, which is the
        # promise this restores rather than a new one.
        #
        # Safe to call from here: _clientLeafTarget is pure -- readlink -f and
        # nothing else, no mkdir and no writes -- and it already answers the
        # zone path for an unset record, for the zone path itself, for the
        # historic canonical name, and for a recorded path that no longer
        # exists. Same three arguments _resolveClientLeafPaths passes, so the
        # two cannot disagree about what counts as FOG's own file.
        PKI_client_encrypt_key="$(_clientLeafTarget "${PKI_client_encrypt_key}" \
            "$(_pkiZoneDir client)/leaf/.srvprivate.key" \
            "${PKI_client_cert_dir}/.srvprivate.key")"
        PKI_client_encrypt_cert="$(_clientLeafTarget "${PKI_client_encrypt_cert}" \
            "$(_pkiZoneDir client)/leaf/.srvpublic.crt" \
            "${PKI_client_cert_dir}/.srvpublic.crt")"
    fi

    # Managed keys, in the canonical order a freshly written file uses. This one
    # list drives both the fresh write and the in-place upgrade merge, so the two
    # can never drift apart again.
    #
    # Named CATEGORY_lower_snake_case, where the first _ is the category
    # boundary (GH-1120). The category is the subsystem that OWNS the value, not
    # every subsystem that reads it -- which is why the storage-node MySQL
    # password is DB_password rather than a STORAGE_ key (that a node also uses
    # it is carried by FOG_install_type='S', not by the key's name), and why
    # WEB_ and BOOT_ are separate namespaces: per ADR 0015, "the web UI uses
    # HTTPS" says nothing about the netboot transport, and separate namespaces
    # make that independence visible in the file itself rather than only in the
    # ADR.
    local -a managedKeys=(
        # --- FOG_: install shape, OS records, update channel, install location
        FOG_install_type FOG_os_id FOG_os_name FOG_install_lang FOG_send_reports
        FOG_copy_back_old
        # FOG_install_mode is which of the four presets the admin picked --
        # standard / http-only / public-cert / embed-ca -- or empty for a shape
        # assembled from the discrete transport flags instead.
        #
        # A genuine persisted PREFERENCE, like FOG_update_channel and
        # PKI_sb_enabled rather than like BOOT_url_proto: it is an answer a
        # person gave, and it has to outlive the run it was given on. Without
        # it, promptInstallMode() had nothing to check and re-asked on every
        # interactive upgrade, where a bare Enter takes the `standard` default
        # and silently reverted a public-cert or embed-ca server.
        #
        # It is NOT the model -- the four WEB_/BOOT_/PKI_ keys below are. It is
        # the preset naming a point in them, which is why any discrete flag
        # clears it (see installfog.sh) rather than letting a stale name
        # overwrite the key that moved.
        FOG_install_mode
        # FOG_installed stays unquoted+numeric in the emitted file to match the
        # historical format (see settingLine below).
        FOG_installed
        # FOG_packages is a RECORD: re-derived every run from the distro package
        # lists in lib/{redhat,ubuntu,alpine,arch}/config.sh.
        FOG_packages
        # FOG_git_path is a RECORD like FOG_program_dir (GH-850), not a control --
        # installfog.sh re-asserts the value it actually resolved after sourcing
        # this file (see resolvedfoggitpath), so a stale path left over from a
        # moved/re-cloned checkout does not silently point bin/updatefog.sh at a
        # directory that may no longer exist.
        #
        # FOG_update_channel IS a genuine persisted preference -- which channel
        # to track -- closer to PKI_sb_enabled/SVC_firewall_control below than to
        # FOG_program_dir: an admin's choice of stable/staging/dev must carry
        # forward on every upgrade, not just on the run it was made. Its VALUES
        # are untouched pending GH-1279, which reconciles them with FOG_CHANNEL.
        FOG_git_path FOG_update_channel
        # The commit that the last install to reach writeUpdateFile was built
        # from. A RECORD, and deliberately not a control: nothing reads it to
        # decide what to check out. Its only consumer is offerRevert(), which
        # uses it to name a commit worth going back to when a later run fails.
        #
        # "Reached writeUpdateFile" is the definition of success here, and it is
        # narrower than "finished". Everything expensive and destructive --
        # configureHttpd's rebuild, updateDB, the TFTP tree, the services -- is
        # already behind that point, and the steps after it are the ones a
        # revert would not have helped with anyway. A failure before it leaves
        # this key naming the previous good commit, which is exactly when the
        # offer is worth making.
        FOG_last_good_commit
        # GH-850: recorded so `grep FOG_program_dir .fogsettings` answers "where
        # does this install live" -- but it is a RECORD, not a control. See the
        # assignment above for why it cannot be one.
        FOG_program_dir

        # --- NET_: this server's own network identity
        NET_interface NET_fog_server_ip NET_subnet_mask NET_hostname

        # --- DHCP_: FOG acting AS a DHCP server
        #
        # DHCP_enabled replaces dodhcp (Y/N) + bldhcp (1/0), which were one
        # answer in two encodings, both written from the same prompt. It carries
        # bldhcp's 1/0 because every DECISION read that one; dodhcp was read only
        # by the prompt loop that wrote it.
        #
        # DHCP_router replaces routeraddress + plainrouter. routeraddress doubled
        # as a config-file comment -- declining a router stored the literal
        # string "#   No router address added" -- so plainrouter existed purely
        # to hold the clean value for display. One key now holds the clean value
        # or nothing, and the config writers emit the comment. DHCP_dns_server_ip
        # had the identical wart with no clean twin and gets the same treatment.
        DHCP_enabled DHCP_engine DHCP_service_name DHCP_router DHCP_dns_server_ip
        DHCP_range_start DHCP_range_end

        # --- DB_: the database connection and its dump path
        #
        # The sn prefix is gone: it read as "storage node", but these are used on
        # a full server too.
        DB_name DB_host DB_user DB_password DB_external DB_backup_path

        # --- WEB_: the web server and the web-UI URL surface
        #
        # What httpproto used to conflate, as independent keys (ADR 0015).
        #
        # WEB_https_redirect is what -S/--force-https has always MEANT -- its own
        # help text says "serve both HTTP and HTTPS without redirecting" -- and
        # is seeded once from a pre-existing WEB_url_proto=https (installfog.sh).
        # Persisting it is what makes that migration one-shot: an admin who
        # turns the redirect off must not have the next upgrade turn it back on
        # by re-reading WEB_url_proto.
        #
        # WEB_url_primary is which of the two management URLs the installer
        # prints FIRST when it finishes -- `name` (default) or `address`. A
        # genuine preference with no flag and no prompt: it changes two lines of
        # closing output and nothing else, so it is not worth a question every
        # install, but it must survive one. See _managementUrls.
        WEB_server_engine WEB_docroot WEB_root WEB_php_version
        WEB_url_proto WEB_https_redirect WEB_url_primary

        # --- BOOT_: the client netboot path -- iPXE, TFTP, FOS kernels
        #
        # BOOT_url_proto is the protocol iPXE uses for boot.php. A RECORD, not a
        # preference: it is re-derived on every run from
        # PKI_web_cert_publicly_trusted/BOOT_rebuild_ipxe_with_my_ca, and
        # persisting it is so .fogsettings readers can see what was resolved. It
        # used to be treated as a preference, which is what let a derived value
        # outlive the keys it was derived from -- see _resolveNetbootProto.
        #
        # BOOT_url_proto_forced is whether that was chosen by --netboot-proto
        # rather than derived. This is the preference half of the pair, and the
        # only thing that makes "the admin forced this" distinguishable from "a
        # previous run worked this out". Without it, protecting a forced value
        # and protecting a stale one are the same code path.
        BOOT_url_proto BOOT_url_proto_forced BOOT_rebuild_ipxe_with_my_ca
        # BOOT_external_tftp_server replaces noTftpBuild and deliberately KEEPS
        # its polarity, so the migration copies the value across with no
        # inversion. It names the reason rather than the mechanic: yes means TFTP
        # lives elsewhere, so skip the rebuild -- and it still reads correctly
        # against the firewall behavior, where an external TFTP server means
        # 69/udp stays closed.
        BOOT_external_tftp_server BOOT_tftp_options
        # Seconds of pre-DHCP sleep written into autoexec.ipxe, for switches
        # that take time to come out of STP/powersave. A genuine preference: it
        # is the admin's answer to their own network hardware, and losing it on
        # upgrade brings back the intermittent "no DHCP answer" boots it was set
        # to cure. 0 (the default) writes no sleep at all.
        BOOT_dhcp_delay_seconds
        # How many prior kernel/init generations backupPreservedCustomizations()
        # keeps under customizations/kernel-backups. A genuine persisted
        # preference like FOG_update_channel, not a record: an admin who chose
        # deeper history must keep it across every future upgrade, or the
        # generations they were relying on get evicted by the next run.
        BOOT_kernel_backups_kept

        # --- STORAGE_: image storage and its NFS export
        STORAGE_image_share_path STORAGE_rebuild_nfs_exports

        # --- SVC_: FOG's system account and host services
        #
        # `password` was the most generically named key in a file holding two
        # different secrets. SVC_password / DB_password says which is which at a
        # glance, which matters given the redaction warning the docs carry.
        #
        # SVC_firewall_control (GH-964 sibling) is what the admin chose for the
        # local firewall: configure/disable/skip. Persisted for the same reason
        # PKI_sb_enabled is -- so an upgrade does not quietly undo a deliberate
        # decision. Without it, an admin who answered "leave it alone" would be
        # re-asked every upgrade, and under -y would simply be overridden.
        SVC_user SVC_password SVC_firewall_control

        # --- PKI_: certificate authorities and trust, with a zone token in the
        #     name (root / web / client / sb), matching PKI_ZONES.md.
        #
        # Secure Boot is PKI_sb_* rather than its own top-level prefix, but its
        # issued material is named codesign, not leaf: the Secure Boot zone
        # carries extendedKeyUsage = codeSigning on both the CA and the cert it
        # issues, while the web zone carries serverAuth. Nothing in the Secure
        # Boot zone authenticates a server, and a shared `leaf` token would say
        # it did.
        #
        # POLICY AND INPUTS first, then the canonical paths under the
        # "## Derived -- do not edit" marker (see the fresh-write path below).
        #
        # PKI_web_cert_publicly_trusted is a persisted STATEMENT, never a
        # measurement. FOG adds its own CA to the host trust store by default,
        # so a plain openssl probe answers "trusted" for FOG's own leaf --
        # exactly the case that needs the rebuild -- and a value re-derived every
        # run from a store other software also writes to is not something to hang
        # a 25-minute build on.
        #
        # PKI_allowed_domain_names governs both the FQDNs the CA may issue for
        # and the names added to certs and the vhost ServerAlias, which is what
        # "allowed" carries. With PKI_internal_subnets these are genuine
        # persisted preferences: an admin who narrowed their CA to specific
        # subnets must keep that narrowing on every later run, or the next
        # upgrade would quietly re-issue with the broad default. They only take
        # effect when an intermediate is FIRST issued -- an existing CA is never
        # re-minted -- so changing them means removing the intermediate as well.
        #
        # PKI_sb_enabled carries --no-secure-boot forward: an opt-out that
        # reverted on the next upgrade would hand the admin back a root-only key
        # and a sudoers rule they had deliberately declined.
        PKI_sb_enabled PKI_web_cert_publicly_trusted
        PKI_allowed_domain_names PKI_internal_subnets
        # The SAN keys keep `san` in the name because they reach past
        # certificates: PKI_san_ip_addresses also writes the nginx maintenance
        # allow list, and PKI_san_dns_names is mirrored into globalSettings as
        # FOG_EXTRA_SERVER_NAMES. Both are genuine preferences -- an admin's
        # extra vhost/cert name(s) must carry forward on every upgrade, not just
        # the run they were set on.
        PKI_san_ip_addresses PKI_san_dns_names
        # Where FOG's own four-zone PKI tree lives: /etc/fog/pki, reachable at
        # its historic $fogprogramdir/pki name through a symlink. An empty value
        # in a file written before the move means the same thing as the default,
        # because that name still resolves.
        PKI_root_dir
        # Where the client-communication leaf and the uploaded snapin SSL
        # material live. NOT where FOG's CAs live any more, which is the whole
        # reason the old name (sslpath) had to go.
        PKI_client_cert_dir
        # Where the admin drops certificates and keys they brought themselves:
        # /etc/fog/customizations/pki, a SIBLING of PKI_root_dir rather than a
        # directory inside it. That is load-bearing -- _externallyManagedLeaf()
        # answers "the admin manages this leaf" for anything resolving outside
        # the web zone, so a sibling needs no flag and nothing that can go
        # stale. Relocatable, like PKI_root_dir, so the shell tests can point it
        # at a scratch directory.
        PKI_custom_dir
        # --- canonical paths (records) ---
        #
        # The trust anchor: what ca.cert.der publishes and what fog-client pins.
        # Recorded explicitly rather than inferred from PKI_web_ca_cert, because
        # that variable names the CA that signs the VHOST leaf -- the Web
        # intermediate -- and deriving the root from it would, on the next run,
        # mistake the intermediate for the root.
        PKI_root_ca_cert PKI_root_ca_key
        # The CA that signs the vhost leaf -- which is what an external CA
        # replaces, and all it replaces. --ca-cert/--ca-key and --web-ca-cert/
        # --web-ca-key still work and still import here; they are run-scoped
        # INPUTS now rather than six persisted keys holding three values, which
        # is what silently discarded anything typed at the prompt whenever the
        # flags were also given.
        PKI_web_ca_cert PKI_web_ca_key
        # An imported root that FOG does not already have. Empty on an ordinary
        # install. Kept SEPARATE from PKI_root_ca_cert on purpose: an external
        # web CA's root is fed to the chain file only, and conflating it with the
        # root fog-client pins is precisely the conflation the three-zone split
        # exists to prevent.
        PKI_web_external_root_cert
        # The web zone's TRUST PATH -- intermediate plus the root anchoring it --
        # and NOT what the vhost serves. The served files are built by
        # _writeWebChainFiles() and never persisted. It survives as a key because
        # a storage node has no web CA of its own and is handed .nodeChain.pem by
        # the master, and because _resolveTrustAnchor() reads the external root
        # back out of it.
        PKI_web_trust_chain
        # The vhost keypair. Externally-managed leaves (ACME, purchased, a
        # corporate issuance process) are handled by pointing these canonical
        # paths at the real files, so there is no separate "is it managed
        # elsewhere" key to forget to set -- FOG asks the filesystem instead.
        PKI_web_vhost_cert PKI_web_vhost_key
        # The client-communication keypair, which every registered client pins.
        # These were local variables and hardcoded $sslpath/ paths, which made
        # the client zone the only one an admin could not point elsewhere -- the
        # exception a model whose premise is "say where the cert is" cannot have.
        # The canonical NAMES are not free: FOGBase builds .srvprivate.key with
        # the name hardcoded, taking the directory from the storage-node record.
        # These keys name a canonical path whose TARGET may move.
        PKI_client_encrypt_cert PKI_client_encrypt_key
        # Persisted so every later upgrade re-signs the kernels without the
        # admin passing the flags again -- an upgrade that silently replaced
        # signed kernels with unsigned ones is the main way this setup breaks.
        #
        # PKI_sb_ca_cert is the certificate endpoints ENROLL, which is not always
        # the one that signs. Persisted so an admin who supplied their own Secure
        # Boot intermediate does not have to re-pass it on every later run -- and
        # so a rotated signing leaf keeps pointing at the same enrolled CA.
        PKI_sb_ca_cert PKI_sb_codesign_cert PKI_sb_codesign_key
    )
    # Keys written by older installers that must be stripped on upgrade.
    #
    # pkiMode and fogClientCACN belong to the four-tier layout that this
    # replaced: a root above a client intermediate, selected by --split-pki.
    # There is one hierarchy now, so a stale pkiMode='flat' left in the file
    # would describe a layout the installer no longer has code for.
    #
    # ###################################################################
    # THIS ARRAY ONLY STRIPS. IT CARRIES NO VALUE.
    #
    # The 79 pre-GH-1120 spellings below are stripped by the awk merge, and
    # nothing here moves their values anywhere. What carries them is the
    # one-shot rename-seed block in bin/installfog.sh, which runs after
    # .fogsettings is sourced and before the flag shadows are applied.
    #
    # Deleting or skipping that block while leaving this array in place wipes
    # every setting on the next upgrade -- silently, and under -y. The two
    # halves are one mechanism; they must land and stay together.
    # ###################################################################
    #
    # Case matters here and is doing real work: fog_git_path and FOG_git_path,
    # fog_update_channel and FOG_update_channel are DISTINCT keys to both the
    # awk merge and the shell, so the old lowercase spellings strip while the
    # new ones are written.
    local -a deprecatedKeys=(
        # pre-GH-1120 layouts, retired earlier
        storageftpuser storageftppass bootfilename notpxedefaultfile php_verAdds
        pkiMode fogClientCACN
        # -> FOG_*
        installtype osid osname packages installlang sendreports fogupdateloaded
        copybackold fog_update_channel fogprogramdir fog_git_path
        # -> NET_*
        interface ipaddress submask hostname
        # -> DHCP_* (dodhcp+bldhcp merged; routeraddress+plainrouter merged)
        dodhcp bldhcp dhcpengine dhcpd routeraddress plainrouter dnsaddress
        startrange endrange
        # -> DB_*
        mysqldbname snmysqlhost snmysqluser snmysqlpass snmysqlexternal backupPath
        # -> WEB_*
        webserver docroot webroot php_ver httpproto httpsRedirect
        # -> BOOT_*
        netbootproto netbootProtoForced rebuildIpxeWithMyCA bootdelay noTftpBuild
        tftpAdvOpts kernelBackupGenerations
        # -> STORAGE_* / SVC_*
        storageLocation blexports username password fwconfigure
        # -> PKI_* (extc*/webExtCA* were six keys holding three values;
        #    webCertFile/webKeyFile folded into the vhost pair)
        rootCAPem rootCAKey sslcapem sslcakey sslcachain sslpubcert sslprivkey
        sslpath publicWebCert internalDomains internalSubnets ipaddresses
        extraServerNames secureboot secureBootCert secureBootKey secureBootMokCert
        extcacert extcakey extcaroot webExtCACert webExtCAKey webExtCARoot
        webCertFile webKeyFile
        # Retired outright by GH-1120 -- no replacement key, the information is
        # now derived. sslcsr: the CSR is read from its canonical path.
        # acmeLeaf: a vhost cert resolving outside the PKI zone dir IS the
        # signal. externalca: already derived from a non-empty import path, and
        # now prompt-scoped. catrust: FOG's CA is always anchored in its own host
        # trust store. caCreated: both uses already paired it with an existence
        # check on the very file it stood in for. sbNameConstraints: constraints
        # come off the Secure Boot zone entirely -- firmware is a verifier FOG
        # cannot patch, unlike iPXE (ADR 0016).
        sslcsr acmeLeaf externalca catrust caCreated sbNameConstraints
    )

    # Emit one "key='value'" line, single-quote-safe for any value (embedded
    # single quotes become '\''). FOG_installed stays unquoted+numeric to
    # match the historical file format.
    settingLine() {
        local key="$1" val
        case "$key" in
            FOG_installed) printf 'FOG_installed=%s\n' "${FOG_installed:-1}"; return ;;
            *) val="${!key}" ;;
        esac
        printf "%s='%s'\n" "$key" "${val//\'/\'\\\'\'}"
    }

    # Section headers for a canonically written file, keyed by the managed key
    # each one precedes. managedKeys above stays the single source of ORDER --
    # this only adds the comments, so the two cannot drift apart.
    settingSection() {
        case "$1" in
            FOG_install_type)         printf '\n## FOG -- install shape, OS records, update channel, install location\n' ;;
            NET_interface)            printf '\n## NET -- this server own network identity\n' ;;
            DHCP_enabled)             printf '\n## DHCP -- FOG acting AS a DHCP server\n' ;;
            DB_name)                  printf '\n## DB -- the database connection and its dump path\n' ;;
            WEB_server_engine)        printf '\n## WEB -- the web server and the web-UI URL surface\n' ;;
            BOOT_url_proto)           printf '\n## BOOT -- the client netboot path: iPXE, TFTP, FOS kernels\n' ;;
            STORAGE_image_share_path) printf '\n## STORAGE -- image storage and its NFS export\n' ;;
            SVC_user)                 printf '\n## SVC -- FOG own system account and host services\n' ;;
            PKI_sb_enabled)           printf '\n## PKI -- certificate authorities and trust\n' ;;
            PKI_root_ca_cert)
                printf '\n## Derived -- do not edit\n'
                printf '## Canonical certificate paths. The installer recomputes most of these\n'
                printf '## every run, so editing one here moves nothing.\n'
                printf '##\n'
                printf '## The exceptions are PKI_web_vhost_cert, PKI_web_vhost_key,\n'
                printf '## PKI_web_trust_chain, PKI_client_encrypt_cert and\n'
                printf '## PKI_client_encrypt_key. To serve or use a certificate FOG did not\n'
                printf '## issue you may either leave those alone and make each path resolve to\n'
                printf '## your file (a symlink is enough), or set them to where your files\n'
                printf '## really are -- the installer only resets one of them while it still\n'
                printf '## holds a default of its own. Either way FOG stops re-issuing it.\n'
                printf '##\n'
                printf '## The two client_encrypt keys are the fog-client communication keypair,\n'
                printf '## normally set with --client-cert/--client-key. Relocating them is a\n'
                printf '## re-pin event if the material differs: the installer says so, and the\n'
                printf '## key must stay readable by the web server or no client can\n'
                printf '## authenticate.\n'
                printf '##\n'
                printf '## Simplest of all: drop web-leaf.pem and web-leaf.key into\n'
                printf '## PKI_custom_dir above and re-run the installer, which finds the pair\n'
                printf '## and points these at it for you.\n'
                ;;
        esac
    }

    # Write the whole file canonically: header, category blocks, then any lines
    # carried over from a previous file that the installer does not manage.
    emitFogSettings() {
        local carry="$1" key
        echo "## Start of FOG Settings"
        echo "## Created by the FOG Installer"
        echo "## Find more information about this file in the FOG Project wiki:"
        echo "##     https://wiki.fogproject.org/wiki/index.php?title=.fogsettings"
        echo "## Version: $version"
        echo "## Install time: $tmpDte"
        for key in "${managedKeys[@]}"; do
            settingSection "$key"
            settingLine "$key"
        done
        echo "## End of FOG Settings"
        if [[ -n $carry && -s $carry ]]; then
            echo
            echo "## Carried over from the previous file. The installer does not manage"
            echo "## these, and preserves them because .fogsettings is sourced in full."
            cat "$carry"
        fi
    }

    local key
    if [[ -f $fogprogramdir/.fogsettings ]] && \
        { grep -q "^## Start of FOG Settings" "$fogprogramdir/.fogsettings" || grep -q "^## Version:" "$fogprogramdir/.fogsettings"; }; then
        local managedLines depList
        managedLines=$(for key in "${managedKeys[@]}"; do settingLine "$key"; done)
        depList=$(printf '%s\n' "${deprecatedKeys[@]}")
        if ! grep -qE "^(FOG|NET|DHCP|DB|WEB|PKI|BOOT|STORAGE|SVC)_[A-Za-z_]+=" "$fogprogramdir/.fogsettings"; then
            # ONE-TIME canonical rewrite: a recognizable file that still carries
            # only pre-GH-1120 spellings (GH-1120 renamed all 79 managed keys).
            #
            # The in-place merge below cannot do this run. It rewrites each
            # managed key IN THE POSITION IT ALREADY OCCUPIES and appends the
            # ones it did not find -- but on this run EVERY old key is deprecated
            # and EVERY new key is absent, so it would strip all 79 lines and
            # append 66 at the end. The category blocks and the "## Derived" 
            # marker would end up describing nothing, and the file would read as
            # a pile of appended keys after "## End of FOG Settings".
            #
            # Unrecognized lines still have to survive. Hand-set keys
            # (inetConnectTimeout, inetMaxTime, storageLocationCapture,
            # ftppasvmin/max, mcastportmin/max) work ONLY because the merge
            # preserves lines it does not know about; a plain fresh write would
            # silently drop every one of them, along with any comment an admin
            # left themselves.
            local carried
            carried=$(mktemp 2>>$error_log)
            mline="$managedLines" deps="$depList" awk '
                BEGIN {
                    n = split(ENVIRON["mline"], ml, "\n")
                    for (i = 1; i <= n; i++) {
                        eq = index(ml[i], "=")
                        MAP[substr(ml[i], 1, eq - 1)] = 1
                    }
                    m = split(ENVIRON["deps"], dl, "\n")
                    for (i = 1; i <= m; i++) if (dl[i] != "") DEP[dl[i]] = 1
                }
                # Drop the header/footer comments we are about to re-emit, but
                # keep any other comment: it is the admins own note.
                /^## (Start of FOG Settings|End of FOG Settings|Created by the FOG Installer|Find more information|    *https:\/\/wiki\.fogproject\.org|Version:|Install time:)/ { next }
                {
                    eq = index($0, "=")
                    key = (eq ? substr($0, 1, eq - 1) : "")
                    if (key != "" && (key in DEP)) next
                    if (key != "" && (key in MAP)) next
                    print
                }
            ' "$fogprogramdir/.fogsettings" > "$carried" 2>>$error_log
            emitFogSettings "$carried" > "$fogprogramdir/.fogsettings.tmp" \
                && cat "$fogprogramdir/.fogsettings.tmp" > "$fogprogramdir/.fogsettings" \
                && rm -f "$fogprogramdir/.fogsettings.tmp"
            rm -f "$carried"
        else
        # Existing, valid file: update managed keys in place, strip deprecated
        # keys, refresh the version header, and leave every other line untouched.
        mline="$managedLines" deps="$depList" ver="$version" awk '
            BEGIN {
                n = split(ENVIRON["mline"], ml, "\n")
                for (i = 1; i <= n; i++) {
                    eq = index(ml[i], "=")
                    ORDER[i] = substr(ml[i], 1, eq - 1)
                    MAP[ORDER[i]] = ml[i]
                }
                m = split(ENVIRON["deps"], dl, "\n")
                for (i = 1; i <= m; i++) if (dl[i] != "") DEP[dl[i]] = 1
                verline = "## Version: " ENVIRON["ver"]
            }
            {
                if ($0 ~ /^## Version:/) { print verline; seenver = 1; next }
                eq = index($0, "=")
                key = (eq ? substr($0, 1, eq - 1) : "")
                if (key != "" && (key in DEP)) next
                if (key != "" && (key in MAP)) { print MAP[key]; SEEN[key] = 1; next }
                print
            }
            END {
                if (!seenver) print verline
                for (i = 1; i <= n; i++) if (!(ORDER[i] in SEEN)) print MAP[ORDER[i]]
            }
        ' "$fogprogramdir/.fogsettings" > "$fogprogramdir/.fogsettings.tmp" \
            && cat "$fogprogramdir/.fogsettings.tmp" > "$fogprogramdir/.fogsettings" \
            && rm -f "$fogprogramdir/.fogsettings.tmp"
        fi
    else
        # No file, or a file with no recognizable header: write from scratch.
        # Fresh files default an empty DB_external to 0 (historical behavior;
        # the in-place upgrade path leaves it as-is).
        DB_external="${DB_external:-no}"
        emitFogSettings "" > "$fogprogramdir/.fogsettings"
    fi
    # This file holds two cleartext passwords -- ${SVC_password} (the ${SVC_user}
    # system account, which is also the FTP account image replication logs in
    # with) and ${DB_password} -- and used to be left at whatever the umask gave
    # it, which is 0644. Every local account on the server could read both, and
    # the FTP one is fleet-wide.
    #
    # It was never merely an oversight: Route::whoami() read this file directly
    # with parse_ini_file(), so the web user NEEDED it readable. That is what
    # .fogsettings.pub below exists to end -- the five server facts whoami
    # answers with are published separately, so the secrets can be shut away
    # without taking a working API route with them.
    chown root:root "$fogprogramdir/.fogsettings" >>$error_log 2>&1
    chmod 0600 "$fogprogramdir/.fogsettings" >>$error_log 2>&1
    # The public half: exactly the facts Route::whoami() answers with, and
    # nothing else. World-readable on purpose -- the web user parses it.
    #
    # Written per server rather than mirrored into globalSettings, which was
    # the obvious alternative and is wrong here. A storage node serves the API
    # too (configureMinHttpd stubs out the management UI, not api/index.php)
    # and its config.class.php points at the MASTER's database, so a
    # globalSettings-backed whoami would have every node reporting the
    # master's hostname, IP and FOG_install_type='N'. Answering "what am I" from a
    # shared table cannot work. Same format as .fogsettings so the same
    # parse_ini_file() call reads it.
    {
        echo "## Written by the FOG installer -- DO NOT EDIT."
        echo "## The public subset of .fogsettings: the server facts the"
        echo "## /api/whoami route answers with. Regenerated on every run."
        echo "## .fogsettings itself is 0600 because it holds passwords."
        local pubkey
        for pubkey in NET_fog_server_ip NET_hostname FOG_os_id FOG_os_name FOG_install_type; do
            settingLine "$pubkey"
        done
    } > "$fogprogramdir/.fogsettings.pub" 2>>$error_log
    chown root:root "$fogprogramdir/.fogsettings.pub" >>$error_log 2>&1
    chmod 0644 "$fogprogramdir/.fogsettings.pub" >>$error_log 2>&1
}
displayBanner() {
    echo
    echo "  =================================="
    echo "  ===        ====    =====      ===="
    echo "  ===  =========  ==  ===   ==   ==="
    echo "  ===  ========  ====  ==  ====  ==="
    echo "  ===  ========  ====  ==  ========="
    echo "  ===      ====  ====  ==  ========="
    echo "  ===  ========  ====  ==  ===   ==="
    echo "  ===  ========  ====  ==  ====  ==="
    echo "  ===  =========  ==  ===   ==   ==="
    echo "  ===  ==========    =====      ===="
    echo "  =================================="
    echo "  ===== Free Opensource Ghost ======"
    echo "  =================================="
    echo "  ============ Credits ============="
    echo "  = https://fogproject.org/Credits ="
    echo "  =================================="
    echo "  == Released under GPL Version 3 =="
    echo "  =================================="
    echo
}
# Returns 0 if the certificate at $1 is a CA certificate (basicConstraints CA:TRUE)
isCACert() {
    local crt="$1"
    if openssl x509 -in "$crt" -noout -ext basicConstraints >/dev/null 2>&1; then
        openssl x509 -in "$crt" -noout -ext basicConstraints 2>/dev/null | grep -qi "CA:TRUE"
    else
        # Older OpenSSL without the -ext option (e.g. some Alpine builds)
        openssl x509 -in "$crt" -noout -text 2>/dev/null | grep -A1 -i "Basic Constraints" | grep -qi "CA:TRUE"
    fi
}
# Validate the admin-supplied external/intermediate CA and import it into FOG's CA
# directory. On success sets sslcakey, sslcapem and sslcachain. Hard-fails the
# install on any validation error.
validateExternalCA() {
    # Zone-parameterised. No argument means the historic flat behavior, so
    # every existing caller keeps working untouched -- the flat zone imports to
    # ${PKI_client_cert_dir}/CA, exactly as before.
    #
    # Both zones now read ONE set of import inputs, $importWebCACert /
    # $importWebCAKey / $importWebCARoot, which --ca-cert/--ca-key/--ca-root and
    # --web-ca-cert/--web-ca-key/--web-ca-root both write (GH-1120). They used
    # to be two parallel sets of persisted keys resolved as
    # ${webExtCACert:-$extcacert}, so the command line always won and anything
    # typed at the prompt was silently discarded.
    local zone="${1:-flat}"
    local certsrc keysrc rootsrc destdir destcert destkey destchain
    case $zone in
        web)
            certsrc="$importWebCACert"; keysrc="$importWebCAKey"; rootsrc="$importWebCARoot"
            destdir="$(_pkiZoneDir web)/ca"; destcert=".fogWebCA.pem"; destkey=".fogWebCA.key"; destchain=".fogWebCAchain.pem"
            ;;
        *)
            certsrc="$importWebCACert"; keysrc="$importWebCAKey"; rootsrc="$importWebCARoot"
            destdir="$(_pkiZoneDir root)/ca"; destcert=".fogCA.pem"; destkey=".fogCA.key"; destchain=".fogCAchain.pem"
            ;;
    esac
    mkdir -p "$destdir" >>$error_log 2>&1

    local f haveSource=1
    for f in "$certsrc" "$keysrc" "$rootsrc"; do
        [[ -z $f || ! -r $f ]] && haveSource=0
    done
    if [[ $haveSource -eq 0 ]]; then
        # No readable source files this run; reuse a previously imported CA if present
        if [[ -e $destdir/$destcert && -e $destdir/$destkey && -e $destdir/$destchain ]]; then
            PKI_web_ca_key="$destdir/$destkey"
            PKI_web_ca_cert="$destdir/$destcert"
            PKI_web_trust_chain="$destdir/$destchain"
            return 0
        fi
        echo "  External CA is enabled but a required file is missing or unreadable"
        echo "  and no previously imported CA was found (zone: $zone):"
        echo "    intermediate cert: ${certsrc:-<unset>}"
        echo "    intermediate key:  ${keysrc:-<unset>}"
        echo "    root cert:         ${rootsrc:-<unset>}"
        echo "  Provide them via the installer prompts or the"
        echo "  --ca-cert/--ca-key/--ca-root options, then re-run the installer."
        exit 1
    fi
    dots "Validating external CA files (${zone})"
    # The supplied private key must match the supplied intermediate certificate
    local certpub keypub certalgorithm certcurve
    # Compare the SUBJECT PUBLIC KEY, not an RSA modulus. `openssl rsa -modulus`
    # only understands RSA, so an EC -- or Ed25519, or RSA-PSS -- CA could never
    # pair with its own key and the install aborted on a "does not match" that
    # was not true (GH-1393). `openssl x509 -pubkey` and `openssl pkey -pubout`
    # emit byte-identical SPKI PEM for every algorithm openssl can load, so ONE
    # comparison covers all of them.
    #
    # Deliberately NOT an algorithm allow-list feeding two per-algorithm
    # comparisons. A list here has to be edited every time openssl names
    # something new, and anything it has not heard of hard-fails an install that
    # would otherwise have worked -- which is how RSA-PSS, supported before this
    # check existed, briefly stopped working. Nothing downstream cares which
    # algorithm the CA uses; it only has to sign, and openssl decides that.
    #
    # Raw PEM, no `openssl md5`: md5 of the empty output an unreadable file
    # produces is a non-empty hash, so with it two unreadable files "pair".
    certpub=$(openssl x509 -pubkey -noout -in "$certsrc" 2>>$error_log)
    keypub=$(openssl pkey -pubout -in "$keysrc" 2>>$error_log)
    if [[ -z $certpub || -z $keypub || $certpub != "$keypub" ]]; then
        echo "Failed"
        echo "  The supplied CA private key ($keysrc) does not match the"
        echo "  supplied CA certificate ($certsrc)."
        exit 1
    fi
    # DSA is the one algorithm openssl will happily pair and nothing downstream
    # will accept: TLS 1.3 removed DSA signatures outright, no current browser
    # trusts a DSA chain, and iPXE has no DSA at all. It was already refused
    # before the comparison above became algorithm-agnostic -- as a bogus "key
    # does not match", because `openssl rsa -modulus` cannot read a DSA key --
    # so this keeps the outcome and drops the lie about the cause.
    #
    # An exact match, not a default-reject arm: a garbage or unreadable
    # certificate leaves this empty, and that case belongs to the pairing check
    # above, which names the two files.
    certalgorithm=$(openssl x509 -noout -text -in "$certsrc" 2>>$error_log         | awk -F': ' '/Public Key Algorithm/ {print $2; exit}')
    if [[ $certalgorithm == "dsaEncryption" ]]; then
        echo "Failed"
        echo "  The supplied CA certificate ($certsrc) uses DSA, which no"
        echo "  current TLS client accepts -- TLS 1.3 removed DSA signatures"
        echo "  entirely, and iPXE cannot verify them at all."
        echo "  Re-issue the CA with an RSA or elliptic curve key."
        exit 1
    fi
    # The supplied intermediate must actually be a CA certificate
    if ! isCACert "$certsrc"; then
        echo "Failed"
        echo "  The supplied certificate ($certsrc) is not a CA certificate"
        echo "  (basicConstraints CA:TRUE is required)."
        exit 1
    fi
    # The intermediate must chain up to the supplied root
    if ! openssl verify -CAfile "$rootsrc" "$certsrc" >>$error_log 2>&1; then
        echo "Failed"
        echo "  The intermediate CA ($certsrc) does not verify against the"
        echo "  supplied root CA ($rootsrc)."
        exit 1
    fi
    # Import into the zone's directory so signing and serial files stay writable
    # and the layout matches the generated case.
    cp "$certsrc" "$destdir/$destcert" >>$error_log 2>&1
    cp "$keysrc" "$destdir/$destkey" >>$error_log 2>&1
    cat "$rootsrc" "$certsrc" > "$destdir/$destchain" 2>>$error_log
    chmod 600 "$destdir/$destkey" >>$error_log 2>&1
    # The root as well, under the name the Certificates page's helper already
    # publishes as PKI_EXTERNAL_ROOT. Copied here rather than merely referenced
    # for the same reason as the three above -- and see the persisted slot below
    # for what recording the source path instead used to cost.
    cp "$rootsrc" "$destdir/.externalRoot.pem" >>$error_log 2>&1
    chmod 0644 "$destdir/.externalRoot.pem" >>$error_log 2>&1
    # ${PKI_web_ca_key}/${PKI_web_ca_cert}/${PKI_web_trust_chain} name the CA that signs the vhost leaf --
    # which is what an external CA replaces, and all it replaces. The root that
    # fog-client pins is ${PKI_root_ca_cert} and is not touched here.
    PKI_web_ca_key="$destdir/$destkey"
    PKI_web_ca_cert="$destdir/$destcert"
    PKI_web_trust_chain="$destdir/$destchain"
    # The imported root gets its OWN persisted slot, deliberately separate from
    # ${PKI_root_ca_cert}. It is fed to the chain file above and nowhere else,
    # and _resolveTrustAnchor() reads it back out from here -- conflating it
    # with the root fog-client pins is exactly the mistake the three-zone split
    # exists to prevent.
    #
    # The CANONICAL copy, not $rootsrc. --ca-root is routinely handed a temp
    # file, so recording the source left this key naming something that no
    # longer existed by the next run: _resolveTrustAnchor()'s -f guard then made
    # the miss silent, and GH-1121's fix -- reading this key so an import is not
    # undone by the next installer run -- held only for roots imported through
    # the Certificates page. fog-pki-admin's setPreferencePath() has always
    # recorded the canonical path; this is the CLI half catching up, and it puts
    # both import routes on one file.
    PKI_web_external_root_cert="$destdir/.externalRoot.pem"
    errorStat $?
    # iPXE's verifier is narrower than openssl's, and THIS certificate is the
    # one it gets: _resolveIpxeTrust() sets ${ipxetrust} to ${PKI_web_ca_cert},
    # and buildipxe.sh compiles it in as CERT=/TRUST=. The pinned tag (fog-ipxe
    # IPXEVER, v2.0.0 at time of writing) carries crypto/rsa.c plus ecdsa.c with
    # p256.c and p384.c, and nothing else -- no P-521, no EdDSA, no RSASSA-PSS.
    #
    # So a CA outside that set is not broken, it is narrower than it looks: the
    # web UI serves fine and fog-client is satisfied, then HTTPS netboot dies at
    # boot.php with iPXE's "Permission denied" out of x509.c, with nothing
    # server-side connecting the two. Say so here, at the moment the CA is
    # chosen, rather than leaving it to be discovered at a PXE client.
    #
    # Advisory and not a rejection: a site that netboots over HTTP, or serves
    # netboot from a publicly-trusted certificate, is unaffected -- and the
    # limit is a pinned iPXE version, not a property of the CA.
    certcurve=$(openssl x509 -pubkey -noout -in "$certsrc" 2>>$error_log         | openssl pkey -pubin -noout -text 2>>$error_log         | awk -F': ' '/NIST CURVE|ASN1 OID/ {print $2; exit}')
    # openssl >= 1.1.0 prints "NIST CURVE: P-256"; 1.0.2 prints only
    # "ASN1 OID: prime256v1". Both spellings are accepted so the advisory does
    # not fire spuriously on an older host.
    case "${certalgorithm}${certcurve:+/$certcurve}" in
        rsaEncryption|id-ecPublicKey/P-256|id-ecPublicKey/prime256v1|id-ecPublicKey/P-384|id-ecPublicKey/secp384r1)
            ;;
        *)
            echo
            echo "  Note: the imported CA uses ${certalgorithm:-an unrecognized algorithm}${certcurve:+ (${certcurve})}."
            echo "  iPXE can only verify RSA and ECDSA P-256/P-384 signatures, so HTTPS"
            echo "  netboot will fail against this CA. The web UI and fog-client are"
            echo "  unaffected. Use HTTP netboot, serve netboot from a publicly-trusted"
            echo "  certificate, or re-issue the CA with an RSA or EC P-256/P-384 key."
            echo
            ;;
    esac
    # If we are replacing the CA on a server that already issued a server cert, warn
    if [[ -n ${PKI_web_vhost_cert} && -e ${PKI_web_vhost_cert} ]] && \
        ! openssl verify -CAfile "${PKI_web_trust_chain}" "${PKI_web_vhost_cert}" >>$error_log 2>&1; then
        echo
        echo "  ###################################################################"
        echo "  # WARNING: switching this FOG server to an external/intermediate   #"
        echo "  # CA. The web server certificate and iPXE binaries will be         #"
        echo "  # regenerated and signed by the new CA. Any host whose fog-client  #"
        echo "  # already pinned the previous FOG CA will not trust this server    #"
        echo "  # until it re-pins, and PXE clients must pull the rebuilt iPXE      #"
        echo "  # binaries. Re-run the fog-client installer and/or reboot PXE       #"
        echo "  # clients after this install completes.                            #"
        echo "  ###################################################################"
        echo
    fi
}
# GH-529: the vhost templates and the docroot symlink both need ${WEB_root} in a
# form other than the "/x/" one installfog.sh normalizes to, so derive them in
# one place rather than in each consumer. Idempotent -- callers invoke it
# without caring whether an earlier one already has.
#
#   webroot     /myfog/    URL path, as stored in .fogsettings
#   webrootbare myfog      filesystem/link name, no slashes
#   webrootre   /myfog/    escaped for the nginx/apache regex contexts, where a
#                          dot is a legitimate path character but a wildcard
#
# The default is repeated here because functions.sh is also sourced by the
# utils scripts, which never run installfog.sh's normalization.
normalizeWebroot() {
    [[ -z ${WEB_root} ]] && WEB_root="/fog/"
    webrootbare="${WEB_root#/}"
    webrootbare="${webrootbare%/}"
    webrootre=$(printf '%s' "${WEB_root}" | sed 's/[.[\*^$()+?{|]/\\&/g')
}
# Emits the fastcgi body shared by the generic `location ~ \.php$` include and
# the maintenance/ location, which needs the same PHP handling but with an
# allow/deny in front of it. Kept in one place so the two cannot drift -- if
# they did, the maintenance location would stop passing PHP to fpm and nginx
# would fall back to serving the source of those files as a static download.
# $1 = target file to append to.
# Reduces ${NET_fog_server_ip} to a single address, keeping the full set in ${PKI_san_ip_addresses}.
#
# GH-954: ${NET_fog_server_ip} is built by `ip -4 addr show ${NET_interface}`, which prints one
# line per address, so on a NIC carrying a second address it arrives multi-line.
# Roughly forty consumers treat it as one value, and the failures are silent or
# baffling: apache refused to start with "Invalid command '<second ip>'", the
# post-install probe URL came out malformed, and DHCP next-server and the iPXE
# chain target would have been handed to clients broken.
#
# Two earlier fixes -- certip for the certificate CN and confighostip for the
# config.class.php host constants, both under GH-650 -- patched single
# consumers. This settles the contract instead: ${NET_fog_server_ip} is THE address,
# ${PKI_san_ip_addresses} is every address, and the handful of places that want the whole
# set ask for it by name.
#
# Called after .fogsettings is sourced as well as after fresh detection, because
# an install written by an older installer has the multi-line value persisted in
# .fogsettings and would otherwise reload it unrepaired.
normalizeIpAddress() {
    [[ -z ${PKI_san_ip_addresses} ]] && PKI_san_ip_addresses="${NET_fog_server_ip}"
    # Unquoted on purpose: word splitting collapses the newline- or
    # space-separated forms to one space-separated list, which is what both
    # `for ip in ${PKI_san_ip_addresses}` and awk want.
    PKI_san_ip_addresses=$(echo ${PKI_san_ip_addresses})
    NET_fog_server_ip=$(echo ${PKI_san_ip_addresses} | awk '{print $1}')
}
emitNginxPhpBody() {
    echo "    set \$phproot ${WEB_docroot};" >> "$1"
    echo "    root ${WEB_docroot};" >> "$1"
    echo "    fastcgi_pass 127.0.0.1:9000;" >> "$1"
    echo "    fastcgi_index index.php;" >> "$1"
    echo "    include fastcgi.conf;" >> "$1"
    echo "    fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;" >> "$1"
    # The API supports HTTP basic auth, but nginx forwards only the
    # fastcgi_params whitelist and Authorization is not on it, so
    # PHP_AUTH_USER/PHP_AUTH_PW were never populated and basic auth
    # could not succeed.
    echo "    fastcgi_param HTTP_AUTHORIZATION \$http_authorization;" >> "$1"
    # fog-agent authenticates with a client certificate; PHP needs the
    # verdict and the certificate itself (URL-escaped: the raw form has
    # newlines, which a fastcgi param cannot carry). Empty for everyone
    # else, and Agent\Principal treats empty as "no certificate".
    echo "    fastcgi_param SSL_CLIENT_VERIFY \$ssl_client_verify;" >> "$1"
    echo "    fastcgi_param SSL_CLIENT_CERT \$ssl_client_escaped_cert;" >> "$1"
    echo "    fastcgi_buffers 16 16k;" >> "$1"
    echo "    fastcgi_buffer_size 32k;" >> "$1"
}
# --- Additive PKI: two intermediates under the existing FOG Server CA -------
#
# See docs/PKI_ZONES.md.
#
#   FOG Server CA                    the EXISTING self-signed root. Byte
#   |                                identical across an upgrade, still
#   |                                published as ca.cert.der.
#   +-- FOG Web CA                   issues the vhost's leaf, and leaves for
#   |                                storage nodes. Online.
#   +-- FOG Secure Boot CA           enrolled as MOK.der / written to db;
#   |                                issues the code-signing leaves.
#   \-- FOG Client Comm leaf         .srvprivate.key + .srvpublic.crt, in
#                                    pki/client/leaf. Signed by the root
#                                    directly -- fog-client pins the root, so
#                                    there is no intermediate to chain through.
#                                    Reachable at the historic $snapindir/ssl
#                                    names through a symlink each.
#
# Why the intermediates hang off the EXISTING root rather than a new one above
# it: an existing server then gets the separation on an ordinary update. A new
# root would have to reach every fog-client before anything beneath it could be
# trusted, which is precisely the cost this structure exists to avoid.
#
# It also means ca.cert.der -- the certificate fog-client pins -- IS the root,
# so the Web CA chains under something every client already trusts.
# Make $2 resolve to $1, so FOG can keep referencing a fixed path while the
# real file lives wherever the admin keeps it.
#
# Silent no-op when they are already the same file, which is the default
# install: linking a path to itself is what GNU ln refuses as "the same file",
# and the old inline version hit that on every run.
#
# Two caveats worth knowing when pointing these at a relocated file: SELinux
# labels follow the symlink TARGET, so a certificate outside the distro's
# expected directories may need restorecon/semanage fcontext on the real path;
# and a private key relocated somewhere world-readable silently defeats the
# 0600 root:root separation the fog-sign-kernel sudo helper depends on.
_linkCanonical() {
    local real="$1" canon="$2"
    [[ -z $real || -z $canon ]] && return 0
    [[ ! -e $real ]] && return 0
    [[ "$(readlink -f "$real" 2>/dev/null)" == "$(readlink -f "$canon" 2>/dev/null)" ]] && return 0
    ln -sf "$real" "$canon" >>$error_log 2>&1
}
# Where FOG's own PKI tree physically lives: /etc/fog/pki, not $fogprogramdir/pki.
#
# Keys and certificates are CONFIGURATION. /etc is where the rest of the system
# keeps them, and it is what a backup policy and a config-management run
# already capture, while /opt/<pkg> is for a package's own static files. The
# directory is not new and needs no per-distro branch: GH-850 already makes
# /etc/fog a real directory on every install, to hold fog.conf.
#
# $fogprogramdir/pki keeps working, as a SYMLINK at the same name, because it
# is a PUBLISHED path -- PKI_ZONES.md, MULTI_SERVER_CA.md and
# EXTERNAL_CA_AND_LETSENCRYPT.md all name /opt/fog/pki/..., an admin's renewal
# cron names /opt/fog/pki/renewal-helper, and a .fogsettings written before
# this change records canonical paths underneath it.
#
# Overridable through PKI_root_dir, recorded in .fogsettings alongside every
# other PKI location. That is what makes the tree EXPRESSIBLE rather than
# hardcoded twice -- the migration below has to be able to name its own source,
# and the shell tests point it at a scratch directory the same way they already
# point $fogprogramdir.
_pkiRootDir() {
    local root="${PKI_root_dir:-/etc/fog/pki}"
    root="${root%/}"
    # The migration is driven from HERE, not from a call site early in the
    # install, because getting that ordering wrong is not a visible failure: a
    # zone accessor answering /etc/fog/pki while the real material still sits
    # under /opt/fog/pki reads as "no CA yet", mints a fresh root, and every
    # fog-client in the estate stops trusting this server. One -L test per call
    # makes the ordering impossible to get wrong, and after the first run that
    # test is all this branch costs.
    #
    # The other two preconditions -- no $fogprogramdir, and a tree already at
    # the target -- are checked once, inside _migratePkiTree, rather than
    # duplicated here. Two copies of the same guard means removing either one
    # is invisible, which is how a guard stops being one.
    [[ -L ${fogprogramdir}/pki ]] || _migratePkiTree "$root"
    echo "$root"
}
# Move an existing $fogprogramdir/pki to $1 and leave a symlink behind.
#
# COPY, then remove, and only if the copy reported success on every file --
# never mv. /opt and /etc are frequently separate mounts, so mv degrades to
# copy-then-unlink anyway; separating the two means a failure at any point
# leaves the SOURCE authoritative and the next run simply redoes the whole
# move, rather than half a tree on each side with no record of which half.
#
# The one window that is not recoverable that way is between the removal and
# the ln, and it recovers too: the next call finds neither a link nor a
# directory, skips the copy, and links the already-populated target.
#
# Idempotent by construction. Every later run stops at the -L test in
# _pkiRootDir and never reaches here.
_migratePkiTree() {
    local target="${1:-/etc/fog/pki}" legacy="${fogprogramdir}/pki"
    [[ -z ${fogprogramdir} ]] && return 0
    [[ $legacy == "$target" ]] && return 0
    [[ -L $legacy ]] && return 0
    mkdir -p "$target" >>$error_log 2>&1 || return 1
    # The tree root is traversed by the web tier on its way to client/leaf. The
    # zones set their own (much tighter) modes; this one only has to be enterable.
    chmod 0755 "$target" >>$error_log 2>&1
    if [[ -d $legacy ]]; then
        # Nothing is removed unless the copy reported success on every file.
        # A partial copy leaves the source authoritative, which is the whole
        # reason this is not an mv.
        cp -a "$legacy/." "$target/" >>$error_log 2>&1 || return 1
        # The key material crossed a filesystem boundary, so the source blocks
        # are still on the old device after the unlink. Overwrite what we can
        # before dropping the tree. Best effort and NOT a guarantee -- shred
        # promises nothing on a journaling or copy-on-write filesystem -- but
        # leaving the fleet's root CA key recoverable from freed blocks because
        # the move was "just a rename" is the worse default.
        if command -v shred >/dev/null 2>&1; then
            find "$legacy" -type f -exec shred -u {} + >>$error_log 2>&1
        fi
        rm -rf "$legacy" >>$error_log 2>&1
    fi
    mkdir -p "$(dirname "$legacy")" >>$error_log 2>&1
    ln -s "$target" "$legacy" >>$error_log 2>&1
    # cp -a preserves the SOURCE's SELinux labels, so the moved files would
    # carry /opt's usr_t while sitting under /etc. Both are readable by the web
    # tier, so nothing breaks either way -- but relabeling to the target's own
    # default is what the next restorecon anyone runs would do, so do it now
    # rather than ship a tree whose labels change under them later.
    command -v restorecon >/dev/null 2>&1 && restorecon -RF "$target" >>$error_log 2>&1
    return 0
}
# Where an administrator drops material they brought themselves.
#
# /etc/fog/customizations is the /etc counterpart of the existing
# $fogprogramdir/customizations, and the two run in OPPOSITE directions. FOG
# writes $fogprogramdir/customizations: it copies the admin's files there before
# a run rebuilds the tree they lived in, and restores from it afterward. Nothing
# but the admin writes /etc/fog/customizations -- it is an input, and FOG only
# reads it.
#
# The split by root is the same one ADR 0037 made for FOG's own PKI. /etc is for
# small, secret, irreplaceable configuration that a backup policy and a
# config-management run are meant to capture; /opt/<pkg> is for a package's own
# static files, and the FHS does not allow binaries under /etc -- which is what
# keeps kernels and iPXE backgrounds on the /opt side.
#
# The PKI subdirectory is a SIBLING of $(_pkiRootDir), never a subdirectory of
# it, and that is the entire mechanism rather than a tidiness preference:
# _externallyManagedLeaf() decides "is this leaf FOG's or the admin's" by asking
# whether the canonical path resolves inside the web zone. A sibling answers
# "the admin's" with no new state to record and nothing that can go stale.
_customPkiDir() {
    local root="${PKI_custom_dir:-}"
    # Derived from _pkiRootDir() rather than hardcoded to /etc/fog, so that
    # relocating the PKI tree relocates this alongside it. On a default install
    # that is /etc/fog/pki -> /etc/fog -> /etc/fog/customizations/pki, which is
    # the documented path. It also keeps the shell tests off the host: they
    # already point PKI_root_dir at a scratch directory, and without this a run
    # that reached _ensureCustomizationsTree would mkdir the real
    # /etc/fog/customizations on whatever box the suite ran on.
    [[ -n $root ]] || root="$(dirname "$(_pkiRootDir)")/customizations/pki"
    echo "${root%/}"
}
# Sums of every readme revision FOG has shipped, one per line, oldest first.
#
# Adding a revision means appending its sum here, never replacing the list. The
# question each entry answers is "did FOG write this exact file", not "is this
# the current text" -- a server installed before a revision has an untouched
# readme whose text is now wrong, and that is precisely the file that should be
# replaced.
_fogShippedReadmeSums() {
    case "$1" in
        etc)
            # GH-1681, the first revision
            echo a469b039e3bb37e8353b9726b8dd0deae6165fd1d49e77e5657d4376be4f9bf9
            # GH-1684, adds the note that FOG may rewrite this file
            echo 02fa0e9953a72b7de62aa59dbcf3dad48b99c90d4070187c25b2f6c8f130ff85
            ;;
        opt)
            # GH-1681, the first revision
            echo b6c5abd15b7bf6120005fa32b346d07df4ce9259ba6922613d5677b85fffd736
            # GH-1684, adds kernel-backups/keep/ and the rewrite note
            echo 521105edd799740d61d5d138df5cec37576c42e0693c238d474186d0b9b9edcc
            ;;
    esac
}
# Whether $1 is a readme FOG may write: absent, or still byte-identical to a
# revision FOG shipped. $2 is the readme kind, etc or opt.
#
# Returns 1 -- leave it alone -- for anything else, which is an admin's edit,
# and for the case where sha256sum is missing and the question cannot be
# answered at all. Keeping somebody's note is the safe direction; there is no
# run in which discarding it is the better mistake.
_readmeIsFogsOwn() {
    local f="$1" kind="$2" cur
    [[ -f $f ]] || return 0
    command -v sha256sum >/dev/null 2>&1 || return 1
    cur=$(sha256sum "$f" 2>/dev/null | cut -d' ' -f1)
    [[ -n $cur ]] || return 1
    _fogShippedReadmeSums "$kind" | grep -qxF "$cur"
}
# The two readme files, and the directories that hold them.
#
# Idempotent, and it does NOT overwrite a readme an admin has edited -- but it
# does replace one FOG wrote itself and has since outgrown. "Written only when
# absent" was the first shape of that rule and it was too crude: it also froze
# FOG's own text, so a server installed before kernel-backups/keep/ existed kept
# a readme saying nothing is written here on your instruction, with no run that
# could ever correct it. The property worth keeping is "never discard the note
# somebody left for the next person", and a checksum tells the two apart.
#
# Both readmes are written, not just the /etc one, because the pair only makes
# sense read together -- "why are there two of these" is the question the files
# exist to answer, and a server upgraded from before this change has the /opt
# directory already and would otherwise get no explanation at all.
_ensureCustomizationsTree() {
    local etcdir customdir optdir
    # Derived from _customPkiDir() rather than hardcoded, so that setting
    # PKI_custom_dir relocates BOTH the pki directory and its parent. The shell
    # tests depend on that: a hardcoded /etc/fog/customizations here would have
    # every test run mkdir into the real host filesystem.
    etcdir="$(dirname "$(_customPkiDir)")"
    customdir="$(_customPkiDir)"
    _resolveCustomizationsDir
    optdir="$customizationsDir"
    # 0755: the web tier traverses neither of these, but an admin reads them, and
    # the PKI subdirectory below tightens to 0700 on its own.
    mkdir -p "$etcdir" "$customdir" >>$error_log 2>&1 || return 1
    chmod 0755 "$etcdir" >>$error_log 2>&1
    # A private key lands here, so the directory is no more open than the web
    # zone's own leaf/ directory is.
    chmod 0700 "$customdir" >>$error_log 2>&1
    [[ -n $optdir ]] && mkdir -p "$optdir" >>$error_log 2>&1
    # kernel-backups/keep is the one directory under here the WEB TIER writes.
    # Marking a boot file to be kept copies it in from service/ipxe, and the
    # copy is the EFFECT of that mark rather than a record of it: bfPinned
    # holds the judgment, per ADR 0042, and this is what the judgment does. The
    # distinction is the whole reason it survives that ADR's no-manifest rule --
    # a manifest is data ABOUT files and can drift from them, while this is a
    # second copy of the bytes and cannot. The pruner tests for its existence
    # with nothing to parse.
    #
    # No sudo helper and no privileged path. The alternative was to follow
    # packages/secureboot/fog-sign-kernel, but that helper's whole security
    # property is taking NO arguments -- every path it touches comes from a
    # root-owned config -- and a helper taking a filename would give that up.
    # It buys nothing either way: kernelfetch() already writes arbitrary files
    # into service/ipxe over SSH with the TFTP credentials, so "the web tier
    # can place a boot file" is a capability it already has.
    #
    # 2775 with the group setgid, so a file the web user copies in stays
    # group-readable to the installer that later restores it.
    if [[ -n $optdir ]]; then
        mkdir -p "$optdir/kernel-backups/keep" >>$error_log 2>&1
        chown "${SVC_user}:${apacheuser}" "$optdir/kernel-backups/keep" >>$error_log 2>&1
        chmod 2775 "$optdir/kernel-backups/keep" >>$error_log 2>&1
    fi
    if _readmeIsFogsOwn "$etcdir/readme.txt" etc; then
        cat > "$etcdir/readme.txt" <<'ETCREADME'
This directory is for configuration you supply yourself.

FOG only READS what is here. Nothing in an install or an update writes or
removes your files, so anything you put here survives every run.

  pki/    certificates and private keys you brought -- from your own CA, from
          Let's Encrypt or another ACME client, or purchased. FOG treats a
          certificate here as yours: it will not re-issue it, re-key it, or
          change the permissions on its private key.

          To use one, make FOG's canonical path resolve to your file, or record
          your path in the matching PKI_ key in .fogsettings. Either works.
          See https://docs.fogproject.org/lets-encrypt-setup for a worked
          example.

There is a second customizations directory, and it is not this one:

  /opt/fog/customizations   written BY FOG, not by you. It holds copies FOG
                            makes of your files before a run rebuilds the tree
                            they lived in -- the iPXE boot menu background,
                            replaced iPXE binaries, previous kernel
                            generations -- and FOG restores from it afterward.

Why two: keys and certificates are small, secret and irreplaceable, so they
belong under /etc, which is what a backup policy and a config-management run
already capture. Kernels and boot images are large, rebuildable binaries, and
the filesystem standard does not put binaries under /etc. FOG's own PKI moved
to /etc/fog/pki for the same reason.

Note that pki/ here sits BESIDE /etc/fog/pki, not inside it. That is what makes
FOG treat what you put here as yours: anything under /etc/fog/pki is read as a
certificate FOG issued and manages, and would be regenerated over.

This file is FOG's own note, and a later version of FOG may rewrite it. Edit it
and FOG leaves it alone from then on -- your version stays for good.
ETCREADME
        chmod 0644 "$etcdir/readme.txt" >>$error_log 2>&1
    fi
    if [[ -n $optdir ]] && _readmeIsFogsOwn "$optdir/readme.txt" opt; then
        cat > "$optdir/readme.txt" <<'OPTREADME'
This directory is written BY FOG. Almost nothing here needs your hand.

Before a run rebuilds a tree that might hold something of yours, FOG copies
what it finds into here, and restores it afterward:

  ipxe-bg/          the iPXE boot menu background image
  ipxe-legacy/      iPXE binaries you replaced in the TFTP tree
  kernel-backups/   previous kernel and init generations, newest kept first.
                    bin/restorekernel.sh restores from these.

One directory here is different, because it is written on YOUR instruction
rather than as part of a rebuild:

  kernel-backups/keep/
                    boot files you marked to keep, from the kernel and init
                    pages in the web interface. Marking one copies it in here;
                    a later run puts it back if it is missing from the live
                    tree. Unmark it there and the copy goes away.

If a restore ever fails, your files are still here -- nothing is deleted on the
way through.

There is a second customizations directory, and it works the other way round:

  /etc/fog/customizations   written by YOU, only read by FOG. That is where
                            certificates and private keys you supply go, under
                            pki/.

Why two: keys and certificates are small, secret and irreplaceable, so they
belong under /etc, which is what a backup policy already captures. Kernels and
boot images are large, rebuildable binaries, and the filesystem standard does
not put binaries under /etc.

This file is FOG's own note, and a later version of FOG may rewrite it. Edit it
and FOG leaves it alone from then on -- your version stays for good.
OPTREADME
        chmod 0644 "$optdir/readme.txt" >>$error_log 2>&1
    fi
    # cp/cat inherit this process's context, and /etc/fog/customizations should
    # carry etc_t the way /etc/fog/pki does -- see _migratePkiTree() for why this
    # is done now rather than left for whoever next runs a relabel.
    command -v restorecon >/dev/null 2>&1 && restorecon -RF "$etcdir" >>$error_log 2>&1
    return 0
}
# Whether $1 (a certificate) and $2 (a private key) are actually a pair.
#
# Compares the SUBJECT PUBLIC KEY, not an RSA modulus. `openssl rsa -modulus`
# cannot read an EC or Ed25519 key at all, so a modulus comparison reports "does
# not match" for a perfectly good pair and, worse, reports it identically to a
# genuine mismatch -- GH-1393. `openssl x509 -pubkey` and `openssl pkey -pubout`
# are algorithm-agnostic and answer the question actually being asked.
#
# Returns 1 when either file is missing or openssl cannot read one. That is the
# safe direction here: every caller uses this to decide whether to ADOPT a pair,
# and "I could not prove these match" must not adopt.
_certKeyPairMatches() {
    local cert="$1" key="$2" certpub keypub
    [[ -n $cert && -f $cert && -n $key && -f $key ]] || return 1
    command -v openssl >/dev/null 2>&1 || return 1
    certpub=$(openssl x509 -pubkey -noout -in "$cert" 2>/dev/null)
    keypub=$(openssl pkey -pubout -in "$key" 2>/dev/null)
    [[ -n $certpub && -n $keypub && $certpub == "$keypub" ]]
}
# The admin-supplied web leaf pair, if there is a usable one.
#
# Echoes the certificate path and then the key path, one per line, and returns 0.
# Returns 1 and echoes nothing when the pair is absent, incomplete or mismatched.
#
# Two documented names, not a glob. A glob would have to choose between several
# candidates on its own -- and the run where it chooses wrong is a run that
# points the vhost at the wrong certificate without saying so. An admin whose
# files are named something else records the path in .fogsettings instead, which
# is the explicit route and needs no guessing.
#
# ${PKI_web_trust_chain} is deliberately NOT part of the pair test. A leaf that
# chains to a publicly-trusted root needs no chain file from us for the browser
# to be happy, and createWebIntermediateCA() already honors an admin's chain
# path on every run. Requiring one here would decline perfectly good setups.
# Record the intermediates an administrator dropped beside their leaf.
#
# Copied to a canonical path with every self-signed certificate REMOVED, and
# then ${PKI_web_trust_chain} is pointed at it. Both halves are load-bearing.
#
# The copy is for the reason the imported root gets one: a setting that records
# wherever the admin's file happened to be names a temp file by next year
# (GH-1683).
#
# The strip is because _resolveTrustAnchor() anchors every self-signed
# certificate it finds in the chain file. A root left in there would be trusted
# by this host as a side effect of supplying a chain, without anybody deciding
# to trust it -- which is precisely what import-root's self-signed-only rule
# exists to prevent. An administrator whose root is not trusted yet imports it
# deliberately, on the Certificates page or with --ca-root.
#
# fog-pki-admin's adopt-custom-leaf does the same two things to the same path,
# so the page and the installer cannot disagree about what this server serves.
_adoptCustomChain() {
    local src="$1" out tmpd f subj issuer st=1
    [[ -n $src && -f $src ]] || return 1
    command -v openssl >/dev/null 2>&1 || return 1
    out="$(_pkiZoneDir web)/leaf/.externalChain.pem"
    mkdir -p "$(dirname "$out")" >>$error_log 2>&1
    tmpd=$(mktemp -d) || return 1
    : > "${tmpd}/chain.pem"
    if _splitPemBundle "$src" "$tmpd"; then
        for f in "$tmpd"/c*.pem; do
            [[ -f $f ]] || continue
            subj=$(openssl x509 -in "$f" -noout -subject 2>/dev/null)
            issuer=$(openssl x509 -in "$f" -noout -issuer 2>/dev/null)
            [[ -z $subj ]] && continue
            [[ ${subj#subject=} == "${issuer#issuer=}" ]] && continue
            cat "$f" >> "${tmpd}/chain.pem"
        done
    fi
    if [[ -s ${tmpd}/chain.pem ]]; then
        # Mode, not ownership: the installer is already root, so the file is
        # root-owned either way -- and forcing it would make this function
        # untestable outside a root shell for no gain.
        if install -m 0644 "${tmpd}/chain.pem" "$out" >>$error_log 2>&1; then
            PKI_web_trust_chain="$out"
            st=0
        fi
    fi
    rm -rf "$tmpd" >>$error_log 2>&1
    return $st
}
# The optional third name: the intermediates for a leaf you brought.
#
# Echoes the path when there is one, and returns 1 when there is not. Optional
# where the pair is required, because a leaf signed straight off a root needs no
# intermediates and demanding one would refuse a valid setup.
_customPkiChain() {
    local f="$(_customPkiDir)/web-leaf-chain.pem"
    [[ -f $f ]] || return 1
    printf '%s' "$f"
}
_customPkiPair() {
    local dir cert key
    dir="$(_customPkiDir)"
    cert="${dir}/web-leaf.pem"
    key="${dir}/web-leaf.key"
    _certKeyPairMatches "$cert" "$key" || return 1
    echo "$cert"
    echo "$key"
}
# Single source of truth for the PKI layout, one directory per zone under
# _pkiRootDir, each split by its callers into ca/ (the zone's own CA) and leaf/
# (what that CA issues for this server to serve/sign with).
# Independent of ${PKI_client_cert_dir} -- unlike ${PKI_client_cert_dir}, which also holds admin-uploaded
# snapin SSL material and the client-communication leaf, this tree holds only
# FOG's own PKI, so it can move without dragging that other content along.
# Which is exactly what it did: the tree lives at /etc/fog/pki now, reachable
# at its historic $fogprogramdir/pki name through a symlink.
_pkiZoneDir() {
    local root
    case "$1" in
        root|web|client|secureboot|agent) root="$(_pkiRootDir)" ;;
        *) return 0 ;;
    esac
    echo "${root}/$1"
}
# The shared openssl configuration both issuing zones read.
#
# req.cnf (the CSR's subject and SANs) and ca.cnf (the v3 extensions written
# into a signed certificate) are NOT client-zone material, even though they
# lived in $snapindir/ssl next to the comm keypair. Each is read by the client
# comm leaf AND the web leaf, and by packages/pki/renewal-helper -- one name set,
# so the two zones can never disagree about which names this server answers to.
# Putting them under a zone would make one zone's directory a dependency of
# another's issuance.
#
# Deliberately not a zone token in _pkiZoneDir: conf/ holds no keys and no
# certificates, so giving it the ca/+leaf/ shape the zones have would say
# something false about it.
_pkiConfDir() {
    echo "$(_pkiRootDir)/conf"
}
# The client zone's leaf directory, and the compatibility links that keep the
# canonical names working.
#
# The comm keypair used to live directly in ${PKI_client_cert_dir}, i.e.
# $snapindir/ssl -- the same directory an admin edits to change snapin SSL, and
# the directory the snapin replicator walks. So "change the snapin certificates"
# and "replace the one keypair every registered client pins" were the same
# operation on the same directory, and the second one is invisible until hosts
# stop checking in.
#
# The real files move into the client zone. What stays behind at the canonical
# names is a SYMLINK per file, because the names are not free: FOGBase's
# _decryptCheck() builds `<sslpath>/.srvprivate.key` with the filename
# hardcoded, taking the directory from the storage-node record rather than from
# .fogsettings, so the path has to keep resolving. Per file and not a directory
# symlink on purpose -- symlinking the whole directory would put snapin uploads
# straight back beside the keypair, which is the thing being fixed.
#
# The rest of what used to sit in that directory moves too, but not all into
# this zone -- see _relocatePkiConf. Only the legacy CA/ tree stays behind: the
# root certificate genuinely lives there (pki/root/ca/.fogCA.pem is a symlink to
# it) and the web UI reads it to report offline-key state.
_resolveClientLeafPaths() {
    local leafdir f
    leafdir="$(_pkiZoneDir client)/leaf"
    mkdir -p "$leafdir" >>$error_log 2>&1
    # 0710 root:${apacheuser}, NOT the 0700 root:root the other zones' leaf dirs
    # get. The web tier must be able to TRAVERSE this to read the private key --
    # certDecrypt() opens it on every fog-client handshake -- and 0700 here
    # fails that as `Private key not readable`, per client, with nothing naming
    # this directory. Traverse only: 0710 grants no listing.
    chown root:${apacheuser} "$leafdir" >>$error_log 2>&1
    chmod 0710 "$leafdir" >>$error_log 2>&1
    # Migrate a REAL file only. A symlink here is either FOG's own compat link
    # from a previous run (already migrated, nothing to do) or an admin pointing
    # at their own file, which _separateCommKey settles first and which must not
    # be dragged into the zone as a link.
    for f in .srvprivate.key .srvpublic.crt; do
        [[ -f "${PKI_client_cert_dir}/${f}" && ! -L "${PKI_client_cert_dir}/${f}" \
            && ! -e "${leafdir}/${f}" ]] \
            && mv "${PKI_client_cert_dir}/${f}" "${leafdir}/${f}" >>$error_log 2>&1
    done
    PKI_client_encrypt_key="$(_clientLeafTarget "${PKI_client_encrypt_key}" \
        "${leafdir}/.srvprivate.key" "${PKI_client_cert_dir}/.srvprivate.key")"
    PKI_client_encrypt_cert="$(_clientLeafTarget "${PKI_client_encrypt_cert}" \
        "${leafdir}/.srvpublic.crt" "${PKI_client_cert_dir}/.srvpublic.crt")"
    # The comm leaf's own CSR belongs with the leaf it requested.
    [[ -f "${PKI_client_cert_dir}/fog.csr" && ! -e "${leafdir}/fog.csr" ]] \
        && mv "${PKI_client_cert_dir}/fog.csr" "${leafdir}/fog.csr" >>$error_log 2>&1
    return 0
}
# Move the shared openssl config, and the web server's DH parameters, out of
# $snapindir/ssl.
#
# No compatibility symlinks for these, unlike the keypair, and the difference is
# the point: nothing outside FOG's own code reads them. The keypair's canonical
# names are baked into FOGBase, so they had to keep resolving; these three are
# named only by this installer, by packages/pki/renewal-helper (updated with
# them) and by the vhost this installer writes. A symlink would be a second name
# for a file with one reader.
#
# The CONTENTS of ca.cnf are unchanged by moving it, which matters more than it
# looks: _createWebLeaf stamps .webLeaf.sans with a hash of this file to decide
# whether the web leaf's name set changed. Moving the file without touching its
# bytes leaves that stamp valid, so no server re-issues its web certificate over
# this.
_relocatePkiConf() {
    local confdir webdir f
    confdir="$(_pkiConfDir)"
    webdir="$(_pkiZoneDir web)"
    mkdir -p "$confdir" "$webdir" >>$error_log 2>&1
    # Readable: openssl reads these as root, but the renewal helper and an admin
    # inspecting the name set do too, and they hold no secrets.
    chmod 0755 "$confdir" >>$error_log 2>&1
    for f in req.cnf ca.cnf; do
        [[ -f "${PKI_client_cert_dir}/${f}" && ! -e "${confdir}/${f}" ]] \
            && mv "${PKI_client_cert_dir}/${f}" "${confdir}/${f}" >>$error_log 2>&1
        [[ -f "${confdir}/${f}" ]] && chmod 0644 "${confdir}/${f}" >>$error_log 2>&1
    done
    # dhparam.pem is web-server TLS parameters -- the nginx vhost names it
    # directly -- so it belongs in the web zone rather than beside the snapins.
    [[ -f "${PKI_client_cert_dir}/dhparam.pem" && ! -e "${webdir}/dhparam.pem" ]] \
        && mv "${PKI_client_cert_dir}/dhparam.pem" "${webdir}/dhparam.pem" >>$error_log 2>&1
    return 0
}
# Where one half of the comm keypair actually lives: FOG's own zone path, or a
# file the admin named with --client-cert/--client-key.
#
# $1 the current record, $2 the zone path, $3 the historic canonical name.
#
# The awkward case is that an UPGRADED server's record holds $3 -- the previous
# version recorded the snapin-dir path -- and $3 is outside the zone, so a plain
# "outside the zone means the admin's" test would declare every upgraded server
# externally managed and never migrate anything. $3 is one of FOG's own names,
# so it is answered with the zone path, exactly as _resolveWebLeafPaths answers
# its own pre-separation and old-flat-layout paths.
#
# Anything else that exists is the admin's file and is returned untouched; the
# canonical name is then symlinked at it, which is what "point FOG at your own
# file" means in this zone. A recorded path that no longer exists falls back to
# the zone rather than being trusted -- the file was removed, and FOG issuing
# into its own zone is recoverable where a dangling record is not.
_clientLeafTarget() {
    local record="$1" zonepath="$2" canon="$3" rr
    [[ -z $record ]] && { echo "$zonepath"; return 0; }
    rr="$(readlink -f "$record" 2>/dev/null)"
    [[ $record == "$zonepath" || $record == "$canon" ]] && { echo "$zonepath"; return 0; }
    [[ -n $rr && ( $rr == "$(readlink -f "$zonepath" 2>/dev/null)" \
        || $rr == "$(readlink -f "$canon" 2>/dev/null)" ) ]] && { echo "$zonepath"; return 0; }
    [[ -f $record ]] && { echo "$record"; return 0; }
    echo "$zonepath"
}
# The compat links themselves, made once the files they point at exist. Split
# from _resolveClientLeafPaths because on a fresh install the keypair does not
# exist yet when the paths have to be settled -- _linkCanonical is a no-op for a
# target that is not there, so linking early would silently link nothing.
_linkClientLeafCompat() {
    mkdir -p "${PKI_client_cert_dir}" >>$error_log 2>&1
    _linkCanonical "${PKI_client_encrypt_key}" "${PKI_client_cert_dir}/.srvprivate.key"
    _linkCanonical "${PKI_client_encrypt_cert}" "${PKI_client_cert_dir}/.srvpublic.crt"
}
# pki/root/leaf/ held discoverability symlinks to the comm keypair, from when
# its real files lived in $snapindir/ssl and the root zone was the only zone
# without a leaf/. The keypair has its own zone now, so that directory would be
# a second set of links pointing at the first -- two indirections to the same
# file and nothing reading either.
#
# Only ever unlinks a SYMLINK, so a real file somebody else put there survives
# and the rmdir then fails harmlessly rather than taking it. Finding nothing
# there is the normal case, on a fresh install and on any server installed after
# this landed.
_retireRootLeafLinks() {
    local rootLeafDir f
    rootLeafDir="$(_pkiZoneDir root)/leaf"
    [[ -d $rootLeafDir ]] || return 0
    for f in .srvprivate.key .srvpublic.crt; do
        [[ -L "${rootLeafDir}/${f}" ]] && rm -f "${rootLeafDir}/${f}" >>$error_log 2>&1
    done
    rmdir "$rootLeafDir" >/dev/null 2>&1
    return 0
}
# Whether the certificate this server serves is managed OUTSIDE FOG.
#
# GH-1120 retired $acmeLeaf/$webCertFile/$webKeyFile and asks the filesystem
# instead. ${PKI_web_vhost_cert} is a CANONICAL path: on an ordinary install it
# resolves inside the web zone dir, and when the leaf is managed elsewhere --
# certbot, acme.sh, step-ca, a corporate issuance process, a purchased cert --
# it resolves outside it, because that is what pointing FOG at your certificate
# means now.
#
# This is the same fact those three keys carried between them, without their two
# failure modes: a persisted "yes" that nothing ever re-checked, and a pair of
# recorded paths that could silently disagree with the vhost. A symlink cannot
# disagree with itself.
#
# Returns 1 (FOG-managed) when the path is unset or unresolvable. That is the
# safe direction: it means FOG goes on managing its own leaf, which is what a
# fresh install needs.
_externallyManagedLeaf() {
    local target zonedir
    [[ -n ${PKI_web_vhost_cert} ]] || return 1
    target="$(readlink -f "${PKI_web_vhost_cert}" 2>/dev/null)"
    [[ -n $target ]] || return 1
    zonedir="$(readlink -f "$(_pkiZoneDir web)" 2>/dev/null)"
    [[ -n $zonedir ]] || return 1
    [[ $target == "$zonedir"/* ]] && return 1
    return 0
}
# ${PKI_client_cert_dir} is normally settled inside createSSLCA(), but the Secure Boot zone
# is reached from downloadfiles() before that runs, so both places have to be
# able to ask. Idempotent, and matches createSSLCA()'s own default exactly.
_resolveSslPath() {
    [[ -n ${PKI_client_cert_dir} ]] && { PKI_client_cert_dir=${PKI_client_cert_dir%/}; return 0; }
    PKI_client_cert_dir="${snapindir:-${fogprogramdir:-/opt/fog}/snapins}/ssl"
    PKI_client_cert_dir=${PKI_client_cert_dir%/}
}
# The permitted subtrees written into both intermediates' nameConstraints.
#
# Emitted as one comma-separated value rather than an @section: the single-line
# form is what the x509v3_config man page documents for nameConstraints, and
# this has to be right on firmware, not merely plausible.
#
# Three rules decide whether the result verifies at all, and each of them fails
# by rejecting a chain rather than by refusing to build:
#
#  1. OpenSSL wants an IP subtree as address/NETMASK, never address/prefixlen.
#     "10.0.0.0/8" is read as a malformed address and the extension does not
#     build.
#  2. RFC 5280 constrains only the name TYPES named in permittedSubtrees. A
#     certificate carrying an IP SAN under a DNS-only constraint is a
#     violation, so both types are always emitted or neither is.
#  3. IPv4 and IPv6 subtrees never match each other -- OpenSSL's nc_ip compares
#     lengths before addresses -- so a leaf with an IPv6 SAN needs an IPv6
#     subtree. Emitted only when this server actually has IPv6, so an admin
#     narrowing the CA to specific v4 subnets does not silently get the whole
#     unique-local range back.
#
# The names every web leaf carries, and the floor every Web/SB intermediate's
# nameConstraints must permit. Single source of truth: SAN generation
# (createSSLCA) and permitted-set generation (_nameConstraints, below) used to
# derive ${NET_hostname}/${PKI_san_dns_names} separately, a few hundred lines apart,
# and a new default added to only one of them would issue a leaf whose SAN
# carries a name its own CA doesn't permit -- signs cleanly, then fails every
# openssl verify, silently, until a client rejects it.
#
# fogserver/fog-server are DEFAULT names, not detected ones: they are the
# fog-client installer's default value for "FOG Server Address", so any
# admin who points that literal name at this box needs a leaf that covers
# it, whether or not they ever pass --extra-server-name.
#
# A bare (undotted) name paired with every configured --internal-domain gets
# an automatic FQDN form too, so an admin who types "fogdev" plus
# --internal-domain domain.com gets fogdev.domain.com without listing it
# twice. Already-dotted names (a detected FQDN hostname, or an admin-supplied
# FQDN) are left alone -- they're not paired again.
_defaultServerNames() {
    local -a names=() bases=()
    local seen="" n short dom fqdn

    for n in ${NET_hostname} fogserver fog-server ${PKI_san_dns_names}; do
        [[ -z $n ]] && continue
        [[ " $seen " == *" $n "* ]] && continue
        seen="$seen $n"
        names+=("$n")
        bases+=("$n")
    done
    short="${NET_hostname%%.*}"
    if [[ -n $short && " $seen " != *" $short "* ]]; then
        seen="$seen $short"
        names+=("$short")
        bases+=("$short")
    fi

    for n in "${bases[@]}"; do
        [[ $n == *.* ]] && continue
        for dom in ${PKI_allowed_domain_names}; do
            [[ -z $dom ]] && continue
            fqdn="${n}.${dom}"
            [[ " $seen " == *" $fqdn "* ]] && continue
            seen="$seen $fqdn"
            names+=("$fqdn")
        done
    done
    printf '%s\n' "${names[@]}"
}
# A DNS base matches itself and anything to its left ("example.com" permits
# "fog.example.com"), so bare domains are emitted rather than dotted ones.
_nameConstraints() {
    local -a dnsnames=() ipnets=()
    local n d ip cidr mask entry seen out=""

    # This server's own names, so a certificate this CA issues for another
    # host in the same domain -- a storage node -- verifies. Derived from
    # _defaultServerNames() so the permitted set can never drift from the
    # leaf's own SAN (see that function's comment). ${PKI_allowed_domain_names} is
    # added separately below, as a bare domain grant rather than a name.
    for n in $(_defaultServerNames); do
        dnsnames+=("$n")
        d="${n#*.}"
        [[ $d != "$n" && -n $d ]] && dnsnames+=("$d")
    done
    for n in ${PKI_allowed_domain_names}; do
        [[ -z $n ]] && continue
        dnsnames+=("$n")
    done
    # No DNS name to permit means no usable constraint: every leaf here carries
    # a DNS SAN, and an IP-only permitted set would reject all of them. Better
    # to issue an unconstrained CA than one that cannot sign anything.
    if [[ ${#dnsnames[@]} -eq 0 ]]; then
        echo ""
        return 0
    fi

    if [[ -n ${PKI_internal_subnets} ]]; then
        # An explicit list REPLACES the RFC1918 default rather than adding to
        # it -- an admin naming their own subnets means those instead of the
        # broad grant, or the flag would not narrow anything.
        for entry in ${PKI_internal_subnets}; do
            ip="${entry%%/*}"
            cidr="${entry#*/}"
            if [[ $cidr == "$entry" ]]; then
                mask="255.255.255.255"
            elif [[ $cidr =~ ^[0-9]+$ ]]; then
                mask=$(cidr2mask "$cidr" 2>/dev/null)
            else
                mask="$cidr"
            fi
            [[ -z $mask ]] && continue
            ipnets+=("${ip}/${mask}")
        done
    else
        ipnets+=("10.0.0.0/255.0.0.0")
        ipnets+=("172.16.0.0/255.240.0.0")
        ipnets+=("192.168.0.0/255.255.0.0")
    fi
    # This server's own addresses, always, whatever the admin listed. The web
    # leaf carries every one of them as a SAN, so omitting any would make the
    # CA unable to sign the certificate it exists to sign -- and an admin who
    # mistypes --internal-subnet would find that out from a dead web server.
    ipnets+=("127.0.0.0/255.0.0.0")
    for ip in ${PKI_san_ip_addresses}; do
        [[ $ip == *:* ]] && continue
        ipnets+=("${ip}/255.255.255.255")
    done
    if [[ ${PKI_san_ip_addresses} == *:* ]]; then
        ipnets+=("::1/ffff:ffff:ffff:ffff:ffff:ffff:ffff:ffff")
        ipnets+=("fc00::/fe00::")
        ipnets+=("fe80::/ffc0::")
    fi

    seen=""
    for n in "${dnsnames[@]}"; do
        [[ " $seen " == *" DNS:$n "* ]] && continue
        seen="$seen DNS:$n"
        out="${out},permitted;DNS:${n}"
    done
    for n in "${ipnets[@]}"; do
        [[ " $seen " == *" IP:$n "* ]] && continue
        seen="$seen IP:$n"
        out="${out},permitted;IP:${n}"
    done
    echo "nameConstraints = critical${out}"
}
# The Secure Boot zone carries NO name constraints, and there is deliberately no
# key or flag to add them (GH-1120 retired $sbNameConstraints and
# --no-sb-name-constraints).
#
# They constrained nothing that matters for code signing -- a code-signing leaf
# carries no names anyone resolves -- while this is the one certificate UEFI and
# shim actually parse, and a critical extension they mishandle costs a firmware
# trip to every machine. An opt-out flag was the wrong shape for that risk: it
# put the safe answer behind a flag nobody passes until a fleet has already
# failed to boot.
#
# _nameConstraints() is untouched and still serves the Web CA, where ADR 0016
# made constraints enforceable by patching iPXE. The distinction is that iPXE is
# a verifier FOG can patch and UEFI firmware is not.
#
# Existing installs keep whatever their .fogSBCA.pem already carries: an
# intermediate is never re-minted (the caller gates on [[ ! -f ]]), so nothing
# here needs migrating.
# Can the root at ${PKI_root_ca_cert} actually anchor an intermediate?
#
# Reachable in practice: a server built with --external-ca against a sub-CA
# their enterprise issued with pathlen:0, which is a perfectly ordinary thing
# for an enterprise PKI to hand out. Signing beneath it would succeed and then
# fail verification on every client, which is the worst of both outcomes -- so
# detect it and leave the server exactly as it is instead.
_rootCACanIssue() {
    rootCAIssuer=1
    rootCAIssuerWhy=""
    local bc
    bc=$(openssl x509 -in "${PKI_root_ca_cert}" -noout -ext basicConstraints 2>/dev/null)
    # Older OpenSSL has no -ext (some Alpine builds); isCACert() hits the same
    # wall and falls back the same way.
    [[ -z $bc ]] && bc=$(openssl x509 -in "${PKI_root_ca_cert}" -noout -text 2>/dev/null | grep -A1 -i "Basic Constraints")
    # No basicConstraints at all is not a failure: a v1 or extension-less
    # self-signed root is still treated as a CA when it is the trust anchor,
    # and FOG generated exactly that for years.
    [[ -z $bc ]] && return 0
    if [[ $bc == *"CA:FALSE"* || $bc == *"CA:false"* ]]; then
        rootCAIssuer=0
        rootCAIssuerWhy="it is not a CA certificate (basicConstraints CA:FALSE)"
    elif [[ $bc == *"pathlen:0"* ]]; then
        rootCAIssuer=0
        rootCAIssuerWhy="it carries pathlen:0, which forbids any CA beneath it"
    fi
    return 0
}
# Settle which certificate is this server's trust anchor.
#
# Deliberately NOT derived from ${PKI_web_ca_cert}. That variable names "the CA that
# signs the vhost leaf", which after this change is the Web intermediate --
# reading the root from it would, on the second run, mistake the intermediate
# for the root and issue a second generation of intermediates beneath it.
#
# The canonical path is where every existing install already keeps its root,
# generated or imported, so an upgrade finds it without being told.
#
# The CERTIFICATE is what defines "this root exists". The key may be
# legitimately absent -- that is what an offline root IS. Testing for both
# would mint a fresh root the first time an admin took that advice, orphaning
# every intermediate already issued and every client that pinned it, silently,
# on an ordinary update.
#
# Path resolution only -- no creation. Split out of _resolveRootCA() so
# something (_collectPkiNames) can ask "does a root exist yet" without
# triggering the mint that _resolveRootCA() does the moment it finds one
# missing.
_resolveRootCAPath() {
    _resolveSslPath
    [[ ! -d ${PKI_client_cert_dir}/CA ]] && mkdir -p "${PKI_client_cert_dir}/CA" >>$error_log 2>&1

    if [[ -z ${PKI_root_ca_cert} ]]; then
        if [[ -f ${PKI_client_cert_dir}/CA/.fogCA.pem ]]; then
            PKI_root_ca_cert="${PKI_client_cert_dir}/CA/.fogCA.pem"
            PKI_root_ca_key="${PKI_client_cert_dir}/CA/.fogCA.key"
        elif [[ -n ${PKI_web_ca_cert} && -f ${PKI_web_ca_cert} ]]; then
            # A pre-existing install whose admin relocated the CA before the
            # canonical symlink existed to follow.
            PKI_root_ca_cert="${PKI_web_ca_cert}"
            PKI_root_ca_key="${PKI_web_ca_key}"
        else
            PKI_root_ca_cert="${PKI_client_cert_dir}/CA/.fogCA.pem"
            PKI_root_ca_key="${PKI_client_cert_dir}/CA/.fogCA.key"
        fi
    fi
    [[ -z ${PKI_root_ca_key} ]] && PKI_root_ca_key="${PKI_root_ca_cert%.pem}.key"
}
_resolveRootCA() {
    _resolveRootCAPath

    # The private key moves out of ${PKI_client_cert_dir} -- that tree is shared with
    # admin-uploaded snapin SSL material and the client-communication leaf,
    # which have no reason to sit next to the one key that can mint a new CA.
    # ${PKI_root_ca_cert} is left exactly where _resolveRootCAPath found it: an
    # existing install already has things pointing at it (fog-client's
    # pinned root), and moving a PUBLIC certificate buys nothing a symlink
    # doesn't already give. One-time and idempotent: once the key exists at
    # the new path, every later call just re-points ${PKI_root_ca_key} at it without
    # touching the filesystem again.
    local cadir="$(_pkiZoneDir root)/ca"
    mkdir -p "$cadir" >>$error_log 2>&1
    chmod 0700 "$cadir" >>$error_log 2>&1
    local canonicalRootKey="${cadir}/.fogCA.key"
    if [[ ${PKI_root_ca_key} != "$canonicalRootKey" ]]; then
        [[ ! -f $canonicalRootKey && -f ${PKI_root_ca_key} ]] && \
            mv "${PKI_root_ca_key}" "$canonicalRootKey" >>$error_log 2>&1
        PKI_root_ca_key="$canonicalRootKey"
    fi
    _linkCanonical "${PKI_root_ca_cert}" "${cadir}/.fogCA.pem"

    if [[ $recreateCA == yes ]]; then
        # Explicit and destructive. Everything beneath the old root is orphaned
        # by definition, so the intermediates go too and get re-issued below --
        # leaving them would produce chains that verify against nothing.
        rm -f "${PKI_root_ca_cert}" "${PKI_root_ca_key}" >>$error_log 2>&1
        rm -rf "$(_pkiZoneDir web)" "$(_pkiZoneDir secureboot)" >>$error_log 2>&1
    fi

    if [[ -f ${PKI_root_ca_cert} ]]; then
        [[ -f ${PKI_root_ca_key} ]] || rootCAKeyOffline=1
        _rootCACanIssue
        return 0
    fi

    dots "Creating FOG Server CA"
    cat > "${PKI_client_cert_dir}/CA/root.cnf" << EOF
[ req ]
distinguished_name = req_dn
prompt             = no
x509_extensions    = v3_root

[ req_dn ]
CN = FOG Server CA
O  = FOG Project
OU = FOG Root CA

[ v3_root ]
basicConstraints = critical,CA:TRUE
keyUsage         = critical,keyCertSign,cRLSign
subjectKeyIdentifier = hash
EOF
    # Written explicitly rather than relying on the distro openssl.cnf's v3_ca
    # section, which is where the historic `req -x509` with no -config took its
    # extensions from. That worked, but it made whether this root can carry
    # intermediates a property of the distro rather than of FOG.
    #
    # No pathlen: the Web and Secure Boot intermediates sit directly beneath
    # this, and a node's leaf sits beneath those.
    #
    # 30 years: a CA isn't something an out-of-the-box install should ever
    # need to think about renewing. Renewing it means re-issuing every
    # intermediate beneath it and re-pinning every client -- nothing like the
    # cheap, routine rotation a leaf gets.
    openssl req -x509 -new -sha512 -nodes -newkey rsa:4096 -days 10950 \
        -config "${PKI_client_cert_dir}/CA/root.cnf" -keyout "${PKI_root_ca_key}" -out "${PKI_root_ca_cert}" \
        >>$error_log 2>&1
    local st=$?
    chmod 0600 "${PKI_root_ca_key}" >>$error_log 2>&1
    chmod 0644 "${PKI_root_ca_cert}" >>$error_log 2>&1
    errorStat $st
    _rootCACanIssue
}
# Asked once, whichever entry point reaches it first -- Secure Boot's or the
# web zone's -- because a CA's name constraints are fixed at the moment it is
# minted and widening them later means the admin has to notice and ask for it
# explicitly (rm -rf the CA directory, re-run). Skipped entirely once every
# CA this run could mint already exists, and skipped if the admin already
# answered on the command line: --extra-server-name/--internal-domain ARE
# the answer to this question, just given up front instead of interactively.
#
# Bounded to 3 minutes under -Y/--autoaccept: that flag exists for unattended
# runs, and a prompt nobody is there to answer must not hang the install
# forever. A normal interactive run waits like every other prompt in this
# installer.
_collectPkiNames() {
    [[ -n $_pkiNamesCollected ]] && return 0
    _pkiNamesCollected=1
    _resolveRootCAPath
    local needRoot=0 needWeb=0 needSB=0
    [[ ! -f ${PKI_root_ca_cert} ]] && needRoot=1
    [[ ! -f "$(_pkiZoneDir web)/ca/.fogWebCA.pem" ]] && needWeb=1
    # Not conditional on ${PKI_sb_enabled}: that flag declines ENROLLMENT, not signing,
    # so the Secure Boot CA is minted on every server. See _ensureSecureBootKeys.
    [[ ! -f "$(_pkiZoneDir secureboot)/ca/.fogSBCA.pem" ]] && needSB=1
    [[ $needRoot -eq 0 && $needWeb -eq 0 && $needSB -eq 0 ]] && return 0
    [[ -n ${PKI_san_dns_names} || -n ${PKI_allowed_domain_names} ]] && return 0

    echo
    echo "  This run will mint a new FOG PKI CA. A CA's name constraints are"
    echo "  fixed at the moment it's issued -- widening them later means"
    echo "  re-issuing it (rm -rf the CA directory, then re-run)."
    local ans domainAns
    if [[ -n $autoaccept ]]; then
        echo "  Extra hostnames for this server, space-separated (3 min, blank = none):"
        read -t 180 -r -p "  > " ans
        echo "  Internal domain, e.g. example.local (3 min, blank = none):"
        read -t 180 -r -p "  > " domainAns
    else
        read -r -p "  Extra hostnames for this server, space-separated (blank = none): " ans
        read -r -p "  Internal domain, e.g. example.local (blank = none): " domainAns
    fi
    [[ -n $ans ]] && PKI_san_dns_names="${PKI_san_dns_names} ${ans}"
    [[ -n $domainAns ]] && PKI_allowed_domain_names="${PKI_allowed_domain_names} ${domainAns}"
    PKI_san_dns_names="${PKI_san_dns_names# }"
    PKI_allowed_domain_names="${PKI_allowed_domain_names# }"
}
# Which protocol iPXE uses to reach boot.php, decided separately from the
# protocol everything else uses.
#
# iPXE can only validate a chain that terminates in a PUBLIC root, via its
# ca.ipxe.org cross-signing fallback. A FOG-PKI or internal-CA certificate is
# perfectly good for browsers, the API and fog-client -- all of which can be
# told to trust the root -- but iPXE has no way to be told anything, so an
# HTTPS netboot against a private CA simply fails.
#
# The historic answer was to rebuild iPXE with the CA baked in (TRUST=), which
# works and costs the signed Secure Boot shim, because a locally rebuilt binary
# is not the signed one. Splitting the protocols avoids that trade entirely:
# the web UI gets real HTTPS while netboot fetches stay on HTTP, which is the
# same exposure a default HTTP install already has, on a pre-boot network.
#
# There are exactly two ways HTTPS netboot can work, and both are now stated
# rather than guessed at:
#
#   PKI_web_cert_publicly_trusted=yes        iPXE's crosscert path validates a public root.
#   BOOT_rebuild_ipxe_with_my_ca=yes  the CA is compiled into the binary.
#
# So netboot defaults to HTTP and is steered to HTTPS by either of those, and
# nothing else. An explicit --netboot-proto always wins, in either direction.
#
# This replaced a test keyed on the old $caCreated key (retired in GH-1120),
# which was a trap rather than a bug while httpproto defaulted to http: it was a
# PERSISTED key, so it was
# "yes" on every re-run of an existing server. The moment httpproto defaults to
# https -- which it now does, for everyone -- that old test resolved
# BOOT_url_proto=https on every upgraded install in existence, which is precisely
# the configuration that cannot work behind a private CA. Keying on what the
# admin actually declared removes the whole class.
# The one place an admin is asked how this server should handle TLS, netboot
# and the iPXE build -- shown together, with what each costs.
#
# This replaces "would you like to enable secure HTTPS on your FOG server?",
# which asked about ${WEB_url_proto} and silently also decided whether Secure Boot
# binaries were staged and whether iPXE was rebuilt. Four named modes are
# honest about a four-dimensional choice in a way one yes/no cannot be.
#
# A preset, not a replacement for the model: it writes the same
# --public-web-cert/--rebuild-ipxe-with-my-ca keys those flags write, and an
# admin who passed any of them (or --install-mode) is not asked at all -- they
# have already answered. It does NOT touch WEB_https_redirect, despite the
# --https-redirect shadow being one of the flags checked below; no mode sets or
# clears the redirect, which is seeded once from a pre-1.6 httpproto=https and
# is the admin's from then on.
#
# ASKED ONCE, which is what $priorInstall enforces. Everything else here is
# run-scoped -- the s* shadows are this run's flags -- so before that line there
# was nothing in the guard that could remember an answer, and every interactive
# upgrade got the menu again. That was not merely repetitive: any unrecognized
# reply including a bare Enter takes the `standard` default below, and
# _applyInstallMode then wrote it straight over a public-cert or embed-ca
# server's keys, which writeUpdateFile persisted. The prompt reverted the very
# choice it was asking about.
#
# $priorInstall rather than $doupdate: the question is "has this machine ever
# had FOG", and $doupdate answers a different one -- it is 0 for --no-upgrade on
# a server that has been running for years. A machine that HAS had FOG has
# either a persisted ${FOG_install_mode} (seeded back into $sinstallMode in
# installfog.sh, so the first guard above has already returned) or a shape built
# from discrete flags, and neither is something to re-ask.
#
# Guarded on `! -t 0` as well as $autoaccept, following the schema-update prompt
# in this file: a piped or cron-driven install has no one to answer, and a read
# there returns instantly with empty input rather than blocking.
promptInstallMode() {
    [[ -n $sinstallMode ]] && return 0
    [[ -n ${sWEB_https_redirect} || -n ${sPKI_web_cert_publicly_trusted} || -n ${sBOOT_rebuild_ipxe_with_my_ca} ]] && return 0
    [[ ${priorInstall:-0} -eq 1 ]] && return 0
    [[ -n $autoaccept || ! -t 0 ]] && return 0

    local answer=""
    echo
    echo " * How should this server handle HTTPS, netboot and Secure Boot?"
    echo
    echo "   1) standard     (default) HTTPS web UI and API, netboot over HTTP."
    echo "                   Secure Boot binaries staged. No redirect, no rebuild."
    echo "                   Right for almost everyone, including FOG's own CA."
    echo
    echo "   2) http-only    Plain HTTP everywhere. Simplest, and what FOG did"
    echo "                   before 1.6."
    echo
    echo "   3) public-cert  Your web certificate chains to a PUBLIC root (Let's"
    echo "                   Encrypt, a commercial CA). Netboot can then use"
    echo "                   HTTPS with no rebuild, because iPXE cross-certifies"
    echo "                   public roots on its own. Needs an FQDN, not an IP."
    echo
    echo "   4) embed-ca     Rebuild iPXE with your own CA compiled in, so"
    echo "                   netboot can use HTTPS behind a private CA."
    echo "                   CAUTION: adds 10-25 minutes to this install AND to"
    echo "                   every future update, with no warm path. The result"
    echo "                   is not upstream's signed binary, so each machine"
    echo "                   needs this server's MOK enrolled BEFORE it can"
    echo "                   netboot at all. Most sites want 1 or 3 instead."
    echo
    read -p " * Choose 1-4, or press Enter for standard: " answer
    case $answer in
        2|http-only)   sinstallMode="http-only" ;;
        3|public-cert) sinstallMode="public-cert" ;;
        4|embed-ca)    sinstallMode="embed-ca" ;;
        # Anything else, including empty and a typo, takes the safe default.
        # There is no wrong answer to re-ask for here: standard is what an
        # admin who is not sure should get.
        *)             sinstallMode="standard" ;;
    esac
    _applyInstallMode
    # Persisted, so this is the last time the question gets asked.
    FOG_install_mode="$sinstallMode"
    echo
    echo " * Using install mode: $sinstallMode"
    echo "   web=${WEB_url_proto} netboot=${BOOT_url_proto:-http} redirect=${WEB_https_redirect:-no}"
    echo "   PKI_web_cert_publicly_trusted=${PKI_web_cert_publicly_trusted:-no} BOOT_rebuild_ipxe_with_my_ca=${BOOT_rebuild_ipxe_with_my_ca:-no}"
    echo
}
# The preset itself, factored out so installfog.sh's flag handling and the
# prompt above cannot drift apart.
_applyInstallMode() {
    case $sinstallMode in
        standard)
            WEB_url_proto="https"; BOOT_url_proto="http"; PKI_web_cert_publicly_trusted="no"; BOOT_rebuild_ipxe_with_my_ca="no"
            ;;
        http-only)
            WEB_url_proto="http"; BOOT_url_proto="http"; PKI_web_cert_publicly_trusted="no"; BOOT_rebuild_ipxe_with_my_ca="no"
            ;;
        public-cert)
            WEB_url_proto="https"; BOOT_url_proto="https"; PKI_web_cert_publicly_trusted="yes"; BOOT_rebuild_ipxe_with_my_ca="no"
            ;;
        embed-ca)
            WEB_url_proto="https"; BOOT_url_proto="https"; PKI_web_cert_publicly_trusted="no"; BOOT_rebuild_ipxe_with_my_ca="yes"
            ;;
    esac
}
_resolveNetbootProto() {
    # An explicit --netboot-proto wins, and is REMEMBERED as explicit so a later
    # run without the flag goes on honoring it.
    #
    # That marker is the fix. This used to return early on any non-empty
    # ${BOOT_url_proto} -- and ${BOOT_url_proto} is persisted, so a value this function
    # DERIVED on one run was indistinguishable from one an admin forced, and
    # short-circuited every run afterward. The consequence was reported from a
    # live server: an install resolved http, wrote it down, and the admin then
    # declared PKI_web_cert_publicly_trusted="yes" and watched it be read and ignored, because
    # BOOT_url_proto=http was already in the file. Nothing said so -- the summary
    # reported HTTP netboot as though it had just decided that.
    if [[ -n ${sBOOT_url_proto} ]]; then
        BOOT_url_proto="${sBOOT_url_proto}"
        BOOT_url_proto_forced="yes"
        return 0
    fi
    [[ ${BOOT_url_proto_forced} == yes && -n ${BOOT_url_proto} ]] && return 0
    # Otherwise DERIVE, every run. Re-deriving is the point rather than a cost:
    # these two keys are exactly what an admin edits to change this answer, so
    # the answer has to follow them instead of outliving them.
    if [[ ${PKI_web_cert_publicly_trusted} == yes || ${BOOT_rebuild_ipxe_with_my_ca} == yes ]]; then
        BOOT_url_proto="https"
    else
        BOOT_url_proto="http"
    fi
}
# Say out loud what just got decided.
#
# _resolveNetbootProto used to emit nothing at all -- no dots, no echo -- so an
# admin who asked for HTTPS and got HTTP netboot had no way to learn that from
# the install, and the divergence only surfaced later as "why is my PXE traffic
# in the clear". This is the user-facing half of the whole change: the point of
# splitting the protocols is that the admin gets to choose, and a choice nobody
# is told about is not one.
#
# Printed after the vhost is settled, not inside _resolveNetbootProto, because
# that runs from configureDefaultiPXEfile() in the middle of writing a file and
# has no business owning several lines of output.
_reportNetbootProto() {
    if [[ ${BOOT_url_proto} == https ]]; then
        # Legal, and worth saying: forcing HTTPS netboot with neither of the
        # two things that make it work is the one combination that produces a
        # server which looks configured and cannot boot a client. Warned, not
        # refused -- an admin may have arranged trust some way FOG cannot see.
        if [[ ${PKI_web_cert_publicly_trusted} != yes && ${BOOT_rebuild_ipxe_with_my_ca} != yes ]]; then
            echo
            echo " ###################################################################"
            echo " # WARNING: netboot is set to HTTPS, but neither                   #"
            echo " # --public-web-cert nor --rebuild-ipxe-with-my-ca is set.         #"
            echo " #                                                                 #"
            echo " # iPXE cannot be told to trust a private CA. Unless this server's #"
            echo " # certificate chains to a PUBLIC root, or the iPXE binaries were  #"
            echo " # built elsewhere with your CA embedded, every PXE client will    #"
            echo " # fail at the TLS handshake with nothing logged on the server.    #"
            echo " #                                                                 #"
            echo " # If that is not what you meant: --netboot-proto http             #"
            echo " ###################################################################"
            echo
        fi
        return 0
    fi
    echo
    echo " * Netboot (PXE) is using HTTP, not HTTPS."
    if [[ ${WEB_url_proto} == https ]]; then
        echo "   Your web UI and API are HTTPS; only iPXE's own fetches are not."
    fi
    echo "   iPXE validates TLS strictly and cannot be told to trust a private"
    echo "   CA, so an HTTPS netboot against one simply fails. HTTP here is the"
    echo "   same exposure a default install has always had, on a pre-boot"
    echo "   network."
    echo
    echo " * Secure Boot binaries ARE staged on this server, in every mode."
    echo "   That used to be skipped on any HTTPS install. To enroll a machine,"
    echo "   boot it and choose 'Enroll Secure Boot Key' from the FOG menu."
    echo
    echo " * To move netboot onto HTTPS, tell FOG which is true:"
    echo "     --public-web-cert          your certificate chains to a public"
    echo "                                root (needs an FQDN, not an IP)"
    echo "     --rebuild-ipxe-with-my-ca  rebuild iPXE with your CA embedded"
    echo "                                (slow, and its MOK must be enrolled"
    echo "                                 before a client can netboot)"
    echo "   Or force it outright with --netboot-proto https."
    echo
}
# Issue an intermediate CA from the root. Shared by both zones so their
# certificates differ only in subject, location and constraints, never in
# shape. $5 carries the zone's own extension lines -- its extendedKeyUsage and,
# where it applies, its nameConstraints.
_issueIntermediateCA() {
    local cn="$1" outdir="$2" keyfile="$3" certfile="$4" extralines="$5" ou="$6" keyOfflineVar="$7"
    local st=0
    mkdir -p "$outdir" >>$error_log 2>&1 || st=1
    chmod 0700 "$outdir" >>$error_log 2>&1
    # The CERTIFICATE is what defines "this intermediate exists" -- same
    # reasoning as the root (_resolveRootCA). The key may be legitimately
    # offline (fog-offline-ca-key --zone secureboot): testing for both would
    # mint a fresh intermediate -- silently overwriting the one every
    # already-enrolled client trusts -- the moment an admin took that advice,
    # on the very next ordinary upgrade.
    if [[ -f "${outdir}/${certfile}" ]]; then
        [[ -f "${outdir}/${keyfile}" ]] || { [[ -n $keyOfflineVar ]] && printf -v "$keyOfflineVar" 1; }
        return 0
    fi
    # Issuing needs the root's private key. An existing intermediate returns
    # above without ever touching it, which is what makes an offline root
    # workable day to day -- but a NEW one cannot be signed without it.
    #
    # Say so instead of failing inside openssl with an unreadable-file error,
    # because the fix is a specific and slightly unusual action: bring the key
    # back, run the installer, take it away again.
    if [[ ${rootCAKeyOffline:-0} -eq 1 ]]; then
        echo "Failed"
        echo " * Cannot issue '${cn}': the Root CA private key is not on this"
        echo "   server (only ${PKI_root_ca_cert} is present)."
        echo " * That is the correct state for an offline root, but issuing a new"
        echo "   intermediate needs it. Restore it to:"
        echo "     ${PKI_root_ca_key}"
        echo "   re-run the installer, then move it back to your vault."
        return 1
    fi
    openssl genrsa -out "${outdir}/${keyfile}" 4096 >>$error_log 2>&1 || st=1
    # Written as a config file rather than passed with -addext: -addext needs
    # OpenSSL 1.1.1+, and the older RHEL variants this installer still supports
    # ship 1.0.2 where it fails with an unhelpful usage error. Same reason
    # createSSLCA() writes req.cnf/ca.cnf and _ensureSecureBootKeys writes
    # mok.cnf instead of using -addext.
    cat > "${outdir}/int.cnf" << EOF
[ req ]
distinguished_name = req_dn
prompt             = no

[ req_dn ]
CN = ${cn}
O  = FOG Project
OU = ${ou}

[ v3_int ]
basicConstraints = critical,CA:TRUE,pathlen:0
keyUsage         = critical,keyCertSign,cRLSign
subjectKeyIdentifier = hash
${extralines}
EOF
    openssl req -new -sha512 -key "${outdir}/${keyfile}" -out "${outdir}/int.csr" \
        -config "${outdir}/int.cnf" >>$error_log 2>&1 || st=1
    # 30 years, same reasoning as the root: an intermediate is a CA too, and
    # renewing it means re-issuing its own leaf and re-verifying every chain
    # beneath it, not a routine rotation.
    openssl x509 -req -in "${outdir}/int.csr" -CA "${PKI_root_ca_cert}" -CAkey "${PKI_root_ca_key}" \
        -CAcreateserial -sha512 -days 10950 -extensions v3_int \
        -extfile "${outdir}/int.cnf" -out "${outdir}/${certfile}" >>$error_log 2>&1 || st=1
    chmod 0600 "${outdir}/${keyfile}" >>$error_log 2>&1
    chmod 0644 "${outdir}/${certfile}" >>$error_log 2>&1
    return $st
}
# The agent zone: an intermediate that issues CLIENT certificates to
# fog-agent installs, through fog-sign-node-cert's agent type. Its own zone
# rather than a use of the Web CA for two reasons the web zone's own notes
# make clear: the Web CA may be one the admin brought (a public CA issues no
# client certificates at all), and an EKU on a CA bounds what it can issue,
# so clientAuth here means nothing from this zone can ever pose as a server
# however its leaf is written. Always minted under the FOG root -- it is what
# the vhost will be told to trust for client certificates, and it must be
# something this server holds.
createAgentIntermediateCA() {
    local agentdir cadir
    agentdir="$(_pkiZoneDir agent)"
    cadir="${agentdir}/ca"
    mkdir -p "$cadir" >>$error_log 2>&1
    chmod 0700 "$cadir" >>$error_log 2>&1
    PKI_agent_ca_key="${cadir}/.fogAgentCA.key"
    PKI_agent_ca_cert="${cadir}/.fogAgentCA.pem"
    if [[ ! -f ${PKI_agent_ca_cert} ]]; then
        dots "Creating FOG Agent CA"
        _issueIntermediateCA "FOG Agent CA" "$cadir" ".fogAgentCA.key" ".fogAgentCA.pem" \
            "extendedKeyUsage = clientAuth" "FOG Agent"
        errorStat $?
    fi
    # The trust file for VERIFYING agent certificates: the agent CA and the
    # root it chains to, public halves only, world-readable. Three readers:
    # the vhost (ssl_client_certificate / SSLCACertificateFile), PHP
    # (Agent\Principal re-verifies against it, see that class for why), and
    # the copy published under management/other for the same reason
    # ca.cert.pem is. Rewritten every run so it follows a re-minted CA.
    PKI_agent_ca_bundle="${agentdir}/agent-ca-bundle.pem"
    cat "${PKI_agent_ca_cert}" "${PKI_root_ca_cert}" > "${PKI_agent_ca_bundle}" 2>>$error_log
    chmod 0644 "${PKI_agent_ca_bundle}" >>$error_log 2>&1
}
# Did ${PKI_root_ca_cert} actually issue ${PKI_web_ca_cert}?
#
# The one question that separates a FOG-generated Web CA from one imported with
# --web-ca-cert, which no comparison of PATHS can answer -- the import lands on
# the same canonical filenames the generator uses. See the call site in
# createWebIntermediateCA for what regenerating the chain from the wrong root
# costs.
_rootIssuedWebCA() {
    [[ -n ${PKI_root_ca_cert} && -s ${PKI_root_ca_cert} ]] || return 1
    [[ -n ${PKI_web_ca_cert} && -s ${PKI_web_ca_cert} ]] || return 1
    openssl verify -trusted "${PKI_root_ca_cert}" "${PKI_web_ca_cert}" >/dev/null 2>&1
}
# The Web zone: an intermediate whose leaf is what the vhost serves. Replacing
# this zone has zero endpoint impact -- browsers just need the root trusted,
# and fog-client already trusts it, because the root is what it pins.
#
# ${PKI_web_ca_key}/${PKI_web_ca_cert} mean "the CA that signs the vhost leaf" -- which is what
# they have always meant, and what validateExternalCA sets. They are repointed
# at the intermediate here; the root stays in ${PKI_root_ca_cert}/${PKI_root_ca_key}.
createWebIntermediateCA() {
    local webdir cadir f
    webdir="$(_pkiZoneDir web)"
    cadir="${webdir}/ca"
    mkdir -p "$cadir" >>$error_log 2>&1
    chmod 0700 "$cadir" >>$error_log 2>&1
    # An install that already ran the flat pki/web layout (one level up from
    # here) migrates its CA material in place -- same key/cert, one more hop
    # -- rather than minting a fresh intermediate it doesn't need.
    for f in .fogWebCA.key .fogWebCA.pem .fogWebCAchain.pem int.cnf int.csr; do
        [[ -f "${webdir}/${f}" && ! -f "${cadir}/${f}" ]] && \
            mv "${webdir}/${f}" "${cadir}/${f}" >>$error_log 2>&1
    done
    PKI_web_ca_key="${cadir}/.fogWebCA.key"
    PKI_web_ca_cert="${cadir}/.fogWebCA.pem"
    if [[ ! -f ${PKI_web_ca_cert} ]]; then
        dots "Creating FOG Web CA"
        # serverAuth alone. An EKU on a CA constrains what it may issue, which
        # is the whole reason this zone is separable: a web certificate from
        # here can never be a code-signing certificate, whatever its leaf says.
        _issueIntermediateCA "FOG Web CA" "$cadir" ".fogWebCA.key" ".fogWebCA.pem" \
            "extendedKeyUsage = serverAuth
$(_nameConstraints)" "FOG Web UI"
        errorStat $?
    fi
    # Chain file is root+intermediate, the same concat shape validateExternalCA
    # already produces, because a client validating the leaf needs both. Only
    # (re)written when ${PKI_web_trust_chain} is empty or still one of the FOG-managed
    # defaults -- this one, the flat pki/web/.fogWebCAchain.pem path one
    # restructuring ago (just moved above, so a value still pointing there is
    # stale rather than an override), or ${PKI_root_ca_cert} from the pathlen:0
    # fallback in createSSLCA, in case an install switched between the two
    # across runs -- an admin who pointed it at their own chain (an ACME
    # client's --ca-file, say) has that choice honored on every later run,
    # the same guarantee _resolveWebLeafPaths already gives
    # sslprivkey/sslpubcert.
    if [[ -z ${PKI_web_trust_chain} || ${PKI_web_trust_chain} == "${cadir}/.fogWebCAchain.pem" \
        || ${PKI_web_trust_chain} == "${webdir}/.fogWebCAchain.pem" || ${PKI_web_trust_chain} == "${PKI_root_ca_cert}" ]]; then
        PKI_web_trust_chain="${cadir}/.fogWebCAchain.pem"
        # The root appended has to be the one that actually ISSUED
        # ${PKI_web_ca_cert}, and the path guard above cannot tell. Under
        # --web-ca-cert/--web-ca-key/--web-ca-root the Web CA was issued by
        # ANOTHER server's root, validateExternalCA imports to this exact
        # canonical path, and it deliberately leaves ${PKI_root_ca_cert}
        # pointing at THIS server's own root. So every path test says
        # "FOG-managed default, safe to regenerate" and the cat then replaces
        # the imported root with one that does not sign the intermediate above
        # it.
        #
        # Nothing complains at the time. The damage surfaces on the NEXT run,
        # because _resolveTrustAnchor reads its root back out of this file and
        # _resolveSelfCacert passes the result as --cacert, which REPLACES
        # curl's bundle -- so backupDB and updateDB both fail with "unable to
        # get local issuer certificate" while the served chain is perfectly
        # fine and every external check passes. That combination is what makes
        # it hard to place: the box can be verified correct from outside and
        # still be unable to verify itself.
        #
        # Checked as a property, not a path, for the same reason _rootFromChain
        # selects on self-signedness rather than on file order.
        #
        # The -s fallback keeps a fresh install working when there is no chain
        # on disk yet: a chain built from the wrong root is still better than
        # no chain at all, and that is the pre-existing behavior.
        if _rootIssuedWebCA || [[ ! -s ${PKI_web_trust_chain} ]]; then
            cat "${PKI_web_ca_cert}" "${PKI_root_ca_cert}" > "${PKI_web_trust_chain}" 2>>$error_log
            chmod 0644 "${PKI_web_trust_chain}" >>$error_log 2>&1
        fi
    fi
}
# The client communication certificate: the public half of the keypair
# fog-client encrypts to, and whose private half FOGBase::certDecrypt() opens.
#
# The keypair's CONTENTS never change: every registered client is already
# encrypting to its public half, so re-keying locks all of them out at once.
# Its LOCATION did move, into the client zone -- see _resolveClientLeafPaths for
# why $snapindir/ssl was the wrong home and what stays behind there. The vhost
# also stopped pointing at this certificate and serves a Web CA leaf instead.
#
# Two properties this has to hold that the historic code did not:
#
#  - The certificate lives OUTSIDE $webdirdest. configureHttpd rm -rf's that
#    tree on every run, and the historic copy under management/other/ssl was
#    therefore re-signed by the root every single time.
#  - It is minted ONCE. Re-signing needs the root's private key, so a server
#    whose admin has taken the root offline -- the end state this design
#    recommends -- would fail on its next update. Names do not matter here
#    (the client encrypts to the public key and never validates a hostname),
#    so there is nothing a re-issue would fix.
_createCommLeaf() {
    # GH-1120 promoted these from locals to managed keys. The client zone was
    # the only one an admin could not point elsewhere, and it holds the one
    # certificate every registered client pins -- the exception a model whose
    # premise is "say where the cert is" cannot have.
    #
    # They are RECORDS of a canonical path, re-derived every run, not controls.
    # Settled by _resolveClientLeafPaths, which createSSLCA calls before this --
    # re-derived here too so the function is safe to reach on its own, and
    # idempotent either way.
    _resolveClientLeafPaths

    # Already present: keep it, whoever issued it. This is also the supported
    # way to run a comm leaf issued OUTSIDE FOG -- drop the pair at
    # ${PKI_client_encrypt_cert} / ${PKI_client_encrypt_key} (or pass
    # --client-cert/--client-key) and FOG leaves both alone from then on.
    #
    # Checked with the same modulus test the adopt branch below uses, and for
    # the same reason it gives: a certificate that does not pair with this key
    # publishes a public key nothing on this server can decrypt against. Every
    # registered fog-client encrypts to that public half, so a mismatch here
    # does not degrade anything -- it locks out every client at once, and
    # FOGBase::certDecrypt() reports it per client as a failed authorize with
    # nothing pointing back at the certificate. Silently keeping whatever was
    # there was the one path into this state.
    if [[ -f ${PKI_client_encrypt_cert} ]]; then
        local haveMod wantMod
        # Raw modulus, no `openssl md5` -- see _discardOrphanedCommLeaf.
        haveMod=$(openssl x509 -noout -modulus -in "${PKI_client_encrypt_cert}" 2>/dev/null)
        wantMod=$(openssl rsa -noout -modulus -in "${PKI_client_encrypt_key}" 2>/dev/null)
        # No key yet is not a mismatch. An install that has the certificate but
        # not the key is mid-migration, not broken -- _separateCommKey runs
        # after this and is what settles that case.
        if [[ -f ${PKI_client_encrypt_key} && -n $haveMod && -n $wantMod && $haveMod != "$wantMod" ]]; then
            echo
            echo "  ###################################################################"
            echo "  # WARNING: the client communication certificate does not match    #"
            echo "  # the client communication private key.                           #"
            echo "  #                                                                 #"
            echo "  #   certificate: ${PKI_client_encrypt_cert}"
            echo "  #   private key: ${PKI_client_encrypt_key}"
            echo "  #                                                                 #"
            echo "  # Every registered fog-client encrypts to the key in that          #"
            echo "  # certificate, so while these disagree NO client can authenticate #"
            echo "  # -- it surfaces per host as a failed check-in, not as anything   #"
            echo "  # naming these files.                                             #"
            echo "  #                                                                 #"
            echo "  # Put back the certificate that pairs with this key, or supply    #"
            echo "  # both halves together if you issue this leaf outside FOG.        #"
            echo "  ###################################################################"
            echo
        fi
        return 0
    fi
    # An existing server already has this certificate, under the web root where
    # the historic code left it. Adopt it rather than re-signing: it is the
    # exact certificate clients have already been handed, and the root key may
    # be offline.
    #
    # The live copy is normally gone by the time this runs -- configureHttpd
    # rm -rf's $webdirdest before calling createSSLCA -- but it copies the tree
    # to ${DB_backup_path}/fog_web_<ver>.BACKUP first, so look there too. Both are
    # checked because createSSLCA is also reachable without that wipe.
    local oldcert
    for oldcert in \
        "$webdirdest/management/other/ssl/srvpublic.crt" \
        "${DB_backup_path}/fog_web_${version}.BACKUP/management/other/ssl/srvpublic.crt"; do
        [[ -f $oldcert && -f ${PKI_client_encrypt_key} ]] || continue
        local certmod keymod
        # Raw modulus, no `openssl md5`. With it, an unreadable $oldcert and an
        # unreadable key both hashed to md5("") and compared EQUAL, so this
        # branch adopted a certificate it had never actually parsed.
        certmod=$(openssl x509 -noout -modulus -in "$oldcert" 2>/dev/null)
        keymod=$(openssl rsa -noout -modulus -in "${PKI_client_encrypt_key}" 2>/dev/null)
        # The modulus test is what makes this safe. A certificate that does NOT
        # pair with this key is the web certificate of a server whose zones
        # were already separated some other way; copying it here would publish
        # a public key nothing on this server can decrypt against.
        if [[ -n $certmod && -n $keymod && $certmod == "$keymod" ]]; then
            dots "Adopting existing client communication certificate"
            cp -f "$oldcert" "${PKI_client_encrypt_cert}" >>$error_log 2>&1
            errorStat $?
            return 0
        fi
    done
    if [[ ${rootCAKeyOffline:-0} -eq 1 ]]; then
        echo " * Cannot issue the client communication certificate: the CA"
        echo "   private key is not on this server. Restore it to:"
        echo "     ${PKI_root_ca_key}"
        echo "   and re-run the installer."
        return 1
    fi
    dots "Creating client communication certificate"
    local st=0
    # Signed by the ROOT, not by an intermediate. fog-client pins the root and
    # fetches this certificate directly; giving it its own intermediate would
    # add a chain the client has no reason to walk.
    openssl x509 -req -in "$(_pkiZoneDir client)/leaf/fog.csr" -CA "${PKI_root_ca_cert}" -CAkey "${PKI_root_ca_key}" \
        -CAcreateserial -sha512 -days 3650 -extensions v3_ca \
        -extfile "$(_pkiConfDir)/ca.cnf" -out "${PKI_client_encrypt_cert}" >>$error_log 2>&1 || st=1
    chmod 0644 "${PKI_client_encrypt_cert}" >>$error_log 2>&1
    errorStat $st
}
# A comm leaf is only ever valid over the key it was minted from. Drop one that
# has just been orphaned, so _createCommLeaf() issues a replacement below.
#
# Called from the one path that orphans it DELIBERATELY: regenerating
# .srvprivate.key under -K/--recreate-keys or -C/--recreate-CA. The caller gates
# on those flags -- see there for why a merely-absent key must not reach this.
# _createCommLeaf() keeps whatever is
# already at .srvpublic.crt -- deliberately, that is how a leaf issued outside
# FOG survives -- so without this the run republishes a certificate whose public
# half pairs with a key this server has just thrown away. That is not "clients
# must re-pin", it is "nothing can authenticate, and no re-pin fixes it".
#
# Left alone when the root private key is off this server: there would be
# nothing to sign the replacement with, and _createCommLeaf() already prints
# where to restore the key. A stale certificate is recoverable -- put the old
# key back -- where no certificate at all is not.
_discardOrphanedCommLeaf() {
    local cert="${PKI_client_encrypt_cert}" key="${PKI_client_encrypt_key}"
    [[ -f $cert && -f $key && ${rootCAKeyOffline:-0} -ne 1 ]] || return 0
    local certmod keymod
    # The raw modulus, NOT piped through `openssl md5`. With the md5 the
    # emptiness guard below does not work: an unreadable file makes the x509/rsa
    # call print nothing, and md5 of nothing is a perfectly non-empty hash of
    # the empty string. So an unreadable CERT beside a readable key produced a
    # "mismatch" and this function deleted the certificate -- exactly the
    # destroyed-certificate outcome the comment below says it must not cause.
    certmod=$(openssl x509 -noout -modulus -in "$cert" 2>/dev/null)
    keymod=$(openssl rsa -noout -modulus -in "$key" 2>/dev/null)
    # Unreadable either way is not a mismatch. _createCommLeaf() reports that
    # case rather than acting on it, and deleting on a failed read would turn a
    # bad openssl invocation into a destroyed certificate.
    [[ -n $certmod && -n $keymod && $certmod != "$keymod" ]] || return 0
    rm -f "$cert" >>$error_log 2>&1
}
# Warn -- loudly, and then keep going -- when this run changes the certificate
# every registered fog-client is already encrypting to.
#
# validateExternalCA() established the shape: detect a real break, print a boxed
# warning, proceed rather than block. This generalises it to the one file where
# the break is completely silent. $webdirdest/management/other/ssl/srvpublic.crt
# is what fog-client fetched as the server's encryption certificate, and
# FOGBase::certDecrypt() opens the private half of it on every handshake.
# Replacing that keypair -- which -K/--recreate-keys and -C/--recreate-CA both
# do -- invalidates every registered client at once, and the only symptom is
# hosts failing to authorize some time later with nothing on the server, in any
# log, naming the install run that caused it.
#
# Warn, never refuse. An admin who passed -K asked for a new key and may have a
# very good reason (a suspected compromise is the obvious one). The job here is
# only to make sure they hear the cost now instead of from the helpdesk queue.
#
# Compared by fingerprint, not by "did we run genrsa": re-issuing a certificate
# over the SAME key is not a break -- the client encrypts to the public key and
# never validates this certificate -- and an ordinary upgrade, which republishes
# the byte-identical file, has to stay silent or the warning stops meaning
# anything.
_warnClientRepin() {
    local newcert="${PKI_client_encrypt_cert:-$(_pkiZoneDir client)/leaf/.srvpublic.crt}"
    [[ -f $newcert ]] || return 0
    # configureHttpd rm -rf's $webdirdest before createSSLCA runs, so the live
    # copy is usually already gone -- but it backs the tree up first. Same two
    # locations _createCommLeaf() adopts from, checked in the same order.
    local deployed oldfp newfp
    for deployed in \
        "$webdirdest/management/other/ssl/srvpublic.crt" \
        "${DB_backup_path}/fog_web_${version}.BACKUP/management/other/ssl/srvpublic.crt"; do
        [[ -f $deployed ]] && break
        deployed=""
    done
    # Nothing has ever been published from this server, so there is no client
    # out there holding the old public key. A first install must be silent.
    [[ -n $deployed ]] || return 0
    oldfp=$(openssl x509 -noout -fingerprint -sha256 -in "$deployed" 2>/dev/null)
    newfp=$(openssl x509 -noout -fingerprint -sha256 -in "$newcert" 2>/dev/null)
    # An unreadable copy is not evidence of a change; do not cry wolf over it.
    [[ -n $oldfp && -n $newfp ]] || return 0
    [[ $oldfp == "$newfp" ]] && return 0
    echo
    echo "  ###################################################################"
    echo "  # WARNING: the client communication certificate has CHANGED.      #"
    echo "  #                                                                 #"
    echo "  # EVERY REGISTERED fog-client MUST BE REINSTALLED OR RE-PINNED.   #"
    echo "  #                                                                 #"
    echo "  # Every client registered against this server encrypts to the key #"
    echo "  # in the PREVIOUS certificate, and this server no longer holds    #"
    echo "  # that key. None of those clients can authenticate until the      #"
    echo "  # fog-client installer is re-run on them, or they are otherwise   #"
    echo "  # re-pinned to the certificate published by this install.         #"
    echo "  #                                                                 #"
    echo "  # Until then hosts simply stop checking in, and it surfaces as a  #"
    echo "  # failed authorization per host -- nothing points back here.      #"
    echo "  ###################################################################"
    echo "    previous: $deployed"
    echo "              ${oldfp#*=}"
    echo "    new:      $newcert"
    echo "              ${newfp#*=}"
    echo
}
# Make .srvprivate.key a file FOG owns outright.
#
# An admin who relocated ${PKI_web_vhost_key} has the canonical
# ${PKI_client_cert_dir}/.srvprivate.key as a symlink
# to their own key -- which under the historic layout was the web key AND the
# comm key. Separating the zones means the comm key has to stop following that
# link, or an ACME renewal writing their file would still change what
# certDecrypt() reads, which is the exact trap this work exists to remove.
#
# The key MATERIAL is copied, never regenerated: every registered client is
# already encrypting to its public half, and a new key would lock all of them
# out at once.
_separateCommKey() {
    local canon="${PKI_client_cert_dir}/.srvprivate.key" target zonedir
    [[ ! -L $canon ]] && return 0
    target=$(readlink -f "$canon" 2>/dev/null)
    [[ -z $target || ! -f $target ]] && return 0
    # FOG's OWN compat link into the client zone is not something to separate --
    # it is the layout. Without this test the link is dereferenced and the key
    # copied back over it on every run: a real duplicate of the client private
    # key reappears in $snapindir/ssl, and this function announces that it
    # separated a key from the web key, which it did not.
    #
    # _linkClientLeafCompat does put the link back later in the same run, so the
    # duplicate is transient -- but only if the run gets that far, and errorStat
    # exits between here and there. A private key left lying in the directory
    # the snapin replicator walks is not a state to reach and then rely on being
    # tidied.
    #
    # Same question, and the same readlink -f on both sides, as
    # _externallyManagedLeaf asks of the web leaf.
    zonedir=$(readlink -f "$(_pkiZoneDir client)" 2>/dev/null)
    [[ -n $zonedir && $target == "$zonedir"/* ]] && return 0
    # And FOG's own compat link at the ADMIN's file is equally not something to
    # separate -- it is what --client-cert/--client-key mean. The zone test
    # above cannot see that case, because the whole point of a relocated comm
    # keypair is that it lives outside the zone.
    #
    # Without this the relocation unwinds itself on the very next run: the link
    # is dereferenced, the admin's key is copied back into $snapindir/ssl as a
    # REAL file, and _resolveClientLeafPaths' migrate loop then moves that file
    # into the zone -- so FOG quietly takes ownership of key material the admin
    # asked it only to point at. The record surviving writeUpdateFile is what
    # makes this test possible; the two halves are one fix.
    [[ -n ${PKI_client_encrypt_key} \
        && $target == "$(readlink -f "${PKI_client_encrypt_key}" 2>/dev/null)" ]] \
        && return 0
    dots "Separating the client communication key from the web key"
    rm -f "$canon" >>$error_log 2>&1
    cp -f "$target" "$canon" >>$error_log 2>&1
    errorStat $?
}
# Point ${PKI_web_vhost_key}/${PKI_web_vhost_cert} at the Web zone, unless the admin has already
# pointed them somewhere of their own.
#
# On an upgrade both still hold their historic values -- the comm key and the
# certificate under the web tree -- and leaving them there would mean the vhost
# kept serving the client communication keypair, which is the whole problem.
# Anything else is an admin's deliberate choice (ACME, /etc/pki, a mounted
# secret) and is left exactly as it is.
_resolveWebLeafPaths() {
    local webdir leafdir f
    webdir="$(_pkiZoneDir web)"
    leafdir="${webdir}/leaf"
    mkdir -p "$leafdir" >>$error_log 2>&1
    chmod 0700 "$leafdir" >>$error_log 2>&1
    # An install that already ran the flat pki/web layout migrates its leaf
    # material in place -- same key/cert, one more hop.
    for f in .webLeaf.key .webLeaf.pem .webLeaf.csr .webLeaf.sans; do
        [[ -f "${webdir}/${f}" && ! -f "${leafdir}/${f}" ]] && \
            mv "${webdir}/${f}" "${leafdir}/${f}" >>$error_log 2>&1
    done
    # The third comparison catches an install whose .fogsettings already
    # points at the old flat pki/web/.webLeaf.* path (from before this zone
    # had a leaf/ subfolder) and repoints it at the new location, same as the
    # other two catch the pre-separation comm-key/web-tree paths.
    if [[ -z ${PKI_web_vhost_key} \
        || "$(readlink -f "${PKI_web_vhost_key}" 2>/dev/null)" == "$(readlink -f "${PKI_client_cert_dir}/.srvprivate.key" 2>/dev/null)" \
        || ${PKI_web_vhost_key} == "${webdir}/.webLeaf.key" ]]; then
        PKI_web_vhost_key="${leafdir}/.webLeaf.key"
    fi
    # The fourth comparison repairs a server whose ${PKI_web_vhost_cert} was pointed at
    # _writeWebChainFiles()'s own output. That path is FOG's derived bundle and
    # can never be a legitimate input, so naming it means something adopted it
    # by accident -- which is exactly what createSSLCA() did from the vhost.
    # Deliberately the exact path and not "any file with a chain in it": an
    # ACME client's own fullchain.pem is a real and supported value here, and
    # repointing that at FOG's leaf would swap the admin's certificate for one
    # nothing is renewing.
    if [[ -z ${PKI_web_vhost_cert} \
        || "$(readlink -f "${PKI_web_vhost_cert}" 2>/dev/null)" == "$(readlink -f "$webdirdest/management/other/ssl/srvpublic.crt" 2>/dev/null)" \
        || ${PKI_web_vhost_cert} == "${webdir}/.webLeaf.pem" \
        || ${PKI_web_vhost_cert} == "${leafdir}/.webFullChain.pem" ]]; then
        PKI_web_vhost_cert="${leafdir}/.webLeaf.pem"
    fi
}
# The certificate the web server actually serves.
#
# Re-issued when the name set changes, which is free: the Web CA is online and
# stays online. The historic test here was `[[ ! -x ${PKI_web_vhost_cert} ]]`, true of
# every certificate ever written, so the leaf was re-signed on every single run
# -- harmless while one key did every job, fatal once the signer can be offline.
# Build what the web server must actually SEND, which is not the same file as
# the leaf.
#
# The vhost was pointed at ${PKI_web_vhost_cert} alone. That is correct only while the
# leaf is signed by the root directly -- one certificate is a complete chain to
# a trusted anchor. The moment an intermediate sits in between (FOG's own Web
# CA, or one supplied with --web-ca-cert), a client that trusts the root still
# cannot build a path to it, because the intermediate is neither in its trust
# store nor on the wire. It fails as "unable to get local issuer certificate",
# which reads exactly like the CA was never installed.
#
# ${PKI_web_trust_chain} holds root+intermediate, in that order, and is deliberately NOT
# what gets served: TLS wants leaf-first ordering, and the root is pointless on
# the wire because a client that does not already have it will not trust it for
# being sent. So two files are derived here:
#
#   $sslfullchain   leaf + intermediates      -- nginx's ssl_certificate
#   $sslchainonly   intermediates only        -- Apache's SSLCertificateChainFile
#
# Apache gets the separate-file form rather than a concatenated
# SSLCertificateFile because concatenation needs httpd >= 2.4.8 and this
# installer still supports distros shipping 2.4.6, where it silently serves
# only the first certificate -- the exact bug this function exists to fix.
#
# Both stay empty when there is no intermediate, and the callers fall back to
# ${PKI_web_vhost_cert}, so a direct-signed server emits byte-identical config to before.
_writeWebChainFiles() {
    local leafdir block subj issuer
    sslfullchain=""
    sslchainonly=""
    # -s, not -f. An EMPTY certificate file passes -f, and cat'ing it into the
    # bundle below contributes nothing -- producing a "full chain" that is only
    # the CA. The web server then presents the CA as the leaf, whose key is not
    # ${PKI_web_vhost_key}, and refuses to start with "key values mismatch" while
    # `nginx -t` on the very same config still passes. That took a live server
    # down, and the leaf on disk was correct the whole time, so nothing about
    # the failure pointed at the file that was actually wrong.
    [[ -n ${PKI_web_vhost_cert} && -s ${PKI_web_vhost_cert} ]] || return 0
    [[ -n ${PKI_web_trust_chain} && -s ${PKI_web_trust_chain} ]] || return 0

    leafdir="$(_pkiZoneDir web)/leaf"
    mkdir -p "$leafdir" >>$error_log 2>&1
    local chainonly="${leafdir}/.webChain.pem"
    local fullchain="${leafdir}/.webFullChain.pem"
    : > "$chainonly"

    # Every certificate in the chain file except self-signed ones. A self-signed
    # certificate in here is the root, and it is the one thing that must not be
    # sent. Splitting on the PEM boundary rather than trusting the file's order,
    # because the writers disagree -- createWebIntermediateCA and
    # fog-sign-node-cert write issuer-first with the root appended, while
    # validateExternalCA writes the root FIRST -- and an admin-supplied chain
    # may be in any order at all. _rootFromChain selects the other way round off
    # the same property, so neither reader depends on the order.
    local tmpd f
    tmpd=$(mktemp -d) || return 0
    _splitPemBundle "${PKI_web_trust_chain}" "$tmpd"
    for f in "$tmpd"/c*.pem; do
        [[ -f $f ]] || continue
        subj=$(openssl x509 -in "$f" -noout -subject 2>/dev/null)
        issuer=$(openssl x509 -in "$f" -noout -issuer 2>/dev/null)
        [[ -z $subj ]] && continue
        # -subject prints "subject=..." and -issuer "issuer=...", so compare the
        # values rather than the whole line.
        [[ ${subj#subject=} == "${issuer#issuer=}" ]] && continue
        cat "$f" >> "$chainonly"
    done
    rm -rf "$tmpd" >>$error_log 2>&1

    if [[ ! -s $chainonly ]]; then
        rm -f "$chainonly" >>$error_log 2>&1
        return 0
    fi
    # Assemble beside the live file, not over it. This bundle is what the web
    # server serves, so a bad one costs the server its start-up; keeping the
    # previous chain is always better than installing a broken one.
    # The LEAF out of ${PKI_web_vhost_cert}, not the whole file: `openssl x509` reads only
    # the first certificate, which is the leaf in any bundle a web server will
    # accept.
    #
    # Load-bearing rather than tidy. ${PKI_web_vhost_cert} is admin-overridable, and one
    # value in particular is this function's own output -- createSSLCA() adopts
    # whatever path the live vhost names, which is $fullchain on every server
    # FOG has already configured. `cat "${PKI_web_vhost_cert}" "$chainonly"` then fed the
    # bundle its own contents plus one more intermediate, once per install.
    # Fourteen certificates in, browsers still shrugged and iPXE did not: it
    # validates a chain pairwise from the trusted root upwards, so the second
    # copy of the intermediate is checked against the first as its issuer,
    # fails, and every HTTPS netboot dies at boot.php with "Permission denied".
    # Reading only the leaf makes the loop structurally impossible and collapses
    # an already-grown bundle back to leaf+chain on the next run.
    { openssl x509 -in "${PKI_web_vhost_cert}" 2>>$error_log; cat "$chainonly"; } \
        > "${fullchain}.new" 2>>$error_log || return 0
    # The first certificate in the bundle is the leaf the web server will pair
    # with ${PKI_web_vhost_key}. Check that here rather than let the web server discover
    # it at start-up: openssl x509 reads only the first certificate, which is
    # exactly the one being checked.
    local leafpub keypub
    leafpub=$(openssl x509 -in "${fullchain}.new" -noout -pubkey 2>>$error_log \
        | openssl sha256 2>>$error_log)
    keypub=""
    [[ -n ${PKI_web_vhost_key} && -s ${PKI_web_vhost_key} ]] && \
        keypub=$(openssl pkey -in "${PKI_web_vhost_key}" -pubout 2>>$error_log \
            | openssl sha256 2>>$error_log)
    if [[ -n $leafpub && -n $keypub && $leafpub != "$keypub" ]]; then
        echo " * WARNING: assembled chain does not match ${PKI_web_vhost_key}."
        echo "   Keeping the existing ${fullchain} rather than installing one"
        echo "   the web server would refuse to load."
        rm -f "${fullchain}.new" >>$error_log 2>&1
        return 0
    fi
    mv -f "${fullchain}.new" "$fullchain" >>$error_log 2>&1 || return 0
    chmod 0644 "$chainonly" "$fullchain" >>$error_log 2>&1
    sslchainonly="$chainonly"
    sslfullchain="$fullchain"
    return 0
}
_createWebLeaf() {
    local webdir leafdir stamp want st=0
    webdir="$(_pkiZoneDir web)"
    leafdir="${webdir}/leaf"
    stamp="${leafdir}/.webLeaf.sans"

    # OR, never an implication. The two answer different questions -- where the
    # canonical path RESOLVES is who manages the leaf file, while
    # PKI_web_cert_publicly_trusted is what it CHAINS TO -- and all four
    # combinations are real (internal ACME with step-ca is an externally managed
    # leaf that is not publicly trusted). Either one means the certificate was
    # issued outside FOG, so FOG should use it and not touch it. Making one
    # imply the other would silently mutate a setting the admin set.
    if { _externallyManagedLeaf || [[ ${PKI_web_cert_publicly_trusted} == yes ]]; } \
        && [[ $recreateKeys != yes && $recreateCA != yes ]]; then
        local why="${PKI_web_vhost_cert} resolves outside $(_pkiZoneDir web)"
        _externallyManagedLeaf || why="PKI_web_cert_publicly_trusted=yes"
        echo " * Web certificate is externally managed (${why}) -- leaving it in place."
        echo "   Re-issue it yourself if you changed --hostname/--extra-server-name,"
        echo "   or the certificate will not cover the new name."
        return 0
    fi
    if [[ $recreateKeys == yes || $recreateCA == yes || ! -e ${PKI_web_vhost_key} ]]; then
        dots "Creating web server private key"
        openssl genrsa -out "${PKI_web_vhost_key}" 4096 >>$error_log 2>&1
        errorStat $?
        rm -f "$stamp" >>$error_log 2>&1
    fi
    # The name set, hashed. ca.cnf is rewritten from ${PKI_san_ip_addresses}/${NET_hostname}/
    # ${PKI_san_dns_names} on every run, so a changed hostname or a new
    # --extra-server-name changes this and nothing else has to notice.
    # The signing CA is part of the stamp, not just the name set. It used to be
    # ca.cnf alone, which meant switching the Web CA -- --web-ca-cert/-key/-root
    # pointing this server at a CA another FOG server issued -- imported the new
    # CA and then returned right here without re-signing anything, because the
    # NAMES had not changed. The install reported success and the vhost went on
    # serving a certificate signed by the CA that had just been replaced, with
    # nothing anywhere saying so.
    want=$( { cat "$(_pkiConfDir)/ca.cnf" 2>/dev/null
              openssl x509 -in "${PKI_web_ca_cert}" -noout -fingerprint -sha256 2>/dev/null
            } | openssl md5 2>/dev/null)
    if [[ -e ${PKI_web_vhost_cert} && -e $stamp && "$(cat "$stamp" 2>/dev/null)" == "$want" ]]; then
        return 0
    fi
    dots "Creating SSL Certificate"
    # CN is ${NET_hostname}, never $certip -- a browser or client validates against
    # the SAN, never the CN, once a SAN is present (it always is here, see the
    # DNS.1 note above), so this is about giving admins and logs a real name
    # instead of an IP, not about validation. -subj overrides only THIS
    # command's subject; -config still supplies req_extensions (the SAN) from
    # the same file req.cnf's comm-leaf CSR (below) also reads, so the two
    # never diverge on names, only on subject.
    openssl req -new -sha512 -key "${PKI_web_vhost_key}" -out "${leafdir}/.webLeaf.csr" \
        -config "$(_pkiConfDir)/req.cnf" \
        -subj "/CN=$(_certLeafName)/O=FOG Project/OU=FOG Web UI" >>$error_log 2>&1 || st=1
    # 5 years: short enough that a compromised leaf key ages out on its own,
    # long enough not to need automatic renewal. renewal-helper (packages/pki)
    # exists for an admin who wants to rotate it sooner.
    openssl x509 -req -in "${leafdir}/.webLeaf.csr" -CA "${PKI_web_ca_cert}" -CAkey "${PKI_web_ca_key}" \
        -CAcreateserial -out "${PKI_web_vhost_cert}" -days 1825 -extensions v3_ca \
        -extfile "$(_pkiConfDir)/ca.cnf" >>$error_log 2>&1 || st=1
    [[ $st -eq 0 ]] && echo "$want" > "$stamp"
    chmod 0600 "${PKI_web_vhost_key}" >>$error_log 2>&1
    chmod 0644 "${PKI_web_vhost_cert}" >>$error_log 2>&1
    errorStat $st
    [[ $st -ne 0 ]] && return $st
    # Prove the certificate we just signed actually verifies under the CA that
    # signed it. The failure this catches is specific and otherwise silent: the
    # Web CA's name constraints are fixed at the moment it is issued and the CA
    # is never re-minted, so renaming this server, or adding an
    # --extra-server-name outside the permitted domains, produces a perfectly
    # well-formed certificate that no client will accept. Left undetected it
    # surfaces as a browser error days later with nothing connecting it to the
    # rename.
    #
    # Verified against the root the CHAIN terminates in, not against
    # ${PKI_root_ca_cert}. Under --external-ca the leaf chains to the ADMIN's root while
    # ${PKI_root_ca_cert} is still FOG's own -- validateExternalCA never reassigns it --
    # so the old form failed on every external-CA install and printed the box
    # below unconditionally, telling the admin to delete a Web zone that was
    # working correctly.
    #
    # -trusted, not -CAfile. -CAfile ADDS to the default trust locations rather
    # than replacing them, and _installCATrustAnchor puts FOG's own CA into this
    # host's store by default, so a -CAfile test can answer "verified" out of
    # the system store instead of out of the file it was handed. -trusted is the
    # documented "only this file" form.
    local vtmp
    local vroot=""
    vtmp=$(mktemp -d 2>>$error_log)
    if [[ -n $vtmp && -n ${PKI_web_trust_chain} && -e ${PKI_web_trust_chain} ]]; then
        _rootFromChain "${PKI_web_trust_chain}" > "${vtmp}/root.pem" 2>>$error_log
        if [[ -s ${vtmp}/root.pem ]]; then
            vroot="${vtmp}/root.pem"
        elif [[ -n ${PKI_root_ca_cert} && -f ${PKI_root_ca_cert} ]]; then
            # A chain carrying no root of its own. FOG's is the only anchor
            # available, and for a FOG-issued leaf it is also the right one.
            vroot="${PKI_root_ca_cert}"
        fi
    fi
    if [[ -n $vroot ]] && \
        ! openssl verify -trusted "$vroot" -untrusted "${PKI_web_trust_chain}" "${PKI_web_vhost_cert}" >>$error_log 2>&1; then
        echo
        echo "  ###################################################################"
        echo "  # WARNING: the web certificate does not verify against the CA     #"
        echo "  # that issued it.                                                 #"
        if [[ $externalca == yes ]]; then
            echo "  #                                                                 #"
            echo "  # This server uses an external CA, so check that the leaf, the    #"
            echo "  # intermediate and the root you supplied really belong together:  #"
            echo "  #   --web-ca-cert / --web-ca-key / --web-ca-root                   #"
            echo "  # Nothing under the FOG PKI tree needs removing for this.         #"
        else
            echo "  # The usual cause is a name outside that CA's name constraints    #"
            echo "  # -- this server was renamed, or gained an --extra-server-name,   #"
            echo "  # after the CA was created.                                       #"
            echo "  #                                                                 #"
            echo "  # Re-run with the name permitted:                                 #"
            echo "  #   --internal-domain <domain>                                    #"
            echo "  # A CA is never re-issued once it exists, so also remove it so    #"
            echo "  # the new constraints take effect:                                #"
            echo "  #   rm -rf $(_pkiZoneDir web)"
        fi
        echo "  ###################################################################"
        echo
    fi
    [[ -n $vtmp ]] && rm -rf "$vtmp" >>$error_log 2>&1
    return 0
}
FOG_MANAGED_BEGIN='# === FOG MANAGED BLOCK -- DO NOT EDIT BETWEEN THESE LINES (see docs/SUPPORTED_CUSTOMIZATIONS.md) ==='
FOG_MANAGED_END='# === END FOG MANAGED BLOCK ==='
# Replaces only the FOG-owned region of $1 with the contents of $2, leaving
# anything the admin added outside that region byte-for-byte intact.
#
# Why a marked block instead of a template file: every vhost write site in
# createSSLCA() is inline bash echo/heredoc, branching on webserver family, OS
# family, SSL on/off and IPv4/IPv6. Extracting all of that into substitutable
# template assets would mean maintaining two representations of the same
# config forever. Wrapping the existing, unchanged generation in two marker
# lines gets the property that actually matters -- FOG can keep improving what
# it owns without discarding what the admin owns.
#
# '#' is a comment in both nginx and Apache syntax, so the markers are inert
# in either file.
#
# Four cases, deliberately no fifth: no file -> write one; both markers
# present -> replace between them; NEITHER marker present -> the file predates
# this mechanism, so replace it whole; exactly one marker (a previous run died
# mid-write, or someone hand-edited) -> append a fresh block and touch nothing
# that was already there. Never guess at a partial patch.
#
# The zero-marker case used to append too, and that was wrong. Before markers
# existed FOG rewrote this file in full on every run -- the file was entirely
# FOG's own output and nothing else could survive there. Appending to it on the
# first post-upgrade run therefore left FOG's *previous* vhost in place above
# the new block, and Apache serves the first matching <VirtualHost *:443>, so
# the stale one won: a server re-installed onto a new web CA kept serving the
# certificate paths from before the upgrade. Replacing whole restores exactly
# what the pre-marker contract always did; the caller's `mv $etcconf
# $etcconf.$timestamp` backup still holds the old content either way.
spliceManagedBlock() {
    local conffile="$1" contentfile="$2" priorfile="$3"
    # $3 names where the file's PREVIOUS content lives, when the caller has
    # already moved it aside. createSSLCA() does exactly that -- it runs
    # `mv -fv $etcconf $etcconf.$timestamp` before generating, so diffconfig()
    # has something to compare against -- which means by the time we are called
    # $conffile does not exist and the admin's content is in the backup.
    #
    # Missing this is what made the first real-server test wipe a hand-added
    # vhost block: every sandbox test had called this against a file that was
    # still in place, so the "no file" branch never ran when it mattered.
    if [[ ! -f "$conffile" && -n $priorfile && -f "$priorfile" ]]; then
        cp -f "$priorfile" "$conffile" 2>/dev/null
    fi
    if [[ ! -f "$conffile" ]]; then
        { echo "$FOG_MANAGED_BEGIN"; cat "$contentfile"; echo "$FOG_MANAGED_END"; } > "$conffile"
        return $?
    fi
    if grep -qF "$FOG_MANAGED_BEGIN" "$conffile" && grep -qF "$FOG_MANAGED_END" "$conffile"; then
        local tmp="${conffile}.fogsplice.$$"
        # The marker test above is grep -F (substring, so a trailing CR or a
        # stray space still matches) but the awk below compared whole lines --
        # two matchers with different ideas of "this line is the marker". A
        # vhost saved with CRLF endings, or with whitespace after a marker,
        # therefore passed the grep, matched nothing in awk, and fell straight
        # through: file copied byte-for-byte, the freshly generated vhost
        # silently discarded, return 0, installer reports success. Admins are
        # invited into this file by SUPPORTED_CUSTOMIZATIONS.md, so one edit
        # from a Windows box was enough to make every later install or update
        # quietly stop updating the vhost -- stranding whatever the managed
        # block carries, including the maintenance/ deny rules.
        #
        # $0 is still what gets PRINTED, so the admin's own line endings
        # elsewhere in the file are preserved untouched; only the comparison
        # is normalized.
        #
        # The !skip guard on the begin rule matters for the partial-marker
        # state this function is documented to tolerate: without it, a second
        # BEGIN encountered while already skipping fired the rule again and
        # emitted a duplicate copy of the whole generated block, which then
        # persisted in the vhost forever. With it, that state collapses back
        # to a single clean block on the next run.
        awk -v b="$FOG_MANAGED_BEGIN" -v e="$FOG_MANAGED_END" -v cf="$contentfile" '
            { k = $0; sub(/[ \t\r]+$/, "", k) }
            k == b && !skip { print; while ((getline line < cf) > 0) print line; close(cf); skip=1; next }
            k == e          { print; skip=0; next }
            !skip           { print }
        ' "$conffile" > "$tmp" && mv -f "$tmp" "$conffile"
        local st=$?
        [[ $st -eq 0 ]] && dropShadowingVhosts "$conffile" "$contentfile"
        return $st
    fi
    # Neither marker -> pre-marker file, FOG owned all of it, replace it whole.
    if ! grep -qF "$FOG_MANAGED_BEGIN" "$conffile" && ! grep -qF "$FOG_MANAGED_END" "$conffile"; then
        { echo "$FOG_MANAGED_BEGIN"; cat "$contentfile"; echo "$FOG_MANAGED_END"; } > "$conffile"
        return $?
    fi
    # Exactly one marker: damaged or hand-edited. Append, change nothing else.
    { echo "$FOG_MANAGED_BEGIN"; cat "$contentfile"; echo "$FOG_MANAGED_END"; } >> "$conffile"
}
# Repairs a vhost that already carries a stale FOG block OUTSIDE the markers.
#
# The fix one branch up stops this happening, but only for a file that has not
# been through it yet. Installs that ran between the marker system landing and
# that fix already have FOG's previous vhost sitting above the managed block,
# and a file in that state has both markers -- so the splice above replaces the
# managed block correctly and walks straight past the stale copy, forever.
# Apache serves the first matching <VirtualHost *:443> and nginx the first
# matching server{}, so the stale copy wins: the install goes on serving
# whatever paths it carried (old certificates, an old webroot) with nothing
# anywhere reporting an error. Left alone, no future run ever clears it.
#
# The removal is deliberately narrow. Only a block that CLAIMS A NAME THE
# MANAGED BLOCK ALSO CLAIMS is dropped -- a name collision FOG's block is meant
# to win, so the block being removed could not have been serving anyway. An
# admin's own vhost for some other name is untouched, and the caller's
# $etcconf.$timestamp backup holds the file as it was either way.
#
# Keyed on names rather than on a FOG-specific signature line because the
# generated vhost's contents have changed repeatedly across releases, while
# "this block answers for the address FOG was installed on" has not.
dropShadowingVhosts() {
    local conffile="$1" contentfile="$2" tmp="${conffile}.fogshadow.$$" names
    # ServerName/ServerAlias (Apache) and server_name (nginx) in one sweep --
    # the file is only ever one of the two, so there is nothing to disambiguate.
    names=$(awk '{
                     d = tolower($1)
                     if (d != "servername" && d != "serveralias" && d != "server_name") next
                     for (i = 2; i <= NF; i++) { t = $i; sub(/;$/, "", t); if (t != "") print t }
                 }' "$contentfile" | sort -u | tr '\n' ' ')
    [[ -z $names ]] && return 0
    awk -v b="$FOG_MANAGED_BEGIN" -v e="$FOG_MANAGED_END" -v names="$names" '
        function claims(line,   d, i, t) {
            d = tolower($1)
            if (d != "servername" && d != "serveralias" && d != "server_name") return 0
            for (i = 2; i <= NF; i++) { t = $i; sub(/;$/, "", t); if (t in want) return 1 }
            return 0
        }
        function flush() {
            if (!hit) printf "%s", buf
            buf = ""; depth = 0; hit = 0
        }
        BEGIN { n = split(names, nm, " "); for (i = 1; i <= n; i++) if (nm[i] != "") want[nm[i]] = 1 }
        { k = $0; sub(/[ \t\r]+$/, "", k) }
        k == b { inblk = 1; print; next }
        k == e { inblk = 0; print; next }
        inblk  { print; next }
        depth == 0 && /^[ \t]*<VirtualHost/     { depth = 1; apache = 1; buf = $0 "\n"; next }
        depth == 0 && /^[ \t]*server[ \t]*\{/   { depth = 1; apache = 0; buf = $0 "\n"; next }
        depth == 0                              { print; next }
        {
            buf = buf $0 "\n"
            if (claims($0)) hit = 1
            if (apache) { if ($0 ~ /<\/VirtualHost>/) flush() }
            else {
                depth += gsub(/\{/, "{")
                depth -= gsub(/\}/, "}")
                if (depth <= 0) flush()
            }
        }
        # An unterminated block means the file was already malformed. Keep it
        # rather than silently swallowing the tail.
        END { if (buf != "") printf "%s", buf }
    ' "$conffile" > "$tmp" || { rm -f "$tmp"; return 0; }
    # Only swap it in if something was actually removed and the result is not
    # empty -- an empty result would mean the parse went wrong, not that the
    # file was all shadow.
    if [[ -s "$tmp" ]] && ! cmp -s "$tmp" "$conffile"; then
        mv -f "$tmp" "$conffile"
        echo "  Removed a stale FOG vhost left outside the managed block in $conffile"
    else
        rm -f "$tmp"
    fi
    return 0
}
# Redirects the vhost generation that follows into a scratch file, so the ~260
# existing `>> "$etcconf"` lines below need no edit at all: they keep writing
# to $etcconf, which now names the scratch file. endManagedVhost() splices
# that content into the real file and restores the variable.
#
# Rewriting every one of those write sites individually would have been the
# obvious way and the wrong one -- 260-odd near-identical mechanical edits is
# exactly where a missed line hides, and a missed line writes half a vhost to
# the wrong path.
beginManagedVhost() {
    vhostfinal="$etcconf"
    # Callers mv the original to $etcconf.$timestamp just above this, so that
    # is where the admin's previous content is. Remember it -- it is the base
    # the new block gets spliced into.
    vhostprior="${etcconf}.${timestamp}"
    etcconf="${etcconf}.fogblock.$$"
    : > "$etcconf"
}
endManagedVhost() {
    local generated="$etcconf"
    etcconf="$vhostfinal"
    spliceManagedBlock "$etcconf" "$generated" "$vhostprior"
    local st=$?
    rm -f "$generated" >>$error_log 2>&1
    unset vhostfinal vhostprior
    return $st
}
# The certificate path the live vhost actually serves, or empty.
#
# Shared by _servedCertName() above and _detectExternalCertManagement() below,
# which both need it and would otherwise drift apart on the directive-matching.
_vhostCertPath() {
    [[ -n $etcconf && -f $etcconf ]] || return 0
    # ssl_certificate_key and SSLCertificateKeyFile do NOT match: both patterns
    # require whitespace immediately after the directive name, and those two
    # continue with '_' and 'K'. SSLCertificateChainFile is excluded for the
    # same reason -- it is the chain, not the leaf.
    # -oiE, not -aoiE. busybox grep has no -a either (see getBroadcastAddress),
    # so on Alpine both of these printed a usage screen and returned nothing --
    # which made _servedCertName and _detectExternalCertManagement blind to the
    # live vhost. -a only ever mattered for a config file containing a NUL, and
    # a vhost that is not text is a broken vhost. See #863.
    grep -oiE '^[[:space:]]*(SSLCertificateFile|ssl_certificate)[[:space:]]+[^;[:space:]]+' "$etcconf" 2>/dev/null \
        | awk '{print $NF}' | head -1
}
# The private-key path the live vhost names, or empty. Companion to
# _vhostCertPath(); kept separate because the two directives differ per server.
_vhostKeyPath() {
    [[ -n $etcconf && -f $etcconf ]] || return 0
    grep -oiE '^[[:space:]]*(SSLCertificateKeyFile|ssl_certificate_key)[[:space:]]+[^;[:space:]]+' "$etcconf" 2>/dev/null \
        | awk '{print $NF}' | head -1
}
# The vhost's primary name and its alias list, for both web servers.
#
# The certificate has been name-first for a while -- _createWebLeaf() issues
# CN=${NET_hostname} with every address as an IP SAN -- but the vhost was still
# address-first: ServerName was ${NET_fog_server_ip} and the name was demoted to an alias.
# That was harmless while nothing verified, and stopped being harmless when the
# installer's own calls to itself started verifying: no public CA will issue for
# an address, so an ACME server could not satisfy a check keyed on one.
#
# So the primary name comes from _servedCertName() -- the same value the
# self-calls verify against, which is what keeps the vhost's identity and the
# TLS identity from disagreeing. It falls back to ${NET_fog_server_ip} when no name is
# available, so a DNS-less lab install is unchanged.
#
# EVERY address stays, as an alias. That is deliberate and must not be tidied
# away: on an ACME install the address will never verify over HTTPS, but a
# client configured with the address still talks to FOG over HTTP, and the alias
# is the general failsafe for anything addressing this server by number.
#
# One helper for both web servers, because the two used to compute this
# separately and drifted -- see the sweep comment above _rewriteManagedNames().
# ServerName takes exactly one name (GH-650: a second address on the same NIC
# emitted a bare "10.0.0.2" line and apache refused to start), so the split
# matters rather than being cosmetic.
_resolveVhostNames() {
    local n seen
    vhostname=$(_servedCertName)
    seen=" $vhostname "
    vhostaliases=""
    for n in ${PKI_san_ip_addresses} ${NET_hostname} ${PKI_san_dns_names}; do
        [[ -z $n ]] && continue
        [[ " $seen " == *" $n "* ]] && continue
        seen="$seen$n "
        vhostaliases="${vhostaliases} ${n}"
    done
}
# Whether the certificate this server serves is managed OUTSIDE FOG.
#
# Before GH-1120 the answer was the $acmeLeaf key, which had to be
# typed into .fogsettings by hand. The cost of forgetting is not cosmetic:
# createSSLCA() regenerates the leaf from the ORIGINAL CSR, so an install whose
# private key on disk is an ACME key ends up with a cert/key mismatch and a web
# server that will not start. An unattended `-y` upgrade did that silently,
# which is the whole reason this exists.
#
# Echoes a reason and returns 0 when the certificate FOG is about to touch is
# demonstrably not FOG's own; returns 1 otherwise.
#
# Only STRONG signals answer yes, and every one of them is about the
# certificate itself. The presence of acme.sh or certbot on the box is
# deliberately NOT among them: plenty of servers run either for an unrelated
# domain, and treating that as proof would stop FOG managing a leaf it really
# does issue -- a false positive that costs an admin their renewals. Tooling is
# reported by _warnExternalCertTooling() instead, which advises and changes
# nothing. Vhost drift is likewise only advisory: an admin may have edited the
# vhost for reasons that have nothing to do with the certificate.
_detectExternalCertManagement() {
    local p leaf vhostcert customdir
    # 0. A leaf and its key dropped into the customizations tree, under the two
    #    documented names. This is the only signal that fires when FOG is not
    #    already pointed at the admin's file and the vhost does not name it
    #    either -- i.e. the admin did the simplest possible thing, put the files
    #    somewhere FOG told them to and re-ran the installer. Everything below
    #    needs one of those two to have happened first.
    #
    #    BOTH files, and they must actually be a pair. Adopting a leaf whose key
    #    is missing or mismatched points the vhost at a certificate the web
    #    server cannot start with, which is a worse outcome than not adopting:
    #    FOG's own leaf still works, so declining leaves a serving server.
    #
    #    The caller asks _customPkiPair() the same question again rather than
    #    reading a variable set here, because this function is called in a
    #    command substitution -- anything it assigns dies with the subshell.
    if _customPkiPair >/dev/null; then
        echo "$(_customPkiDir)/web-leaf.pem is a certificate you supplied, with a matching key"
        return 0
    fi
    # 1. FOG pointed at a file inside an ACME client's tree. The most direct
    #    evidence available -- somebody already told FOG to use their leaf.
    for p in "${PKI_web_vhost_key}" "${PKI_web_vhost_cert}"; do
        [[ -n $p ]] || continue
        case "$p" in
            /etc/letsencrypt/*|*/.acme.sh/*|/etc/dehydrated/*)
                echo "$p is inside an ACME client's tree"
                return 0
                ;;
        esac
    done
    # 2. The live vhost serving a leaf from outside the paths FOG issues into.
    #    Anywhere else is the admin's file. This is the signal that fires on a
    #    novhost=yes install, where FOG never wrote the vhost and ${PKI_web_vhost_cert}
    #    still names FOG's own unused leaf.
    #
    #    ${PKI_client_cert_dir} alone WAS the whole test, on the stated premise that "FOG
    #    issues into ${PKI_client_cert_dir} and nowhere else". That premise died when the
    #    zoned PKI moved FOG's own web leaf to $fogprogramdir/pki/web/leaf, and
    #    the test did not follow. So on an ordinary FOG-issued HTTPS server the
    #    vhost named a certificate FOG had issued, in a tree FOG owns, and this
    #    concluded the admin managed it: an external leaf recorded on a server
    #    nothing external was touching, and ${PKI_web_vhost_cert} repointed at
    #    _writeWebChainFiles()'s own output. See that function for what the
    #    second half then did to the served chain.
    #
    #    Matched on the pki/ tree rather than on each zone's leaf directory:
    #    the question is "did FOG write this", and every zone under pki/ is
    #    FOG's, including ones added later.
    vhostcert=$(_vhostCertPath)
    if [[ -n $vhostcert && -n ${PKI_client_cert_dir} && $vhostcert != "${PKI_client_cert_dir}"* \
        && $vhostcert != "${fogprogramdir%/}/pki/"* ]]; then
        echo "the vhost serves $vhostcert, outside FOG's ${PKI_client_cert_dir} and PKI tree"
        return 0
    fi
    # 3. A leaf sitting at FOG's own path that FOG's own CA did not sign --
    #    _createCommLeaf() documents dropping a certificate in place as a
    #    supported thing to do, so this is a real configuration and not an
    #    error. Decided by verification rather than by matching "FOG" in the
    #    issuer name, because an admin can rename their CA and a public issuer
    #    can be called anything.
    #
    #    -trusted, not -CAfile: -CAfile ADDS to curl's default locations
    #    instead of replacing them, and _installCATrustAnchor() puts FOG's own
    #    CA into this host's store -- so a -CAfile test can answer "verified"
    #    out of the system store rather than out of the file it was handed.
    #    Same reasoning as _createWebLeaf()'s own check.
    leaf="$vhostcert"
    [[ -n $leaf && -f $leaf ]] || leaf="${PKI_web_vhost_cert}"
    #    The leaf file is passed as its own -untrusted source as well. openssl
    #    verify reads only the FIRST certificate out of the file under test and
    #    ignores the rest, so a fullchain -- which is what nginx must be pointed
    #    at, and what _writeWebChainFiles produces -- was checked without the
    #    intermediate it carries. That only mattered while ${PKI_web_trust_chain} was
    #    empty, but ${PKI_web_trust_chain} is settled LATER in this same function and
    #    arrives from .fogsettings, which an install that died before
    #    writeUpdateFile never wrote. Re-running such an install therefore
    #    reached this test with a FOG-issued fullchain and no intermediate to
    #    check it against, concluded the admin managed the certificate, and
    #    pointed the canonical path outside the zone dir permanently -- after which FOG stops
    #    re-issuing or re-keying its own web certificate. Found while getting
    #    an Alpine install to complete (#863); nothing about it is Alpine
    #    specific.
    if [[ -n $leaf && -f $leaf && -n ${PKI_root_ca_cert} && -f ${PKI_root_ca_cert} ]] \
        && command -v openssl >/dev/null 2>&1; then
        if ! openssl verify -trusted "${PKI_root_ca_cert}" \
            ${PKI_web_trust_chain:+-untrusted "${PKI_web_trust_chain}"} -untrusted "$leaf" \
            "$leaf" >/dev/null 2>&1; then
            echo "$leaf does not chain to this server's own CA"
            return 0
        fi
    fi
    return 1
}
# Advisory only. Names tooling and vhost drift that MIGHT mean the certificate
# is managed elsewhere, without concluding it -- see the comment above for why
# neither is allowed to repoint the canonical path on its own.
_warnExternalCertTooling() {
    local -a notes=()
    if command -v acme.sh >/dev/null 2>&1 \
        || [[ -x $HOME/.acme.sh/acme.sh || -x /root/.acme.sh/acme.sh ]]; then
        notes+=("acme.sh is installed")
    fi
    if command -v certbot >/dev/null 2>&1 \
        || [[ -d /etc/letsencrypt/live ]] && [[ -n $(ls -A /etc/letsencrypt/live 2>/dev/null) ]]; then
        notes+=("certbot or /etc/letsencrypt/live is present")
    fi
    # Drift: the file exists, has content, and carries none of FOG's markers.
    if [[ -n $etcconf && -s $etcconf ]] \
        && ! grep -qF "$FOG_MANAGED_BEGIN" "$etcconf" 2>/dev/null; then
        notes+=("$etcconf carries no FOG managed block, so it was authored by hand")
    fi
    [[ ${#notes[@]} -eq 0 ]] && return 0
    local n
    echo " * Note: this server's web certificate looks FOG-issued, but:"
    for n in "${notes[@]}"; do
        echo "     - $n"
    done
    echo "   If that certificate is in fact managed elsewhere, point"
    echo "   ${PKI_web_vhost_cert} at it -- a symlink is enough -- and FOG"
    echo "   stops re-issuing it. FOG will keep managing the vhost either way."
    return 0
}
createSSLCA() {
    # This function also emits the web server vhost further down, and those
    # nginx location / apache LocationMatch blocks used to hardcode ^/fog/ --
    # so a custom -W/--webroot installed the files somewhere the web server was
    # never told to serve.
    normalizeWebroot
    if [[ -z ${PKI_client_cert_dir} ]]; then
        PKI_client_cert_dir="$snapindir/ssl"
    fi
    PKI_client_cert_dir=${PKI_client_cert_dir//\/$}
    [[ ! -d ${PKI_client_cert_dir} ]] && mkdir -p ${PKI_client_cert_dir} >>$error_log 2>&1
    [[ ! -d ${PKI_client_cert_dir}/CA ]] && mkdir -p ${PKI_client_cert_dir}/CA >>$error_log 2>&1
    # Before anything reads or writes req.cnf/ca.cnf -- which is both zones'
    # issuance below -- so an upgrade finds them where this run expects them and
    # a fresh install has the directory to write into.
    _relocatePkiConf
    _collectPkiNames
    _resolveRootCA
    # Detect-then-LINK, before anything below decides whether to issue a
    # leaf. The point is not to hand the vhost back to the admin -- FOG goes on
    # managing the redirect, the iPXE exclusions and HSTS, which nobody wants to
    # hand-maintain. It is only to stop re-issuing a certificate FOG did not
    # issue, which createSSLCA() would otherwise do from the ORIGINAL CSR and
    # leave the web server unable to start.
    #
    # No prompt. Under -y there is nobody to ask, and that is exactly the run
    # that used to do the damage silently, so the safe behavior has to be the
    # DEFAULT rather than an answer. Everything needed is already on disk: the
    # vhost names the files and the certificate names itself.
    if ! _externallyManagedLeaf; then
        local extReason="" extCert="" extKey="" customPair="" customChain=""
        if extReason=$(_detectExternalCertManagement); then
            # Signal 0 first, and it wins outright: a pair sitting in the
            # customizations tree is the admin saying which certificate to use,
            # and neither the vhost nor ${PKI_web_vhost_cert} can point at it yet
            # -- that is what this run is for. Asked again here because the
            # detector runs in a command substitution and cannot hand anything
            # back except its reason string.
            customPair=$(_customPkiPair) && {
                extCert=$(echo "$customPair" | sed -n 1p)
                extKey=$(echo "$customPair" | sed -n 2p)
                # The chain is adopted with the pair rather than separately.
                # Doing it here, inside the signal-0 arm, is deliberate: the
                # other signals describe a leaf FOG was ALREADY pointed at, and
                # repointing the trust chain in those cases would overwrite a
                # setting the admin may have made by hand.
                customChain=$(_customPkiChain) && _adoptCustomChain "$customChain"
            }
            if [[ -z $extCert ]]; then
                extCert=$(_vhostCertPath)
                extKey=$(_vhostKeyPath)
            fi
            # Fall back to whatever FOG was already pointed at -- signal 1 of
            # the detector is exactly the case where those ARE the admin's
            # files and the vhost may not exist yet.
            [[ -z $extCert ]] && extCert="${PKI_web_vhost_cert}"
            [[ -z $extKey ]] && extKey="${PKI_web_vhost_key}"
            echo " * Detected a web certificate managed outside FOG:"
            echo "     $extReason"
            # Point the canonical paths AT the admin's files instead of
            # recording a flag beside them (GH-1120). The fact "this leaf is not
            # FOG's" then lives in the filesystem, which is the one place that
            # cannot go stale and cannot disagree with the vhost -- the three
            # keys this replaces could each do both.
            _linkCanonical "$extCert" "${PKI_web_vhost_cert}"
            _linkCanonical "$extKey"  "${PKI_web_vhost_key}"
            echo " * FOG will keep managing this vhost, but will not re-issue or"
            echo "   re-key that certificate. Undo by pointing"
            echo "   ${PKI_web_vhost_cert} back inside $(_pkiZoneDir web)."
        else
            _warnExternalCertTooling
        fi
    fi
    # nginx's ssl_certificate must BE the concatenated chain. When the leaf is
    # externally managed, what the canonical path resolves to is whatever the
    # admin's tooling maintains -- correct for nginx by construction, and not to
    # be second-guessed. _hardenPkiPermissions already exempts that key from
    # being locked to root:root 0600 out from under whatever renews it.
    if _externallyManagedLeaf && [[ -f ${PKI_web_vhost_cert} ]]; then
        sslfullchain="${PKI_web_vhost_cert}"
    fi
    # An interface can carry several IPs, so ${NET_fog_server_ip} may arrive as a list:
    # newline-separated from fresh detection, or space-separated when read back
    # from .fogsettings. A certificate has a single subject, so the first IP
    # becomes the CN while every IP is added as a subjectAltName so the cert
    # validates on each address.
    certip="${NET_fog_server_ip}"
    sanentries=""
    sancount=0
    for ip in ${PKI_san_ip_addresses}; do
        sancount=$((sancount + 1))
        [[ -n $sanentries ]] && sanentries="${sanentries}"$'\n'
        sanentries="${sanentries}IP.${sancount} = ${ip}"
    done
    # Every DNS name in ONE pass from _defaultServerNames(), which already emits
    # ${NET_hostname} first when there is one.
    #
    # DNS.1 used to be hardcoded to ${NET_hostname}, with this loop starting at
    # DNS.2 and skipping it to avoid a duplicate. That meant an empty hostname
    # emitted a literal `DNS.1 = ` -- and OpenSSL ACCEPTS that, putting a
    # zero-length dNSName in the certificate. It signs cleanly and then fails
    # every `openssl verify` against the DNS nameConstraints both intermediates
    # carry, which is the silent half of the hazard the note below describes.
    #
    # DNS.1 is still not optional. Where a certificate has no DNS SAN at all
    # OpenSSL falls back to matching the subject CN against those constraints --
    # and this CN is an IP literal, so a leaf with only IP SANs would be
    # rejected by its own CA. Nothing is lost by deriving it: _defaultServerNames()
    # always yields at least fogserver/fog-server, so there is always a DNS.1.
    dnscount=0
    dnsSanEntries=""
    while IFS= read -r extraname; do
        [[ -z $extraname ]] && continue
        dnscount=$((dnscount + 1))
        [[ -n $dnsSanEntries ]] && dnsSanEntries="${dnsSanEntries}"$'\n'
        dnsSanEntries="${dnsSanEntries}DNS.${dnscount} = ${extraname}"
    done < <(_defaultServerNames)
    cat > $(_pkiConfDir)/ca.cnf << EOF
[v3_ca]
subjectAltName = @alt_names
[alt_names]
$sanentries
$dnsSanEntries
EOF
    # Written unconditionally, unlike historically: the web leaf's CSR is built
    # from it on any run where the name set changed, not only on the run that
    # first created a key.
    # prompt = no, not yes. Under "prompt = yes" OpenSSL reads the right-hand
    # side of each [req_distinguished_name] entry as the PROMPT TEXT and then
    # demands a value on stdin for every field. That was survivable while CN was
    # the only entry -- the heredoc below fed exactly one line -- but once O and
    # OU were added there were three prompts and still one line, so O and OU hit
    # EOF and openssl aborted with "Error making certificate request". It only
    # ever showed on a FRESH install, because this whole block is guarded on
    # .srvprivate.key not already existing, which is why upgrades never hit it.
    # With prompt = no the values below are taken literally, which is what they
    # were always meant to be.
    cat > $(_pkiConfDir)/req.cnf << EOF
[req]
distinguished_name = req_distinguished_name
req_extensions = v3_req
prompt = no
[req_distinguished_name]
CN = $certip
O = FOG Project
OU = FOG Client Communication
[v3_req]
subjectAltName = @alt_names
[alt_names]
$sanentries
$dnsSanEntries
EOF

    # --- Client communication keypair -------------------------------------
    #
    # .srvprivate.key and the CSR built from it are exactly what they have
    # always been, and deliberately so: this is the key FOGBase::certDecrypt()
    # opens on every fog-client handshake, and every registered client is
    # already encrypting to its public half.
    # GH-1120 retired $sslcsr: a persisted key that could only ever hold this
    # one path, and was re-derived to it on every run anyway. Every reader now
    # names the canonical location directly, so there is no ordering dependency
    # between this function and _createCommLeaf() below.
    # Order matters. _separateCommKey first, while the canonical name may still
    # be an admin's symlink to their web key: it copies the material out from
    # under that link. _resolveClientLeafPaths second, which then has a real
    # file to move into the client zone and sets the two canonical-path records
    # every line below reads.
    _separateCommKey
    _resolveClientLeafPaths
    if [[ $recreateKeys == yes || $recreateCA == yes || ! -e ${PKI_client_encrypt_key} || ! -e $(_pkiZoneDir client)/leaf/fog.csr ]]; then
        dots "Creating SSL Private Key"
        if [[ $(validip $certip) -ne 0 ]]; then
            echo -e "\n"
            echo "  You seem to be using a DNS name instead of an IP address."
            echo "  This would cause an error when generating SSL key and certs"
            echo "  and so we will stop here! Please adjust variable 'ipaddress'"
            echo "  in .fogsettings file if this is an update and make sure you"
            echo "  provide an IP address when re-running the installer."
            exit 1
        fi
        mkdir -p ${PKI_client_cert_dir} >>$error_log 2>&1
        # 4096 to match what certDecrypt() expects: it chunks the ciphertext by
        # modulus size (openssl_pkey_get_details -> bits/8), so the key size is
        # part of the wire framing, not a tunable.
        if [[ ! -e ${PKI_client_encrypt_key} || $recreateKeys == yes || $recreateCA == yes ]]; then
            openssl genrsa -out ${PKI_client_encrypt_key} 4096 >>$error_log 2>&1
            # Only under the flags that ASKED for a new key. This branch also
            # fires when .srvprivate.key is merely ABSENT -- a bad restore, a
            # lost disk -- and that is damage, not intent. There, the surviving
            # certificate is the admin's way back: put the old key alongside it
            # and the server is whole again. Deleting it takes that away and
            # silently converts a recoverable accident into a mandatory re-pin
            # of every client. _createCommLeaf()'s mismatch warning covers that
            # case instead, which is the right answer for it.
            if [[ $recreateKeys == yes || $recreateCA == yes ]]; then
                _discardOrphanedCommLeaf
            fi
        fi
        # No heredoc: req.cnf is prompt = no, so every DN value comes from the
        # config and openssl reads nothing from stdin. Feeding it a line here
        # would be dead input, and it was the mismatch between that one line and
        # the number of prompted fields that broke fresh installs.
        openssl req -new -sha512 -key ${PKI_client_encrypt_key} -out $(_pkiZoneDir client)/leaf/fog.csr -config $(_pkiConfDir)/req.cnf >>$error_log 2>&1
        errorStat $?
    fi
    _createCommLeaf
    # Before the publish below overwrites the deployed copy this compares
    # against. Placed after _createCommLeaf rather than beside the genrsa so it
    # sees the certificate this run will actually hand out, whether that was
    # re-issued, adopted, or left exactly as it was.
    _warnClientRepin

    # The compat links at the canonical $snapindir/ssl names, now that the
    # keypair definitely exists. FOGBase's _decryptCheck() builds
    # `<sslpath>/.srvprivate.key` from the storage-node record with the filename
    # hardcoded, so those two names have to keep resolving whatever the layout
    # underneath them is.
    _linkClientLeafCompat

    _retireRootLeafLinks

    # --- Web zone ----------------------------------------------------------
    #
    # --external-ca (and the --ca-* trio) targets this zone, which is what they
    # have always effectively meant: they name the CA that signs the vhost's
    # leaf. The root, and therefore what fog-client pins, is untouched by them.
    if [[ $externalca == yes ]]; then
        validateExternalCA web
    elif [[ ${rootCAIssuer:-1} -eq 1 ]]; then
        createWebIntermediateCA
    else
        # The root cannot anchor an intermediate. Sign the web leaf directly
        # from it -- exactly the historic behavior -- rather than issuing a
        # chain that would verify nowhere.
        echo " * Not creating a Web CA: the CA at ${PKI_root_ca_cert}"
        echo "   cannot issue one, because ${rootCAIssuerWhy}."
        echo "   The web certificate will be signed by it directly, as before."
        PKI_web_ca_key="${PKI_root_ca_key}"
        PKI_web_ca_cert="${PKI_root_ca_cert}"
        # Same override guard as createWebIntermediateCA's chain assignment,
        # mirrored here so a switch between the two branches across runs
        # still recognizes either FOG-managed default as "not an override".
        if [[ -z ${PKI_web_trust_chain} || ${PKI_web_trust_chain} == "${PKI_root_ca_cert}" \
            || ${PKI_web_trust_chain} == "$(_pkiZoneDir web)/ca/.fogWebCAchain.pem" \
            || ${PKI_web_trust_chain} == "$(_pkiZoneDir web)/.fogWebCAchain.pem" ]]; then
            PKI_web_trust_chain="${PKI_root_ca_cert}"
        fi
    fi
    # Outside the web-zone branch on purpose: an install that brought its own
    # Web CA still needs an agent CA, and it is issued by the FOG root, which
    # every master holds whatever signs its web leaf.
    createAgentIntermediateCA
    _resolveWebLeafPaths
    _createWebLeaf
    _writeWebChainFiles

    # Canonical paths. FOG's own consumers -- the vhost, sbsign, certDecrypt --
    # only ever reference the canonical location, so the real file may live
    # anywhere: /etc/pki, /etc/letsencrypt/live, a mounted secret. Relocating a
    # certificate then never means editing the vhost.
    #
    # .srvprivate.key is deliberately absent from this list now: it is no
    # longer a relocatable pointer to the web key but the comm key itself, and
    # linking it at a relocated web key is what used to make an ACME renewal
    # break client authentication.
    _linkCanonical "${PKI_web_ca_key}"   "${PKI_client_cert_dir}/CA/.fogWebCA.key"
    _linkCanonical "${PKI_web_ca_cert}"   "${PKI_client_cert_dir}/CA/.fogWebCA.pem"
    # An install that already ran the CA/web layout (the canonical location
    # one restructuring ago) keeps that path resolving too. Guarded on the
    # directory already existing -- nothing creates it fresh any more, so a
    # new install has no reason to grow it just for this symlink.
    if [[ -d ${PKI_client_cert_dir}/CA/web ]]; then
        _linkCanonical "${PKI_web_ca_key}" "${PKI_client_cert_dir}/CA/web/.fogWebCA.key"
        _linkCanonical "${PKI_web_ca_cert}" "${PKI_client_cert_dir}/CA/web/.fogWebCA.pem"
    fi
    mkdir -p $webdirdest/management/other/ssl >>$error_log 2>&1
    # srvpublic.crt is what fog-client fetches as the server's encryption
    # certificate, so it is the COMM leaf. The vhost serves ${PKI_web_vhost_cert}, which
    # now lives in the Web zone outside the web tree. That separation is the
    # whole point: renewing the web certificate touches nothing fog-client
    # depends on.
    dots "Publishing client communication certificate"
    cp -f "${PKI_client_encrypt_cert}" $webdirdest/management/other/ssl/srvpublic.crt >>$error_log 2>&1
    errorStat $?
    dots "Creating auth pub key and cert"
    # The pinned anchor is the ROOT. On an upgrade this file is byte-identical
    # to what it was before, so no fog-client re-pins -- and because the Web CA
    # sits beneath it, a client that trusts this now also trusts the web
    # certificate.
    cp -f "${PKI_root_ca_cert}" $webdirdest/management/other/ca.cert.pem >>$error_log 2>&1
    openssl x509 -outform der -in $webdirdest/management/other/ca.cert.pem -out $webdirdest/management/other/ca.cert.der >>$error_log 2>&1
    # What Agent\Principal verifies client certificates against (see
    # createAgentIntermediateCA). A storage node mints no agent CA and
    # publishes nothing here, and the router's agent gate then refuses
    # every certificate, which is right: a node is not an agent server.
    if [[ -n ${PKI_agent_ca_bundle:-} && -f ${PKI_agent_ca_bundle} ]]; then
        cp -f "${PKI_agent_ca_bundle}" $webdirdest/management/other/agent-ca-bundle.pem >>$error_log 2>&1
    fi
    errorStat $?
    dots "Resetting SSL Permissions"
    chown -R $apacheuser:$apacheuser $webdirdest/management/other >>$error_log 2>&1
    errorStat $?
    # "Forced SSL" describes the REDIRECT, so it follows httpsRedirect. Left on
    # httpproto it would print on every install, since httpproto is https for
    # everyone now -- labeling a plain HTTPS-available server as one that
    # forces HTTPS, which is the opposite of what this line is for.
    [[ ${WEB_https_redirect} == yes ]] && sslenabled=" (Forced SSL)" || sslenabled=" (normal)"
    # ${PKI_san_dns_names} is a space-joined string (see --extra-server-name).
    # Computed once here and reused by both the nginx server_name lines below
    # and Apache's vhostaliases, so an admin's extra name(s) reach every vhost
    # block this function writes, not just one.
    extraServerNamesSuffix=""
    for extraname in ${PKI_san_dns_names}; do
        extraServerNamesSuffix="${extraServerNamesSuffix} ${extraname}"
    done
    # Name-first, addresses as aliases, for both web servers at once.
    _resolveVhostNames
    case ${WEB_server_engine} in
        nginx)
            case $novhost in
                [Yy]|[Yy][Ee][Ss])
                    echo "Skipped"
                    ;;
                *)
                    dots "Setting up Nginx virtualhost${sslenabled}"
                    [[ -z $phploc ]] && phploc="$fogprogramdir/php.loc"
                    # Long asset caching is safe: FOG stamps css/js URLs with
                    # ?ver= (FOG_BCACHE_VER), so upgrades bust browser caches.
                    # HTTP/2 only if the binary has ngx_http_v2_module; then
                    # nginx >= 1.25.1 wants "http2 on;" while older versions
                    # only understand the deprecated "listen ... http2" flag.
                    nginxver=$(nginx -v 2>&1 | grep -oE '[0-9]+\.[0-9]+\.[0-9]+' | head -1)
                    nginxhttp2listen=""
                    nginxhttp2directive=""
                    if nginx -V 2>&1 | grep -q 'http_v2_module'; then
                        if [[ -n $nginxver && $(printf '%s\n' "1.25.1" "$nginxver" | sort -V | head -1) == "1.25.1" ]]; then
                            nginxhttp2directive="    http2 on;"
                        else
                            nginxhttp2listen=" http2"
                        fi
                    fi
                    echo 'location ~ \.php$ {' > "$phploc"
                    emitNginxPhpBody "$phploc"
                    echo "}" >> "$phploc"
                    # Apache's branch below backs up $etcconf the same way before
                    # rewriting it, which is what lets its own diffconfig call
                    # further down actually detect a change; nginx was calling
                    # diffconfig without ever taking this backup first, so it was
                    # comparing the new file to nothing and never fired.
                    mv -fv "${etcconf}" "${etcconf}.${timestamp}" >>$workingdir/error_logs/fog_error_${version}.log 2>&1
                    # Everything below writes to the scratch file; endManagedVhost
                    # splices it into the real one before nginx -t sees it.
                    beginManagedVhost
                    echo "server {" > "$etcconf"
                    echo "    listen 80;" >> "$etcconf"
                    echo "    server_name ${vhostname}${vhostaliases};" >> "$etcconf"
                    # Whether :80 SERVES the site or redirects away from it is
                    # the redirect's decision, not ${WEB_url_proto}'s. 443 listens
                    # either way -- see the ssl server block emitted in both
                    # arms below -- so an admin can move to HTTPS whenever they
                    # like without this being on.
                    if [[ ${WEB_https_redirect} != yes ]]; then
                        echo "    root ${WEB_docroot};" >> "$etcconf"
                        echo "    index index.html index.htm index.php;" >> "$etcconf"
                        echo "    client_max_body_size 3000m;" >> "$etcconf"
                        echo "    gzip on;" >> "$etcconf"
                        echo "    gzip_types text/css text/javascript application/javascript application/json image/svg+xml;" >> "$etcconf"
                        echo "    gzip_min_length 1024;" >> "$etcconf"
                        echo "    gzip_comp_level 5;" >> "$etcconf"
                        echo "    gzip_vary on;" >> "$etcconf"
                        echo "    error_page 500 502 503 504 /50x.html;" >> "$etcconf"
                        # maintenance/ holds installer-only endpoints (a full DB dump,
                        # storage-node create/update). They gate themselves on the
                        # request being same-machine, but the directory is only removed
                        # when an install RUNS TO COMPLETION -- one that dies partway
                        # leaves them on disk indefinitely. Deny them here too.
                        #
                        # This has to precede the generic php include: nginx tries regex
                        # locations in order and the first match wins, so placed after,
                        # `location ~ \.php$` would take these URLs and the allow/deny
                        # would never run. It also has to repeat the fastcgi body (a
                        # location cannot be nested inside another) or nginx, finding no
                        # handler, would serve the PHP source as a static file.
                        echo "    location ~ ^${webrootre}maintenance/ {" >> "$etcconf"
                        echo "        allow 127.0.0.1;" >> "$etcconf"
                        echo "        allow ::1;" >> "$etcconf"
                        for ip in ${PKI_san_ip_addresses}; do
                            echo "        allow ${ip};" >> "$etcconf"
                        done
                        echo "        deny all;" >> "$etcconf"
                        emitNginxPhpBody "$etcconf"
                        echo "    }" >> "$etcconf"
                        echo "    include ${phploc};" >> "$etcconf"
                        echo "    location = /50x.html {" >> "$etcconf"
                        echo "        root /var/lib/nginx/html;" >> "$etcconf"
                        echo "    }" >> "$etcconf"
                        echo "    location ~* ^${webrootre}management/(?!other/).+\.(css|js|png|jpg|gif|svg|ico|woff2?|ttf|eot)\$ {" >> "$etcconf"
                        echo "        expires 30d;" >> "$etcconf"
                        echo "        try_files \$uri =404;" >> "$etcconf"
                        echo "    }" >> "$etcconf"
                        echo "    location ~ ^${webrootre}(.*)\$ {" >> "$etcconf"
                        # $is_args$args, because a try_files fallback to a
                        # plain URI hands the router an EMPTY query string.
                        # Apache's rewrite carries QSA and always has, so
                        # every routed endpoint that reads a query parameter
                        # -- the API's expand/start/length, and the OIDC
                        # plugin's provider/state/code -- worked there and
                        # silently saw nothing here. Route::queryParam()
                        # re-parses REQUEST_URI to survive the old vhosts
                        # this leaves behind; that fallback stays.
                        echo "        try_files \$uri \$uri/ ${WEB_root}api/index.php\$is_args\$args;" >> "$etcconf"
                        echo "    }" >> "$etcconf"
                        echo "    proxy_cookie_domain ~(?P<secure_domain>([-0-9a-z]+\.)?[-0-9a-z]+\.[a-z]+)$ \"$secure_domain; secure\";" >> "$etcconf"
                        echo "}" >> "$etcconf"
                        # Creates the diffie helman param file.
                        if [[ ! -f $(_pkiZoneDir web)/dhparam.pem ]]; then
                            # The web zone dir normally exists by here, but a
                            # storage node reaches this vhost writer without
                            # having minted a Web CA, so nothing has created it.
                            mkdir -p "$(_pkiZoneDir web)" >>$error_log 2>&1
                            openssl dhparam -dsaparam -out $(_pkiZoneDir web)/dhparam.pem 4096 >>$workingdir/error_logs/fog_error_${version}.log 2>&1
                        fi
                        echo "server {" >> "$etcconf"
                        echo "    listen ${NET_fog_server_ip}:443 ssl${nginxhttp2listen};" >> "$etcconf"
                        echo "    server_name ${vhostname}${vhostaliases};" >> "$etcconf"
                        echo "    root ${WEB_docroot};" >> "$etcconf"
                        echo "    index index.html index.htm index.php;" >> "$etcconf"
                        echo "    client_max_body_size 3000m;" >> "$etcconf"
                        echo "    ssl_protocols TLSv1.2 TLSv1.3;" >> "$etcconf"
                        echo "    ssl_prefer_server_ciphers off;" >> "$etcconf"
                        echo "    ssl_dhparam $(_pkiZoneDir web)/dhparam.pem;" >> "$etcconf"
                        echo "    ssl_ciphers ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256:ECDHE-ECDSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-GCM-SHA384:ECDHE-ECDSA-CHACHA20-POLY1305:ECDHE-RSA-CHACHA20-POLY1305:DHE-RSA-AES128-GCM-SHA256:DHE-RSA-AES256-GCM-SHA384:DHE-RSA-CHACHA20-POLY1305;" >> "$etcconf"
                        # nginx has no separate chain directive -- ssl_certificate must BE the
                        # concatenation. Falls back to the bare leaf when nothing sits between
                        # it and the root.
                        echo "    ssl_certificate ${sslfullchain:-${PKI_web_vhost_cert}};" >> "$etcconf"
                        echo "    ssl_certificate_key ${PKI_web_vhost_key};" >> "$etcconf"
                        # fog-agent client certificates. `optional`, and at
                        # server scope because nginx allows nothing finer:
                        # a browser is never asked for one it does not have
                        # -- the request names only the FOG Agent CA, which
                        # no browser holds a certificate from -- and a
                        # request with no certificate reaches PHP with an
                        # empty verdict, where the router's agent gate
                        # decides. Verification against the agent bundle
                        # (agent CA + root) and depth 2 for exactly that
                        # chain. Absent on a node, which mints no agent CA.
                        if [[ -n ${PKI_agent_ca_bundle:-} && -f ${PKI_agent_ca_bundle} ]]; then
                            echo "    ssl_client_certificate ${PKI_agent_ca_bundle};" >> "$etcconf"
                            echo "    ssl_verify_client optional;" >> "$etcconf"
                            echo "    ssl_verify_depth 2;" >> "$etcconf"
                        fi
                        echo "    ssl_session_timeout 1d;" >> "$etcconf"
                        # Zone name is FOG-specific on purpose. Alpine's stock
                        # nginx.conf already declares `shared:SSL:2m` in the
                        # http block, and nginx refuses to start when one zone
                        # name is given two sizes -- so a FOG vhost using the
                        # generic name took the whole web server down with
                        # "the size 52428800 of shared memory zone SSL
                        # conflicts with already declared size 2097152". See
                        # #863. Any distro is free to ship its own "SSL" zone;
                        # FOG should not be squatting on the obvious name.
                        echo "    ssl_session_cache shared:FOGSSL:50m;" >> "$etcconf"
                        # HSTS follows the redirect, and only the redirect.
                        #
                        # This used to be emitted on the :443 server in BOTH
                        # arms -- including on a plain-HTTP install -- which
                        # made it the one setting an admin could not take back.
                        # A browser that has seen this header refuses plain HTTP
                        # to this host for six months, from its own cache; no
                        # server-side change reaches it, so turning the redirect
                        # off did nothing for anyone who had already visited.
                        # That is the redirect's semantics with a memory, so it
                        # belongs to the redirect's key.
                        [[ ${WEB_https_redirect} == yes ]] && \
                            echo "    add_header Strict-Transport-Security max-age=15768000;" >> "$etcconf"
                        [[ -n $nginxhttp2directive ]] && echo "$nginxhttp2directive" >> "$etcconf"
                        echo "    gzip on;" >> "$etcconf"
                        echo "    gzip_types text/css text/javascript application/javascript application/json image/svg+xml;" >> "$etcconf"
                        echo "    gzip_min_length 1024;" >> "$etcconf"
                        echo "    gzip_comp_level 5;" >> "$etcconf"
                        echo "    gzip_vary on;" >> "$etcconf"
                        echo "    error_page 500 502 503 504 /50x.html;" >> "$etcconf"
                        # See the first server block -- installer-only, same-machine only.
                        echo "    location ~ ^${webrootre}maintenance/ {" >> "$etcconf"
                        echo "        allow 127.0.0.1;" >> "$etcconf"
                        echo "        allow ::1;" >> "$etcconf"
                        for ip in ${PKI_san_ip_addresses}; do
                            echo "        allow ${ip};" >> "$etcconf"
                        done
                        echo "        deny all;" >> "$etcconf"
                        emitNginxPhpBody "$etcconf"
                        echo "    }" >> "$etcconf"
                        echo "    include ${phploc};" >> "$etcconf"
                        echo "    location = /50x.html {" >> "$etcconf"
                        echo "        root /var/lib/nginx/html;" >> "$etcconf"
                        echo "    }" >> "$etcconf"
                        echo "    location ~* ^${webrootre}management/(?!other/).+\.(css|js|png|jpg|gif|svg|ico|woff2?|ttf|eot)\$ {" >> "$etcconf"
                        echo "        expires 30d;" >> "$etcconf"
                        echo "        try_files \$uri =404;" >> "$etcconf"
                        echo "    }" >> "$etcconf"
                        echo "    location ~ ^${webrootre}(.*)\$ {" >> "$etcconf"
                        # $is_args$args, because a try_files fallback to a
                        # plain URI hands the router an EMPTY query string.
                        # Apache's rewrite carries QSA and always has, so
                        # every routed endpoint that reads a query parameter
                        # -- the API's expand/start/length, and the OIDC
                        # plugin's provider/state/code -- worked there and
                        # silently saw nothing here. Route::queryParam()
                        # re-parses REQUEST_URI to survive the old vhosts
                        # this leaves behind; that fallback stays.
                        echo "        try_files \$uri \$uri/ ${WEB_root}api/index.php\$is_args\$args;" >> "$etcconf"
                        echo "    }" >> "$etcconf"
                        echo "    proxy_cookie_domain ~(?P<secure_domain>([-0-9a-z]+\.)?[-0-9a-z]+\.[a-z]+)$ \"$secure_domain; secure\";" >> "$etcconf"
                        echo "}" >> "$etcconf"
                    else
                        # Netboot stays on HTTP when the web certificate is not
                        # publicly chainable, so the redirect must NOT catch
                        # iPXE's own fetches -- otherwise it lands right back
                        # on the HTTPS it cannot validate and boot fails.
                        #
                        # The rule is "every path a BOOTLOADER itself fetches",
                        # which is three directories, not one:
                        #
                        #   service/ipxe/       boot.php, advanced.php, the
                        #                       kernel and init (fetched
                        #                       relative to boot.php's own URI),
                        #                       refind, grub, the menu artwork.
                        #   service/secureboot/ MOK.der, which IpxeBootMenu imgfetches
                        #                       so MokManager can enroll it from
                        #                       memory, and mmx64.efi /
                        #                       arm64-efi/mmaa64.efi, which it
                        #                       chains. See IpxeBootMenu's Secure
                        #                       Boot entries.
                        #
                        #   service/uboot/      boot.php, for ARM boards that
                        #                       cannot run iPXE at all. U-Boot's
                        #                       `wget` is HTTP-only with no TLS
                        #                       whatsoever, so it cannot even
                        #                       FAIL a validation -- a 308 to
                        #                       https simply ends the boot. The
                        #                       comment below about a FOS fetch
                        #                       dropping -k is the same trap;
                        #                       this is that trap arriving.
                        #
                        # Everything else FOS reaches under ${web} is fetched by
                        # curl -Lks, which follows the redirect and skips
                        # verification, so it survives one. That tolerance is
                        # load-bearing and undocumented anywhere else: if a FOS
                        # fetch ever drops -k, its path has to be added here too.
                        if [[ ${BOOT_url_proto} != https ]]; then
                            local nbdir
                            for nbdir in ipxe secureboot uboot; do
                                echo "    location ^~ ${WEB_root}service/${nbdir}/ {" >> "$etcconf"
                                echo "        root ${WEB_docroot};" >> "$etcconf"
                                echo "        index index.php;" >> "$etcconf"
                                echo "        try_files \$uri \$uri/ =404;" >> "$etcconf"
                                echo "        include ${phploc};" >> "$etcconf"
                                echo "    }" >> "$etcconf"
                            done
                        fi
                        # The CA itself, always reachable over plain HTTP --
                        # independent of netboot transport, because the client
                        # that needs this is one that trusts nothing yet.
                        # Redirecting it to HTTPS makes fetching the CA require
                        # already trusting the CA. Apache's branch has had this
                        # exemption since GH-529; nginx never did, so on nginx
                        # the bootstrap was simply broken.
                        #
                        # `location =` is an exact match and beats both the `^~`
                        # prefixes above and `location /`, whatever their order.
                        echo "    location = ${WEB_root}management/other/ca.cert.der {" >> "$etcconf"
                        echo "        root ${WEB_docroot};" >> "$etcconf"
                        echo "    }" >> "$etcconf"
                        # The redirect is a `location`, NOT a server-level
                        # `return`. nginx runs a server-level return in the
                        # server rewrite phase, which is BEFORE location
                        # selection -- so emitting the ipxe location first buys
                        # nothing, the return fires for every request and the
                        # exclusion above is dead code. Measured against real
                        # nginx: server-level return 308'd
                        # /fog/service/ipxe/boot.php; as `location /` the same
                        # request serves 200 and everything else still 308s.
                        # `^~` on the ipxe prefix beats `/`, which is what makes
                        # the exclusion win. Apache's branch below has no such
                        # problem -- RewriteCond really does guard the next
                        # RewriteRule.
                        echo "    location / {" >> "$etcconf"
                        echo "        return 308 https://\$host\$request_uri;" >> "$etcconf"
                        echo "    }" >> "$etcconf"
                        echo "}" >> "$etcconf"
                        echo "Continued (See Below)"
                        # Creates the diffie helman param file.
                        if [[ ! -f $(_pkiZoneDir web)/dhparam.pem ]]; then
                            dots "Creating DHParam file"
                            # See the other dhparam site: a storage node reaches
                            # this without having minted a Web CA, so nothing has
                            # created the zone directory.
                            mkdir -p "$(_pkiZoneDir web)" >>$error_log 2>&1
                            openssl dhparam -dsaparam -out $(_pkiZoneDir web)/dhparam.pem 4096 >>$workingdir/error_logs/fog_error_${version}.log 2>&1
                            echo "Done"
                        fi
                        dots "Setting up Nginx virtualhost${sslenabled}"
                        # In Apache we have SSLCertificateFile, SSLCertificateKeyFile, and SSLCACertificateFile.
                        #  SSLCertificateFile is the public certificate created by the CA
                        #  SSLCACertificateFile is the public certificate of the CA
                        #  SSLCertificateKeyFile is the private key that generated the public certificate.
                        # In NGINX we have ssl_certificate and ssl_certificate_key
                        #  The ssl_certificate is the concatenated form of the CA Certificate and the public certificate generated.
                        #  The ssl_certificate_key is the private key.
                        # This generates the concatenated version of the CA and Public certificate.
                        if [[ ! -x $webdirdest/management/other/ssl/srvchained.crt ]]; then
                            cat $webdirdest/management/other/{ca.cert.pem,ssl/srvpublic.crt} >> $webdirdest/management/other/ssl/srvchained.crt
                        fi
                        echo "server {" >> "$etcconf"
                        echo "    listen ${NET_fog_server_ip}:443 ssl${nginxhttp2listen};" >> "$etcconf"
                        echo "    server_name ${vhostname}${vhostaliases};" >> "$etcconf"
                        echo "    root ${WEB_docroot};" >> "$etcconf"
                        echo "    index index.html index.htm index.php;" >> "$etcconf"
                        echo "    client_max_body_size 3000m;" >> "$etcconf"
                        echo "    ssl_protocols TLSv1.2 TLSv1.3;" >> "$etcconf"
                        echo "    ssl_prefer_server_ciphers off;" >> "$etcconf"
                        echo "    ssl_dhparam $(_pkiZoneDir web)/dhparam.pem;" >> "$etcconf"
                        echo "    ssl_ciphers ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256:ECDHE-ECDSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-GCM-SHA384:ECDHE-ECDSA-CHACHA20-POLY1305:ECDHE-RSA-CHACHA20-POLY1305:DHE-RSA-AES128-GCM-SHA256:DHE-RSA-AES256-GCM-SHA384:DHE-RSA-CHACHA20-POLY1305;" >> "$etcconf"
                        # nginx has no separate chain directive -- ssl_certificate must BE the
                        # concatenation. Falls back to the bare leaf when nothing sits between
                        # it and the root.
                        echo "    ssl_certificate ${sslfullchain:-${PKI_web_vhost_cert}};" >> "$etcconf"
                        echo "    ssl_certificate_key ${PKI_web_vhost_key};" >> "$etcconf"
                        # fog-agent client certificates. `optional`, and at
                        # server scope because nginx allows nothing finer:
                        # a browser is never asked for one it does not have
                        # -- the request names only the FOG Agent CA, which
                        # no browser holds a certificate from -- and a
                        # request with no certificate reaches PHP with an
                        # empty verdict, where the router's agent gate
                        # decides. Verification against the agent bundle
                        # (agent CA + root) and depth 2 for exactly that
                        # chain. Absent on a node, which mints no agent CA.
                        if [[ -n ${PKI_agent_ca_bundle:-} && -f ${PKI_agent_ca_bundle} ]]; then
                            echo "    ssl_client_certificate ${PKI_agent_ca_bundle};" >> "$etcconf"
                            echo "    ssl_verify_client optional;" >> "$etcconf"
                            echo "    ssl_verify_depth 2;" >> "$etcconf"
                        fi
                        echo "    ssl_session_timeout 1d;" >> "$etcconf"
                        # Zone name is FOG-specific on purpose. Alpine's stock
                        # nginx.conf already declares `shared:SSL:2m` in the
                        # http block, and nginx refuses to start when one zone
                        # name is given two sizes -- so a FOG vhost using the
                        # generic name took the whole web server down with
                        # "the size 52428800 of shared memory zone SSL
                        # conflicts with already declared size 2097152". See
                        # #863. Any distro is free to ship its own "SSL" zone;
                        # FOG should not be squatting on the obvious name.
                        echo "    ssl_session_cache shared:FOGSSL:50m;" >> "$etcconf"
                        # HSTS follows the redirect, and only the redirect.
                        #
                        # This used to be emitted on the :443 server in BOTH
                        # arms -- including on a plain-HTTP install -- which
                        # made it the one setting an admin could not take back.
                        # A browser that has seen this header refuses plain HTTP
                        # to this host for six months, from its own cache; no
                        # server-side change reaches it, so turning the redirect
                        # off did nothing for anyone who had already visited.
                        # That is the redirect's semantics with a memory, so it
                        # belongs to the redirect's key.
                        [[ ${WEB_https_redirect} == yes ]] && \
                            echo "    add_header Strict-Transport-Security max-age=15768000;" >> "$etcconf"
                        [[ -n $nginxhttp2directive ]] && echo "$nginxhttp2directive" >> "$etcconf"
                        echo "    gzip on;" >> "$etcconf"
                        echo "    gzip_types text/css text/javascript application/javascript application/json image/svg+xml;" >> "$etcconf"
                        echo "    gzip_min_length 1024;" >> "$etcconf"
                        echo "    gzip_comp_level 5;" >> "$etcconf"
                        echo "    gzip_vary on;" >> "$etcconf"
                        echo "    error_page 500 502 503 504 /50x.html;" >> "$etcconf"
                        # See the first server block -- installer-only, same-machine only.
                        echo "    location ~ ^${webrootre}maintenance/ {" >> "$etcconf"
                        echo "        allow 127.0.0.1;" >> "$etcconf"
                        echo "        allow ::1;" >> "$etcconf"
                        for ip in ${PKI_san_ip_addresses}; do
                            echo "        allow ${ip};" >> "$etcconf"
                        done
                        echo "        deny all;" >> "$etcconf"
                        emitNginxPhpBody "$etcconf"
                        echo "    }" >> "$etcconf"
                        echo "    include ${phploc};" >> "$etcconf"
                        echo "    location = /50x.html {" >> "$etcconf"
                        echo "        root /var/lib/nginx/html;" >> "$etcconf"
                        echo "    }" >> "$etcconf"
                        echo "    location ~* ^${webrootre}management/(?!other/).+\.(css|js|png|jpg|gif|svg|ico|woff2?|ttf|eot)\$ {" >> "$etcconf"
                        echo "        expires 30d;" >> "$etcconf"
                        echo "        try_files \$uri =404;" >> "$etcconf"
                        echo "    }" >> "$etcconf"
                        echo "    location ~ ^${webrootre}(.*)\$ {" >> "$etcconf"
                        # $is_args$args, because a try_files fallback to a
                        # plain URI hands the router an EMPTY query string.
                        # Apache's rewrite carries QSA and always has, so
                        # every routed endpoint that reads a query parameter
                        # -- the API's expand/start/length, and the OIDC
                        # plugin's provider/state/code -- worked there and
                        # silently saw nothing here. Route::queryParam()
                        # re-parses REQUEST_URI to survive the old vhosts
                        # this leaves behind; that fallback stays.
                        echo "        try_files \$uri \$uri/ ${WEB_root}api/index.php\$is_args\$args;" >> "$etcconf"
                        echo "    }" >> "$etcconf"
                        echo "    proxy_cookie_domain ~(?P<secure_domain>([-0-9a-z]+\.)?[-0-9a-z]+\.[a-z]+)$ \"$secure_domain; secure\";" >> "$etcconf"
                        echo "}" >> "$etcconf"
                        # Going to add display errors but only if debugmode is configured
                        # also going to loop through all php*.ini files in /etc/ and change accordingly
                        if [[ ${DEBUGMODE} == true ]];then
                            phpinifiles=$(find /etc/ -type f -name php*.ini)
                            for i in $phpinifiles; do
                                sed -i "s/display_errors = Off/display_errors = On/g" $i >>$workingdir/error_logs/fog_error_${version}.log 2>&1
                            done
                        fi
                    fi
                    # Splice BEFORE nginx -t: that tests the real file on disk,
                    # so it has to be the spliced result, not the scratch copy.
                    endManagedVhost
                    echo "Done"
                    dots "Testing nginx configuration"
                    # Capture nginx's own status: errorStat used to read $?
                    # from the diffconfig below it, so a vhost nginx rejected
                    # outright was reported as "Testing nginx configuration
                    # ... OK" and the install carried on to fail somewhere
                    # else entirely. Found on Alpine (#863) via the shared
                    # memory zone collision above, but the misread was never
                    # distro-specific.
                    nginx -t >> $workingdir/error_logs/fog_error_${version}.log 2>&1
                    local nginxtest=$?
                    diffconfig "${etcconf}"
                    errorStat $nginxtest
                    # Self-referential link so /fog/fog/... resolves. $webdirdest
                    # carries a trailing slash, hence the basename.
                    linkIfAbsent $webdirdest ${webdirdest%/}/$(basename $webdirdest)
                    ;;
            esac
            ;;
        httpd|apache*)
            dots "Setting up Apache virtual host${sslenabled}"
            case $novhost in
                [Yy]|[Yy][Ee][Ss])
                    echo "Skipped"
                    ;;
                *)
                    if [[ ${FOG_os_id} -eq 2 ]]; then
                        a2dissite 001-fog >>$workingdir/error_logs/fog_error_${version}.log 2>&1
                        a2ensite 000-default >>$workingdir/error_logs/fog_error_${version}.log 2>&1
                    fi
                    # GH-650: ${NET_fog_server_ip} is one address per line (see the
                    # `ip -4 addr show` in lib/common/input.sh), so a NIC
                    # carrying a second address emitted
                    #     ServerName 10.0.0.1
                    #     10.0.0.2
                    # and apache refused to start with "Invalid command
                    # '10.0.0.2'", failing the install at "Starting and
                    # checking status of web services". ServerName takes
                    # exactly one name; the extras go on ServerAlias, which is
                    # variadic, so a multi-homed server still answers to every
                    # address it has.
                    mv -fv "${etcconf}" "${etcconf}.${timestamp}" >>$workingdir/error_logs/fog_error_${version}.log 2>&1
                    # See the nginx branch above -- same scratch-file swap, so
                    # none of the write sites below change.
                    beginManagedVhost
                    echo "<VirtualHost *:80>" > "$etcconf"
                    echo "    <FilesMatch \"\.php\$\">" >> "$etcconf"
                    if [[ ${FOG_os_id} -eq 1 && $OSVersion -lt 7 ]]; then
                        echo "        SetHandler application/x-httpd-php" >> "$etcconf"
                    else
                        echo "        SetHandler \"proxy:fcgi://127.0.0.1:9000/\"" >> "$etcconf"
                    fi
                    echo "    </FilesMatch>" >> "$etcconf"
                    # The API supports HTTP basic auth, but proxy_fcgi drops
                    # the Authorization header, so PHP_AUTH_USER/PHP_AUTH_PW
                    # were never populated and basic auth could not succeed.
                    # SetEnvIf is used in preference to CGIPassAuth because
                    # CGIPassAuth needs httpd >= 2.4.13 and an unknown
                    # directive stops Apache from starting at all; SetEnvIf
                    # works on every 2.4 and is a no-op under mod_php.
                    echo "    SetEnvIf Authorization \"(.+)\" HTTP_AUTHORIZATION=\$1" >> "$etcconf"
                    echo "    KeepAlive Off" >> "$etcconf"
                    echo "    ServerName $vhostname" >> "$etcconf"
                    [[ -n $vhostaliases ]] && echo "    ServerAlias${vhostaliases}" >> "$etcconf"
                    # maintenance/ holds installer-only endpoints (a full DB dump,
                    # storage-node create/update). Each one gates itself on the
                    # request being same-machine, but the directory is only removed
                    # when an install RUNS TO COMPLETION -- an install that dies
                    # partway leaves them on disk indefinitely. Deny them at the
                    # web server too, so a file added there later without its own
                    # check is not exposed by that omission alone.
                    #
                    # LocationMatch, not Directory: the tree is also published at
                    # ${WEB_docroot}/${webrootbare} via a symlink, and Directory does
                    # not follow symlinks, so a Directory rule would miss that
                    # path entirely. Require local matches loopback and the case
                    # where client and server address are the same -- which is how
                    # the installer calls in.
                    echo "    <LocationMatch \"^${webrootre}maintenance/\">" >> "$etcconf"
                    echo "        Require local" >> "$etcconf"
                    echo "    </LocationMatch>" >> "$etcconf"
                    echo "    DocumentRoot ${WEB_docroot}" >> "$etcconf"
                    # See the nginx branch: the redirect is its own setting now.
                    if [[ ${WEB_https_redirect} == yes ]]; then
                        echo "    RewriteEngine On" >> "$etcconf"
                        echo "    RewriteCond %{REQUEST_METHOD} ^(TRACE|TRACK)" >> "$etcconf"
                        echo "    RewriteRule .* - [F]" >> "$etcconf"
                        echo "    RewriteRule /management/other/ca.cert.der$ - [L]" >> "$etcconf"
                        echo "    RewriteCond %{HTTPS} off" >> "$etcconf"
                        # GH-978: ^/?(.*)$ rather than (.*). In vhost context a
                        # RewriteRule pattern is matched against the URL-path
                        # WITH its leading slash, so (.*) captured "/fog/..."
                        # and the substitution emitted a Location of
                        # https://host//fog/... -- a doubled slash. The bare
                        # (.*) form is .htaccess idiom, where the leading slash
                        # is stripped for you; it has been wrong here since
                        # 2017. Apache's MergeSlashes normally hides it, which
                        # is why it went unreported for so long.
                        # See the nginx branch for the full reasoning: every
                        # path a BOOTLOADER itself fetches must not be
                        # redirected to an HTTPS it cannot validate, and that is
                        # three directories -- service/ipxe/, service/secureboot/
                        # (IpxeBootMenu imgfetches MOK.der and chains mmx64.efi /
                        # arm64-efi/mmaa64.efi out of it) and service/uboot/,
                        # whose caller is U-Boot's HTTP-only `wget` and so cannot
                        # follow the redirect at all.
                        #
                        # The conditions go immediately before the rule they
                        # guard, since RewriteCond applies only to the next
                        # RewriteRule. Multiple RewriteConds are ANDed by
                        # default, which is what is wanted: skip the redirect
                        # only when the request is for neither directory.
                        if [[ ${BOOT_url_proto} != https ]]; then
                            local nbdir
                            for nbdir in ipxe secureboot uboot; do
                                echo "    RewriteCond %{REQUEST_URI} !^${webrootre}service/${nbdir}/" >> "$etcconf"
                            done
                        fi
                        echo "    RewriteRule ^/?(.*)\$ https://%{HTTP_HOST}/\$1 [R,L]" >> "$etcconf"
                        echo "</VirtualHost>" >> "$etcconf"
                        echo "<VirtualHost *:443>" >> "$etcconf"
                        echo "    KeepAlive Off" >> "$etcconf"
                        echo "    <FilesMatch \"\.php\$\">" >> "$etcconf"
                        if [[ ${FOG_os_id} -eq 1 && $OSVersion -lt 7 ]]; then
                            echo "        SetHandler application/x-httpd-php" >> "$etcconf"
                        else
                            echo "        SetHandler \"proxy:fcgi://127.0.0.1:9000/\"" >> "$etcconf"
                        fi
                        echo "    </FilesMatch>" >> "$etcconf"
                        # Keeps API basic auth working; see the :80 vhost.
                        echo "    SetEnvIf Authorization \"(.+)\" HTTP_AUTHORIZATION=\$1" >> "$etcconf"
                        echo "    ServerName $vhostname" >> "$etcconf"
                        [[ -n $vhostaliases ]] && echo "    ServerAlias${vhostaliases}" >> "$etcconf"
                        # See the :80 vhost -- installer-only, same-machine only.
                        echo "    <LocationMatch \"^${webrootre}maintenance/\">" >> "$etcconf"
                        echo "        Require local" >> "$etcconf"
                        echo "    </LocationMatch>" >> "$etcconf"
                        echo "    DocumentRoot ${WEB_docroot}" >> "$etcconf"
                        echo "    SSLEngine On" >> "$etcconf"
                        echo "    SSLProtocol -all +TLSv1.2" >> "$etcconf"
                        echo "    SSLCipherSuite HIGH:ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256:ECDHE-ECDSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-GCM-SHA384:ECDHE-ECDSA-CHACHA20-POLY1305:ECDHE-RSA-CHACHA20-POLY1305:DHE-RSA-AES128-GCM-SHA256:DHE-RSA-AES256-GCM-SHA384:DHE-RSA-CHACHA20-POLY1305:!MEDIUM:!LOW" >> "$etcconf"
                        echo "    SSLHonorCipherOrder On" >> "$etcconf"
                        echo "    SSLSessionTickets Off" >> "$etcconf"
                        echo "    SSLCertificateFile ${PKI_web_vhost_cert}" >> "$etcconf"
                        echo "    SSLCertificateKeyFile ${PKI_web_vhost_key}" >> "$etcconf"
                        # Separate file rather than concatenating into SSLCertificateFile:
                        # concatenation needs httpd >= 2.4.8 and this installer still
                        # supports 2.4.6, which would silently serve only the first
                        # certificate -- the exact failure this is here to fix.
                        [[ -n $sslchainonly ]] && echo "    SSLCertificateChainFile $sslchainonly" >> "$etcconf"
                        # fog-agent client certificates. Apache verifies them
                        # against SSLCACertificateFile, so with an agent CA
                        # present that file is the agent bundle (agent CA +
                        # root) rather than the web trust chain -- the
                        # directive governs client verification only, the
                        # server's own chain is SSLCertificateChainFile above.
                        # `optional` at vhost scope: a Location-scoped
                        # requirement means renegotiation, which TLS 1.3 has
                        # not got and Go's client does not do. The env vars
                        # are exported only under /agent/, the one place PHP
                        # reads them. Agent\Principal re-verifies regardless.
                        if [[ -n ${PKI_agent_ca_bundle:-} && -f ${PKI_agent_ca_bundle} ]]; then
                            echo "    SSLCACertificateFile ${PKI_agent_ca_bundle}" >> "$etcconf"
                            echo "    SSLVerifyClient optional" >> "$etcconf"
                            echo "    SSLVerifyDepth 2" >> "$etcconf"
                            echo "    <Location ${WEB_root}agent/>" >> "$etcconf"
                            echo "        SSLOptions +StdEnvVars +ExportCertData" >> "$etcconf"
                            echo "    </Location>" >> "$etcconf"
                        else
                            echo "    SSLCACertificateFile ${PKI_web_trust_chain}" >> "$etcconf"
                        fi
                        echo "    <IfModule http2_module>" >> "$etcconf"
                        echo "        Protocols h2 http/1.1" >> "$etcconf"
                        echo "    </IfModule>" >> "$etcconf"
                        # Options +FollowSymLinks, stated rather than inherited.
                        # Two things in the web tree are symlinks and neither is
                        # served without it: the self-referential
                        # $webdirdest/$(basename $webdirdest) link created after
                        # this block, and lib/plugins/<name> for every plugin
                        # installed under $fogprogramdir/plugins, which
                        # Plugin::syncAssetLinks() maintains so an external
                        # plugin's js/css is reachable from outside the document
                        # root (ADR 0009).
                        #
                        # This has always worked only because the distro's stock
                        # httpd.conf grants "Options Indexes FollowSymLinks" on
                        # /var/www/html and FOG never said otherwise. A hardened
                        # base config, or a docroot outside that block, silently
                        # turned FOG's own symlinks into 403s.
                        #
                        # SymLinksIfOwnerMatch is not a substitute: the link is
                        # written by the web user and the plugin directory it
                        # points at is typically root-owned, so the owners do not
                        # match and the link would be refused.
                        echo "    <Directory $webdirdest>" >> "$etcconf"
                        echo "        Options +FollowSymLinks" >> "$etcconf"
                        echo "        DirectoryIndex index.php index.html index.htm" >> "$etcconf"
                        echo "    </Directory>" >> "$etcconf"
                        # GH-529: apache does NOT resolve symlinks when matching
                        # <Directory>, so the block above covers the real tree
                        # but not the path a custom webroot is published at --
                        # leaving a bare "/mywebroot/" with no DirectoryIndex
                        # and a 403. Emit the published path too when it is a
                        # different string.
                        if [[ ${WEB_docroot%/}/${webrootbare} != ${webdirdest%/} && -n $webrootbare ]]; then
                            echo "    <Directory ${WEB_docroot%/}/${webrootbare}>" >> "$etcconf"
                            echo "        Options +FollowSymLinks" >> "$etcconf"
                            echo "        DirectoryIndex index.php index.html index.htm" >> "$etcconf"
                            echo "    </Directory>" >> "$etcconf"
                        fi
                        echo "    <IfModule mod_deflate.c>" >> "$etcconf"
                        echo "        <IfModule mod_filter.c>" >> "$etcconf"
                        echo "            AddOutputFilterByType DEFLATE text/html text/css text/javascript application/javascript application/json image/svg+xml" >> "$etcconf"
                        echo "        </IfModule>" >> "$etcconf"
                        echo "    </IfModule>" >> "$etcconf"
                        echo "    <IfModule mod_expires.c>" >> "$etcconf"
                        echo "        <LocationMatch \"^${webrootre}management/(?!other/).+\.(css|js|png|jpg|gif|svg|ico|woff2?|ttf|eot)\$\">" >> "$etcconf"
                        echo "            ExpiresActive On" >> "$etcconf"
                        echo "            ExpiresDefault \"access plus 30 days\"" >> "$etcconf"
                        echo "        </LocationMatch>" >> "$etcconf"
                        echo "    </IfModule>" >> "$etcconf"
                        echo "    Timeout 600" >> "$etcconf"
                        echo "    ProxyTimeout 600" >> "$etcconf"
                        echo "    RewriteEngine On" >> "$etcconf"
                        echo "    RewriteCond %{REQUEST_METHOD} ^(TRACE|TRACK)" >> "$etcconf"
                        echo "    RewriteRule .* - [F]" >> "$etcconf"
                        echo "    RewriteCond %{DOCUMENT_ROOT}/%{REQUEST_FILENAME} !-f" >> "$etcconf"
                        echo "    RewriteCond %{DOCUMENT_ROOT}/%{REQUEST_FILENAME} !-d" >> "$etcconf"
                        echo "    RewriteRule ^${webrootre}(.*)$ ${WEB_root}api/index.php [QSA,L]" >> "$etcconf"
                        echo "</VirtualHost>" >> "$etcconf"
                    else
                        echo "    <Directory $webdirdest>" >> "$etcconf"
                        echo "        Options +FollowSymLinks" >> "$etcconf"
                        echo "        DirectoryIndex index.php index.html index.htm" >> "$etcconf"
                        echo "    </Directory>" >> "$etcconf"
                        # GH-529: apache does NOT resolve symlinks when matching
                        # <Directory>, so the block above covers the real tree
                        # but not the path a custom webroot is published at --
                        # leaving a bare "/mywebroot/" with no DirectoryIndex
                        # and a 403. Emit the published path too when it is a
                        # different string.
                        if [[ ${WEB_docroot%/}/${webrootbare} != ${webdirdest%/} && -n $webrootbare ]]; then
                            echo "    <Directory ${WEB_docroot%/}/${webrootbare}>" >> "$etcconf"
                            echo "        Options +FollowSymLinks" >> "$etcconf"
                            echo "        DirectoryIndex index.php index.html index.htm" >> "$etcconf"
                            echo "    </Directory>" >> "$etcconf"
                        fi
                        echo "    <IfModule mod_deflate.c>" >> "$etcconf"
                        echo "        <IfModule mod_filter.c>" >> "$etcconf"
                        echo "            AddOutputFilterByType DEFLATE text/html text/css text/javascript application/javascript application/json image/svg+xml" >> "$etcconf"
                        echo "        </IfModule>" >> "$etcconf"
                        echo "    </IfModule>" >> "$etcconf"
                        echo "    <IfModule mod_expires.c>" >> "$etcconf"
                        echo "        <LocationMatch \"^${webrootre}management/(?!other/).+\.(css|js|png|jpg|gif|svg|ico|woff2?|ttf|eot)\$\">" >> "$etcconf"
                        echo "            ExpiresActive On" >> "$etcconf"
                        echo "            ExpiresDefault \"access plus 30 days\"" >> "$etcconf"
                        echo "        </LocationMatch>" >> "$etcconf"
                        echo "    </IfModule>" >> "$etcconf"
                        echo "    Timeout 600" >> "$etcconf"
                        echo "    ProxyTimeout 600" >> "$etcconf"
                        echo "    RewriteEngine On" >> "$etcconf"
                        echo "    RewriteCond %{REQUEST_METHOD} ^(TRACE|TRACK)" >> "$etcconf"
                        echo "    RewriteRule .* - [F]" >> "$etcconf"
                        echo "    RewriteCond %{DOCUMENT_ROOT}/%{REQUEST_FILENAME} !-f" >> "$etcconf"
                        echo "    RewriteCond %{DOCUMENT_ROOT}/%{REQUEST_FILENAME} !-d" >> "$etcconf"
                        echo "    RewriteRule ^${webrootre}(.*)$ ${WEB_root}api/index.php [QSA,L]" >> "$etcconf"
                        echo "</VirtualHost>" >> "$etcconf"
                        echo "<VirtualHost *:443>" >> "$etcconf"
                        echo "    KeepAlive Off" >> "$etcconf"
                        echo "    <FilesMatch \"\.php\$\">" >> "$etcconf"
                        if [[ ${FOG_os_id} -eq 1 && $OSVersion -lt 7 ]]; then
                            echo "        SetHandler application/x-httpd-php" >> "$etcconf"
                        else
                            echo "        SetHandler \"proxy:fcgi://127.0.0.1:9000/\"" >> "$etcconf"
                        fi
                        echo "    </FilesMatch>" >> "$etcconf"
                        # Keeps API basic auth working; see the :80 vhost.
                        echo "    SetEnvIf Authorization \"(.+)\" HTTP_AUTHORIZATION=\$1" >> "$etcconf"
                        echo "    ServerName $vhostname" >> "$etcconf"
                        [[ -n $vhostaliases ]] && echo "    ServerAlias${vhostaliases}" >> "$etcconf"
                        # See the :80 vhost -- installer-only, same-machine only.
                        echo "    <LocationMatch \"^${webrootre}maintenance/\">" >> "$etcconf"
                        echo "        Require local" >> "$etcconf"
                        echo "    </LocationMatch>" >> "$etcconf"
                        echo "    DocumentRoot ${WEB_docroot}" >> "$etcconf"
                        echo "    SSLEngine On" >> "$etcconf"
                        echo "    SSLProtocol -all +TLSv1.2" >> "$etcconf"
                        echo "    SSLCipherSuite HIGH:ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256:ECDHE-ECDSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-GCM-SHA384:ECDHE-ECDSA-CHACHA20-POLY1305:ECDHE-RSA-CHACHA20-POLY1305:DHE-RSA-AES128-GCM-SHA256:DHE-RSA-AES256-GCM-SHA384:DHE-RSA-CHACHA20-POLY1305:!MEDIUM:!LOW" >> "$etcconf"
                        echo "    SSLHonorCipherOrder Off" >> "$etcconf"
                        echo "    SSLSessionTickets Off" >> "$etcconf"
                        echo "    SSLCertificateFile ${PKI_web_vhost_cert}" >> "$etcconf"
                        echo "    SSLCertificateKeyFile ${PKI_web_vhost_key}" >> "$etcconf"
                        # Separate file rather than concatenating into SSLCertificateFile:
                        # concatenation needs httpd >= 2.4.8 and this installer still
                        # supports 2.4.6, which would silently serve only the first
                        # certificate -- the exact failure this is here to fix.
                        [[ -n $sslchainonly ]] && echo "    SSLCertificateChainFile $sslchainonly" >> "$etcconf"
                        # fog-agent client certificates. Apache verifies them
                        # against SSLCACertificateFile, so with an agent CA
                        # present that file is the agent bundle (agent CA +
                        # root) rather than the web trust chain -- the
                        # directive governs client verification only, the
                        # server's own chain is SSLCertificateChainFile above.
                        # `optional` at vhost scope: a Location-scoped
                        # requirement means renegotiation, which TLS 1.3 has
                        # not got and Go's client does not do. The env vars
                        # are exported only under /agent/, the one place PHP
                        # reads them. Agent\Principal re-verifies regardless.
                        if [[ -n ${PKI_agent_ca_bundle:-} && -f ${PKI_agent_ca_bundle} ]]; then
                            echo "    SSLCACertificateFile ${PKI_agent_ca_bundle}" >> "$etcconf"
                            echo "    SSLVerifyClient optional" >> "$etcconf"
                            echo "    SSLVerifyDepth 2" >> "$etcconf"
                            echo "    <Location ${WEB_root}agent/>" >> "$etcconf"
                            echo "        SSLOptions +StdEnvVars +ExportCertData" >> "$etcconf"
                            echo "    </Location>" >> "$etcconf"
                        else
                            echo "    SSLCACertificateFile ${PKI_web_trust_chain}" >> "$etcconf"
                        fi
                        echo "    <IfModule http2_module>" >> "$etcconf"
                        echo "        Protocols h2 http/1.1" >> "$etcconf"
                        echo "    </IfModule>" >> "$etcconf"
                        echo "    <Directory $webdirdest>" >> "$etcconf"
                        echo "        Options +FollowSymLinks" >> "$etcconf"
                        echo "        DirectoryIndex index.php index.html index.htm" >> "$etcconf"
                        echo "    </Directory>" >> "$etcconf"
                        # GH-529: apache does NOT resolve symlinks when matching
                        # <Directory>, so the block above covers the real tree
                        # but not the path a custom webroot is published at --
                        # leaving a bare "/mywebroot/" with no DirectoryIndex
                        # and a 403. Emit the published path too when it is a
                        # different string.
                        if [[ ${WEB_docroot%/}/${webrootbare} != ${webdirdest%/} && -n $webrootbare ]]; then
                            echo "    <Directory ${WEB_docroot%/}/${webrootbare}>" >> "$etcconf"
                            echo "        Options +FollowSymLinks" >> "$etcconf"
                            echo "        DirectoryIndex index.php index.html index.htm" >> "$etcconf"
                            echo "    </Directory>" >> "$etcconf"
                        fi
                        echo "    <IfModule mod_deflate.c>" >> "$etcconf"
                        echo "        <IfModule mod_filter.c>" >> "$etcconf"
                        echo "            AddOutputFilterByType DEFLATE text/html text/css text/javascript application/javascript application/json image/svg+xml" >> "$etcconf"
                        echo "        </IfModule>" >> "$etcconf"
                        echo "    </IfModule>" >> "$etcconf"
                        echo "    <IfModule mod_expires.c>" >> "$etcconf"
                        echo "        <LocationMatch \"^${webrootre}management/(?!other/).+\.(css|js|png|jpg|gif|svg|ico|woff2?|ttf|eot)\$\">" >> "$etcconf"
                        echo "            ExpiresActive On" >> "$etcconf"
                        echo "            ExpiresDefault \"access plus 30 days\"" >> "$etcconf"
                        echo "        </LocationMatch>" >> "$etcconf"
                        echo "    </IfModule>" >> "$etcconf"
                        echo "    Timeout 600" >> "$etcconf"
                        echo "    ProxyTimeout 600" >> "$etcconf"
                        echo "    RewriteEngine On" >> "$etcconf"
                        echo "    RewriteCond %{REQUEST_METHOD} ^(TRACE|TRACK)" >> "$etcconf"
                        echo "    RewriteRule .* - [F]" >> "$etcconf"
                        echo "    RewriteCond %{DOCUMENT_ROOT}/%{REQUEST_FILENAME} !-f" >> "$etcconf"
                        echo "    RewriteCond %{DOCUMENT_ROOT}/%{REQUEST_FILENAME} !-d" >> "$etcconf"
                        echo "    RewriteRule ^${webrootre}(.*)$ ${WEB_root}api/index.php [QSA,L]" >> "$etcconf"
                        echo "</VirtualHost>" >> "$etcconf"
                    fi
                    endManagedVhost
                    diffconfig "${etcconf}"
                    errorStat $?
                    # Self-referential link so /fog/fog/... resolves. $webdirdest
                    # carries a trailing slash, hence the basename.
                    linkIfAbsent $webdirdest ${webdirdest%/}/$(basename $webdirdest)
                    if [[ ${FOG_os_id} -eq 2 ]]; then
                        # No `a2enmod php` here. Debian/Ubuntu name that module
                        # php<version> (php7.4, php8.3), never plain php, so the
                        # call only ever printed "ERROR: Module php does not
                        # exist!" -- the line that made a working install look
                        # broken in forums topic 18204. Enabling mod_php would be
                        # wrong anyway: FOG serves PHP through FPM via proxy_fcgi
                        # below, and mod_php forces mpm_prefork, which conflicts.
                        a2enmod proxy_fcgi setenvif >>$workingdir/error_logs/fog_error_${version}.log 2>&1
                        a2enmod rewrite >>$workingdir/error_logs/fog_error_${version}.log 2>&1
                        a2enmod expires deflate filter >>$workingdir/error_logs/fog_error_${version}.log 2>&1
                        a2enmod ssl >>$workingdir/error_logs/fog_error_${version}.log 2>&1
                        a2ensite "001-fog" >>$workingdir/error_logs/fog_error_${version}.log 2>&1
                        a2dissite "000-default" >>$workingdir/error_logs/fog_error_${version}.log 2>&1
                    fi
                    # After a2ensite, not beside the diffconfig above, and that
                    # ordering is the whole point on Debian/Ubuntu: the fog
                    # vhost lives in sites-available and is not part of the
                    # loaded config until the line above links it. Testing
                    # earlier would have passed while saying nothing about the
                    # file just written.
                    #
                    # This is the misread the nginx arm already fixed (see
                    # "Testing nginx configuration" above): the apache arm ends
                    # in `diffconfig; errorStat $?`, and diffconfig returns 0
                    # when there is no backup to compare against -- so errorStat
                    # printed OK for a vhost apache cannot parse and the install
                    # carried on to die at "Starting and checking status of web
                    # services", with nothing pointing at the config. GH-650 is
                    # exactly that failure reaching a user.
                    #
                    # The tool is named differently per distro and no distro
                    # ships the others, so try them in turn rather than casing
                    # on ${FOG_os_id}: apache2ctl on Debian/Ubuntu, apachectl on
                    # RHEL/Arch/Alpine, httpd where only the daemon is on PATH.
                    # None present means nothing to test and nothing to report.
                    dots "Testing Apache configuration"
                    local httpdtest=0 httpdtool="" httpdcandidate=""
                    for httpdcandidate in apache2ctl apachectl httpd; do
                        if command -v $httpdcandidate >/dev/null 2>&1; then
                            httpdtool=$httpdcandidate
                            break
                        fi
                    done
                    if [[ -n $httpdtool ]]; then
                        $httpdtool -t >> $workingdir/error_logs/fog_error_${version}.log 2>&1
                        httpdtest=$?
                    fi
                    errorStat $httpdtest
                    ;;
            esac
            ;;
        *) ;;
    esac
    dots "Configuring PHP FPM"
    case ${FOG_os_id} in
        1)
            phpfpmconf='/etc/php-fpm.d/www.conf';
            ;;
        2)
            phpfpmconf="/etc/php/${WEB_php_version}/fpm/pool.d/www.conf"
            ;;
        3)
            # Alpine's pool lives beside its php.ini, under the versioned
            # directory (/etc/php83, /etc/php84, ...). The Arch path that used
            # to be here matched nothing on Alpine, so none of the tuning below
            # was ever applied.
            phpfpmconf="$(dirname $phpini)/php-fpm.d/www.conf"
            ;;
        4)
            phpfpmconf='/etc/php/php-fpm.d/www.conf'
            ;;
    esac
    if [[ -n $phpfpmconf ]]; then
        # The pool runs the PHP that IS FOG -- nginx only ever serves static
        # files and proxies everything else here -- so it has to run as the user
        # FOG owns its files with. The packaged default disagrees on every nginx
        # install: RedHat/Fedora ship user=apache, Arch user=http, Debian/Ubuntu
        # user=www-data, while $apacheuser is "nginx" on all of them.
        #
        # That mismatch is why an $apacheuser-owned directory is not reliably
        # writable by the process that needs it. It is the reason
        # $fogprogramdir/cache is 1777 rather than $apacheuser-owned, and it is
        # what made the Secure Boot staging directory (0750 nginx:nginx)
        # unreachable to a pool running as apache -- kernel updates failed there
        # with nothing but "Failed to open temp file", which names neither
        # php-fpm nor the directory.
        #
        # Set unconditionally rather than only under nginx: where the packaged
        # user already equals $apacheuser this is a no-op, and pinning it makes
        # the pool's identity explicit instead of silently inherited from the
        # distro packaging, which is free to change it.
        #
        # The patterns cannot catch listen.owner/listen.group -- those start
        # "listen.", so the anchored "user"/"group" match skips them -- and the
        # listen socket is TCP (below) anyway, so their values do not matter.
        sed -i "s/^[;[:space:]]*user[[:space:]]*=.*/user = ${apacheuser}/" $phpfpmconf >>$workingdir/error_logs/fog_error_${version}.log 2>&1
        sed -i "s/^[;[:space:]]*group[[:space:]]*=.*/group = ${apacheuser}/" $phpfpmconf >>$workingdir/error_logs/fog_error_${version}.log 2>&1
        # Changing the pool user orphans whatever the previous worker owned, and
        # the session directory is the one that bites immediately: PHP writes
        # sessions there AS the pool user, so a pool that can no longer read its
        # own session files logs every admin out and refuses new logins, with
        # nothing in the browser pointing at php-fpm. Re-own it to match.
        #
        # Derived from the pool's own php_value override first, then php.ini,
        # rather than from a hardcoded list of distro paths that would rot.
        phpsessdir=$(sed -n "s/^[;[:space:]]*php_value\[session\.save_path\][[:space:]]*=[[:space:]]*//p" $phpfpmconf | tail -1 | tr -d '"')
        [[ -z $phpsessdir ]] && phpsessdir=$(sed -n "s/^[;[:space:]]*session\.save_path[[:space:]]*=[[:space:]]*//p" $phpini 2>/dev/null | tail -1 | tr -d '"')
        # Guarded hard because this is a recursive chown running as root: it must
        # be an absolute path, must exist, must not be /, and must actually look
        # like a session directory. A parse that goes wrong resolves to nothing
        # rather than to a recursive chown of somewhere that matters.
        if [[ -n $phpsessdir && $phpsessdir == /* && $phpsessdir != "/" && -d $phpsessdir && $phpsessdir == *session* ]]; then
            chown -R ${apacheuser}:${apacheuser} "$phpsessdir" >>$workingdir/error_logs/fog_error_${version}.log 2>&1
        fi
        # The pool's error log is orphaned by the same user change, and it
        # fails silently in a way the session directory does not: php-fpm
        # cannot open it, so every error_log() call from FOG's PHP is
        # discarded and the log stays zero bytes forever. Nothing reports
        # this -- not the browser, not the master's own error.log. The
        # diagnostics that vanish are the ones written for exactly the
        # cases nobody can reproduce: the storage-group fallback warning,
        # the OIDC plugin's reason for rejecting an ID token, the login
        # page's reason for refusing a provider button.
        #
        # Measured on a Fedora nginx install: the RPM ships
        # /var/log/php-fpm owned apache:root and www-error.log owned
        # apache:apache, the pool runs as nginx after the rewrite above,
        # and `test -w` says no to both.
        #
        # The file is chowned unconditionally; the directory only when its
        # own name marks it as php-fpm's. On Debian the log sits directly
        # in /var/log, and chowning that to the web user would be a far
        # worse bug than the one being fixed.
        #
        # logrotate keeps the ownership: the packaged php-fpm rule has no
        # `create` line, so a rotated file inherits the attributes of the
        # one it replaced.
        phpfpmlog=$(sed -n "s/^[;[:space:]]*php_admin_value\[error_log\][[:space:]]*=[[:space:]]*//p" $phpfpmconf | tail -1 | tr -d '"')
        if [[ -n $phpfpmlog && $phpfpmlog == /* && $phpfpmlog != "/" && -d $(dirname "$phpfpmlog") ]]; then
            [[ -f $phpfpmlog ]] || touch "$phpfpmlog" >>$workingdir/error_logs/fog_error_${version}.log 2>&1
            chown ${apacheuser}:${apacheuser} "$phpfpmlog" >>$workingdir/error_logs/fog_error_${version}.log 2>&1
            phpfpmlogdir=$(dirname "$phpfpmlog")
            case "$(basename "$phpfpmlogdir")" in
                *fpm*|*php*)
                    chown ${apacheuser}:${apacheuser} "$phpfpmlogdir" >>$workingdir/error_logs/fog_error_${version}.log 2>&1
                    ;;
            esac
        fi
        sed -i 's/listen = .*/listen = 127.0.0.1:9000/g' $phpfpmconf >>$workingdir/error_logs/fog_error_${version}.log 2>&1
        sed -i 's/^[;]pm\.max_requests = .*/pm.max_requests = 2000/g' $phpfpmconf >>$workingdir/error_logs/fog_error_${version}.log 2>&1
        sed -i 's/^[;]php_admin_value\[memory_limit\] = .*/php_admin_value[memory_limit] = 256M/g' $phpfpmconf >>$workingdir/error_logs/fog_error_${version}.log 2>&1
        sed -i 's/pm\.max_children = .*/pm.max_children = 50/g' $phpfpmconf >>$workingdir/error_logs/fog_error_${version}.log 2>&1
        sed -i 's/pm\.min_spare_servers = .*/pm.min_spare_servers = 5/g' $phpfpmconf >>$workingdir/error_logs/fog_error_${version}.log 2>&1
        sed -i 's/pm\.max_spare_servers = .*/pm.max_spare_servers = 10/g' $phpfpmconf >>$workingdir/error_logs/fog_error_${version}.log 2>&1
        sed -i 's/pm\.start_servers = .*/pm.start_servers = 5/g' $phpfpmconf >>$workingdir/error_logs/fog_error_${version}.log 2>&1
    fi
    echo "Done"
    dots "Starting and checking status of web services"
    case $systemctl in
        yes)
            case ${FOG_os_id} in
                2)
                    systemctl is-active --quiet ${WEB_server_engine} $phpfpm && systemctl stop ${WEB_server_engine} $phpfpm >>$error_log 2>&1 || true
                    systemctl is-active --quiet ${WEB_server_engine} $phpfpm && true || systemctl start ${WEB_server_engine} $phpfpm >>$error_log 2>&1
                    systemctl status ${WEB_server_engine} $phpfpm >>$error_log 2>&1
                    ;;
                *)
                    systemctl is-active --quiet ${WEB_server_engine} php-fpm && systemctl stop ${WEB_server_engine} php-fpm >>$error_log 2>&1 || true
                    sleep 1
                    systemctl is-active --quiet ${WEB_server_engine} php-fpm && true || systemctl start ${WEB_server_engine} php-fpm >>$error_log 2>&1
                    sleep 1
                    systemctl status ${WEB_server_engine} php-fpm >>$error_log 2>&1
                    ;;
            esac
            ;;
        *)
            case ${FOG_os_id} in
                2)
                    service ${WEB_server_engine} stop >>$error_log 2>&1
                    service ${WEB_server_engine} start >>$error_log 2>&1
                    service $phpfpm stop >>$error_log 2>&1
                    service $phpfpm start >>$error_log 2>&1
                    service ${WEB_server_engine} status >>$error_log 2>&1
                    service $phpfpm status >>$error_log 2>&1
                    ;;
                3)
                    rc-service nginx stop >>$workingdir/error_logs/fog_error_${version}.log 2>&1
                    rc-service nginx start >>$workingdir/error_logs/fog_error_${version}.log 2>&1
                    rc-service $phpfpm stop >>$workingdir/error_logs/fog_error_${version}.log 2>&1
                    rc-service $phpfpm start >>$workingdir/error_logs/fog_error_${version}.log 2>&1
                    rc-service nginx status >>$workingdir/error_logs/fog_error_${version}.log 2>&1
                    rc-service $phpfpm status >>$workingdir/error_logs/fog_error_${version}.log 2>&1
                    ;;
                *)
                    service ${WEB_server_engine} stop >>$error_log 2>&1
                    service ${WEB_server_engine} start >>$error_log 2>&1
                    service php-fpm stop >>$error_log 2>&1
                    service php-fpm start >>$error_log 2>&1
                    service ${WEB_server_engine} status >>$error_log 2>&1
                    service php-fpm status >>$error_log 2>&1
                    ;;
            esac
            ;;
    esac
    errorStat $?
}
# Name, by directory, any plugin that was hand-placed under the OLD web
# tree's lib/plugins/ -- the only way plugins existed on 1.5, and still a
# working way to add one on 1.6 right up until an upgrade runs. ADR 0009
# split plugins into two roots specifically so this stops happening again,
# but the split does nothing for whatever is already sitting in an upgrading
# admin's tree: configureHttpd() is about to overwrite $webdirdest from
# $webdirsrc, downloadplugins()'s bundled set, and nothing else survives that.
#
# The only remaining trace afterward is fog_web_<ver>.BACKUP, and only with
# --oldcopy does anything even copy it back -- silently, as a directory an
# admin has to know to go looking for. This runs right after that backup is
# taken, while the old tree is still on disk under a name someone can find,
# and says the two things $error_log never will: which directories were
# there, and where a third-party plugin belongs now.
#
# Compared against $webdirsrc/lib/plugins/, which is the bundled set THIS
# release actually fetched (downloadplugins() runs before configureHttpd()),
# not a hand-kept list that would drift the moment a plugin joins or leaves
# fog-plugins.
#
# Bundled plugins 1.6 RETIRES rather than relocates, in the order they should
# be reported.
#
# Top level rather than inside the backup step so it is a property of the
# installer that both _stripRetiredPlugins and the test suite can read. A copy
# living inside one step would let the list and its coverage drift apart
# silently, which is the whole failure mode a retirement list exists to stop.
#
# Each is deleted from the backup so --oldcopy cannot lay it back down beside
# the core that replaced it, and each gets its OWN advice in
# _warnUnrecognizedPlugins -- the "copy it to $fogprogramdir/plugins" line the
# rest of that notice gives would tell an admin to reinstall what this upgrade
# just removed.
retiredplugins="accesscontrol persistentgroups"
# Remove each RETIRED bundled plugin from the backup, remembering which were
# there.
#
# The removal itself is old behavior and is right: 1.6 replaces these plugins
# with core, their registration rows are deleted by schema steps (307 for
# accesscontrol, 402 for persistentgroups), and --oldcopy restoring their PHP
# into a 1.6 tree would lay a retired plugin back down beside the core that
# retired it.
#
# What was missing is the record. This runs inside the backup step, which is
# BEFORE _warnUnrecognizedPlugins scans that same backup -- so by the time
# anything could name one of these, the directory is already gone. No scan of
# the backup can find it however it is written, which is why the fact is carried
# in a variable instead.
#
# persistentgroups joined the list for ADR 0038: core now resolves group grants
# instead of copying them onto hosts, so the plugin compensates for a defect
# that no longer exists. Its retirement matters more than accesscontrol's,
# because it leaves behind something no file deletion reaches -- an AFTER INSERT
# trigger on `groupMembers`, dropped by schema step 402.
_stripRetiredPlugins() {
    local name dir
    for name in $retiredplugins; do
        dir="${DB_backup_path}/fog_web_${version}.BACKUP/lib/plugins/${name}"
        [[ -d $dir ]] && retiredpluginsstripped="${retiredpluginsstripped} ${name}"
        rm -rf "$dir"
    done
    # Never fatal, and never the step's exit status: this sits between a cp and
    # an errorStat $? that is reporting on the BACKUP, not on this.
    return 0
}
# Detection only -- nothing here copies, deletes or blocks anything.
_warnUnrecognizedPlugins() {
    local oldplugins="${DB_backup_path}/fog_web_${version}.BACKUP/lib/plugins"
    # Retired plugins are named FIRST and outside every guard below, because
    # they are not unrecognized plugins -- they are RETIRED ones, and the advice
    # the rest of this function gives would be wrong for them. Their 1.6
    # equivalent is core, so copying them to $fogprogramdir/plugins would
    # reinstall the thing the upgrade just replaced.
    #
    # Outside the guards for two reasons. They cannot come from the scan at all
    # (_stripRetiredPlugins deleted them from the backup already), and this is
    # not a comparison against the bundled set -- so neither "no old plugins
    # directory" nor "nothing bundled to compare against" has any bearing on
    # whether this is worth saying.
    local retired
    for retired in ${retiredpluginsstripped:-}; do
        echo
        case $retired in
            accesscontrol)
                echo "  The retired accesscontrol plugin was in the old web tree."
                echo "  1.6 replaces it with core roles and permissions -- your roles and"
                echo "  user assignments were migrated into them by the schema update, and"
                echo "  the plugin's own registration row was removed."
                echo "  Do NOT copy it to ${fogprogramdir:-/opt/fog}/plugins: it is not a"
                echo "  plugin to relocate, and it is not carried into the backup either."
                echo "  Review what came across under Role Management once this finishes."
                ;;
            persistentgroups)
                echo "  The retired persistentgroups plugin was in the old web tree."
                echo "  1.6 makes a group's snapins and printers a standing grant that is"
                echo "  resolved when it is used, so a host added to a group picks them up"
                echo "  without anything being copied onto it -- which is what that plugin"
                echo "  existed to fake."
                echo "  Its database TRIGGER has been dropped by the schema update. That"
                echo "  matters, because the trigger outlived the plugin's files: removing"
                echo "  the code never stopped it firing."
                echo "  Do NOT copy it to ${fogprogramdir:-/opt/fog}/plugins: installing it"
                echo "  again re-creates that trigger."
                ;;
        esac
        echo
    done
    [[ -d $oldplugins ]] || return 0
    # "Unrecognized" is decided by comparison, so with nothing to compare
    # against there is no finding to report -- only a list of every plugin
    # the admin has, named as third-party. That is not hypothetical:
    # configureMinHttpd() (the STORAGE NODE path, installfog.sh:1353) calls
    # configureHttpd without downloadplugins ever running, and lib/plugins is
    # gitignored on 1.6, so a storage-node install from a clone reaches here
    # with an empty bundled set. Say nothing rather than cry wolf.
    compgen -G "${webdirsrc}/lib/plugins/*/" >/dev/null 2>&1 || return 0
    local dir name found=""
    for dir in "$oldplugins"/*/; do
        [[ -d $dir ]] || continue
        name=$(basename "$dir")
        [[ -d ${webdirsrc}/lib/plugins/${name} ]] && continue
        if [[ -z $found ]]; then
            echo
            echo "  Third-party plugin(s) found in the old web tree's lib/plugins/:"
            found=1
        fi
        echo "   * $name"
    done
    [[ -n $found ]] || return 0
    echo "  These are not part of the bundled plugin set and this upgrade does"
    echo "  not carry them forward. They now belong in ${fogprogramdir:-/opt/fog}/plugins"
    echo "  -- copy them there from ${DB_backup_path}/fog_web_${version}.BACKUP/lib/plugins/"
    echo "  and re-run, or install them from that directory via the UI."
    echo
}
configureHttpd() {
    normalizeWebroot
    # Both customizations trees, before anything below needs either of them.
    #
    # This used to sit inside createSSLCA(), which this function calls further
    # down, so it ran late and it made the PKI routine responsible for creating
    # kernel-backups/keep/ -- a boot-file directory that has nothing to do with
    # certificates. Same run, same order relative to the restore below; only the
    # function that owns the call changes.
    _ensureCustomizationsTree
    dots "Stopping web service"
    case $systemctl in
        yes)
            case ${FOG_os_id} in
                1)
                    systemctl is-active --quiet ${WEB_server_engine} php-fpm && systemctl stop ${WEB_server_engine} php-fpm >>$error_log 2>&1 || true
                    ;;
                2)
                    systemctl is-active --quiet ${WEB_server_engine} php${WEB_php_version}-fpm && systemctl stop ${WEB_server_engine} php${WEB_php_version}-fpm >>$error_log 2>&1 || true
                    ;;
                4)
                    systemctl is-active --quiet ${WEB_server_engine} $phpfpm && systemctl stop ${WEB_server_engine} $phpfpm >>$error_log 2>&1 || true
                    ;;
            esac
            errorStat $?
            ;;
        *)
            case ${FOG_os_id} in
                1)
                    service ${WEB_server_engine} stop >>$error_log 2>&1
                    service php-fpm stop >>$error_log 2>&1
                    errorStat $?
                    ;;
                2)
                    service ${WEB_server_engine} stop >>$error_log 2>&1
                    service php${WEB_php_version}-fpm stop >>$error_log 2>&1
                    errorStat $?
                    ;;
                3)
                    rc-service nginx stop >>$workingdir/error_logs/fog_error_${version}.log 2>&1
                    errorStat $?
                    # Was `service php-fpm${WEB_php_version} stop`, which is wrong
                    # twice over on Alpine: there is no `service` command, and
                    # ${WEB_php_version} is the dotted version (8.3) while Alpine's fpm
                    # service takes the undotted suffix. $phpfpm already holds
                    # the right name -- php-fpm83. See #863.
                    rc-service $phpfpm stop >>$workingdir/error_logs/fog_error_${version}.log 2>&1
                    ;;
            esac
            ;;
    esac
    dots "Setting up Apache and PHP files"
    if [[ ! -f $phpini ]]; then
        echo "Failed"
        echo "   ###########################################"
        echo "   #                                         #"
        echo "   #      PHP Failed to install properly     #"
        echo "   #                                         #"
        echo "   ###########################################"
        echo
        echo "   Could not find $phpini!"
        exit 1
    fi
    if [[ ${FOG_os_id} -eq 4 ]]; then
        # Arch ships httpd.conf with almost every module commented out and
        # nothing enabled beyond a bare static server, so FOG has to turn on
        # what it needs. PHP runs through php-fpm over mod_proxy_fcgi, which is
        # why event stays and prefork/worker go: mod_php would force prefork and
        # is the reason the recipe in GH-447 could not have worked as written.
        if [[ ! -f $httpdconf ]]; then
            echo "Failed"
            echo "   Could not find $httpdconf!"
            exit 1
        fi
        sed -i '/LoadModule mpm_event_module modules\/mod_mpm_event.so/s/^#//g' $httpdconf >>$error_log 2>&1
        sed -i '/LoadModule mpm_prefork_module modules\/mod_mpm_prefork.so/s/^/#/g' $httpdconf >>$error_log 2>&1
        sed -i '/LoadModule mpm_worker_module modules\/mod_mpm_worker.so/s/^/#/g' $httpdconf >>$error_log 2>&1
        for archmod in proxy_html xml2enc proxy proxy_http proxy_fcgi socache_shmcb ssl rewrite; do
            sed -i "/LoadModule ${archmod}_module modules\/mod_${archmod}.so/s/^#//g" $httpdconf >>$error_log 2>&1
        done
        unset archmod
        grep -q "^Include conf/extra/fog\.conf" $httpdconf 2>/dev/null || \
            echo -e "# FOG Virtual Host\nListen 443\nInclude conf/extra/fog.conf" >>$httpdconf
    fi
    # Uncommenting ";extension=" lines is Arch's php.ini convention -- it builds
    # nearly everything into the php package and ships it all disabled.
    #
    # Alpine must NOT do this, and the claim that it was harmless there was
    # simply wrong. Alpine enables its modules with drop-ins under
    # /etc/php8x/conf.d, but its php.ini is the ordinary upstream one and does
    # carry these commented lines, so uncommenting them did two things:
    #
    #   - turned on ftp and zip, which Alpine packages separately and FOG does
    #     not install, giving a PHP startup warning on every single request;
    #   - loaded mysqli and pdo_mysql from php.ini, which PHP reads BEFORE the
    #     conf.d drop-ins and therefore before 01_mysqlnd.ini. Both then failed
    #     to relocate ("mysqlnd_poll: symbol not found") and FOG was left with
    #     no database driver at all.
    #
    # open_basedir still applies to both -- it is a plain setting, not an
    # extension, and Alpine's php.ini carries it too. See #863.
    if [[ ${FOG_os_id} -eq 4 ]]; then
        sed -i 's/;extension=bcmath/extension=bcmath/g' $phpini >>$error_log 2>&1
        sed -i 's/;extension=curl/extension=curl/g' $phpini >>$error_log 2>&1
        sed -i 's/;extension=ftp/extension=ftp/g' $phpini >>$error_log 2>&1
        sed -i 's/;extension=gd/extension=gd/g' $phpini >>$error_log 2>&1
        sed -i 's/;extension=gettext/extension=gettext/g' $phpini >>$error_log 2>&1
        sed -i 's/;extension=ldap/extension=ldap/g' $phpini >>$error_log 2>&1
        sed -i 's/;extension=mysqli/extension=mysqli/g' $phpini >>$error_log 2>&1
        sed -i 's/;extension=openssl/extension=openssl/g' $phpini >>$error_log 2>&1
        sed -i 's/;extension=pdo_mysql/extension=pdo_mysql/g' $phpini >>$error_log 2>&1
        sed -i 's/;extension=posix/extension=posix/g' $phpini >>$error_log 2>&1
        sed -i 's/;extension=sockets/extension=sockets/g' $phpini >>$error_log 2>&1
        sed -i 's/;extension=zip/extension=zip/g' $phpini >>$error_log 2>&1
    fi
    if [[ ${FOG_os_id} -eq 3 || ${FOG_os_id} -eq 4 ]]; then
        sed -i 's/^open_basedir\ =/;open_basedir\ =/g' $phpini >>$error_log 2>&1
    fi
    sed -i 's/post_max_size\ \=\ 8M/post_max_size\ \=\ 3000M/g' $phpini >>$error_log 2>&1
    sed -i 's/upload_max_filesize\ \=\ 2M/upload_max_filesize\ \=\ 3000M/g' $phpini >>$error_log 2>&1
    sed -i 's/.*max_input_vars\ \=.*$/max_input_vars\ \=\ 250000/g' $phpini >>$error_log 2>&1
    errorStat $?
    dots "Testing and removing symbolic links if found"
    # GH-1146: $webdirdest IS ${WEB_docroot}fog/, so unlinking it here left the
    # "Backing up old data" test below with nothing to find. No
    # fog_web_<ver>.BACKUP was written, and the management/other/ carry-forward
    # further down -- which reads that directory -- silently did nothing, on
    # every install whose web root is a symlink. The only trace was a find(1)
    # complaint in the error log. Remember where the link pointed so the tree
    # is still reachable once the link itself is gone.
    priorwebdir=""
    if [[ -h ${WEB_docroot}fog ]]; then
        priorwebdir=$(readlink -f "${WEB_docroot}fog" 2>>$error_log)
        rm -f ${WEB_docroot}fog >>$error_log 2>&1
    fi
    if [[ -h ${WEB_docroot}${WEB_root} ]]; then
        [[ -z $priorwebdir ]] && priorwebdir=$(readlink -f "${WEB_docroot}${WEB_root}" 2>>$error_log)
        rm -f ${WEB_docroot}${WEB_root} >>$error_log 2>&1
    fi
    # A link pointing at the document root itself, or at one of its parents,
    # is not a FOG tree to copy aside -- it is somebody's whole web server.
    # GH-953 is the standing reminder of what taking a path like that at face
    # value costs. Nothing below reads $priorwebdir once it is cleared.
    if [[ -n $priorwebdir ]]; then
        case "${WEB_docroot%/}/" in
            "${priorwebdir%/}/"*)
                priorwebdir=""
                ;;
        esac
    fi
    errorStat $?
    dots "Backing up old data"
    # Whether either branch below actually copied anything. Both can be false:
    # $webdirdest may not exist at all, and $priorwebdir is only set when
    # ${WEB_docroot}fog was a symlink this run removed. See the report at the end
    # of this step for why that has to be said out loud.
    webbackedup=""
    # Which retired plugins were actually in the tree just backed up. Set by
    # _stripRetiredPlugins below, read by _warnUnrecognizedPlugins, because by
    # the time that runs the evidence has been deleted. The list itself is
    # $retiredplugins, defined at the top level beside those two functions.
    retiredpluginsstripped=""
    if [[ -d ${DB_backup_path}/fog_web_${version}.BACKUP ]]; then
        rm -rf ${DB_backup_path}/fog_web_${version}.BACKUP >>$error_log 2>&1
    fi
    if [[ -d $webdirdest ]]; then
        cp -RT "$webdirdest" "${DB_backup_path}/fog_web_${version}.BACKUP" >>$error_log 2>&1
        webbackedup=1
        _stripRetiredPlugins
        rm -rf "$webdirdest" >>$error_log 2>&1
    elif [[ -n $priorwebdir && -d $priorwebdir ]]; then
        # Copy only, no removal. The branch above deletes $webdirdest because
        # the new tree is about to be written over that exact path.
        # $priorwebdir is somewhere else the admin chose, and it was already
        # being left behind before this fix -- backing it up is the gain here,
        # and deleting it would be a new behavior nobody asked for.
        cp -RT "$priorwebdir" "${DB_backup_path}/fog_web_${version}.BACKUP" >>$error_log 2>&1
        webbackedup=1
        _stripRetiredPlugins
    fi
    if [[ ${FOG_os_id} -eq 2 ]]; then
        # GH-953: this removed ${WEB_docroot} -- the whole document root, taking any
        # other site sharing it with FOG. It only reaches the rm when the
        # rm -rf "$webdirdest" above failed to remove the same directory, so it
        # was a recovery path that deleted the parent of what it could not
        # delete. Only the fog directory was ever meant to go.
        if [[ -d ${WEB_docroot}fog ]]; then
            rm -rf ${WEB_docroot}fog >>$error_log 2>&1
        fi
    fi
    mkdir -p "$webdirdest" >>$error_log 2>&1
    if [[ -d ${WEB_docroot} && ! -h ${WEB_docroot}fog ]] || [[ ! -d ${WEB_docroot}fog ]]; then
        ln -s $webdirdest  ${WEB_docroot}/fog >>$error_log 2>&1
    fi
    # GH-529: $webdirdest is a filesystem path and is always "<docroot>/fog";
    # ${WEB_root} is the URL path the web server publishes. With the default
    # "/fog/" the two coincide, which is why nothing ever linked them -- and
    # why -W/--webroot produced a vhost pointing at a URL with nothing behind
    # it. Publish the tree at the requested path as well. The removal of
    # ${WEB_docroot}${WEB_root} earlier in this function has always expected this
    # link to exist; it just was not being created.
    if [[ ${WEB_docroot%/}/${webrootbare} != ${webdirdest%/} && -n $webrootbare ]]; then
        linkIfAbsent "${webdirdest%/}" "${WEB_docroot%/}/${webrootbare}"
    fi
    # This step printed "OK" whether or not a fog_web_<ver>.BACKUP was written.
    # errorStat is reached forty lines after the copy and reports the status of
    # the link work above it, so an install that found nothing to preserve
    # still told the admin their web root had been backed up. It is reachable
    # on any server whose webroot is moved aside between installs -- the tree
    # is neither at $webdirdest nor behind a symlink this run removed, so both
    # branches are skipped -- and the only trace was the absence of a directory
    # nobody looks for until they need it.
    webbackupstat=$?
    errorStat $webbackupstat skip
    if [[ -n $webbackedup ]]; then
        echo "OK"
    else
        echo "Skipped"
    fi
    # Independent of --oldcopy: the backup this step just took is where a
    # hand-placed plugin now only exists, whether or not anything copies it
    # back automatically.
    _warnUnrecognizedPlugins
    if [[ ${FOG_copy_back_old} == yes ]]; then
        if [[ -d ${DB_backup_path}/fog_web_${version}.BACKUP ]]; then
            dots "Copying back old web folder as is";
            cp -Rf ${DB_backup_path}/fog_web_${version}.BACKUP/* $webdirdest/
            errorStat $?
            # GH-1136: this runs on the tree just restored from the backup,
            # and the new tree is laid over it below -- so anything left
            # CamelCase here reappears beside its own lowercased copy, and
            # the autoloader (which keys on the lowercased basename stem)
            # then picks between them by directory read order. The shipped
            # tree carries no CamelCase class file, which is what makes the
            # rename converge rather than duplicate.
            # Three fixes to the loop itself while here: lowercase the
            # BASENAME only (lowercasing the whole path breaks any
            # $webdirdest containing an uppercase letter -- the mv target
            # directory does not exist); parenthesise the -name arms,
            # because find's -o binds looser than the implicit -a and only
            # the first arm was getting -type f; and read null-delimited so
            # a path containing a space stays one item. .page.php and
            # .report.php are autoloaded on the same lowercased key as the
            # other three and belong in the same sweep.
            dots "Ensuring all classes are lowercased"
            find "$webdirdest" -type f \( \
                -name "*[A-Z]*.class.php" -o \
                -name "*[A-Z]*.event.php" -o \
                -name "*[A-Z]*.hook.php" -o \
                -name "*[A-Z]*.page.php" -o \
                -name "*[A-Z]*.report.php" \) -print0 2>>$error_log |
                while IFS= read -r -d '' i; do
                    mv "$i" "$(dirname "$i")/$(basename "$i" | tr 'A-Z' 'a-z')" >>$error_log 2>&1
                done
            errorStat $?
            # A class file the new release DROPPED is still in the backup, and
            # the copy above puts it straight back -- the same problem
            # retired_web_other solves for management/other/ further down this
            # function. It has been latent for any dropped class file. The
            # PSR-4 move makes 201 of them stale in a single release, because
            # every lib/**/*.class.php that held a core class now ships as
            # src/<Bucket>/<Class>.php instead.
            #
            # Left behind they are not merely clutter. autoload() answers a
            # bare class name out of src/ BEFORE it consults the scanned class
            # map, so the classes themselves are inert -- but
            # Initiator::classFileList() still walks these files, they still
            # enter that map, and they are still on the include_path built
            # from its dirnames. A file that is only reachable on servers
            # which happen to have upgraded with --oldcopy is the definition
            # of a difference between two installs with nothing to say so.
            #
            # Asked of the source tree rather than named, for the reason given
            # at retired_web_other: a hand-kept list of retired paths drifts
            # from what is actually shipped, and that drift is the bug that
            # list was added to fix. maxdepth 2 keeps this to lib/<dir>/<file>
            # -- a bundled plugin's class files are one level deeper, at
            # lib/plugins/<name>/class/, and are the plugin release's to
            # manage, not this loop's.
            #
            # There is no longer a keep here, and removing it was the point.
            # config.class.php is GENERATED and so is never in $webdirsrc, which
            # is why it needed one -- but it is generated into commons/ now, and
            # this loop only walks $webdirdest/lib. So the generated file is out
            # of reach of the sweep by construction rather than by exception.
            #
            # What that leaves under lib/fog/ is the PREVIOUS install's config,
            # and it has to go. Left behind it is a file holding that install's
            # DATABASE_PASSWORD, both FTP passwords and its
            # FOG_SCHEMA_INSTALL_TOKEN, sitting readable in the web root while a
            # different file is the one actually being used. Nothing reads it,
            # nothing reports it, and it survives every future upgrade.
            # THE DISCOVERY EXTENSIONS ARE NOT OPTIONAL HERE, AND THEY ARE
            # WORSE THAN THE .class.php CASE ABOVE.
            #
            # GH-1528 retired 52 more files the same way: every core page,
            # hook, report and event moved from lib/{pages,hooks,reports,
            # events}/<lowercase>.<type>.php to src/<Bucket>/<Class>.php. A
            # loop matching only *.class.php leaves all 52 behind.
            #
            # A stale .class.php is inert -- the comment above says why. A
            # stale *.report.php is NOT. ReportManagement::loadCustomReports()
            # merges core's src/Reports with the fileitems() walk that finds
            # plugin reports, and the walk reaches lib/reports/ as well, so
            # every core report is found TWICE and the Reports menu renders
            # each of them twice. Measured on a 1.6 install upgraded this way:
            # 17 reports became 30, 13 of them duplicates.
            #
            # So the sweep asks for the four discovery extensions alongside
            # the class files. The keep-if-still-shipped test is unchanged and
            # is what makes that safe: lib/router/ still ships its .class.php
            # files and they are matched, tested and kept, exactly as before.
            dots "Removing retired class files from the old web folder"
            local relpath
            while IFS= read -r -d '' i; do
                relpath="${i#$webdirdest/}"
                [[ -e ${webdirsrc}/${relpath} ]] && continue
                rm -f "$i" >>$error_log 2>&1
            done < <(find "$webdirdest/lib" -maxdepth 2 -type f \( \
                -name '*.class.php' -o \
                -name '*.page.php' -o \
                -name '*.hook.php' -o \
                -name '*.report.php' -o \
                -name '*.event.php' \) -print0 2>>$error_log)
            errorStat $?
        fi
    fi
    dots "Copying new files to web folder"
    cp -Rf $webdirsrc/* $webdirdest/
    errorStat $?
    # The web root was rm -rf'd above and rebuilt, so any file the new release
    # DROPPED is genuinely gone. Initiator::classFileList() caches the scanned
    # class-file list to $fogprogramdir/cache with a 300 second TTL, and that
    # cache does not know the tree just changed underneath it.
    #
    # Left alone, every request for the rest of the TTL walks a list naming
    # files that no longer exist. startClassFromFiles() used to die on the
    # first one, which is an uncaught ReflectionException inside LoadGlobals --
    # a bodyless 500 on every page, including the "Checking web server serves
    # FOG" probe a few steps below, which then reports a failed install. It
    # heals itself when the TTL expires, so it reads as a flaky install rather
    # than as this.
    #
    # Observed with fog-plugins v1.6.11, which drops ldap/hooks/addldapapi.hook.php.
    #
    # startClassFromFiles() now skips a vanished file instead of dying, so this
    # is the second of two guards rather than the only one -- but a stale list
    # still means a hook that quietly does not load, and that is a feature
    # silently not happening. Clearing it here means the very next request
    # rescans.
    #
    # Only the source lists. The same directory holds the settings-cache flush
    # signal (see the cache block in configureFOGService) which must survive.
    #
    # Two lists, because there are two roots: srcmap.* describes core's PSR-4
    # tree and pluginsrc.* describes every installed plugin's (ADR 0035).
    # filelist.* was the third, from the discovery-suffix scan that both
    # replaced; it is removed too so an upgrade from before that change does
    # not leave the file behind forever.
    dots "Dropping the stale class file lists"
    rm -f $fogprogramdir/cache/srcmap.*.json $fogprogramdir/cache/pluginsrc.*.json $fogprogramdir/cache/filelist.*.json >>$error_log 2>&1
    errorStat $?
    # management/other/ is where an administrator's own files live, and the
    # rm -rf above took them with it, so they are restored from the backup.
    #
    # What must NOT come back is anything FOG owns, and FOG owns three
    # different kinds of file in here:
    #
    #   1. what this release ships there. Asked of the source tree rather than
    #      named, so the answer cannot drift from what is actually shipped.
    #      This used to be `gpl-3.0.txt` and `index.php` spelled out in the
    #      find below -- a second, hand-kept description of the release.
    #   2. what FOG shipped there in the PAST and has since dropped. The
    #      source tree CANNOT see these: a file the release no longer ships is
    #      indistinguishable from one the administrator put there, so
    #      retirement has to be recorded, and that is what retired_web_other
    #      is for. Without it a dropped file is copied back out of the backup
    #      on every upgrade for the rest of the server's life -- which is what
    #      kept management/other/_variables.scss, a Font Awesome 4 icon list
    #      dead since the FA7 migration, on every upgraded install with no way
    #      to leave.
    #   3. ca.*, which is not shipped in the tree at all but MINTED into this
    #      directory by _installCATrustAnchor(). Restoring the previous one
    #      over a freshly generated CA would hand the server a stale trust
    #      anchor -- the certificate fog-client pins and iPXE is built
    #      against. Named here rather than derived, because there is nothing
    #      to derive it from.
    #
    # retired_web_other is APPEND-ONLY. Dropping an entry lets the file return
    # from the backup held by every server that still has one. Add a name here
    # in the same commit that removes the file from packages/web.
    local retired_web_other=(_variables.scss)
    local otherfile
    for i in $(find ${DB_backup_path}/fog_web_${version}.BACKUP/management/other/ -maxdepth 1 -type f -not -name 'ca.*' 2>>$error_log); do
        otherfile=$(basename "$i")
        if [[ -e ${webdirsrc}/management/other/${otherfile} ]]; then
            continue
        fi
        case " ${retired_web_other[*]} " in
            *" ${otherfile} "*)
                continue
                ;;
        esac
        cp -Rf $i ${webdirdest}/management/other/ >>$error_log 2>&1
    done
    if [[ ${FOG_install_lang} == yes ]]; then
        dots "Creating the language binaries"
        langpath="${webdirdest}/management/languages"
        languagesfound=$(find $langpath -maxdepth 1 -type d -exec basename {} \; | awk -F. '/\./ {print $1}' 2>>$error_log)
        languagemogen "$languagesfound" "$langpath"
        echo "Done"
    fi
    # Generate a per-install schema bootstrap token written into config.class.php
    # as FOG_SCHEMA_INSTALL_TOKEN. It lets the installer deploy the schema before
    # any FOG user/database exists without leaving the endpoint open to anonymous
    # callers. Reused by updateDB() (called after this) for the deploy request.
    installToken=$(openssl rand -hex 32 2>/dev/null)
    [[ -z $installToken ]] && installToken=$(tr -dc 'a-f0-9' < /dev/urandom 2>/dev/null | head -c 64)
    dots "Creating config file"
    # Same multi-IP input as GH-650: on a multi-homed interface ${NET_fog_server_ip} is a
    # list, and these four constants each want one host. Interpolating the list
    # is valid PHP -- it just yields a two-line string -- so the install reports
    # success and then every TFTP/FTP/storage/WOL connection targets a hostname
    # that cannot resolve. Use the same first address the certificate's CN uses,
    # so the host FOG advertises and the host its cert is issued for agree.
    confighostip="${NET_fog_server_ip}"
    phpescsnmysqlpass="${DB_password//\\/\\\\}";   # Replace every \ with \\ ...
    phpescsnmysqlpass="${phpescsnmysqlpass//\'/\\\'}"   # and then every ' with \' for full PHP escaping
    # Derive the master's network CIDR (e.g. 192.168.1.0/24) from the chosen
    # interface so the default storage group can trust node-to-node status
    # calls from the local subnet out of the box. Stays empty (no extra trust)
    # if it cannot be derived; the schema migration then leaves the group alone.
    storageDefaultCidr=""
    storageTrustPrefix=$(getCidr "${NET_interface}")
    if [[ -n ${NET_fog_server_ip} && -n $storageTrustPrefix ]]; then
        storageTrustMask=$(cidr2mask "$storageTrustPrefix" 2>/dev/null)
        storageTrustNetwork=$(mask2network "${NET_fog_server_ip}" "$storageTrustMask" 2>/dev/null)
        [[ -n $storageTrustNetwork ]] && storageDefaultCidr="${storageTrustNetwork}/${storageTrustPrefix}"
    fi
    echo "<?php
/**
 * The main configuration FOG uses.
 *
 * PHP Version 5
 *
 * Constructs the configuration we need to run FOG.
 *
 * @category Config
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * The main configuration FOG uses.
 *
 * @category Config
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class Config
{
    /**
     * Calls the required functions to define items
     *
     * @return void
     */
    public function __construct()
    {
        global \$node;
        self::_dbSettings();
        self::_svcSetting();
        if (\$node == 'schema') {
            self::_initSetting();
        }
    }
    /**
     * Defines the database settings for FOG
     *
     * @return void
     */
    private static function _dbSettings()
    {
        define('DATABASE_TYPE', 'mysql'); // mysql or oracle
        define('DATABASE_HOST', '${DB_host}');
        define('DATABASE_NAME', '${DB_name}');
        define('DATABASE_USERNAME', '${DB_user}');
        define('DATABASE_PASSWORD', '$phpescsnmysqlpass');
        // Per-install secret allowing the schema deploy endpoint to run before
        // any user/database exists. Presented back by the installer; required
        // for any unauthenticated schema operation.
        define('FOG_SCHEMA_INSTALL_TOKEN', '$installToken');
    }
    /**
     * Defines the service settings
     *
     * @return void
     */
    private static function _svcSetting()
    {
        define('UDPSENDERPATH', '/usr/local/sbin/udp-sender');
        define('MULTICASTINTERFACE', '${NET_interface}');
        define('UDPSENDER_MAXWAIT', null);
    }
    /**
     * Initial values if fresh install are set here
     * NOTE: These values are only used on initial
     * installation to set the database values.
     * If this is an upgrade, they do not change
     * the values within the Database.
     * Please use FOG Configuration->FOG Settings
     * to change these values after everything is
     * setup.
     *
     * @return void
     */
    private static function _initSetting()
    {
        define('TFTP_HOST', \"${confighostip}\");
        define('TFTP_FTP_USERNAME', \"${SVC_user}\");
        define('TFTP_FTP_PASSWORD', '${SVC_password}');
        define('TFTP_PXE_KERNEL_DIR', \"${webdirdest}/service/ipxe/\");
        define('TFTP_ROOT_DIR', \"${tftpdirdst}\");
        define('PXE_KERNEL', 'bzImage');
        define('PXE_KERNEL_RAMDISK', 275000);
        define('USE_SLOPPY_NAME_LOOKUPS', true);
        define('MEMTEST_KERNEL', 'mt86plus_x86_64');
        define('PXE_IMAGE', 'init.xz');
        define('STORAGE_HOST', \"${confighostip}\");
        define('STORAGE_FTP_USERNAME', \"${SVC_user}\");
        define('STORAGE_FTP_PASSWORD', '${SVC_password}');
        define('STORAGE_DATADIR', '${STORAGE_image_share_path}/');
        define('STORAGE_DATADIR_CAPTURE', '${storageLocationCapture}');
        define('STORAGE_BANDWIDTHPATH', '${WEB_root}status/bandwidth.php');
        define('STORAGE_INTERFACE', '${NET_interface}');
        define('STORAGE_DEFAULT_CIDR', \"${storageDefaultCidr}\");
        define('CAPTURERESIZEPCT', 7);
        define('WEB_HOST', \"${confighostip}\");
        define('WEB_ROOT', '${WEB_root}');
        define('WOL_HOST', \"${confighostip}\");
        define('WOL_PATH', '/${WEB_root}wol/wol.php');
        define('WOL_INTERFACE', \"${NET_interface}\");
        define('SNAPINDIR', \"${snapindir}/\");
        define('QUEUESIZE', '10');
        define('CHECKIN_TIMEOUT', 600);
        define('USER_MINPASSLENGTH', 4);
        define('NFS_ETH_MONITOR', \"${NET_interface}\");
        define('UDPCAST_INTERFACE', \"${NET_interface}\");
        // Must be an even number! recommended between 49152 to 65535
        define('UDPCAST_STARTINGPORT', 63100);
        define('FOG_MULTICAST_MAX_SESSIONS', 64);
        define('FOG_JPGRAPH_VERSION', '2.3');
        define('FOG_REPORT_DIR', './reports/');
        define('FOG_CAPTUREIGNOREPAGEHIBER', true);
        define('FOG_THEME', 'default/fog.css');
    }
}" > "${webdirdest}/commons/config.class.php"
    # "skipOk", because this step is not finished until the permissions below
    # are set: the OK belongs to the errorStat after them, not to this one.
    # A failure here still aborts loudly -- that is the half skipOk does not
    # touch.
    errorStat $? "skipOk"
    # This file holds ${DB_password}, both FTP passwords (${SVC_password}, and
    # the storage node account the same value backs) and the per-install schema
    # bootstrap token generated above. It is written by a plain redirect, so
    # without this it lands at whatever umask root is carrying -- 0644 on every
    # distro we support. Every local account on the server could then read all
    # of them, and the FTP credential is fleet-wide, not per-server.
    #
    # Same reasoning as .fogsettings, which is chmod 0600 for the same two
    # passwords. This one cannot be 0600: unlike .fogsettings it is read by
    # PHP rather than by the installer -- the web tier includes it on every
    # request, and FOGPluginRunner and FOGRetentionRunner are the two daemons
    # that run as ${apacheuser} instead of root. Group read is what keeps all
    # three working, which is why the owner is set here and not left to the
    # chown -R at the end of this function: the mode is only meaningful once
    # the group is right, and a failure between the two should not leave a
    # window where it is neither.
    #
    # Storage nodes take this path too -- configureMinHttpd() calls this
    # function before stubbing out the management UI -- and a node's copy
    # carries the MASTER's database password, so the node case is the one that
    # matters most.
    chown ${apacheuser}:${apacheuser} "${webdirdest}/commons/config.class.php" >>$error_log 2>&1
    chmod 0640 "${webdirdest}/commons/config.class.php" >>$error_log 2>&1
    errorStat $?
    dots "Creating paths file"
    # GH-850: hand the installer's $fogprogramdir to the PHP runtime so
    # FOG_BASE_DIR is no longer a hardcoded string in system.class.php.
    #
    # This is a separate file from config.class.php on purpose. FOG_BASE_DIR is
    # needed by Initiator BEFORE the class autoloader is registered (the boot
    # file-list cache lives under FOG_CACHE_DIR), and config.class.php is a
    # class -- it only loads lazily, far too late. A plain define-only include
    # is the one thing that can be pulled in that early.
    #
    # Regenerated on every install/upgrade. If it is missing (pre-850 install,
    # or a hand-copied web tree) the PHP side falls back to /opt/fog, which is
    # exactly the behavior before this change.
    echo "<?php
/**
 * Filesystem paths chosen at install time.
 *
 * GENERATED BY THE FOG INSTALLER -- DO NOT EDIT.
 * Rewritten on every install/upgrade from \$fogprogramdir. To change the base
 * path, re-run the installer; editing this file alone will not move anything.
 *
 * Loaded by commons/init.php before the autoloader, so it must contain
 * defines and nothing else.
 */
define('FOG_BASE_DIR', '${fogprogramdir%/}');" > "${webdirdest}/commons/fogpaths.php"
    errorStat $?
    dots "Creating redirection index file"
    if [[ ! -f ${WEB_docroot}/index.php ]]; then
        echo "<?php
header('Location: ${WEB_root}index.php');
die();
?>" > ${WEB_docroot}/index.php && chown ${apacheuser}:${apacheuser} ${WEB_docroot}/index.php
        errorStat $?
    else
        echo "Skipped"
    fi
    downloadfiles
    if [[ ${FOG_os_id} -eq 2 ]]; then
        php -m | grep mysqlnd >>$error_log 2>&1
        if [[ ! $? -eq 0 ]]; then
            phpenmod mysqlnd >>$error_log 2>&1
            if [[ ! $? -eq 0 ]]; then
                if [[ -e /etc/php${WEB_php_version}/conf.d/mysqlnd.ini ]]; then
                    cp -f "/etc/php${WEB_php_version}/conf.d/mysqlnd.ini" "/etc/php${WEB_php_version}/mods-available/php${WEB_php_version}-mysqlnd.ini" >>$error_log 2>&1
                    phpenmod mysqlnd >>$error_log 2>&1
                fi
            fi
        fi
    fi
    dots "Enabling ${WEB_server_engine} and fpm services on boot"
    if [[ ${FOG_os_id} -eq 2 ]]; then
        if [[ $systemctl == yes ]]; then
            systemctl is-enabled --quiet ${WEB_server_engine} && true || systemctl enable ${WEB_server_engine} >>$error_log 2>&1
            systemctl is-enabled --quiet $phpfpm && true || systemctl enable $phpfpm >>$error_log 2>&1
        else
            sysv-rc-conf ${WEB_server_engine} on >>$error_log 2>&1
            sysv-rc-conf $phpfpm on >>$error_log 2>&1
        fi
    elif [[ $systemctl == yes ]]; then
        systemctl is-enabled --quiet ${WEB_server_engine} php-fpm && true || systemctl enable ${WEB_server_engine} php-fpm >>$error_log 2>&1
    elif [[ ${FOG_os_id} -eq 3 ]]; then
        # Alpine's unit is versioned (php-fpm83, php-fpm84), which is exactly
        # what $phpfpm holds; a bare "php-fpm" matches nothing there.
        rc-update add $phpfpm >>$error_log 2>&1
        rc-update add ${WEB_server_engine} >>$error_log 2>&1
    else
        chkconfig php-fpm on >>$error_log 2>&1
        chkconfig ${WEB_server_engine} on >>$error_log 2>&1
    fi
    errorStat $?
    createSSLCA
    dots "Changing permissions on apache log files"
    chmod +rx $apachelogdir
    chmod +rx $apacheerrlog
    chmod +rx $apacheacclog
    chown -R ${apacheuser}:${apacheuser} $webdirdest
    # fog_login_accepted.log and fog_login_failed.log are no longer created or
    # written (ADR 0021 merge 9). auditLog records both outcomes, for every
    # entry point rather than just the web form, and it is queryable.
    #
    # Existing files are deliberately left where they are: they are the only
    # record of a login from before the upgrade, and deleting somebody's
    # history as a side effect of an install is not this script's call.
    errorStat $?
    [[ -d /var/www/html/ && ! -e /var/www/html/fog/ ]] && ln -s "$webdirdest" /var/www/html/
    [[ -d /var/www/ && ! -e /var/www/fog ]] && ln -s "$webdirdest" /var/www/
    # FOGBase::_writeLogLine()'s destination. Not shipped in the repo, and the
    # rm -rf above takes it out on every install, so without this it is created
    # by whichever FOG process logs first after a deploy -- and ten of the
    # twelve daemons run as root while the web UI, FOGPluginRunner and
    # FOGRetentionRunner do not. Root normally wins that race at boot, leaving
    # a root-owned 0755 directory that denies every non-root writer for the
    # life of the install.
    #
    # Created here so the answer is settled before anything races for it. 2775
    # rather than 0755 because root daemons legitimately write here too, and
    # setgid is what makes the files they create carry the web group instead of
    # root's; _writeLogLine() widens the file mode to match. The chown below
    # covers this directory, which is why it is created ahead of it.
    mkdir -p "${webdirdest%/}/management/logs" >>$error_log 2>&1
    chmod 2775 "${webdirdest%/}/management/logs" >>$error_log 2>&1
    chown -R ${apacheuser}:${apacheuser} "$webdirdest"
    chown -R ${SVC_user}:${apacheuser} "$webdirdest/service/ipxe"
}
# The installer's scratch directory, as an ABSOLUTE path, or non-zero with a
# reason on stderr.
#
# Callers used to write this inline as
#
#     [[ ! -d ../tmp/ ]] && mkdir -p ../tmp/ >/dev/null 2>&1
#     cd ../tmp/
#
# which has three faults that compound into a bad failure. "../tmp/" resolves
# against whatever the ambient cwd happens to be; the mkdir error goes to
# /dev/null, so the one message explaining a failure is destroyed at the moment
# it occurs; and the cd is unguarded, so execution CONTINUES in the wrong
# directory. Because the copy step that follows was also relative, a failed cd
# downloaded 60-80MB of kernels into bin/ and then copied them from there -- the
# install "succeeded", left untracked binaries in the source tree, and reported
# only a bare "cd: ../tmp/: No such file or directory" that nothing could act on.
#
# Anchored on $workingdir, which installfog.sh captures with pwd before anything
# has a chance to move, so this is correct regardless of who cd'd where.
#
# The not-a-directory case is called out separately because it is the one that
# does NOT clear on a re-run: mkdir -p refuses when the path exists as a file or
# a dangling symlink, so every subsequent attempt fails the same way, and the
# generic message sends people looking for a permissions problem they do not have.
_installerTmpDir() {
    local d="${workingdir%/}/../tmp"
    if [[ -e $d && ! -d $d ]]; then
        echo "ERROR: ${d} exists but is not a directory." >&2
        echo "       Remove or rename it -- the installer needs it as scratch space." >&2
        return 1
    fi
    if ! mkdir -p "$d" 2>>$error_log; then
        echo "ERROR: could not create ${d}" >&2
        echo "       See ${error_log} for the reason." >&2
        return 1
    fi
    ( cd "$d" 2>/dev/null && pwd ) || {
        echo "ERROR: ${d} exists but could not be entered." >&2
        return 1
    }
}
downloadfiles() {
    local copypath="" tmpdir=""
    dots "Downloading kernel, init and fog-client binaries"
    clientVer="$(awk -F\' /"define\('FOG_CLIENT_VERSION'[,](.*)"/'{print $4}' ../packages/web/src/Base/System.php | tr -d '[[:space:]]')"
    fosURL="https://github.com/FOGProject/fos/releases/download"
    # Bounded like every other fetch here. This one takes --max-time as well as
    # --connect-timeout because it is a few KB of JSON, not a tarball, so there
    # is no legitimate slow-link case for it to break.
    fileversions=$(curl -sL --connect-timeout $inetConnectTimeout --max-time $inetMaxTime -H "Accept: application/vnd.github+json" 'https://api.github.com/repos/FOGProject/fos/releases/latest' | jq '.tag_name, .body' | paste -sd '|')
    tag_name="$(echo $fileversions | awk -F'|' '{print $1}')"
    fileversion="$(echo $fileversions | awk -F'|' '{print $2}')"
    kern_version=$(echo -e $fileversion | sed -n 's/.*Linux kernel \([0-9.]*\).*/\1/p')
    build_version=$(echo -e $fileversion | sed -n 's/.*Buildroot \([0-9.]*\).*/\1/p')
    fosLatestURL="https://github.com/FOGProject/fos/releases/latest/download"
    fogclientURL="https://github.com/FOGProject/fog-client/releases/download"
    # Fail here, loudly, rather than downloading into the source tree. The old
    # code carried on after a failed cd and the install appeared to succeed.
    if ! tmpdir="$(_installerTmpDir)"; then
        echo "Failed"
        echo " * Could not prepare the installer's scratch directory."
        exit 1
    fi
    # Every cp below goes through $copypath, so the copy step no longer depends
    # on the cwd either -- belt as well as braces. copypath was already the hook
    # for this and had only ever been the empty string.
    copypath="${tmpdir}/"
    local cwd="$(pwd)"
    if ! cd "$tmpdir"; then
        echo "Failed"
        echo " * Could not enter ${tmpdir}."
        exit 1
    fi
    if [[ $version =~ ^[0-9]\.[0-9]\.[0-9]+$ ]]; then
        urls=( "${fosURL}/${version}/init.xz" "${fosURL}/${version}/init_32.xz" "${fosURL}/${version}/bzImage" "${fosURL}/${version}/bzImage32" "${fogclientURL}/${clientVer}/FOGService.msi" "${fogclientURL}/${clientVer}/SmartInstaller.exe" )
        urls+=( "${fosURL}/${version}/arm_init.cpio.gz" "${fosURL}/${version}/arm_Image" )
    else
        urls=( "${fosLatestURL}/init.xz" "${fosLatestURL}/init_32.xz" "${fosLatestURL}/bzImage" "${fosLatestURL}/bzImage32" "${fogclientURL}/${clientVer}/FOGService.msi" "${fogclientURL}/${clientVer}/SmartInstaller.exe" )
        urls+=( "${fosLatestURL}/arm_init.cpio.gz" "${fosLatestURL}/arm_Image" )
    fi
    for url in "${urls[@]}"; do
        checksum=1
        cnt=0
        filename=$(basename -- "$url")
        hashfile="${filename}.sha256"
        baseurl=$(dirname -- "$url")
        hashurl="${baseurl}/${hashfile}"
        # make sure we download the most recent hash file to start with
        if [[ -f $hashfile && ! $version =~ ^[0-9]\.[0-9]\.[0-9]+$ ]]; then
            rm -f $hashfile
            curl --silent -OL --connect-timeout $inetConnectTimeout \
                --speed-time 30 --speed-limit 1024 $hashurl >>$error_log 2>&1
        fi
        # Eight URLs, ten rounds, two curls each: 160 connects, none of them
        # bounded, all of them silent under one "Downloading kernel, init and
        # fog-client binaries" line. On a host with no route out that was the
        # single longest stall the installer could produce. --connect-timeout
        # bounds an unreachable host and --speed-time/--speed-limit a transfer
        # that opens and then stops; --max-time is deliberately absent, because
        # these are multi-megabyte kernels and a slow link must still finish.
        # When checkInternetConnection has already established the host is
        # unreachable there is nothing to retry FOR, so make one attempt.
        tries=10
        [[ $internet_ok -ne 1 ]] && tries=1
        while [[ $checksum -ne 0 && $cnt -lt $tries ]]; do
            [[ -f $hashfile ]] && sha256sum -c $hashfile >>$error_log 2>&1
            checksum=$?
            if [[ $checksum -ne 0 ]]; then
                # No -k, same reasoning as fetchipxeasset(): the hash file
                # travels the same connection as the payload, so skipping
                # verification here voids the checksum too.
                curl --silent -OL --connect-timeout $inetConnectTimeout \
                    --speed-time 30 --speed-limit 1024 $url >>$error_log
                curl --silent -OL --connect-timeout $inetConnectTimeout \
                    --speed-time 30 --speed-limit 1024 $hashurl >>$error_log
            fi
            let cnt+=1
        done
        if [[ $checksum -ne 0 ]]; then
            echo " * Could not download $filename properly"
            [[ -z $exitFail ]] && exit 1
        fi
    done
    echo "Done"
    dots "Copying binaries to destination paths"
    cp -vf ${copypath}bzImage ${webdirdest}/service/ipxe/ >>$error_log 2>&1 || errorStat $?
    attr -s version -V $kern_version ${webdirdest}/service/ipxe/bzImage >>$error_log 2>&1 || errorStat $?
    attr -s tag_name -V $tag_name ${webdirdest}/service/ipxe/bzImage >>$error_log 2>&1 || errorStat $?
    _stampFogSum ${webdirdest}/service/ipxe/bzImage
    cp -vf ${copypath}bzImage32 ${webdirdest}/service/ipxe/ >>$error_log 2>&1 || errorStat $?
    attr -s version -V $kern_version ${webdirdest}/service/ipxe/bzImage32 >>$error_log 2>&1 || errorStat $?
    attr -s tag_name -V $tag_name ${webdirdest}/service/ipxe/bzImage32 >>$error_log 2>&1 || errorStat $?
    _stampFogSum ${webdirdest}/service/ipxe/bzImage32
    cp -vf ${copypath}arm_Image ${webdirdest}/service/ipxe/ >>$error_log 2>&1 || errorStat $?
    attr -s version -V $kern_version ${webdirdest}/service/ipxe/arm_Image >>$error_log 2>&1 || errorStat $?
    attr -s tag_name -V $tag_name ${webdirdest}/service/ipxe/arm_Image >>$error_log 2>&1 || errorStat $?
    _stampFogSum ${webdirdest}/service/ipxe/arm_Image
    cp -vf ${copypath}init.xz ${webdirdest}/service/ipxe/ >>$error_log 2>&1 || errorStat $?
    attr -s version -V $build_version ${webdirdest}/service/ipxe/init.xz >>$error_log 2>&1 || errorStat $?
    attr -s tag_name -V $tag_name ${webdirdest}/service/ipxe/init.xz >>$error_log 2>&1 || errorStat $?
    _stampFogSum ${webdirdest}/service/ipxe/init.xz
    cp -vf ${copypath}init_32.xz ${webdirdest}/service/ipxe/ >>$error_log 2>&1 || errorStat $?
    attr -s version -V $build_version ${webdirdest}/service/ipxe/init_32.xz >>$error_log 2>&1 || errorStat $?
    attr -s tag_name -V $tag_name ${webdirdest}/service/ipxe/init_32.xz >>$error_log 2>&1 || errorStat $?
    _stampFogSum ${webdirdest}/service/ipxe/init_32.xz
    cp -vf ${copypath}arm_init.cpio.gz ${webdirdest}/service/ipxe/ >>$error_log 2>&1 || errorStat $?
    attr -s version -V $build_version ${webdirdest}/service/ipxe/arm_init.cpio.gz >>$error_log 2>&1 || errorStat $?
    attr -s tag_name -V $tag_name ${webdirdest}/service/ipxe/arm_init.cpio.gz >>$error_log 2>&1 || errorStat $?
    _stampFogSum ${webdirdest}/service/ipxe/arm_init.cpio.gz
    cp -vf ${copypath}FOGService.msi ${copypath}SmartInstaller.exe ${webdirdest}/client/ >>$error_log 2>&1
    errorStat $?
    cd "$cwd"
    _ensureSecureBootKeys
    _ensureSecureBootPlatformKeys
    _resignKernels
    # Re-stamp AFTER signing. _resignKernels rewrites each kernel in place, so
    # a checksum taken at download time no longer matches the file on disk --
    # which made the next run report bzImage32/arm_Image as hand-installed on a
    # server where nobody had touched them. Stamp what is actually there once
    # everything that modifies it has run.
    local _k
    for _k in bzImage bzImage32 arm_Image init.xz init_32.xz arm_init.cpio.gz; do
        _stampFogSum "${webdirdest}/service/ipxe/${_k}"
    done
    _installSecureBootSigner
    _publishSecureBootKit
    _publishSecureBootAuthVars
}
# Copy an admin-supplied Secure Boot pair somewhere the installer cannot
# destroy, and point ${PKI_sb_codesign_key}/${PKI_sb_codesign_cert} at the copy.
#
# The gap this closes: --secure-boot-key/--secure-boot-cert are persisted to
# .fogsettings verbatim and _ensureSecureBootKeys() then trusts that path
# forever, but nothing ever copies the file anywhere. An admin who parks the
# pair under $webdirdest -- not unreasonable, it is where the enrollment kit is
# published -- loses it to configureHttpd()'s rm -rf $webdirdest, in the SAME
# run that first accepted the flags, before _resignKernels() ever reads it.
#
# Copied to admin-MOK.* rather than MOK.*, deliberately. MOK.key/MOK.pem are
# where _ensureSecureBootKeys() keeps FOG's OWN generated pair, and that pair
# is never regenerated precisely because every client that already enrolled it
# would be stranded. Writing an admin's key over that path would destroy it
# with no backup and no way back. Continuity across later runs comes from
# .fogsettings holding the new path, not from reusing the filename.
#
# The original file the admin pointed at is never modified -- this only
# decides which copy gets used from here on. Idempotent: once .fogsettings
# records the copy, every later run sees a path already under the Secure
# Boot PKI zone dir and does nothing.
preserveSecureBootAdminFiles() {
    [[ -z ${PKI_sb_codesign_key} || -z ${PKI_sb_codesign_cert} ]] && return 0
    local keydir="$(_pkiZoneDir secureboot)"
    local destkey="${keydir}/admin-MOK.key"
    local destcert="${keydir}/admin-MOK.pem"
    local st=0

    # Already somewhere this installer never deletes -- including FOG's own
    # generated pair, which must be left exactly where it is.
    case "$(readlink -f "${PKI_sb_codesign_key}" 2>/dev/null)" in
        "$(readlink -f "$keydir" 2>/dev/null)"/*) return 0 ;;
    esac

    dots "Preserving admin-supplied Secure Boot key"
    mkdir -p "$keydir" >>$error_log 2>&1 || st=1
    chown root:root "$keydir" >>$error_log 2>&1
    chmod 0700 "$keydir" >>$error_log 2>&1
    cp -f "${PKI_sb_codesign_key}" "$destkey" >>$error_log 2>&1 || st=1
    cp -f "${PKI_sb_codesign_cert}" "$destcert" >>$error_log 2>&1 || st=1
    if [[ $st -ne 0 ]]; then
        echo "Failed"
        echo " * Could not copy the Secure Boot signing pair into ${keydir}."
        echo " * Leaving --secure-boot-key/--secure-boot-cert pointed at the"
        echo "   originals. If either lives under ${webdirdest}, MOVE IT NOW --"
        echo "   the web tree is rebuilt later in this run. See $error_log."
        return 0
    fi
    chown root:root "$destkey" "$destcert" >>$error_log 2>&1
    # Key restricted, certificate public by design -- it is the thing handed
    # out for enrollment. Mirrors _ensureSecureBootKeys()'s own permissions.
    chmod 0600 "$destkey" >>$error_log 2>&1
    chmod 0644 "$destcert" >>$error_log 2>&1
    PKI_sb_codesign_key="$destkey"
    PKI_sb_codesign_cert="$destcert"
    errorStat 0
}
# Generate the Secure Boot signing key when the admin has not supplied one.
#
# Signing used to require --secure-boot-key/--secure-boot-cert, which meant it
# was off unless someone already knew to ask for it -- so on a stock server the
# Secure Boot page had no fingerprint to show and no enrollment kit to hand out,
# and the feature was effectively invisible. Generating a key by default makes
# it present everywhere; enrolling it on a client is still a deliberate act by
# someone physically at the machine, so defaulting this on grants no trust by
# itself.
#
# The key NEVER regenerates once it exists. A fresh key silently invalidates
# enrollment on every machine that already trusted the old one, and nothing
# surfaces that until a client fails to boot -- long after the install that
# caused it. So an existing pair is always reused, and --recreate-keys
# deliberately does not reach this.
# The Secure Boot zone: an intermediate CA whose certificate is what gets
# enrolled in firmware, issuing a short-lived leaf that actually signs kernels.
#
# The flat model enrolls the SIGNING certificate itself -- a self-signed leaf
# that can issue nothing. That makes the thing you must never change and the
# thing you want to rotate the same object: replacing the signing key means a
# physical MokManager trip to every machine, and a storage node cannot sign at
# all without being handed the one key the whole fleet trusts.
#
# Enrolling the intermediate instead means the leaf can be rotated, revoked, or
# issued per node and the fleet keeps booting, because firmware trusts the
# issuer rather than the specific signer. sbsign --addcert ships the
# intermediate inside the signature so shim can build the chain.
#
# Sets TWO variables where flat sets one:
#   secureBootKey/secureBootCert -> the LEAF. What sbsign signs with.
#   secureBootMokCert            -> the INTERMEDIATE. What firmware enrolls,
#                                   what MOK.der publishes, what goes in db.
# In flat mode secureBootMokCert is simply the same file as secureBootCert, so
# nothing downstream has to branch.
createSecureBootIntermediateCA() {
    local sbdir="$(_pkiZoneDir secureboot)"
    local cadir="${sbdir}/ca"
    local leafdir="${sbdir}/leaf"
    local keydir="${fogprogramdir}/secureboot"
    local f st=0

    # Secure Boot runs from downloadfiles(), which reaches this BEFORE
    # createSSLCA() has run -- so neither ${PKI_client_cert_dir} nor the root CA exists yet.
    # Resolving the path here rather than assuming createSSLCA got there first
    # is what keeps the root out of "/CA/root" at the filesystem root.
    #
    # _collectPkiNames also has to be reachable from here, not only from
    # createSSLCA(): this CA's name constraints are fixed at mint time, and if
    # this is the first run on this server, this function mints them before
    # createSSLCA() ever gets a turn.
    _resolveSslPath
    _collectPkiNames
    _resolveRootCA
    # An install that already ran the flat ${fogprogramdir}/secureboot/{ca,leaf}
    # layout migrates its CA and leaf material in place -- same key/cert, one
    # more hop -- rather than minting fresh ones it doesn't need. $keydir is
    # the flat MOK's own directory (_ensureSecureBootKeys), untouched by this.
    #
    # Skipped under --recreate-CA: _resolveRootCA just wiped the new zone dir
    # deliberately, and resurrecting the old material here would silently
    # undo that. The old directories are removed outright instead, so a
    # recreate does not leave stale material an admin might mistake for live.
    if [[ $recreateCA == yes ]]; then
        rm -rf "${keydir}/ca" "${keydir}/leaf" >>$error_log 2>&1
    else
        mkdir -p "$cadir" "$leafdir" >>$error_log 2>&1
        for f in .fogSBCA.key .fogSBCA.pem .fogSBCA.der int.cnf int.csr; do
            [[ -f "${keydir}/ca/${f}" && ! -f "${cadir}/${f}" ]] && \
                mv "${keydir}/ca/${f}" "${cadir}/${f}" >>$error_log 2>&1
        done
        for f in sign.key sign.pem sign.csr sign.cnf; do
            [[ -f "${keydir}/leaf/${f}" && ! -f "${leafdir}/${f}" ]] && \
                mv "${keydir}/leaf/${f}" "${leafdir}/${f}" >>$error_log 2>&1
        done
    fi
    # A root that cannot anchor an intermediate leaves Secure Boot exactly as it
    # was: a self-signed MOK. Signing beneath it would produce a chain that
    # verifies nowhere, and the failure would surface as a machine that will not
    # boot rather than as an installer error.
    if [[ ${rootCAIssuer:-1} -ne 1 ]]; then
        return 1
    fi
    if [[ ! -f "${cadir}/.fogSBCA.pem" ]]; then
        dots "Creating FOG Secure Boot CA"
        # codeSigning alone: an EKU on a CA constrains what it may issue, so
        # this intermediate can never mint a server certificate however its
        # leaf is written.
        _issueIntermediateCA "FOG Secure Boot CA" "$cadir" ".fogSBCA.key" ".fogSBCA.pem" \
            "extendedKeyUsage = codeSigning" "FOG Secure Boot" sbCAKeyOffline
        errorStat $?
    fi
    # A DER sibling of the intermediate, right next to .fogSBCA.pem in the PKI
    # zone dir -- not only inside the web-servable kit _publishSecureBootKit
    # stages. Without this, confirming what got enrolled (openssl, sha1sum, a
    # comparison against what MokManager shows) means reaching into
    # $webdirdest instead of the canonical PKI tree. Outside the "only if
    # missing" block above so an install upgrading onto this code backfills
    # it without touching the CA's own key/cert.
    if [[ -f "${cadir}/.fogSBCA.pem" && ! -f "${cadir}/.fogSBCA.der" ]]; then
        openssl x509 -in "${cadir}/.fogSBCA.pem" -outform der \
            -out "${cadir}/.fogSBCA.der" >>$error_log 2>&1
        chown root:root "${cadir}/.fogSBCA.der" >>$error_log 2>&1
        chmod 0644 "${cadir}/.fogSBCA.der" >>$error_log 2>&1
    fi
    if [[ ! -f "${leafdir}/sign.key" || ! -f "${leafdir}/sign.pem" ]]; then
        # Signing a leaf needs the Secure Boot CA's own key, same as a new
        # intermediate needs the root's -- say so rather than letting openssl
        # fail on a missing file, since the fix is the same shape: restore the
        # key, re-run, take it back offline.
        if [[ ${sbCAKeyOffline:-0} -eq 1 ]]; then
            echo " * Cannot issue the Secure Boot code-signing certificate: the"
            echo "   Secure Boot CA private key is not on this server (only"
            echo "   ${cadir}/.fogSBCA.pem is present)."
            echo " * Restore it to:"
            echo "     ${cadir}/.fogSBCA.key"
            echo "   re-run the installer, then move it back to your vault."
            return 1
        fi
        dots "Creating Secure Boot code signing certificate"
        mkdir -p "$leafdir" >>$error_log 2>&1 || st=1
        chmod 0700 "$leafdir" >>$error_log 2>&1
        # Same extension profile the flat MOK already uses -- CA:FALSE plus the
        # codeSigning EKU -- written as a config file rather than -addext for
        # the same reason: -addext needs OpenSSL 1.1.1+ and the older RHEL
        # variants this installer supports ship 1.0.2.
        #
        # The subjectAltName is a guard, not decoration. When the issuing CA
        # carries DNS name constraints and the certificate beneath it has no
        # dNSName SAN, OpenSSL falls back to matching the subject CN against
        # those constraints. Measured against OpenSSL 3.5: a CN of
        # "evil.example.com" under a corp.local constraint is REJECTED, while
        # this CN passes only because "FOG Project Secure Boot Signing" is not
        # hostname-shaped and so is never treated as a DNS name.
        #
        # That exemption is a parsing quirk to depend on. Rename this CN to
        # anything hostname-shaped and every machine in the fleet stops
        # booting, discovered at the machines -- shim links OpenSSL and
        # verifies the chain itself. A permitted DNS name here both satisfies
        # the constraint and stops the CN fallback from ever running.
        #
        # Resolved into a variable first, and via _certLeafName() rather than
        # ${NET_hostname} raw. This line was `DNS:${NET_hostname:-$(hostname)}`,
        # and on a server with no hostname both halves are empty: the extension
        # string becomes a bare `DNS:`, OpenSSL refuses to parse it, and the
        # errorStat below kills the installer with the web server already
        # stopped and not yet restarted. _certLeafName() cannot return empty.
        local sbSanName
        sbSanName=$(_certLeafName)
        cat > "${leafdir}/sign.cnf" << EOF
[ req ]
distinguished_name = req_dn
prompt             = no

[ req_dn ]
CN = FOG Project Secure Boot Signing
O  = FOG Project
OU = FOG Secure Boot

[ v3_sign ]
basicConstraints = critical,CA:FALSE
extendedKeyUsage = codeSigning
subjectKeyIdentifier = hash
subjectAltName   = DNS:${sbSanName}
EOF
        openssl req -new -sha256 -nodes -newkey rsa:2048 \
            -config "${leafdir}/sign.cnf" -keyout "${leafdir}/sign.key" \
            -out "${leafdir}/sign.csr" >>$error_log 2>&1 || st=1
        # Shorter than the intermediate's 30 years, not because it has to be,
        # but because rotating it is cheap -- renewal-helper (packages/pki)
        # re-signs it on request with no firmware re-enrollment needed, since
        # what's enrolled is the intermediate above it, not this leaf.
        openssl x509 -req -in "${leafdir}/sign.csr" \
            -CA "${cadir}/.fogSBCA.pem" -CAkey "${cadir}/.fogSBCA.key" \
            -CAcreateserial -sha256 -days 1825 -extensions v3_sign \
            -extfile "${leafdir}/sign.cnf" -out "${leafdir}/sign.pem" >>$error_log 2>&1 || st=1
        chown root:root "${leafdir}/sign.key" "${leafdir}/sign.pem" >>$error_log 2>&1
        chmod 0600 "${leafdir}/sign.key" >>$error_log 2>&1
        chmod 0644 "${leafdir}/sign.pem" >>$error_log 2>&1
        errorStat $st
    fi
    # Report failure rather than naming files that were never written. The
    # caller's fallback is the self-signed MOK, which is a working server; a
    # ${PKI_sb_codesign_key} pointing at nothing is a server that silently ships
    # unsigned kernels.
    if [[ ! -f "${cadir}/.fogSBCA.pem" || ! -f "${leafdir}/sign.pem" ]]; then
        if [[ ${rootCAKeyOffline:-0} -eq 1 ]]; then
            echo " * Cannot issue the Secure Boot CA: the CA private key is not"
            echo "   on this server. Restore it to:"
            echo "     ${PKI_root_ca_key}"
            echo "   re-run the installer, then move it back to your vault."
        fi
        return 1
    fi
    PKI_sb_codesign_key="${leafdir}/sign.key"
    PKI_sb_codesign_cert="${leafdir}/sign.pem"
    PKI_sb_ca_cert="${cadir}/.fogSBCA.pem"
}
_ensureSecureBootKeys() {
    local keydir="$(_pkiZoneDir secureboot)"
    local oldkeydir="${fogprogramdir}/secureboot"
    local key="${keydir}/MOK.key"
    local cert="${keydir}/MOK.pem"
    local f

    # ${PKI_sb_enabled}=0 is deliberately NOT handled here any more, and the keys are
    # minted for an opted-out server exactly as for any other.
    #
    # What the opt-out turns off is ENROLLMENT -- publishing MOK.der and the
    # PK/KEK/db variable updates, and with them the PXE menu entry, which
    # IpxeBootMenu gates on service/secureboot/MOK.der existing
    # (bootmenu.class.php:2089). It does not turn off SIGNING, because an
    # appended PE signature is inert on a machine booting with Secure Boot off
    # -- which is every machine on an opted-out server -- and costs nothing.
    # Leaving the binaries unsigned instead only means that the day anyone does
    # enroll, or moves one of these files onto a machine that already has Secure
    # Boot on, the file is useless and nothing on this server can fix it without
    # a re-install.
    #
    # So the gate every downstream signer already has -- "is there a key?" --
    # now answers yes on every server, and _signLocalIpxe/_resignRefind/
    # _resignKernels/_resignCustomKernels sign unconditionally. The opt-out is
    # re-applied in _ensureSecureBootPlatformKeys and _publishSecureBootKit,
    # which are the two functions that publish enrollment material.
    #
    # An admin-supplied pair always wins and is never touched or overwritten.
    # Their certificate is also what gets enrolled, exactly as before -- an
    # admin bringing their own Secure Boot intermediate points
    # --secure-boot-cert at it and --secure-boot-key at the leaf's key.
    #
    # ${PKI_sb_codesign_key}/${PKI_sb_codesign_cert} are persisted to .fogsettings on every
    # run (see writeUpdateFile) precisely so an admin's choice, or FOG's own
    # previously-resolved leaf, carries forward without being re-supplied --
    # but that means a value read back from .fogsettings is indistinguishable
    # from one just passed on the command line. Require the files to still
    # exist before trusting either: without this, deleting the Secure Boot
    # directory to force a fresh key just left the stale path in
    # .fogsettings, which got trusted here and failed downstream instead,
    # with a "cannot find MOK.key" nowhere near the actual cause.
    if [[ -n ${PKI_sb_codesign_key} && -n ${PKI_sb_codesign_cert} ]]; then
        if [[ -f ${PKI_sb_codesign_key} && -f ${PKI_sb_codesign_cert} ]]; then
            [[ -z ${PKI_sb_ca_cert} ]] && PKI_sb_ca_cert="${PKI_sb_codesign_cert}"
            return 0
        fi
        echo " * The configured Secure Boot key/certificate is missing on disk:"
        echo "     ${PKI_sb_codesign_key}"
        echo "   Treating it as unset and generating a new one."
        PKI_sb_codesign_key=""
        PKI_sb_codesign_cert=""
        PKI_sb_ca_cert=""
    fi
    # An install that already ran the flat ${fogprogramdir}/secureboot layout
    # migrates the flat MOK material in place -- same key/cert, one more hop
    # -- before the existence checks below run against the new location. Not
    # doing this first would make the "no MOK yet" branch fire on a server
    # that has one, minting a fresh key and stranding every machine that
    # already enrolled the old one.
    mkdir -p "$keydir" >>$error_log 2>&1
    for f in MOK.key MOK.pem mok.cnf; do
        [[ -f "${oldkeydir}/${f}" && ! -f "${keydir}/${f}" ]] && \
            mv "${oldkeydir}/${f}" "${keydir}/${f}" >>$error_log 2>&1
    done
    # The intermediate is enrolled and a leaf signs. See
    # createSecureBootIntermediateCA.
    #
    # Deliberately NOT guarded on the flat MOK's absence. A server that already
    # generated a self-signed MOK is moved onto the intermediate too, and any
    # machine that enrolled the old key has to enroll once more -- which is the
    # whole reason this lands before Secure Boot reaches a stable release. The
    # flat MOK is a signing certificate that can issue nothing, so leaving a
    # server on it means it can never rotate a signing key, and never let a
    # storage node sign at all, without a firmware trip to every machine. That
    # cost only grows.
    #
    # The old MOK.key/MOK.pem are left on disk untouched, so an admin who needs
    # to re-sign something with the previously enrolled key still can.
    if createSecureBootIntermediateCA; then
        # Suppressed on an opted-out server: it publishes no MOK.der and offers
        # no "Enroll Secure Boot Key" menu item, so both routes this names are
        # absent and the notice would only send an admin looking for a 404. The
        # keys are still minted and the binaries still signed -- see the top of
        # this function -- there is simply nothing to enroll them with yet.
        if [[ -f $key && -f $cert && ${PKI_sb_enabled:-yes} == yes ]]; then
            echo
            echo "  ###################################################################"
            echo "  # NOTICE: this server's Secure Boot trust has moved from a self-  #"
            echo "  # signed key to an issuing CA, so that signing keys can be        #"
            echo "  # rotated and storage nodes can sign without holding the fleet's  #"
            echo "  # one trusted key.                                                #"
            echo "  #                                                                 #"
            echo "  # Any machine that already enrolled the previous MOK must enroll    #"
            echo "  # once more. After that, no future signing-key change needs a     #"
            echo "  # firmware trip.                                                  #"
            echo "  #                                                                 #"
            echo "  #   ${WEB_url_proto}://${NET_fog_server_ip}${WEB_root}service/secureboot/MOK.der"
            echo "  #                                                                 #"
            echo "  # or boot the 'Enroll Secure Boot Key' PXE menu item.             #"
            echo "  # The previous key is still on disk at:                           #"
            echo "  #   ${keydir}/MOK.pem                                             #"
            echo "  ###################################################################"
            echo
        fi
        return 0
    fi
    # Falls through only when the CA cannot anchor an intermediate, which
    # createSecureBootIntermediateCA has already explained. Behave exactly as
    # before in that case.
    if [[ -f $key && -f $cert ]]; then
        PKI_sb_codesign_key="$key"
        PKI_sb_codesign_cert="$cert"
        PKI_sb_ca_cert="$cert"
        return 0
    fi

    dots "Generating Secure Boot signing key"
    mkdir -p "$keydir" >>$error_log 2>&1
    # 0700 root:root. The web user signs through the fog-sign-kernel sudo
    # helper and must never be able to read the key itself -- that separation
    # is the whole reason the helper takes no arguments. $fogprogramdir is
    # never inside $webdirdest, so nothing here is web-reachable either.
    chown root:root "$keydir" >>$error_log 2>&1
    chmod 0700 "$keydir" >>$error_log 2>&1
    # Written as a config file rather than passed with -addext: -addext needs
    # OpenSSL 1.1.1+, and the older RHEL variants this installer still supports
    # ship 1.0.2, where it fails with an unhelpful usage error. The same reason
    # createSSLCA() writes req.cnf/ca.cnf instead of using -addext.
    cat > "${keydir}/mok.cnf" << EOF
[ req ]
distinguished_name = req_dn
prompt             = no
x509_extensions    = v3_mok

[ req_dn ]
CN = FOG Project Secure Boot Signing
O  = FOG Project
OU = FOG Secure Boot

[ v3_mok ]
basicConstraints = critical,CA:FALSE
extendedKeyUsage = codeSigning
subjectKeyIdentifier = hash
EOF
    # 30 years, same as the real CAs: whatever CA:FALSE says, this is the
    # enrolled firmware trust anchor in this fallback path, and rotating it
    # costs the same fleet-wide re-enrollment a real CA's renewal would.
    if ! openssl req -x509 -new -nodes -newkey rsa:2048 -sha256 -days 10950 \
            -config "${keydir}/mok.cnf" -keyout "$key" -out "$cert" \
            >>$error_log 2>&1; then
        echo "Failed"
        echo " * Could not generate a Secure Boot signing key. The FOS kernels"
        echo "   will be left unsigned and Secure Boot clients will not boot."
        echo "   See $error_log."
        rm -f "$key" "$cert" >>$error_log 2>&1
        PKI_sb_codesign_key=""
        PKI_sb_codesign_cert=""
        return 0
    fi
    chown root:root "$key" "$cert" >>$error_log 2>&1
    chmod 0600 "$key" >>$error_log 2>&1
    # The certificate is public by design -- it is the thing published in the
    # enrollment kit -- so only the key is restricted.
    chmod 0644 "$cert" >>$error_log 2>&1
    PKI_sb_codesign_key="$key"
    PKI_sb_codesign_cert="$cert"
    # Flat: the signing certificate IS what firmware enrolls.
    PKI_sb_ca_cert="$cert"
    echo "Done"
}
# Generate this server's Secure Boot PLATFORM keys (PK and KEK).
#
# Separate from _ensureSecureBootKeys because these are a different kind of key
# doing a different job. MOK.key signs FOS kernels. PK/KEK sign nothing that ever
# executes -- they exist only to authorize updates to a client's own Secure Boot
# databases, which is what makes the automatic (Setup Mode) enrollment path in
# fos ADR-0009 possible at all.
#
# Why the server needs its own PK when a client in Setup Mode enforces nothing:
# once the client leaves Setup Mode it is in User Mode with OUR PK, and from that
# point the UEFI spec requires a KEK-signed update to touch db and a PK-signed
# update to touch KEK. Holding those keys is what lets this same server push a db
# change to an already-enrolled fleet later without another firmware trip. A
# server that enrolled a throwaway PK would strand every client it enrolled.
#
# Like the MOK key, these NEVER regenerate once they exist -- a new PK is not
# accepted by any client already carrying the old one, and the failure surfaces
# as an unbootable machine long after the install that caused it.
_ensureSecureBootPlatformKeys() {
    local keydir="$(_pkiZoneDir secureboot)"
    local oldkeydir="${fogprogramdir}/secureboot"
    local pkKey="${keydir}/PK.key"
    local pkCert="${keydir}/PK.pem"
    local kekKey="${keydir}/KEK.key"
    local kekCert="${keydir}/KEK.pem"
    local subject f

    secureBootPKKey=""
    secureBootPKCert=""
    secureBootKEKKey=""
    secureBootKEKCert=""

    # The platform keys exist for one job -- signing the PK/KEK/db variable
    # updates a client writes in Setup Mode -- so this is one of the two places
    # the ${PKI_sb_enabled} opt-out is applied. Signing keys are minted regardless
    # (see _ensureSecureBootKeys); enrollment material is not. Blanked rather
    # than merely skipped so _publishSecureBootAuthVars takes its "no platform
    # keys" branch and clears any blobs a previous, non-opted-out run left.
    [[ ${PKI_sb_enabled:-yes} != yes ]] && return 0
    # No signing key means the whole feature is opted out; there is nothing for
    # a platform key to authorize.
    [[ -z ${PKI_sb_codesign_key} || -z ${PKI_sb_codesign_cert} ]] && return 0

    # An install that already ran the flat ${fogprogramdir}/secureboot layout
    # migrates the platform keys in place -- same key/cert, one more hop --
    # before the existence check below runs against the new location. These
    # never regenerate once they exist (see the note above this function), so
    # missing this would strand every client that already trusts them.
    mkdir -p "$keydir" >>$error_log 2>&1
    for f in PK.key PK.pem KEK.key KEK.pem; do
        [[ -f "${oldkeydir}/${f}" && ! -f "${keydir}/${f}" ]] && \
            mv "${oldkeydir}/${f}" "${keydir}/${f}" >>$error_log 2>&1
    done

    if [[ -f $pkKey && -f $pkCert && -f $kekKey && -f $kekCert ]]; then
        secureBootPKKey="$pkKey"
        secureBootPKCert="$pkCert"
        secureBootKEKKey="$kekKey"
        secureBootKEKCert="$kekCert"
        return 0
    fi

    dots "Generating Secure Boot platform keys"
    mkdir -p "$keydir" >>$error_log 2>&1
    chown root:root "$keydir" >>$error_log 2>&1
    chmod 0700 "$keydir" >>$error_log 2>&1

    # Named after the server so an admin standing at a client's firmware screen
    # can tell WHICH FOG server owns the platform key it is now carrying. With a
    # generic CN, a site running two FOG servers has no way to tell them apart
    # from the machine that got enrolled.
    subject="FOG Project ($(_certLeafName))"
    # 4096-bit and no extendedKeyUsage: these are trust anchors in a firmware
    # database, not code-signing certificates, and some firmware rejects a PK
    # carrying a codeSigning EKU. 3650 days matches the MOK key -- an expired PK
    # does not stop a client booting (UEFI does not check validity dates on db
    # entries) but it does confuse tooling.
    if ! openssl req -x509 -new -nodes -newkey rsa:4096 -sha256 -days 3650 \
            -subj "/CN=${subject} Platform Key/O=FOG Project/OU=FOG Secure Boot" \
            -keyout "$pkKey" -out "$pkCert" >>$error_log 2>&1 ||
       ! openssl req -x509 -new -nodes -newkey rsa:4096 -sha256 -days 3650 \
            -subj "/CN=${subject} Key Exchange Key/O=FOG Project/OU=FOG Secure Boot" \
            -keyout "$kekKey" -out "$kekCert" >>$error_log 2>&1; then
        echo "Failed"
        echo " * Could not generate the Secure Boot platform keys. Automatic"
        echo "   enrollment will be unavailable; the MOK paths are unaffected."
        echo "   See $error_log."
        rm -f "$pkKey" "$pkCert" "$kekKey" "$kekCert" >>$error_log 2>&1
        return 0
    fi
    chown root:root "$pkKey" "$pkCert" "$kekKey" "$kekCert" >>$error_log 2>&1
    chmod 0600 "$pkKey" "$kekKey" >>$error_log 2>&1
    chmod 0644 "$pkCert" "$kekCert" >>$error_log 2>&1
    secureBootPKKey="$pkKey"
    secureBootPKCert="$pkCert"
    secureBootKEKKey="$kekKey"
    secureBootKEKCert="$kekCert"
    echo "Done"
}
# Publish the MOK enrollment kit under the web root.
#
# Only the *certificate* is published -- it is public by design, and is the
# thing you are meant to distribute. The private key stays where the admin put
# it and is never copied anywhere near the web root; that separation is the one
# thing in this feature that must not be got wrong.
_publishSecureBootKit() {
    local kitdir="${webdirdest}/service/secureboot"
    local cadir="$(_pkiZoneDir secureboot)/ca"

    # MOK.der publishes the certificate to be ENROLLED, which is not always the
    # one that signs. In split mode that is the Secure Boot intermediate, so a
    # rotated signing leaf never invalidates an enrollment; in flat mode
    # ${PKI_sb_ca_cert} is the same file as ${PKI_sb_codesign_cert} and this is
    # byte-identical to before.
    #
    # ${PKI_sb_enabled}=0 lands here too, and this is the second of the two places the
    # opt-out is applied. Declining Secure Boot means declining ENROLLMENT: no
    # MOK.der, and so no PXE menu entry either, since IpxeBootMenu gates that on
    # this file existing (bootmenu.class.php:2089). The binaries are still
    # signed -- see _ensureSecureBootKeys for why signing is not part of what
    # the flag turns off.
    if [[ -z ${PKI_sb_ca_cert} || ${PKI_sb_enabled:-yes} != yes ]]; then
        rm -rf "$kitdir" >>$error_log 2>&1
        return 0
    fi

    dots "Publishing Secure Boot enrollment kit"
    mkdir -p "$kitdir" >>$error_log 2>&1
    # The intermediate case already has a canonical DER sibling next to
    # .fogSBCA.pem in the PKI zone dir (see createSecureBootIntermediateCA) --
    # reuse it rather than re-deriving, so this kit's MOK.der is
    # byte-identical to what an admin can already verify straight from the
    # PKI tree, without reaching into $webdirdest.
    if [[ ${PKI_sb_ca_cert} == "${cadir}/.fogSBCA.pem" && -f "${cadir}/.fogSBCA.der" ]]; then
        cp -f "${cadir}/.fogSBCA.der" "${kitdir}/MOK.der" >>$error_log 2>&1
    # A DER copy of the certificate is what mokutil wants. Accept a PEM cert
    # too, since openssl is happy to produce either and admins mix them up.
    elif openssl x509 -in "${PKI_sb_ca_cert}" -inform der -noout >/dev/null 2>&1; then
        cp -f "${PKI_sb_ca_cert}" "${kitdir}/MOK.der" >>$error_log 2>&1
    elif openssl x509 -in "${PKI_sb_ca_cert}" -outform der -out "${kitdir}/MOK.der" >>$error_log 2>&1; then
        :
    else
        echo "Failed"
        echo " * Could not read ${PKI_sb_ca_cert} as a certificate."
        return 0
    fi
    cp -f ../packages/secureboot/fog-enroll-mok.sh "${kitdir}/" >>$error_log 2>&1
    cp -f ../packages/secureboot/fog-enroll-mok.desktop "${kitdir}/" >>$error_log 2>&1
    # MokManager, for the "Enroll Secure Boot Key" PXE menu item. IpxeBootMenu
    # chains to it over $_booturl, which is the WEB root
    # (http://<server>/fog/service) -- but downloadipxesecureboot stages these
    # binaries under $tftpdirdst/secureboot, which the web server does not
    # serve. Without this copy the menu item resolves to a 403/404 on every
    # architecture and falls straight into its own error branch.
    #
    # Copied rather than linked: the TFTP tree may be on a different
    # filesystem, and it is two small binaries.
    local mmsrc="${tftpdirdst%/}/secureboot"
    if [[ -f ${mmsrc}/mmx64.efi ]]; then
        cp -f "${mmsrc}/mmx64.efi" "${kitdir}/" >>$error_log 2>&1
    fi
    if [[ -f ${mmsrc}/arm64-efi/mmaa64.efi ]]; then
        mkdir -p "${kitdir}/arm64-efi" >>$error_log 2>&1
        cp -f "${mmsrc}/arm64-efi/mmaa64.efi" "${kitdir}/arm64-efi/" >>$error_log 2>&1
    fi
    # The iPXE binaries for local ESP boot are NOT published here. They live in
    # service/localboot, a sibling -- see _publishLocalBootFiles(). Deliberately
    # outside this directory, because the rm -rf above removes the whole kit
    # when there is no MOK, and local ESP boot is still wanted on a server with
    # no Secure Boot keys at all.
    # Keep the directory from being browsable, matching service/ipxe.
    echo '<?php header("HTTP/1.1 404 Not Found");' > "${kitdir}/index.php"
    chmod 0644 "${kitdir}"/MOK.der "${kitdir}"/*.desktop "${kitdir}"/index.php >>$error_log 2>&1
    # Guarded rather than globbed blind: an HTTPS install stages no Secure Boot
    # binaries at all (downloadipxesecureboot skips it), so an unguarded
    # chmod would log a "No such file" for every run on those servers.
    [[ -f ${kitdir}/mmx64.efi ]] && chmod 0644 "${kitdir}/mmx64.efi" >>$error_log 2>&1
    [[ -f ${kitdir}/arm64-efi/mmaa64.efi ]] && chmod 0644 "${kitdir}/arm64-efi/mmaa64.efi" >>$error_log 2>&1
    chmod 0755 "${kitdir}/fog-enroll-mok.sh" >>$error_log 2>&1
    chown -R "${apacheuser}":"${apacheuser}" "$kitdir" >>$error_log 2>&1
    echo "Done"
}
# efitools has no package on RHEL/Rocky/Alma/CentOS Stream 9: confirmed
# absent from EPEL9, and the only RPMs that exist for it live in those
# distros' "devel" repos -- build infrastructure, not something an admin is
# expected to enable. (gnu-efi-utils is NOT a substitute: that package is the
# gnu-efi project's own debugging utilities, unrelated to the cert-to-efi-*/
# sign-efi-* tools this needs.) Built from source as a last resort.
#
# `install:` depends on `all` upstream, so there is no way to `make install`
# just the two binaries fog-build-sb-authvars calls (cert-to-efi-sig-list,
# sign-efi-sig-list) without building the whole suite -- sbvarsign and the
# rest, none of which FOG uses, plus the self-signed sample PK/KEK/db test
# certs `all` generates for itself. That is harmless clutter, not a risk:
# `install` puts binaries in /usr/bin and the sample EFI test apps in
# /usr/share/efitools/efi (see the upstream Makefile/Make.rules), never the
# real EFI System Partition. Run verbatim -- `make && make install`, no
# narrower invocation -- because that is the exact sequence confirmed to
# build clean on Rocky 9; deriving a trimmed-down equivalent ourselves is
# more to get subtly wrong than it is worth saving a few seconds of build
# time on what is already a last-resort path.
#
# Pinned to 1.9.2 from the canonical upstream, git.kernel.org's jejb tree --
# not a GitHub mirror -- the same source Fedora/AlmaLinux package.
_ensureEfitools() {
    command -v cert-to-efi-sig-list >/dev/null 2>&1 && \
        command -v sign-efi-sig-list >/dev/null 2>&1 && return 0
    command -v curl >/dev/null 2>&1 || return 1

    local ver="1.9.2"
    local url="https://git.kernel.org/pub/scm/linux/kernel/git/jejb/efitools.git/snapshot/efitools-${ver}.tar.gz"
    local work
    work=$(mktemp -d) || return 1

    dots "Building efitools (no package for this distro)"
    # The build-time packages this needs beyond the C toolchain and
    # sbsigntools this install already has -- named the same across every
    # distro this installer supports, unlike gnu-efi's runtime package, so no
    # alternatives list is needed here. libuuid-devel, openssl-devel, and
    # perl-File-Slurp are linked/used by the build itself, not just gcc/make/
    # gnu-efi-devel/help2man -- confirmed by a clean build on Rocky 9 failing
    # without them.
    $packageinstaller gcc make gnu-efi-devel libuuid-devel openssl-devel \
        help2man perl-File-Slurp >>$error_log 2>&1
    if ! curl -fsSL --connect-timeout $inetConnectTimeout \
        --speed-time 30 --speed-limit 1024 "$url" -o "${work}/efitools.tar.gz" >>$error_log 2>&1; then
        echo "Failed"
        echo " * Could not download efitools ${ver} from ${url}."
        rm -rf "$work" >>$error_log 2>&1
        return 1
    fi
    tar -xzf "${work}/efitools.tar.gz" -C "$work" >>$error_log 2>&1
    if ! (cd "${work}/efitools-${ver}" && make && make install) >>$error_log 2>&1 ||
       ! command -v cert-to-efi-sig-list >/dev/null 2>&1 ||
       ! command -v sign-efi-sig-list >/dev/null 2>&1; then
        echo "Failed"
        echo " * efitools ${ver} did not build; see ${error_log}."
        rm -rf "$work" >>$error_log 2>&1
        return 1
    fi
    rm -rf "$work" >>$error_log 2>&1
    echo "Done"
}
# Build and publish the signed PK/KEK/db variable updates.
#
# These are what a client in Setup Mode writes to enroll this server's
# certificate automatically -- no MokManager, no password, no USB stick. See fos
# ADR-0009 for why Setup Mode is the only path that scales.
#
# Runs AFTER _publishSecureBootKit deliberately: the kit's `chown -R` would
# otherwise be the last thing to touch the directory, and the ordering of who
# owns what would depend on which function happened to run last. This function
# owns the permissions on the files it writes.
#
# Nothing published here is secret -- .auth blobs are public certificates plus
# signatures over them. The private keys stay in the Secure Boot PKI zone dir.
_publishSecureBootAuthVars() {
    local kitdir="${webdirdest}/service/secureboot"
    local msdst="$(_pkiZoneDir secureboot)/mscerts"
    local helper="${fogprogramdir}/bin/fog-build-sb-authvars"
    local conf="${fogprogramdir}/.fog-secureboot"

    # An install that already ran the flat ${fogprogramdir}/secureboot layout
    # moves its cached copy in place -- it's fully reproducible from the
    # packaged source below regardless, so this is only to avoid leaving a
    # stale duplicate behind.
    if [[ -d "${fogprogramdir}/secureboot/mscerts" && ! -d $msdst ]]; then
        mkdir -p "$(dirname "$msdst")" >>$error_log 2>&1
        mv "${fogprogramdir}/secureboot/mscerts" "$msdst" >>$error_log 2>&1
    fi

    # No platform keys means no automatic path. Clear any blobs from a previous
    # install rather than leaving stale ones a client would happily enroll: an
    # .auth signed by a key this server no longer holds enrolls a platform the
    # server can never update again.
    #
    # This used to return in silence, which made it the ONE cause of "automatic
    # enrollment is unavailable" that produced no diagnostic anywhere -- and it
    # is the likeliest cause on a server that has efitools installed. An admin
    # re-running the installer to fix it therefore saw nothing at all, then read
    # a web page confidently naming a different cause (GH-1266). It says which
    # of its three reasons applied now, so re-running the installer surfaces it.
    if [[ -z $secureBootPKKey || -z $secureBootKEKKey ]]; then
        rm -f "$helper" "${kitdir}"/{PK,KEK,db}.auth >>$error_log 2>&1
        dots "Publishing Secure Boot variable updates"
        echo "Skipped"
        if [[ ${PKI_sb_enabled:-yes} != yes ]]; then
            echo " * Secure Boot enrollment material is switched off for this"
            echo "   install (PKI_sb_enabled is not \"yes\"), so no platform keys"
            echo "   were minted and the automatic enrollment blobs were not"
            echo "   built. FOS kernels are still signed."
        elif [[ -z ${PKI_sb_codesign_key} || -z ${PKI_sb_codesign_cert} ]]; then
            echo " * No Secure Boot signing key is configured, so there is"
            echo "   nothing for a platform key to authorize and the automatic"
            echo "   enrollment blobs were not built."
        else
            echo " * The Secure Boot platform keys (PK/KEK) are missing, so the"
            echo "   automatic enrollment blobs were not built. Generating them"
            echo "   failed earlier in this run -- see $error_log."
        fi
        echo "   The MOK enrollment paths are unaffected."
        return 0
    fi

    _ensureEfitools
    dots "Publishing Secure Boot variable updates"
    if ! command -v cert-to-efi-sig-list >/dev/null 2>&1 ||
       ! command -v sign-efi-sig-list >/dev/null 2>&1; then
        echo "Skipped"
        echo " * efitools is not installed and could not be built from source,"
        echo "   so the automatic Secure Boot enrollment blobs were not built."
        echo "   See ${error_log}. The MOK enrollment paths are unaffected."
        rm -f "${kitdir}"/{PK,KEK,db}.auth >>$error_log 2>&1
        return 0
    fi

    # Microsoft's published CA certificates, staged root-owned next to the keys
    # rather than under the web root. They are public, but the builder reads
    # them to decide what a client will trust forever after, so a web-writable
    # copy would be a way to influence that decision from the web tier.
    mkdir -p "$msdst" >>$error_log 2>&1
    cp -f ../packages/secureboot/mscerts/* "$msdst"/ >>$error_log 2>&1
    chown -R root:root "$msdst" >>$error_log 2>&1
    chmod 0755 "$msdst" >>$error_log 2>&1
    chmod 0644 "$msdst"/* >>$error_log 2>&1

    # 0700 and no sudoers rule, unlike fog-sign-kernel: nothing but root ever
    # runs this, so the web user should not even be able to execute it.
    mkdir -p "${fogprogramdir}/bin" >>$error_log 2>&1
    install -o root -g root -m 0700 ../packages/secureboot/fog-build-sb-authvars \
        "$helper" >>$error_log 2>&1 || {
        echo "Failed"
        # The only arm here that used to print a bare "Failed" with no cause,
        # which is the same complaint as GH-1266 one line down.
        echo " * Could not install the Secure Boot variable builder to $helper,"
        echo "   so automatic enrollment will be unavailable. The MOK enrollment"
        echo "   paths are unaffected. See $error_log."
        return 0
    }
    sed -i "s|^CONF=.*|CONF=\"${conf}\"|" "$helper" >>$error_log 2>&1
    if ! grep -qxF "CONF=\"${conf}\"" "$helper"; then
        echo "Failed"
        echo " * Could not set the config path in $helper."
        return 0
    fi

    mkdir -p "$kitdir" >>$error_log 2>&1
    if ! "$helper" >>$error_log 2>&1; then
        echo "Failed"
        echo " * Could not build the Secure Boot variable updates, so automatic"
        echo "   enrollment will be unavailable. The MOK enrollment paths are"
        echo "   unaffected. See $error_log."
        rm -f "${kitdir}"/{PK,KEK,db}.auth >>$error_log 2>&1
        return 0
    fi
    chown "${apacheuser}":"${apacheuser}" "${kitdir}"/{PK,KEK,db}.auth >>$error_log 2>&1
    chmod 0644 "${kitdir}"/{PK,KEK,db}.auth >>$error_log 2>&1
    echo "Done"
}
# Normalize --secure-boot-cert to PEM and echo the path.
#
# sbsign and sbverify read certificates with PEM_read_bio_X509 and reject DER
# outright:
#
#   $ sbsign --key MOK.priv --cert MOK.der --output out.efi in.efi
#   Can't load certificate from file 'MOK.der'
#   error:0480006C:PEM routines:get_name:no start line ... Expecting: CERTIFICATE
#
# mokutil and MokManager want the opposite -- DER. So an admin following any
# Secure Boot guide ends up holding one of each, with nothing telling them which
# tool takes which, and handing the wrong one to --secure-boot-cert fails at
# signing time with an OpenSSL error that says nothing about the format.
#
# Convert once here rather than pushing the distinction onto the user: the flag
# accepts either, _resignKernels and the signing helper get PEM, and
# _publishSecureBootKit still converts to DER for enrollment.
_secureBootCertPem() {
    local pem="${fogprogramdir}/.fog-secureboot.pem"
    [[ -z ${PKI_sb_codesign_cert} ]] && return 1
    mkdir -p "$fogprogramdir" >>$error_log 2>&1
    if openssl x509 -in "${PKI_sb_codesign_cert}" -inform pem -noout >/dev/null 2>&1; then
        cp -f "${PKI_sb_codesign_cert}" "$pem" >>$error_log 2>&1 || return 1
    elif ! openssl x509 -in "${PKI_sb_codesign_cert}" -inform der -outform pem \
            -out "$pem" >>$error_log 2>&1; then
        return 1
    fi
    # World-readable on purpose: a certificate is public, and it is the private
    # key next to it that the 0600 config protects.
    chown root:root "$pem" >>$error_log 2>&1
    chmod 0644 "$pem" >>$error_log 2>&1
    echo "$pem"
}
# Re-sign the FOS kernels for UEFI Secure Boot.
#
# The kernels above are downloaded unsigned on every install AND every upgrade,
# so without this a working Secure Boot setup silently stops booting the moment
# someone updates FOG. That is the single most common way the setup breaks.
#
# No-op unless both secureBootKey and secureBootCert are configured. Missing
# sbsign is a warning rather than a failure: the rest of the install is fine and
# aborting it would be a worse outcome than an unsigned kernel the admin is told
# about.
# Install the root-only signing helper the web UI calls through sudo.
#
# The Kernel Update page runs as the web user, which must never be able to read
# the signing key -- a web compromise would otherwise walk off with it. So the
# key stays root-only and the web user gets a single, argument-less command it
# may run as root.
#
# Removes the helper, its config and the sudoers rule again when signing is
# turned off, so disabling the feature actually disables the privilege.
_installSecureBootSigner() {
    # $fogprogramdir, not a "/opt/fog" literal: GH-850 made the base path
    # installer-driven, and hardcoding it here would scatter the helper, its
    # config and the staging directory back into /opt/fog on a server installed
    # anywhere else.
    local bindir="${fogprogramdir}/bin"
    local helper="${bindir}/fog-sign-kernel"
    local conf="${fogprogramdir}/.fog-secureboot"
    local stagedir="${fogprogramdir}/secureboot-staging"
    local sudoersfile="/etc/sudoers.d/fog-secureboot"
    local certpem

    if [[ -z ${PKI_sb_codesign_key} || -z ${PKI_sb_codesign_cert} ]]; then
        rm -f "$helper" "$conf" "$sudoersfile" >>$error_log 2>&1
        return 0
    fi

    dots "Installing Secure Boot signing helper"
    certpem=$(_secureBootCertPem) || {
        echo "Failed"
        echo " * Could not read ${PKI_sb_codesign_cert} as a certificate (PEM or DER)."
        return 0
    }
    mkdir -p "$bindir" >>$error_log 2>&1
    install -o root -g root -m 0755 ../packages/secureboot/fog-sign-kernel "$helper" >>$error_log 2>&1 || {
        echo "Failed"
        return 0
    }
    # Point the helper at this install's config. It takes no arguments on
    # purpose -- that is what stops a compromised web server naming its own key
    # -- so the path has to be baked in here rather than passed at call time.
    # Quoted: $fogprogramdir may contain a space, and `CONF=/a/fog custom/x`
    # assigns only "/a/fog" and then tries to RUN "custom/x". bash -n does not
    # catch that -- it is valid syntax, just not what anyone meant.
    sed -i "s|^CONF=.*|CONF=\"${conf}\"|" "$helper" >>$error_log 2>&1
    if ! grep -qxF "CONF=\"${conf}\"" "$helper"; then
        echo "Failed"
        echo " * Could not set the config path in $helper."
        return 0
    fi
    # Root-owned, root-readable only: the web user learns nothing about where
    # the key lives, and cannot rewrite these paths to point somewhere else.
    # SECUREBOOT_CERT is the normalized PEM -- sbsign cannot read DER.
    #
    # The PK/KEK/mscerts/authvars lines are for fog-build-sb-authvars, not for
    # fog-sign-kernel, which ignores them. They live in the same file because
    # this function is the only writer of it -- a second config would have to be
    # kept in step with this one, and the failure mode of them drifting apart is
    # a signing helper and a variable builder disagreeing about which key is the
    # server's. Written unconditionally-or-not-at-all: an empty value here makes
    # the builder refuse with "config is incomplete", which is the right answer
    # when the platform keys could not be generated.
    {
        echo "SECUREBOOT_KEY=${PKI_sb_codesign_key}"
        echo "SECUREBOOT_CERT=${certpem}"
        # The certificate ENDPOINTS trust, which is not always the one that
        # signs. fog-build-sb-authvars puts this in db and fog-sign-kernel
        # --addcert's it; in flat mode it equals SECUREBOOT_CERT and both
        # behave exactly as before.
        echo "SECUREBOOT_MOK_CERT=${PKI_sb_ca_cert:-$certpem}"
        echo "SECUREBOOT_STAGING=${stagedir}"
        echo "SECUREBOOT_PK_KEY=${secureBootPKKey}"
        echo "SECUREBOOT_PK_CERT=${secureBootPKCert}"
        echo "SECUREBOOT_KEK_KEY=${secureBootKEKKey}"
        echo "SECUREBOOT_KEK_CERT=${secureBootKEKCert}"
        echo "SECUREBOOT_MSCERTS=$(_pkiZoneDir secureboot)/mscerts"
        echo "SECUREBOOT_AUTHVARS=${webdirdest}/service/secureboot"
    } > "$conf"
    chown root:root "$conf" >>$error_log 2>&1
    chmod 0600 "$conf" >>$error_log 2>&1

    # The web user owns only the staging directory -- it has to write the
    # downloaded kernel there and read the signed result back.
    mkdir -p "$stagedir" >>$error_log 2>&1
    chown "${apacheuser}":"${apacheuser}" "$stagedir" >>$error_log 2>&1
    chmod 0750 "$stagedir" >>$error_log 2>&1

    # Validate before installing: a malformed sudoers drop-in breaks sudo for
    # the whole machine, which is a far worse outcome than no signing.
    echo "${apacheuser} ALL=(root) NOPASSWD: ${helper}" > "${sudoersfile}.tmp"
    chmod 0440 "${sudoersfile}.tmp" >>$error_log 2>&1
    if visudo -cqf "${sudoersfile}.tmp" >>$error_log 2>&1; then
        mv -f "${sudoersfile}.tmp" "$sudoersfile" >>$error_log 2>&1
        chown root:root "$sudoersfile" >>$error_log 2>&1
        echo "Done"
    else
        rm -f "${sudoersfile}.tmp" >>$error_log 2>&1
        echo "Failed"
        echo " * Refusing to install an invalid sudoers rule; the web Kernel"
        echo "   Update page will download unsigned kernels. See $error_log."
    fi
}
_resignKernels() {
    [[ -z ${PKI_sb_codesign_key} || -z ${PKI_sb_codesign_cert} ]] && return 0
    if ! command -v sbsign >/dev/null 2>&1 || ! command -v sbverify >/dev/null 2>&1; then
        echo " * WARNING: Secure Boot signing configured but sbsign/sbverify are not installed."
        echo "   Install sbsigntool (Debian/Ubuntu) or sbsigntools (RHEL/Fedora)"
        echo "   and re-run the installer, or Secure Boot clients will not boot."
        return 0
    fi
    dots "Signing FOS kernels and Memtest86+ for Secure Boot"
    local kernel kpath failed=0 certpem
    # sbsign/sbverify take PEM only; the admin may well have handed us the DER
    # copy that mokutil wanted. See _secureBootCertPem().
    certpem=$(_secureBootCertPem) || {
        echo "Failed"
        echo " * Could not read ${PKI_sb_codesign_cert} as a certificate (PEM or DER)."
        echo "   Secure Boot clients will not boot until this is fixed."
        return 0
    }
    # The two Memtest86+ binaries ride along: each is a bzImage that is also
    # a PE, and on a UEFI client iPXE chains it as a PE, which under Secure
    # Boot needs the same countersignature the kernels get (#321).
    for kernel in bzImage bzImage32 arm_Image mt86plus_x86_64 mt86plus_i586; do
        kpath="${webdirdest}/service/ipxe/${kernel}"
        [[ -f $kpath ]] || continue
        # Already carrying our signature means nothing was re-downloaded since
        # the last run, so there is nothing to do. Skipping here is what keeps a
        # second invocation from stacking a second signature on the same image.
        sbverify --cert "$certpem" "$kpath" >/dev/null 2>&1 && continue
        # Otherwise this is a freshly downloaded, unsigned kernel. Snapshot it,
        # because sbsign will not cleanly re-sign an already-signed image and the
        # next run needs an unsigned original to work from. The snapshot has to
        # be refreshed every time rather than kept forever, or an upgrade would
        # re-sign the *previous* version over the new one.
        cp -af "$kpath" "${kpath}.unsigned" >>$error_log 2>&1
        # --addcert ships the issuing intermediate inside the signature, which
        # is what lets shim (and the firmware, via db) chain a leaf-signed
        # kernel back to the certificate that was actually enrolled. Without
        # it a split-mode kernel is signed by a certificate no endpoint has
        # ever seen and simply will not boot.
        #
        # Built as an array so flat mode passes no extra argument at all and
        # its command line stays byte-identical to before.
        local addcert=()
        [[ -n ${PKI_sb_ca_cert} ]] \
            && [[ "$(readlink -f "${PKI_sb_ca_cert}" 2>/dev/null)" != "$(readlink -f "$certpem" 2>/dev/null)" ]] \
            && addcert=(--addcert "${PKI_sb_ca_cert}")
        if sbsign --key "${PKI_sb_codesign_key}" --cert "$certpem" "${addcert[@]}" \
                --output "$kpath" "${kpath}.unsigned" >>$error_log 2>&1; then
            chown "${SVC_user}" "$kpath" >>$error_log 2>&1
        else
            failed=1
        fi
    done
    if [[ $failed -ne 0 ]]; then
        echo "Failed"
        echo " * At least one kernel could not be signed. See $error_log."
        echo "   Secure Boot clients will not boot until this is fixed."
        return 0
    fi
    echo "Done"
}
# Re-sign the rEFInd binaries for UEFI Secure Boot.
#
# Why this exists at all: FOG_EFI_BOOT_EXIT_TYPE selects how a UEFI host leaves
# the boot menu -- when a task finishes and when no task exists -- and
# 'refind_efi' is one of the choices. bootmenu.class then emits
# 'chain -ar ${boot-url}/service/ipxe/refind*.efi', and under EFI that is
# LoadImage/StartImage, so the firmware (or shim, on our signed snponly path)
# validates it exactly as it validates the FOS kernel. An unsigned rEFInd dies
# there with SECURITY VIOLATION.
#
# The symptom is deceptive, which is why this went unnoticed: imaging itself
# works perfectly -- iPXE is signed, the kernel is signed by _resignKernels --
# and the machine only fails afterward, on the way to the disk. It reads as a
# bootloader or partitioning problem, not a Secure Boot one. Reported on the
# forum against 1.6.3200 (topic 18217), where the reporter fixed it by hand.
#
# Still unconditional now that 'sanboot' is the default rather than
# 'refind_efi', and deliberately so. The default only decides what an untouched
# server does; rEFInd is still shipped, still restored across upgrades, still
# offered in the dropdown, and still settable per host. Signing it costs one
# sbsign per binary on installs that will never boot it, and NOT signing it
# turns a dropdown selection into a Secure Boot failure two reboots later, with
# the deceptive symptom above. That trade has not changed.
#
# What ships is not a substitute: refind.efi and refind_x64.efi carry Rod
# Smith's own self-signed certificate, which is in nobody's db, and
# refind_ia32.efi/refind_aa64.efi carry no signature at all.
#
# Deliberately NOT folded into _resignKernels(). That runs from downloadfiles(),
# i.e. inside configureTFTPandPXE -- and restorePreservedCustomizations() runs
# AFTER that and unconditionally copies the preserved refind set back over the
# live one. Signing there would be undone on every upgrade by a restore doing
# exactly its job. This is called after the restore instead, so it signs
# whatever actually ends up in place.
#
# Nothing strips the existing signature: sbsign appends rather than replaces
# ("Image was already signed; adding additional signature"), and a site that
# followed rEFInd's own Secure Boot documentation may have enrolled Rod's
# certificate. Removing it would break them for no gain.
#
# Master only. Storage nodes get configureMinHttpd, which never lays down the
# web package's service/ipxe tree, so there is no rEFInd there to sign.
#
# PEM path of the certificate an image signed by this server VERIFIES against,
# which in split-PKI mode is NOT the certificate it is signed WITH.
#
# sbverify resolves the embedded chain against the -cert as a trust anchor, so
# an image signed by the leaf with --addcert <intermediate> verifies against the
# intermediate and fails against the leaf:
#
#   $ sbsign --key leaf.key --cert leaf.crt --addcert ca.crt -o out.efi in.efi
#   $ sbverify --cert leaf.crt out.efi   -> FAIL
#   $ sbverify --cert ca.crt   out.efi   -> PASS
#
# That is why the "already signed by us, skip" test below cannot just reuse
# _secureBootCertPem(): on a split-PKI server it would fail against every file
# this function had already signed, and each installer run would append one more
# signature to a file that grows forever.
#
# secureBootMokCert may be the DER copy an admin passed to --secureboot-ca-cert,
# and sbverify takes PEM only -- same normalization _secureBootCertPem() does
# for the signing cert, against a separate filename so the two cannot clobber
# each other.
_secureBootAnchorPem() {
    local pem="${fogprogramdir}/.fog-secureboot-anchor.pem"
    # Flat mode, or no intermediate: the signing cert IS the anchor.
    [[ -z ${PKI_sb_ca_cert} ]] && { _secureBootCertPem; return $?; }
    mkdir -p "$fogprogramdir" >>$error_log 2>&1
    if openssl x509 -in "${PKI_sb_ca_cert}" -inform pem -noout >/dev/null 2>&1; then
        cp -f "${PKI_sb_ca_cert}" "$pem" >>$error_log 2>&1 || return 1
    elif ! openssl x509 -in "${PKI_sb_ca_cert}" -inform der -outform pem \
            -out "$pem" >>$error_log 2>&1; then
        return 1
    fi
    chown root:root "$pem" >>$error_log 2>&1
    chmod 0644 "$pem" >>$error_log 2>&1
    echo "$pem"
}
_resignRefind() {
    [[ -z ${PKI_sb_codesign_key} || -z ${PKI_sb_codesign_cert} ]] && return 0
    local ipxedir="${webdirdest%/}/service/ipxe"
    [[ -d $ipxedir ]] || return 0
    if ! command -v sbsign >/dev/null 2>&1 || ! command -v sbverify >/dev/null 2>&1; then
        # _resignKernels() has already warned about the missing tools in this
        # same run; saying it twice helps nobody.
        return 0
    fi
    local f fpath certpem anchorpem failed=0 signed=0
    certpem=$(_secureBootCertPem) || return 0
    # Verified against the anchor, signed with the leaf. See
    # _secureBootAnchorPem() -- these are the same file in flat mode and
    # deliberately different in split-PKI mode.
    anchorpem=$(_secureBootAnchorPem) || anchorpem="$certpem"
    local addcert=()
    [[ -n ${PKI_sb_ca_cert} ]] \
        && [[ "$(readlink -f "${PKI_sb_ca_cert}" 2>/dev/null)" != "$(readlink -f "$certpem" 2>/dev/null)" ]] \
        && addcert=(--addcert "${PKI_sb_ca_cert}")
    # refind.conf is data, not a PE image -- it is preserved alongside these but
    # is not signable and does not need to be.
    for f in refind.efi refind_x64.efi refind_ia32.efi refind_aa64.efi; do
        fpath="${ipxedir}/${f}"
        [[ -f $fpath ]] || continue
        # Already carrying OUR signature. Either this run has nothing to do, or
        # the file is an admin's own already-signed copy that
        # restorePreservedCustomizations() just put back -- leave both alone.
        # This is also what stops a re-run stacking a second signature, which
        # is why it verifies against the anchor and not the signing cert.
        sbverify --cert "$anchorpem" "$fpath" >/dev/null 2>&1 && continue
        [[ $signed -eq 0 ]] && dots "Signing rEFInd for Secure Boot"
        signed=1
        # Signed via a temporary rather than in place: sbsign reads its input
        # while writing its output, so input and --output must differ. The
        # temporary is created in the same directory so it inherits the same
        # SELinux context, and takes the original's ownership so the web user
        # can still serve it.
        if sbsign --key "${PKI_sb_codesign_key}" --cert "$certpem" "${addcert[@]}" \
                --output "${fpath}.signing" "$fpath" >>$error_log 2>&1; then
            chown --reference="$fpath" "${fpath}.signing" >>$error_log 2>&1
            chmod --reference="$fpath" "${fpath}.signing" >>$error_log 2>&1
            mv -f "${fpath}.signing" "$fpath" >>$error_log 2>&1 || failed=1
        else
            rm -f "${fpath}.signing" >>$error_log 2>&1
            failed=1
        fi
    done
    [[ $signed -eq 0 ]] && return 0
    if [[ $failed -ne 0 ]]; then
        echo "Failed"
        echo " * At least one rEFInd binary could not be signed. See $error_log."
        echo "   Secure Boot clients will fail with SECURITY VIOLATION when they"
        echo "   exit the boot menu until this is fixed."
        return 0
    fi
    echo "Done"
}
# Re-stamp .fog-ipxe-manifest for the files just signed.
#
# _copyIpxeTree() records a sha256 of every file it lays down so a later run can
# tell FOG's own copy from one the admin replaced, and decline to overwrite
# theirs -- the "Kept your own copies of these iPXE files" report. Signing
# rewrites those bytes AFTER that stamp was taken, so without this every .efi
# compares unequal on the NEXT run: FOG stops updating its own binaries and
# names all 45 as admin-modified, every run, permanently. Found by reading; it
# needs two installs of a Secure Boot server to show up, which is why the
# original design's "nothing stamps or verifies /tftpboot" note was true when
# written and is not now.
#
# Only lines for files actually signed are rewritten. Everything else is copied
# through byte for byte -- including an entry deliberately carrying the ORIGINAL
# sum for a file the admin really did replace, which is what keeps that file
# from being quietly overwritten on the run after this one.
_restampIpxeManifest() {
    local tftproot="$1"; shift
    local manifest="${tftproot}/.fog-ipxe-manifest"
    [[ -f $manifest && $# -gt 0 ]] || return 0
    command -v sha256sum >/dev/null 2>&1 || return 0
    local rel sum staging="${manifest}.restamp"
    declare -A resigned=()
    for rel in "$@"; do resigned["$rel"]=1; done
    : > "$staging" 2>>$error_log || return 0
    while IFS='|' read -r sum rel; do
        [[ -z $sum || -z $rel ]] && continue
        if [[ -n ${resigned[$rel]:-} ]]; then
            sum=$(sha256sum "${tftproot}/${rel}" 2>/dev/null | cut -d' ' -f1)
            # A file that vanished between signing and here: drop the line
            # rather than carry a sum that matches nothing.
            [[ -z $sum ]] && continue
        fi
        printf '%s|%s\n' "$sum" "$rel" >> "$staging" 2>>$error_log
    done < "$manifest"
    mv -f "$staging" "$manifest" >>$error_log 2>&1
}
# Sign FOG's own iPXE binaries so one can be chainloaded from a machine's ESP
# under Secure Boot.
#
# Some machines cannot netboot at all -- firmware with no PXE boot option -- and
# for the rest, a queued task otherwise needs the boot order changed to reach the
# network, which is the commoner problem. Both have been handled for years by
# putting an iPXE .efi on the ESP and pointing the boot manager at it. Secure
# Boot broke that: the chain has to start at a Microsoft-signed shim, and FOG's
# binaries carry no signature shim will accept, so it has nowhere to land.
#
#   \EFI\snponly-shimx64.efi   upstream signed shim
#   \EFI\ipxe.efi              upstream signed snponly, no script compiled in
#   \EFI\autoexec.ipxe         two lines, read off the ESP
#   \EFI\fogipxe.efi           FOG's own build -- what this function signs
#
# Upstream's snponly.efi cannot finish the job itself: it binds the firmware's
# UEFI SNP protocol, which this class of hardware frequently does not provide --
# the same reason its firmware has no PXE option -- so the chain has to reach a
# binary carrying iPXE's own NIC drivers, and that one is ours. Once shim has run
# its security policy override stays installed for the rest of the boot, so a
# MOK-signed image loads, and the MOK is the one _publishSecureBootKit() already
# publishes and clients already enroll.
#
# This is NOT on the netboot path and must not become one. Enrollment depends on
# an un-enrolled machine reaching the FOG menu to run MokManager, and a
# MOK-signed binary there could not load, because the MOK is not enrolled yet.
# Signing in place is safe for the same reason it is invisible: an appended PE
# signature changes nothing for a client booting with Secure Boot off, which is
# every client that boots these files today.
#
# No build is involved, because the release asset is not a vanilla iPXE build --
# fog-ipxe's workflow runs buildipxe.sh with no arguments and ships its output
# verbatim, so these already carry FOG's embedded boot scripts, config overlays
# and the whole variant matrix. The only per-site input to a build is the CA, and
# an HTTPS-with-your-own-CA install has already compiled locally before this runs.
#
# Runs on every server, including one that passed --no-secureboot: that flag
# declines enrollment, not signatures. See _ensureSecureBootKeys.
_signLocalIpxe() {
    [[ -z ${PKI_sb_codesign_key} || -z ${PKI_sb_codesign_cert} ]] && return 0
    local tftproot="${tftpdirdst%/}"
    [[ -d $tftproot ]] || return 0
    if ! command -v sbsign >/dev/null 2>&1 || ! command -v sbverify >/dev/null 2>&1; then
        # _resignKernels() has already warned about the missing tools in this
        # same run; saying it twice helps nobody.
        return 0
    fi
    local fpath certpem anchorpem failed=0 signed=0
    local restamp=()
    certpem=$(_secureBootCertPem) || return 0
    # Verified against the anchor, signed with the leaf -- the same split-PKI
    # handling _resignRefind() uses. See _secureBootAnchorPem().
    anchorpem=$(_secureBootAnchorPem) || anchorpem="$certpem"
    local addcert=()
    [[ -n ${PKI_sb_ca_cert} ]] \
        && [[ "$(readlink -f "${PKI_sb_ca_cert}" 2>/dev/null)" != "$(readlink -f "$certpem" 2>/dev/null)" ]] \
        && addcert=(--addcert "${PKI_sb_ca_cert}")
    # secureboot/ is pruned: that directory is upstream's signed shim and loader,
    # delivered as a separate release asset by downloadipxesecureboot(). They are
    # already signed by Microsoft and by iPXE, they are the two stages the whole
    # chain hangs off, and adding a signature of ours to them buys nothing.
    #
    # *.efi only. The BIOS artifacts beside them -- .kpxe, .lkrn, .usb, .iso --
    # are not PE images and Secure Boot does not apply to them.
    #
    # Read from a process substitution rather than a pipe so the loop runs in
    # this shell and $signed/$failed survive it.
    local count=0
    while IFS= read -r fpath; do
        # Already carrying OUR signature: nothing to do. This is what stops a
        # re-run stacking a second signature, and it verifies against the anchor
        # rather than the signing cert so a rotated leaf does not restart the
        # stacking.
        #
        # Note what this test does NOT skip, because it is deliberate rather
        # than incidental: a binary the admin built and signed with their OWN
        # CA does not verify against FOG's anchor, so it falls through and gets
        # signed here too. That is wanted. sbsign APPENDS to the signature list
        # rather than replacing it, so their signature survives intact and the
        # binary gains one this server's MOK vouches for -- which is what lets a
        # custom build boot on a machine enrolled against FOG.
        #
        # It also means the sweep below is a sweep, not a list: anything an
        # admin dropped anywhere under the TFTP root gets the same treatment,
        # including the stock/ copies kept when a rebuild replaces the published
        # binaries.
        sbverify --cert "$anchorpem" "$fpath" >/dev/null 2>&1 && continue
        [[ $signed -eq 0 ]] && dots "Signing iPXE binaries for Secure Boot"
        signed=1
        count=$((count + 1))
        # Signed via a temporary rather than in place: sbsign reads its input
        # while writing its output, so input and --output must differ. The
        # temporary is created in the same directory so it inherits the same
        # SELinux context, and takes the original's ownership and mode.
        if sbsign --key "${PKI_sb_codesign_key}" --cert "$certpem" "${addcert[@]}" \
                --output "${fpath}.signing" "$fpath" >>$error_log 2>&1; then
            chown --reference="$fpath" "${fpath}.signing" >>$error_log 2>&1
            chmod --reference="$fpath" "${fpath}.signing" >>$error_log 2>&1
            if mv -f "${fpath}.signing" "$fpath" >>$error_log 2>&1; then
                # Collected rather than stamped here: the sum has to be taken
                # after the mv, and rewriting the manifest once at the end is
                # one pass over it instead of one per file.
                restamp+=("${fpath#${tftproot}/}")
            else
                failed=1
            fi
        else
            rm -f "${fpath}.signing" >>$error_log 2>&1
            failed=1
        fi
    done < <(find "$tftproot" -path "${tftproot}/secureboot" -prune -o \
                -type f -name '*.efi' -print 2>>$error_log)
    [[ ${#restamp[@]} -gt 0 ]] && _restampIpxeManifest "$tftproot" "${restamp[@]}"
    [[ $signed -eq 0 ]] && return 0
    if [[ $failed -ne 0 ]]; then
        echo "Failed"
        echo " * At least one iPXE binary could not be signed. See $error_log."
        echo "   Netboot is unaffected. A machine booting one of these from its"
        echo "   own ESP with Secure Boot on will fail until this is fixed."
        return 0
    fi
    # Counted so "everything is signed" is observable rather than assumed. A
    # run that signs nothing prints nothing at all (the early return above), so
    # a number here means work actually happened.
    echo "Done (${count})"
}
# ===========================================================================
# Local ESP boot: the published archives
# ===========================================================================
#
# Machines this exists for cannot fetch a boot file over the network -- that is
# the whole problem -- so the files have to be reachable over HTTP to get onto
# an ESP at all. The TFTP tree is not web-served, which until this existed meant
# every admin hand-rolling symlinks into the document root.
#
# What is published is THREE ARCHIVES and a manifest, and nothing else:
#
#   service/localboot/manifest.json          the index
#   service/localboot/fog-esp-<arch>.zip     ready-to-copy ESP folder
#
# One archive per architecture, packed FLAT -- its contents are its top level,
# with no wrapper directory named after the archive. It used to carry one, which
# is a level too many on Windows: Explorer's "Extract All" makes a folder named
# after the zip and unpacks the wrapper inside it, so you get
# fog-esp-x86_64\fog-esp-x86_64\ and the README's "copy the contents of this
# folder" names the wrong one.
#
# That in turn replaced a directory tree that published the same bytes twice -- a
# "menu" of binaries under their TFTP names at the root, and a "kit" of the same
# binaries renamed fog*.efi under esp/ -- with arm64 appearing in four different
# places.
#
# WHY THE ARCHIVE, AND NOT LOOSE FILES: the two audiences turned out to be one.
# The only reason the kit needed different filenames is that two names on an ESP
# are reserved (see below), and those names are strictly the safer choice
# everywhere -- so publishing one correctly-named folder serves both the admin
# assembling an ESP and the admin who wants a single binary out of it. The cost,
# recorded because it is a real one: no single binary has a URL any more, so
# nothing here can be a UEFI HTTP Boot target or an iPXE `chain` destination.
# Publishing the loose set alongside is purely additive if that is ever wanted.
#
# ---------------------------------------------------------------------------
# WHY EVERY BINARY SITS IN A SUBDIRECTORY WITH ITS OWN COPY OF THE SCRIPT
# ---------------------------------------------------------------------------
#
# This is the load-bearing part of the layout, and it is not cosmetic.
#
# Since fog-ipxe v2.0.0-fog.8 no EFI target is built with EMBED=, so no binary
# FOG or upstream ships carries the DHCP/proxyDHCP/next-server script internally
# -- they READ it, from a file called autoexec.ipxe. iPXE resolves that name
# against the directory the running binary was itself loaded from
# (efi_autoexec_filesystem() -> "file:autoexec.ipxe", falling back to
# "file:/autoexec.ipxe" at the volume root).
#
# So the rule is simply: a folder holding a bootable binary holds a copy of the
# script -- fog-ipxe/, fog-ipxe-customca/, secureboot-upstream/, secureboot-fog/
# and secureboot-fog-customca/, whichever of them staged -- plus a root copy for
# the volume fallback, for an ESP whose contents were unpacked at the top level.
#
# A folder that exists but holds no *.efi does NOT get one: on an HTTPS-only
# install the enrollment material still lands in secureboot-upstream/ while no shim
# does, and a script beside no binary implies a route the archive does not have.
#
# EVERY COPY IS IDENTICAL, from _espAutoexecScript(). That is the fix for what
# shipped before, which was two DIFFERENT scripts: a chain ladder at the root and
# the real boot logic in a subfolder. Refs GH-1195.
#
# WHY DIFFERENT SCRIPTS CANNOT WORK HERE, since this is the trap to avoid
# reintroducing. When iPXE chains an EFI image, the chained image resolves
# autoexec.ipxe through the synthetic EFI_SIMPLE_FILE_SYSTEM_PROTOCOL that
# efi_image_exec() installs, and that handle serves registered images BY FLAT
# NAME. A chained binary therefore reads whatever the CHAINING iPXE registered
# under that name -- not the file beside itself. Directory separation does not
# isolate the scripts, because flat-name lookup ignores directories. With the
# ladder at the root, a chained fog*.efi re-read the ladder, chained itself, and
# recursed until the firmware ran out of pool memory.
#
# Making every copy identical makes that re-read harmless. Nothing in the archive
# chains anything else now, so it should not arise at all -- but if some future
# change reintroduces a chain, it will read the same script either way.
#
# THE 10-SECOND DELAY IS NO LONGER A SECOND SET OF ARCHIVES. It used to be, back
# when it was two lines compiled into a binary and the choice therefore had to be
# made at download time. With the script on disk it is two lines of text, so
# every copy carries them commented out, and --boot-delay writes them live --
# exactly what _applyBootDelay() already does to the TFTP copy for netboot
# clients. Six archives become three, one behavior, one place to look.
#
# ---------------------------------------------------------------------------
#
# HOW SHIM PICKS ITS SECOND STAGE, and the older comment here had it wrong in a
# way that invites a change which breaks things. Refs ipxe/ipxe#1684.
#
# shim derives the name from its OWN filename AT RUNTIME -- rewriting its
# "-shim<arch>.efi" suffix to ".efi" -- and resolves it in its own directory. That
# is the intent, and it holds only where firmware reports the loaded image's
# filename. MANY FIRMWARES DO NOT. When the name is unavailable shim falls back to
# "ipxe.efi", so a locally booted snponly-shimx64.efi goes looking for ipxe.efi.
# The issue lists Asus H87M/H87-Pro, IGEL M340/M350, exone NS70MU, Surface Laptop 3
# and Parallels, and reports no tested device avoiding it on local boot.
#
# NETBOOT AND LOCAL BOOT THEREFORE BEHAVE DIFFERENTLY WITH THE SAME FILE. Over
# TFTP the device path carries the filename, so snponly-shimx64.efi correctly
# fetches snponly.efi -- observed. Off an ESP the same binary hunts for ipxe.efi.
# Do not "fix" one path by assuming the other.
#
# Three things follow, all of them load-bearing:
#
#   * RENAMING A SHIM ACHIEVES NOTHING and can break it. Renaming cannot change
#     what it looks for; where firmware does report the name, it breaks the
#     derivation. Tested.
#   * The two entry points in secureboot-upstream/ are NOT independent on affected
#     firmware -- booting either shim locally lands on upstream's ipxe.efi.
#   * secureboot-fog/ ships FOG's build under BOTH ipxe.efi and snponly.efi, and
#     both shims, because which name gets asked for is not predictable from here.
#     Measured: both shims reach a full boot on physical hardware, VMware and KVM.
#     Do not trim it to one.
#
# The second stage still has to be upstream's copy in secureboot-upstream/,
# because upstream's is what shim's embedded certificate vouches for. In
# secureboot-fog/ it is FOG's build instead, which needs FOG's certificate in
# MokList or db -- that is the whole point of that folder.
#
# FOG's builds keep the fog* prefix in fog-ipxe/ even though separate directories
# have made the collision impossible: the names are in the README, in the docs and
# in every bug report since GH-1117, and renaming them buys nothing.
#
# HOW FAR UPSTREAM'S LOADER GETS ON ITS OWN. An older comment asserted it does not
# load iPXE's own NIC drivers off an ESP and therefore "dead-ends on exactly the
# hardware this feature exists for". Measured since across physical hardware,
# VMware and KVM: secureboot-upstream/ reaches a full boot in every Secure Boot
# state EXCEPT with nothing enrolled, where it reaches the menu, exits to disk and
# runs MokManager but cannot image -- FOS's kernel is FOG-signed.
#
# So it is a complete route where firmware provides SNP. Where firmware provides
# none -- no PXE option at all, the case this archive exists for -- it has nothing
# to bind, and fog-ipxe/ is the answer. Keep both. One KVM guest looked like the
# no-SNP case and turned out to be a firmware setting: its NIC had no IPv4
# configuration, so no SNP device existed. See the README's troubleshooting note.
#
# MokManager ships too. shim launches mm<arch>.efi FROM ITS OWN DIRECTORY when
# it cannot verify the next stage, and that is the only way to enroll a MOK --
# shim's MokList is a boot-services-only variable, so nothing in a running OS
# can write it. Without mmx64.efi beside the shim, an ESP that has not been
# enrolled yet is a dead end with no route out of it. Found the hard way: it had
# to be downloaded by hand.
#
# MOK.der ships WITH it, which the first implementation missed. MokManager
# enrolls by browsing the ESP for a certificate, so shipping MokManager without
# one is still a dead end -- it just fails one screen later.
#
# PK/KEK/db.auth ship as well, and they are what make the i386 archive worth
# building. Those are signed EFI variable updates a machine in Setup Mode
# writes to put THIS server's certificate straight into db, after which firmware
# verifies a signed fogipxe.efi directly -- no shim, no MokManager, no MOK.
# Upstream signs no shim for ia32, so the earlier design concluded i386 had no
# Secure Boot path at all. Via db it does.
#
# rEFInd ships in refind/, which is new (GH-1185). It is what a UEFI host
# chainloads leaving the boot menu when FOG_EFI_BOOT_EXIT_TYPE is 'refind_efi',
# and an ESP assembled from this kit had no local-boot chainloader on it at all.
# It comes from the WEB tree rather than $tftpdirdst (it has never existed in the
# TFTP tree) and _resignRefind() has already signed it by the time this runs. Its
# own subdirectory because rEFInd reads refind.conf from the directory it was
# loaded from, which is also where every rEFInd installation on earth puts it.
#
# Published even though the default exit type is now 'sanboot', which needs no
# chainloader at all -- firmware boots the next entry itself. Two reasons. The
# archive is assembled by hand onto a disk that may never talk to this server
# again, so it should carry every route the server supports rather than only the
# one this server is currently configured for; and refind_efi remains a
# per-host setting, so a host booting from this ESP can be the one that wants
# it. It is ~230KB in an archive already several MB of signed binaries.
#
# STILL NOT PUBLISHED: the BIOS artifacts (.kpxe/.lkrn/.usb/.iso), which are not
# PE images and which an ESP cannot boot. They remain on TFTP for anyone
# assembling that setup deliberately.
#
# Everything here is public by nature: FOG's own binaries, upstream's signed
# shim and loader (downloadable from fog-ipxe's release assets anyway), and
# certificates plus signatures over them. FOG already serves the MOK-signed
# bzImage over unauthenticated HTTP from service/ipxe, so this is not a new
# class of exposure. The private keys never leave the PKI zone directory.
# The files one archive takes from $tftpdirdst, as "src|dst" -- src relative to
# $tftpdirdst, dst relative to the archive's top-level directory.
#
#   $1  architecture: x86_64 | i386 | arm64
#   $2  1 if stock/ exists in the TFTP tree, meaning a local CA rebuild happened
#
# ONE FOLDER PER ROUTE, nothing loose at the archive root. Each is self-contained:
# copy the folder, point the boot manager at the one entry point inside it.
#
#   fog-ipxe/                 FOG's builds, generic. The route for firmware with
#                             no PXE option -- these carry iPXE's own NIC drivers
#                             and need no firmware SNP. Also the route once this
#                             server's certificate is in db, with no shim at all.
#   fog-ipxe-customca/        the same builds with this server's CA embedded.
#                             Only exists where --rebuild-ipxe-with-my-ca ran.
#   secureboot-upstream/      upstream's Microsoft-signed shim and the loader it
#                             hands to, unmodified. Nothing to enroll.
#   secureboot-fog/           upstream's shims with FOG's build standing in as
#                             the second stage. For Secure Boot with the MOK
#                             enrolled, or Secure Boot off, keeping the shim.
#   secureboot-fog-customca/  the same on the CA-embedded build.
#
# EVERY FOLDER HOLDING A BOOTABLE BINARY ALSO HOLDS THE SCRIPT, written by
# _publishLocalBootFiles() from one generator and identical everywhere. iPXE reads
# autoexec.ipxe out of the directory the running binary was loaded from, so a
# folder of binaries without one cannot boot. The archive root keeps a copy too,
# as iPXE's documented volume-root fallback. This mirrors FOG's own TFTP tree,
# where root, i386-efi/, arm64-efi/ and secureboot/ each carry a copy.
#
# CA EMBEDDING IS ORTHOGONAL TO SECURE BOOT and the two get conflated constantly.
# Embedding decides whether iPXE will accept THIS server's HTTPS certificate;
# Secure Boot signing decides whether firmware or shim will load the image at all.
# _signLocalIpxe() signs every *.efi under the TFTP root with the same key,
# stock/ included, so both variants behave identically under Secure Boot and one
# enrollment covers either.
#
# WHAT THIS COSTS: roughly 16MB against the ~7MB of the two-folder layout, from
# five FOG binaries duplicated for the CA variant plus two shims and a binary in
# each shim-redirect folder. Deliberate. It is fetched once over the LAN when an
# admin assembles an ESP, not per host at boot.
_espKitFiles() {
    local arch="$1" havestock="${2:-0}"
    local fogdir="" sbdir="secureboot" shimsfx="x64" mm="mmx64.efi" name
    case $arch in
        i386)
            fogdir="i386-efi/"
            # No shim, loader or MokManager: upstream signs none for ia32. The
            # archive is still built, because db enrollment needs no shim -- see
            # the header.
            sbdir=""
            ;;
        arm64)
            fogdir="arm64-efi/"
            sbdir="secureboot/arm64-efi"
            shimsfx="aa64"
            mm="mmaa64.efi"
            ;;
    esac
    # WHICH TREE HOLDS WHICH BUILD, and it is the opposite of the obvious guess.
    #
    # _preserveStockIpxe() snapshots the published release binaries into stock/
    # BEFORE a --rebuild-ipxe-with-my-ca build, and buildipxe.sh then builds into
    # the tree ROOT. So the root is the CA-embedded set exactly when stock/
    # exists, and stock/ is the generic one. With no rebuild there is no stock/
    # and the root is generic.
    # NOTE: cadir is legitimately the EMPTY STRING on x86_64, because FOG's
    # x86_64 builds live at the tree root with no prefix. So every test for "is
    # there a CA variant" has to be $havestock, never [[ -n $cadir ]] -- the
    # latter is false exactly on the architecture that matters most.
    local genericdir="${fogdir}" cadir=""
    if [[ $havestock -eq 1 ]]; then
        genericdir="stock/${fogdir}"
        cadir="${fogdir}"
    fi
    # secureboot-upstream/: upstream's signed chain, untouched.
    if [[ -n $sbdir ]]; then
        echo "${sbdir}/snponly-shim${shimsfx}.efi|secureboot-upstream/snponly-shim${shimsfx}.efi"
        echo "${sbdir}/snponly.efi|secureboot-upstream/snponly.efi"
        echo "${sbdir}/ipxe-shim${shimsfx}.efi|secureboot-upstream/ipxe-shim${shimsfx}.efi"
        echo "${sbdir}/ipxe.efi|secureboot-upstream/ipxe.efi"
        echo "${sbdir}/${mm}|secureboot-upstream/${mm}"
    fi
    # fog-ipxe/ and fog-ipxe-customca/: FOG's own builds, pre-named for the ESP.
    # Listed ipxe-first because that is the one to try first on firmware with no
    # SNP, NOT because anything walks this order -- nothing chains any more.
    for name in ipxe snp intel realtek snponly; do
        echo "${genericdir}${name}.efi|fog-ipxe/fog${name}.efi"
    done
    if [[ $havestock -eq 1 ]]; then
        for name in ipxe snp intel realtek snponly; do
            echo "${cadir}${name}.efi|fog-ipxe-customca/fog${name}.efi"
        done
    fi
    # secureboot-fog/ and secureboot-fog-customca/: upstream's shims with FOG's
    # build standing in as the second stage.
    #
    # FOG'S BUILD GOES IN TWICE, UNDER BOTH NAMES, and that is coverage rather
    # than duplication -- see ipxe/ipxe#1684 in the header. A locally booted shim
    # asks for whichever name it derives, and on firmware that will not report
    # the loaded image's filename it falls back to ipxe.efi regardless of which
    # shim you launched. Shipping the binary as both ipxe.efi and snponly.efi
    # satisfies either resolution. Both shims ship for the same reason: which one
    # a given firmware accepts is not predictable from here.
    #
    # The shims keep their ORIGINAL names. Renaming one cannot change what it
    # looks for, and on firmware that does report the filename it breaks the
    # derivation outright.
    if [[ -n $sbdir ]]; then
        echo "${sbdir}/snponly-shim${shimsfx}.efi|secureboot-fog/snponly-shim${shimsfx}.efi"
        echo "${sbdir}/ipxe-shim${shimsfx}.efi|secureboot-fog/ipxe-shim${shimsfx}.efi"
        echo "${sbdir}/${mm}|secureboot-fog/${mm}"
        echo "${genericdir}ipxe.efi|secureboot-fog/ipxe.efi"
        echo "${genericdir}ipxe.efi|secureboot-fog/snponly.efi"
        if [[ $havestock -eq 1 ]]; then
            echo "${sbdir}/snponly-shim${shimsfx}.efi|secureboot-fog-customca/snponly-shim${shimsfx}.efi"
            echo "${sbdir}/ipxe-shim${shimsfx}.efi|secureboot-fog-customca/ipxe-shim${shimsfx}.efi"
            echo "${sbdir}/${mm}|secureboot-fog-customca/${mm}"
            echo "${cadir}ipxe.efi|secureboot-fog-customca/ipxe.efi"
            echo "${cadir}ipxe.efi|secureboot-fog-customca/snponly.efi"
        fi
    fi
}
# The files one archive takes from the WEB tree's service/ipxe, same "src|dst"
# shape. Separate from _espKitFiles() because it is a different source root, not
# because it is a different kind of file.
#
#   $1  architecture: x86_64 | i386 | arm64
#
# WHICH rEFInd BINARY IS NOT A STRAIGHT THREE-WAY MAP. refind.efi is a preferred
# generic: bootmenu.class.php:235-237 uses it in place of refind_x64.efi whenever
# it exists, so the same preference is applied here. Otherwise the ESP and the
# PXE path would disagree about which binary is canonical, and a bug report
# naming "rEFInd" would mean two different files.
#
# refind.conf rides along and is deliberately NOT signed -- it is data, not a PE
# image (see _resignRefind). rEFInd reads it from its own directory, which is
# what refind/ is for.
_espRefindFiles() {
    local arch="$1" ipxedir="${webdirdest%/}/service/ipxe"
    case $arch in
        i386)  echo "refind_ia32.efi|refind/refind_ia32.efi" ;;
        arm64) echo "refind_aa64.efi|refind/refind_aa64.efi" ;;
        *)
            if [[ -f ${ipxedir}/refind.efi ]]; then
                echo "refind.efi|refind/refind.efi"
            else
                echo "refind_x64.efi|refind/refind_x64.efi"
            fi
            ;;
    esac
    echo "refind.conf|refind/refind.conf"
}
# What each published file is, for the manifest. Kept as data rather than
# comments because it is what replaced curation-by-omission: the earlier
# directory said which binary to use by leaving the others out, which is how
# fogsnponly.efi came to be unavailable at all. A manifest can carry the same
# advice without having to withhold a file to give it.
#
# Matched on the path relative to the archive root, not on the basename, because
# autoexec.ipxe appears once per directory. Those copies are identical, so they
# share one role -- the path still matters for everything else.
_espFileRole() {
    case "$1" in
        */snponly-shim*.efi|*/ipxe-shim*.efi)
                                          echo "shim" ;;
        # Upstream's loader only in secureboot-upstream/. The same two names in
        # secureboot-fog*/ are FOG's build wearing them -- a different role and a
        # different origin, which is why these match on the full path.
        secureboot-upstream/snponly.efi|secureboot-upstream/ipxe.efi)
                                          echo "upstream-loader" ;;
        secureboot-fog*/snponly.efi|secureboot-fog*/ipxe.efi)
                                          echo "fog-ipxe-as-shim-stage" ;;
        */mmx64.efi|*/mmaa64.efi)         echo "mokmanager" ;;
        fog-ipxe*/fogipxe.efi)            echo "fog-ipxe" ;;
        fog-ipxe*/fogsnp.efi)             echo "fog-snp" ;;
        fog-ipxe*/fogintel.efi)           echo "fog-intel" ;;
        fog-ipxe*/fogrealtek.efi)         echo "fog-realtek" ;;
        fog-ipxe*/fogsnponly.efi)         echo "fog-snponly" ;;
        autoexec.ipxe|*/autoexec.ipxe)    echo "boot-script" ;;
        refind/*.efi)                     echo "chainloader" ;;
        refind/refind.conf)               echo "config" ;;
        */MOK.der)                        echo "enrollment-cert" ;;
        */PK.auth|*/KEK.auth|*/db.auth)   echo "enrollment-var" ;;
        */fog-enroll-mok.*)               echo "helper" ;;
        README.txt|MANIFEST.json)         echo "doc" ;;
        *)                                echo "other" ;;
    esac
}
_espFileOrigin() {
    case "$1" in
        # Only the shims, MokManager and secureboot-upstream/'s loaders are
        # upstream's bytes. secureboot-fog*/ipxe.efi wears an upstream name and is
        # FOG's build, so it must not be reported as upstream -- fogSigned in the
        # manifest would then look like a contradiction.
        */snponly-shim*.efi|*/ipxe-shim*.efi|*/mmx64.efi|*/mmaa64.efi|\
        secureboot-upstream/snponly.efi|secureboot-upstream/ipxe.efi)
            echo "upstream" ;;
        autoexec.ipxe|*/autoexec.ipxe|README.txt|MANIFEST.json)
            echo "generated" ;;
        *)  echo "fog" ;;
    esac
}
_espFileNote() {
    case "$1" in
        secureboot-upstream/snponly-shim*.efi|secureboot-upstream/ipxe-shim*.efi)
            echo "Microsoft-signed shim. Point your boot manager at this for the Secure Boot route. It derives its second stage from its own filename at runtime -- but where firmware will not report that filename it falls back to ipxe.efi, so on many machines both shims here reach the same loader. Do not rename it. See ipxe/ipxe#1684." ;;
        secureboot-fog*/snponly-shim*.efi|secureboot-fog*/ipxe-shim*.efi)
            echo "Microsoft-signed shim, same binary as in secureboot-upstream/. Here its second stage is FOG's own build rather than upstream's, so this route needs FOG's certificate in MokList or db. Both shims ship because which name a locally booted shim asks for is not predictable. Do not rename either." ;;
        secureboot-upstream/snponly.efi|secureboot-upstream/ipxe.efi)
            echo "Upstream's signed loader, shim's second stage. Reads autoexec.ipxe from this directory and boots FOG with it. Measured across physical hardware, VMware and KVM: a complete route wherever firmware provides SNP. With nothing enrolled it reaches the menu, exits to disk and runs MokManager but cannot image, because FOS's kernel is FOG-signed. Where firmware provides no SNP at all, use fog-ipxe/ instead." ;;
        secureboot-fog*/snponly.efi|secureboot-fog*/ipxe.efi)
            echo "FOG's own all-drivers build, carrying an upstream filename so that the shim beside it will load it -- shim picks its second stage by name, and on many firmwares falls back to ipxe.efi whichever shim you launched, so the binary ships under both names. Needs FOG's certificate in MokList or db; with nothing enrolled shim rejects it. NOT upstream's loader despite the name." ;;
        secureboot-upstream/mmx64.efi|secureboot-upstream/mmaa64.efi|secureboot-fog*/mmx64.efi|secureboot-fog*/mmaa64.efi)
            echo "MokManager. shim launches it from this directory when it cannot verify the next stage; enroll MOK.der beside it. Not optional -- nothing in a running OS can write shim's MokList. Note it is only invoked when a MOK request is already pending, so a first boot with nothing enrolled simply fails rather than offering to enroll." ;;
        fog-ipxe/fogipxe.efi)
            echo "FOG's build with all of iPXE's own NIC drivers. Start here on firmware that offers no PXE boot option at all -- such firmware usually provides no UEFI SNP protocol either, so a binary needing one is no use to it. Boots directly with Secure Boot off, or with this server's certificate in db. A MOK does NOT help here: firmware never reads shim's MokList." ;;
        fog-ipxe-customca/fogipxe.efi)
            echo "As fog-ipxe/fogipxe.efi, but built with this server's CA embedded so iPXE will accept an HTTPS FOG server whose certificate chains to a private CA. Identical under Secure Boot -- CA embedding does not touch the signature." ;;
        fog-ipxe*/fogsnp.efi)
            echo "FOG's build driving the NIC through the firmware's SNP protocol, binding every SNP device it can see. For firmware that does provide one and hardware iPXE's own drivers do not cover." ;;
        fog-ipxe*/fogintel.efi)
            echo "FOG's build, Intel driver only. For when the all-drivers build misbehaves on that specific NIC. Untested here -- kept because it is the answer when it is the answer." ;;
        fog-ipxe*/fogrealtek.efi)
            echo "FOG's build, Realtek driver only. For when the all-drivers build misbehaves on that specific NIC. Untested here -- kept because it is the answer when it is the answer." ;;
        fog-ipxe*/fogsnponly.efi)
            echo "FOG's build bound to the SNP device iPXE was loaded FROM. Booted off an ESP that device is the disk, so in principle it finds no NIC -- though it booted fine on every machine tested here, so try it rather than assuming. Also the right binary when something chainloads it over the network." ;;
        autoexec.ipxe|*/autoexec.ipxe)
            echo "FOG's boot script, read by whichever iPXE binary in THIS directory runs. Walks net0/net1/net2 for DHCP, handles proxyDHCP and next-server, then chains default.ipxe from the server. Every copy in this archive is identical, because iPXE only reads the one beside the binary it started. Edit it to change how this ESP boots; a pre-DHCP sleep for STP or port power-save is commented out at the top." ;;
        refind/refind.conf)
            echo "rEFInd's configuration, read from its own directory. Data, not a PE image, so it carries no signature and needs none." ;;
        refind/*.efi)
            echo "rEFInd, signed by this server. A boot manager that finds and boots whatever OS is installed locally. Not needed by the default exit type (sanboot hands straight back to firmware), but it is what the refind_efi exit type chainloads, and it is here so an ESP assembled from this archive carries every route off FOG rather than only the configured one." ;;
        */MOK.der)
            echo "This server's certificate in DER form, and it does TWO jobs. MokManager enrolls it, which makes the shim routes work. It is also the certificate to put in db, which is what lets FOG's binaries boot directly with no shim -- and when you enroll through the FIRMWARE's own tool, or a hypervisor setting, db on its own is enough because that write is unauthenticated. Enrolling from a running OS instead (FOG's task, a Linux tool, PowerShell) is a User Mode write that must be authenticated by a KEK-signed update, so that route needs the full PK/KEK/db set. It is the intermediate that FOG's signatures carry via sbsign --addcert, so this one certificate covers every FOG binary and the signing leaf can rotate without re-enrolling." ;;
        */PK.auth|*/KEK.auth|*/db.auth)
            echo "Signed EFI variable update, for FOG's unattended enrollment task, which writes all of them from a client in Setup/Custom Mode. NOT what a firmware menu or a hypervisor wants -- those take a plain DER certificate, so use MOK.der there. Replacing db with this keeps Microsoft's CAs and so still boots Windows, but appending MOK.der to the existing db is the lower-risk operation." ;;
        */fog-enroll-mok.sh|*/fog-enroll-mok.desktop)
            echo "Enrolls MOK.der via mokutil from a booted Linux OS. Not read by firmware; it is here so one folder carries every enrollment route." ;;
        README.txt)
            echo "What this archive is and how to use it." ;;
        *)  echo "" ;;
    esac
}
# FOG's boot logic: find a DHCP answer, work out which server to talk to, chain
# FOG's menu.
#
# Emitted into BOTH ESP scripts from this one function, which is the point. The
# two copies used to be different scripts doing different jobs, and the archive
# root's copy could not boot a machine on its own. They are now the same logic
# with a different preamble, so they cannot drift -- and a binary that reads
# either one boots the same way.
#
# Keep this in step with fog-ipxe's own autoexec.ipxe and with src/ipxescript,
# the script the BIOS binaries still embed. All three must behave identically,
# or which platform a site booted becomes a variable in every bug report.
_espBootWalk() {
    cat <<'ESPWALK'
echo Checking net0 for DHCP...
isset ${net0/mac} && ifopen net0 && dhcp net0 || goto dhcpnet1
echo Received DHCP answer on interface net0 && goto proxycheck

:dhcpnet1
echo Checking net1 for DHCP...
isset ${net1/mac} && ifopen net1 && dhcp net1 || goto dhcpnet2
echo Received DHCP answer on interface net1 && goto proxycheck

:dhcpnet2
echo Checking net2 for DHCP...
isset ${net2/mac} && ifopen net2 && dhcp net2 || goto dhcpall
echo Received DHCP answer on interface net2 && goto proxycheck

:dhcpall
echo No DHCP answer on any interface, trying proxy DHCP...
dhcp && goto proxycheck || goto dhcperror

:dhcperror
echo DHCP error!
prompt --key s --timeout 10000 DHCP failed, hit 's' for the iPXE shell; reboot in 10 seconds && shell || reboot

:proxycheck
echo Trying proxy DHCP...
isset ${proxydhcp/next-server} && set next-server ${proxydhcp/next-server} || goto nextservercheck

:nextservercheck
echo Checking for next-server...
isset ${next-server} && goto netboot || goto setserv

:setserv
echo -n Please enter tftp server: && read next-server && goto netboot || goto setserv

:chainloadfailed
prompt --key s --timeout 10000 Chainloading failed, hit 's' for the iPXE shell; reboot in 10 seconds && shell || reboot

:netboot
echo starting netboot to default.ipxe...
chain tftp://${next-server}/default.ipxe || goto chainloadfailed
ESPWALK
}
# THE archive's autoexec.ipxe. One generator, one script, copied verbatim into
# every directory that holds a bootable binary and once at the archive root.
#
# NOTHING IN THIS ARCHIVE CHAINS ANY MORE, and that is the whole design. The
# archive root used to hold a five-deep `chain local/fog*.efi` ladder while
# local/ held the real boot logic -- two files with one name doing different
# jobs. It looped.
#
# WHY IT LOOPED, because this is the part that is not obvious from the source.
# When iPXE chains an EFI image, the chained image resolves autoexec.ipxe through
# the synthetic EFI_SIMPLE_FILE_SYSTEM_PROTOCOL that efi_image_exec() installs,
# and that handle serves registered images BY FLAT NAME. So the chained fog*.efi
# re-read the ROOT ladder rather than its own sibling in local/, hit the ladder
# again, and chained itself until the firmware ran out of pool memory -- which is
# what surfaced as a VMware firmware-exception dialog. local/ never isolated the
# two scripts: flat-name lookup ignores directories. Refs GH-1195.
#
# A guard on `isset ${netN/mac}` was tried instead of removing the chain, and
# rejected. It could not be bounded: each chained binary re-reads the root script
# from the top, and iPXE settings do not survive a chain into a new image, so
# there is no sentinel to break a second pass. It also only checked net0-net2,
# where the walk below falls back to a bare `dhcp` covering every interface iPXE
# enumerated -- so a machine with its NIC at net3 would have been handed off
# instead of booting. Removing the chain removes both problems: with no `chain`
# anywhere, no loop is constructible, and the flat-name re-read above becomes
# harmless because every copy of this file is identical.
#
# WHAT REPLACES THE LADDER for firmware that provides no SNP: point the boot
# manager at local\fogipxe.efi directly. The README says so. That needs no
# chain, and it is the documented route for the hardware this feature exists
# for -- firmware with no PXE option at all.
#
# Measured, and it is why the ladder could go: upstream's snponly.efi booted off
# an ESP brings up net0 and netboots FOG perfectly well with the WHOLE local/
# DIRECTORY DELETED. The old comment's claim that both upstream loaders
# "dead-end" is therefore not universal -- it may still hold on firmware with no
# SNP, which is untested.
#
# STILL UNTESTED, recorded so nobody reads more confidence into this than it has:
# no physical machine has run any of it. Every result above came from VMs, which
# all provide SNP or NII and so are not the hardware in question. Two of them
# disagreed about which variant drives their NIC -- one reported SNP, one NII --
# so no preference order is encoded anywhere in this archive.
#
# One earlier run reportedly failed with the plain netboot script at the archive
# root, which is what motivated the ladder originally. That script and this one
# are byte-identical; three variables were uncontrolled across those runs and all
# three have since been individually eliminated -- mounted install media, db/KEK
# enrollment state, and which shim/loader pair was used. Treat that failure as
# unexplained rather than as evidence for a chain.
_espAutoexecScript() {
    cat <<'ESPAUTOEXEC'
#!ipxe
# FOG's boot script, read off the ESP by whichever iPXE binary in THIS directory
# the firmware started. Finds a DHCP answer, works out which server to talk to,
# and chains FOG's menu. Edit this file to change how this ESP boots.
#
# One identical copy sits in every directory of this archive that holds a
# bootable binary, plus one at the archive root as iPXE's volume-root fallback.
# They are the same file on purpose: iPXE only ever reads the one beside the
# binary it started, so which copy a machine used stops being a variable.
#
# Kept in step with the copy on the FOG server's TFTP root, with fog-ipxe's own
# autoexec.ipxe, and with src/ipxescript, the script the BIOS binaries still
# embed. All of them must behave identically.
ESPAUTOEXEC
    _bootDelayBlock
    _espBootWalk
}
# The archive's README. Written per archive rather than once, because which
# folders exist depends on whether a shim landed, whether rEFInd landed, and
# whether this server rebuilt iPXE with its own CA.
#   $1 arch  $2 archive stem  $3 1 if an upstream loader is present
#   $4 1 if rEFInd is present  $5 1 if the custom-CA folders are present
_espKitReadme() {
    local arch="$1" stem="$2" haveloader="$3" haverefind="$4" havestock="$5"
    cat <<ESPREADME
${stem}
$(printf '%*s' ${#stem} '' | tr ' ' '=')

FOG Project -- iPXE boot files for an EFI System Partition (${arch}).
ESPREADME
    cat <<'ESPINTRO'

Copy everything here onto the ESP -- \EFI\FOG\ is a good place -- keeping the
subdirectories exactly as they are. Then point the firmware boot manager at ONE
file inside ONE folder.

THE SUBDIRECTORIES ARE NOT TIDINESS. iPXE reads its boot script, autoexec.ipxe,
out of the directory the running binary was loaded from, so every folder that
holds a bootable binary holds a copy of that script. The copies are identical.
Move a binary away from its script and it has nothing to boot with.

NOTHING HERE CHAINS ANYTHING ELSE. The binary you pick reads the script beside it
and boots FOG. If it does not work, pick a different one -- there is no fallback
chain to wait for, by design: the chain that used to be here could re-enter
itself and hang the firmware.

ESPINTRO
    # Branched: an i386 archive has no shim at all (upstream signs none for
    # ia32), and an HTTPS-only install stages none either. Naming folders that
    # are not in the archive sends an admin hunting for files that do not exist,
    # which is the failure the previous README was careful to avoid.
    if [[ $haveloader -eq 1 ]]; then
        cat <<'ESPPICK'

WHICH FOLDER
------------
  Machine PXE boots normally
      -> secureboot-upstream\   Upstream's shim and loader, nothing to enroll.

  No PXE boot option, or firmware provides no SNP
      -> fog-ipxe\              FOG's builds carry iPXE's own NIC drivers and
                                bring the network up themselves. This is the
                                case this archive exists for.

  Secure Boot ON with this server's MOK enrolled, or Secure Boot OFF,
  and you want to keep using the shim
      -> secureboot-fog\        Upstream's shim, FOG's build as its second stage.

  This server's certificate is in db
      -> fog-ipxe\              Boot it directly. The shim buys nothing once
                                firmware trusts the certificate itself, and this
                                is the shortest route: one image, one signature.

WHAT WORKS IN WHICH SECURE BOOT STATE
-------------------------------------
Measured on physical hardware, VMware and KVM.

                            SB off    SB on      SB on      SB on
                                      nothing    MOK        cert
                                      enrolled   enrolled   in db
  secureboot-upstream\      yes       menu only  yes        yes
  fog-ipxe\                 yes       NO         NO         yes
  secureboot-fog\           yes       NO         yes        yes

  "menu only"  reaches FOG's menu, exits to disk and can run MokManager, but
               IMAGING TASKS FAIL -- FOS's kernel is signed by this server, so
               imaging needs the certificate trusted however you got to the menu.
  "NO"         the image is refused and never runs.

TWO THINGS THAT SURPRISE PEOPLE
  A MOK does nothing for fog-ipxe\. MokList belongs to shim; firmware never
  reads it. Booting FOG's binary directly under Secure Boot needs db.

  With nothing enrolled, secureboot-fog\ does not offer to enroll -- it just
  fails. shim only launches MokManager when a request is already pending, and
  nothing here stages one. Enroll first, then boot.
ESPPICK
    else
        cat <<'ESPPICKNOSB'

WHICH FOLDER
------------
There is only one: fog-ipxe\. No Microsoft-signed shim exists for this
architecture, so this archive carries no shim route at all.

WHAT WORKS IN WHICH SECURE BOOT STATE
-------------------------------------
                            SB off    SB on      SB on      SB on
                                      nothing    MOK        cert
                                      enrolled   enrolled   in db
  fog-ipxe\                 yes       NO         NO         yes

A MOK does nothing here. MokList belongs to shim, and there is no shim on this
architecture; firmware never reads MokList itself. With Secure Boot on, the only
route is this server's certificate in db -- see ENROLLING below.
ESPPICKNOSB
    fi
    # Unquoted, because the folder guide branches on what actually staged. Watch
    # for two things when editing: a line must not END with a backslash (bash
    # reads it as continuation), and a literal $ has to be escaped.
    cat <<ESPBODY

THE FOLDERS
-----------
$(if [[ $haveloader -eq 1 ]]; then cat <<'ESPFLD1'
  secureboot-upstream\   Upstream's Microsoft-signed shims and the loaders they
                         hand to, unmodified, plus MokManager and this server's
                         enrollment material. Boot snponly-shimx64.efi or
                         ipxe-shimx64.efi.
  secureboot-fog\        The same shims, but FOG's own build stands in as the
                         second stage. It appears twice, as ipxe.efi and as
                         snponly.efi, because a locally booted shim asks for
                         whichever name it can derive -- and on many firmwares
                         it cannot derive one and falls back to ipxe.efi. Do not
                         rename the shims; that cannot change what they look for.
ESPFLD1
fi)
  fog-ipxe\              FOG's own builds, no shim involved.
$(if [[ $havestock -eq 1 ]]; then cat <<'ESPFLD2'
  fog-ipxe-customca\     The same builds with this server's CA embedded, so iPXE
                         will accept an HTTPS FOG server whose certificate chains
                         to a private CA. Identical under Secure Boot -- CA
                         embedding does not touch the signature. Use these if
                         your FOG server uses HTTPS with your own CA.
  secureboot-fog-customca\ The shim route on those CA-embedded builds.
ESPFLD2
fi)

IF IT DOES NOT BRING UP YOUR NETWORK
------------------------------------
FIRST, CHECK THE FIRMWARE HAS AN IPv4-CONFIGURED NIC. This is the trap. If the
NIC's IPv4 setting is not DHCP, no SNP device exists at all -- so snp and snponly
builds find nothing, and the firmware will not offer a UEFI PXE boot option
either. On OVMF/KVM: Device Manager -> Network Device List -> pick the NIC ->
IPv4 Network Configuration -> tick Enable DHCP -> SAVE WITH F10. The device and
the PXE option both appear afterward.

Then, if it is really the binary, pick a different one:

  fog-ipxe\fogipxe.efi     all of iPXE's own NIC drivers. Start here on firmware
                           with no PXE boot option -- such firmware usually
                           provides no SNP either, so a binary needing one is no
                           use to it.
  fog-ipxe\fogsnp.efi      drives the NIC through the firmware's SNP protocol.
                           Good where the firmware does provide one.
  fog-ipxe\fogintel.efi    single-driver builds, for when the all-drivers build
  fog-ipxe\fogrealtek.efi  misbehaves on that specific NIC. Untested here.
  fog-ipxe\fogsnponly.efi  binds only the device iPXE was loaded from -- off an
                           ESP that is the disk, so in principle it finds no NIC.
                           It booted fine on every machine tested here anyway,
                           so try it rather than assuming.

No order beyond that is prescribed, because it genuinely varies: two machines
tested during this work disagreed about which build drove their NIC, one
reporting SNP and the other NII.

CHANGING HOW IT BOOTS
---------------------
autoexec.ipxe is FOG's boot script: the DHCP walk across net0/net1/net2,
proxyDHCP, next-server, then FOG's menu. Edit it in place; nothing has to be
rebuilt. Every copy in this archive is identical, so edit the one in the same
folder as the binary you boot -- or all of them, if you switch between them.
If your switch runs STP or port power-save and the link is not up when iPXE first
asks for DHCP, uncomment the sleep at the top -- or reinstall the server with
--boot-delay <seconds>, which writes that line for you here and for netboot
clients at the same time.

ENROLLING THIS SERVER'S CERTIFICATE
-----------------------------------
MOK.der is this server's certificate, and it does TWO jobs. Only the name
advertises the first.
$(if [[ $haveloader -eq 1 ]]; then cat <<'ESPMOK'

  As a MOK, for the shim routes:
      Boot a shim. When it cannot verify the next stage it launches MokManager;
      choose "Enroll key from disk" and select MOK.der from that same folder.
      Reboot. Note this only happens when a request is already pending -- see
      above.
ESPMOK
else cat <<'ESPNOMOK'

  As a MOK: not on this architecture. MokList belongs to shim and there is no
  shim here, so the MOK route does not exist. Use db, below.
ESPNOMOK
fi)

  As the db certificate, for booting with no shim at all:
      Put MOK.der in db. db is what firmware checks to verify a boot image; PK
      and KEK only control who may CHANGE db.

      HOW MANY VARIABLES YOU NEED DEPENDS ON WHO DOES THE WRITE:

        Firmware's own tool, or a hypervisor setting, with the platform in
        Setup/Custom Mode -- db ALONE is enough. That write is unauthenticated,
        so nothing has to vouch for it. Confirmed: MOK.der added to db by itself,
        then FOG's signed binary booted directly with no shim.

        From a running OS -- FOG's enrollment task, a Linux tool, PowerShell's
        Secure Boot cmdlets -- expect to need PK, KEK and db together. In User
        Mode a db write must be authenticated by a KEK-signed update, and the
        machine only trusts FOG's KEK if FOG's PK is enrolled too. That is what
        the .auth files are for.

      Not every firmware has been tested either way. If yours behaves
      differently, please say so on the FOG forums or in the issue tracker.

      MOK.der is the intermediate, and FOG's signatures carry it inside them, so
      this one certificate covers every binary here and the signing key can be
      rotated without re-enrolling anything.

      On VMware, put MOK.der in the VM's directory and add to the .vmx:
          uefi.secureBoot.dbDefault.file0 = "MOK.der"
      On an existing VM, uefi.allowAuthBypass = "TRUE" lets you add it through
      the firmware UI. Hand the UI MOK.der -- NOT the .auth files; those are a
      different kind of artifact and a firmware menu cannot read them.

      APPEND, do not replace. Replacing db drops Microsoft's certificates and
      Windows stops booting.

      Changing db is measured into TPM PCR 7, so it can trigger BitLocker
      recovery. Suspend BitLocker first on machines that use it.

PK.auth, KEK.auth and db.auth are for FOG's own unattended enrollment task, which
writes all of them from a client in Setup/Custom Mode. They are not what a
firmware menu or a hypervisor wants.
$(if [[ $haverefind -eq 1 ]]; then cat <<'ESPREFIND'

BOOTING THE LOCAL OS AGAIN
--------------------------
refind\ holds rEFInd, signed by this server. FOG's default exit type chainloads
it to boot whatever OS is installed on the machine, so it is what gets a host off
FOG and back into Windows or Linux. You can also point the firmware boot manager
straight at it. refind.conf beside it is its configuration; rEFInd reads that
from its own directory, which is why the two travel together.
ESPREFIND
fi)

MANIFEST.json lists every file here with its sha256 and what it is for.
Full documentation: docs/SUPPORTED_CUSTOMIZATIONS.md, "Local ESP boot files".
ESPBODY
}
# Quote a string as a JSON scalar.
#
# Deliberately not jq, even though jq is in the package list. Everything this
# feature encodes is content this file itself produced -- our own filenames,
# the role/note text above, hex checksums, decimal sizes -- so the escaping
# problem is bounded, and one code path that always works beats a dependency
# whose absence would mean publishing no manifest at all. That is the same
# reasoning as the tar fallback for zip below: a partly-failed package install
# should cost a server some polish, not the whole feature.
#
# Covers the characters JSON forbids in a string; anything else in the ASCII
# range is legal unescaped, and nothing here emits a control character.
_jsonStr() {
    local s="$1"
    s="${s//\\/\\\\}"
    s="${s//\"/\\\"}"
    s="${s//$'\t'/\\t}"
    s="${s//$'\r'/\\r}"
    s="${s//$'\n'/\\n}"
    printf '"%s"' "$s"
}
# One JSON array describing every file in a staged archive directory.
#
# RECURSES, and "name" is the path relative to the staged root --
# "fog-ipxe/fogipxe.efi", not "fogipxe.efi". A -maxdepth 1 walk would omit the
# subdirectories entirely, and it would do it silently: every consumer of this
# array, including the test harness, checks the files the manifest names rather
# than the files on disk, so an under-reporting manifest reads as a passing one.
#
# The role/origin/note lookups take that same relative path, because two files
# here are called autoexec.ipxe and they are not the same thing.
#
# Called BEFORE MANIFEST.json is written, so MANIFEST.json is deliberately
# absent from its own inventory -- a file cannot carry its own checksum.
#   $1 staged directory   $2 Secure Boot anchor PEM, or empty
_espKitContentsJson() {
    local dir="$1" anchor="$2"
    local f name sum size fogsigned first=1
    printf '['
    while IFS= read -r f; do
        name="${f#${dir}/}"
        sum=$(sha256sum "$f" 2>/dev/null | cut -d' ' -f1)
        size=$(wc -c < "$f" 2>/dev/null | tr -d '[:space:]')
        [[ -z $sum || -z $size ]] && continue
        # Asked of the file rather than inferred from whether this server holds
        # keys, so the answer stays right for the upstream binaries (which carry
        # Microsoft's and iPXE's signatures, never FOG's) and for a run where
        # signing partly failed.
        fogsigned=false
        if [[ -n $anchor && $name == *.efi ]] && \
           sbverify --cert "$anchor" "$f" >/dev/null 2>&1; then
            fogsigned=true
        fi
        [[ $first -eq 0 ]] && printf ','
        first=0
        printf '{"name":%s,"size":%s,"sha256":%s,"role":%s,"origin":%s,"fogSigned":%s,"note":%s}' \
            "$(_jsonStr "$name")" "$size" "$(_jsonStr "$sum")" \
            "$(_jsonStr "$(_espFileRole "$name")")" \
            "$(_jsonStr "$(_espFileOrigin "$name")")" \
            "$fogsigned" "$(_jsonStr "$(_espFileNote "$name")")"
    done < <(find "$dir" -type f 2>/dev/null | sort)
    printf ']'
}
# The FOS kernel/init set, listed but NOT copied.
#
# Bundling them would be 60-80MB per architecture and would still not produce a
# working local boot on its own: FOS reads per-host, per-task kernel arguments
# that boot.php generates, so a kernel and initrd sitting on an ESP do nothing
# until someone hand-writes those. That is a different feature with its own
# design questions.
#
# Listing them costs nothing and makes this manifest the single index for
# everything fetchable for a local boot. No new exposure: every PXE client
# already fetches these over unauthenticated HTTP from the same directory
# (bootmenu.class.php, $_booturl).
_espKernelsJson() {
    local ipxedir="${webdirdest%/}/service/ipxe"
    local n arch kind sum size first=1
    printf '['
    for n in bzImage bzImage32 arm_Image init.xz init_32.xz arm_init.cpio.gz; do
        [[ -f ${ipxedir}/${n} ]] || continue
        case $n in
            bzImage)          arch=x86_64; kind=kernel ;;
            bzImage32)        arch=i386;   kind=kernel ;;
            arm_Image)        arch=arm64;  kind=kernel ;;
            init.xz)          arch=x86_64; kind=init ;;
            init_32.xz)       arch=i386;   kind=init ;;
            arm_init.cpio.gz) arch=arm64;  kind=init ;;
        esac
        sum=$(sha256sum "${ipxedir}/${n}" 2>/dev/null | cut -d' ' -f1)
        size=$(wc -c < "${ipxedir}/${n}" 2>/dev/null | tr -d '[:space:]')
        [[ -z $sum || -z $size ]] && continue
        [[ $first -eq 0 ]] && printf ','
        first=0
        printf '{"name":%s,"path":%s,"arch":%s,"kind":%s,"size":%s,"sha256":%s}' \
            "$(_jsonStr "$n")" "$(_jsonStr "../ipxe/${n}")" \
            "$(_jsonStr "$arch")" "$(_jsonStr "$kind")" \
            "$size" "$(_jsonStr "$sum")"
    done
    printf ']'
}
# Build and publish the archives.
#
# NOT gated on Secure Boot, and not gated on _signLocalIpxe() having signed
# anything. Booting a machine from an iPXE binary on its own ESP is a plain
# feature that predates Secure Boot by years -- firmware with no PXE option, or
# a queued task that would otherwise need the boot order changed. Secure Boot
# only added the requirement for a signature.
#
# That is also why this lives at service/localboot/ rather than under
# service/secureboot/: _publishSecureBootKit() rm -rf's its whole kit directory
# when it has no MOK to publish, which would take this with it on exactly the
# servers that still want it. The enrollment material it publishes is COPIED into
# each archive rather than linked, so an archive stays self-contained and a
# server that later opts out of enrollment cannot dangle a reference inside one.
#
# COPIES, never a symlink to $tftpdirdst, which was the first design. SELinux
# labels the TFTP tree tftpdir_t and httpd_t has no rule permitting it to read
# that type, so a link 403s on every enforcing host; it also needed Options
# -Indexes across three Apache variants and rested on Apache matching
# <Directory> against the unresolved path (GH-529), a dependency that fails
# OPEN. Files created here inherit the web root's own label and need no policy
# change at all.
_publishLocalBootFiles() {
    local tftproot="${tftpdirdst%/}"
    [[ -d $tftproot ]] || return 0
    local bootdir="${webdirdest%/}/service/localboot"
    local kitdir="${webdirdest%/}/service/secureboot"
    local ipxedir="${webdirdest%/}/service/ipxe"
    dots "Publishing local ESP boot archives"
    # Rebuilt rather than updated in place: a variant dropped upstream should
    # disappear here too, and a stale archive must not outlive the binaries it
    # was built from. Safe unconditionally -- configureHttpd() rm -rf's the
    # whole web root every run, so there is never anything here worth keeping.
    rm -rf "$bootdir" >>$error_log 2>&1
    mkdir -p "$bootdir" >>$error_log 2>&1
    # Resolved once. Empty on a server with no signing key, which makes every
    # fogSigned in the manifest false -- correctly.
    local anchorpem=""
    if [[ -n ${PKI_sb_codesign_key} && -n ${PKI_sb_codesign_cert} ]] && \
       command -v sbverify >/dev/null 2>&1; then
        anchorpem=$(_secureBootAnchorPem) || anchorpem=""
    fi
    local work
    work=$(mktemp -d 2>>$error_log) || {
        echo "Failed"
        echo " * Could not create a staging directory. See $error_log."
        return 0
    }
    local arch stem staged pair src dst f d
    local copied haveloader haverefind contents archive ext asum asize
    local built=0 failed=0 archjson="" first=1
    # Whether this server rebuilt iPXE with its own CA. stock/ exists only because
    # _preserveStockIpxe() snapshotted the published set before that build, so its
    # presence is exactly the signal -- and it means the TREE ROOT is the
    # CA-embedded set and stock/ is the generic one. _espKitFiles() reads this to
    # decide which tree feeds fog-ipxe/ versus fog-ipxe-customca/.
    local havestock=0
    [[ -d ${tftproot}/stock ]] && havestock=1
    for arch in x86_64 i386 arm64; do
        stem="fog-esp-${arch}"
        staged="${work}/${stem}"
        mkdir -p "${staged}" >>$error_log 2>&1
        copied=0
        haveloader=0
        haverefind=0
        while IFS= read -r pair; do
            [[ -z $pair ]] && continue
            src="${pair%%|*}"
            dst="${pair#*|}"
            # Missing is not a failure. An HTTPS install stages no Secure
            # Boot binaries at all -- downloadipxesecureboot() skips it --
            # so the upstream entries are absent on those servers by design,
            # and the archive is still worth building without them.
            [[ -f ${tftproot}/${src} ]] || continue
            # Create the destination directory here rather than pre-creating a
            # fixed list: every destination in _espKitFiles() now carries a
            # subdirectory prefix, cp will not create one, and creating them
            # lazily means a set that staged nothing leaves no empty directory
            # behind in the archive.
            mkdir -p "$(dirname "${staged}/${dst}")" >>$error_log 2>&1
            if cp -f "${tftproot}/${src}" "${staged}/${dst}" >>$error_log 2>&1; then
                copied=$((copied + 1))
                case $dst in
                    secureboot-upstream/snponly.efi|secureboot-upstream/ipxe.efi)
                        haveloader=1 ;;
                esac
            else
                failed=1
            fi
        done < <(_espKitFiles "$arch" "$havestock")
        # rEFInd, from the web tree. A storage node has no service/ipxe at all
        # (configureMinHttpd never lays it down), and an admin can have removed
        # a binary, so absence is not a failure here either -- the archive is
        # still a working netboot kit without a local-boot chainloader.
        while IFS= read -r pair; do
            [[ -z $pair ]] && continue
            src="${pair%%|*}"
            dst="${pair#*|}"
            [[ -f ${ipxedir}/${src} ]] || continue
            mkdir -p "$(dirname "${staged}/${dst}")" >>$error_log 2>&1
            if cp -f "${ipxedir}/${src}" "${staged}/${dst}" >>$error_log 2>&1; then
                copied=$((copied + 1))
                case $dst in
                    refind/*.efi) haverefind=1 ;;
                esac
            else
                failed=1
            fi
        done < <(_espRefindFiles "$arch")
        # Nothing for this architecture in the tree at all: no archive,
        # rather than an archive holding only a README.
        if [[ $copied -eq 0 ]]; then
            rm -rf "$staged" >>$error_log 2>&1
            continue
        fi
        # Whatever enrollment material this server actually published. Taken
        # by existence rather than by asking whether Secure Boot is
        # configured, so an opted-out server ships neither and a server with
        # only a MOK ships only that.
        #
        # The .auth set goes to secureboot-upstream/ only -- they belong to FOG's
        # unattended enrollment task and are nothing a shim reads.
        #
        # MOK.der goes to EVERY folder that holds a shim, because MokManager
        # browses for it and shim launches MokManager out of its own directory. A
        # folder with a shim and no certificate beside it is a dead end at exactly
        # the moment the admin needs the file.
        for f in PK.auth KEK.auth db.auth \
                 fog-enroll-mok.sh fog-enroll-mok.desktop; do
            [[ -f ${kitdir}/${f} ]] || continue
            mkdir -p "${staged}/secureboot-upstream" >>$error_log 2>&1
            cp -f "${kitdir}/${f}" "${staged}/secureboot-upstream/${f}" \
                >>$error_log 2>&1
        done
        if [[ -f ${kitdir}/MOK.der ]]; then
            for d in secureboot-upstream secureboot-fog secureboot-fog-customca; do
                [[ -d ${staged}/${d} ]] || continue
                cp -f "${kitdir}/MOK.der" "${staged}/${d}/MOK.der" \
                    >>$error_log 2>&1
            done
        fi
        # One identical copy of the boot script in every directory that holds a
        # bootable binary, plus one at the archive root.
        #
        # iPXE asks for autoexec.ipxe relative to the directory the running
        # binary was loaded from and only then falls back to the volume root, so
        # a directory of binaries without a script beside them is a directory
        # that cannot boot. The root copy is the volume-root fallback for an ESP
        # whose contents were unpacked at the top level, and the landing spot for
        # the flat-name lookup a chaining iPXE performs.
        #
        # Identical, from one generator, so it does not matter which one a
        # machine read -- which is the property the old two-different-scripts
        # arrangement lacked, and the reason it could loop.
        #
        # Driven off the folder holding a BOOTABLE BINARY, not merely existing.
        # The distinction is load-bearing: on an HTTPS-only install no shim
        # stages, but the enrollment material still lands in secureboot-upstream/,
        # so that folder exists while containing nothing that could read a script.
        # A script beside no binary is a file that implies a route the archive
        # does not have.
        _espAutoexecScript > "${staged}/autoexec.ipxe" 2>>$error_log
        for d in fog-ipxe fog-ipxe-customca secureboot-upstream \
                 secureboot-fog secureboot-fog-customca; do
            [[ -d ${staged}/${d} ]] || continue
            compgen -G "${staged}/${d}/*.efi" >/dev/null || continue
            _espAutoexecScript > "${staged}/${d}/autoexec.ipxe" 2>>$error_log
        done
        _espKitReadme "$arch" "$stem" "$haveloader" "$haverefind" "$havestock" \
            > "${staged}/README.txt" 2>>$error_log
        contents=$(_espKitContentsJson "$staged" "$anchorpem")
        {
            printf '{\n'
            printf '  "archive": %s,\n' "$(_jsonStr "$stem")"
            printf '  "arch": %s,\n' "$(_jsonStr "$arch")"
            printf '  "contents": %s\n' "$contents"
            printf '}\n'
        } > "${staged}/MANIFEST.json" 2>>$error_log
        ext="zip"
        # PACKED WITHOUT A WRAPPER DIRECTORY -- the archive's own contents are
        # its top level.
        #
        # It used to hold a single directory named after the archive, so
        # extracting fog-esp-x86_64.zip gave you fog-esp-x86_64/. That is one
        # level too many on Windows, where Explorer's "Extract All" creates a
        # folder named after the zip and then unpacks the wrapper inside it --
        # fog-esp-x86_64\fog-esp-x86_64\ -- and the README's "copy the CONTENTS
        # of this folder" then names the wrong folder. Packing the contents flat
        # means Explorer's own folder IS the archive directory.
        #
        # Both writers change directory into the staged tree, which means
        # $bootdir has to be absolute. It always is -- ${WEB_docroot} is a distro
        # default under / and --docroot is normalized with a leading slash
        # (installfog.sh) -- but that is the dependency to keep in mind if
        # docroot handling changes. Both recurse, so local/, secureboot/ and
        # refind/ need nothing extra here.
        if command -v zip >/dev/null 2>&1; then
            # -X drops the extra attribute blocks, so a re-run over
            # unchanged binaries differs only by mtime.
            ( cd "$staged" && zip -q -r -X "${bootdir}/${stem}.zip" . ) \
                >>$error_log 2>&1
        else
            # zip is in the install list, so this is a fallback for a server
            # whose package install partly failed rather than an expected
            # path. tar is a hard dependency of the installer already.
            ext="tar.gz"
            tar -czf "${bootdir}/${stem}.tar.gz" -C "$staged" . \
                >>$error_log 2>&1
        fi
        archive="${stem}.${ext}"
        if [[ ! -f ${bootdir}/${archive} ]]; then
            failed=1
            rm -rf "$staged" >>$error_log 2>&1
            continue
        fi
        asum=$(sha256sum "${bootdir}/${archive}" 2>/dev/null | cut -d' ' -f1)
        asize=$(wc -c < "${bootdir}/${archive}" 2>/dev/null | tr -d '[:space:]')
        [[ $first -eq 0 ]] && archjson="${archjson},"
        first=0
        # No "root" key any more: the archive no longer contains a wrapper
        # directory, so there is no name for a consumer to strip. Its absence is
        # the signal, which is why "schema" below went to 3.
        archjson="${archjson}$(printf \
            '{"path":%s,"arch":%s,"size":%s,"sha256":%s,"contents":%s}' \
            "$(_jsonStr "$archive")" "$(_jsonStr "$arch")" \
            "${asize:-0}" "$(_jsonStr "$asum")" "$contents")"
        built=$((built + 1))
        rm -rf "$staged" >>$error_log 2>&1
    done
    rm -rf "$work" >>$error_log 2>&1
    # Static, written once here. Deliberately NOT a PHP endpoint that lists the
    # directory on request: that would be a directory listing with a traversal
    # surface, to save writing a file that changes only when the installer runs.
    # Paths are relative to this file's own URL so it resolves under whatever
    # hostname and webroot the admin reached it by.
    {
        printf '{\n'
        # 3: archives no longer carry a wrapper directory, so each entry lost its
        # "root" key, and the upstream Secure Boot set moved from the archive
        # root into secureboot/. Both change the paths a consumer would build.
        printf '  "schema": 3,\n'
        printf '  "generated": %s,\n' \
            "$(_jsonStr "$(date -u +%Y-%m-%dT%H:%M:%SZ 2>/dev/null)")"
        printf '  "fogVersion": %s,\n' "$(_jsonStr "${version}")"
        printf '  "ipxeVersion": %s,\n' "$(_jsonStr "${ipxeVer}")"
        printf '  "archives": [%s],\n' "$archjson"
        printf '  "kernels": %s\n' "$(_espKernelsJson)"
        printf '}\n'
    } > "${bootdir}/manifest.json" 2>>$error_log
    # The same 404 stub the Secure Boot kit and service/ipxe use. One directory
    # now instead of ten: DirectoryIndex names index.php in every variant
    # configureHttpd() emits, but only suppresses a listing where an index.php
    # actually exists -- and mod_autoindex is live on a stock /var/www/html,
    # because the "Options +FollowSymLinks" emitted there MERGES with the
    # distro's own "Options Indexes FollowSymLinks" rather than replacing it.
    echo '<?php header("HTTP/1.1 404 Not Found");' > "${bootdir}/index.php"
    chmod 0755 "$bootdir" >>$error_log 2>&1
    find "$bootdir" -type f -exec chmod 0644 {} \; >>$error_log 2>&1
    # _publishSecureBootKit()'s own chown -R does not reach here; this is a
    # sibling of that directory, not a child of it.
    chown -R "${apacheuser}":"${apacheuser}" "$bootdir" >>$error_log 2>&1
    if [[ $failed -ne 0 || $built -eq 0 || ! -s ${bootdir}/manifest.json ]]; then
        echo "Failed"
        echo " * Could not publish the local ESP boot archives to $bootdir."
        echo "   Netboot is unaffected; assembling an ESP from that URL will be"
        echo "   missing files. See $error_log."
        return 0
    fi
    echo "Done (${built})"
}
# Sign CUSTOM kernels for UEFI Secure Boot.
#
# _resignKernels() covers the three names FOG downloads -- bzImage, bzImage32,
# arm_Image -- and nothing else. But a kernel reaching a client does not have to
# be one of those: bootmenu.class.php honors a per-host hostKernel/hostInit
# override, groups set the same fields, and FOG_TFTP_PXE_KERNEL/_32/_ARM change
# the default globally. Any of those boots a file this server has never signed,
# which under Secure Boot fails exactly like the unsigned rEFInd did -- only at
# the imaging step rather than on the way out of the menu.
#
# Runs after restorePreservedCustomizations() for the same reason _resignRefind
# does, and more strongly: a custom kernel is not in the web package at all, so
# configureHttpd()'s rm -rf removes it and the restore is what puts it back.
# Before the restore there is literally nothing here to sign.
#
# WHICH files are custom is decided by subtraction, mirroring
# backupPreservedCustomizations() -- (live directory) minus (what the source
# tree ships) -- rather than by enumerating the settings above. Enumeration
# cannot be complete: an admin's own pre-boot customization can chain a kernel
# name FOG records nowhere. Subtraction covers those too, and reuses a rule that
# already had to be got right for the backup.
#
# WHETHER a leftover file is signable is decided by sbverify, not by its name or
# by an MZ magic test. The initrds (init.xz, arm_init.cpio.gz) sit in this same
# directory and are not PE images; neither are memdisk or memtest.bin. grub.exe
# is the one that makes a hand-rolled check dangerous -- it opens with a valid
# "MZ" and is still not a PE ("pehdr is beyond end of file"), so a magic-byte
# test would try to sign it. sbverify --list accepts exactly the images sbsign
# can handle and rejects all of the above.
_resignCustomKernels() {
    [[ -z ${PKI_sb_codesign_key} || -z ${PKI_sb_codesign_cert} ]] && return 0
    local ipxedir="${webdirdest%/}/service/ipxe"
    [[ -d $ipxedir ]] || return 0
    # _resignKernels() has already warned in this same run if these are absent.
    command -v sbsign >/dev/null 2>&1 || return 0
    command -v sbverify >/dev/null 2>&1 || return 0
    local shippeddir="${webdirsrc%/}/service/ipxe"
    local kf bn certpem anchorpem failed=0 signed=0 names=""
    certpem=$(_secureBootCertPem) || return 0
    anchorpem=$(_secureBootAnchorPem) || anchorpem="$certpem"
    local addcert=()
    [[ -n ${PKI_sb_ca_cert} ]] \
        && [[ "$(readlink -f "${PKI_sb_ca_cert}" 2>/dev/null)" != "$(readlink -f "$certpem" 2>/dev/null)" ]] \
        && addcert=(--addcert "${PKI_sb_ca_cert}")
    for kf in "${ipxedir}"/*; do
        [[ -f $kf ]] || continue
        bn=$(basename "$kf")
        # Shipped by FOG -> not custom. rEFInd is caught here, having already
        # been dealt with by _resignRefind(). If the source tree cannot be
        # found this test fails for everything and each file falls through to
        # the checks below, which is harmless: the PE gate rejects the blobs
        # and the anchor test skips anything already signed.
        [[ -e "${shippeddir}/${bn}" ]] && continue
        case $bn in
            # _resignKernels() owns the downloaded defaults, and signs them
            # from a pristine .unsigned snapshot. Touching them here would
            # append a second signature outside that scheme.
            bzImage|bzImage32|arm_Image|init.xz|init_32.xz|arm_init.cpio.gz) continue ;;
            # The .unsigned snapshots _resignKernels() keeps, the per-version
            # siblings the backup leaves behind, and this function's own
            # temporary. All are archival copies that nothing ever boots --
            # and signing a .unsigned file would destroy the very property
            # _resignKernels() depends on it for.
            bzImage.*|bzImage32.*|arm_Image.*|init.xz.*|init_32.xz.*|arm_init.cpio.gz.*) continue ;;
            *.unsigned|*.signing) continue ;;
        esac
        # Not a PE/COFF image sbsign could handle -- an initrd, a background,
        # a BIOS blob. Not an error, just not ours to sign.
        sbverify --list "$kf" >/dev/null 2>&1 || continue
        # Already carries our signature. See _secureBootAnchorPem() for why
        # this verifies against the anchor rather than the signing cert.
        sbverify --cert "$anchorpem" "$kf" >/dev/null 2>&1 && continue
        [[ $signed -eq 0 ]] && dots "Signing custom kernels for Secure Boot"
        signed=1
        if sbsign --key "${PKI_sb_codesign_key}" --cert "$certpem" "${addcert[@]}" \
                --output "${kf}.signing" "$kf" >>$error_log 2>&1; then
            chown --reference="$kf" "${kf}.signing" >>$error_log 2>&1
            chmod --reference="$kf" "${kf}.signing" >>$error_log 2>&1
            if mv -f "${kf}.signing" "$kf" >>$error_log 2>&1; then
                names="${names}${bn} "
            else
                failed=1
            fi
        else
            rm -f "${kf}.signing" >>$error_log 2>&1
            failed=1
        fi
    done
    [[ $signed -eq 0 ]] && return 0
    if [[ $failed -ne 0 ]]; then
        echo "Failed"
        echo " * At least one custom kernel could not be signed. See $error_log."
        echo "   Hosts booting it will fail under Secure Boot until this is fixed."
        return 0
    fi
    echo "Done"
    # Named explicitly. These are the admin's own files, found by subtraction
    # rather than from a list anyone wrote down, so the run should say what it
    # decided to modify rather than change them silently.
    echo " * Signed: ${names% }"
}
# The architecture -> boot-file mapping below intentionally mirrors the ISC
# "class" blocks in the ISC branch of configureDHCP(). Keep the two in sync.
# Hoisted into a helper so the live Kea config (configureKeaDHCP) and the
# copy-ready sample (writeKeaSample) can never drift apart.
_keaBaseClasses() {
    # Piped through sed rather than unquoting the heredoc: the block is JSON and
    # keeping it literal is what stops a stray $ or backtick in a future edit
    # being expanded by the shell. The BIOS name varies with --boot-delay
    # (_biosBootFile) and the 64-bit UEFI names with whether the signed chain is
    # staged (_uefiBootFile).
    #
    # The arm64 expression comes first, but the order is not load-bearing: each
    # pattern matches a whole quote-delimited value, so "snponly.efi" cannot also
    # match inside "arm64-efi/snponly.efi".
    #
    # i386-efi is deliberately absent -- no signed 32-bit shim exists -- and so
    # is the Apple BSDP class, which lives in _keaAppleClass and serves Intel
    # Macs over a protocol Secure Boot never enters.
    cat <<'EOFCLS' | sed \
        -e "s|\"boot-file-name\": \"undionly.kkpxe\"|\"boot-file-name\": \"$(_biosBootFile)\"|" \
        -e "s|\"boot-file-name\": \"arm64-efi/snponly.efi\"|\"boot-file-name\": \"$(_uefiBootFile arm64)\"|" \
        -e "s|\"boot-file-name\": \"snponly.efi\"|\"boot-file-name\": \"$(_uefiBootFile x64)\"|"
        {
            "name": "FOG-Legacy-BIOS",
            "test": "substring(option[60].hex,0,20) == 'PXEClient:Arch:00000'",
            "boot-file-name": "undionly.kkpxe"
        },
        {
            "name": "FOG-UEFI-32-2",
            "test": "substring(option[60].hex,0,20) == 'PXEClient:Arch:00002'",
            "boot-file-name": "i386-efi/snponly.efi"
        },
        {
            "name": "FOG-UEFI-32-1",
            "test": "substring(option[60].hex,0,20) == 'PXEClient:Arch:00006'",
            "boot-file-name": "i386-efi/snponly.efi"
        },
        {
            "name": "FOG-UEFI-64-1",
            "test": "substring(option[60].hex,0,20) == 'PXEClient:Arch:00007'",
            "boot-file-name": "snponly.efi"
        },
        {
            "name": "FOG-UEFI-64-2",
            "test": "substring(option[60].hex,0,20) == 'PXEClient:Arch:00008'",
            "boot-file-name": "snponly.efi"
        },
        {
            "name": "FOG-UEFI-64-3",
            "test": "substring(option[60].hex,0,20) == 'PXEClient:Arch:00009'",
            "boot-file-name": "snponly.efi"
        },
        {
            "name": "FOG-UEFI-ARM64",
            "test": "substring(option[60].hex,0,20) == 'PXEClient:Arch:00011'",
            "boot-file-name": "arm64-efi/snponly.efi"
        },
        {
            "name": "FOG-Surface-Pro-4",
            "test": "substring(option[60].hex,0,32) == 'PXEClient:Arch:00007:UNDI:003016'",
            "boot-file-name": "snponly.efi"
        }
EOFCLS
}
# The two ways off the default boot file, emitted commented out.
#
# This used to be the other way round: the base classes served the unsigned
# snponly.efi and this block was the Secure Boot opt-in. The signed chain is now
# the default for every 64-bit UEFI and arm64 client (see _uefiBootFile for why
# that is a superset rather than a conditional), so what is left to document is
# how to move away from it.
#
# Both are DHCP-only changes. Nothing is renamed server-side: the shim picks its
# second stage out of its own filename, so both chains sit side by side in one
# directory and the boot file name alone decides which runs.
_keaBootFileFallbackComment() {
    cat <<'EOFSBC'
#        Two alternatives to the boot file above, if you need them. Uncomment
#        one, add a leading comma to the entry above, and narrow the test to the
#        affected machines -- by subnet, MAC or a client class of your own.
#
#        1. The chain loads but the network never comes up. That is the
#           firmware's own UEFI SNP driver, not anything signed. Use iPXE's
#           built-in NIC drivers instead (arm64: secureboot/arm64-efi/ipxe-shimaa64.efi):
#        {
#            "name": "FOG-UEFI-64-IpxeDrivers",
#            "test": "substring(option[60].hex,0,20) == 'PXEClient:Arch:00007'",
#            "boot-file-name": "secureboot/ipxe-shimx64.efi"
#        }
#
#        2. You want FOG's own builds rather than upstream's signed pair. They
#           are in the TFTP root (arm64: arm64-efi/snponly.efi), signed with this
#           server's key -- so a Secure Boot client will refuse them until that
#           certificate is enrolled on it:
#        {
#            "name": "FOG-UEFI-64-FogBuild",
#            "test": "substring(option[60].hex,0,20) == 'PXEClient:Arch:00007'",
#            "boot-file-name": "snponly.efi"
#        }
EOFSBC
}
_keaAppleClass() {
    cat <<'EOFAPL'
        {
            "name": "FOG-Apple-Intel-Netboot",
            "test": "substring(option[60].text,0,14) == 'AAPLBSDPC/i386'",
            "boot-file-name": "snponly.efi",
            "option-data": [
                { "code": 43, "csv-format": false, "data": "01:01:01:04:02:80:00:07:04:81:00:05:2a:09:0D:81:00:05:2a:08:69:50:58:45:2d:46:4f:47" }
            ]
        }
EOFAPL
}
_keaRunAs() {
    # Print the account "kea-dhcp4 -t" must run as to read $1, or nothing for root.
    #
    # Debian/Ubuntu ship /etc/kea as 0750 owned by the service account (_kea),
    # and their AppArmor profile grants kea-dhcp4 "/etc/kea/** r" while
    # deliberately withholding cap_dac_read_search and cap_dac_override. Root is
    # neither the directory's owner nor in its group, so a root-run validation
    # cannot even traverse the directory: Kea reports "Unable to open file
    # <path>" for a file that plainly exists, and the install aborts (#1039).
    # The daemon itself was never affected because systemd runs it as _kea.
    #
    # Validating as the directory's owner needs no DAC bypass at all, so it
    # succeeds with the AppArmor profile intact. Do NOT "fix" this by putting the
    # profile into complain mode or deleting it -- that disables a protection the
    # distro shipped on purpose, on a box the admin did not ask us to weaken.
    # Where /etc/kea is root-owned (RedHat, Arch, Alpine) this returns nothing
    # and the validation runs as root exactly as before.
    local owner
    owner=$(stat -c '%U' "$(dirname "$1")" 2>/dev/null)
    [[ -z $owner || $owner == root || $owner == UNKNOWN ]] && return 0
    id -u "$owner" >/dev/null 2>&1 || return 0
    printf '%s' "$owner"
}
_keaValidate() {
    # Syntax-check $1, dropping to the config directory's owner when root cannot
    # read it (see _keaRunAs). Returns kea-dhcp4's exit status.
    local runas
    runas=$(_keaRunAs "$1")
    if [[ -z $runas ]]; then
        kea-dhcp4 -t "$1" >>$error_log 2>&1
    elif command -v runuser >/dev/null 2>&1; then
        runuser -u "$runas" -- kea-dhcp4 -t "$1" >>$error_log 2>&1
    else
        su -s /bin/sh -c "kea-dhcp4 -t '$1'" "$runas" >>$error_log 2>&1
    fi
}
_writeKeaConfig() {
    # $1 = target file, $2 = client-classes block. Reads ${NET_interface}, ${NET_fog_server_ip},
    # $network, $cidr, ${DHCP_range_start}, ${DHCP_range_end} and $optdata from the caller's scope.
    cat > "$1" <<EOFKEA
{
    "Dhcp4": {
        "interfaces-config": { "interfaces": [ "${NET_interface}" ] },
        "lease-database": { "type": "memfile", "lfc-interval": 3600 },
        "valid-lifetime": 21600,
        "max-valid-lifetime": 43200,
        "next-server": "${NET_fog_server_ip}",
        "option-data": [
            { "name": "tftp-server-name", "data": "${NET_fog_server_ip}" }
        ],
        "subnet4": [
            {
                "id": 1,
                "subnet": "$network/$cidr",
                "pools": [ { "pool": "${DHCP_range_start} - ${DHCP_range_end}" } ],
                "option-data": [
$optdata
                ]
            }
        ],
        "client-classes": [
$2
        ]
    }
}
EOFKEA
    # The service account has to be able to read this, and a hardened root umask
    # (027/077) would otherwise leave it unreadable to anyone but root -- which
    # breaks the daemon, not just the syntax check. 0644 is the mode the distro
    # packages ship this file with; the generated config holds no credentials
    # (the lease database is memfile).
    chmod 0644 "$1" >>$error_log 2>&1
}
configureKeaDHCP() {
    local cidr=$(mask2cidr ${NET_subnet_mask})
    local target="$dhcpconfig"
    local tmp="${target}.fogtmp"
    [[ -d $(dirname "$target") ]] || mkdir -p "$(dirname "$target")" >>$error_log 2>&1
    [[ -f $target ]] && mv -fv "$target" "${target}.${timestamp}" >>$error_log 2>&1
    local optdata="                { \"name\": \"subnet-mask\", \"data\": \"${NET_subnet_mask}\" }"
    [[ $(validip ${DHCP_router}) -eq 0 ]] && optdata="${optdata},
                { \"name\": \"routers\", \"data\": \"${DHCP_router}\" }"
    [[ $(validip ${DHCP_dns_server_ip}) -eq 0 ]] && optdata="${optdata},
                { \"name\": \"domain-name-servers\", \"data\": \"${DHCP_dns_server_ip}\" }"
    local baseclasses
    baseclasses=$(_keaBaseClasses)
    local appleclass
    appleclass=$(_keaAppleClass)
    # Tier 1: base classes must validate or we refuse to start a broken server.
    _writeKeaConfig "$target" "$baseclasses"
    if [[ ! -s $target ]]; then
        echo "Failed"
        echo "Kea base configuration could not be written to $target (verify $(dirname "$target") is a writable directory); see $error_log"
        return 1
    fi
    if command -v kea-dhcp4 >/dev/null 2>&1; then
        if ! _keaValidate "$target"; then
            echo "Failed"
            echo "Kea base configuration failed validation (kea-dhcp4 -t); see $error_log"
            # "Unable to open file" against a file we just wrote and can stat is
            # never a syntax error -- it is a mandatory access control denial
            # (AppArmor on Debian/Ubuntu, SELinux on RedHat) stopping kea-dhcp4
            # from reading it. Say so, because the generic message sends people
            # hunting for a JSON typo that isn't there (#1039).
            if [[ -s $target ]] && tail -n 20 "$error_log" 2>/dev/null | grep -q 'Unable to open file'; then
                echo ""
                echo " * $target exists and is readable, so this is not a syntax error."
                echo "   Something is denying kea-dhcp4 access to it. Check:"
                echo "     dmesg | grep -i 'apparmor.*kea'      (Debian/Ubuntu)"
                echo "     ausearch -m avc -c kea-dhcp4         (RedHat/Rocky)"
                echo "   Please report this with that output rather than disabling AppArmor."
            fi
            return 1
        fi
        # Tier 2: best-effort Apple BSDP; drop if Kea rejects it.
        _writeKeaConfig "$tmp" "${baseclasses},
${appleclass}"
        if _keaValidate "$tmp"; then
            mv -f "$tmp" "$target"
        else
            rm -f "$tmp"
            echo ""
            echo " * Note: Apple Intel netboot (BSDP) is not supported under Kea and was skipped"
        fi
    else
        echo " * Warning: kea-dhcp4 not found; wrote config without validation" >>$error_log 2>&1
    fi
    diffconfig "$target"
    return 0
}
writeKeaSample() {
    # For admins who run a dedicated/external Kea DHCP server (FOG is NOT hosting
    # DHCP): drop a ready-to-copy kea-dhcp4.conf next to the FOG web root so they
    # have a working starting point instead of hand-writing one. Not activated and
    # no service is touched here -- it is a reference file for their DHCP server.
    local target="${webdirdest%/}/kea-dhcp4.conf.fog-sample"
    [[ -z $webdirdest ]] && target="/etc/kea/kea-dhcp4.conf.fog-sample"
    [[ -d $(dirname "$target") ]] || return 0
    local sampleip
    sampleip=$(ip -4 -o addr show ${NET_interface} | awk -F'([ /])+' '/global/ {print $4}')
    [[ -z $sampleip ]] && sampleip="${NET_fog_server_ip}"
    [[ -z ${NET_subnet_mask} ]] && NET_subnet_mask=$(cidr2mask $(getCidr ${NET_interface}))
    local network=$(mask2network $sampleip ${NET_subnet_mask})
    local cidr=$(mask2cidr ${NET_subnet_mask})
    local DHCP_range_start=$(addToAddress $network 10)
    # GH-667: an interface with no brd flag, or any failure inside these
    # helpers, used to leave endrange holding an error string that went
    # straight into the generated config. Fall back to the broadcast computed
    # from the network and mask we already have.
    local broadcast=$(interface2broadcast ${NET_interface})
    [[ $(validip $broadcast) -ne 0 ]] && broadcast=$(mask2broadcast $network ${NET_subnet_mask})
    local DHCP_range_end=$(subtract1fromAddress $broadcast)
    [[ $(validip ${DHCP_range_end}) -ne 0 ]] && DHCP_range_end=$(subtract1fromAddress $(mask2broadcast $network ${NET_subnet_mask}))
    local optdata="                { \"name\": \"subnet-mask\", \"data\": \"${NET_subnet_mask}\" }"
    [[ $(validip ${DHCP_router}) -eq 0 ]] && optdata="${optdata},
                { \"name\": \"routers\", \"data\": \"${DHCP_router}\" }"
    [[ $(validip ${DHCP_dns_server_ip}) -eq 0 ]] && optdata="${optdata},
                { \"name\": \"domain-name-servers\", \"data\": \"${DHCP_dns_server_ip}\" }"
    # Full reference: base classes + Apple BSDP, plus the commented-out
    # boot-file fallbacks. The admin can trim as needed.
    _writeKeaConfig "$target" "$(_keaBaseClasses),
$(_keaAppleClass)
$(_keaBootFileFallbackComment)"
    if [[ -s $target ]]; then
        echo
        echo " * A sample Kea DHCP config for a dedicated/external DHCP server was"
        echo " | written to: $target"
        echo " | Copy it to your DHCP server as /etc/kea/kea-dhcp4.conf and adjust the"
        echo " | subnet/pool/routers/domain-name-servers to match that network."
        echo " | next-server is already set to this FOG server (${NET_fog_server_ip})."
    fi
}
configureDHCP() {
    if [[ ${DHCP_enabled} == yes && ${DHCP_engine} == kea ]]; then
        dots "Setting up and starting DHCP Server (Kea)"
    else
        case $linuxReleaseName_lower in
            *debian*)
                if [[ ${DHCP_enabled} == yes ]]; then
                    dots "Setting up and starting DHCP Server (incl. debian 9 fix)"
                    sed -i.fog "s/INTERFACESv4=\"\"/INTERFACESv4=\"${NET_interface}\"/g" /etc/default/isc-dhcp-server
                else
                    dots "Setting up and starting DHCP Server"
                fi
                ;;
            *)
                dots "Setting up and starting DHCP Server"
                ;;
        esac
    fi
    case ${DHCP_enabled} in
        yes)
            # GH-954: one line per address, so a second address on the NIC
            # made this multi-line and every consumer below it wrong.
            serverip=$(ip -4 -o addr show ${NET_interface} | awk -F'([ /])+' '/global/ {print $4}' | head -1)
            [[ -z $serverip ]] && serverip=$(/sbin/ifconfig ${NET_interface} | grep -oE 'inet[:]? addr[:]?([0-9]{1,3}\.){3}[0-9]{1,3}' | awk -F'(inet[:]? ?addr[:]?)' '{print $2}')
            [[ -z ${NET_subnet_mask} ]] && NET_subnet_mask=$(cidr2mask $(getCidr ${NET_interface}))
            network=$(mask2network $serverip ${NET_subnet_mask})
            [[ -z ${DHCP_range_start} ]] && DHCP_range_start=$(addToAddress $network 10)
            # GH-667: same guard as writeKeaSample -- never let a helper's
            # failure become the value that lands in dhcpd.conf.
            if [[ -z ${DHCP_range_end} ]]; then
                broadcast=$(interface2broadcast ${NET_interface})
                [[ $(validip $broadcast) -ne 0 ]] && broadcast=$(mask2broadcast $network ${NET_subnet_mask})
                DHCP_range_end=$(subtract1fromAddress $broadcast)
                [[ $(validip ${DHCP_range_end}) -ne 0 ]] && DHCP_range_end=$(subtract1fromAddress $(mask2broadcast $network ${NET_subnet_mask}))
            fi
            [[ ! $(validip ${DHCP_router}) -eq 0 ]] && DHCP_router=$(echo ${DHCP_router} | grep -oE "\b([0-9]{1,3}\.){3}[0-9]{1,3}\b")
            [[ ! $(validip ${DHCP_dns_server_ip}) -eq 0 ]] && DHCP_dns_server_ip=$(echo ${DHCP_dns_server_ip} | grep -oE "\b([0-9]{1,3}\.){3}[0-9]{1,3}\b")
            if [[ ${DHCP_engine} == kea ]]; then
                if ! configureKeaDHCP; then
                    # Honor -X/--exitFail: a Kea config failure must not abort
                    # the whole installer, or later steps (TFTP/PXE) never run.
                    [[ -z $exitFail ]] && exit 1
                    return
                fi
            else
            [[ -f $dhcpconfig ]] && dhcptouse=$dhcpconfig
            [[ -f $dhcpconfigother ]] && dhcptouse=$dhcpconfigother
            if [[ -z $dhcptouse || ! -f $dhcptouse ]]; then
                echo "Failed"
                echo "Could not find dhcp config file"
                # Honor -X/--exitFail: same as the Kea branch, don't abort the
                # whole installer or later steps (TFTP/PXE) never run.
                [[ -z $exitFail ]] && exit 1
                return
            fi
            mv -fv "${dhcptouse}" "${dhcptouse}.${timestamp}" >>$error_log 2>&1
            echo "# DHCP Server Configuration file\n#see /usr/share/doc/dhcp*/dhcpd.conf.sample" > $dhcptouse
            echo "# This file was created by FOG" >> "$dhcptouse"
            echo "#Definition of PXE-specific options" >> "$dhcptouse"
            echo "# Code 1: Multicast IP Address of bootfile" >> "$dhcptouse"
            echo "# Code 2: UDP Port that client should monitor for MTFTP Responses" >> "$dhcptouse"
            echo "# Code 3: UDP Port that MTFTP servers are using to listen for MTFTP requests" >> "$dhcptouse"
            echo "# Code 4: Number of seconds a client must listen for activity before trying" >> "$dhcptouse"
            echo "#         to start a new MTFTP transfer" >> "$dhcptouse"
            echo "# Code 5: Number of seconds a client must listen before trying to restart" >> "$dhcptouse"
            echo "#         a MTFTP transfer" >> "$dhcptouse"
            echo "option space PXE;" >> "$dhcptouse"
            echo "option PXE.mtftp-ip code 1 = ip-address;" >> "$dhcptouse"
            echo "option PXE.mtftp-cport code 2 = unsigned integer 16;" >> "$dhcptouse"
            echo "option PXE.mtftp-sport code 3 = unsigned integer 16;" >> "$dhcptouse"
            echo "option PXE.mtftp-tmout code 4 = unsigned integer 8;" >> "$dhcptouse"
            echo "option PXE.mtftp-delay code 5 = unsigned integer 8;" >> "$dhcptouse"
            echo "option arch code 93 = unsigned integer 16;" >> "$dhcptouse"
            echo "use-host-decl-names on;" >> "$dhcptouse"
            echo "ddns-update-style interim;" >> "$dhcptouse"
            echo "ignore client-updates;" >> "$dhcptouse"
            echo "# Specify subnet of ether device you do NOT want service." >> "$dhcptouse"
            echo "# For systems with two or more ethernet devices." >> "$dhcptouse"
            echo "# subnet 136.165.0.0 netmask 255.255.0.0 {}" >> "$dhcptouse"
            echo "subnet $network netmask ${NET_subnet_mask}{" >> "$dhcptouse"
            echo "    option subnet-mask ${NET_subnet_mask};" >> "$dhcptouse"
            echo "    range dynamic-bootp ${DHCP_range_start} ${DHCP_range_end};" >> "$dhcptouse"
            echo "    default-lease-time 21600;" >> "$dhcptouse"
            echo "    max-lease-time 43200;" >> "$dhcptouse"
            [[ ! $(validip ${DHCP_router}) -eq 0 ]] && DHCP_router=$(echo ${DHCP_router} | grep -oE "\b([0-9]{1,3}\.){3}[0-9]{1,3}\b")
            [[ ! $(validip ${DHCP_dns_server_ip}) -eq 0 ]] && DHCP_dns_server_ip=$(echo ${DHCP_dns_server_ip} | grep -oE "\b([0-9]{1,3}\.){3}[0-9]{1,3}\b")
            [[ $(validip ${DHCP_router}) -eq 0 ]] && echo "    option routers ${DHCP_router};" >> "$dhcptouse" || echo "    #option routers 0.0.0.0" >> "$dhcptouse"
            [[ $(validip ${DHCP_dns_server_ip}) -eq 0 ]] && echo "    option domain-name-servers ${DHCP_dns_server_ip};" >> "$dhcptouse" || echo "    #option domain-name-servers 0.0.0.0" >> "$dhcptouse"
            echo "    next-server ${NET_fog_server_ip};" >> "$dhcptouse"
            echo "}" >> "$dhcptouse"
            echo "class \"Legacy\" {" >> "$dhcptouse"
            echo "    match if substring(option vendor-class-identifier, 0, 20) = \"PXEClient:Arch:00000\";" >> "$dhcptouse"
            echo "    filename \"$(_biosBootFile)\";" >> "$dhcptouse"
            echo "}" >> "$dhcptouse"
            echo "class \"UEFI-32-2\" {" >> "$dhcptouse"
            echo "    match if substring(option vendor-class-identifier, 0, 20) = \"PXEClient:Arch:00002\";" >> "$dhcptouse"
            echo "    filename \"i386-efi/snponly.efi\";" >> "$dhcptouse"
            echo "}" >> "$dhcptouse"
            echo "class \"UEFI-32-1\" {" >> "$dhcptouse"
            echo "    match if substring(option vendor-class-identifier, 0, 20) = \"PXEClient:Arch:00006\";" >> "$dhcptouse"
            echo "    filename \"i386-efi/snponly.efi\";" >> "$dhcptouse"
            echo "}" >> "$dhcptouse"
            echo "class \"UEFI-64-1\" {" >> "$dhcptouse"
            echo "    match if substring(option vendor-class-identifier, 0, 20) = \"PXEClient:Arch:00007\";" >> "$dhcptouse"
            echo "    filename \"$(_uefiBootFile x64)\";" >> "$dhcptouse"
            echo "}" >> "$dhcptouse"
            echo "class \"UEFI-64-2\" {" >> "$dhcptouse"
            echo "    match if substring(option vendor-class-identifier, 0, 20) = \"PXEClient:Arch:00008\";" >> "$dhcptouse"
            echo "    filename \"$(_uefiBootFile x64)\";" >> "$dhcptouse"
            echo "}" >> "$dhcptouse"
            echo "class \"UEFI-64-3\" {" >> "$dhcptouse"
            echo "    match if substring(option vendor-class-identifier, 0, 20) = \"PXEClient:Arch:00009\";" >> "$dhcptouse"
            echo "    filename \"$(_uefiBootFile x64)\";" >> "$dhcptouse"
            echo "}" >> "$dhcptouse"
            echo "class \"UEFI-ARM64\" {" >> "$dhcptouse"
            echo "    match if substring(option vendor-class-identifier, 0, 20) = \"PXEClient:Arch:00011\";" >> "$dhcptouse"
            echo "    filename \"$(_uefiBootFile arm64)\";" >> "$dhcptouse"
            echo "}" >> "$dhcptouse"
            echo "class \"SURFACE-PRO-4\" {" >> "$dhcptouse"
            echo "    match if substring(option vendor-class-identifier, 0, 32) = \"PXEClient:Arch:00007:UNDI:003016\";" >> "$dhcptouse"
            echo "    filename \"$(_uefiBootFile x64)\";" >> "$dhcptouse"
            echo "}" >> "$dhcptouse"
            echo "class \"Apple-Intel-Netboot\" {" >> "$dhcptouse"
            echo "    match if substring(option vendor-class-identifier, 0, 14) = \"AAPLBSDPC/i386\";" >> "$dhcptouse"
            echo "    option dhcp-parameter-request-list 1,3,17,43,60;" >> "$dhcptouse"
            echo "    if (option dhcp-message-type = 8) {" >> "$dhcptouse"
            echo "        option vendor-class-identifier \"AAPLBSDPC\";" >> "$dhcptouse"
            echo "        if (substring(option vendor-encapsulated-options, 0, 3) = 01:01:01) {" >> "$dhcptouse"
            echo "            # BSDP List" >> "$dhcptouse"
            echo "            option vendor-encapsulated-options 01:01:01:04:02:80:00:07:04:81:00:05:2a:09:0D:81:00:05:2a:08:69:50:58:45:2d:46:4f:47;" >> "$dhcptouse"
            echo "            filename \"snponly.efi\";" >> "$dhcptouse"
            echo "        }" >> "$dhcptouse"
            echo "    }" >> "$dhcptouse"
            echo "}" >> "$dhcptouse"
            # The two ways off the default boot file, commented out. Mirrors
            # _keaBootFileFallbackComment() -- see the note on _uefiBootFile()
            # for why the signed chain is the default for every 64-bit UEFI
            # client rather than a per-machine opt-in, and why the boot file
            # names the shim rather than the loader.
            echo "# Two alternatives to the UEFI boot file above, if you need them." >> "$dhcptouse"
            echo "# Uncomment one and narrow the match to the affected machines. Both are" >> "$dhcptouse"
            echo "# DHCP-only changes -- nothing is renamed on this server." >> "$dhcptouse"
            echo "#" >> "$dhcptouse"
            echo "# 1. The chain loads but the network never comes up: that is the firmware's" >> "$dhcptouse"
            echo "#    own UEFI SNP driver, so use iPXE's built-in drivers instead." >> "$dhcptouse"
            echo "#    On arm64, secureboot/arm64-efi/ipxe-shimaa64.efi." >> "$dhcptouse"
            echo "#class \"FOG-UEFI-64-IpxeDrivers\" {" >> "$dhcptouse"
            echo "#    match if substring(option vendor-class-identifier, 0, 20) = \"PXEClient:Arch:00007\";" >> "$dhcptouse"
            echo "#    filename \"secureboot/ipxe-shimx64.efi\";" >> "$dhcptouse"
            echo "#}" >> "$dhcptouse"
            echo "#" >> "$dhcptouse"
            echo "# 2. You want FOG's own builds rather than upstream's signed pair. They" >> "$dhcptouse"
            echo "#    are in the TFTP root; on arm64, arm64-efi/snponly.efi. They carry" >> "$dhcptouse"
            echo "#    this server's own signature, so a Secure Boot client refuses them" >> "$dhcptouse"
            echo "#    until that certificate is enrolled on it." >> "$dhcptouse"
            echo "#class \"FOG-UEFI-64-FogBuild\" {" >> "$dhcptouse"
            echo "#    match if substring(option vendor-class-identifier, 0, 20) = \"PXEClient:Arch:00007\";" >> "$dhcptouse"
            echo "#    filename \"snponly.efi\";" >> "$dhcptouse"
            echo "#}" >> "$dhcptouse"
            diffconfig "${dhcptouse}"
            # Non-fatal syntax check; ISC has historically started without one.
            if command -v dhcpd >/dev/null 2>&1; then
                dhcpd -t -cf "$dhcptouse" >>$error_log 2>&1 || echo " * Warning: dhcpd -t reported issues with $dhcptouse (see $error_log)" >>$error_log 2>&1
            fi
            fi
            # When FOG owns DHCP, make sure the other engine is not also bound to
            # port 67 (covers an admin switching engines on an existing box).
            otherdhcp=""
            [[ ${DHCP_engine} == kea ]] && otherdhcp="$iscservice" || otherdhcp="$keaservice"
            if [[ -n $otherdhcp && $systemctl == yes ]]; then
                systemctl is-active --quiet $otherdhcp && systemctl stop $otherdhcp >>$error_log 2>&1
                systemctl is-enabled --quiet $otherdhcp && systemctl disable $otherdhcp >>$error_log 2>&1
            fi
            case $systemctl in
                yes)
                    systemctl is-enabled --quiet ${DHCP_service_name} && true || systemctl enable ${DHCP_service_name} >>$error_log 2>&1
                    systemctl is-active --quiet ${DHCP_service_name} && systemctl stop ${DHCP_service_name} >>$error_log 2>&1 || false
                    systemctl is-active --quiet ${DHCP_service_name} && true || systemctl start ${DHCP_service_name} >>$error_log 2>&1
                    systemctl status ${DHCP_service_name} >>$error_log 2>&1
                    ;;
                *)
                    case ${FOG_os_id} in
                        1)
                            chkconfig ${DHCP_service_name} on >>$error_log 2>&1
                            service ${DHCP_service_name} stop >>$error_log 2>&1
                            service ${DHCP_service_name} start >>$error_log 2>&1
                            service ${DHCP_service_name} status >>$error_log 2>&1
                            ;;
                        2)
                            sysv-rc-conf ${DHCP_service_name} on >>$error_log 2>&1
                            /etc/init.d/${DHCP_service_name} stop >>$error_log 2>&1
                            /etc/init.d/${DHCP_service_name} start >>$error_log 2>&1
                            ;;
                        3)
                            # Alpine is Kea-only (see lib/alpine/config.sh), so
                            # ${DHCP_service_name} here is kea-dhcp4, not the ISC daemon this
                            # case was written around. With no arm the step did
                            # nothing and still reported OK -- an operator who
                            # asked FOG to own DHCP got a server that never
                            # answered a PXE client. See #863.
                            rc-update add ${DHCP_service_name} default >>$error_log 2>&1
                            rc-service ${DHCP_service_name} stop >>$error_log 2>&1
                            rc-service ${DHCP_service_name} start >>$error_log 2>&1
                            rc-service ${DHCP_service_name} status >>$error_log 2>&1
                            ;;
                    esac
                    ;;
            esac
            errorStat $?
            ;;
        *)
            echo "Skipped"
            writeKeaSample
            ;;
    esac
}
vercomp() {
    [[ $1 == $2 ]] && return 0
    local IFS=.
    local i ver1=($1) ver2=($2)
    for ((i=${#ver1[@]}; i<${#ver2}; i++)); do
        ver1[i]=0
    done
    for ((i=0; i<${#ver1[@]}; i++)); do
        [[ -z ${ver2[i]} ]] && ver2[i]=0
        if ((10#${ver1[i]} > 10#${ver2[i]})); then
            return 1
        fi
        if ((10#${ver1[i]} < 10#${ver2[i]})); then
            return 2
        fi
    done
    return 0
}
languagemogen() {
    local languages="$1"
    local langpath="$2"
    local IFS=$'\n'
    local lang=''
    for lang in ${languages[@]}; do
        [[ ! -d "${langpath}/${lang}.UTF-8" ]] && continue
        msgfmt -o \
            "${langpath}/${lang}.UTF-8/LC_MESSAGES/messages.mo" \
            "${langpath}/${lang}.UTF-8/LC_MESSAGES/messages.po" \
            >>$error_log 2>&1
    done
}
generatePassword() {
    local length="$1"
    [[ $length -ge 12 && $length -le 128 ]] || length=20

    while [[ ${#genpassword} -lt $((length-1)) || -z $special ]]; do
        newchar=$(head -c1 /dev/urandom | tr -dc '0-9a-zA-Z!#$%&()*+,-./:;<=>?@[]^_{|}~')
        if [[ -n $(echo $newchar | tr -dc '!#$%&()*+,-./:;<=>?@[]^_{|}~') ]]; then
            special=${newchar}
        elif [[ ${#genpassword} -lt $((length-1)) ]]; then
            genpassword=${genpassword}${newchar}
        fi
    done
    # 9$(date +%N) seems weird but it's important because date may return
    # a leading 0 causing modulo to fail on reading it as octal number
    position=$(( 9$(date +%N) % $length ))
    # inject the special character at a random position
    echo ${genpassword::($position)}$special${genpassword:($position)}
}
checkPasswordChars() {
    checkpass="$(echo "$1" | tr -d '0-9a-zA-Z!#$%&()*+,-./:;<=>?@[]^_{|}~')"
    if [[ -n "$checkpass" ]]; then
        echo "Failed"
        echo ""
        echo "# The fog system account password includes characters we cannot properly"
        echo "# handle. Please remove the following character(s) in line SVC_password= of"
        echo "# your .fogsettings file before re-running the installer: $checkpass"
        echo ""
        exit 1
    fi
}
diffconfig() {
    local conffile="$1"
    [[ ! -f "${conffile}.${timestamp}" ]] && return 0
    diff -q "${conffile}" "${conffile}.${timestamp}" >>$error_log 2>&1
    if [[ $? -eq 0 ]]; then
        rm -f "${conffile}.${timestamp}" >>$error_log 2>&1
    else
        backupconfig="${backupconfig} ${conffile}"
    fi
}
setupFogReporting() {
    [[ ${FOG_send_reports} != yes ]] && return
    local reportingdir="$fogprogramdir/reporting"
    local rreports="$reportingdir/report.sh"
    dots "Setting up FOG External Reporting"
    # Make sure required directories exist
    mkdir -p $reportingdir >>$error_log 2>&1
    mkdir -p /var/log/fog >>$error_log 2>&1
    # If the report settings file does not exist, create it.
    if [[ ! -f $reportingdir/settings ]]; then
        /usr/bin/awk -f $workingdir/../utils/reporting/reportingcronrandom.awk >> $reportingdir/settings
    fi
    # Pull in our reporting settings
    source $reportingdir/settings >>$error_log 2>&1

    # Alpine has no /etc/cron.d at all -- busybox crond reads per-user tables
    # under /etc/crontabs -- so this wrote nothing, printed "No such file or
    # directory" onto the install output, and still reported Done. A per-user
    # table also has no user column, which is why the line is built twice
    # rather than the path just being swapped. See #863.
    if [[ ${FOG_os_id} -eq 3 ]]; then
        crondfile="/etc/crontabs/${user_to_run_as}"
        mkdir -p /etc/crontabs >>$error_log 2>&1
        mv -fv "${crondfile}" "${crondfile}.${timestamp}" >>$error_log 2>&1
        # APPEND to that file, never replace it. On the other distros the
        # cron.d file below is FOG's own and rewriting it whole is correct;
        # /etc/crontabs/root is the host's, and Alpine ships it carrying
        # busybox's run-parts entries for /etc/periodic/*. Writing it whole
        # deletes every scheduled job the machine had.
        #
        # A previous FOG block is stripped first so re-running does not stack
        # copies. spliceManagedBlock() is deliberately not reused here: its
        # "neither marker present" branch replaces the file whole, which is
        # right for a vhost FOG owns and wrong for this one.
        if [[ -f "${crondfile}.${timestamp}" ]]; then
            awk -v b="$FOG_MANAGED_BEGIN" -v e="$FOG_MANAGED_END" '
                $0 == b { skip = 1; next }
                $0 == e { skip = 0; next }
                !skip
            ' "${crondfile}.${timestamp}" > "${crondfile}" 2>>$error_log
        else
            : > "${crondfile}" 2>>$error_log
        fi
        # SHELL and PATH sit INSIDE the block, after everything the host had:
        # a crontab applies an assignment to the lines that follow it, so
        # putting them at the top would quietly re-point the host's own jobs.
        {
            echo "$FOG_MANAGED_BEGIN"
            echo "SHELL=/bin/sh"
            echo "PATH=${PATH}"
            echo "${minute_of_hour} ${hour_of_day} * * ${day_of_week} ${rreports} >> ${reporting_log} 2>&1"
            echo "$FOG_MANAGED_END"
        } >> "${crondfile}"
        # And something has to read it. Alpine installs busybox crond but does
        # not enable it, so without this the entry above is inert -- the same
        # silently-does-nothing shape the rest of #863 is about.
        rc-update add crond default >>$error_log 2>&1
        rc-service crond status >/dev/null 2>&1 || rc-service crond start >>$error_log 2>&1
    else
        crondfile="/etc/cron.d/fog_reporting"
        mv -fv "${crondfile}" "${crondfile}.${timestamp}" >>$error_log 2>&1
        # Build the cron.d file
        cat > ${crondfile} <<END_OF_REPORTING_FILE
SHELL=/bin/bash
PATH=${PATH}
${minute_of_hour} ${hour_of_day} * * ${day_of_week} ${user_to_run_as} ${rreports} >> ${reporting_log} 2>&1
END_OF_REPORTING_FILE
    fi
    diffconfig "${crondfile}"
    # If the reporting script exists, create a backup of it.
    mv -fv "${rreports}" "${rreports}.${timestamp}" >>$error_log 2>&1
    # Copy the new reporting script
    cp $workingdir/../utils/reporting/report.sh ${rreports} >>$error_log 2>&1
    # List change into backupconfig variable
    diffconfig "${rreports}"
    chmod +x ${rreports} >>$error_log 2>&1
    echo "Done"
}
