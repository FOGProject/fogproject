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

    public class DirCleaner : AbstractFOGService
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
        private String strURLDirListing;
        private String strURLModuleStatus;
        private Boolean blGo;

        private const String MOD_NAME = "FOG::DirCleaner";

        public DirCleaner()
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


                    String pre = ini.readSetting("dircleaner", "urlprefix");
                    String post = ini.readSetting("dircleaner", "urlpostfix");
                    if (ip != null && ip.Length > 0 && pre != null && pre.Length > 0 && post != null && post.Length > 0)
                    {
                        strURLDirListing = pre + ip + post;
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
            return "Directory Cleaner - This module will clean out a list of directories on user log off.";
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


                log(MOD_NAME, "Starting directory cleaning process...");

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

               
                // First check and see if the module is active
                //
                Boolean blLoop = false;
                if (macList != null && macList.Length > 0)
                {
                    Boolean blConnectOK = false;
                    String strData = "";
                    while (!blConnectOK)
                    {
                        try
                        {
                            log(MOD_NAME, "Attempting to connect to fog server...");
                            WebClient wc = new WebClient();
                            String strPath = strURLModuleStatus + "?mac=" + macList + "&moduleid=dircleanup";
                            strData = wc.DownloadString(strPath);
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
                        

                        log(MOD_NAME, "Determining which directories should be cleaned...");
                        try
                        {
                            WebClient wc = new WebClient();
                            String strRes = wc.DownloadString(strURLDirListing);
                            strRes = strRes.Trim();
                            if (strRes != null)
                            {
                                arLines = strRes.Split('\n');
                                if (arLines.Length == 0 )
                                {
                                    log(MOD_NAME, "No directories are configured to be cleaned.");
                                    blGo = false;
                                }
                                else if (arLines.Length == 1 && arLines[0].Trim() == "")
                                {
                                    log(MOD_NAME, "No directories are configured to be cleaned.");
                                    blGo = false;
                                }
                                else
                                {
                                    log(MOD_NAME, arLines.Length + " directories located");
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


                        log(MOD_NAME, "Starting directory cleaning loop...");
                        while (blGo)
                        {
                            Boolean blCurLgIn = isLoggedIn();

                            if (blLgIn != blCurLgIn)
                            {
                                if (!blCurLgIn)
                                {
                                    log(MOD_NAME, "Logout detected, taking action...");
                                    for (int i = 0; i < arLines.Length; i++)
                                    {
                                        String strCur = decode64(arLines[i]);
                                        if (strCur != null)
                                        {
                                            if ( Directory.Exists( strCur ) )
                                            {
                                                if (recursiveDelete(strCur))
                                                    log(MOD_NAME, strCur + " was removed.");
                                                else
                                                    log(MOD_NAME, strCur + " failed to remove.");
                                          
                                            }
                                        }
                                    }
                                }
                                blLgIn = blCurLgIn;
                            }

                            try
                            {
                                System.Threading.Thread.Sleep(1000);
                            }
                            catch (Exception )
                            {

                            }
                        }
                        log(MOD_NAME, "Module has finished work and will now exit.");
                    }
                }
                else
                {
                    log(MOD_NAME, "Unable to continue, MAC is null!");
                }

            }
            catch (Exception e)
            {
                pushMessage("FOG Directory Cleaner error:\n" + e.Message);
                log(MOD_NAME, e.Message);
                log(MOD_NAME, e.StackTrace);
            }
            finally
            {
            }
            intStatus = STATUS_TASKCOMPLETE;
            
        }

        private Boolean recursiveDelete( String strRoot )
        {
            try
            {
                if (Directory.Exists(strRoot))
                {
                    String[] arFiles = Directory.GetFiles(strRoot);
                    for (int i = 0; i < arFiles.Length; i++)
                    {
                        if (File.Exists(arFiles[i]))
                        {
                            try
                            {
                                File.SetAttributes(arFiles[i], FileAttributes.Normal);
                                File.Delete(arFiles[i]);
                            }
                            catch (Exception exF)
                            {
                                log(MOD_NAME, "Error removing: " + arFiles[i]);
                                log(MOD_NAME, exF.Message);
                            }
                        }
                    }

                    String[] arDirs = Directory.GetDirectories(strRoot);
                    for (int i = 0; i < arDirs.Length; i++)
                    {
                        if (Directory.Exists(arDirs[i]))
                        {
                            recursiveDelete(arDirs[i]);
                            Directory.Delete(arDirs[i]);
                        }
                    }
                }
                else
                    return false;
            }
            catch (Exception e)
            {
                log(MOD_NAME, e.Message);
                log(MOD_NAME, e.StackTrace);
                return false;
            }
            return true;
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
}
