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

    public class SnapinClient : AbstractFOGService
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

        private int intStatus, intPause;
        private String strURLPathCheckin, strURLPathDownload;
        private String strURLModuleStatus;
        private Boolean blGo;

        private const String MOD_NAME = "FOG::SnapinClient";

        public SnapinClient()
        {
            intStatus = STATUS_STOPPED;
            intPause = 400;
            blGo = false;
        }

        private Boolean readSettings()
        {
            if (ini != null)
            {
                if (ini.isFileOk())
                {
                    String preCheckIn = ini.readSetting("snapinclient", "checkinurlprefix");
                    String postCheckIn = ini.readSetting("snapinclient", "checkinurlpostfix");

                    String preDL = ini.readSetting("snapinclient", "downloadurlprefix");
                    String postDL = ini.readSetting("snapinclient", "downloadurlpostfix");

                    String ip = ini.readSetting("fog_service", "ipaddress");

                    if (ip == null || ip.Trim().Length == 0)
                        ip = "fogserver";

                    // get the module status URL 
                    String strPreMS = ini.readSetting("fog_service", "urlprefix");
                    String strPostMS = ini.readSetting("fog_service", "urlpostfix");
                    if (ip != null && strPreMS != null && strPostMS != null)
                        strURLModuleStatus = strPreMS + ip + strPostMS;
                    else
                    {
                        return false;
                    }

                    try
                    {
                        intPause = Int32.Parse( ini.readSetting("snapinclient", "checkintime").Trim() );
                    }
                    catch
                    { }

                    if (ip != null && ip.Length > 0 && preCheckIn != null && preCheckIn.Length > 0 && postCheckIn != null && postCheckIn.Length > 0 && preDL != null && preDL.Length > 0 && postDL != null && postDL.Length > 0)
                    {

                        strURLPathCheckin = preCheckIn + ip + postCheckIn;
                        strURLPathDownload = preDL + ip + postDL;
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
                    startWatching();
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
            return "Snapin Client - Installs snapins on client computers.";
        }

        private void startWatching()
        {
            try
            {
                log(MOD_NAME, "Starting snapin client process...");

                String strCurrentHostName = getHostName();

                ArrayList alMACs = getMacAddress();

                try
                {
                    Random r = new Random();
                    //int intSleep = r.Next(350, 500);	// 0.32
                    int intSleep = r.Next(30, 60);	// 0.33
                    
                    log(MOD_NAME, "Sleeping for " + intSleep + " seconds.");
                    System.Threading.Thread.Sleep(intSleep * 1000);
                }
                catch{}

                while (blGo)
                {
                    // process every mac address on the machine
                    // to make sure we get all intended snapins
                    Boolean blInstalled = false;

                    String macList = null;
                    if (alMACs != null && alMACs.Count > 0)
                    {
                        String[] strMacs = (String[])alMACs.ToArray(typeof(String));
                        macList = String.Join("|", strMacs);
                    }

                    if (macList != null && macList.Length > 0)
                    {
                        try
                        {
                            Boolean blConnectOK = false;
                                String strDta = "";
                                while (!blConnectOK)
                                {
                                    try
                                    {
                                        log(MOD_NAME, "Attempting to connect to fog server...");
                                        WebClient wc = new WebClient();
                                        String strPath = strURLModuleStatus + "?mac=" + macList + "&moduleid=snapin";
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
                                    WebClient web = new WebClient();
                                    String strPathCheckin = strURLPathCheckin + "?mac=" + macList;
                                    String strData = web.DownloadString(strPathCheckin);
                                    if (strData != null)
                                    {
                                        String[] arData = strData.Split('\n');
                                        String strAction = arData[0];
                                        strAction = strAction.Trim();

                                        if (strAction.StartsWith("#!db"))
                                        {
                                            log(MOD_NAME, "Unable to determine snapin status due to a database error.");
                                        }
                                        else if (strAction.StartsWith("#!im"))
                                        {
                                            log(MOD_NAME, "Unable to determine snapin status because the MAC address was not correctly formated.");
                                        }
                                        else if (strAction.StartsWith("#!er"))
                                        {
                                            log(MOD_NAME, "Unable to determine snapin status due to an unknown error.");
                                            log(MOD_NAME, "Maybe MAC isn't registered: " + macList);
                                        }
                                        else if (strAction.StartsWith("#!it"))
                                        {
                                            log(MOD_NAME, "Will NOT perform any snapin tasks because an image task exists for this host.");
                                        }
                                        else if (strAction.StartsWith("#!ns"))
                                        {
                                            // ok, no tasks found
                                            log(MOD_NAME, "No Tasks found for: " + macList);
                                        }
                                        else if (strAction.StartsWith("#!ok"))
                                        {
                                            // task exists load meta data, then download file.

                                            if (arData.Length == 9)
                                            {

                                                String strTaskID = arData[1];
                                                String strCreated = arData[2];
                                                String strSnapinName = arData[3];
                                                String strArgs = arData[4];
                                                String strRestart = arData[5];
                                                String strFileName = arData[6];
                                                String strRunWith = arData[7];
                                                String strRunWithArgs = arData[8];

                                                if (strRunWith != null)
                                                {
                                                    strRunWith = strRunWith.Trim();
                                                    if (strRunWith.StartsWith("SNAPINRUNWITH=")) strRunWith = strRunWith.Replace("SNAPINRUNWITH=", "");
                                                }

                                                if (strRunWithArgs != null)
                                                {
                                                    strRunWithArgs = strRunWithArgs.Trim();
                                                    if (strRunWithArgs.StartsWith("SNAPINRUNWITHARGS=")) strRunWithArgs = strRunWithArgs.Replace("SNAPINRUNWITHARGS=", "");
                                                }

                                                if (strTaskID != null)
                                                {
                                                    strTaskID = strTaskID.Trim();
                                                    if (strTaskID.StartsWith("JOBTASKID=")) strTaskID = strTaskID.Replace("JOBTASKID=", "");

                                                }

                                                if (strCreated != null)
                                                {
                                                    strCreated = strCreated.Trim();
                                                    if (strCreated.StartsWith("JOBCREATION=")) strCreated = strCreated.Replace("JOBCREATION=", "");

                                                }

                                                if (strSnapinName != null)
                                                {
                                                    strSnapinName = strSnapinName.Trim();
                                                    if (strSnapinName.StartsWith("SNAPINNAME=")) strSnapinName = strSnapinName.Replace("SNAPINNAME=", "");
                                                }

                                                if (strArgs != null)
                                                {
                                                    strArgs = strArgs.Trim();
                                                    if (strArgs.StartsWith("SNAPINARGS=")) strArgs = strArgs.Replace("SNAPINARGS=", "");
                                                }

                                                if (strRestart != null)
                                                {
                                                    strRestart = strRestart.Trim();
                                                    if (strRestart.StartsWith("SNAPINBOUNCE=")) strRestart = strRestart.Replace("SNAPINBOUNCE=", "");
                                                }

                                                if (strFileName != null)
                                                {
                                                    strFileName = strFileName.Trim();
                                                    if (strFileName.StartsWith("SNAPINFILENAME=")) strFileName = strFileName.Replace("SNAPINFILENAME=", "");
                                                }
                                                // check all the values we got back and make sure the make sense.

                                                int intTaskID = -1;
                                                Boolean blReboot = (strRestart == "1");

                                                try
                                                {
                                                    intTaskID = int.Parse(strTaskID);
                                                }
                                                catch (Exception exNum)
                                                {
                                                    log(MOD_NAME, "Failed to obtain a valid Task ID Number.");
                                                    log(MOD_NAME, exNum.Message);
                                                    log(MOD_NAME, exNum.StackTrace);
                                                    continue;
                                                }

                                                if (strFileName == null)
                                                    continue;

                                                log(MOD_NAME, "Snapin Found: ");
                                                log(MOD_NAME, "    ID: " + intTaskID);
                                                log(MOD_NAME, "    RunWith: " + strRunWith);
                                                log(MOD_NAME, "    RunWithArgs: " + strRunWithArgs);
                                                log(MOD_NAME, "    Name: " + strSnapinName);
                                                log(MOD_NAME, "    Created: " + strCreated);
                                                log(MOD_NAME, "    Args: " + strArgs);
                                                if (blReboot)
                                                    log(MOD_NAME, "    Reboot: Yes");
                                                else
                                                    log(MOD_NAME, "    Reboot: No");

                                                log(MOD_NAME, "Starting FOG Snapin Download");
                                                String strLocalPath;
                                                try
                                                {
                                                    WebClient download = new WebClient();
                                                    String strLocalDir = AppDomain.CurrentDomain.BaseDirectory + @"tmp";
                                                    if (!Directory.Exists(strLocalDir))
                                                    {
                                                        Directory.CreateDirectory(strLocalDir);
                                                    }
                                                    strLocalPath = strLocalDir + @"\" + strFileName;
                                                    download.DownloadFile(strURLPathDownload + "?mac=" + macList + "&taskid=" + intTaskID, strLocalPath);
                                                }
                                                catch (Exception exDl)
                                                {
                                                    log(MOD_NAME, "Failed to download file.");
                                                    log(MOD_NAME, exDl.Message);
                                                    log(MOD_NAME, exDl.StackTrace);
                                                    continue;
                                                }

                                                // check that we have a file.


                                                if (File.Exists(strLocalPath))
                                                {
                                                    try
                                                    {
                                                        FileInfo fi = new FileInfo(strLocalPath);
                                                        if (fi.Length > 0)
                                                        {
                                                            log(MOD_NAME, "Download complete.");
                                                            // Great! run the snapin

                                                            log(MOD_NAME, "Starting FOG Snapin Installation.");

                                                            Process p = new Process();
                                                            p.StartInfo.CreateNoWindow = true;
                                                            p.StartInfo.UseShellExecute = false;
                                                            p.StartInfo.WindowStyle = ProcessWindowStyle.Hidden;
                                                            if (strRunWith != null && strRunWith.Length > 0)
                                                            {
                                                                String args = "";
                                                                if (strRunWithArgs != null && strRunWithArgs.Length > 0)
                                                                {
                                                                    args = " " + strRunWithArgs + " ";
                                                                }
                                                                p.StartInfo.FileName = Environment.ExpandEnvironmentVariables(strRunWith);

                                                                p.StartInfo.Arguments = args + " \"" + strLocalPath + "\" " + strArgs;
                                                            }
                                                            else
                                                            {
                                                                p.StartInfo.FileName = strLocalPath;
                                                                p.StartInfo.Arguments = strArgs;
                                                            }

                                                            try
                                                            {
                                                                p.Start();
                                                                p.WaitForExit();
                                                                log(MOD_NAME, "FOG Snapin Installtion complete.");

                                                                String strReturnCode = p.ExitCode.ToString();
                                                                log(MOD_NAME, "Installation returned with code: " + strReturnCode);
                                                                String strURLReturn = strPathCheckin + "&taskid=" + strTaskID + "&exitcode=" + strReturnCode;
                                                                WebClient w = new WebClient();
                                                                w.DownloadString(strURLReturn);
                                                            }
                                                            catch (Exception exp)
                                                            {
                                                                log(MOD_NAME, exp.Message);
                                                                log(MOD_NAME, exp.StackTrace);
                                                                String strURLReturn = strPathCheckin + "&taskid=" + strTaskID + "&exitcode=-1&exitdesc=" + encodeTo64(exp.Message);
                                                                try
                                                                {
                                                                    WebClient w = new WebClient();
                                                                    web.DownloadString(strURLReturn);
                                                                }
                                                                catch { }
                                                            }



                                                            String strRebootMsg = "";
                                                            if (blReboot)
                                                                strRebootMsg = "  This computer will restart very soon, please save all data!";
                                                            pushMessage("FOG Snapin, " + strSnapinName + " has been installed.  " + strRebootMsg);
                                                            File.Delete(strLocalPath);

                                                            if (blReboot)
                                                            {
                                                                try
                                                                {
                                                                    log(MOD_NAME, "Snapin requires system restart, restarting computer.");
                                                                    System.Threading.Thread.Sleep(25000);
                                                                    restartComputer();
                                                                }
                                                                catch { }
                                                            }

                                                            blInstalled = true;

                                                        }
                                                        else
                                                        {
                                                            log(MOD_NAME, "Download Failed; Zero size file.");
                                                        }
                                                    }
                                                    catch (Exception exFle)
                                                    {
                                                        log(MOD_NAME, exFle.Message);
                                                        log(MOD_NAME, exFle.StackTrace);
                                                    }


                                                }
                                                else
                                                {
                                                    log(MOD_NAME, "Download Failed; File not found.");
                                                }

                                            }
                                            else
                                            {
                                                log(MOD_NAME, "A snapin task was found but, not all the meta data was sent.  Meta data length: " + arData.Length + " Required: 7.");
                                            }
                                        }
                                    }
                                }
                        }
                        catch (Exception e)
                        {
                            log(MOD_NAME, e.Message);
                            log(MOD_NAME, e.StackTrace);
                        }
                    }


                    if (!blInstalled)
                    {
                        // If a snapin was installed, then don't wait because there may be another waiting.
                        try
                        {
                            System.Threading.Thread.Sleep(intPause * 1000);
                        }
                        catch { }
                    }
                }
            }
            catch (Exception e)
            {
                pushMessage("FOG Snapin error:\n" + e.Message);
                log(MOD_NAME, e.Message);
                log(MOD_NAME, e.StackTrace);
            }
            finally
            {
            }
            intStatus = STATUS_TASKCOMPLETE;
            
        }

        private string encodeTo64(string strEncode)
        {
            try
            {
                byte[] b = System.Text.ASCIIEncoding.ASCII.GetBytes(strEncode);
                return System.Convert.ToBase64String(b);
            }
            catch
            {
                return "";
            }
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
