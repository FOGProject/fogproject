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
using System.Threading;

namespace FOG 
{

    public class TaskReboot : AbstractFOGService
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
        private int intCheckIn;
        private String url;
        private Boolean blGo;
        private Boolean blForce;
        private String strURLModuleStatus;

        private const String MOD_NAME = "FOG::TaskReboot";

        public TaskReboot()
        {
            intStatus = STATUS_STOPPED;
            blForce = false;
        }

        public override void mStart()
        {
            try
            {
                intStatus = STATUS_RUNNING;
                blGo = true;
                if (readSettings())
                    startWatching();
                else
                    log(MOD_NAME, "Halted, unable to read ini settings");
            }
            catch
            {
            }
        }

        public Boolean readSettings()
        {
            if (ini != null)
            {
                try
                {
                    String tmpPre = ini.readSetting("taskreboot", "urlprefix");
                    String tmpPost = ini.readSetting("taskreboot", "urlpostfix");
                    String tmpIP = ini.readSetting("fog_service", "ipaddress");

                    if (tmpIP == null || tmpIP.Trim().Length == 0)
                        tmpIP = "fogserver";

                    String strPreMS = ini.readSetting("fog_service", "urlprefix");
                    String strPostMS = ini.readSetting("fog_service", "urlpostfix");
                    if (tmpIP != null && strPreMS != null && strPostMS != null)
                        strURLModuleStatus = strPreMS + tmpIP + strPostMS;
                    else
                    {
                        return false;
                    }

                    blForce = (ini.readSetting("taskreboot", "forcerestart") == "1");

                    if (blForce)
                        log(MOD_NAME, "Taskreboot in force mode.");
                    else
                        log(MOD_NAME, "Taskreboot in lazy mode.");

                    intCheckIn = Int32.Parse(ini.readSetting("taskreboot", "checkintime"));
                    url = tmpPre + tmpIP + tmpPost + "?mac=";
                    if (tmpPre != null && tmpPost != null && tmpIP != null && intCheckIn > 0)
                        return true;
                }
                catch
                {
                    return false;
                }

            }
            return false;
        }

        public override string mGetDescription()
        {
            return "Task Reboot - This sub service will periodically check for a task and if one is found, it will reboot the computer.";
        }

        private Boolean hasTask()
        {
            try
            {
                ArrayList alMACs = getMacAddress();

                String macList = null;
                if (alMACs != null && alMACs.Count > 0)
                {
                    String[] strMacs = (String[])alMACs.ToArray(typeof(String));
                    macList = String.Join("|", strMacs);
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
                            String strPath = strURLModuleStatus + "?mac=" + macList + "&moduleid=taskreboot";
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
                        return false;
                    }
                    else if (strDta.StartsWith("#!nh", true, null))
                    {
                        log(MOD_NAME, "Module is disabled on this mac.");
                    }
                    else if (strDta.StartsWith("#!um", true, null))
                    {
                        log(MOD_NAME, "Unknown Module ID passed to server.");
                    }
                    else if (strDta.StartsWith("#!er", true, null))
                    {
                        log(MOD_NAME, "General Error Returned: ");
                        log(MOD_NAME, strDta);
                    }
                    else
                    {
                        log(MOD_NAME, "Unknown error, module will exit.");
                    }

                    WebClient web = new WebClient();
                    String strData = null;

                    if (blLoop)
                    {
                        try
                        {
                            log(MOD_NAME, "Attempting to connect to fog server...");
                            web = new WebClient();
                            strData = web.DownloadString(url + macList);
                            blConnectOK = true;
                        }
                        catch (Exception exp)
                        {
                            log(MOD_NAME, "Failed to connect to fog server!");
                            log(MOD_NAME, exp.Message);
                            log(MOD_NAME, exp.StackTrace);
                        }
                    }

                    if (strData != null)
                    {
                        strData = strData.Trim();
                        //*  "#!db" => Database error
                        //*  "#!im" => Invalid MAC Format
                        //*  "#!er" => Other error.
                        //*  "#!ok" => Job Exists -> GO!
                        //*  "#!nj" => No Job Exists

                        if (strData.StartsWith("#!OK", true, null))
                        {
                            return true;
                        }
                        else if (strData.StartsWith("#!im", true, null))
                        {
                            log(MOD_NAME, "Invalid MAC address format for " + macList);
                        }
                        else if (strData.StartsWith("#!er", true, null))
                        {
                            log(MOD_NAME, "General error for " + macList);
                        }
                        else if (strData.StartsWith("#!nj", true, null))
                        {
                            log(MOD_NAME, "No job exists for " + macList);
                        }
                        else if (strData.StartsWith("#!db", true, null))
                        {
                            log(MOD_NAME, "Database error for " + macList);
                        }
                        else if (strDta.StartsWith("#!er", true, null))
                        {
                            log(MOD_NAME, "General Error Returned: ");
                            log(MOD_NAME, strDta);
                        }
                    }
                }
                else
                    log(MOD_NAME, "No valid MAC addresses found!");
            }
            catch (Exception e)
            {
                log(MOD_NAME, e.Message);
                return false;
            }
            return false;
        }

        private void startWatching()
        {
            try
            {
                log(MOD_NAME, "Starting Task Reboot...");

                while (blGo)
                {
                    if (!isLoggedIn() || blForce)
                    {
                        if (hasTask())
                        {
                            log(MOD_NAME, "A task was found for this client, computer will restart shortly.");
                            pushMessage("This computer has been scheduled for a FOG Task and will reboot shortly.  Please save all data now!");
                            try
                            {
                                Thread.Sleep(30000);
                                // I give up on managed code!
                                //restartComputer();

                                unmanagedExitWindows(ExitWindows.Reboot | ExitWindows.Force);

                            }
                            catch { }
                        }
                        else
                            log(MOD_NAME, "No task found for client.");
                    }

                    try
                    {

                        System.Threading.Thread.Sleep(intCheckIn * 1000);
                    }
                    catch { }
                }
                log(MOD_NAME, "Stopping Task Reboot...");

            }
            catch (Exception e)
            {
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
            blGo = false;
            log(MOD_NAME, "Stopping...");
            return true;
        }

        public override int mGetStatus()
        {
            return intStatus;
        }
    }
}
