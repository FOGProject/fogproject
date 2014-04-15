################################################################################
#
# efibootmgr
#
################################################################################

EFIBOOTMGR_VERSION = 0.6.0
EFIBOOTMGR_SOURCE = efibootmgr-$(EFIBOOTMGR_VERSION).tar.gz
EFIBOOTMGR_SITE = http://linux.dell.com/efibootmgr/efibootmgr-0.6.0/
EFIBOOTMGR_INSTALL_STAGING = YES


$(BUILD_DIR)/efibootmgr-$(EFIBOOTMGR_VERSION): $(BUILD_DIR)/efibootmgr-$(EFIBOOTMGR_VERSION)/.unpacked
	cd $(BUILD_DIR)/efibootmgr-$(EFIBOOTMGR_VERSION); make
	
define EFIBOOTMGR_INSTALL_STAGING_CMDS
	cd $(BUILD_DIR)/efibootmgr-$(EFIBOOTMGR_VERSION); make
	$(INSTALL) -D -m 0755 $(@D)/src/efibootmgr/efibootmgr $(STAGING_DIR)/usr/sbin/efibootmgr
endef

define EFIBOOTMGR_INSTALL_TARGET_CMDS
	$(INSTALL) -D -m 0755 $(@D)/src/efibootmgr/efibootmgr $(TARGET_DIR)/usr/sbin/efibootmgr
endef

$(eval $(generic-package))
