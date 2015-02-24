FOGProject starting point

Introduction

FOG is a free open-source cloning/imaging solution/rescue suite. FOG can be used to image Windows XP, Vista, Windows 7 and Window 8 PCs using PXE, PartClone, and a Web GUI to tie it together. Includes featues like memory and disk test, disk wipe, av scan & task scheduling.

Installation Stable

Step 1: Download the file(s)

All that is needed to start installation is to download the files to perform the install. This will cover how to do this with Stable Releases. (At the time of this writing is 1.2.0) After you have installed your fog server os and performed any necessary updates run: `wget http://downloads.sourceforge.net/project/freeghost/FOG/fog_1.2.0/fog_1.2.0.tar.gz`

Step 2: Untar/zip the file

Once the file is downloaded untar and unzip the file: 
`tar -xzf fog_1.2.0.tar.gz`

Step 3: Go into the source/bin folder:

Once the file is extracted go to the installer location
`cd fog_1.2.0/bin`

Step 4: Install

Now you will begin installing the FOG system. Follow all prompts and answer accordingly: 
`sudo ./installfog.sh`

Step 5: Enjoy

All should now be installed and you can start configuring and registering systems. Please see: http://fogproject.org/wiki/index.php/Managing_FOG to assist you in setting up further.

Installation Trunk/SVN

Step 1: Download the file(s)

`sudo apt-get install subversion` (for Debian/Ubuntu Users) `sudo yum install subversion` (For Redhat/CentOS/Fedora Users)

Step 2: Pull the latest data.

`svn co https://svn.code.sf.net/p/freeghost/code/trunk`

Step 3: Go into the source/bin folder:

`cd trunk/bin`

Step 4: Install

`sudo ./installfog.sh`

Step 5: Enjoy

All should now be installed and you can start configuring and registering systems. Please see: http://fogproject.org/wiki/index.php/Managing_FOG to assist you in setting up further. As you are running a development branch, please post BUGs to either: http://fogproject.org/forum/forums/bug-reports.17 or create a new issue on https://github.com/FOGProject/fogproject/issues

Installation Trunk/GIT

Step 1: Download the file(s)

`sudo apt-get install git` (for Debian/Ubuntu Users) `sudo yum install git` (for Redhat/CentOS/Fedora Users)

Step 2: Pull the data from Github

`git clone https://github.com/fogproject/fogproject.git trunk`

Step 3: Go into the source/bin folder:

`cd trunk/bin`

Step 4: Install

`sudo ./installfog.sh`

Step 5: Enjoy

All should now be installed and you can start configuring and registering your systems. Please see: http://fogproject.org/wiki/index.php/Managing_FOG to assist you in setting up further As you are running a development branch, please post BUGs to either: http://fogproject.org/forum/forums/bug-reports.17 or create a new issue on https://github.com/FOGProject/fogproject/issues

Resources

There are many resources for assistance. Please check: http://fogproject.org/wiki for any information. http://fogproject.org/forum Email one of the developers directly (emails if devs have allowed themselves should be on the wiki/Credits page.
