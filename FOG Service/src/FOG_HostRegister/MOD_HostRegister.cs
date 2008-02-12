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

namespace FOG 
{

    public class HostRegister : AbstractFOGService
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
        private String strURLPath;

        private const String MOD_NAME = "FOG::HostRegister";

        public HostRegister()
        {
            intStatus = STATUS_STOPPED;
        }

        private Boolean readSettings()
        {
            if (ini != null)
            {
                if (ini.isFileOk())
                {
                    String pre = ini.readSetting("hostregister", "urlprefix");
                    String post = ini.readSetting("hostregister", "urlpostfix");
                    String ip = ini.readSetting("fog_service", "ipaddress");
                    if (ip != null && ip.Length > 0 && pre != null && pre.Length > 0 && post != null && post.Length > 0)
                    {
                        
                        strURLPath = pre + ip + post;
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
                if (readSettings())
                    registerHost();
                else
                {
                    log(MOD_NAME, "Failed to read ini settings.");
                }
            }
            catch
            {
            }
        }

        public override string mGetDescription()
        {
            return "Host Register - Registers a host with the FOG database if it doesn't exist.";
        }

        private void registerHost()
        {
            try
            {
                log(MOD_NAME, "Starting host registration process...");

                String strCurrentHostName = getHostName();
                String strMACAddress = "";
                String strIPAddress = "";
                String strOS = "";

                switch (System.Environment.OSVersion.Version.Major)
                {
                    case 5:
                        strOS = "1";
                        break;
                    case 6:
                        strOS = "2";
                        break;
                    default:
                        log(MOD_NAME, "This module has only been tested on Windows XP or Vista!");
                        break;
                }

                ArrayList alMACs = getMacAddress();
                if ( alMACs != null )
                {
                        for (int i = 0; i < alMACs.Count; i++)
                        {
                            if (alMACs[i] != null)
                            {
                                // we take the first MAC address and use it
                                strMACAddress = (String)alMACs[i];
                                break;
                            }
                        }
                    
                }

                ArrayList alIPs = getIPAddress();
                if (alIPs != null)
                {
                    for (int i = 0; i < alIPs.Count; i++)
                    {
                        if (alIPs[i] != null)
                        {
                            // take the first ip we find
                            strIPAddress = (String)alIPs[i];
                        }
                    }
                }


                if (strMACAddress != null && strCurrentHostName != null)
                {
                    WebClient web = new WebClient();
                    String strPath = strURLPath + "?mac=" + strMACAddress + "&hostname=" + strCurrentHostName + "&ip=" + strIPAddress + "&os=" + strOS;
                    String strData = web.DownloadString(strPath);
                    strData = strData.Trim();
                    if (strData.StartsWith("#!ok", true, null))
                    {
                        log(MOD_NAME, "Host has been registered.");
                        pushMessage("This host has been registered with the FOG Server.");
                    }
                    else if (strData.StartsWith("#!db", true, null))
                    {
                        log(MOD_NAME, "Database error.");
                        pushMessage("Unable to register host with FOG Server due to a database error.");
                    }
                    else if (strData.StartsWith("#!ma", true, null))
                    {
                        log(MOD_NAME, "MAC already registered.");
                    }
                    else
                    {
                        log(MOD_NAME, "Unknown error.");
                        pushMessage("Unable to register host with FOG Server due to an unknown error.");
                    }
                }
                else
                {
                    log(MOD_NAME, "Unable to register, either MAC or Hostname is null!");
                }

            }
            catch (Exception e)
            {
                pushMessage("FOG Host registration error:\n" + e.Message);
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
