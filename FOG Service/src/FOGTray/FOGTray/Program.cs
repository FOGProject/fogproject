using System;
using System.Collections.Generic;
using System.Windows.Forms;
using System.Diagnostics;

namespace FOGTray
{
    static class Program
    {
        /// <summary>
        /// The main entry point for the application.
        /// </summary>
        [STAThread]
        static void Main()
        {
            Process prc = Process.GetCurrentProcess();
            string strProcName = prc.ProcessName;

            if (Process.GetProcessesByName(strProcName).Length == 1)
            {
                Application.EnableVisualStyles();
                Application.SetCompatibleTextRenderingDefault(false);
                Application.Run(new frmMain());
            }
            else
                MessageBox.Show("An instance of FOG Tray is already running!");
        }
    }
}