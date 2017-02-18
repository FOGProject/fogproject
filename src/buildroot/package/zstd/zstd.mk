#############################################################
#
# zstd
#
#############################################################
ZSTD_VERSION = 1.1.3
ZSTD_SOURCE = v$(ZSTD_VERSION).tar.gz
ZSTD_SITE = https://github.com/facebook/zstd/archive

define ZSTD_BUILD_CMDS
	$(MAKE)
endef

define ZSTD_INSTALL_TARGET_CMDS
    $(MAKE) install
endef

$(eval $(generic-package))
