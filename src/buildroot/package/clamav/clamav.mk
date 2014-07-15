#############################################################
#
# clamav
#
#############################################################
CLAMAV_VERSION:=0.98
CLAMAV_SOURCE:=clamav-$(CLAMAV_VERSION).tar.gz
CLAMAV_SITE:=http://downloads.sourceforge.net/project/clamav/clamav/$(CLAMAV_VERSION)
CLAMAV_INSTALL_STAGING=YES
CLAMAV_LIBTOOL_PATCH=NO
CLAMAV_CONF_OPT= --disable-clamav \
		 --enable-clamdtop=no \
		 --disable-clamuko \
		 --enable-check=no \
		 --enable-llvm=no \
		 --program-transform-name=
CLAMAV_DEPENDENCIES=zlib bzip2

define CLAMAV_REMOVE_DB
        rm -f $(TARGET_DIR)/usr/share/clamav/*.cvd
endef

define CLAMAV_SIMPLE_CONFIG
	echo "DatabaseMirror database.clamav.net" > $(TARGET_DIR)/etc/freshclam.conf
	echo "DatabaseOwner root" >> $(TARGET_DIR)/etc/freshclam.conf
endef

CLAMAV_POST_INSTALL_TARGET_HOOKS += CLAMAV_REMOVE_DB
CLAMAV_POST_INSTALL_TARGET_HOOKS += CLAMAV_SIMPLE_CONFIG

$(eval $(autotools-package))
