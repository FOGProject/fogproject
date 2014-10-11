<<<<<<< HEAD
#############################################################
#
# fog
#
#############################################################
FOG_DIR:=$(BUILD_DIR)/fog_initrd_files
FOG_DEPENDENCIES = parted
FOG_PARTCLONE_ARCH =  $(if $(BR2_i386),partclone32,partclone64)

$(FOG_DIR)/.unpacked: $(DL_DIR)/$(FOG_SOURCE)
	mkdir -p $(FOG_DIR)
	cp -r package/fog/src/* $(FOG_DIR)
	touch $@

$(FOG_DIR)/fog_initrd_binaries: $(FOG_DIR)/.unpacked
	$(MAKE) $(TARGET_CONFIGURE_OPTS) -C $(FOG_DIR) \
		CXXFLAGS="$(TARGET_CXXFLAGS)" \
		LDFLAGS="$(TARGET_LDFLAGS)" 

$(TARGET_DIR)/bin/fogmbrfix: $(FOG_DIR)/fog_initrd_binaries
	rm -f $(TARGET_DIR)/bin/fogmbrfix
	$(INSTALL) -D -m 0755 $(FOG_DIR)/fogmbrfix $(TARGET_DIR)/bin/fogmbrfix
	$(STRIPCMD) $(STRIP_STRIP_ALL) $@

$(TARGET_DIR)/bin/fogpartinfo: $(FOG_DIR)/fog_initrd_binaries
	rm -f $(TARGET_DIR)/bin/fogpartinfo
	$(INSTALL) -D -m 0755 $(FOG_DIR)/fogpartinfo $(TARGET_DIR)/bin/fogpartinfo
	$(STRIPCMD) $(STRIP_STRIP_ALL) $@

fogscripts: 
	$(foreach script, \
	$(shell find package/fog/scripts/ -type f | sed 's:package/fog/scripts/:./:g'),  \
	$(INSTALL) -D -m 0755 package/fog/scripts/$(script) $(TARGET_DIR)/$(script);)

inittabfix:
	sed -i 's/^tty1.*/tty1::respawn:\/sbin\/getty -i -n -l \/bin\/sh 38400 tty1/; s/^tty2/#tty2/' $(TARGET_DIR)/etc/inittab

clamavfix:
	rm -rf $(TARGET_DIR)/usr/share/clamav
	mkdir $(TARGET_DIR)/usr/share/clamav

fogpartclone:
	cp package/fog/$(FOG_PARTCLONE_ARCH)/partclone.* $(TARGET_DIR)/usr/sbin/

fog: parted $(TARGET_DIR)/bin/fogmbrfix $(TARGET_DIR)/bin/fogpartinfo fogscripts inittabfix clamavfix fogpartclone

fog-clean:
	-$(MAKE) -C $(FOG_DIR) clean
	rm -f $(TARGET_DIR)/bin/fogmbrfix
	rm -f $(TARGET_DIR)/bin/partinfo
	$(foreach script, \
	$(shell find package/fog/scripts/ -type f | sed 's:package/fog/scripts/:./:g'),  \
	rm -f $(script);)

fog-dirclean:
	rm -rf $(FOG_DIR)

#############################################################
#
# Toplevel Makefile options
#
#############################################################
ifeq ($(BR2_PACKAGE_FOG),y)
TARGETS+=fog
endif
=======
#############################################################
#
# fog
#
#############################################################
FOG_DIR:=$(BUILD_DIR)/fog_initrd_files
FOG_DEPENDENCIES = parted
FOG_PARTCLONE_ARCH =  $(if $(BR2_i386),partclone32,partclone64)

$(FOG_DIR)/.unpacked: $(DL_DIR)/$(FOG_SOURCE)
	mkdir -p $(FOG_DIR)
	cp -r package/fog/src/* $(FOG_DIR)
	touch $@

$(FOG_DIR)/fog_initrd_binaries: $(FOG_DIR)/.unpacked
	$(MAKE) $(TARGET_CONFIGURE_OPTS) -C $(FOG_DIR) \
		CXXFLAGS="$(TARGET_CXXFLAGS)" \
		LDFLAGS="$(TARGET_LDFLAGS)" 

$(TARGET_DIR)/bin/fogmbrfix: $(FOG_DIR)/fog_initrd_binaries
	rm -f $(TARGET_DIR)/bin/fogmbrfix
	$(INSTALL) -D -m 0755 $(FOG_DIR)/fogmbrfix $(TARGET_DIR)/bin/fogmbrfix
	$(STRIPCMD) $(STRIP_STRIP_ALL) $@

$(TARGET_DIR)/bin/fogpartinfo: $(FOG_DIR)/fog_initrd_binaries
	rm -f $(TARGET_DIR)/bin/fogpartinfo
	$(INSTALL) -D -m 0755 $(FOG_DIR)/fogpartinfo $(TARGET_DIR)/bin/fogpartinfo
	$(STRIPCMD) $(STRIP_STRIP_ALL) $@

fogscripts: 
	$(foreach script, \
	$(shell find package/fog/scripts/ -type f | sed 's:package/fog/scripts/:./:g'),  \
	$(INSTALL) -D -m 0755 package/fog/scripts/$(script) $(TARGET_DIR)/$(script);)

inittabfix:
	sed -i 's/^tty1.*/tty1::respawn:\/sbin\/getty -i -n -l \/bin\/sh 38400 tty1/; s/^tty2/#tty2/' $(TARGET_DIR)/etc/inittab

clamavfix:
	rm -rf $(TARGET_DIR)/usr/share/clamav
	mkdir $(TARGET_DIR)/usr/share/clamav

fogpartclone:
	cp package/fog/$(FOG_PARTCLONE_ARCH)/partclone.* $(TARGET_DIR)/usr/sbin/

usbreset:
	rm -f $(TARGET_DIR)/usr/sbin/usbreset
	$(CC) $(FOG_DIR)/usbreset.c -o $(FOG_DIR)/usbreset
	$(INSTALL) -D -m 0755 $(FOG_DIR)/usbreset $(TARGET_DIR)/usr/sbin/usbreset

fog: parted $(TARGET_DIR)/bin/fogmbrfix $(TARGET_DIR)/bin/fogpartinfo fogscripts inittabfix clamavfix fogpartclone usbreset

fog-clean:
	-$(MAKE) -C $(FOG_DIR) clean
	rm -f $(TARGET_DIR)/bin/fogmbrfix
	rm -f $(TARGET_DIR)/bin/partinfo
	$(foreach script, \
	$(shell find package/fog/scripts/ -type f | sed 's:package/fog/scripts/:./:g'),  \
	rm -f $(script);)

fog-dirclean:
	rm -rf $(FOG_DIR)

#############################################################
#
# Toplevel Makefile options
#
#############################################################
ifeq ($(BR2_PACKAGE_FOG),y)
TARGETS+=fog
endif
>>>>>>> dev-branch
