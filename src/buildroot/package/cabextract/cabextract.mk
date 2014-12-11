###########################################################
#
# cabextract
#
###########################################################

CABEXTRACT_SITE=http://www.cabextract.org.uk
CABEXTRACT_VERSION=1.4
CABEXTRACT_SOURCE=cabextract-$(CABEXTRACT_VERSION).tar.gz
CABEXTRACT_CONF_OPTS=ac_cv_func_fnmatch_gnu=yes ac_cv_func_fnmatch_works=yes

$(eval $(autotools-package))
