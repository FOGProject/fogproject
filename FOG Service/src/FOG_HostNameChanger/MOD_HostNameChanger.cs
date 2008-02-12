using System;
using System.Collections.Generic;
using System.Text;
using System.Data;
using System.Net;
using System.Collections;
using System.Runtime.InteropServices;
using Microsoft.Win32;
using FOG;
using IniReaderObj;
using System.IO;
using System.Diagnostics;

namespace FOG 
{

    public class HostNameChanger : AbstractFOGService
    {
        [DllImport("user32.dll")] private static extern bool SetForegroundWindow(IntPtr hWnd);
        [DllImport("user32.dll")] private static extern bool ShowWindowAsync(IntPtr hWnd, int nCmdShow);
        [DllImport("user32.dll")] private static extern bool IsIconic(IntPtr hWnd);

        /*
         * Below is the PASSKEY you should change if you want to make your FOG installion more secure!
         * Just remember to change the PASSKEY in the config file of the application that you use to 
         * encrypt the passwords!
         */

        private const String PASSKEY = "FOG-OpenSource-Imaging";


        /*    / \
         *   / | \
         *     |
         *     |
         *     |
         *     |
         * 
         */

        private const int SW_HIDE = 0;
        private const int SW_SHOWNORMAL = 1;
        private const int SW_SHOWMINIMIZED = 2;
        private const int SW_SHOWMAXIMIZED = 3;
        private const int SW_SHOWNOACTIVATE = 4;
        private const int SW_RESTORE = 9;
        private const int SW_SHOWDEFAULT = 10;

        private int intStatus;
        
        private String strURLPath;
        private String strDomain;
        private String strUser;
        private String strPass;
        private String strOrgUnit;
        private String strNetDom;

        private Boolean isDefaultMode = true;

        private const String MOD_NAME = "FOG::HostnameChanger";

        public HostNameChanger()
        {
            intStatus = STATUS_STOPPED;
        }

        private Boolean readSettings()
        {
            if (ini != null)
            {
                if (ini.isFileOk())
                {
                    String pre = ini.readSetting("hostname_changer", "urlprefix");
                    String post = ini.readSetting("hostname_changer", "urlpostfix");
                    String ip = ini.readSetting("fog_service", "ipaddress");
                    if (ip != null && ip.Length > 0 && pre != null && pre.Length > 0 && post != null && post.Length > 0)
                    {

                        strURLPath = pre + ip + post;
                        strNetDom = ini.readSetting("hostname_changer", "netdompath");
                        
                        return true;
                    }
                }
            }
            return false;
        }

        public override void mStart()
        {
            try
            {
                intStatus = STATUS_RUNNING;
                if ( readSettings() )
                    changeHostName();
                else
                    log(MOD_NAME, "Failed to read ini settings.");
            }
            catch
            {
            }
        }

        public override string mGetDescription()
        {
            return "Hostname Changer - Changes the computer's hostname to match the FOG database.";
        }

        private void doDefaultMode( string newname)
        {
            RegistryKey regKey = null;
            log(MOD_NAME, "Using default fog method.");
            regKey = Registry.LocalMachine.OpenSubKey(@"SYSTEM\CurrentControlSet\Services\Tcpip\Parameters", true);
            regKey.SetValue("NV Hostname", newname);
            regKey = Registry.LocalMachine.OpenSubKey(@"SYSTEM\CurrentControlSet\Control\ComputerName\ActiveComputerName", true);
            regKey.SetValue("ComputerName", newname);
            regKey = Registry.LocalMachine.OpenSubKey(@"SYSTEM\CurrentControlSet\Control\ComputerName\ComputerName", true);
            regKey.SetValue("ComputerName", newname);

            log(MOD_NAME, "Computer is about to restart.");
            pushMessage("This computer's hostname has been updated and will reboot shortly.");
            try
            {
                System.Threading.Thread.Sleep(20000);
            }
            catch { }

            restartComputer();
        }

        private void changeHostName()
        {
            try
            {
                if (System.Environment.OSVersion.Version.Major != 5 && System.Environment.OSVersion.Version.Major != 6)
                {
                    log(MOD_NAME, "This module has only been tested on Windows XP or Vista!" );
                }

                log(MOD_NAME, "Starting hostname change process...");
                Random r = new Random();
                int intsleep = r.Next(1, 10);
                log(MOD_NAME, "Yielding to other subservices for " + intsleep + " seconds.");
                try
                {
                    System.Threading.Thread.Sleep(intsleep * 1000);
                }
                catch { }

                String strCurrentHostName = getHostName();
                String strHostName = null;
                ArrayList alMACs = getMacAddress();

                if ( alMACs != null )
                {
                    if (strCurrentHostName != null && strCurrentHostName.Length > 0)
                    {
                        for (int i = 0; i < alMACs.Count; i++)
                        {
                            if (alMACs[i] != null)
                            {
                                String strMAC = (String)alMACs[i];
                                WebClient web = new WebClient();
                                String strData = web.DownloadString(strURLPath + "?mac=" + strMAC);
                                strData = strData.Trim();
                                if (strData != null)
                                {
                                    String[] arData = strData.Split('\n');
                                    String strHostResults = arData[0];
                                    if (strHostResults.StartsWith("#!OK=", true, null))
                                    {
                                        if (arData.Length == 6)
                                        {
                                            strHostName = strHostResults.Remove(0, 5);
                                            String strUseAD = arData[1];
                                            String strD = arData[2];
                                            String strOU = arData[3];
                                            String strU = arData[4];
                                            String strP = arData[5];

                                            if (strUseAD != null)
                                            {
                                                strUseAD = strUseAD.Trim();
                                                if (strUseAD.StartsWith("#AD=")) strUseAD = strUseAD.Replace("#AD=", "");
                                            }

                                            if (strD != null)
                                            {
                                                strD = strD.Trim();
                                                if (strD.StartsWith("#ADDom=")) strD = strD.Replace("#ADDom=", "");
                                            }

                                            if (strOU != null)
                                            {
                                                strOU = strOU.Trim();
                                                if (strOU.StartsWith("#ADOU=")) strOU = strOU.Replace("#ADOU=", "");
                                            }

                                            if (strU != null)
                                            {
                                                strU = strU.Trim();
                                                if (strU.StartsWith("#ADUser=")) strU = strU.Replace("#ADUser=", "");
                                            }

                                            if (strP != null)
                                            {
                                                strP = strP.Trim();
                                                if (strP.StartsWith("#ADPass=")) strP = strP.Replace("#ADPass=", "");
                                            }

                                            if (strUseAD == "1")
                                            {
                                                log(MOD_NAME, "AD mode requested, confirming settings.");
                                                if (strD != null)
                                                {
                                                    strDomain = strD;
                                                    if (strOU != null)
                                                    {
                                                        strOrgUnit = strOU;
                                                    }

                                                    if (strU != null)
                                                    {

                                                        strUser = strU;
                                                        if (strP != null && strP != "")
                                                            strPass = new FOGCrypt(PASSKEY).decryptHex(strP);
                                                        else
                                                            strPass = "";

                                                        if (File.Exists(strNetDom))
                                                        {
                                                            isDefaultMode = false;
                                                        }
                                                        else
                                                        {
                                                            log(MOD_NAME, "Failed: netdom not found.");
                                                            strHostName = "";
                                                        }
                                                    }
                                                    else
                                                    {
                                                        log(MOD_NAME, "Failed: Invalid domain user.");
                                                        strHostName = "";
                                                    }

                                                }
                                                else
                                                {
                                                    log(MOD_NAME, "Failed: Invalid domain.");
                                                    strHostName = "";
                                                }
                                            }

                                            break;
                                        }
                                        else
                                        {
                                            log(MOD_NAME, "Failed: Incomplete server response; got: " + arData.Length + "; wanted: 6.");
                                            strHostName = "";
                                        }
                                    }
                                    else if (strHostResults.StartsWith("#!db", true, null))
                                    {
                                        log(MOD_NAME, "Database error");
                                    }
                                    else if (strHostResults.StartsWith("#!im", true, null))
                                    {
                                        log(MOD_NAME, "Invalid MAC address format");
                                    }
                                    else if (strHostResults.StartsWith("#!ih", true, null))
                                    {
                                        log(MOD_NAME, "Invalid Hostname format");
                                    }
                                    else if (strHostResults.StartsWith("#!nf", true, null))
                                    {
                                        log(MOD_NAME, "No host found");
                                    }
                                }
                            }
                        }
                    }
                }

                if (strHostName != null && strHostName.Length > 0 && strHostName.Length < 16)
                {
                        if (strCurrentHostName != null)
                        {
                            if (strCurrentHostName.ToLower().Trim() != strHostName.ToLower().Trim())
                            {
                                log(MOD_NAME, "Hostnames are different - " + strCurrentHostName + " - " + strHostName);
                                if (!isDefaultMode)
                                {
                                    log(MOD_NAME, "Attempting to unregister from domain...");
                                    Process p = new Process();
                                    p.StartInfo.WindowStyle = ProcessWindowStyle.Hidden;
                                    p.StartInfo.CreateNoWindow = true;
                                    p.StartInfo.UseShellExecute = false;
                                    p.StartInfo.RedirectStandardOutput = true;
                                    p.StartInfo.FileName = strNetDom;

                                    p.StartInfo.Arguments = " REMOVE " + strCurrentHostName + " /Domain:" + strDomain + " /UserD:" + strUser + " /passwordd:" + strPass;
                                    p.Start();
                                    string strOutput = p.StandardOutput.ReadToEnd();
                                    p.WaitForExit();
                                    log(MOD_NAME, "netdom output: " + strOutput);

                                }
                                doDefaultMode(strHostName);
                                
                            }
                            else
                            {
                                log(MOD_NAME, "Hostname is up to date");
                                if (!isDefaultMode)
                                {
                                    // AD Mode
                                    log(MOD_NAME, "Attempting to join domain if not already a member....");
                                    Process p = new Process();
                                    p.StartInfo.WindowStyle = ProcessWindowStyle.Hidden;
                                    p.StartInfo.CreateNoWindow = true;
                                    p.StartInfo.UseShellExecute = false;
                                    p.StartInfo.RedirectStandardOutput = true;
                                    p.StartInfo.FileName = strNetDom;

                                    String strO = "";
                                    if (strOrgUnit != null && strOrgUnit != "")
                                        strO = " /OU:\"" + strOrgUnit + "\"";

                                    p.StartInfo.Arguments = " JOIN " + strCurrentHostName + " /Domain:" + strDomain + " " + strO + " /UserD:" + strUser + " /passwordd:" + strPass + " /REBoot:35";
                                    p.Start();
                                    string strOutput = p.StandardOutput.ReadToEnd();
                                    p.WaitForExit();
                                    log(MOD_NAME, "netdom output: " + strOutput);
                                }
                            }
                        }
                        else
                        {
                            log(MOD_NAME, "Failed because I was unable to determine the current hostname");
                        }
                    }
                    else
                    {
                        log(MOD_NAME, "Host name was not found in the database.");
                }

            }
            catch (Exception e)
            {
                pushMessage("FOG Hostname changer error:\n" + e.Message );
                log(MOD_NAME, e.Message);
                log(MOD_NAME, e.StackTrace);
            }
            finally
            {
            }
            intStatus = STATUS_TASKCOMPLETE;
        }

        

        public override Boolean mStop()
        {
            log(MOD_NAME, "Shutdown complete");
            return true;
        }

        public override int mGetStatus()
        {
            return intStatus;
        }
    }
}
