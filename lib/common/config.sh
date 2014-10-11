<<<<<<< HEAD
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
#
#

# Linux Account that is used for FTP transactions
username="fog";

# This is used for storage nodes
dbuser="root"
dbpass=""
dbhost="localhost"

# where are the php files from the download package?
webdirsrc="../packages/web";

# where are the tftp files from the download package?
tftpdirsrc="../packages/tftp";

# where are the udpcast files from the download package?
udpcastsrc="../packages/udpcast-20120424.tar.gz";
udpcasttmp="/tmp/udpcast.tar.gz";
udpcastout="udpcast-20120424";

# where are the service files from the download package?
servicesrc="../packages/service";

# where do the service files go?
servicedst="/opt/fog/service"

# where do the service log files go?
servicelogs="/opt/fog/log"

# where do the fog program files go?
fogprogramdir="/opt/fog"

# where do generic fog utils go?
fogutilsdir="${fogprogramdir}/utils";

# where do generic fog utils come from?
fogutilsdirsrc="../packages/utils";

# what version are we working with?
version="1.2.0";

# what is the schema version
schemaversion="23";
=======
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
#
#

# Linux Account that is used for FTP transactions
username="fog";

# This is used for storage nodes
dbuser="root"
dbpass=""
dbhost="localhost"

# where are the php files from the download package?
webdirsrc="../packages/web";

# where are the tftp files from the download package?
tftpdirsrc="../packages/tftp";

# where are the udpcast files from the download package?
udpcastsrc="../packages/udpcast-20120424.tar.gz";
udpcasttmp="/tmp/udpcast.tar.gz";
udpcastout="udpcast-20120424";

# where are the service files from the download package?
servicesrc="../packages/service";

# where do the service files go?
servicedst="/opt/fog/service"

# where do the service log files go?
servicelogs="/opt/fog/log"

# where do the fog program files go?
fogprogramdir="/opt/fog"

# where do generic fog utils go?
fogutilsdir="${fogprogramdir}/utils";

# where do generic fog utils come from?
fogutilsdirsrc="../packages/utils";

# what version are we working with?
version="1.3.0";

# what is the schema version
schemaversion="23";
>>>>>>> dev-branch
