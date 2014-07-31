::
::  FOG is a computer imaging solution.
::  Copyright (C) 2007-2014  Chuck Syperski & Jian Zhang
::
::   This program is free software: you can redistribute it and/or modify
::   it under the tedels of the GNU General Public License as published by
::   the Free Software Foundation, either version 3 of the License, or
::    any later version.
::
::   This program is distributed in the hope that it will be useful,
::   but WITHOUT ANY WARRANTY; without even the implied warranty of
::   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
::   GNU General Public License for more details.
::
::   You should have received a copy of the GNU General Public License
::   along with this program.  If not, see <http://www.gnu.org/licenses/>.
::
::

@echo off
setlocal enabledelayedexpansion

::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::
::Configuration
set ver=1.0
set defaultFrameworkVersion=v3.5
::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::
::Header output
echo(                                         
echo        ..#######:.    ..,#,..     .::##::.   
echo   .:######          .:####:......#..      
echo   ...##...        ...##,##::::.##...       
echo      ,#          ...##.....##:::##     ..::  
echo      ##    .::###,,##.   . ##.::#.:######::. 
echo   ...##:::###::....#. ..  .#...#. #...#:::.  
echo   ..:####:..    ..##......##::##  ..  #      
echo       #  .      ...##:,##:::#: ... ##..    
echo      .#  .       .:####::::.##:::#:..     
echo       #                     ..:###..        
echo( 
echo   ###########################################
echo   #     FOG                                 #
echo   #     Free Computer Imaging Solution      #
echo   #                                         #
echo   #     http://www.fogproject.org/          #
echo   #                                         #
echo   #     Developers:                         #
echo   #         Chuck Syperski                  #	
echo   #         Jian Zhang                      #
echo   #         Peter Gilchrist                 #
echo   #         Tom Elliott                     #		
echo   #     GNU GPL Version 3                   #		
echo   ###########################################
echo(


echo(
echo ============FOG Service Compiler============
echo =================Version %ver%================
echo(

::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::
::Handle command line parameters
::TODO: crypto key:  -key=XXXXX
::framework version: -framework=vX.X
::Usage = Compile.cmd -framework=v3.5
::The first "parameter" is the switch and the second is its value

IF "%1" == "/framework"  (
	set "version=%2"
	set frameworkVersion=!version!
) ELSE IF "%1" == "-framework"  (
	set "version=%2"
	set frameworkVersion=!version!
) ELSE (
	set frameworkVersion=%defaultFrameworkVersion%
)



::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::
::Set the working directory to the script's location
cd "%~dp0"

::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::
::Dependency Check
echo Checking dependencies

::NSIS
<nul set /p= ---^> NSIS...
for /f "tokens=*" %%b in ('where nsis') do set NSIS_Path=%%b
IF NOT EXIST "%NSIS_Path%" (
   <nul set /p=Failed
   echo(
   pause
   exit /b
)
<nul set /p=Success

::NSIS Simple Service Plugin
for %%i in ("%NSIS_Path%") do (
	set NSISFolder=%%~di%%~pi
)

echo(
<nul set /p= ------^> Simple Service Plugin...

IF NOT EXIST "%NSISFolder%Plugins\SimpleSC.dll" (
   <nul set /p=Failed
   echo(
   pause
   exit /b
)
<nul set /p=Success
echo(

:: .Net Framework msbuild tool
<nul set /p= ---^> .Net Framework %frameworkVersion%...
IF NOT EXIST "%windir%\Microsoft.NET\Framework\%frameworkVersion%\msbuild.exe" (
   <nul set /p=Failed
   echo(
   echo ERROR: Could not find .Net Framework %frameworkVersion% on your machine, either install this version or update frameworkVersion in this script
   pause
   exit /b
)
<nul set /p=Success
echo(
echo(

::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::
::Generate build files
<nul set /p=Generating build folder...
rmdir /S /Q "%~dp0build" > nul 2>&1
mkdir "%~dp0build" > nul 2>&1
call:checkErrors

del "%~dp0Setup.exe" > nul 2>&1

echo(
echo Building Files (.Net Framework %frameworkVersion%)
for /R %%a in (*.sln) do (
	for %%f in ("%%a") do (
		Set Folder=%%~dpf
		Set Name=%%~nxf
	)

	<nul set /p= ---^> Building !Name!...
	cd "!Folder!"
	"%windir%\Microsoft.NET\Framework\%frameworkVersion%\msbuild" "!Name!" /property:OutputPath="%~dp0build" > nul
	call:checkErrors
)
cd "%~dp0" 

::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::
::Copy extra files
echo(
<nul set /p=Transfering include files...
xcopy "%~dp0\include" "%~dp0build" /e /v /y  > nul 2>&1
call:checkErrors

<nul set /p=Transfering license...
cd ..
cd ..
copy license.txt "%~dp0build" > nul
call:checkErrors
cd "%~dp0"

<nul set /p=Transfering installer script...
copy "FOG Service Installer\FOG_Service_Installer.nsi" "%~dp0FOG_Service_Installer.nsi" > nul
call:checkErrors

::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::
::Build installer
echo(
<nul set /p=Building installer...
START /B /wait makensis FOG_Service_Installer.nsi > nul
call:checkErrors

::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::
::Remove build files	
:cleanBuildFiles
echo(
echo Removing build files
cd "%~dp0"
<nul set /p= ---^> Build Directory...
rmdir /S /Q "%~dp0build" > nul
IF EXIST "%~dp0build\NUL" (
   <nul set /p=Failed
   echo(
   pause
   exit /b
)
<nul set /p=Success
echo(

<nul set /p= ---^> Installer Script...
del "%~dp0FOG_Service_Installer.nsi" > nul 2>&1
call:checkErrors

echo(
IF EXIST %~dp0Setup.exe (
	echo Installer located at "%~dp0Setup.exe"
)
echo(
echo ========================Finished========================
echo(
pause
goto:eof

:checkErrors
if errorlevel 1 (
	<nul set /p=Failed
) else (
	<nul set /p=Success
)
echo(

:eof
setlocal disabledelayedexpansion
exit /b