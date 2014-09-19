using System;
using System.Collections.Generic;
using System.ComponentModel;
using System.Text;
using System.Data;
using System.Net;
using System.Collections;
using System.Runtime.InteropServices;
using System.Diagnostics;
using System.IO;
using System.Drawing.Printing;
using System.Management;
using Microsoft.Win32;
using FOG;
using IniReaderObj;
using System.Threading;
using Microsoft.Win32.SafeHandles;

namespace FOG 
{

    public class PrinterManager : AbstractFOGService
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
        private Boolean blEnabled;

        private Boolean blGo;

        private const String MOD_NAME = "FOG::PrinterManager";

        private MessagingServer server;
        private PrinterManagerPackage printPack;
        private String strMACList;
        private Object objMutex;

        public PrinterManager()
        {
            printPack = null;
            server = null;
            intStatus = STATUS_STOPPED;
            server = new MessagingServer("fog_printer_pipe");
            objMutex = new Object();
            blEnabled = false;
            server.MessageReceived += new MessagingServer.MessageReceivedHandler(serverMessageReceived);
        }

        private void serverMessageReceived(Client c, String msg)
        {
            if (msg == "refresh")
            {
                if (blEnabled)
                {
                    log(MOD_NAME, "Printer update was request from fog tray...");
                    pullNewServerList();
                    doInstallRemoveProcess();
                    doSetDefault();
                }
                else
                    log(MOD_NAME, "Sevice is disabled.");
            }
            else
            {
                log(MOD_NAME, "Unknown request: " + msg);
            }
        }

        private Boolean readSettings()
        {
            if (ini != null)
            {
                if (ini.isFileOk())
                {
                    String pre = ini.readSetting("printmanager", "urlprefix");
                    String post = ini.readSetting("printmanager", "urlpostfix");
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
            blGo = true;
            try
            {
                intStatus = STATUS_RUNNING;
                if (readSettings())
                {
                    log(MOD_NAME, "Starting interprocess communication process...");
                    server.start();
                    if ( server.isRunning() )
                        log(MOD_NAME, " interprocess comm startup: OK");
                    else
                        log(MOD_NAME, " interprocess comm startup: FAILED");

                    ArrayList alMACs = getMacAddress();

                    strMACList = null;
                    if (alMACs != null && alMACs.Count > 0)
                    {
                        String[] strMacs = (String[])alMACs.ToArray(typeof(String));
                        strMACList = String.Join("|", strMacs);
                    }



                    if (strMACList != null && strMACList.Length > 0)
                    {

                        Boolean blConnectOK = false;
                        String strDta = "";
                        while (!blConnectOK)
                        {
                            try
                            {
                                log(MOD_NAME, "Attempting to connect to fog server...");
                                WebClient wc = new WebClient();
                                String strPath = strURLModuleStatus + "?mac=" + strMACList + "&moduleid=printermanager";
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

                        blEnabled = blLoop;
                        if (blLoop)
                            managePrinters();
                    }
                }
                else
                    log(MOD_NAME, "Failed to read ini settings.");
            }
            catch
            {
            }
        }

        public override string mGetDescription()
        {
            return "Printer Manager - Manages print devices and drivers on the client computer.";
        }

        private String[] getInstalledPrinters()
        {
            ArrayList installedPrinters = new ArrayList();
            foreach( String printer in PrinterSettings.InstalledPrinters )
            {
                
                installedPrinters.Add(printer);
            }
            return (String[])(installedPrinters.ToArray(typeof(String)));
        }

        private Boolean isServerInstallRequested(String local, Printer[] server)
        {
            if (server != null && local != null)
            {
                for (int i = 0; i < server.Length; i++)
                {
                    if (server[i] != null)
                    {
                        if (server[i].getAlias().Trim() == local.Trim())
                            return true;
                    }
                }
                return false;
            }
            else
                return true;
        }

        private Boolean isInstalledLocally( String[] local, Printer server )
        {
            if (server != null && local != null)
            {
                for (int i = 0; i < local.Length; i++)
                {
                    if (server.getAlias().Trim() == local[i].Trim())
                    {
                        return true;
                    }
                }
                return false;
            }
            
            return true;
        }

        private void doInstallRemoveProcess()
        {
            try
            {
                lock (objMutex)
                {
                    if (strMACList != null)
                    {
                        if (printPack != null)
                        {
                            String[] arInstalledPrinters = getInstalledPrinters();

                            log(MOD_NAME, "Management level = " + printPack.getManagementLevel());

                            if (printPack.getManagementLevel() == PrinterManagerPackage.NOMANAGEMENT)
                            {
                                log(MOD_NAME, "This host is set to NO MANAGEMENT, we will exit now.");
                                return;
                            }

                            if (printPack.getManagementLevel() == PrinterManagerPackage.FULLCONTROL)
                            {
                                // unistall unwanted printers
                                log(MOD_NAME, "Removing unwanted printers from host...");
                                Printer[] printers = printPack.getPrinters();
                                for (int i = 0; i < arInstalledPrinters.Length; i++)
                                {
                                    if (printers != null)
                                    {
                                        if (!isServerInstallRequested(arInstalledPrinters[i], printers))
                                        {
                                            log(MOD_NAME, "Removal Requested for " + arInstalledPrinters[i]);
                                            if (new Printer("", arInstalledPrinters[i], "", "", "", false).uninstall())
                                            {
                                                log(MOD_NAME, "Printer Removed: " + arInstalledPrinters[i]);
                                                pushMessage("Printer Removed: " + arInstalledPrinters[i]);
                                            }
                                            else
                                            {
                                                log(MOD_NAME, "Failed to Removed: " + arInstalledPrinters[i]);
                                                pushMessage("Error Removing Printer : " + arInstalledPrinters[i]);
                                            }
                                        }
                                        else
                                        {
                                            log(MOD_NAME, "Server Match: " + arInstalledPrinters[i] + " printer will stay as is");
                                        }
                                    }
                                    else
                                    {
                                        if (new Printer("", arInstalledPrinters[i], "", "", "", false).uninstall())
                                        {
                                            log(MOD_NAME, "Printer Removed: " + arInstalledPrinters[i]);
                                            pushMessage("Printer Removed: " + arInstalledPrinters[i]);
                                        }
                                        else
                                        {
                                            log(MOD_NAME, "Failed to Removed: " + arInstalledPrinters[i]);
                                            pushMessage("Error Removing Printer : " + arInstalledPrinters[i]);
                                        }
                                    }
                                }
                            }

                            arInstalledPrinters = getInstalledPrinters();
                            if (printPack.getManagementLevel() == PrinterManagerPackage.FULLCONTROL || printPack.getManagementLevel() == PrinterManagerPackage.INSTALLONLY)
                            {
                                // install printers
                                log(MOD_NAME, "Adding new printers to host...");
                                Printer[] printers = printPack.getPrinters();

                                if (printers != null)
                                {
                                    log(MOD_NAME, printers.Length + " found on server side.");
                                    for (int i = 0; i < printers.Length; i++)
                                    {
                                        if (printers[i] != null)
                                        {
                                            if (!isInstalledLocally(arInstalledPrinters, printers[i]))
                                            {
                                                log(MOD_NAME, "Installation requested for " + printers[i].getAlias());

                                                if (printers[i].install())
                                                {
                                                    log(MOD_NAME, "Printer Installed: " + printers[i].getAlias());
                                                    pushMessage("Printer Installed: " + printers[i].getAlias());
                                                }
                                                else
                                                {
                                                    log(MOD_NAME, "Printer Installation Failed: " + printers[i].getAlias());
                                                    log(MOD_NAME, printers[i].getError());
                                                    pushMessage("Printer Installation Failed: " + printers[i].getAlias());
                                                }
                                            }
                                            else
                                                log(MOD_NAME, "Printer already installed " + printers[i].getAlias());
                                        }
                                    }
                                }
                                else
                                    log(MOD_NAME, "0 found on server side.");
                            }

                        }
                        else
                        {
                            log(MOD_NAME, "Unable to get valid printer list.");
                        }
                    }
                    else
                    {
                        log(MOD_NAME, "Unable to determine a valid mac address for host");
                    }
                }
            }
            catch { }

        }

        private Boolean pullNewServerList()
        {
            try
            {
                lock( objMutex )
                {
                    WebClient web = new WebClient();
                    String strPath = strURLPath + "?mac=" + strMACList;
                    String strData = web.DownloadString(strPath);
                    strData = strData.Trim();
                    printPack = new PrinterManagerPackage(strData);
                    return printPack.parseResponse();
                }
            }
            catch (Exception ex)
            {
                log(MOD_NAME, "Error pulling printer list...");
                log(MOD_NAME, ex.Message);
                log(MOD_NAME, ex.StackTrace);
                return false;
            }


        }

        private void doSetDefault()
        {
            if (printPack != null && printPack.getManagementLevel() != PrinterManagerPackage.NOMANAGEMENT)
            {
                if (strMACList != null)
                {
                    try
                    {
                        lock (objMutex)
                        {
                            Printer[] printers = printPack.getPrinters();
                            String[] arInstalledPrinters = getInstalledPrinters();
                            if (printers != null)
                            {
                                log(MOD_NAME, "Setting Default Printer...");
                                for (int i = 0; i < printers.Length; i++)
                                {
                                    if (printers[i] != null)
                                    {
                                        if (printers[i].isDefault())
                                        {
                                            if (isInstalledLocally(arInstalledPrinters, printers[i]))
                                            {
                                                log(MOD_NAME, "Setting default for " + printers[i].getAlias());
                                                try
                                                {


                                                    int retries = 6;
                                                    while (retries > 0)
                                                    {
                                                        log(MOD_NAME, "Remaining: " + retries + " Sending message to FOG Tray...");
                                                        server.sendMessage("[MD]:" + printers[i].getAlias());

                                                        retries--;
                                                        try
                                                        {
                                                            System.Threading.Thread.Sleep(20000);
                                                        }
                                                        catch { }
                                                    }
                                                }
                                                catch (Exception pe)
                                                {
                                                    log(MOD_NAME, pe.Message);
                                                    log(MOD_NAME, pe.StackTrace);
                                                }
                                            }
                                            else
                                            {
                                                log(MOD_NAME, "Failed: it looks like the local printer is missing.");
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                    catch { }
                }
            }
        }

        private void managePrinters()
        {
            try
            {
                log(MOD_NAME, "Starting printer manager...");

                Random r = new Random();
                int s = r.Next(20, 50);
                log(MOD_NAME, "Yielding to other services for " + s + " seconds.");
                try
                {
                    System.Threading.Thread.Sleep(s * 1000);
                }
                catch
                {
                }

                while (!pullNewServerList())
                {
                    log(MOD_NAME, "Failed to connect to fog server!");
                    log(MOD_NAME, "This is typically caused by a network error!");
                    log(MOD_NAME, "Sleeping for 1 minute.");
                    try
                    {
                        System.Threading.Thread.Sleep(60000);
                    }
                    catch { }
                }

                doInstallRemoveProcess();

                log(MOD_NAME, "Major printing operations complete, moving to tracking mode.");
                String lastUser = null;
                while (blGo)
                {
                    try
                    {
                        System.Threading.Thread.Sleep(5000);
                    }
                    catch { }

                    try
                    {
                        if (isLoggedIn())
                        {
                            String tmpUsr = getUserName();
                            if (lastUser != tmpUsr)
                            {
                                log(MOD_NAME, "New user detected: " + tmpUsr);
                                try
                                {
                                    log(MOD_NAME, "Waiting for tray to load...");
                                    System.Threading.Thread.Sleep(10000);
                                }
                                catch { }

                                doSetDefault();
                                lastUser = tmpUsr;
                            }
                        }
                        else
                        {
                            lastUser = null;
                        }
                    }
                    catch (Exception ex )
                    {
                        log(MOD_NAME, ex.Message);
                        log(MOD_NAME, ex.StackTrace);
                        log(MOD_NAME, ex.InnerException.StackTrace);
                    }
                }
            }
            catch (Exception e)
            {
                pushMessage("FOG Printer Management error:\n" + e.Message);
                log(MOD_NAME, e.Message);
                log(MOD_NAME, e.StackTrace);
                log(MOD_NAME, e.InnerException.StackTrace);
            }
            finally
            {
            }
            intStatus = STATUS_TASKCOMPLETE;
        }

        public override Boolean mStop()
        {
            blGo = false;
            log(MOD_NAME, "Shuting down");
            return true;
        }

        public override int mGetStatus()
        {
            return intStatus;
        }
    }

    public class PrinterManagerPackage
    {
        private String strData, strError;
        private int intManagementLevel;
        private Printer[] printers;

        public const int NOMANAGEMENT = 0;
        public const int INSTALLONLY = 1;
        public const int FULLCONTROL = 2;

        public PrinterManagerPackage(String response)
        {
            strData = response;
            printers = null;
            strError = "";
        }

        public int getManagementLevel()
        {
            return intManagementLevel;
        }

        public Printer[] getPrinters()
        {
            return printers;
        }

        public String getError()
        {
            return strError;
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

        public Boolean parseResponse()
        {
            try
            {
                if (strData != null)
                {
                    String[] arLines = strData.Split('\n');
                    if (arLines.Length > 0)
                    {
                        // first line is management level
                        String strLine = decode64(arLines[0]);
                        
                        if (strLine.StartsWith("#!mg="))
                        {
                            strLine = strLine.Remove(0, 5);
                            if (strLine.Trim() == "0")
                            {
                                    intManagementLevel = NOMANAGEMENT;
                            }
                            else if (strLine.Trim() == "1")
                            {
                                    intManagementLevel = INSTALLONLY;
                            }
                            else if (strLine.Trim() == "2")
                            {
                                    intManagementLevel = FULLCONTROL;
                            }
                            else
                            {
                                strError = "unknown management level : " + strLine;
                                return false;
                            }

                            if (arLines.Length > 1)
                            {
                                ArrayList printers = new ArrayList();
                                for (int i = 1; i < arLines.Length; i++)
                                {
                                    String line = decode64(arLines[i].Trim());
                                    if (line != null)
                                    {
                                        String[] arLine = line.Split(new Char[] { '|' });
                                        if (arLine.Length == 6)
                                        {
                                            printers.Add(new Printer(arLine[2], arLine[3], arLine[1], arLine[0], arLine[4], (arLine[5] == "1")));
                                        }
                                        else
                                        {
                                            
                                            strError = "Line Rejected::Invalid field count::" + line;
                                            return false;
                                        }
                                    }
                                }
                                this.printers = (Printer[])(printers.ToArray(typeof(Printer)));
                                
                                return true;
                            }
                            return true;
                        }
                        else if (strLine.StartsWith("#!db"))
                            strError = "Database error";
                        else if (strLine.StartsWith("#!im"))
                            strError = "Invalid MAC Format";
                        else if (strLine.StartsWith("#!er"))
                            strError = "Other Error";
                        else
                        {
                            strError = "unable to determine management level";
                            strError += strLine;
                        }
                    }
                    else
                        strError = "invalid data response";
                }
                else
                    strError = "data response was null";
                return false;
            }
            catch (Exception e)
            {
                strError = e.Message;
                return false;
            }

        }
    }

    public class MessagingServer
    {
        [DllImport("kernel32.dll", SetLastError = true)]
        public static extern SafeFileHandle CreateNamedPipe(String pipeName, uint dwOpenMode, uint dwPipeMode, uint nMaxInstances, uint nOutBufferSize, uint nInBufferSize, uint nDefaultTimeOut, IntPtr lpSecurityAttributes);

        [DllImport("kernel32.dll", SetLastError = true)]
        public static extern int ConnectNamedPipe(SafeFileHandle hNamedPipe, IntPtr lpOverlapped);

        [DllImport("Advapi32.dll", SetLastError = true)]
        public static extern bool InitializeSecurityDescriptor(out SECURITY_DESCRIPTOR sd, int dwRevision);

        [DllImport("Advapi32.dll", SetLastError = true)]
        public static extern bool SetSecurityDescriptorDacl(ref SECURITY_DESCRIPTOR sd, bool bDaclPresent, IntPtr Dacl, bool bDaclDefaulted);

        [StructLayout(LayoutKind.Sequential)]
        public struct SECURITY_ATTRIBUTES
        {
            public int nLength;
            public IntPtr lpSecurityDescriptor;
            public bool bInheritHandle;
        }

        [StructLayout(LayoutKind.Sequential)]
        public struct SECURITY_DESCRIPTOR
        {
            private byte Revision;
            private byte Sbz1;
            private ushort Control;
            private IntPtr Owner;
            private IntPtr Group;
            private IntPtr Sacl;
            private IntPtr Dacl;
        }

        public const uint DUPLEX = (0x00000003);
        public const uint FILE_FLAG_OVERLAPPED = (0x40000000);

        public delegate void MessageReceivedHandler(Client client, string message);

        public event MessageReceivedHandler MessageReceived;
        public const int BUFFER_SIZE = 4096;

        String strPipeName;
        Thread listenThread;
        Boolean blRunning;
        List<Client> clients;

        public MessagingServer(String pipeName)
        {
            blRunning = false;
            strPipeName = pipeName;
            clients = new List<Client>();
        }

        public void start()
        {
            listenThread = new Thread(new ThreadStart(listenForClients));
            listenThread.IsBackground = true;
            listenThread.Start();
            blRunning = true;
        }

        private void listenForClients()
        {
            // Do the security stuff to allow any user to connect.
            // This was fixed in version 0.16 to allow users in the group
            // "users" to interact with the backend service.
            Boolean blSecOk = false;

            IntPtr ptrSec = IntPtr.Zero;
            SECURITY_ATTRIBUTES secAttrib = new SECURITY_ATTRIBUTES();
            SECURITY_DESCRIPTOR secDesc;

            if (InitializeSecurityDescriptor(out secDesc, 1))
            {
                if (SetSecurityDescriptorDacl(ref secDesc, true, IntPtr.Zero, false))
                {
                    secAttrib.lpSecurityDescriptor = Marshal.AllocHGlobal(Marshal.SizeOf(typeof(SECURITY_DESCRIPTOR)));
                    Marshal.StructureToPtr(secDesc, secAttrib.lpSecurityDescriptor, false);
                    secAttrib.bInheritHandle = false;
                    secAttrib.nLength = Marshal.SizeOf(typeof(SECURITY_ATTRIBUTES));
                    ptrSec = Marshal.AllocHGlobal(Marshal.SizeOf(typeof(SECURITY_ATTRIBUTES)));
                    Marshal.StructureToPtr(secAttrib, ptrSec, false);
                    blSecOk = true;
                }
            }

            if (blSecOk)
            {
                while (true)
                {
                    SafeFileHandle clientHandle = CreateNamedPipe(@"\\.\pipe\" + strPipeName, DUPLEX | FILE_FLAG_OVERLAPPED, 0, 255, BUFFER_SIZE, BUFFER_SIZE, 0, ptrSec);

                    if (clientHandle.IsInvalid)
                        return;

                    int success = ConnectNamedPipe(clientHandle, IntPtr.Zero);

                    if (success == 0)
                        return;

                    Client client = new Client();
                    client.setHandle(clientHandle);

                    lock (clients)
                        this.clients.Add(client);

                    Thread readThread = new Thread(new ParameterizedThreadStart(read));
                    readThread.IsBackground = true;
                    readThread.Start(client);
                }
            }
        }

        private void read(object objClient)
        {
            Client client = (Client)objClient;
            client.setStream(new FileStream(client.getHandle(), FileAccess.ReadWrite, BUFFER_SIZE, true));

            byte[] buffer = new byte[BUFFER_SIZE];
            ASCIIEncoding encoder = new ASCIIEncoding();

            while (true)
            {
                int bRead = 0;

                try
                {
                    bRead = client.getStream().Read(buffer, 0, BUFFER_SIZE);
                }
                catch { }

                if (bRead == 0)
                    break;

                if (MessageReceived != null)
                    MessageReceived(client, encoder.GetString(buffer, 0, bRead));
            }

            client.getStream().Close();
            client.getHandle().Close();
            lock (clients)
                clients.Remove(client);
        }

        public void sendMessage(String msg)
        {
            lock (this.clients)
            {
                ASCIIEncoding encoder = new ASCIIEncoding();
                byte[] mBuf = encoder.GetBytes(msg);
                foreach (Client c in clients)
                {
                    c.getStream().Write(mBuf, 0, mBuf.Length);
                    c.getStream().Flush();
                }
            }
        }

        public Boolean isRunning() { return blRunning; }

        public String getPipeName() { return strPipeName; }
    }

    public class Client
    {
        private SafeFileHandle hndl;
        private FileStream stream;

        public Client()
        {
            hndl = null;
            stream = null;
        }

        public Client(SafeFileHandle hndl, FileStream stream)
        {
            this.hndl = null;
            this.stream = null;
        }

        public SafeFileHandle getHandle() { return this.hndl; }
        public FileStream getStream() { return this.stream; }

        public void setHandle(SafeFileHandle h) { this.hndl = h; }
        public void setStream(FileStream s) { this.stream = s; }
    }

    public class Printer
    {
        private String strModel, strAlias, strFile, strPort, strIP;
        private String strError;
        private Boolean blIsDefault;

        public Printer(String model, String alias, String file, String port, String ip, Boolean isDefault)
        {
            strModel = model;
            strAlias = alias;
            strFile = file;
            strPort = port;
            strIP = ip;
            strError = "";
            blIsDefault = isDefault;
        }

        public string getPort()
        {
            return strPort;
        }

        public string getFile()
        {
            return strFile;
        }

        public string getIP()
        {
            return strIP;
        }

        public string getModel()
        {
            return strModel;
        }

        public String getAlias()
        {
            return strAlias;
        }

        public Boolean isDefault()
        {
            return blIsDefault;
        }

        public Boolean isComplete()
        {
            return ( strModel != null && strAlias != null && strPort!= null );
        }

        private void sleep(int millis)
        {
            try
            {
                System.Threading.Thread.Sleep(millis);
            }
            catch
            { }

        }

        public String getInstallArguments()
        {
            return " printui.dll,PrintUIEntry /if /q /b \"" + strAlias + "\" /f \"" + strFile + "\" /r \"" + strPort + "\" /m \"" + strModel + "\"";
        }

        public Boolean install()
        {
            return install(60000);
        }

        public Boolean uninstall()
        {
            return uninstall(60000);
        }

        private Boolean uninstall(long maxWait)
        {
            long waited = 0L;
            if (maxWait > 0)
            {
                if (strAlias != null)
                {
                    Process proc;
                    if (strAlias.Trim().ToLower().StartsWith("\\\\"))
                    {
                        proc = Process.Start("rundll32.exe", " printui.dll,PrintUIEntry /gd /q /n \"" + strAlias + "\"");
                    }
                    else
                    {
                        proc = Process.Start("rundll32.exe", " printui.dll,PrintUIEntry /dl /q /n \"" + strAlias + "\"");
                    }
                    while (!proc.HasExited)
                    {
                        sleep(100);
                        waited += 100;
                        if (waited > maxWait)
                        {
                            proc.Kill();
                            return false;
                        }
                    }
                    //bounceSpooler();

                    return (proc.ExitCode == 0);
                }
            }
            return false;
        }

        public Boolean install(long maxWait)
        {
            long waited = 0L;
            int port = 9100;
            if (isComplete())
            {
                if (maxWait > 0)
                {
                    //if ( strFile.Length == 0 || File.Exists(strFile))
                    //{
                        if (strIP != null && strIP.Length > 0 )
                        {
                            if (strIP.Contains(":"))
                            {
                                String[] arIP = strIP.Split(new char[] { ':' });
                                if (arIP.Length == 2)
                                {
                                    strIP = arIP[0];
                                    try
                                    {
                                        port = Int32.Parse( arIP[1] );
                                    }
                                    catch
                                    {

                                    }
                                }
                            }

                            ConnectionOptions conn = new ConnectionOptions();
                            conn.EnablePrivileges = true;
                            conn.Impersonation = ImpersonationLevel.Impersonate;

                            ManagementPath mPath = new ManagementPath("Win32_TCPIPPrinterPort");

                            ManagementScope mScope = new ManagementScope(@"\\.\root\cimv2", conn);
                            mScope.Options.EnablePrivileges = true;
                            mScope.Options.Impersonation = ImpersonationLevel.Impersonate;

                            ManagementObject mPort = new ManagementClass(mScope, mPath, null).CreateInstance();

                            mPort.SetPropertyValue("Name", "IP_" + strIP);
                            mPort.SetPropertyValue("Protocol", 1);
                            mPort.SetPropertyValue("HostAddress", strIP);
                            mPort.SetPropertyValue("PortNumber", port);
                            mPort.SetPropertyValue("SNMPEnabled", false);

                            PutOptions put = new PutOptions();
                            put.UseAmendedQualifiers = true;
                            put.Type = PutType.UpdateOrCreate;
                            mPort.Put(put);
                        }

                        Process proc;
                        Boolean blIsIPP = false;

                        if (strPort.Trim().ToLower().StartsWith("ipp://"))
                        {
                            // iprint installation
                            proc = new Process();
                            proc.StartInfo.FileName = @"c:\windows\system32\iprntcmd.exe";
                            proc.StartInfo.Arguments = " -a no-gui \"" + strPort + "\"";
                            proc.StartInfo.CreateNoWindow = true;
                            proc.StartInfo.WindowStyle = ProcessWindowStyle.Hidden;
                            proc.Start();
                            blIsIPP = true;
                        }
                        else if (strAlias.Trim().ToLower().StartsWith("\\\\"))
                        {
                            // Add per machine printer connection
                            proc = Process.Start("rundll32.exe", " printui.dll,PrintUIEntry /ga /n " + strAlias);
                            proc.WaitForExit(120000);
                            // Add printer network connection, download the drivers from the print server
                            proc = Process.Start("rundll32.exe", " printui.dll,PrintUIEntry /in /n " + strAlias);
                            proc.WaitForExit(120000);
                            bounceSpooler();
                        }
                        else
                        {
                            // Normal Installation
                            proc = Process.Start("rundll32.exe", getInstallArguments());
                        }
                        
                        while (!proc.HasExited)
                        {
                            sleep(100);
                            waited += 100;
                            if (waited > maxWait)
                            {
                                proc.Kill();
                                strError = "Max install time exceeded (" + maxWait + ")";
                                return false;
                            }
                        }

                        if (blIsIPP)
                        {
                            strError = "IPP Return codes unknown";
                            return true;
                        }

                        if (proc.ExitCode == 0)
                        {
                            strError = "";
                            return true;
                        }
                    //}
                    //else
                        //strError = "Printer Definition file was not found!";
                    //strError = "Strfile:" + strFile + ".";
                }
                else
                    strError = "Max wait time was less than zero!";
            }
            else
                strError = "Printer information is incomplete";
            return false;
        }

        public void bounceSpooler()
        {
            // Restart print service so new printers show up
            Process spool = new Process();
            ProcessStartInfo pi = new ProcessStartInfo();
            pi.FileName = "cmd";
            pi.UseShellExecute = false;
            pi.CreateNoWindow = true;
            pi.RedirectStandardInput = true;
            pi.RedirectStandardOutput = true;
            spool.StartInfo = pi;
            spool.Start();
            spool.StandardInput.WriteLine(" net stop spooler && net start spooler");
        }

        public String getError()
        {
            return strError;
        }
    }
}
