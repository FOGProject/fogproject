# FOGProject starting point

## Introduction

 FOG is a free open-source cloning/imaging/rescue suite/inventory management system. FOG can be used to image Windows XP, Vista, Windows 7, Window 8/8.1, and Windows 10, Windows 11 PCs using PXE, PartClone, and a Web GUI to tie it together. Includes features like memory and disk test, disk wipe, av scan, task scheduling, inventory management, remote deployment of OS's, and remote installation of software packages. Features can be triggered through the web GUI, once the client machine has been registered with FOG.

## Install stable version

0. Install and update your linux server distro

1. Download the installation file(s)

* All that is needed to start installation is to download the files to perform the install. Choose one of the following methods you prefer;

  * **ZIP archive** `wget https://github.com/FOGProject/fogproject/archive/master.zip; unzip master.zip`

  * **TAR/GZ archive** `wget https://github.com/FOGProject/fogproject/archive/master.tar.gz; tar xzf master.tar.gz`

  * **git** `git clone https://github.com/fogproject/fogproject.git fogproject-master`

2. Run the install script **as root** and follow all prompts accordingly

```
sudo -i
cd /path/to/fogproject-master/bin
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
 - **Wiki:** http://fogproject.org/wiki for any information.
 - **Forum:** http://fogproject.org/forum.
 - **Email:** A Developer directly. If a dev permits a change, they can have themselves added on the wiki/Credits page.

## Development

 Download the source with git and checkout the branch `dev-branch` for the latest code or a more specific feature branch you would like to help work on.

 As you are running a development branch, please post bugs to either:

 - https://github.com/FOGProject/fogproject/issues
 - https://forums.fogproject.org/category/17/bug-reports

 If you would like to create a pull request, please make the pull request into the `dev-branch` branch.
