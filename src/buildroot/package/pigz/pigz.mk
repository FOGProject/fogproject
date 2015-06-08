#############################################################
#
# pigz
#
#############################################################
PIGZ_VERSION = 2.3.1
PIGZ_SOURCE = pigz-$(PIGZ_VERSION).tar.gz
PIGZ_SITE = download.freenas.org/distfiles
PIGZ_CAT = $(ZCAT)
PIGZ_DIR = $(BUILD_DIR)/pigz-$(PIGZ_VERSION)
PIGZ_BINARY = pigz
PIGZ_DEPENDENCIES = zlib

define PIGZ_EXTRACT_CMDS
	$(ZCAT) $(DL_DIR)/$(PIGZ_SOURCE) | tar -C $(BUILD_DIR) $(TAR_OPTIONS) -
endef

define PIGZ_BUILD_CMDS
	$(MAKE) $(TARGET_CONFIGURE_OPTS) -C $(@D) \
		CFLAGS="$(TARGET_CFLAGS)" \
		LDFLAGS="$(TARGET_LDFLAGS)"
endef

define PIGZ_INSTALL_TARGET_CMDS
	$(INSTALL) -D -m 0755 $(@D)/pigz $(TARGET_DIR)/usr/bin/pigz
	$(STRIPCMD) $(STRIP_STRIP_ALL) $(TARGET_DIR)/usr/bin/pigz
endef

$(eval $(generic-package))
