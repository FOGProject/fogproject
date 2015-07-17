################################################################################
#
# partimage
#
################################################################################

PARTIMAGE_VERSION = 0.6.9
PARTIMAGE_SOURCE = partimage-$(PARTIMAGE_VERSION).tar.bz2
PARTIMAGE_SITE = ftp://ftp.edu.ee/gentoo/distfiles
PARTIMAGE_DEPENDENCIES = e2fsprogs
PARTIMAGE_INSTALL_STAGING = YES
PARTIMAGE_CONF_OPTS = ac_cv_func_setpgrp_void=yes

$(eval $(autotools-package))
