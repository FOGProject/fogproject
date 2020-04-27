#!/bin/bash
if [[ -r $1 ]]; then
  BUILDOPTS="CERT=$1 TRUST=$1"
elif [[ -r /opt/fog/snapins/ssl/CA/.fogCA.pem ]]; then
  BUILDOPTS="CERT=/opt/fog/snapins/ssl/CA/.fogCA.pem TRUST=/opt/fog/snapins/ssl/CA/.fogCA.pem"
fi
IPXEGIT="https://github.com/ipxe/ipxe"

# Change directory to base ipxe files
SCRIPT=$(readlink -f "$BASH_SOURCE")
FOGDIR=$(dirname $(dirname $(dirname "$SCRIPT") ) )
BASE=$(dirname "$FOGDIR")

if [[ -d ${BASE}/ipxe ]]; then
  cd ${BASE}/ipxe
  git clean -fd
  git reset --hard
  git pull
  cd src/
  # make sure this is being re-compiled in case the CA has changed!
  touch crypto/rootcert.c
else
  git clone ${IPXEGIT} ${BASE}/ipxe
  cd ${BASE}/ipxe/src/
fi


# Get current header and script from fogproject repo
echo "Copy (overwrite) iPXE headers and scripts..."
cp ${FOGDIR}/src/ipxe/src/ipxescript .
cp ${FOGDIR}/src/ipxe/src/ipxescript10sec .
cp ${FOGDIR}/src/ipxe/src/config/general.h config/
cp ${FOGDIR}/src/ipxe/src/config/settings.h config/
cp ${FOGDIR}/src/ipxe/src/config/console.h config/

# Build the files
make EMBED=ipxescript bin/ipxe.iso bin/{undionly,ipxe,intel,realtek}.{,k,kk}pxe bin/ipxe.lkrn bin/ipxe.usb ${BUILDOPTS}

# Copy files to repo location as required
cp bin/ipxe.iso bin/{undionly,ipxe,intel,realtek}.{,k,kk}pxe bin/ipxe.lkrn bin/ipxe.usb ${FOGDIR}/packages/tftp/
cp bin/ipxe.lkrn ${FOGDIR}/packages/tftp/ipxe.krn

# Build with 10 second delay
make EMBED=ipxescript10sec bin/ipxe.iso bin/{undionly,ipxe,intel,realtek}.{,k,kk}pxe bin/ipxe.lkrn bin/ipxe.usb ${BUILDOPTS}

# Copy files to repo location as required
cp bin/ipxe.iso bin/{undionly,ipxe,intel,realtek}.{,k,kk}pxe bin/ipxe.lkrn bin/ipxe.usb ${FOGDIR}/packages/tftp/10secdelay/
cp bin/ipxe.lkrn ${FOGDIR}/packages/tftp/10secdelay/ipxe.krn



# Change to the efi layout
if [[ -d ${BASE}/ipxe-efi ]]; then
  cd ${BASE}/ipxe-efi/
  git clean -fd
  git reset --hard
  git pull
  cd src/
  # make sure this is being re-compiled in case the CA has changed!
  touch crypto/rootcert.c
else
  git clone ${IPXEGIT} ${BASE}/ipxe-efi
  cd ${BASE}/ipxe-efi/src/
fi

# Get current header and script from fogproject repo
echo "Copy (overwrite) iPXE headers and scripts..."
cp ${FOGDIR}/src/ipxe/src-efi/ipxescript .
cp ${FOGDIR}/src/ipxe/src-efi/ipxescript10sec .
cp ${FOGDIR}/src/ipxe/src-efi/config/general.h config/
cp ${FOGDIR}/src/ipxe/src-efi/config/settings.h config/
cp ${FOGDIR}/src/ipxe/src-efi/config/console.h config/

# Build the files
make EMBED=ipxescript bin-{i386,x86_64}-efi/{snp{,only},ipxe,intel,realtek,ncm--ecm--axge}.efi ${BUILDOPTS}
[[ "x$armsupport" == "x1" ]] && make CROSS_COMPILE=aarch64-linux-gnu- ARCH=arm64 EMBED=ipxescript bin-arm64-efi/{snp{,only},ipxe,intel,realtek,ncm--ecm--axge}.efi ${BUILDOPTS}

# Copy the files to upload
[[ "x$armsupport" == "x1" ]] && cp bin-arm64-efi/{snp{,only},ipxe,intel,realtek,ncm--ecm--axge}.efi ${FOGDIR}/packages/tftp/arm64-efi/
cp bin-i386-efi/{snp{,only},ipxe,intel,realtek,ncm--ecm--axge}.efi ${FOGDIR}/packages/tftp/i386-efi/
cp bin-x86_64-efi/{snp{,only},ipxe,intel,realtek,ncm--ecm--axge}.efi ${FOGDIR}/packages/tftp/

# Build with 10 second delay
make EMBED=ipxescript10sec bin-{i386,x86_64}-efi/{snp{,only},ipxe,intel,realtek,ncm--ecm--axge}.efi ${BUILDOPTS}
[[ "x$armsupport" == "x1" ]] && make CROSS_COMPILE=aarch64-linux-gnu- ARCH=arm64 EMBED=ipxescript10sec bin-arm64-efi/{snp{,only},ipxe,intel,realtek,ncm--ecm--axge}.efi ${BUILDOPTS}

# Copy the files to upload
[[ "x$armsupport" == "x1" ]] && cp bin-arm64-efi/{snp{,only},ipxe,intel,realtek,ncm--ecm--axge}.efi ${FOGDIR}/packages/tftp/10secdelay/arm64-efi/
cp bin-i386-efi/{snp{,only},ipxe,intel,realtek,ncm--ecm--axge}.efi ${FOGDIR}/packages/tftp/10secdelay/i386-efi/
cp bin-x86_64-efi/{snp{,only},ipxe,intel,realtek,ncm--ecm--axge}.efi ${FOGDIR}/packages/tftp/10secdelay/
