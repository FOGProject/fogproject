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
using System.Reflection;
using System.Security.Cryptography;

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

        private int intStatus;
        private String strURL;
        private String strRoot;
        private String strURLModuleStatus;

        private const String MOD_NAME = "FOG::ClientUpdater";

        public SnapinClient()
        {
            intStatus = STATUS_STOPPED;
        }

        private Boolean readSettings()
        {
            if (ini != null)
            {
                if (ini.isFileOk())
                {
                    String strURLPrefix = ini.readSetting("updater", "urlprefix");
                    String strURLPostfix = ini.readSetting("updater", "urlpostfix");
                    strRoot = ini.readSetting("fog_service", "root");
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

                    if ( strRoot != null && ip != null && ip.Length > 0 && strURLPrefix != null && strURLPostfix != null )
                    {

                        strURL = strURLPrefix + ip + strURLPostfix;
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
                    startUpdate();
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
            return "Client Updater - Updates the FOG service.";
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

        private String[] getAllPublishedModules()
        {
            ArrayList alFileList = new ArrayList();
            String strPathCheckin = strURL + "?action=list";
            WebClient webClient = new WebClient();
            String strData = webClient.DownloadString(strPathCheckin);
            if (strData != null && strData.Length > 0)
            {
                if (strData == "#!er")
                    log(MOD_NAME, "General Error in server response");
                else if (strData == "#!db")
                    log(MOD_NAME, "Database Error in server response ");
                else
                {
                    strData = strData.Trim();
                    String[] arList = strData.Split(new char[] { '\n' });
                    for (int i = 0; i < arList.Length; i++)
                    {
                        if (arList[i] != null)
                        {
                            String tmp = decode64(arList[i].Trim());
                            if (tmp != null) alFileList.Add(tmp);
                        }
                    }
                    return (String[])alFileList.ToArray(typeof(String));
                }
            }
            else
            {
                log(MOD_NAME, "Zero byte response returned");
            }
            return null;
        }

        private String getFileHash(String file)
        {
            if (File.Exists(file))
            {
                StringBuilder sBuilder = new StringBuilder();
                MD5 md5 = new MD5CryptoServiceProvider();
                byte[] bytes = File.ReadAllBytes(file);
                byte[] result = md5.ComputeHash(bytes);
                for (int o = 0; o < result.Length; o++)
                {
                    sBuilder.Append(result[o].ToString("x2"));
                }
                return sBuilder.ToString();
            }
            return "";
        }

        private Boolean downloadAndDeploy(String localFile)
        {
            try
            {
                String tmpDir = strRoot + @"\tmp";
                if (!Directory.Exists(tmpDir))
                {
                    Directory.CreateDirectory(tmpDir);
                }

                if (Directory.Exists(tmpDir))
                {
                    FileInfo fleInfo = new FileInfo(localFile);
                    
                    
                    log(MOD_NAME, "Checking Status : " + fleInfo.Name);
                    
                    String strData = "-1";
                    String strLocalHash = "";
                    
                    String strFileNameBase64 = encodeTo64(fleInfo.Name);
                    
                    strLocalHash = getFileHash(localFile); 
                    String strPathCheckin = strURL + "?action=ask&file=" + strFileNameBase64;
                    Boolean blConnectOK = false;
                    WebClient webClient = null;

                    while (!blConnectOK)
                    {
                        try
                        {
                            webClient = new WebClient();
                            strData = webClient.DownloadString(strPathCheckin);
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

                    if (strData != null && strData.Length > 0)
                    {
                            if (strData == "#!nf")
                            {
                                log(MOD_NAME, "No update found on server for " + fleInfo.Name );
                            }
                            else if ( strData == "#!er" )
                                log(MOD_NAME, "General Error in server response" );
                            else if ( strData == "#!db" )
                                log(MOD_NAME, "Database Error in server response " );
                            else
                            {
                                String strServerHash = strData.Trim();
                                if (strServerHash.ToLower() != strLocalHash.ToLower())
                                {
                                    log(MOD_NAME, "File: " + fleInfo.Name + " requires an update.");
                                    
                                    String strPathDownload = strURL + "?action=get&file=" + strFileNameBase64;
                                    
                                    webClient = new WebClient();
                                    webClient.DownloadFile(strPathDownload, tmpDir + @"\" + fleInfo.Name);
                                    if (File.Exists(tmpDir + @"\" + fleInfo.Name))
                                    {
                                        if (getFileHash(tmpDir + @"\" + fleInfo.Name).ToLower() == strServerHash.ToLower())
                                        {
                                            File.Delete(localFile + ".backup");
                                            if (File.Exists(localFile))
                                                File.Move(localFile, localFile + ".backup");
                                            File.Move(tmpDir + @"\" + fleInfo.Name, localFile);

                                            if (File.Exists(localFile))
                                            {
                                                File.Delete(localFile + ".backup");
                                                log(MOD_NAME, "File: " + fleInfo.Name + " upgrade complete.");
                                                return true;
                                            }
                                            else
                                            {
                                                File.Move(localFile + ".backup", localFile);
                                                log(MOD_NAME, "File: " + fleInfo.Name + " upgrade failed.");
                                            }
                                        }
                                        else
                                        {
                                            log(MOD_NAME, "File: " + tmpDir + @"\" + fleInfo.Name + " hash doesn't match server hash!");
                                            File.Delete(tmpDir + @"\" + fleInfo.Name);
                                        }
                                    }
                                    else
                                        log(MOD_NAME, "File download failed for " + fleInfo.Name);
                                }
                                else
                                {
                                    log(MOD_NAME, "File: " + fleInfo.Name + " does not need an update.");
                                }
                            }
                    }
                    else
                    {
                        log(MOD_NAME, "Zero byte response returned");
                    }
                }
            }
            catch( Exception e )
            {
                log(MOD_NAME, e.Message);
                log(MOD_NAME, e.StackTrace);
                //log(MOD_NAME, e.ToString());
            }
            return false;
        }

        private void startUpdate()
        {
            try
            {
                log(MOD_NAME, "Starting client update process...");

                try
                {
                    Random r = new Random();
                    int intSleep = r.Next(60, 500);
                    log(MOD_NAME, "Sleeping for " + intSleep + " seconds.");
                    System.Threading.Thread.Sleep(intSleep * 1000);
                }
                catch{}

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
                                String strPath = strURLModuleStatus + "?mac=" + macList + "&moduleid=clientupdater";
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
                            String[] files = Directory.GetFiles(strRoot);
                            if (files != null && files.Length > 0)
                            {
                                for (int i = 0; i < files.Length; i++)
                                {
                                    if (files[i] != null)
                                    {
                                        if (files[i].EndsWith(".dll"))
                                        {
                                            try
                                            {
                                                byte[] buffer = File.ReadAllBytes(files[i]);
                                                Assembly assemb = Assembly.Load(buffer);
                                                if (assemb != null)
                                                {
                                                    Type[] type = assemb.GetTypes();
                                                    for (int z = 0; z < type.Length; z++)
                                                    {
                                                        Object module = Activator.CreateInstance(type[z]);
                                                        Assembly abstractA = Assembly.LoadFrom(strRoot + @"\" + @"AbstractFOGService.dll");
                                                        Type t = abstractA.GetTypes()[0];
                                                        if (module.GetType().IsSubclassOf(t))
                                                        {
                                                            downloadAndDeploy(files[i]);
                                                            t = null;
                                                            abstractA = null;
                                                            module = null;
                                                        }
                                                    }
                                                }
                                            }
                                            catch
                                            {
                                            }
                                        }
                                    }
                                }

                                // check if the config file needs an update
                                String strConfig = strRoot + @"\etc\config.ini";
                                downloadAndDeploy(strConfig);

                                // check for new modules posted
                                String[] arModules = getAllPublishedModules();
                                ArrayList alNewMods = new ArrayList();


                                if (arModules != null)
                                {
                                    for (int i = 0; i < arModules.Length; i++)
                                    {
                                        Boolean blExists = false;
                                        for (int z = 0; z < files.Length; z++)
                                        {
                                            FileInfo fInfo = new FileInfo(files[z]);
                                            if (fInfo != null)
                                            {
                                                if (fInfo.Name.ToLower() == arModules[i].ToLower())
                                                {
                                                    blExists = true;
                                                    break;
                                                }
                                            }
                                        }

                                        if (!blExists)
                                        {
                                            alNewMods.Add(arModules[i]);
                                        }
                                    }
                                }

                                // download and install new files
                                String[] arNewFiles = (String[])alNewMods.ToArray(typeof(String));
                                log(MOD_NAME, arNewFiles.Length + " new modules found!");
                                for (int i = 0; i < arNewFiles.Length; i++)
                                {
                                    downloadAndDeploy(strRoot + @"\" + arNewFiles[i]);
                                }

                            }
                            else
                                log(MOD_NAME, "No files found to update.");
                        }
                    }
                    else
                        log(MOD_NAME, "No valid mac address(es) found for this host.");
                }
                catch (Exception e)
                {
                    log(MOD_NAME, e.Message);
                    log(MOD_NAME, e.StackTrace);
                }    
            }
            catch (Exception e)
            {
                pushMessage("FOG Client updater error:\n" + e.Message);
                log(MOD_NAME, e.Message);
                log(MOD_NAME, e.StackTrace);
            }
            finally
            {
            }
            intStatus = STATUS_TASKCOMPLETE;
            log(MOD_NAME, "Client update will be applied during next service startup.");
            log(MOD_NAME, "Client update process complete, exiting...");
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
