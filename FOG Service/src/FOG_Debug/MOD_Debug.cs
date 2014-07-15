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


namespace FOG 
{

    public class MODDebug : AbstractFOGService
    {
        [DllImport("user32.dll")] private static extern bool SetForegroundWindow(IntPtr hWnd);
        [DllImport("user32.dll")] private static extern bool ShowWindowAsync(IntPtr hWnd, int nCmdShow);
        [DllImport("user32.dll")] private static extern bool IsIconic(IntPtr hWnd);

        private const int SW_HIDE = 0;
        private const int SW_SHOWNORMAL = 1;
        private const int SW_SHOWMINIMIZED = 2;
        private const int SW_SHOWMAXIMIZED = 3;
        private const int SW_SHOWNOACTIVATE = 4;
        private const int SW_RESTORE = 9;
        private const int SW_SHOWDEFAULT = 10;

        private int intStatus;
        private String strURLDisplay;
        private String strURLModuleStatus;

        private const String MOD_NAME = "FOG::MODDebug";

        public MODDebug()
        {
            intStatus = STATUS_STOPPED;
            log(MOD_NAME, "MODDEBUG constructed");
        }

        private Boolean readSettings()
        {
            if (ini != null)
            {
                if (ini.isFileOk())
                {
                    // Get the FOG Server IP Address or hostname
                    String ip = ini.readSetting("fog_service", "ipaddress");

                    if (ip == null || ip.Trim().Length == 0)
                        ip = "fogserver";

                    // get the module status URL 
                    String strPreMS = ini.readSetting("fog_service", "urlprefix");
                    String strPostMS = ini.readSetting("fog_service", "urlpostfix");
                    if ( ip != null && strPreMS != null && strPostMS != null )
                        strURLModuleStatus = strPreMS + ip + strPostMS;
                    else
                    {
                        return false;
                    }

                    String pre = ini.readSetting("moddebug", "urlprefix");
                    String post = ini.readSetting("moddebug", "urlpostfix");
                    if (ip != null && ip.Length > 0 && pre != null && pre.Length > 0 && post != null && post.Length > 0)
                    {
                        strURLDisplay = pre + ip + post;
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
                log(MOD_NAME, "Start Called");
                intStatus = STATUS_RUNNING;

                log(MOD_NAME, "Reading config settings...");
                if (readSettings())
                {
                    log(MOD_NAME, "Reading of config settings passed.");
                    doWork();
                }
                else
                {
                    log(MOD_NAME, "Failed to read ini settings.");
                }
            }
            catch ( Exception e )
            {
                log(MOD_NAME, e.Message);
                log(MOD_NAME, e.StackTrace);
            }
        }

        public override string mGetDescription()
        {
            return "Debug Module - This module will only output some debugging information to the fog.log file to aid in troubleshooting.";
        }

        private void doWork()
        {
            try
            {
                log(MOD_NAME, "Starting Core processing...");

                String strMACAddress = "";

                switch (System.Environment.OSVersion.Version.Major)
                {
                    case 5:
                        break;
                    case 6:
                        break;
                    case 7:
                        break;
                    default:
                        log(MOD_NAME, "This module has only been tested on Windows XP, Vista & 7!");
                        break;
                }

                log(MOD_NAME, "Operating System ID: " + System.Environment.OSVersion.Version.Major);
                log(MOD_NAME, "Operating System Minor: " + System.Environment.OSVersion.Version.Minor);

                ArrayList alMACs = getMacAddress();

                int mid = 0;
                if (alMACs != null)
                {
                    for (int i = 0; i < alMACs.Count; i++)
                    {
                        if (alMACs[i] != null)
                        {
                            log( MOD_NAME, "MAC ID " + mid++ + " " + (String)alMACs[i] );        
                        }
                    }

                    String[] strOutMac = (String[])alMACs.ToArray(typeof(String));
                    log(MOD_NAME, "MAC POST String: " + String.Join("|", strOutMac ));
                } 
                
                if (strMACAddress != null )
                {
                    Boolean blLgIn = isLoggedIn();
                    if (blLgIn)
                    {
                        log(MOD_NAME, "A user is currently logged in");
                        log(MOD_NAME, "Username: " + getUserName());
                    }
                    else
                        log(MOD_NAME, "No user is currently logged in");

                    try
                    {
                        log(MOD_NAME, "Hostname: " + getHostName());

                        log(MOD_NAME, "Attempting to open connect to: " + strURLDisplay);
                        WebClient wc = new WebClient();
                        String strRes = wc.DownloadString(strURLDisplay);
                        strRes = strRes.Trim();
                        if (strRes != null)
                        {
                            log(MOD_NAME, "Server responded with: " + strRes);

                            }
                            else
                            {
                                log(MOD_NAME, "Server response was null.");
                            }
                        }
                        catch (Exception exp)
                        {
                            log(MOD_NAME, exp.Message);
                            log(MOD_NAME, exp.StackTrace);
                        }

                        log(MOD_NAME, "Module has finished work and will now exit.");
                }
                else
                {
                    log(MOD_NAME, "Unable to continue, MAC is null!");
                }

            }
            catch (Exception e)
            {
                pushMessage("FOG error:\n" + e.Message);
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
