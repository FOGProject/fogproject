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

    public class GreenFog : AbstractFOGService
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
        private String strURLTimes;
        private String strURLModuleStatus;
        private Boolean blGo;

        private const String MOD_NAME = "FOG::GreenFog";

        public GreenFog()
        {
            intStatus = STATUS_STOPPED;
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


                    String pre = ini.readSetting("greenfog", "urlprefix");
                    String post = ini.readSetting("greenfog", "urlpostfix");
                    if (ip != null && ip.Length > 0 && pre != null && pre.Length > 0 && post != null && post.Length > 0)
                    {
                        strURLTimes = pre + ip + post;
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
                {
                    blGo = true;
                    doWork();
                }
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
            return "Green FOG - This module will shutdown or restart the client computer on a schedule.";
        }

        private string decode64(string strEncode)
        {
            try
            {
                byte[] b = Convert.FromBase64String(strEncode);
                return Encoding.ASCII.GetString(b);
            }
            catch
            {
                return "";
            }
        }

        private void doWork()
        {
            try
            {
                try
                {
                    Random r = new Random();
                    int pause = r.Next(30, 60);
                    log(MOD_NAME, "Sleeping for " + pause + " seconds.");
                    System.Threading.Thread.Sleep(pause * 1000);
                }
                catch (Exception)
                { }


                log(MOD_NAME, "Starting green fog...");

                String[] arLines = null;
                

                switch (System.Environment.OSVersion.Version.Major)
                {
                    case 5:
                        break;
                    case 6:
                        break;
                    case 7:
                        break;
                    default:
                        log(MOD_NAME, "This module has only been tested on Windows XP, Vista and 7!");
                        break;
                }

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
                            String strPath = strURLModuleStatus + "?mac=" + macList + "&moduleid=greenfog";
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
                        log(MOD_NAME, "Module is disabled on this host.");
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

                    if (blLoop)
                    {
                        Boolean blLgIn = isLoggedIn();

                        try
                        {
                            WebClient wc = new WebClient();
                            String strRes = wc.DownloadString(strURLTimes);
                            strRes = strRes.Trim();
                            if (strRes != null)
                            {
                                arLines = strRes.Split('\n');
                                if (arLines.Length == 0)
                                {
                                    log(MOD_NAME, "No actions were found.");
                                    blGo = false;
                                }
                                else if (arLines.Length == 1 && arLines[0].Trim() == "")
                                {
                                    log(MOD_NAME, "No actions were found.");
                                    blGo = false;
                                }
                                else
                                {
                                    log(MOD_NAME, arLines.Length + " actions found, validating...");
                                }
                            }
                            else
                            {
                                blGo = false;
                                log(MOD_NAME, "Server response was null.");
                            }
                        }
                        catch (Exception exp)
                        {
                            log(MOD_NAME, exp.Message);
                            log(MOD_NAME, exp.StackTrace);
                            blGo = false;
                        }

                        ArrayList arTasks = new ArrayList();
                        for (int i = 0; i < arLines.Length; i++)
                        {
                            String strSingleLine = decode64( arLines[i] );
                            if (strSingleLine != null)
                            {
                                String[] strPieces = strSingleLine.Split('@');
                                if (strPieces != null)
                                {
                                    if (strPieces.Length == 3)
                                    {
                                        int h;
                                        int m;
                                        int a = -1;

                                        try
                                        {
                                            h = int.Parse( strPieces[0].Trim() );
                                            m = int.Parse(strPieces[1].Trim());
                                            if (strPieces[2].Trim() == "s")
                                                a = GreenFOGTask.SHUTDOWN;
                                            else if (strPieces[2].Trim() == "r")
                                                a = GreenFOGTask.REBOOT;
                                        }
                                        catch (Exception e)
                                        {
                                            log(MOD_NAME, e.Message);
                                            log(MOD_NAME, e.StackTrace);
                                            break;
                                        }

                                        if (h >= 0 && h <= 23 && m >= 0 && m <= 59 && (a == GreenFOGTask.REBOOT || a == GreenFOGTask.SHUTDOWN))
                                        {
                                            arTasks.Add(new GreenFOGTask(h, m, a));
                                        }
                                    }
                                }
                            }
                        }

                        if ( arTasks != null && arTasks.Count > 0 )
                        {
                            GreenFOGTask[] tasks = (GreenFOGTask[])(arTasks.ToArray(typeof(GreenFOGTask)));
                            if (tasks.Length > 0)
                            {
                                log(MOD_NAME, "Starting green fog loop...");
                                while (blGo)
                                {
                                    try
                                    {
                                        Boolean blCurLgIn = isLoggedIn();
                                        if (!blCurLgIn)
                                        {
                                            TimeSpan ts = DateTime.Now.TimeOfDay;
                                            for( int i = 0; i < tasks.Length; i++ )
                                            {
                                                if ( tasks[i] != null )
                                                {
                                                    if (ts.Hours == tasks[i].getHour() && ts.Minutes == tasks[i].getMinute())
                                                    {
                                                        if (tasks[i].getAction() == GreenFOGTask.SHUTDOWN)
                                                        {
                                                            log(MOD_NAME, "Scheduled shutdown @ " + tasks[i].getTime());
                                                            //shutdownComputer();

                                                            unmanagedExitWindows(ExitWindows.PowerOff | ExitWindows.Force);
                                                        }
                                                        else if (tasks[i].getAction() == GreenFOGTask.REBOOT)
                                                        {
                                                            log(MOD_NAME, "Scheduled reboot @ " + tasks[i].getTime());
                                                            //restartComputer();
                                                            unmanagedExitWindows(ExitWindows.Reboot | ExitWindows.Force);
                                                        }
                                                    }
                                                }
                                            }
                                        }
                                    }
                                    catch (Exception ex)
                                    {
                                        log(MOD_NAME, ex.Message);
                                        log(MOD_NAME, ex.StackTrace);
                                    }

                                    try
                                    {
                                        System.Threading.Thread.Sleep(40000);
                                    }
                                    catch { }
                                }
                                log(MOD_NAME, "Module has finished work and will now exit.");
                            }
                            else
                            {
                                log(MOD_NAME, "No tasks found after validation!");
                            }
                        }
                        else
                        {
                            log( MOD_NAME, "No tasks found after validation!" );
                        }

                    }

                }
                else
                {
                    log(MOD_NAME, "Unable to continue, MAC is null!");
                }
            }
            catch (Exception e)
            {
                pushMessage("Green FOG error:\n" + e.Message);
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
            blGo = false;
            return true;
        }

        public override int mGetStatus()
        {
            return intStatus;
        }
    }

    public class GreenFOGTask
    {
        public const int SHUTDOWN = 1;
        public const int REBOOT = 2;

        private int intHour;
        private int intMin;
        private int intAction;

        public GreenFOGTask( int hour, int minute, int action )
        {
            intHour = hour;
            intMin = minute;
            intAction = action;
        }

        public int getHour() { return this.intHour; }
        public int getMinute() { return this.intMin; }
        public int getAction() { return this.intAction; }
        public String getTime() { return getHour() + ":" + getMinute(); }
        public String getActionString()
        {
            if (intAction == SHUTDOWN)
                return "SHUTDOWN";
            else if (intAction == REBOOT)
                return "REBOOT";
            else
                return "N/A";
        }
    }
}
