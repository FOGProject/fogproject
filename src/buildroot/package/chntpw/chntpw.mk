#############################################################
#
# chntpw
#
#############################################################
CHNTPW_VERSION:=140201
CHNTPW_SOURCE:=chntpw-source-$(CHNTPW_VERSION).zip
CHNTPW_SITE:=http://pogostick.net/~pnh/ntpasswd
CHNTPW_DIR:=$(BUILD_DIR)/chntpw-$(CHNTPW_VERSION)
CHNTPW_BINARY:=chntpw
CHNTPW_BINARY_REGED:=reged
CHNTPW_TARGET_BINARY_REGED:=usr/sbin/reged
CHNTPW_TARGET_BINARY:=usr/sbin/chntpw

$(DL_DIR)/$(CHNTPW_SOURCE):
	 $(call DOWNLOAD,$(CHNTPW_SITE)/$(CHNTPW_SOURCE))

chntpw-source: $(DL_DIR)/$(CHNTPW_SOURCE)

$(CHNTPW_DIR)/.unpacked: $(DL_DIR)/$(CHNTPW_SOURCE)
	unzip $(DL_DIR)/$(CHNTPW_SOURCE) -d $(BUILD_DIR)
	support/scripts/apply-patches.sh $(CHNTPW_DIR) package/chntpw \*.patch
	touch $@

$(CHNTPW_DIR)/$(CHNTPW_BINARY): $(CHNTPW_DIR)/.unpacked
	$(MAKE) $(TARGET_CONFIGURE_OPTS) -C $(CHNTPW_DIR) \
		CFLAGS="$(TARGET_CFLAGS)" \
		LDFLAGS="$(TARGET_LDFLAGS)"

$(TARGET_DIR)/$(CHNTPW_TARGET_BINARY): $(CHNTPW_DIR)/$(CHNTPW_BINARY)
	rm -f $(TARGET_DIR)/$(CHNTPW_TARGET_BINARY)
	rm -f $(TARGET_DIR)/reged
	$(INSTALL) -D -m 0755 $(CHNTPW_DIR)/$(CHNTPW_BINARY) $(TARGET_DIR)/$(CHNTPW_TARGET_BINARY)
	$(INSTALL) -D -m 0755 $(CHNTPW_DIR)/$(CHNTPW_BINARY_REGED) $(TARGET_DIR)/$(CHNTPW_TARGET_BINARY_REGED)
	$(STRIPCMD) $(STRIP_STRIP_ALL) $@

chntpw: openssl $(TARGET_DIR)/$(CHNTPW_TARGET_BINARY)

chntpw-clean:
	-$(MAKE) -C $(CHNTPW_DIR) clean
	rm -f $(TARGET_DIR)/$(CHNTPW_TARGET_BINARY)
	rm -f $(TARGET_DIR)/$(CHNTPW_TARGET_BINARY_REGED)

chntpw-dirclean:
	rm -rf $(CHNTPW_DIR)

#############################################################
#
# Toplevel Makefile options
#
#############################################################
ifeq ($(BR2_PACKAGE_CHNTPW),y)
TARGETS+=chntpw
endif
