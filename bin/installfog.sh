<<<<<<< HEAD
#!/bin/sh

script /var/log/foginstall.log -c ./.install.sh
=======
#!/bin/sh
./.install.sh 2>&1 | tee /var/log/foginstall.log
>>>>>>> dev-branch
