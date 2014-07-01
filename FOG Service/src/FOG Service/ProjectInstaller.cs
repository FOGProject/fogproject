using System;
using System.Collections.Generic;
using System.ComponentModel;
using System.Configuration.Install;
using System.IO;
using Microsoft.Win32;
using System.Collections.Specialized;

namespace Dist88ServiceManager
{
    [RunInstaller(true)]
    public partial class ProjectInstaller : Installer
    {
        private const string EXE = @"./FOGServiceConfig.exe";

        public ProjectInstaller()
        {
            InitializeComponent();
            serviceInstaller.StartType = System.ServiceProcess.ServiceStartMode.Automatic;
            serviceProcessInstaller.Account = System.ServiceProcess.ServiceAccount.LocalSystem;
        }

        private void serviceInstaller_AfterInstall(object sender, InstallEventArgs e)
        {
            try
            {
                RegistryKey ckey = Registry.LocalMachine.OpenSubKey(@"SYSTEM\CurrentControlSet\Services\Fog Service", true);
                if (ckey != null)
                {
                    if (ckey.GetValue("Type") != null)
                    {
                        ckey.SetValue("Type", ((int)ckey.GetValue("Type") | 256));
                    }
                }
            }
            catch { }

            //try
            //{
                //String cf = Environment.GetFolderPath(Environment.SpecialFolder.ProgramFiles) + @"\FOG\FOGServiceConfig.exe";
                //System.Windows.Forms.MessageBox.Show( Context.Parameters.Count + " count" );
                //System.Windows.Forms.MessageBox.Show(System.Environment.GetEnvironmentVariable("fog"));

                //foreach ( String myString in Context.Parameters.Keys )
                //{

                 //   System.Windows.Forms.MessageBox.Show(myString);
                //}

                //if (Context.Parameters.ContainsKey("default-fogserver"))
                //{
                //    System.Windows.Forms.MessageBox.Show("Using default hostname of FOGSERVER.");
               // }
                //else
                //{
                //    if (File.Exists(cf))
                //    {
                //        System.Diagnostics.Process.Start(cf);
                //    }
                //    else
                //        System.Windows.Forms.MessageBox.Show("The FOG Service configuration application was not found in the typical location.  If you changed the installation directory of the FOG Service, then please run the FOGServiceConfig.exe in that directory.");
                //}
            //}
            //catch { }
        }

        private void serviceProcessInstaller_AfterInstall(object sender, InstallEventArgs e)
        {

        }

    }
}
