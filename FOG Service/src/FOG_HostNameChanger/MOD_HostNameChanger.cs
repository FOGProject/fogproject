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
    public class HostNameChanger : AbstractFOGService
    {
        [DllImport("user32.dll")] private static extern bool SetForegroundWindow(IntPtr hWnd);
        [DllImport("user32.dll")] private static extern bool ShowWindowAsync(IntPtr hWnd, int nCmdShow);
        [DllImport("user32.dll")] private static extern bool IsIconic(IntPtr hWnd);
        [DllImport("netapi32.dll", CharSet=CharSet.Unicode)] private static extern int NetJoinDomain( string lpServer, string lpDomain, string lpAccountOU, string lpAccount, string lpPassword, JoinOptions NameType );
        [DllImport("netapi32.dll", CharSet=CharSet.Unicode)] private static extern int NetUnjoinDomain(string lpServer, string lpAccount, string lpPassword, UnJoinOptions fUnjoinOptions);

        [Flags]
        public enum UnJoinOptions
        {
            NONE = 0x00000000,
            NETSETUP_ACCOUNT_DELETE = 0x00000004
        }

        [Flags]
        public enum JoinOptions
        {
            NETSETUP_JOIN_DOMAIN = 0x00000001,
            NETSETUP_ACCT_CREATE = 0x00000002,
            NETSETUP_ACCT_DELETE = 0x00000004,
            NETSETUP_WIN9X_UPGRADE = 0x00000010,
            NETSETUP_DOMAIN_JOIN_IF_JOINED = 0x00000020,
            NETSETUP_JOIN_UNSECURE = 0x00000040,
            NETSETUP_MACHINE_PWD_PASSED = 0x00000080,
            NETSETUP_DEFER_SPN_SET = 0x10000000
        }

        public struct NERR
        {
            public const int Success = 0;
            public const int AccessDenied = 5;
            public const int BadNetPath = 53;
            public const int WrongPassword = 1323;
            public const int UnknownDevDir = 2116;
            public const int TooManyItems = 2121;
            public const int RemoteErr = 2127;
            public const int NetworkError = 2136;
            public const int WkstaInconsistentState = 2137;
            public const int WkstaNotStarted = 2138;
            public const int BrowserNotStarted = 2139;
            public const int InternalError = 2140;
            public const int BadTransactConfig = 2141;
            public const int InvalidAPI = 2142;
            public const int BadEventName = 2143;
            public const int DupNameReboot = 2144;
            public const int JobNotFound = 2151;
            public const int DestNotFound = 2152;
            public const int DestExists = 2153;
            public const int NotInDispatchTbl = 2192;
            public const int AlreadyLoggedOn = 2200;
            public const int NotLoggedOn = 2201;
            public const int BadUsername = 2202;
            public const int BadPassword = 2203;
            public const int LogonDomainExists = 2216;
            public const int NonValidatedLogon = 2217;
            public const int ACFNotFound = 2219;
            public const int GroupNotFound = 2220;
            public const int UserNotFound = 2221;
            public const int ResourceNotFound = 2222;
            public const int UserLogon = 2231;
            public const int AccountUndefined = 2238;
            public const int AccountExpired = 2239;
            public const int InvalidWorkstation = 2240;
            public const int InvalidLogonHours = 2241;
            public const int PasswordExpired = 2242;
            public const int PasswordCantChange = 2243;
            public const int PasswordHistConflict = 2244;
            public const int NoComputerName = 2270;
            public const int NameNotFound = 2273;
            public const int AlreadyForwarded = 2274;
            public const int DelComputerName = 2278;
            public const int LocalForward = 2279;
            public const int GrpMsgProcessor = 2280;
            public const int PausedRemote = 2281;
            public const int BadReceive = 2282;
            public const int NameInUse = 2283;
            public const int MsgNotStarted = 2284;
            public const int NotLocalName = 2285;
            public const int NoForwardName = 2286;
            public const int RemoteFull = 2287;
            public const int InvalidDevice = 2294;
            public const int MultipleNets = 2300;
            public const int NotLocalDomain = 2320;
            public const int BadDev = 2341;
            public const int InvalidComputer = 2351;
            public const int MaxLenExceeded = 2354;
            public const int BadDest = 2382;
            public const int ShareNotFound = 2392;
            public const int AcctLimitExceeded = 2434;
            public const int DCNotFound = 2453;
            public const int LogonTrackingError = 2454;
            public const int NetlogonNotStarted = 2455;
            public const int TimeDiffAtDC = 2457;
            public const int PasswordMismatch = 2458;
            public const int TooManyConnections = 2465;
            public const int NoAlternateServers = 2467;
            public const int RemoteBootFailed = 2503;
            public const int BadFileCheckSum = 2504;
            public const int NoRplBootSystem = 2505;
            public const int RplLoadrNetBiosErr = 2506;
            public const int BrowserConfiguredToNotRun = 2550;
            public const int SetupAlreadyJoined = 2691;
            public const int SetupNotJoined = 2692;
            public const int SetupDomainController = 2693;
            public const int DefaultJoinRequired = 2694;
            public const int InvalidWorkgroupName = 2695;
            public const int NameUsesIncompatibleCodePage = 2696;
            public const int ComputerAccountNotFound = 2697;
            public const int PersonalSku = 2698;
            public const int PasswordMustChange = 2701;
            public const int AccountLockedOut = 2702;
            public const int PasswordTooLong = 2703;
            public const int PasswordNotComplexEnough = 2704;
            public const int PasswordFilterError = 2705;
        }

        /*
         * Below is the PASSKEY you should change if you want to make your FOG installion more secure!
         * Just remember to change the PASSKEY in the config file of the application that you use to 
         * encrypt the passwords!
         */

        private const String PASSKEY = "FOG-OpenSource-Imaging";


        /*    / \
         *   / | \
         *     |
         *     |
         *     |
         *     |
         * 
         */

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
        private String strDomain;
        private String strUser;
        private String strPass;
        private String strOrgUnit;

        private Boolean isDefaultMode = true;

        private const String MOD_NAME = "FOG::HostnameChanger";

        public HostNameChanger()
        {
            intStatus = STATUS_STOPPED;
        }

        private Boolean readSettings()
        {
            if (ini != null)
            {
                if (ini.isFileOk())
                {
                    String pre = ini.readSetting("hostname_changer", "urlprefix");
                    String post = ini.readSetting("hostname_changer", "urlpostfix");
                    String ip = ini.readSetting("fog_service", "ipaddress");

                    String strPreMS = ini.readSetting("fog_service", "urlprefix");
                    String strPostMS = ini.readSetting("fog_service", "urlpostfix");

                    if (ip == null || ip.Trim().Length == 0 )
                        ip = "fogserver";

                    if ( strPreMS != null && strPostMS != null)
                        strURLModuleStatus = strPreMS + ip + strPostMS;
                    else
                    {
                        return false;
                    }

                    if (ip != null && ip.Length > 0 && pre != null && pre.Length > 0 && post != null && post.Length > 0)
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
                if ( readSettings() )
                    changeHostName();
                else
                    log(MOD_NAME, "Failed to read ini settings.");
            }
            catch
            {
            }
        }

        public override string mGetDescription()
        {
            return "Hostname Changer - Changes the computer's hostname to match the FOG database and joins the host to Active Directory.";
        }

        private void doDefaultMode( string newname)
        {
            RegistryKey regKey = null;
            log(MOD_NAME, "Using default fog method.");
            regKey = Registry.LocalMachine.OpenSubKey(@"SYSTEM\CurrentControlSet\Services\Tcpip\Parameters", true);
            regKey.SetValue("NV Hostname", newname);
            regKey = Registry.LocalMachine.OpenSubKey(@"SYSTEM\CurrentControlSet\Control\ComputerName\ActiveComputerName", true);
            regKey.SetValue("ComputerName", newname);
            regKey = Registry.LocalMachine.OpenSubKey(@"SYSTEM\CurrentControlSet\Control\ComputerName\ComputerName", true);
            regKey.SetValue("ComputerName", newname);

            log(MOD_NAME, "Computer is about to restart.");
            pushMessage("This computer's hostname has been updated and will reboot shortly.");
            try
            {
                System.Threading.Thread.Sleep(20000);
            }
            catch { }

            restartComputer();
        }

        private void changeHostName()
        {
            try
            {
                if (System.Environment.OSVersion.Version.Major != 5 && System.Environment.OSVersion.Version.Major != 6 && System.Environment.OSVersion.Version.Major != 7)
                {
                    log(MOD_NAME, "This module has only been tested on Windows XP, Vista, and Windows 7!" );
                }

                log(MOD_NAME, "Starting hostname change process...");
                Random r = new Random();
                int intsleep = r.Next(1, 10);
                log(MOD_NAME, "Yielding to other subservices for " + intsleep + " seconds.");
                try
                {
                    System.Threading.Thread.Sleep(intsleep * 1000);
                }
                catch { }

                String strCurrentHostName = getHostName();
                String strHostName = null;
                ArrayList alMACs = getMacAddress();

                String macList = null;
                if (alMACs != null && alMACs.Count > 0)
                {
                    String[] strMacs = (String[])alMACs.ToArray(typeof(String));
                    macList = String.Join("|", strMacs);
                }

                if (macList != null && macList.Length > 0)
                {
                    if (strCurrentHostName != null && strCurrentHostName.Length > 0)
                    {
                        Boolean blConnectOK = false;
                        String strDta = "";
                        while (!blConnectOK)
                        {
                            try
                            {
                                log(MOD_NAME, "Attempting to connect to fog server...");
                                WebClient wc = new WebClient();
                                String strPath = strURLModuleStatus + "?mac=" + macList + "&moduleid=hostnamechanger";
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
                            String strData = web.DownloadString(strURLPath + "?mac=" + macList);
                            strData = strData.Trim();
                            if (strData != null)
                            {
                                String[] arData = strData.Split('\n');
                                String strHostResults = arData[0];
                                if (strHostResults.StartsWith("#!OK=", true, null))
                                {
                                    if (arData.Length == 5 || arData.Length == 6)
                                    {
                                        strHostName = strHostResults.Remove(0, 5);
                                        String strUseAD = arData[1];
                                        String strD = arData[2];
                                        String strOU = arData[3];
                                        String strU = arData[4];
                                        String strP = arData[5];
										
                                        if(arData.Length == 6) {
	                                        String strKey = arData[6];
											if (strKey != null)
											{
												strKey = strKey.Trim();
												if (strKey.StartsWith("#Key="))
												{
													strKey = strKey.Replace("#Key=", "");
													Process scriptProc = new Process();
													scriptProc.StartInfo.FileName = @"cscript";
													scriptProc.StartInfo.Arguments =@"//B //Nologo c:\windows\system32\slmgr.vbs /ipk " + strKey;
													scriptProc.StartInfo.WindowStyle = ProcessWindowStyle.Hidden;
													scriptProc.Start();
													scriptProc.WaitForExit();
													scriptProc.Close();
													scriptProc.StartInfo.Arguments =@"//B //Nologo c:\windows\system32\slmgr.vbs /ato";
													scriptProc.Start();
													scriptProc.WaitForExit();
													scriptProc.Close();
												}
											}
                                        }
                                        
                                        if (strUseAD != null)
                                        {
                                            strUseAD = strUseAD.Trim();
                                            if (strUseAD.StartsWith("#AD=")) strUseAD = strUseAD.Replace("#AD=", "");
                                        }

                                        if (strD != null)
                                        {
                                            strD = strD.Trim();
                                            if (strD.StartsWith("#ADDom=")) strD = strD.Replace("#ADDom=", "");
                                        }

                                        if (strOU != null)
                                        {
                                            strOU = strOU.Trim();
                                            if (strOU.StartsWith("#ADOU=")) strOU = strOU.Replace("#ADOU=", "");
                                        }

                                        if (strU != null)
                                        {
                                            strU = strU.Trim();
                                            if (strU.StartsWith("#ADUser=")) strU = strU.Replace("#ADUser=", "");
                                        }

                                        if (strP != null)
                                        {
                                            strP = strP.Trim();
                                            if (strP.StartsWith("#ADPass=")) strP = strP.Replace("#ADPass=", "");
                                        }

                                        if (strUseAD == "1")
                                        {
                                            log(MOD_NAME, "AD mode requested, confirming settings.");
                                            if (strD != null)
                                            {
                                                strDomain = strD;
                                                if (strOU != null)
                                                {
                                                    strOrgUnit = strOU;
                                                }

                                                if (strU != null)
                                                {

                                                    strUser = strU;
                                                    if (strP != null && strP != "")
                                                        strPass = new FOGCrypt(PASSKEY).decryptHex(strP);
                                                    else
                                                        strPass = "";

                                                    isDefaultMode = false;
                                                }
                                                else
                                                {
                                                    log(MOD_NAME, "Failed: Invalid domain user.");
                                                    strHostName = "";
                                                }

                                            }
                                            else
                                            {
                                                log(MOD_NAME, "Failed: Invalid domain.");
                                                strHostName = "";
                                            }
                                        }
                                    }
                                    else
                                    {
                                        log(MOD_NAME, "Failed: Incomplete server response; got: " + arData.Length + "; wanted: 6.");
                                        strHostName = "";
                                    }
                                }
                                else if (strHostResults.StartsWith("#!db", true, null))
                                {
                                    log(MOD_NAME, "Database error");
                                }
                                else if (strHostResults.StartsWith("#!im", true, null))
                                {
                                    log(MOD_NAME, "Invalid MAC address format");
                                }
                                else if (strHostResults.StartsWith("#!ih", true, null))
                                {
                                    log(MOD_NAME, "Invalid Hostname format");
                                }
                                else if (strHostResults.StartsWith("#!nf", true, null))
                                {
                                    log(MOD_NAME, "No host found");
                                }
                                else if (strHostResults.StartsWith("#!er", true, null))
                                {
                                    log(MOD_NAME, "General Error Returned: ");
                                    log(MOD_NAME, strHostResults);
                                }
                            }
                        }
                    }
                }

                if (strHostName != null && strHostName.Length > 0 && strHostName.Length < 16)
                {
                        if (strCurrentHostName != null)
                        {
                            if (strCurrentHostName.ToLower().Trim() != strHostName.ToLower().Trim())
                            {
                                log(MOD_NAME, "Hostnames are different - " + strCurrentHostName + " - " + strHostName);
                                if (!isDefaultMode)
                                {
                                    log(MOD_NAME, "Attempting to unregister from domain...");
                                    try
                                    {
                                        int result = NetUnjoinDomain(null, strUser, strPass, UnJoinOptions.NETSETUP_ACCOUNT_DELETE);
                                        if (result == NERR.Success)
                                        {
                                            log(MOD_NAME, "Domain Un-Join was successful!");
                                        }
                                        else
                                        {
                                            log(MOD_NAME, getErrorDescription(result));
                                        }
                                    }
                                    catch (Exception exp)
                                    {
                                        log(MOD_NAME, "NetUnJoinDomain Error: " + exp.Message);
                                        log(MOD_NAME, exp.StackTrace);
                                    }
                                }
                                doDefaultMode(strHostName);
                                
                            }
                            else
                            {
                                log(MOD_NAME, "Hostname is up to date");
                                if (!isDefaultMode)
                                {
                                    log(MOD_NAME, "Attempting to join domain if not already a member....");
                                    String strO = null;
                                    if (strOrgUnit != null && strOrgUnit != "")
                                        strO = strOrgUnit;
                                    try
                                    {
                                        int result = NetJoinDomain(null, strDomain, strO, strUser, strPass, (JoinOptions.NETSETUP_JOIN_DOMAIN | JoinOptions.NETSETUP_ACCT_CREATE));
                                        
					                    if (result == 2224)
					                    {
						                    log(MOD_NAME, "Existing computer account found....");
						                    result = NetJoinDomain(null, strDomain, null, strUser, strPass, (JoinOptions.NETSETUP_JOIN_DOMAIN));
					                    }

                                        if (result == NERR.Success)
                                        {
                                            log(MOD_NAME, "Domain Join was successful!");

                                            log(MOD_NAME, "Computer is about to restart.");
                                            pushMessage("This computer has joined domain: " + strDomain + " and needs to reboot now.");
                                            try
                                            {
                                                System.Threading.Thread.Sleep(20000);
                                            }
                                            catch { }

                                            restartComputer();
                                        }
                                        else
                                        {
                                            log(MOD_NAME, getErrorDescription(result));
                                        }
                                    }
                                    catch (Exception exp)
                                    {
                                        log(MOD_NAME, "NetJoinDomain Error: " + exp.Message);
                                        log(MOD_NAME, exp.StackTrace);
                                    }
                                }
                            }
                        }
                        else
                        {
                            log(MOD_NAME, "Failed because I was unable to determine the current hostname");
                        }
                    }
                    else
                    {
                        log(MOD_NAME, "Host name was not found in the database.");
                }

            }
            catch (Exception e)
            {
                pushMessage("FOG Hostname changer error:\n" + e.Message );
                log(MOD_NAME, e.Message);
                log(MOD_NAME, e.StackTrace);
            }
            finally
            {
            }
            intStatus = STATUS_TASKCOMPLETE;
        }

        private String getErrorDescription(int code)
        {
            switch (code)
            {
                case NERR.AccessDenied:
                    return "Domain Error! ('Access Denied' Code: " + code + ") ";
                case NERR.BadNetPath:
                    return "Domain Error! ('Bad Netpath' Code: " + code + ") ";
                case NERR.WrongPassword:
                    return "Domain Error! ('Wrong Password' Code: " + code + ") ";
                case NERR.DCNotFound:
                    return "Domain Error! ('DCNotFound' Code: " + code + ") ";
                case NERR.SetupAlreadyJoined:
                    return "Domain Error! ('SetupAlreadyJoined' Code: " + code + ") ";
                case NERR.InvalidWorkgroupName:
                    return "Domain Error! ('Invalid Workgroup/Domain Name' Code: " + code + ") ";
                default:
                    return "Domain Error! ('Unknown Error' Code: " + code + ") ";
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
