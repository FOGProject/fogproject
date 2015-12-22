#############################################################
#
# fog
#
#############################################################
FOG_VERSION = 1
FOG_SOURCE = fog_$(FOG_VERSION).tar.gz
FOG_SITE = https://www.fogproject.org
FOG_DEPENDENCIES = parted

define FOG_BUILD_CMDS
	cp -rf package/fog/src $(@D)
	cp -rf package/fog/scripts $(@D)
	$(MAKE) $(TARGET_CONFIGURE_OPTS) -C $(@D)/src \
	CXXFLAGS="$(TARGET_CXXFLAGS)" \
	LDFLAGS="$(TARGET_LDFLAGS)"
	$(CC) $(@D)/src/usbreset.c -o $(@D)/src/usbreset
endef

define FOG_INSTALL_TARGET_CMDS
	$(INSTALL) -D -m 0755 $(@D)/src/fogmbrfix $(TARGET_DIR)/bin/fogmbrfix
	$(STRIPCMD) $(STRIP_STRIP_ALL) $(TARGET_DIR)/bin/fogmbrfix
	$(foreach script, \
	$(shell find $(@D)/scripts/ -type f | sed 's:$(@D)/scripts/:./:g'), \
	$(INSTALL) -D -m 0755 $(@D)/scripts/$(script) $(TARGET_DIR)/$(script);)
	mkdir -p $(TARGET_DIR)/usr/share/clamav
	$(INSTALL) -D -m 0755 $(@D)/src/usbreset $(TARGET_DIR)/usr/sbin/usbreset
endef

$(eval $(generic-package))
