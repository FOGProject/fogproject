################################################################################
#
# partimage
#
################################################################################

PARTIMAGE_VERSION = 0.6.9
PARTIMAGE_SOURCE = partimage-$(PARTIMAGE_VERSION).tar.bz2
PARTIMAGE_SITE = http://sourceforge.net/projects/partimage/files/stable/$(PARTIMAGE_VERSION)
PARTIMAGE_DEPENDENCIES = e2fsprogs
PARTIMAGE_INSTALL_STAGING = YES
PARTIMAGE_CONF_OPT= ac_cv_func_setpgrp_void=yes

$(eval $(autotools-package))
