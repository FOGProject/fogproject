# FOGProject starting point

## Introduction

FOG is a free open-source cloning/imaging solution/rescue suite. FOG can be used to image Windows XP, Vista, Windows 7 and Window 8 PCs using PXE, PartClone, and a Web GUI to tie it together. Includes features like memory and disk test, disk wipe, av scan & task scheduling.

## Install Stable (1.2.0)

0. Install and update your chosen linux server

1. Download the file(s)

All that is needed to start installation is to download the files to perform the install. Choose one of the following methods you prefer;

- **tar/zip** `wget http://downloads.sourceforge.net/project/freeghost/FOG/fog_1.2.0/fog_1.2.0.tar.gz | tar -xzfC trunk/`
- **git** `git clone https://github.com/fogproject/fogproject.git trunk/`
- **SVN** ` svn checkout https://svn.code.sf.net/p/freeghost/code/trunk trunk/`

2. Go into the downloaded source/bin folder

`cd trunk/bin`

3. Run the Install and follow all prompts accordingly

`sudo ./installfog.sh`

4. Enjoy

## Further Help

All should now be installed and you can start configuring and registering systems. Please see: http://fogproject.org/wiki/index.php/Managing_FOG to assist you in setting up further.

There are many resources for assistance. 
- **Wiki:** http://fogproject.org/wiki for any information.
- **Forum:** http://fogproject.org/forum.
- **Email:** A Developer directly. If a dev permit it they have added themselves on the wiki/Credits page.

## Development

Download the source with either git or SVN and checkout the `dev-branch` for the latest code or a more specific feature branch you would like to help work on.

As you are running a development branch, please post bugs to either:

- A new issue on https://github.com/FOGProject/fogproject/issues
- http://fogproject.org/forum/forums/bug-reports.17
