using System;
using System.Collections.Generic;
using System.Windows.Forms;

namespace FOG
{
    static class Program
    {
        /// <summary>
        /// The main entry point for the application.
        /// </summary>
        [STAThread]
        static void Main(String[] args)
        {
            try
            {
                Application.EnableVisualStyles();
                Application.SetCompatibleTextRenderingDefault(false);
                FrmSetup frm = new FrmSetup(args);
                if (frm.isConfigFilePresent())
                {
                    if (!frm.isConfigured())
                    {
                        if (frm.isQuiet())
                        {
                            frm.writeQuiet();
                        }
                        else
                        {
                            if (frm.isConfigFilePresent())
                                Application.Run(frm);
                        }
                    }
                }
                else
                        System.Windows.Forms.MessageBox.Show("Unable to locate config file!");
            }
            catch (Exception e)
            {
                System.Windows.Forms.MessageBox.Show(e.Message);
                System.Windows.Forms.MessageBox.Show(e.StackTrace);
            }
        }
    }
}