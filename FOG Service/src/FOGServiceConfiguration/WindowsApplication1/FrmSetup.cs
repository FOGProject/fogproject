using System;
using System.Collections.Generic;
using System.ComponentModel;
using System.Data;
using System.Drawing;
using System.Text;
using System.Windows.Forms;

using System.Configuration;
using System.IO;
using System.Collections;
using System.Reflection;

namespace FOG
{
    public partial class FrmSetup : Form
    {
        private CheckBox[] arChkBx;
        private ArrayList alModules;
        private String CONFIGPATH;
        private String CONFIGPATHBACKUP;

        public FrmSetup()
        {
            InitializeComponent();
        }

        private void FrmSetup_Load(object sender, EventArgs e)
        {

            CONFIGPATH = Directory.GetParent(System.Reflection.Assembly.GetExecutingAssembly().Location) +@"\etc\config.ini";
            CONFIGPATHBACKUP = Directory.GetParent(System.Reflection.Assembly.GetExecutingAssembly().Location) + @"\etc\config.ini.backup";
            
            pnlIP.Dock = DockStyle.Fill;
            pnlDone.Dock = DockStyle.Fill;
            pnlDone.Visible = false;
            btnDone.Left = btnSave.Left;
            btnDone.Top = btnSave.Top;
            if (File.Exists(CONFIGPATH))
            {
                Boolean blFound = false;
                String[] strConfig = File.ReadAllLines(CONFIGPATH);
                for (int i = 0; i < strConfig.Length; i++)
                {
                    if (strConfig[i].Contains("x.x.x.x"))
                    {
                        loadServiceInfo();
                        blFound = true;
                    }
                }

                if (!blFound)
                {
                    MessageBox.Show("It appears that the FOG service has already been configured");
                    this.Close();
                }
                
            }
            else
            {
                MessageBox.Show("Fatal Error:\nUnable to locate coniguration file for FOG Service!");
                this.Close();
            }
        }

        private void loadServiceInfo()
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
                                            Assembly abstractA = Assembly.LoadFrom(AppDomain.CurrentDomain.BaseDirectory + @"AbstractFOGService.dll");
                                            Type t = abstractA.GetTypes()[0];

                                            if (module.GetType().IsSubclassOf(t))
                                            {
                                                alModules.Add(new SubClassMenuItem(files[i], ((AbstractFOGService)module).mGetDescription()));
                                            }
                                            t = null;
                                            abstractA = null;
                                            module = null;
                                        }
                                        catch
                                        {
                                        }
                                    }
                                }
                            }
                            assemb = null;
                        }
                        catch
                        {
                        }
                    }
                }

               
                if (alModules.Count > 0)
                {
                    arChkBx = new CheckBox[alModules.Count];
                    for (int i = 0; i < alModules.Count; i++)
                    {
                        try
                        {
                            SubClassMenuItem sub = (SubClassMenuItem)alModules[i];
                            arChkBx[i] = new CheckBox();
                            arChkBx[i].Text = sub.getDescription();
                            arChkBx[i].Width = pnlServices.Width - 10;
                            arChkBx[i].Height = 40;
                            arChkBx[i].Checked = true;
                            pnlServices.Controls.Add(arChkBx[i]);
                            pnlServices.Refresh();
                        }
                        catch (Exception ex)
                        {
                            MessageBox.Show(ex.Message);
                        }

                    }
                }
                else
                {
                    Label noneFound = new Label();
                    noneFound.Width = pnlServices.Width - 10;
                    noneFound.Text = "No sub services were found!";
                    pnlServices.Controls.Add(noneFound);
                }
            }
                    
        }

        private void makeBackup()
        {
            if (File.Exists(CONFIGPATHBACKUP))
            {
                File.Delete(CONFIGPATHBACKUP);
            }

            File.Copy(CONFIGPATH, CONFIGPATHBACKUP);
        }

        private void btnSave_Click(object sender, EventArgs e)
        {


                String strIP = txtIP.Text;
                if (strIP != null && strIP.Length > 0)
                {
                    String[] strConfig = File.ReadAllLines(CONFIGPATH);
                    Boolean blWrite = false;
                    for (int i = 0; i < strConfig.Length; i++)
                    {
                        if (strConfig[i].Contains("x.x.x.x"))
                        {
                            strConfig[i] = strConfig[i].Replace("x.x.x.x", strIP.Trim());
                            blWrite = true;
                        }
                    }

                    if (blWrite)
                    {
                        makeBackup();
                        File.WriteAllLines(CONFIGPATH, strConfig);
                    }

                    for (int i = 0; i < arChkBx.Length; i++)
                    {
                        if (!arChkBx[i].Checked)
                        {
                            try
                            {
                                String strF = ((SubClassMenuItem)alModules[i]).getFile();
                                if (File.Exists(strF))
                                {
                                    File.Delete(strF);
                                    if (File.Exists(strF))
                                        MessageBox.Show("Error removing module!\nYou must manually delete:\n" + strF);
                                }
                            }
                            catch (Exception ex)
                            {
                                MessageBox.Show(ex.Message);
                            }
                        }
                    }


                    pnlIP.Visible = false;
                    pnlDone.Visible = true;
                }
                else
                    MessageBox.Show("Unable to save changes:\nPlease enter an IP address or hostname.");
            }

        private void btnDone_Click(object sender, EventArgs e)
        {
            this.Close();
        }
        
    }
}