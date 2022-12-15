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

# REF: https://forums.fogproject.org/post/115420 - @Tom Elliott mentions to
#   truncate 5 tables of tasks. This will create a database backup, then get
#   rid of some of that history. He also mentions this won't be needed in 1.6
#   due to proper SQL pagination. So, only use this on 1.5.x
BACKUP_FILE="fog-original.sql"
LOG_FILE="purge_mysql_tables.log"
mysqldump fog > "$BACKUP_FILE"
echo "Database backed up to ${BACKUP_FILE}." | tee -a ${LOG_FILE}

DAYS_TO_KEEP=7
TABLE_NAMES=(snapinJobs imagingLog tasks)
DATE_COLS=(sjCreateTime ilStartTime taskCreateTime)
for index in "${!TABLE_NAMES[@]}"; do
    echo "Truncating entries in ${TABLE_NAMES[index]}..." | tee -a ${LOG_FILE}
    mysql -e "DELETE FROM ${TABLE_NAMES[index]} WHERE ${DATE_COLS[index]} <
    date_add(current_date,interval -${DAYS_TO_KEEP} day)" fog
done

MSIDS=$(mysql -e "SELECT msID FROM multicastSessions WHERE msStartDateTime <
        date_add(current_date,interval -${DAYS_TO_KEEP} day)" fog  | \
        cut -d' ' -f1)
if [[ -n $MSIDS ]]; then
    # Loop through the IDs and delete them from both tables
    echo "Truncating Multicast Sessions log..." | tee -a ${LOG_FILE}
    for id in "${MSIDS[@]}"; do
        mysql -e "DELETE FROM multicastSessions WHERE msID = ${id}" fog
        mysql -e "DELETE FROM multicastSessionsAssoc WHERE msID = ${id}" fog
    done
else
    echo "No Multicast Session Logs to truncate." | tee -a ${LOG_FILE}
fi

echo "Done" | tee -a ${LOG_FILE}

