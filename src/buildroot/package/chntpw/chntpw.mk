#############################################################
#
# chntpw
#
#############################################################
CHNTPW_VERSION = 140201
CHNTPW_SOURCE = chntpw-source-$(CHNTPW_VERSION).zip
CHNTPW_SITE = http://pogostick.net/~pnh/ntpasswd

define CHNTPW_EXTRACT_CMDS
	unzip $(DL_DIR)/$(CHNTPW_SOURCE) -d $(BUILD_DIR)
endef

define CHNTPW_BUILD_CMDS
	$(MAKE) $(TARGET_CONFIGURE_OPTS) -C $(@D) \
		CFLAGS="$(TARGET_CFLAGS)" \
		LDFLAGS="$(TARGET_LDFLAGS)"
endef

define CHNTPW_INSTALL_TARGET_CMDS
	$(INSTALL) -D -m 0755 $(@D)/chntpw $(TARGET_DIR)/usr/sbin/chntpw
	$(INSTALL) -D -m 0755 $(@D)/reged $(TARGET_DIR)/usr/sbin/reged
	$(STRIPCMD) $(STRIP_STRIP_ALL) $(TARGET_DIR)/usr/sbin/chntpw
	$(STRIPCMD) $(STRIP_STRIP_ALL) $(TARGET_DIR)/usr/sbin/reged
endef

$(eval $(generic-package))
