#############################################################
#
# pigz
#
#############################################################
PIGZ_VERSION = 2.3.3
PIGZ_SOURCE = v$(PIGZ_VERSION).tar.gz
PIGZ_SITE = https://github.com/madler/pigz/archive
PIGZ_CAT = $(ZCAT)
PIGZ_DIR = $(BUILD_DIR)/pigz-$(PIGZ_VERSION)
PIGZ_BINARY = pigz
PIGZ_DEPENDENCIES = zlib

define PIGZ_BUILD_CMDS
	$(MAKE) $(TARGET_CONFIGUR_OPTS) -C $(@D) 
endef

define PIGZ_INSTALL_TARGET_CMDS
	$(INSTALL) -D -m 0755 $(@D)/pigz $(TARGET_DIR)/usr/bin/pigz
	$(STRIPCMD) $(STRIP_STRIP_ALL) $(TARGET_DIR)/usr/bin/pigz
endef

$(eval $(generic-package))
