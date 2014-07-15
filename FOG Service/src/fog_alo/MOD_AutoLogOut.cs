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
using System.Threading;
using System.Drawing;


namespace FOG 
{

    public class AutoLogOut : AbstractFOGService
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
        private int intTimeout = -1;
        private String strURLTimeout;
        private String strURLBG;
        private String strURLModuleStatus;
        private Boolean blGo;
        private ALOForm frm;
        private Image bgImage;

        private const String MOD_NAME = "FOG::AutoLogOut";

        public AutoLogOut()
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


                    String pre = ini.readSetting("autologout", "urlprefix");
                    String post = ini.readSetting("autologout", "urlpostfix");
                    if (ip != null && ip.Length > 0 && pre != null && pre.Length > 0 && post != null && post.Length > 0)
                    {
                        strURLTimeout = pre + ip + post;
                    }
                    else
                        return false;

                    String preBG = ini.readSetting("autologout", "urlprefixbg");
                    String postBG = ini.readSetting("autologout", "urlpostfixbg");
                    if (ip != null && ip.Length > 0 && preBG != null && preBG.Length > 0 && postBG != null && postBG.Length > 0)
                    {
                        strURLBG = preBG + ip + postBG;
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
            return "AutoLogOut - This module log a user off a this computer after X minutes of inactivity.";
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

        public void ALOLogOffUser()
        {
            if (isLoggedIn())
            {
                log(MOD_NAME, "restarting computer due to inactivity");
                restartComputer();
            }
        }

        private void doWork()
        {
            try
            {
                log(MOD_NAME, "Starting process...");
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

                // First check and see if the module is active
                //
                Boolean blLoop = false;
                if (macList != null && macList.Length > 0)
                {
                    String strData = null;
                    Boolean blConnectOK = false;
                    while (!blConnectOK)
                    {
                        try
                        {
                            WebClient web = new WebClient();
                            String strPath = strURLModuleStatus + "?mac=" + macList + "&moduleid=autologout";
                            strData = web.DownloadString(strPath);
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

                    strData = strData.Trim();
                    if (strData.StartsWith("#!ok", true, null))
                    {
                        log(MOD_NAME, "Module is active...");
                        blLoop = true;
                    }
                    else if (strData.StartsWith("#!db", true, null))
                    {
                        log(MOD_NAME, "Database error.");
                    }
                    else if (strData.StartsWith("#!im", true, null))
                    {
                        log(MOD_NAME, "Invalid MAC address format.");
                    }
                    else if (strData.StartsWith("#!ng", true, null))
                    {
                        log(MOD_NAME, "Module is disabled globally on the FOG Server.");
                    }
                    else if (strData.StartsWith("#!nh", true, null))
                    {
                        log(MOD_NAME, "Module is disabled on this host.");
                    }
                    else if (strData.StartsWith("#!um", true, null))
                    {
                        log(MOD_NAME, "Unknown Module ID passed to server.");
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
                            String strRes = wc.DownloadString(strURLTimeout + "?mac=" + macList);
                            strRes = strRes.Trim();
                            if (strRes != null)
                            {
                                
                                String strTimeout = decode64(strRes);
                                
                                if (strTimeout != null)
                                {
                                    try
                                    {
                                        intTimeout = int.Parse( strTimeout );
                                        if (intTimeout == 0)
                                        {
                                            log(MOD_NAME, "Timeout value is Zero, disabling module.");
                                            blGo = false;
                                        }
                                        else if (intTimeout <= 1)
                                        {
                                            log(MOD_NAME, "Timeout value was either 1 minute or less, I am going to assume this is a mistake and exit.");
                                            blGo = false;
                                        }

                                    }
                                    catch (Exception e)
                                    {
                                        log(MOD_NAME, e.Message);
                                        log(MOD_NAME, e.StackTrace);
                                        blGo = false;
                                    }
                                }
                                else
                                {
                                    blGo = false;
                                    log(MOD_NAME, "Unable to determine timeout settings.");
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

                        String strBGImage = null;
                        
                        try
                        {
                            WebClient wc = new WebClient();
                            String strRes = wc.DownloadString(strURLBG);
                            strBGImage = decode64(strRes);

                            Boolean blWebBased = false;
                            if (strBGImage.StartsWith("https://", true, null) || strBGImage.StartsWith("http://", true, null) || strBGImage.StartsWith("ftp://", true, null))
                            {
                                blWebBased = true;
                            }

                            if (blWebBased)
                            {
                                WebClient webDL = new WebClient();
                                Byte[] bImg;
                                bImg = webDL.DownloadData(strBGImage);
                                MemoryStream ms = new MemoryStream(bImg);
                                bgImage = Image.FromStream(ms);
                            }
                            else
                            {
                                bgImage = Image.FromFile(strBGImage);
                            }

                        }
                        catch (Exception exImg)
                        {
                            log(MOD_NAME, exImg.Message);
                            log(MOD_NAME, exImg.StackTrace);
                        }

                        if (blGo)
                        {
                            Thread t = new Thread(new ThreadStart(logInOutTracker));
                            t.IsBackground = true;
                            t.Start();

                            while (true)
                            {
                                log(MOD_NAME, "Creating ALO interface.");
                                frm = new ALOForm(intTimeout, bgImage, this);
                                frm.setLoggedIn(isLoggedIn());
                                frm.ShowDialog();
                                log(MOD_NAME, "ALO interface has died, will respawn in 30 seconds.");
                                try
                                {
                                    Thread.Sleep(30000);
                                }
                                catch { }
                            }

                            
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
                pushMessage("FOG Auto Log Off error:\n" + e.Message);
                log(MOD_NAME, e.Message);
                log(MOD_NAME, e.StackTrace);
            }
            finally
            {
            }
            intStatus = STATUS_TASKCOMPLETE;
            
        }

        private void logInOutTracker()
        {

            Boolean blLogin = isLoggedIn();
            while (frm == null) 
            {
                try
                {
                    log(MOD_NAME, "Tracker thread is waiting for interface.");
                    Thread.Sleep(5000);
                }
                catch
                {
                    
                }
            }

            blLogin = isLoggedIn();
            while (true)
            {
                try
                {
                    Boolean blTmplgin = isLoggedIn();
                    if (blLogin != blTmplgin)
                    {
                        if (frm != null)
                        {
                            frm.setLoggedIn(blTmplgin);
                            blLogin = blTmplgin;
                        }                            
                    }
                }
                catch (Exception e)
                {
                    log(MOD_NAME, e.Message);
                    log(MOD_NAME, e.StackTrace);
                }

                try
                {
                    Thread.Sleep(1000);
                }
                catch { }
            }
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

        public void logOffUser()
        {
            //restartComputer();
            unmanagedExitWindows(ExitWindows.Reboot | ExitWindows.Force);
        }
    }
}
