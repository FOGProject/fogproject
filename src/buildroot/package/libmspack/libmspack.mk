#############################################################
#
# libmspack
#
#############################################################
LIBMSPACK_VERSION:=0.4alpha
LIBMSPACK_SOURCE:=libmspack-$(LIBMSPACK_VERSION).tar.gz
LIBMSPACK_SITE:=http://www.cabextract.org.uk/libmspack
LIBMSPACK_INSTALL_STAGING=YES

$(eval $(autotools-package))
