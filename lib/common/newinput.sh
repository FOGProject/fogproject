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

while [[ -z $hostname ]]; do
    blHost="N"
    if [[ -z $autoaccept ]]; then
        echo
        echo "  Would you like to change the default hostname $strSuggestedHostname?"
        echo "  The fully qualified hostname is used for the webserver certificate."
        echo -n "  If you are not sure, select No. [y/N] "
        read blHost
    fi
    case $blHost in
        [Nn]|[Nn][Oo]|"")
            hostname=$strSuggestedHostname
            ;;
        [Yy]|[Yy][Ee][Ss])
            echo -n "  Which hostname would you like to use? "
            read hostname
            ;;
        *)
            echo "  Invalid input, please try again."
            ;;
    esac
done
