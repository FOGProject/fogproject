################################################################################
#
# partclone
#
################################################################################

PARTCLONE_VERSION = 0.2.78
PARTCLONE_SOURCE = partclone_$(PARTCLONE_VERSION).orig.tar.gz
PARTCLONE_SITE = http://sourceforge.net/projects/partclone/files/testing/src
PARTCLONE_INSTALL_STAGING = YES
PARTCLONE_AUTORECONF = YES
PARTCLONE_DEPENDENCIES = attr e2fsprogs libgcrypt lzo xz zlib xfsprogs ncurses host-pkgconf
PARTCLONE_CONF_OPTS = --enable-static --enable-xfs --enable-btrfs --enable-ntfs --enable-extfs --enable-fat --enable-hfsp --enable-static --enable-ncursesw

define PARTCLONE_LINK_LIBRARIES_TOOL
	ln -f -s $(@D)/../xfsprogs-*/include/xfs $(@D)/../../staging/usr/include/
    ln -f -s $(@D)/../xfsprogs-*/libxfs/.libs/libxfs.* $(@D)/../../staging/usr/lib/
endef

PARTCLONE_POST_PATCH_HOOKS += PARTCLONE_LINK_LIBRARIES_TOOL

$(eval $(autotools-package))
