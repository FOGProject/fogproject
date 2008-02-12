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
                    int intSleep = r.Next(10, 30);
                    log(MOD_NAME, "Sleeping for " + intSleep + " seconds.");
                    System.Threading.Thread.Sleep(intSleep * 1000);
                }
                catch{}


                while (blGo)
                {
                    // process every mac address on the machine
                    // to make sure we get all intended snapins
                    Boolean blInstalled = false;

                    if (alMACs != null)
                    {
                        for (int i = 0; i < alMACs.Count; i++)
                        {
                            try
                            {

                                if (alMACs[i] != null)
                                {

                                    String strMACAddress = (String)alMACs[i];
                                    WebClient web = new WebClient();
                                    String strPathCheckin = strURLPathCheckin + "?mac=" + strMACAddress;
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
                                        }
                                        else if (strAction.StartsWith("#!it"))
                                        {
                                            log(MOD_NAME, "Will NOT perform any snapin tasks because an image task exists for this host.");
                                        }
                                        else if (strAction.StartsWith("#!ns"))
                                        {
                                            // ok, not tasks found
                                        }
                                        else if (strAction.StartsWith("#!ok"))
                                        {
                                            // task exists load meta data, then download file.
                                            if (arData.Length == 7)
                                            {
                                                String strTaskID = arData[1];
                                                String strCreated = arData[2];
                                                String strSnapinName = arData[3];
                                                String strArgs = arData[4];
                                                String strRestart = arData[5];
                                                String strFileName = arData[6];

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

                                                if ( strFileName != null )
                                                {
                                                    strFileName = strFileName.Trim();
                                                    if ( strFileName.StartsWith("SNAPINFILENAME=") ) strFileName = strFileName.Replace("SNAPINFILENAME=", "" );
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

                                                if ( strFileName == null )
                                                    continue;

                                                log(MOD_NAME, "Snapin Found: ");
                                                log(MOD_NAME, "    ID: " + intTaskID);
                                                log(MOD_NAME, "    Name: " + strSnapinName);
                                                log(MOD_NAME, "    Created: " + strCreated);
                                                log(MOD_NAME, "    Args: " + strArgs);
                                                if ( blReboot )
                                                    log(MOD_NAME, "    Reboot: Yes");
                                                else
                                                    log(MOD_NAME, "    Reboot: No");

                                                log(MOD_NAME, "Starting FOG Snapin Download");
                                                String strLocalPath;
                                                try
                                                {
                                                    WebClient download = new WebClient();
                                                    String strLocalDir = AppDomain.CurrentDomain.BaseDirectory + @"tmp";
                                                    if (! Directory.Exists(strLocalDir))
                                                    {
                                                        Directory.CreateDirectory(strLocalDir);
                                                    }
                                                    strLocalPath = strLocalDir + @"\" + strFileName;
                                                    download.DownloadFile(strURLPathDownload + "?mac=" + strMACAddress + "&taskid=" + intTaskID, strLocalPath);
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
                                                            p.StartInfo.FileName = strLocalPath;
                                                            p.StartInfo.Arguments = strArgs;
                                                            p.Start();
                                                            p.WaitForExit();

                                                            log(MOD_NAME, "FOG Snapin Installtion complete.");
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
