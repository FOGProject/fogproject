################################################################################
#
# partclone
#
################################################################################

PARTCLONE_VERSION = 0.2.73
PARTCLONE_SOURCE = partclone-$(PARTCLONE_VERSION).tar.gz
PARTCLONE_SITE = http://sourceforge.net/projects/partclone/files/testing/src/
PARTCLONE_INSTALL_STAGING = YES
PARTCLONE_AUTORECONF = YES
PARTCLONE_DEPENDENCIES = attr e2fsprogs libgcrypt lzo xz zlib xfsprogs ncurses host-pkgconf
PARTCLONE_CONF_OPT = --enable-static --enable-xfs --enable-btrfs --enable-ntfs --enable-extfs --enable-fat --enable-hfsp --enable-static --enable-ncursesw

define PARTCLONE_LINK_LIBRARIES_TOOL
	rm -rf $(@D)/../../staging/usr/include/xfs
	rm -rf $(@D)/../../staging/usr/include/ncursesw
	rm -rf $(@D)/../../staging/usr/lib/libxfs*
	ln -s /usr/include/xfs $(@D)/../../staging/usr/include/ && \
	ln -s /usr/lib/libxfs* $(@D)/../../staging/usr/lib/
	ln -s /usr/include/ncursesw $(@D)/../../staging/usr/include/
endef

PARTCLONE_POST_PATCH_HOOKS += PARTCLONE_LINK_LIBRARIES_TOOL

$(eval $(autotools-package))
