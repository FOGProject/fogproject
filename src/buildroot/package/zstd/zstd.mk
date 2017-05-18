#############################################################
#
# zstd
#
#############################################################
ZSTD_VERSION = 1.2.0
ZSTD_SOURCE = v$(ZSTD_VERSION).tar.gz
ZSTD_SITE = https://github.com/facebook/zstd/archive

define ZSTD_BUILD_CMDS
	$(MAKE) CC="$(TARGET_CC)" -C $(@D) \
		LD="$(TARGET_LD)" zstdmt
endef

define ZSTD_INSTALL_TARGET_CMDS
	$(INSTALL) -D -m 0755 $(@D)/zstdmt $(TARGET_DIR)/usr/bin/zstdmt
	$(STRIPCMD) $(STRIP_STRIP_ALL) $(TARGET_DIR)/usr/bin/zstdmt
endef

$(eval $(generic-package))
