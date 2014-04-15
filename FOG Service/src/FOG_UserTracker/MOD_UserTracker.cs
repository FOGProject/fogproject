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

    public class UserTracker : AbstractFOGService
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
        private String strVarData;
        private Boolean blGo;

        private const String MOD_NAME = "FOG::UserTracker";
        private const int SLEEP = 5000;

        public UserTracker()
        {
            intStatus = STATUS_STOPPED;
            blGo = false;
        }

        private Boolean readSettings()
        {
            if (ini != null)
            {
                if (ini.isFileOk())
                {
                    String pre = ini.readSetting("usertracker", "urlprefix");
                    String post = ini.readSetting("usertracker", "urlpostfix");
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

                    strVarData = ini.readSetting("fog_service", "var");
                    if (strVarData != null && strVarData.Length > 0 && ip != null && ip.Length > 0 && pre != null && pre.Length > 0 && post != null && post.Length > 0)
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
                {
                    blGo = true;
                    track();
                }
                else
                    log(MOD_NAME, "Failed to read ini settings.");
            }
            catch
            {
            }
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

        private String prepMacString(string maclist)
        {
            if (maclist != null)
                return maclist.Replace('|', '@');
            return null;
        }

        private String sendMessage(String action, String mac, String user, String date)
        {
            try
            {
                WebClient web = new WebClient();
                String strQuery = strURLPath + "?mac=" + encodeTo64(mac) + "&action=" + encodeTo64(action) + "&user=" + encodeTo64(user) + "&date=" + encodeTo64(date);
                String strData = web.DownloadString(strQuery);
                return strData;
            }
            catch
            {
                return null;
            }
        }

        private String getMySqlDateTime()
        {
            return DateTime.Now.ToString("yyyy-MM-dd HH:mm:ss");
        }

        private Boolean writeJournal(String action, String mac, String user, String date)
        {
            try
            {
                if (!Directory.Exists(strVarData))
                {
                    Directory.CreateDirectory(strVarData);
                }

                if (Directory.Exists(strVarData))
                {
                    String d = getMySqlDateTime();
                    if (date != null)
                        d = date;
                    String output = action + "|" + prepMacString( mac ) + "|" + user + "|" + d;
                    TextWriter txt = new StreamWriter(strVarData + @"\journal.dat", true);
                    txt.WriteLine(encodeTo64(output));
                    txt.Close();
                }
                else
                    log(MOD_NAME, "Unable to write journal, " + strVarData + " doesn't exist!");
            }
            catch (Exception e)
            {
                log(MOD_NAME, e.Message);
                log(MOD_NAME, e.StackTrace);
                return false;
            }
            return false;
        }

        private Boolean removeFirstLines(String file, int numLines)
        {
            try
            {
                if (File.Exists(file))
                {
                    if (numLines > 0)
                    {
                        String strOrig = file + ".001";
                        File.Move(file, strOrig);

                        if (File.Exists(strOrig))
                        {
                            String strLine;
                            StreamReader sr = File.OpenText(strOrig);
                            TextWriter tw = new StreamWriter(file, true);
                            strLine = sr.ReadLine();
                            int count = 0;
                            while (strLine != null)
                            {
                                if (count < numLines)
                                {
                                    count++;
                                }
                                else
                                    tw.WriteLine(strLine);

                                strLine = sr.ReadLine();
                            }
                            tw.Close();
                            sr.Close();

                            File.Delete(strOrig);
                        }
                        else
                        {
                            log(MOD_NAME, "Unable to move file: " + file + " to " + strOrig);
                            return false;
                        }
                    }
                }
            }
            catch (Exception e)
            {
                log(MOD_NAME, e.Message);
                log(MOD_NAME, e.StackTrace);
                return false;
            }

            return false;
        }

        private JournalEntry[] popFirstLines(String file)
        {
            try
            {
                if (File.Exists(file))
                {

                    FileInfo fi = new FileInfo(file);
                    int lines = 1;
                    if (((fi.Length / 1024) / 1024) > 1)
                        lines = 5000;

                    String strLine;
                    StreamReader sr = File.OpenText(file);
                    strLine = sr.ReadLine();

                    if (strLine == null) return null;

                    JournalEntry[] journal = new JournalEntry[lines];
                    int intCurLine =0;
                    while (strLine != null && intCurLine < lines)
                    {
                        String strReal = decode64(strLine);
                        String[] arReal = strReal.Split(new char[] {'|'});
                        if (arReal.Length == 4)
                        {
                            if (arReal[1] != null)
                                arReal[1] = arReal[1].Replace('@', '|');
                            journal[intCurLine] = new JournalEntry(arReal[0], arReal[1], arReal[2], arReal[3]);
                        }
                        else
                        {
                            //log(MOD_NAME, "Removing journal entry, it contains invalid data");
                            journal[intCurLine] = null;
                        }
                        strLine = sr.ReadLine();
                        intCurLine++;
                    }
                    sr.Close();
                    return journal;
                }
            }
            catch (Exception e ) 
            {
                log(MOD_NAME, e.Message);
                log(MOD_NAME, e.StackTrace);
                return null; 
            }
        
            return null;
        }

        private void replayJournal()
        {
            try
            {
                // clean any stale journal files
                if (File.Exists(strVarData + @"\journal.dat.001"))
                {
                    log(MOD_NAME, "Removing Stale Journal File.");
                    File.Delete(strVarData + @"\journal.dat.001");
                }

                JournalEntry[] journal = popFirstLines(strVarData + @"\journal.dat");
                while (journal != null)
                {
                    int numGood = 0;

                    for (int i = 0; i < journal.Length; i++)
                    {
                        if (journal[i] != null)
                        {
                            String action = journal[i].getAction();
                            String mac = journal[i].getMAC();
                            String user = journal[i].getUser();
                            String date = journal[i].getDate();

                            String strData = sendMessage(action, mac, user, date);
                            if (strData != null)
                            {
                                processServerResponse(strData);
                                if (strData.StartsWith("#!ok"))
                                    numGood++;
                            }
                            else
                            {
                                log(MOD_NAME, "Unable to replay journal, no server connection found.");
                                writeJournal(action, mac, user, date);
                            }

                            try
                            {
                                System.Threading.Thread.Sleep(10);
                            }
                            catch
                            {
                            }
                        }
                        else
                        {
                            log(MOD_NAME, "Journal Entry was null!");
                        }
                    }
                    removeFirstLines(strVarData + @"\journal.dat", journal.Length);

                    if (numGood == 0) return;

                    journal = popFirstLines(strVarData + @"\journal.dat");
                }
            }
            catch (Exception e)
            {
                log(MOD_NAME, e.Message);
                log(MOD_NAME, e.StackTrace);
            }
        }

        private void processServerResponse( String strData )
        {
            if (strData.StartsWith("#!db"))
            {
                log(MOD_NAME, "Unable to notify server due to a database error.");
            }
            else if (strData.StartsWith("#!im"))
            {
                log(MOD_NAME, "Unable to notify server because the MAC address was not correctly formated.");
            }
            else if (strData.StartsWith("#!ac"))
            {
                log(MOD_NAME, "Unable to notify server because the action command was invalid.");
            }
            else if (strData.StartsWith("#!nh"))
            {
                log(MOD_NAME, "Unable to notify server because the host isn't present in the FOG database.");
            }
            else if (strData.StartsWith("#!us"))
            {
                log(MOD_NAME, "Unable to notify server because the username is invalid.");
            }
            else if (strData.StartsWith("#!er"))
            {
                log(MOD_NAME, "Unable to notify server due to a general error.");
            }
            else if (strData.StartsWith("#!ok"))
            {
                log(MOD_NAME, "Record processed by server!");
            }
            else
            {
                log(MOD_NAME, "Unhandled Response from server: " + strData);
            }
        }

        private void track()
        {
            try
            {
                log(MOD_NAME, "Starting user tracking process...");
                ArrayList alMACs = getMacAddress();

                String macList = null;
                if (alMACs != null && alMACs.Count > 0)
                {
                    String[] strMacs = (String[])alMACs.ToArray(typeof(String));
                    macList = String.Join("|", strMacs);
                }

                try
                {

                    // get baseline
                    Boolean blIsLoggedIn = isLoggedIn();
                    String strUser = getUserName();

                    if (macList != null)
                    {
                        Boolean blConnectOK = false;
                        String strDta = "";
                        while (!blConnectOK)
                        {
                            try
                            {
                                log(MOD_NAME, "Attempting to connect to fog server...");
                                WebClient wc = new WebClient();
                                String strPath = strURLModuleStatus + "?mac=" + macList + "&moduleid=usertracker";
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

                        if ( blLoop )
                        {

                            // attempt to reply journal on startup
                            replayJournal();

                            // alert server of service startup

                            String strDataStart = sendMessage("START", macList, strUser, null);
                            if (strDataStart == null)
                                writeJournal("START", macList, strUser, null);
                            

                            while (blGo)
                            {
                                try
                                {
                                    Random r = new Random();
                                    if (r.Next(1, 120) == 50)
                                        replayJournal();

                                    String tmpUser = "";
                                    Boolean blNewLoggedIn = isLoggedIn();
                                    if (blNewLoggedIn)
                                        tmpUser = getUserName();

                                    Boolean blStateChange = (blIsLoggedIn != blNewLoggedIn);

                                    if ( blStateChange  && ( ( blNewLoggedIn &&  tmpUser != null && tmpUser.Length > 0) || ! blNewLoggedIn))
                                    {
                                        String action = "";
                                        String user = "";
                                        if (blNewLoggedIn)
                                        {
                                            action = "LOGIN";
                                            user = tmpUser;
                                        }
                                        else
                                        {
                                            action = "LOGOUT";
                                            user = strUser;
                                        }


                                        log(MOD_NAME, "Event: " + action + " for " + user);

                                        String strData = sendMessage(action, macList, user, null);
                                        if (strData != null)
                                        {
                                            strData = strData.Trim();
                                            processServerResponse(strData);

                                            // If last response was OK and the used has logged in attempt to replay journal
                                            if (strData.StartsWith("#!ok"))
                                            {
                                                if ( isLoggedIn() )
                                                    replayJournal();
                                            }
                                        }
                                        else
                                        {
                                            // write journal entry
                                            writeJournal(action, macList, user, null);
                                        }
                                            
                                        sleep(10);
                                        blIsLoggedIn = blNewLoggedIn;

                                        if (!blIsLoggedIn)
                                            strUser = null;
                                        else
                                            strUser = tmpUser;
                                    }
                                }
                                catch (Exception exp)
                                {
                                    log(MOD_NAME, exp.Message);
                                    log(MOD_NAME, exp.StackTrace);
                                }


                                sleep(SLEEP);

                            }
                        }
                    }
                    else
                    {
                        log(MOD_NAME, "Unable to determine MAC address, exiting...");
                    }
                }
                catch { }

            }
            catch (Exception ex)
            {
                log(MOD_NAME, ex.Message);
                log(MOD_NAME, ex.StackTrace);
            }
        }

        private void sleep(int millis)
        {
            try
            {
                System.Threading.Thread.Sleep(millis);
            }
            catch
            {
            }
        }

        public override string mGetDescription()
        {
            return "User Tracker - Trackers User Logins and Logoffs";
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

    public class UserAction
    {
        private String strAction;
        private String strUser;

        public const String LOGIN = "LOGIN";
        public const String LOGOUT = "LOGOUT";
        public const String STARTUP = "START";

        public UserAction(String action, String user)
        {
            strAction = action;
            strUser = user;
        }

        public String getUser() { return strUser; }
        public String getAction() { return strAction; }
    }

    public class JournalEntry
    {
        private String strAction, strMAC, strUser, strDate;

        public JournalEntry(String a, String m, String u, String d)
        {
            strAction = a;
            strMAC = m;
            strUser = u;
            strDate = d;
        }

        public String getAction() { return strAction; }
        public String getMAC() { return strMAC; }
        public String getUser() { return strUser; }
        public String getDate() { return strDate; }
    }
}
