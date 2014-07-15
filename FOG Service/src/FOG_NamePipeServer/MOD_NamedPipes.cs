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
using System.Threading;
using System.IO;
using Microsoft.Win32.SafeHandles;

namespace FOG 
{

    public class NamedPipes : AbstractFOGService
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

        private const String MOD_NAME = "FOG::NamedPipes";
        public const String PIPE_NAME = @"\\.\pipe\myNamedPipe";

        public NamedPipes()
        {
            
        }

        public override void mStart()
        {
            try
            {
                
                if (readSettings())
                    startWatching();
                else
                    log(MOD_NAME, "Halted, unable to read ini settings");
            }
            catch
            {
            }
        }

        public Boolean readSettings()
        {
            return true;
        }

        public override string mGetDescription()
        {
            return "Task Reboot - This sub service will periodically check for a task and if one is found, it will reboot the computer.";
        }

        void pipeServer_MessageReceived(Server.Client client, string message)
        {
            log(MOD_NAME, "From tray: " + message);
        }

        private void startWatching()
        {
            try
            {
                

                log(MOD_NAME, "Starting named pipe server...");
                Server pipeServer = new Server();
                pipeServer.MessageReceived += new Server.MessageReceivedHandler(pipeServer_MessageReceived);

                if (! pipeServer.Running)
                {
                    pipeServer.PipeName = PIPE_NAME;
                    pipeServer.Start();
                    log(MOD_NAME, "server running...");
                }
                else
                    log(MOD_NAME,"Server already running.");

                while (true)
                {
                    try
                    {
                        Thread.Sleep(1000);
                    }
                    catch { }
                }
                

            }
            catch (Exception e)
            {
                log(MOD_NAME, e.Message);
                log(MOD_NAME, e.StackTrace);
            }
            finally
            {
            }
            
        }

        public override Boolean mStop()
        {
            
            log(MOD_NAME, "Stopping...");
            return true;
        }

        public override int mGetStatus()
        {
            return 1;
        }
    }

    class Server
    {
        [DllImport("kernel32.dll", SetLastError = true)]
        public static extern SafeFileHandle CreateNamedPipe(
           String pipeName,
           uint dwOpenMode,
           uint dwPipeMode,
           uint nMaxInstances,
           uint nOutBufferSize,
           uint nInBufferSize,
           uint nDefaultTimeOut,
           IntPtr lpSecurityAttributes);

        [DllImport("kernel32.dll", SetLastError = true)]
        public static extern int ConnectNamedPipe(
           SafeFileHandle hNamedPipe,
           IntPtr lpOverlapped);

        public const uint DUPLEX = (0x00000003);
        public const uint FILE_FLAG_OVERLAPPED = (0x40000000);

        public class Client
        {
            public SafeFileHandle handle;
            public FileStream stream;
        }

        public delegate void MessageReceivedHandler(Client client, string message);

        public event MessageReceivedHandler MessageReceived;
        public const int BUFFER_SIZE = 4096;

        string pipeName;
        Thread listenThread;
        bool running;
        List<Client> clients;

        public string PipeName
        {
            get { return this.pipeName; }
            set { this.pipeName = value; }
        }

        public bool Running
        {
            get { return this.running; }
        }

        public Server()
        {
            this.clients = new List<Client>();
        }

        public void Start()
        {
            //start the listening thread
            this.listenThread = new Thread(new ThreadStart(ListenForClients));
            this.listenThread.Start();

            this.running = true;
        }

        private void ListenForClients()
        {
            while (true)
            {
                SafeFileHandle clientHandle =
                CreateNamedPipe(
                     this.pipeName,
                     DUPLEX | FILE_FLAG_OVERLAPPED,
                     0,
                     255,
                     BUFFER_SIZE,
                     BUFFER_SIZE,
                     0,
                     IntPtr.Zero);

                //could not create named pipe
                if (clientHandle.IsInvalid)
                    return;

                int success = ConnectNamedPipe(clientHandle, IntPtr.Zero);

                //could not connect client
                if (success == 0)
                    return;

                Client client = new Client();
                client.handle = clientHandle;

                lock (clients)
                    this.clients.Add(client);

                Thread readThread = new Thread(new ParameterizedThreadStart(Read));
                readThread.Start(client);
            }
        }

        private void Read(object clientObj)
        {
            Client client = (Client)clientObj;
            client.stream = new FileStream(client.handle, FileAccess.ReadWrite, BUFFER_SIZE, true);
            byte[] buffer = new byte[BUFFER_SIZE];
            ASCIIEncoding encoder = new ASCIIEncoding();

            while (true)
            {
                int bytesRead = 0;

                try
                {
                    bytesRead = client.stream.Read(buffer, 0, BUFFER_SIZE);
                }
                catch
                {
                    //read error has occurred
                    break;
                }

                //client has disconnected
                if (bytesRead == 0)
                    break;

                //fire message received event
                if (this.MessageReceived != null)
                    this.MessageReceived(client, encoder.GetString(buffer, 0, bytesRead));
            }

            //clean up resources
            client.stream.Close();
            client.handle.Close();
            lock (this.clients)
                this.clients.Remove(client);
        }

        
        public void SendMessage(string message)
        {
            lock (this.clients)
            {
                ASCIIEncoding encoder = new ASCIIEncoding();
                byte[] messageBuffer = encoder.GetBytes(message);
                foreach (Client client in this.clients)
                {
                    client.stream.Write(messageBuffer, 0, messageBuffer.Length);
                    client.stream.Flush();
                }
            }
        }
    }
}
