############################################################################################
#                              FOG Service Installer Script
############################################################################################
#Product Information
!define APP_NAME "FOG Service"
!define COMP_NAME "FOG"
!define WEB_SITE "http://fogproject.org/"
!define VERSION "00.00.00.20"
!define COPYRIGHT "FOG © 2007-2014"
!define DESCRIPTION "Application"
!define LICENSE_TXT "build\license.txt"
!define INSTALLER_NAME "Setup.exe"
!define MAIN_APP_EXE "FOGService.exe"
!define INSTALL_TYPE "SetShellVarContext all"
!define REG_ROOT "HKLM"
!define REG_APP_PATH "Software\Microsoft\Windows\CurrentVersion\App Paths\${MAIN_APP_EXE}"
!define UNINSTALL_PATH "Software\Microsoft\Windows\CurrentVersion\Uninstall\${APP_NAME}"

######################################################################
#Product Configuration
VIProductVersion  "${VERSION}"
VIAddVersionKey "ProductName"  "${APP_NAME}"
VIAddVersionKey "CompanyName"  "${COMP_NAME}"
VIAddVersionKey "LegalCopyright"  "${COPYRIGHT}"
VIAddVersionKey "FileDescription"  "${DESCRIPTION}"
VIAddVersionKey "FileVersion"  "${VERSION}"

######################################################################
#Installer Variables
SetCompressor ZLIB
Name "${APP_NAME}"
Caption "${APP_NAME}"
OutFile "${INSTALLER_NAME}"
BrandingText "${APP_NAME}"
XPStyle on
InstallDirRegKey "${REG_ROOT}" "${REG_APP_PATH}" ""
InstallDir "$PROGRAMFILES\FOG"

######################################################################
#Uninstall Macros
!macro RmDirsButOneMacro un
Function un.RmDirsButOne
 Exch $R0 ; exclude dir
 Exch
 Exch $R1 ; route dir
 Push $R2
 Push $R3
 
  ClearErrors
  FindFirst $R3 $R2 "$R1\*.*"
  IfErrors Exit
 
  Top:
   StrCmp $R2 "." Next
   StrCmp $R2 ".." Next
   StrCmp $R2 $R0 Next
   IfFileExists "$R1\$R2\*.*" 0 Next
    RmDir /r "$R1\$R2"
 
   #Goto Exit ;uncomment this to stop it being recursive (delete only one dir)
 
   Next:
    ClearErrors
    FindNext $R3 $R2
    IfErrors Exit
   Goto Top
 
  Exit:
  FindClose $R3
 
 Pop $R3
 Pop $R2
 Pop $R1
 Pop $R0
FunctionEnd
!macroend

######################################################################
#Includes
!include "MUI.nsh"
!include "FileFunc.nsh"
!include "LogicLib.nsh"
!insertmacro GetParameters
!insertmacro GetOptions
!insertmacro RmDirsButOneMacro "un."
!define MUI_ABORTWARNING
!define MUI_UNABORTWARNING

######################################################################
#Pages
!insertmacro MUI_PAGE_WELCOME

!ifdef LICENSE_TXT
!insertmacro MUI_PAGE_LICENSE "${LICENSE_TXT}"
!endif

!ifdef REG_START_MENU
!define MUI_STARTMENUPAGE_NODISABLE
!define MUI_STARTMENUPAGE_DEFAULTFOLDER "FOG"
!define MUI_STARTMENUPAGE_REGISTRY_ROOT "${REG_ROOT}"
!define MUI_STARTMENUPAGE_REGISTRY_KEY "${UNINSTALL_PATH}"
!define MUI_STARTMENUPAGE_REGISTRY_VALUENAME "${REG_START_MENU}"
!insertmacro MUI_PAGE_STARTMENU Application $SM_Folder
!endif

!insertmacro MUI_PAGE_DIRECTORY
!insertmacro MUI_PAGE_COMPONENTS
!insertmacro MUI_PAGE_INSTFILES
!insertmacro MUI_PAGE_FINISH
!insertmacro MUI_UNPAGE_CONFIRM
!insertmacro MUI_UNPAGE_INSTFILES
!insertmacro MUI_UNPAGE_FINISH

######################################################################
#Languages
!insertmacro MUI_LANGUAGE "English"
  
;--------------------------------
;Descriptions

######################################################################
#Elements
LangString DESC_SecTRAY ${LANG_ENGLISH} "Automatically start the FOG tray icon on login"
;Assign language strings to sections
!insertmacro MUI_FUNCTION_DESCRIPTION_BEGIN
!insertmacro MUI_DESCRIPTION_TEXT ${TRAY} $(DESC_SecTRAY)
!insertmacro MUI_FUNCTION_DESCRIPTION_END
  
######################################################################
#Command line switches
Var trayIcon
Var parameters
######################################################################

Section "FOG Tray Icon" TRAY
	ReadEnvStr $R0 AllUsersProfile
	CreateShortCut "$R0\Start Menu\Programs\Startup\FOGTray.lnk" "$INSTDIR\FOGTray.exe" "" ""
SectionEnd

Function .onInit
  ${GetParameters} $parameters
  ${GetOptions} $parameters "/tray" $trayIcon
  ${If} $trayIcon == ""
	${GetOptions} $parameters "-tray" $trayIcon  
  ${EndIf}
  ${If} $trayIcon == "=false"
	!insertmacro ReverseSection  ${TRAY}
  ${EndIf}  
  
FunctionEnd

Section -MainProgram
${INSTALL_TYPE}
SetOverwrite ifnewer
SetOutPath "$INSTDIR"
SimpleSC::InstallService "FOG Service" "FOGService" "16" "2" "$INSTDIR\FOGService.exe" "" "" ""
File /r "build\*"

ExecWait "$INSTDIR\FOGServiceConfig.exe $parameters"

SectionEnd

######################################################################

Section -Icons_Reg
SetOutPath "$INSTDIR"
WriteUninstaller "$INSTDIR\uninstall.exe"

!ifdef REG_START_MENU
!insertmacro MUI_STARTMENU_WRITE_BEGIN Application
CreateDirectory "$SMPROGRAMS\$SM_Folder"
CreateShortCut "$SMPROGRAMS\$SM_Folder\${APP_NAME}.lnk" "$INSTDIR\${MAIN_APP_EXE}"
!ifdef WEB_SITE
WriteIniStr "$INSTDIR\${APP_NAME} website.url" "InternetShortcut" "URL" "${WEB_SITE}"
CreateShortCut "$SMPROGRAMS\$SM_Folder\${APP_NAME} Website.lnk" "$INSTDIR\${APP_NAME} website.url"
!endif
!insertmacro MUI_STARTMENU_WRITE_END
!endif

!ifndef REG_START_MENU
CreateDirectory "$SMPROGRAMS\FOG Service"
CreateShortCut "$SMPROGRAMS\FOG Service\${APP_NAME}.lnk" "$INSTDIR\${MAIN_APP_EXE}"
!ifdef WEB_SITE
WriteIniStr "$INSTDIR\${APP_NAME} website.url" "InternetShortcut" "URL" "${WEB_SITE}"
CreateShortCut "$SMPROGRAMS\FOG Service\${APP_NAME} Website.lnk" "$INSTDIR\${APP_NAME} website.url"
!endif
!endif

WriteRegStr ${REG_ROOT} "${REG_APP_PATH}" "" "$INSTDIR\${MAIN_APP_EXE}"
WriteRegStr ${REG_ROOT} "${UNINSTALL_PATH}"  "DisplayName" "${APP_NAME}"
WriteRegStr ${REG_ROOT} "${UNINSTALL_PATH}"  "UninstallString" "$INSTDIR\uninstall.exe"
WriteRegStr ${REG_ROOT} "${UNINSTALL_PATH}"  "DisplayIcon" "$INSTDIR\${MAIN_APP_EXE}"
WriteRegStr ${REG_ROOT} "${UNINSTALL_PATH}"  "DisplayVersion" "${VERSION}"
WriteRegStr ${REG_ROOT} "${UNINSTALL_PATH}"  "Publisher" "${COMP_NAME}"

!ifdef WEB_SITE
WriteRegStr ${REG_ROOT} "${UNINSTALL_PATH}"  "URLInfoAbout" "${WEB_SITE}"
!endif
SectionEnd

######################################################################

Section Uninstall
${INSTALL_TYPE}
SimpleSC::RemoveService "FOGService"

#Delete all files except the etc folder and its content
Push "$INSTDIR" 
Push "etc" 		;dir to exclude
Call un.RmDirsButOne
Delete "$INSTDIR\*"

#Delete the tray shortcut
ReadEnvStr $R0 AllUsersProfile
Delete "$R0\Start Menu\Programs\Startup\FOGTray.lnk"
 
Delete "$INSTDIR\uninstall.exe"

!ifdef REG_START_MENU
!insertmacro MUI_STARTMENU_GETFOLDER "Application" $SM_Folder
Delete "$SMPROGRAMS\$SM_Folder\${APP_NAME}.lnk"
!ifdef WEB_SITE
Delete "$SMPROGRAMS\$SM_Folder\${APP_NAME} Website.lnk"
!endif
RmDir "$SMPROGRAMS\$SM_Folder"
!endif

!ifndef REG_START_MENU
Delete "$SMPROGRAMS\FOG Service\${APP_NAME}.lnk"
!ifdef WEB_SITE
Delete "$SMPROGRAMS\FOG Service\${APP_NAME} Website.lnk"
!endif
RmDir "$SMPROGRAMS\FOG Service"
!endif

DeleteRegKey ${REG_ROOT} "${REG_APP_PATH}"
DeleteRegKey ${REG_ROOT} "${UNINSTALL_PATH}"
SectionEnd

######################################################################
