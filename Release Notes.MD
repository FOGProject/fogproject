FOG Project Release Notes
=========================

* [FOG 1.5.10](#fog-1510)
* [FOG 1.5.10 revisions](##fog-1510-revisions)
* [FOG 1.5.9](#fog-159)
* [FOG 1.5.8](#fog-158)

# FOG 1.5.10

## FOG 1.5.10 revisions

### FOG 1.5.10.48

https://github.com/FOGProject/fogproject/releases/tag/1.5.10.48

### FOG 1.5.10.41

https://github.com/FOGProject/fogproject/releases/tag/1.5.10.41

## FOG 1.5.10 overview

FOG 1.5.10 officially released: https://news.fogproject.org/fog-1-5-10-officially-released/

Below is a summary of the many fixes and improvements introduced over the last 2.5 years since the last official release.
We have squashed bugs, further hardened the security of the system, and introduced new features, improvements, and support for new operating systems.

Some highlights include:

* PHP 8 support
* Windows 11 support
* More customizable printer configurations
* More security checks to prevent users from accessing administrative functions accidentally or maliciously
* Added and improved pxe boot menu options
* Support for recent Linux OS's and an improved installer experience
* Improved internationalization support
* And so much more!

Thank you to all that have reported issues and feature requests in the forums and github requests and to all our developers, contributors, and users. 
We couldn't do this without you!

This version was re-released (re-tagged) not long after the initial release to patch issues with the location plugin that would cause trouble when updating from an earlier version.

## FOG Web UI and Services

To view the full list of commits with the code changes introducing these fixes and features, see the [dev branch commit history](https://github.com/FOGProject/fogproject/commits/dev-branch)

### Security Fixes

* Hide function parameters for ldap connection call to not print out username and password information to log files (#445).
* Add authentication check in getfiles to prevent file enumeration, thanks @cla
* Add security checks to default pxe boot options like delete, change windows key, and join multicast domain, thanks @skyrider223
* Do not prepend filename to kernel information, thanks @wayneworkman
* Remove replication of ssl files and directories
* Update select JS and CSS library to latest version 4.0.13
* Prevent leak of AD password when applying default AD infos in host
* Remove header not posing much of security as mentioned in #473, thanks @djohle
* Add query for >=MariaDB 10.4 mysql.global_privs table
* Check download URL and destination filename to prevent from malicious use, thanks @sml
* Added web login logging
* Add changes to firewall and SELinux checks
* Added a defined [security policy](https://github.com/FOGProject/fogproject/blob/8ce090ba82ce22208daa0a20bc5018364e20949a/SECURITY.md)

### Improvements

* PHP 8 Support
* Allow adjustment of timeout between all partitions to be set, thanks @Magelfik
* Windows 11 compatibility added to notes and docs
* Updated Readme for various things
* Updated contribution guidelines
* Update udpcast to version 20200328
* Improved client downloader in web gui
* Repair host listing icons for Linux systems
* Try to find information of machines currently reporting empty OS name
* Allow for replication of sub directories, e.g. /images/drivers/video 
* Add kernel parameter for udpcast maximum wait time
* Improve groupmanager.log output a fair bit
* Ignore parent directory entry in file size calculation
* Improved logging of various systems
* Add keymap (keyboard layout) setting to our iPXE binaries
* Update iPXE to the latest pull
* Add REBOOT menu to Host Bios Exit Type and Host EFI Exit Type
* Add kernel information to reporting script
* Leave 7 percent space when shrinking partitions with resizable image
* Allow hostname regex for printers as well as IP addresses
* Add ability to specify additional TFTP server command line options
* Switch default database engine from MyISAM to InnoDB
* Point FOG web UI kernel update page to the kernel repo on github.
* Switch to snponly.efi for UEFI systems as default
* Save resources by only enabling systemd tftp.socket
* Move from xinetd to systemd for TFTP connection handling on redhat
* Change internet connection check to better suit corporate networks
* Add hint on image data files not being deleted through multi delete
* Updated Internationalization support (multiple languages)
* Installer support for more operating systems
* Attempt https and http for WakeOnLAN
* Separate the scheduled tasks from the Power Management tasks
* More verbose Logging in various locations
* Installer pre-req check improvements and other general installer improvements
* Improved db table sorting
* Improved backup functions in installer
* Added image start, end, and duration to email notifications for imaging 


### Bug Fixes

* Fix problem when updating to 1.5.10 with location plugin enabled (#561)
* Add missing protocol in the URL on snapin download with location plugin (#562)
* Fix location based snapin download (#371)
* Fix group cron style task scheduling (#544)
* Fix incorrect array index name in location plugin code.
* Do not install sysv-rc-conf on Debian or Ubuntu, thanks @stevemoca
* Allow -U parameter to work as expected in installer, thanks to @lukebarone 
* Prevent from installing package dhcp* when not selected by installer
* Fixed behavioral issues of replication services
* Prevent from saving the whole snapin object when only associations need saving
* Silence warnings when upgrading a FOG install with services being enabled
* Remove inconsistent excess kernel reporting information, thanks @wayneworkman
* Make MulticastTask getAllMulticastTasks() function non static as call 
* Fixes for several parts of the iPXE boot menu like delete and approve hosts
* Repair installer for RedHat, Alma, Rocky and CentOS
* Fix snapin deletion causing an undefined variable exception with PHP 8
* Fix display of name for capone plugin within FOG configuration page
* Fix issue where filename got corrupted when uploading a new snapin file
* Fix bug in iPXE embedded scripts which prevents drive boot method EXIT 
* Prevent AD fields from being overwritten with default settings even if fields are not empty 
* Fix typo in snapinjob call that was preventing snapins to run
* Remove bootfilename from installer and .fogsettings as it's not in use 
* Silence the output in case xinetd is not installed at all 
* Set image size on server to zero when directory is not found
* Added missing openssl package dependency
* Fix installer password character check
* Correct option domain-name-servers on dhcpd.conf generation when empty
* Fix host edit page load issue when snapin history has entries set to 0000-00-00 00:00:00 timestamp

## fog-client

fog-client 0.13.0 is included with FOG 1.5.10

To view the full list of commits with the code changes introducing these fixes and features, see 

* [fog client commit history](https://github.com/FOGProject/fog-client/commits/master)
* [fog zazzles commit history](https://github.com/FOGProject/zazzles/commits/master)

### Security Fixes

* Add a break to logging and authentication attempts when the fog client fails to authenticate after 5 attempts, thanks to @Iri5s
* Update external library SharpZipLib to v1.3.3 to fix security issues
* Update external library Newtonsoft.Json to v13.0.1
* Allow using certificate files from local filesystem
* Update timestamp server list and use new signing cert
* Run authenticity check in mac and linux os clients
* Add check for TLS versions, enable secure TLS versions and disable deprecated versions

### Improvements

* Added ability to use custom PrintUI parameters, thanks to @Iri5s
* Update Zazzles library to the latest version build
* Updated internationalization support
* Update copyright information in all source files and assembly to 2023
* Change logging of the installer for a more clear output on the console 
* Add XML schema that came with Quartz.NET last version update

### Bug Fixes

* Fix applying of printer settings through PrintUI, thanks @tatanas.
* Removed unused dlls
* Print CA cert subject name instead of friendly name

## FOS (FOG Operating System)

FOS is the linux based OS that is booted into from pxe and is used to perform imaging and other tasks.

We have upgraded the versions of its main dependencies, added many improvements and features and squashed some common and not so common bugs.

### Improvements

* Add support for wiping NVMe drives using nvme format, thanks @rluzuriaga
* Add server disk space check for mounted share when capturing an image
* Github Actions workflow for creating new releases, thanks @rluzuriaga
* Update Buildroot to v2022.02.9
* Update Linux kernel to v5.15.93
* Update partclone to v0.3.20
* Update gptfdisk to 1.0.8
* Add support for F2FS imaging and expanding F2FS on deployments, thanks @JlnWntr
* Add patch capability when building filesystem, thanks to @rluzuriaga
* Add feature to specify disk serial as host primary disk setting to make setups with identical sized NVMe drives work as well
* Change AWK script to handle awkward name and attrs fields in partition layouts we created ourselves
* Add tag name and ID to log output
* Add upload success check and silence curl calls
* Allow raw/dd image type capture without any partition on disk
* Add firmware file bnx2x-e2-7.13.15.0.fw for HP Blade
* Add feature to move partitions in GPT layouts forward when shrinking a partition would leave a gap if there is another partition after it. The subsequent partition often prevents from being able to deploy the image to a smaller size disk and so we try to get rid of that gap which is only possible in UEFI setups (GPT layout) where boot loaders don't reference to a sector on disk.
* Add potential capability for BTRFS resizing
* Add sysserial to the autoreg item to allow dynamic name setting
* Add some network drivers to the ARM64 kernel that were missed
* Remove kernel features power management, ACPI battery & AC, CPU idle driver
* Add firmware blob for Realtek 8125 based NICs
* Add feature to specify host primary disk via size instead of device name
* Enable USB type C support
* Add kernel modules for LUKS encryption
* Disable networking stuff like IPSec protocols: AH, ESP, PF_KEY, IPComp, XFRM and socket monitoring
* Enable access to /proc/config.gz, disable SWAP

### Bug Fixes

* Make the init boot up so it doesn't wait indefinitly on network error
* Removed deprecated methods from readme
* Modify build.sh to check needed packages on Ubuntu, thanks @rluzuriaga
* Fixed typo in tmpEBRFileName function, thanks to @Piotr86PL  
* Repair package dependency check on Debian where checks fail on dual installed libraries (32 and 64 bit)
* Add libelf depency to the build script, thanks @JlnWntr
* Change mount commands to fix exFAT-FS errors on BTRFS mounts, thanks @Piotr86PL
* Fixing a typo in the calculate_float function and adding a couple of messages to the resizing functions, thanks to @Piotr86PL
* Change mount commands to fix exFAT-FS errors
* Enclose artifact search query as bash globbing on * causes problems when files exist in the dist path.
* Add checksum check to release script just to be sure the binaries are ok
* Repair disk enumeration for image type MPA (all disks)
* Remove support for module loading from the kernel as we don't use modules 

# FOG 1.5.9

This release mainly focuses on bug fixes for FOG.

https://news.fogproject.org/fog-1-5-9-officially-released/

Comparison of 1.5.8 to 1.5.9: https://github.com/FOGProject/fogproject/compare/1.5.8...1.5.9

## FOG Web UI and Services

### Improvements

* Enhance error message when root environment is not properly loaded
* Get rid of trim on array warning when canceling a multicast task
* Add back reset of image size on client and update numbers only on capture
* Update description of Normal wipe (thanks to @abulhol)
* Change default global setting on compression format
* Fix two typos in ipxescripts (thanks to @bchr)

### Bug Fixes

* Add installer check to see if compiling iPXE binaries is needed
* Fix exec command line in systemd FOGSnapinReplicator file
* Force re-compile of rootcert.c in iPXE to prevent from issues when CA changes
* Update compile check scripts to latest versions on Raspberry Pi
* Fix IP/interface check on service startup
* Properly query multicast session id to be able to retrieve image info
* Repair product key decoding on call to hostinfo.php
* Improve input check on aesdecrypt in case of unencrypted passwords with pipe characters
* Handle DB root password more conservative to not lock out people with newer MariaDB versions
* Re-work the checks and setting up of DB security
* Prevent FOGMulticastManager from completing a task too early
* Fix some bugs in Sites plugin (thanks to Fernando Gietz)
* Switch to libcurl4 for Raspbian 10 as well
* Fix Image Manager to consider global setting on image creation
* Fix installer prompting for DB root password
* Force correct PHP version on Debian and remove old PHP when found to be still installed
* Improve check on fogstorage account access granted
* Prevent FOG services from filling up memory on hook events array
* Fix interface enumeration on systems with DHCP enabled
* Using PHP 7.4 on Ubuntu 20.04 as they moved just before the official release
* Fix installer issue with PPP that is not using a via gateway route
* Correct snapin list view column showing if it's a pack or not

## fog-client

fog-client 0.12.0 is included with FOG 1.5.9

### Improvements

* Move to .Net Framework 4.5.2
* Update Quartz to 3.0.7 and ZipLib to 1.2.0
* Add Romanian and Polish language support

### Bug Fixes

* Repair hang on Linux systems when shutting down FOGService (issue #93)
* Fix unzipping subdirectories on Linux/Unix systems (issue #122)

## FOS (FOG Operating System)

FOS is the linux based OS that is booted into from pxe and is used to perform imaging and other tasks.

### Improvements

* Switch to using shred instead of dd for normal wipe to make it faster
* Update Kernel (4.19.118) and Buildroot (2020.02.1)

# FOG 1.5.8

FOG 1.5.8 officially released: https://news.fogproject.org/fog-1-5-8-officially-released/

https://github.com/FOGProject/fogproject/compare/1.5.7...1.5.8

## FOG Web UI and Services

### Bug Fixes

* Use URL encoded parameter for password in CURL calls (587a25f, 79f94bd)
* Handle VLAN network interface names like eth0@if56 properly when enumerating interfaces (a2fc95e2)
* Fix LDAP port in plugin code (97b57ac, 403d1d1)
* Repair installer to work on all current Ubuntu versions (28debc9, 9233067, 4d61492, 79388ae)
* Rearange compile parameters as iPXE fails on SSL with CERT/TRUST not being last (e424b04)
* Change type of alternative subject name information as modern browsers check those strictly (7fdb3f6)
* Use SSLCACertificateFile instead as SSLCertificateChainFile which is obsolete since Apache 2.4.8 (6f7d462)
* Make sure we wait until an interface with our known IP is up #268 (69b4c44)
* Only ask for PHP/apache reinstall if PHP 5 was found installed (70078be)
* Repair building of iPXE binaries for HTTPS setups (296877d, 831d8ea)
* Fix typo in snapin class (6314aa4 thanks to Quazz)
* Repair version query in FOG configuration web view (8c69efc, fcfe759)
* Fix storage group failure on task finish updating (3c28f7b)
* Prevent from setting a MAC address that is in use by another host (88e10d8)
* Fix issue with double pipes in ad password field (9e70bc2)
* Fix download of snapins from wrong FOG server in location setups (d0162c8)
* Prevent from adding group associations with zero host or group ID (cf01c7e, c0d8509)
* Fix interface selection on systems where more than one NICs are up (ca3c2fa)
* Fix API call to editing host object, #329 (54beb9b)
* Prevent from migrating to MariaDB on Ubuntu installs to not cause trouble (29af4df)
* Do not reset the image size on client value on client checkin (9ff8776)
* Fix reports to work without groups and add search field (dde4d9d, b1f21c3, 7a6dc13)
* Correct package list update for Debian 10 buster upgrades (3f3b237)


### Improvements

* Update README with current information on downloading and installing FOG (0922112)
* Add username substitution to email field for deployment notification (7f80368)
* Re-try querying size and hash information via HTTPS (0fd7913, 2e55ba9)
* Make sure we don't ask for HTTPS again if parameter is given (ac08798)
* Improve check on fogproject account existing (8af5b3e)
* Add separate download for ARM platform binary archive and compilation of iPXE (d67a620, 30c4200, 6b1b260, 91fc00a)
* Remove old pxelinux stuff and ltsp config so people don't use it anymore (50e2995)
* Change default image definitions to Windows 10 and Zstd compression (45ec9fa)
* Add Ubuntu 20.xx support - tested on daily build currently PHP 7.3 (91f7cb0)
* Add installer question on enabling HTTPS (755cbc4)
* Switch to auto colors for bandwith graph (0ffd39f)
* Present storage node settings to the user more clearly (4226527)
* Improve database security by using less privileged account and force users to set a DB root password #355 (490cb3b, cc71a32)
* Add check for proper root environemnt within the installer (3c2d492)
* Add kernel parameter to disable problematic power saving for NVMe drives (e67ce75)
* Remove feature to add a changed primary MAC as additional MAC (8c820e2)
* Update installer to work on Ubuntu 19.xx (ab61ce0)
* Hide/mask AD password from users eyes, #337 (72b0de0)
* Improve firewall check to make it work for CentOS 8 as well (a039fd4)
* Update installer to work on CentOS 8 (3e125db, 5ff4c8b)
* Add sanity checking in the remote file hash and size response (933d7c3, bee5419 thanks to @mjrider)
* Only test the availability of the remote nodes once (b511e16 thanks to @mjrider)
* Add hints on image type setting including a link to the wiki article (2c17837)
* Add check for snapin file upload size to print appropriate warning to the user (1f4ad45)
* Re-add code for ARM based bootloaders (PR #327, thanks to parkerberb-nfv)
* Add iPXE AXGE native binary for ASIX AX88179 USB 3.0 (156001f)
* Ensure we download the latest hashsum to check the binaries against (9235729)
* Added possibilty to queue multicast task when the is not any slot free on the storage node (PR #310, bdbaf33 thanks to @Fernando Gietz)


## FOS (FOG Operating System)

### Bug Fixes

* Repair issues with partclone.imager that were introduced with version 0.3.12 (7faf4b6, 8b60ec0)
* Update awk cmd lines to no longer escape unncessarily (6f2f46a, 80e959a, 5f0e7b5 thanks to @Quazz)
* Get NVMe device names right (e0202be, 276b22d)
* Fix swap fs creation if capture and deploy use different disk technologies - SATA/SSD vs. NVMe vs. mmcblk (07fee1a)
* Make reize way more unreliable by adding flock to sfdisk calls (fd3daf2 also backported to 1.5.7)

### Improvements

* Update Linux Kernel (4.19.90) and Buildroot (2019.04.8)
* Update various packages: Partclone (0.3.13), Zstd (1.4.4), Testdisk & Photorec (7.1) - many thanks to @Quazz
* Add NVME cli utility (a6b7d24 thanks to @Quazz)
* Add error output when an error happens on partition layout restore (0e893c1)
* Improve algorithm to match destinations in multi disk setups (4439247)
* Allow for higher CPU core numbers and add Intel idle driver to fix #31 (fe58b6a)
* Prevent from adding partitions to fixed list twice and sort the list
* Add support for APFS filesystem (e151e67, 6c01ce6)
* Add rsyncable options when calling partclone (3cc47ca)

# FOG 1.x.y

FOG 1.x.y officially released: https://news.fogproject.org/...

Below is a summary of the many fixes and improvements introduced. 
We have squashed bugs, further hardened the security of the system, and introduced new features, improvements, and support for new operating systems.

Some highlights include:

* tbd

Thank you to all that have reported issues and feature requests in the forums and github requests and to all our developers, contributors, and users. 
We couldn't do this without you!

## FOG Web UI and Services

To view the full list of commits with the code changes introducing these fixes and features, see the [dev branch commit history](https://github.com/FOGProject/fogproject/commits/dev-branch)

### Security Fixes

* tbd

### Improvements

* tbd

### Bug Fixes

* tbd

## fog-client

fog-client 0.a.b is included with FOG 1.x.y

To view the full list of commits with the code changes introducing these fixes and features, see 

* [fog client commit history](https://github.com/FOGProject/fog-client/commits/master)
* [fog zazzles commit history](https://github.com/FOGProject/zazzles/commits/master)

### Security Fixes

* tbd

### Improvements

* tbd

### Bug Fixes

* tbd

## FOS (FOG Operating System)

FOS is the linux based OS that is booted into from pxe and is used to perform imaging and other tasks.

We have upgraded the versions of its main dependencies, added many improvements and features and squashed some common and not so common bugs.

### Security Fixes

* tbd

### Improvements

* tbd

### Bug Fixes

* tbd
