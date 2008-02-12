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

        private const String MOD_NAME = "FOG::TaskReboot";

        public TaskReboot()
        {
            intStatus = STATUS_STOPPED;
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
                if (alMACs != null)
                {
                    for (int i = 0; i < alMACs.Count; i++)
                    {
                        if (alMACs[i] != null)
                        {
                            String strMAC = (String)alMACs[i];
                            
                            WebClient web = new WebClient();
                            String strData = web.DownloadString(url + strMAC);
                            strData = strData.Trim();
                            //*  "#!db" => Database error
                            //*  "#!im" => Invalid MAC Format
                            //*  "#!er" => Other error.
                            //*  "#!ok" => Job Exists -> GO!
                            //*  "#!nj" => No Job Exists
                            if (strData.StartsWith("#!OK=", true, null))
                            {
                                return true;
                            }
                            else if (strData.StartsWith("#!im", true, null))
                            {
                                log(MOD_NAME, "Invalid MAC address format for " + strMAC);
                            }
                            else if (strData.StartsWith("#!er", true, null))
                            {
                                log(MOD_NAME, "General error for " + strMAC);
                            }
                            else if (strData.StartsWith("#!nj", true, null))
                            {
                                log(MOD_NAME, "No job exists for " + strMAC);
                            }
                            else if (strData.StartsWith("#!db", true, null))
                            {
                                log(MOD_NAME, "Database error for " + strMAC);
                            }
                        }
                    }
                }
                else
                    log(MOD_NAME, "No MAC address found.");
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
                    if (!isLoggedIn())
                    {
                        if (hasTask())
                        {
                            log(MOD_NAME, "A task was found for this client, computer will restart shortly.");
                            pushMessage("This computer has been scheduled for a FOG Task and will reboot shortly.  Please save all data now!");
                            try
                            {
                                Thread.Sleep(30000);
                            }
                            catch { }
                        }
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
