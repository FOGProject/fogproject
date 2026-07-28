# FOGProject starting point

## Introduction

 FOG is a free open-source cloning/imaging/rescue suite/inventory management system. FOG can be used to image Windows XP, Vista, Windows 7, Windows 8/8.1, Windows 10, and Windows 11 PCs using PXE, PartClone, and a Web GUI to tie it together. Includes features like memory and disk test, disk wipe, av scan, task scheduling, inventory management, remote deployment of OS's, and remote installation of software packages. Features can be triggered through the web GUI, once the client machine has been registered with FOG.

## Versioning and branches

FOG uses a versioning schema that follows the general principles of semantic versioning with some adjustments to fit the development lifecycle. You can find the automatic release workflows in the [fog-workflows repo](https://github.com/FOGProject/fog-workflows) and the distro install tests in https://github.com/FOGProject/fogproject-install-validation

 


| Workflow | Status |
|----------|--------|
| Release | [![Stable Release](https://github.com/FOGProject/fog-workflows/actions/workflows/stable-releases.yml/badge.svg)](https://github.com/FOGProject/fog-workflows/actions/workflows/stable-releases.yml) |
| RHEL Distros | [![AlmaLinux 10](https://github.com/FOGProject/fogproject-install-validation/actions/workflows/distro_alma_10.yml/badge.svg)](https://github.com/FOGProject/fogproject-install-validation/actions/workflows/distro_alma_10.yml) [![AlmaLinux 9](https://github.com/FOGProject/fogproject-install-validation/actions/workflows/distro_alma_9.yml/badge.svg)](https://github.com/FOGProject/fogproject-install-validation/actions/workflows/distro_alma_9.yml) [![Debian 12](https://github.com/FOGProject/fogproject-install-validation/actions/workflows/distro_debian_12.yml/badge.svg)](https://github.com/FOGProject/fogproject-install-validation/actions/workflows/distro_debian_12.yml) [![Debian 13](https://github.com/FOGProject/fogproject-install-validation/actions/workflows/distro_debian_13.yml/badge.svg)](https://github.com/FOGProject/fogproject-install-validation/actions/workflows/distro_debian_13.yml) [![Fedora 40](https://github.com/FOGProject/fogproject-install-validation/actions/workflows/distro_fedora_40.yml/badge.svg)](https://github.com/FOGProject/fogproject-install-validation/actions/workflows/distro_fedora_40.yml) [![Fedora 41](https://github.com/FOGProject/fogproject-install-validation/actions/workflows/distro_fedora_41.yml/badge.svg)](https://github.com/FOGProject/fogproject-install-validation/actions/workflows/distro_fedora_41.yml) [![Fedora 42](https://github.com/FOGProject/fogproject-install-validation/actions/workflows/distro_fedora_42.yml/badge.svg)](https://github.com/FOGProject/fogproject-install-validation/actions/workflows/distro_fedora_42.yml) [![Rocky 10](https://github.com/FOGProject/fogproject-install-validation/actions/workflows/distro_rocky_10.yml/badge.svg)](https://github.com/FOGProject/fogproject-install-validation/actions/workflows/distro_rocky_10.yml) [![Rocky 9](https://github.com/FOGProject/fogproject-install-validation/actions/workflows/distro_rocky_9.yml/badge.svg)](https://github.com/FOGProject/fogproject-install-validation/actions/workflows/distro_rocky_9.yml) [![Ubuntu 22.04](https://github.com/FOGProject/fogproject-install-validation/actions/workflows/distro_ubuntu_22_04.yml/badge.svg)](https://github.com/FOGProject/fogproject-install-validation/actions/workflows/distro_ubuntu_22_04.yml)|

* The default branch of `stable` will always have the latest patch release, for most users this is where you want to install from.
* The `master` branch has the baseline of the latest Minor release. You should not typically install from here as it won't include security patches released since the baseline was set.
* `dev-branch` is where the latest patch release changes are staged and tested. You can install from dev-branch to help test bug-fixes, security-fixes, and minor feature enhancements on a more frequent cadence.
* `working-*` and `feature-named` branches are where work on the next Major or Minor release take place. They can be used to install and test the current beta version or specific working features.

This gives us a Production, Staging, and Dev branches to follow standard devops practices.

| Dev Cycle Stage  | Branches                                                                                                              | Version Property Associated |
|------------------|-----------------------------------------------------------------------------------------------------------------------| ----------------------------|
| Production       | stable, master                                                                                                        | Minor and Patch
| Staging          | dev-branch                                                                                                            | Patch
| Dev              | working-*, {feature-name}                                                                                             | Major, Minor

See also: [Version Sync Automation](https://docs.fogproject.org/development/version-sync-automation/) for how `FOG_VERSION`/`FOG_CHANNEL` are kept in sync with git state on each branch.

### Version Format

Our versions are formatted in a x.x.x.x format like so:

`{CodeBaseMajor}.{Major}.{Minor}.{Patch}`

| Version Property | Description                                                                                                           | Example |
|------------------|-----------------------------------------------------------------------------------------------------------------------|-----------|
| CodeBaseMajor    | Major code baseline changes and API breaking changes, requires formal release                                         | 1.x.x.x   |
| Major            | Major feature additions and UI changes, potential breaking changes within the same code base, requires formal release | 1.5.x.x   |
| Minor            | Non-breaking major feature enhancements, requires formal release                                                      | 1.5.10.x  |
| Patch            | On-going Bug and security fixes and feature enhancements, automated releases                                          | 1.5.10.41 |


## Install stable version

0. Install and update your linux server distro

1. Download the installation file(s)

* All that is needed to start installation is to download the files to perform the install. Choose one of the following methods you prefer;

  * **ZIP archive** `wget https://github.com/FOGProject/fogproject/archive/stable.zip; unzip stable.zip`

  * **TAR/GZ archive** `wget https://github.com/FOGProject/fogproject/archive/stable.tar.gz; tar xzf stable.tar.gz`

  * **git** `git clone https://github.com/fogproject/fogproject.git fogproject-stable`

2. Run the install script **as root** and follow all prompts accordingly

```
sudo -i
cd /path/to/fogproject-stable/bin
./installfog.sh
```

3. You should now be ready to use FOG

## Install latest development version

0. Install and update your linux server distro

1. Download the installation file(s)

* All that is needed to start the installation is to download the files to perform the install. Choose one of the following methods you prefer;

  * **git** `git clone https://github.com/fogproject/fogproject.git fogproject-dev-branch; cd fogproject-dev-branch; git checkout dev-branch` (**recommended if you want to keep up with current developments!**

  * **ZIP archive** `wget https://github.com/FOGProject/fogproject/archive/dev-branch.zip; unzip dev-branch.zip`

  * **TAR/GZ archive** `wget https://github.com/FOGProject/fogproject/archive/dev-branch.tar.gz; tar xzf dev-branch.tar.gz`

2. Run the install script **as root** and follow all prompts accordingly

```
sudo -i
cd /path/to/fogproject-dev-branch/bin
./installfog.sh
```
3. You should now be ready to use FOG

All should now be installed and you can start configuring and registering systems. Please see: http://fogproject.org/wiki/index.php/Managing_FOG to assist you in setting up further.

There are many resources for assistance.
 - **CSV Import/Export:** see [docs/CSV_IMPORT_EXPORT.md](docs/CSV_IMPORT_EXPORT.md) for the mass import/export file format — headered or positional columns per object type, and the associations column.
 - **Group shared state:** see [docs/GROUP_SHARED_STATE.md](docs/GROUP_SHARED_STATE.md) for how the group edit page surfaces what member hosts share — tri‑state associations (snapins/printers/modules) and no‑clobber config values (AD, auto‑logout, kernel/general, default printer).
 - **docs:** https://docs.fogproject.org for documentation. (New docs, under construction)
 - **Wiki:** http://fogproject.org/wiki for any information. (Legacy docs)
 - **Forum:** http://fogproject.org/forum.
 - **Email:** A Developer directly. If a dev permits a change, they can have themselves added on the wiki/Credits page.

## Development

 Download the source with git and checkout the branch `dev-branch` for the latest code or a more specific feature branch you would like to help work on.

 For further details please check out the [information on contributing to the project](CONTRIBUTING.md).
