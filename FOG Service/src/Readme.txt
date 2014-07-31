=============TODO============
*Test NSIS v3.0b
*Don't require NSIS to be in the PATH variable
==============================

=========Initial Setup========
Before using Compile.cmd ensure you have:
*NSIS v2.46 installed (http://nsis.sourceforge.net/Download 32 or 64 bit)
*The simple service plugin (http://nsis.sourceforge.net/NSIS_Simple_Service_Plugin) installed (make sure SimpleSC.dll is in the Plugins folder)
*NSIS's installation directory added to the PATH variable
*.NET Framework v3.5 (some compile errors have been detected with higher versions) You can check by going to %windir%\Microsoft.NET\Framework\ and ensuring there is a v3.5 folder
==============================

=============Usage============
*To compile a new installer run Compile.cmd (elevated privileges are NOT required)
*You can pass the /framework= parameter to specify a build target (using a - instead of / works), make sure to include a v before version number (e.g. /framework=v3.5)
==============================

======Installer Switches======
*/S                                 Silent Mode (required for all other switches to work)
*/tray=false                        Does not auto start the tray whenever someone logs in (-tray= works too)
*/ip=x.x.x.x                        Specify the FOG server's ip address (using -ip= works too)
*/DLL_NAME_NO_EXTENSION=false       Removes the specified dll from installation, DLL_NAME_NO_EXTENSION is case insensitive (e.g. /AutoLogOut=false or /AUTOLOGOUT=false will work) (using -DLL_NAME_NO_EXTENSION=false works too)
==============================