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
        public const String PROGRAMFILES_VAR = "{{FOG_PF_DIR}}";

        private CheckBox[] arChkBx;
        private ArrayList alModules;
        private String CONFIGPATH;
        private String CONFIGPATHBACKUP;
        
        private String strInstallLocation;
        private String strIPAddress;      
        	
        private bool headless;

        public FrmSetup(String[] args)
        {
            InitializeComponent();
            
            CONFIGPATH = Directory.GetParent(System.Reflection.Assembly.GetExecutingAssembly().Location) + @"\etc\config.ini";
            CONFIGPATHBACKUP = Directory.GetParent(System.Reflection.Assembly.GetExecutingAssembly().Location) + @"\etc\config.ini.backup";
            if (isConfigFilePresent())
            {
                Boolean blFound = !isConfigured();
                if (blFound)
                    loadServiceInfo();
<<<<<<< HEAD:FOG Service/src/FOGServiceConfiguration/FrmSetup.cs
=======
                
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
>>>>>>> Add functional compile script and new installer script:FOG Service/src/FOGServiceConfiguration/FrmSetup.cs
            }
            parseArgs(args);
        }

        private void parseArgs(String[] args)
        {
            if (args != null)
            {
                strInstallLocation = @"c:\program files\fog";
                strIPAddress = "";
                							
                for (int i = 0; i < args.Length; i++)
                {
                    String arg = args[i];
                    if( arg != null) {
                    	if (arg.ToLower().CompareTo("/s") == 0) { //Silent mode
	                        headless = true;
	                    } else if (arg.StartsWith("/ip=", StringComparison.CurrentCultureIgnoreCase)) { 
<<<<<<< HEAD:FOG Service/src/FOGServiceConfiguration/FrmSetup.cs
                    		arg = arg.Replace("/ip=","");
                    		arg = arg.Replace("-ip=",""); 
                    		strIPAddress = arg;
	                    } else if (arg.StartsWith("/pf=", StringComparison.CurrentCultureIgnoreCase)) { //Set installation dir
                    		arg = arg.Replace("/pf=","");
                    		arg = arg.Replace("-pf=",""); 	                    	
                    		strInstallLocation = arg;
	                    	
=======
	                    	strIPAddress = arg.Replace("/ip=","");
	                    } else if (arg.StartsWith("/pf=", StringComparison.CurrentCultureIgnoreCase)) { //Set installation dir
	                    	strInstallLocation = arg.Replace("/pf=","");
>>>>>>> Add functional compile script and new installer script:FOG Service/src/FOGServiceConfiguration/FrmSetup.cs
	                        if (strInstallLocation != null && strInstallLocation.Length > 0) {
	                            strInstallLocation = strInstallLocation.Replace("\"", "");
	                            if (strInstallLocation.EndsWith("\\"))
	                                strInstallLocation.Remove(strInstallLocation.LastIndexOf(@"\"));
	                        }
                    	} else {
                    		if(alModules != null) {
<<<<<<< HEAD:FOG Service/src/FOGServiceConfiguration/FrmSetup.cs
                    			arg = arg.Replace("/","");
                    			arg = arg.Replace("-","");
	                    		foreach(SubClassMenuItem component in alModules) {
                    				if(arg.StartsWith(component.getFileName().ToLower())) {
                    					if(arg.ToLower().EndsWith("false")) //no point in handling true because each component is enabled by default
=======
	                    		foreach(SubClassMenuItem component in alModules) {
                    				if(arg.Contains(component.getFileName().ToLower())) {
                    					if(arg.ToLower().Contains("false")) //no point in handling true because each component is enabled by default
>>>>>>> Add functional compile script and new installer script:FOG Service/src/FOGServiceConfiguration/FrmSetup.cs
	                    					component.setActive(false);
	                    			}
	                    		}
                    		}
                    	}
                    }
                }
            }
        }

        public Boolean isQuiet()
        {
            return headless;
        }

        public Boolean writeQuiet()
        {
        	
			for (int i = 0; i < arChkBx.Length; i++) {
        		try {
        			if(alModules != null) {
				    	foreach(SubClassMenuItem component in alModules) {
        					if(!component.getActive()) {
        						String strF = component.getFile();
						        if (File.Exists(strF))
						        {
						            File.Delete(strF);
						            if (File.Exists(strF)) {
						                Console.WriteLine("Error removing module!\nYou must manually delete:");
						           		Console.WriteLine(strF);
						            } else {
						                File.Create(strF);
						            }
						        }
        					}
        				}
        			}
			    }
			    catch (Exception ex)
			    {
			        Console.WriteLine(ex.Message);
				}
            }
            return writeFile(strIPAddress, strInstallLocation);
        }

        public Boolean isConfigFilePresent()
        {
            try
            {
                return (File.Exists(CONFIGPATH));
            }
            catch (Exception)
            {
                return false;
            }
        }

        public Boolean isConfigured()
        {
            if (File.Exists(CONFIGPATH))
            {
                String[] strConfig = File.ReadAllLines(CONFIGPATH);
                Boolean found = false;
                for (int i = 0; i < strConfig.Length; i++)
                {
                    if (strConfig[i].Contains("x.x.x.x"))
                    {
                        found = true;
                        break;
                    }
                }
                return !found;
            }
            return false;
        }

        private void FrmSetup_Load(object sender, EventArgs e)
        { 
            pnlIP.Dock = DockStyle.Fill;
            pnlDone.Dock = DockStyle.Fill;
            pnlDone.Visible = false;
            btnDone.Left = btnSave.Left;
            btnDone.Top = btnSave.Top;
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

        private Boolean writeFile(String address, String pf)
        {
            if (address != null && pf != null)
            {
                String[] strConfig = File.ReadAllLines(CONFIGPATH);
                Boolean blWrite = false;
                for (int i = 0; i < strConfig.Length; i++)
                {
                    if (strConfig[i].Contains("x.x.x.x"))
                    {
                        strConfig[i] = strConfig[i].Replace("x.x.x.x", address.Trim());
                        blWrite = true;
                    }
                    if (strConfig[i].Contains(PROGRAMFILES_VAR))
                    {
                        strConfig[i] = strConfig[i].Replace(PROGRAMFILES_VAR, pf);
                    }
                }

                if (blWrite)
                {
                    makeBackup();
                    File.WriteAllLines(CONFIGPATH, strConfig);
                }

                return blWrite;
            }
            return false;
        }

        private void btnSave_Click(object sender, EventArgs e)
        {
            String strIP = txtIP.Text;
            if (strIP != null )
            {
                writeFile(strIP, strInstallLocation);
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
                                else
                                    File.Create(strF);
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