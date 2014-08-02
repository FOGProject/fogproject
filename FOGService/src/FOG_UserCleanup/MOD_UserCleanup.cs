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
using System.DirectoryServices;

namespace FOG 
{

    public class UserCleanup : AbstractFOGService
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
        private String strURLUserListing;
        private String strURLModuleStatus;
        private Boolean blGo;

        private const String MOD_NAME = "FOG::UserCleanup";

        public UserCleanup()
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


                    String pre = ini.readSetting("usercleanup", "urlprefix");
                    String post = ini.readSetting("usercleanup", "urlpostfix");
                    if (ip != null && ip.Length > 0 && pre != null && pre.Length > 0 && post != null && post.Length > 0)
                    {
                        strURLUserListing = pre + ip + post;
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
            return "User Cleanup - This module will clean out any stale user account left from services like dynamic local user.";
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
                    int pause = r.Next(10, 20);
                    log(MOD_NAME, "Sleeping for " + pause + " seconds.");
                    System.Threading.Thread.Sleep(pause * 1000);
                }
                catch (Exception)
                { }

                log(MOD_NAME, "Starting user cleanup process...");

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
                            String strPath = strURLModuleStatus + "?mac=" + macList + "&moduleid=usercleanup";
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

                    ArrayList alUsers = new ArrayList();
                    if (blLoop)
                    {
                        Boolean blLgIn = isLoggedIn();
                        
                        log(MOD_NAME, "Determining which users should be protected...");
                        try
                        {
                            WebClient wc = new WebClient();
                            String strRes = wc.DownloadString(strURLUserListing);
                            strRes = strRes.Trim();
                            if (strRes != null)
                            {
                                arLines = strRes.Split('\n');
                                Boolean blOpen = false;
                                Boolean blClose = false;
                                if (arLines != null)
                                {
                                    for (int i = 0; i < arLines.Length; i++)
                                    {
                                        if (!blOpen)
                                        {
                                            // look for opening line
                                            if (arLines[i] != null)
                                            {
                                                if (arLines[i].StartsWith("#!start"))
                                                    blOpen = true;
                                            }
                                        }
                                        else
                                        {
                                            if (arLines[i].StartsWith("#!end"))
                                            {
                                                blClose = true;
                                                break;
                                            }
                                            else
                                            {
                                                if (arLines[i] != null)
                                                {
                                                    String strUser = decode64(arLines[i]);
                                                    if ( strUser != null && strUser != "" )
                                                        alUsers.Add(strUser);
                                                }
                                            }
                                        }
                                    }

                                    if (!blClose)
                                    {
                                        log(MOD_NAME, "Server response was not correctly closed.");
                                        blGo = false;
                                    }

                                    if (alUsers.Count == 0)
                                    {
                                        log(MOD_NAME, "No protected users found, I will assume this is an error and exit.");
                                        blGo = false;
                                    }
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


                        log(MOD_NAME, "Starting user cleanup loop...");
                        Boolean blFirst = true;
                        while (blGo)
                        {
                            Boolean blCurLgIn = isLoggedIn();

                            // if this is the first iteration of the loop
                            // and no one is logged in, then attempt to 
                            // do a cleanup.  This will cleanup users on a reboot
                            if (blFirst && !blCurLgIn)
                            {
                                log(MOD_NAME, "Running cleanup (1st iteration)...");
                                doCleanup(alUsers);
                            }
                            blFirst = false;

                            if (blLgIn != blCurLgIn)
                            {
                                if (!blCurLgIn)
                                {
                                    log(MOD_NAME, "Logout detected, waiting 30 seconds...");
                                    try
                                    {
                                        System.Threading.Thread.Sleep(30000);
                                    }
                                    catch { }
                                    if (! isLoggedIn())
                                    {
                                        log(MOD_NAME, "Cleaning up users...");
                                        doCleanup(alUsers);
                                    }
                                }
                                blLgIn = blCurLgIn;
                            }

                            try
                            {
                                System.Threading.Thread.Sleep(2000);
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

        private void doCleanup(ArrayList alUsers)
        {
            String[] strLocalUsers = getLocalUsers();
            if (strLocalUsers != null && strLocalUsers.Length > 0)
            {
                for (int k = 0; k < strLocalUsers.Length; k++)
                {
                    
                    Boolean blProtected = false;
                    for (int i = 0; i < alUsers.Count; i++)
                    {
                        String strCur = (String)alUsers[i];
                        if (strCur != null)
                        {
                            try
                            {
                                if (strLocalUsers[k].Trim().ToLower().StartsWith(strCur.Trim().ToLower(), true, null) || strCur.ToLower().CompareTo(strLocalUsers[k].ToLower()) == 0)
                                {
                                    blProtected = true;
                                    break;
                                }
                            }
                            catch (Exception exp)
                            {
                                log(MOD_NAME, exp.Message);
                                blProtected = true;
                                break;
                            }
                        }
                    }

                    if (!blProtected)
                    {
                        // remove the user account
                        try
                        {
                            DirectoryEntry localDirectory = new DirectoryEntry("WinNT://" + Environment.MachineName + ",computer");
                            DirectoryEntry singleUser = localDirectory.Children.Find(strLocalUsers[k]);
                            if (singleUser != null)
                            {
                                log(MOD_NAME, "Removing User: " + strLocalUsers[k]);
                                System.Threading.Thread.Sleep(10000);
                                if (!isLoggedIn())
                                    localDirectory.Children.Remove(singleUser);
                                else
                                {
                                    log(MOD_NAME, "Aborting...it looks like a user is logged in!");
                                    return;
                                }
                            }
                        }
                        catch (Exception lex)
                        {
                            log(MOD_NAME, lex.Message);
                            log(MOD_NAME, lex.StackTrace);
                        }
                    }
                    else
                    {
                        log(MOD_NAME, "User: " + strLocalUsers[k] + " is protected.");
                    }
                }
            }
            else
            {
                log(MOD_NAME, "Unable to find any local users, fog is assuming that this is an error, so I will exit.");
            }
        }

        private String[] getLocalUsers()
        {
            ArrayList users = new ArrayList();
            try
            {
                DirectoryEntry localDirectory = new DirectoryEntry("WinNT://" + Environment.MachineName + ",computer");
                DirectoryEntries dirObjects = localDirectory.Children;
                foreach (DirectoryEntry dirEntry in dirObjects)
                {
                    if (dirEntry != null)
                    {
                        if (dirEntry.SchemaClassName == "User")
                        {
                            try
                            {
                                // hard coded protection for the support*, administrator, and admin accounts.
                                if (!dirEntry.Name.StartsWith("admin", false, null) && !dirEntry.Name.StartsWith("support", false, null) && !dirEntry.Name.StartsWith("administrator", false, null))
                                {
                                    users.Add(dirEntry.Name);
                                }
                            }
                            catch (Exception) { }
                        }
                    }
                }
                localDirectory.Close();
            }
            catch (Exception e)
            {
                log(MOD_NAME, e.Message);
                log(MOD_NAME, e.StackTrace);
            }
            
            return (String[])users.ToArray(typeof(String));
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
