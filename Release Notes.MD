# Fog 1.5.10 Stable release

Fog 1.5.10 officially released

## Security Fixes

* Add authentication check in getfiles to prevent file enumeration
* Add security checks to default pxe boot options like delete, change windows key, and join multicast domain
* Do not prepend filename to kernel information, thanks @wayneworkman
* Remove replication of ssl files and directories
* Update select2 JS and CSS library to latest version 4.0.13
* Prevent leak of AD password when applying default AD infos in host
* Remove header not posing much of security as mentioned in #473, thanks @djohle

## improvements

* PHP 8 Partial Support
* Add FOG version information to the dashboard storage node dropdown
* Allow adjustment of timeout between all partitions to be set, thanks @Magelfik
* Windows 11 compatibility added to notes and docs
* Updated Readme for various things
* Updated contribution guidelines
* Update udpcast to version 20200328
* Improved client downloader in web gui
* Repair host listing icons for Linux systems
* Try to find information of machines currently reporting empty OS name
* Allow for replication of sub directories, e.g. /images/drivers/video.… 
* Add kernel parameter for udpcast maximum wait time
* Improve groumanager.log output a fair bit
* Ignore parent directory entry in file size calculation
* Improved logging of various systems
* Add keymap (keyboard layout) setting to our iPXE binaries
* Update iPXE to the latest pull
* Add REBOOT menu to Host Bios Exit Type and Host EFI Exit Type
* Add kernel information to reporting script (#471) 
* Leave 7 percent space when shrinking partitions with resizable image
* Allow hostname regex for printers as well as IP addresses
* Add ability to specify additional TFTP server command line options
* Switch default database engine from MyISAM to InnoDB
* Point FOG web UI kernel update page to the kernel repo on github.
* Switch to snponly.efi for UEFI systems as default
* Save resouces by only enabling systemd tftp.socket
* Move from xinetd to systemd for TFTP connection handling on redhat
* Change internet connection check to better suit corporate networks

## bug fixes

* Various php 8 related fixes
* Fix incorrect array index name in location plugin code.
* Do not install sysv-rc-conf on Debian or Ubuntu, thanks @stevemoca
* Allow -U parameter to work as expected in installer, thanks to @lukebarone 
* Prevent from installing package dhcp* when not selected by installer
* Fixed behavioral issues of replication services
* Prevent from saving the whole snapin object when only associations need saving
* Silence warnings when upgrading a FOG install with services being enabled
* Remove inconsistent excess kernel reporting imformation, thanks @wayneworkman
* Make MulticastTask getAllMulticastTasks() function non static as call 
* Fixes for several parts of the iPXE boot menu like delete and approve hosts
* Repair installer for RedHat, Alma, Rocky and CentOS
* Fix snapin deletion causing an undefined variable exception with PHP 8
* Fix display of name for capone plugin within FOG configuration page
* Fix issue where filename got corrupted when uploading a new snapin file
* Fix bug in iPXE embedded scripts which prevents drive boot method EXIT 
* Prevent AD fields to be overwritten with default settings even if fie… 
* Fix typo in snapinjob call that was preventing snapins to run
* Remove bootfilename from installer and .fogsettings as it's not in use 
* Silence the output in case xinetd is not installed at all 
* Update installer scripts to fix Ubuntu 21.10 install
