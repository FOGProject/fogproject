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

namespace FOG 
{

    public class GUIWatcher : AbstractFOGService
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
        private Boolean blGo;

        private const String MOD_NAME = "FOG::GUIWatcher";

        public GUIWatcher()
        {
            intStatus = STATUS_STOPPED;
        }

        private Boolean readSettings()
        {
            return true;
        }

        public override void mStart()
        {
            try
            {
                intStatus = STATUS_RUNNING;
                blGo = true;
                startWatching();
            }
            catch
            {
            }
        }

        public override string mGetDescription()
        {
            return "GUI Watcher - The only job of this subservice is to display message to the FOG Service GUI.";
        }

        private void startWatching()
        {
            try
            {
                log(MOD_NAME, "Starting GUI Watcher...");

                while (blGo)
                {
                    if (hasMessages())
                    {
                        log(MOD_NAME, "Message found, attempting to notify GUI!");
                        if (attemptPushToGUI())
                        {
                            log(MOD_NAME, "Dispatch OK!");
                        }
                        else
                        {
                            log(MOD_NAME, "Dispatch Failed!");
                        }
                    }

                    try
                    {
                        System.Threading.Thread.Sleep(2000);
                    }
                    catch { }
                }
                log(MOD_NAME, "Stopping GUI Watcher...");

            }
            catch (Exception e)
            {
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
            blGo = false;
            log(MOD_NAME, "Stopping...");
            return true;
        }

        public override int mGetStatus()
        {
            return intStatus;
        }
    }
}
