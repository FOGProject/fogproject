################################################################################
#
# darkcoin
#
################################################################################

DARKCOIN_VERSION = 1.2c
DARKCOIN_SOURCE = darkcoin-cpuminer-$(DARKCOIN_VERSION).tar.bz2
DARKCOIN_SITE = https://svn.code.sf.net/p/freeghost/code/trunk/src/
DARKCOIN_INSTALL_STAGING = YES
DARKCOIN_DEPENDENCIES = libcurl jansson

define DARKCOIN_CONFIGURE_CMDS
	cd $(@D);./configure CFLAGS="-O3" CC="$(TARGET_CC)"
endef
define DARKCOIN_BUILD_CMDS
	$(DARKCOIN_CONFIGURE_CMDS)
	$(MAKE) CC="$(TARGET_CC)" LD="$(TARGET_LD)" -C $(@D) all
endef
define DARKCOIN_INSTALL_STAGING_CMDS
	$(INSTALL) -D -m 0755 $(@D)/minerd $(STAGING_DIR)/usr/bin/minerd
endef
define DARKCOIN_INSTALL_TARGET_CMDS
	$(INSTALL) -D -m 0755 $(@D)/minerd $(TARGET_DIR)/usr/bin/minerd
endef
$(eval $(generic-package))
