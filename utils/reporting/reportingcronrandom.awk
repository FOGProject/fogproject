#!/usr/bin/awk -f
BEGIN {
    srand();
    dow = int(rand() * (0-6) + 6);
    hod = int(rand() * (0-23) + 23);
    moh = int(rand() * (0-59) + 59);
    reporting_log = "/var/log/fog/reporting.log";
    print "day_of_week="dow;
    print "hour_of_day="hod;
    print "minute_of_hour="moh;
    print "reporting_log="reporting_log;
    print "user_to_run_as=root";
    exit 0
}
