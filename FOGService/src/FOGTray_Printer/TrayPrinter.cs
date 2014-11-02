using System;
using System.Collections.Generic;
using System.Diagnostics;
using System.Text;
using AbstractTrayModule;
using System.Windows.Forms;
using System.Drawing;
using System.Threading;
using Microsoft.Win32.SafeHandles;
using System.IO;
using System.Runtime.InteropServices;

namespace FOGTray_Printer
{
    public class TrayPrinter : AbstractFOGTrayModule
    {

        public delegate void buttonClick(object sender, EventArgs e);

        public event buttonClick onclick;

        private ToolStripMenuItem mainmenu, refresh;

        private MessagingClient client;


        public TrayPrinter()
        {
            onclick += new buttonClick(requestHandler);
        }

        public override void start()
        {
            client = new MessagingClient("fog_printer_pipe");
            client.MessageReceived += new MessagingClient.MessageReceivedHandler(clientMessageReceived);
            client.Connect();          
        }

        public override void stop()
        {
            if (client != null)
            {
                client.kill();
            }
        }

        public override String getDescription() { return "Allows users to controll certain aspects of their local printers."; }

        private void clientMessageReceived(String msg)
        {
            
            if ( msg != null )
            {
                
                if (msg.Trim().StartsWith("[MD]:"))
                {
                    msg = msg.Replace("[MD]:", "");
                    Printer p = new Printer(msg);
                    p.makeDefault();
                }
            }
        }

        public void requestHandler(object sender, EventArgs e)
        {
            if (sender != null)
            {
                if ((ToolStripMenuItem)sender == refresh)
                {
                    if (!client.isConnected())
                    {
                        if (!client.Connect())
                        {
                            MessageBox.Show("Unable to connect to printer service module, make sure the FOG Service is running.", "FOG Error", MessageBoxButtons.OK, MessageBoxIcon.Exclamation);
                            return;
                        }
                    }

                    if (client.isConnected())
                    {
                        try
                        {
                            client.sendMessage("refresh");
                        }
                        catch (Exception ex)
                        {
                            MessageBox.Show(ex.Message);
                            MessageBox.Show(ex.StackTrace);
                        }
                    }
                    else
                    {
                        MessageBox.Show("Unable to connect to printer service module.", "FOG Error", MessageBoxButtons.OK, MessageBoxIcon.Exclamation);
                    }
                }
            }
        }

        public override ToolStripMenuItem getMenuSegment()
        {
            mainmenu = new ToolStripMenuItem("Printers", null, new EventHandler(onclick));
            refresh = new ToolStripMenuItem("Refresh My Printers", null, new EventHandler(onclick));
            mainmenu.DropDownItems.Add(refresh);
            return mainmenu;
        }

    }

    public class Printer
    {
        private String strName;

        public Printer(String name)
        {
            strName = name;
        }

        public Boolean makeDefault()
        {
            try
            {
                Process proc = Process.Start("rundll32.exe", " printui.dll,PrintUIEntry /y /n \"" + strName + "\"");
                int waited = 0;
                while (!proc.HasExited)
                {
                    System.Threading.Thread.Sleep(100);
                    waited += 100;
                    if (waited > 60000)
                    {
                        proc.Kill();
                        return false;
                    }
                }
                return true;
            }
            catch
            {

            }
            return false;
        }
    }

    public class MessagingClient
    {
        [DllImport("kernel32.dll", SetLastError = true)]
        public static extern SafeFileHandle CreateFile(String pipeName, uint dwDesiredAccess, uint dwShareMode,IntPtr lpSecurityAttributes, uint dwCreationDisposition, uint dwFlagsAndAttributes, IntPtr hTemplate);

        public const uint GENERIC_READ = (0x80000000);
        public const uint GENERIC_WRITE = (0x40000000);
        public const uint OPEN_EXISTING = 3;
        public const uint FILE_FLAG_OVERLAPPED = (0x40000000);

        public delegate void MessageReceivedHandler(string message);
        public event MessageReceivedHandler MessageReceived;

        public const int BUFFER_SIZE = 4096;

        private Boolean blConnected;
        private string strPipeName;
        private FileStream stream;
        private SafeFileHandle handle;
        private Thread readThread;

        public MessagingClient(String pipe)
        {
            blConnected = false;
            strPipeName = pipe;
        }

        public Boolean isConnected() { return blConnected; }

        public String getPipeName() { return strPipeName; }

        public Boolean Connect()
        {
            try
            {
                handle = CreateFile(@"\\.\pipe\" + strPipeName, GENERIC_READ | GENERIC_WRITE, 0, IntPtr.Zero, OPEN_EXISTING, FILE_FLAG_OVERLAPPED, IntPtr.Zero);

                if (handle == null) return false;

                if (handle.IsInvalid)
                {
                    blConnected = false;
                    return false;
                }

                blConnected = true;

                readThread = new Thread(new ThreadStart(readFromPipe));
                readThread.Start();
                return true;
            }
            catch (Exception e)
            {
                return false;
            }
        }

        public void kill()
        {
            try
            {
                if (stream != null)
                {
                    stream.Close();
                }

                if (handle != null)
                {
                    handle.Close();
                }

                readThread.Abort();
            }
            catch { }
        }

        public void readFromPipe()
        {
            stream = new FileStream(handle, FileAccess.ReadWrite, BUFFER_SIZE, true);
            byte[] readBuffer = new byte[BUFFER_SIZE];

            ASCIIEncoding encoder = new ASCIIEncoding();
            while (true)
            {
                int bRead = 0;

                try
                {
                    bRead = stream.Read(readBuffer, 0, BUFFER_SIZE);
                }
                catch
                {
                    break;
                }

                if (bRead == 0) break;

                if (MessageReceived != null) MessageReceived(encoder.GetString(readBuffer, 0, bRead));
            }
            stream.Close();
            handle.Close();
        }

        public void sendMessage(String message)
        {
            try
            {
                ASCIIEncoding encoder = new ASCIIEncoding();
                byte[] messageBuffer = encoder.GetBytes(message);

                stream.Write(messageBuffer, 0, messageBuffer.Length);
                stream.Flush();
            }
            catch (Exception e)
            {
                MessageBox.Show(e.Message);
                MessageBox.Show(e.StackTrace);
            }
        }

    }
}
