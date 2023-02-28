# Fog 1.5.10 Stable release

Fog 1.5.10 officially released.

Below is a summary of the many fixes and improvements introduced over the last 2.5 years since the last official release.
We have squashed bugs, further hardened the security of the system, and introduced new features, improvements, and support for new operating systems.

Some highlights include

* PHP 8 support (partial)
* Windows 11 support
* More customizable printer configurations
* More security checks to prevent users from accessing administrative functions accidentally or maliciously
* Added and improved pxe boot menu options
* Support for recent Linux OS's and an improved installer experience
* Improved internationalization support
* And so much more!

Thank you to all that have reported issues and feature requests in the forums and github requests and to all our developers, contributors, and users. 
We couldn't do this without you!

## Updates to Fog WebUI and FOS

To view the full list of commits with the code changes introducing these fixes and features, see the [dev branch commit history](https://github.com/FOGProject/fogproject/commits/dev-branch)

### Security Fixes

* Add authentication check in getfiles to prevent file enumeration
* Add security checks to default pxe boot options like delete, change windows key, and join multicast domain
* Do not prepend filename to kernel information, thanks @wayneworkman
* Remove replication of ssl files and directories
* Update select JS and CSS library to latest version 4.0.13
* Prevent leak of AD password when applying default AD infos in host
* Remove header not posing much of security as mentioned in #473, thanks @djohle
* Add query for >=MariaDB 10.4 mysql.global_privs table
* Check download URL and destination filename to prevent from malicious use, thanks to sml for reporting
* Added web login logging
* Add changes to firewall and SELinux checks
* Added a defined [security policy](https://github.com/FOGProject/fogproject/blob/8ce090ba82ce22208daa0a20bc5018364e20949a/SECURITY.md)

### Improvements

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
* Allow for replication of sub directories, e.g. /images/drivers/video 
* Add kernel parameter for udpcast maximum wait time
* Improve groupmanager.log output a fair bit
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


### Bug fixes

* Various php 8 related fixes
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
* Update installer scripts to fix Ubuntu 21.10 install
* Set image size on server to zero when directory is not found
* Added missing openssl package dependency
* Fix installer password character check
* Correct option domain-name-servers on dhcpd.conf generation when empty
* Fix host edit page load issue when snapin history has entries set to 0000-00-00 00:00:00 timestamp

## Fog Client Changes

Fog Client 0.13.0 is included with Fog 1.5.10

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
