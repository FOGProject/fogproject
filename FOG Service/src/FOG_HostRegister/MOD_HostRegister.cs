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
        private String strURLModuleStatus;

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

                    if (ip == null || ip.Trim().Length == 0)
                        ip = "fogserver";

                    String strPreMS = ini.readSetting("fog_service", "urlprefix");
                    String strPostMS = ini.readSetting("fog_service", "urlpostfix");
                    if (ip != null && strPreMS != null && strPostMS != null)
                        strURLModuleStatus = strPreMS + ip + strPostMS;
                    else
                    {
                        return false;
                    }

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

                ArrayList alMACs = getMacAddress();

                String macList = null;
                if (alMACs != null && alMACs.Count > 0)
                {
                    String[] strMacs = (String[])alMACs.ToArray(typeof(String));
                    macList = String.Join("|", strMacs);
                }

                if (alMACs != null && alMACs.Count < 2)
                {
                    log(MOD_NAME, "Exiting because only " + alMACs.Count + " mac address was found.");
                    return;
                }

                if (macList != null && macList.Length > 0)
                {
                    Boolean blConnectOK = false;
                    String strDta = "";
                    while (!blConnectOK)
                    {
                        try
                        {
                            log(MOD_NAME, "Attempting to connect to fog server...");
                            WebClient wc = new WebClient();
                            String strPath = strURLModuleStatus + "?mac=" + macList + "&moduleid=hostregister";
                            strDta = wc.DownloadString(strPath);
                            blConnectOK = true;
                        }
                        catch (Exception exp)
                        {
                            log(MOD_NAME, "Failed to connect to fog server!");
                            log(MOD_NAME, exp.Message);
                            log(MOD_NAME, exp.StackTrace);
                            log(MOD_NAME, "Sleeping for 1 minute.");
                            try
                            {
                                System.Threading.Thread.Sleep(60000);
                            }
                            catch { }
                        }
                    }

                    strDta = strDta.Trim();
                    Boolean blLoop = false;
                    if (strDta.StartsWith("#!ok", true, null))
                    {
                        log(MOD_NAME, "Module is active...");
                        blLoop = true;

                    }
                    else if (strDta.StartsWith("#!db", true, null))
                    {
                        log(MOD_NAME, "Database error.");
                    }
                    else if (strDta.StartsWith("#!im", true, null))
                    {
                        log(MOD_NAME, "Invalid MAC address format.");
                    }
                    else if (strDta.StartsWith("#!ng", true, null))
                    {
                        log(MOD_NAME, "Module is disabled globally on the FOG Server, exiting.");
                        return;
                    }
                    else if (strDta.StartsWith("#!nh", true, null))
                    {
                        log(MOD_NAME, "Module is disabled on this mac.");
                    }
                    else if (strDta.StartsWith("#!um", true, null))
                    {
                        log(MOD_NAME, "Unknown Module ID passed to server.");
                    }
                    else
                    {
                        log(MOD_NAME, "Unknown error, module will exit.");
                    }


                    if (blLoop)
                    {
                        WebClient web = new WebClient();
                        String strPath = strURLPath + "?mac=" + macList + "&version=2";
                        String strData = null;
                        
                            try
                            {
                                log(MOD_NAME, "Attempting to connect to fog server...");
                                web = new WebClient();
                                strData = web.DownloadString(strPath);
                                blConnectOK = true;
                            }
                            catch (Exception exp)
                            {
                                log(MOD_NAME, "Failed to connect to fog server!");
                                log(MOD_NAME, exp.Message);
                                log(MOD_NAME, exp.StackTrace);
                            }

                        
                        strData = strData.Trim();
                        if (strData.StartsWith("#!ok", true, null))
                        {
                            log(MOD_NAME, "At least one MAC address was added to the pending mac address list.");
                        }
                        else if (strData.StartsWith("#!ig", true, null))
                        {
                            log(MOD_NAME, "No action was taken.");
                        }
                        else if (strData.StartsWith("#!db", true, null))
                        {
                            log(MOD_NAME, "Database error.");
                        }
                        else if (strData.StartsWith("#!ma", true, null))
                        {
                            log(MOD_NAME, "MAC already registered.");
                        }
                        else
                        {
                            log(MOD_NAME, "Unknown error.");
                            pushMessage("Unable to register host with FOG Server due to an unknown error. " + strData);
                        }
                    }
                }
                else
                {
                    log(MOD_NAME, "Unable to register, MAC  is null!");
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
