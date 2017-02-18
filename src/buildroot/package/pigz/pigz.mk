#############################################################
#
# pigz
#
#############################################################
PIGZ_VERSION = 2.3.4
PIGZ_SOURCE = v$(PIGZ_VERSION).tar.gz
PIGZ_SITE = https://github.com/madler/pigz/archive
PIGZ_DEPENDENCIES = zlib

define PIGZ_BUILD_CMDS
	$(MAKE) CC="$(TARGET_CC)" -C $(@D) \
		LD="$(TARGET_LD)"
endef

define PIGZ_INSTALL_TARGET_CMDS
	$(INSTALL) -D -m 0755 $(@D)/pigz $(TARGET_DIR)/usr/bin/pigz
	$(STRIPCMD) $(STRIP_STRIP_ALL) $(TARGET_DIR)/usr/bin/pigz
endef

$(eval $(generic-package))
