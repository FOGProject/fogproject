using System;
using System.Collections.Generic;
using System.Collections;
using System.ComponentModel;
using System.Data;
using System.Drawing;
using System.Text;
using System.Windows.Forms;
using System.IO;
using AbstractTrayModule;
using System.Reflection;

namespace FOGTray
{
    public partial class frmMain : Form
    {
        private const String VERSION = "0.1";
        private ArrayList alModules;

        public frmMain()
        {
            InitializeComponent();
        }

        private void frmMain_Load(object sender, EventArgs e)
        {
            Hide();
            loadMenuItems();
        }

        private void loadMenuItems()
        {
            alModules = new ArrayList();
            if (Directory.Exists(AppDomain.CurrentDomain.BaseDirectory))
            {
                String[] files = Directory.GetFiles(AppDomain.CurrentDomain.BaseDirectory);
                for (int i = 0; i < files.Length; i++)
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
                                    if (type[z] != null)
                                    {
                                        try
                                        {
                                            Object module = Activator.CreateInstance(type[z]);
                                            Assembly abstractA = Assembly.LoadFrom(AppDomain.CurrentDomain.BaseDirectory + @"AbstractFOGTrayModule.dll");
                                            Type t = abstractA.GetTypes()[0];
                                            if (module.GetType().IsSubclassOf(t))
                                            {
                                                alModules.Add(module);
                                            }
                                        }
                                        catch { }

                                    }
                                }
                            }
                        }
                        catch { }
                    }
                }

                try
                {
                    System.Threading.Thread.Sleep(2000);
                }
                catch { }

                if (alModules.Count > 0)
                {
                    for (int i = 0; i < alModules.Count; i++)
                    {
                        try
                        {
                            AbstractFOGTrayModule genericModule = (AbstractFOGTrayModule)alModules[i];
                            genericModule.start();
                            menuStrip.Items.Insert(2, genericModule.getMenuSegment());
                        }
                        catch { }
                    }
                }
                else
                {
                    menuStrip.Items.Insert(2, new ToolStripMenuItem("No Modules Found!"));
                }
            }
        }

        private void closeToolStripMenuItem_Click(object sender, EventArgs e)
        {
            if (alModules.Count > 0)
            {
                for (int i = 0; i < alModules.Count; i++)
                {
                    try
                    {
                        AbstractFOGTrayModule genericModule = (AbstractFOGTrayModule)alModules[i];
                        genericModule.stop();
                    }
                    catch { }
                }
            }
            Close();
        }

        private void toolStripMenuItem1_Click(object sender, EventArgs e)
        {
            MessageBox.Show("FOG Computer Cloning Solution\n\nFog Tray Version: " + VERSION + "\nReleased Under GPL Version 3\n\nCreated By:\nChuck Syperski &\nJian Zhang", "FOG", MessageBoxButtons.OK, MessageBoxIcon.Information);
        }


    }
}