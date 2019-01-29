#!/bin/bash
if [[ -r $1 ]]; then
  BUILDOPTS="TRUST=$1"
elif [[ -r /opt/fog/snapins/ssl/CA/.fogCA.pem ]]; then
  BUILDOPTS="TRUST=/opt/fog/snapins/ssl/CA/.fogCA.pem"
fi
IPXEGIT="https://git.ipxe.org/ipxe.git"

# Change directory to base ipxe files
SCRIPT=$(readlink -f "$BASH_SOURCE")
FOGDIR=$(dirname $(dirname $(dirname "$SCRIPT") ) )
BASE=$(dirname "$FOGDIR")

if [[ -d ${BASE}/ipxe ]]; then
  cd ${BASE}/ipxe
  git pull
  cd src/
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
make bin/ipxe.iso bin/{undionly,ipxe,intel,realtek}.{,k,kk}pxe bin/ipxe.lkrn EMBED=ipxescript ${BUILDOPTS} $*

# Copy files to repo location as required
cp bin/ipxe.iso bin/{undionly,ipxe,intel,realtek}.{,k,kk}pxe bin/ipxe.lkrn ${FOGDIR}/packages/tftp/

# Build with 10 second delay
make bin/ipxe.iso bin/{undionly,ipxe,intel,realtek}.{,k,kk}pxe bin/ipxe.lkrn EMBED=ipxescript10sec ${BUILDOPTS} $*

# Copy files to repo location as required
cp bin/ipxe.iso bin/{undionly,ipxe,intel,realtek}.{,k,kk}pxe bin/ipxe.lkrn ${FOGDIR}/packages/tftp/10secdelay/



# Change to the efi layout
if [[ -d ${BASE}/ipxe-efi ]]; then
  cd ${BASE}/ipxe-efi/
  git pull
  cd src/
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
make bin-{i386,x86_64}-efi/{snp{,only},ipxe,intel,realtek}.efi EMBED=ipxescript ${BUILDOPTS} $*

# Copy the files to upload
cp bin-i386-efi/{snp{,only},ipxe,intel,realtek}.efi ${FOGDIR}/packages/tftp/i386-efi/
cp bin-x86_64-efi/{snp{,only},ipxe,intel,realtek}.efi ${FOGDIR}/packages/tftp/

# Build with 10 second delay
make bin-{i386,x86_64}-efi/{snp{,only},ipxe,intel,realtek}.efi EMBED=ipxescript10sec ${BUILDOPTS} $*

# Copy the files to upload
cp bin-i386-efi/{snp{,only},ipxe,intel,realtek}.efi ${FOGDIR}/packages/tftp/10secdelay/i386-efi/
cp bin-x86_64-efi/{snp{,only},ipxe,intel,realtek}.efi ${FOGDIR}/packages/tftp/10secdelay/
