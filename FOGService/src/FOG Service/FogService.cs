using System;
using System.Collections.Generic;
using System.ComponentModel;
using System.Data;
using System.Diagnostics;
using System.ServiceProcess;
using System.Text;

using System.Threading;
using System.Configuration;
using System.IO;
using System.Collections;
using System.Reflection;
using System.Net;
using IniReaderObj;



namespace FOG
{
    public partial class FogService : ServiceBase
    {
        private Thread thrdManager;
        private ArrayList alModules;
        private ArrayList alThreads;
        private IniReader ini;
        private String strLogPath;
        private const String VERSION = "3";
        private long maxLogSize;

        public FogService()
        {
            InitializeComponent();

            maxLogSize = 102400;
            strLogPath = @".\fog.log";
            ini = null;

            this.CanHandlePowerEvent = true;
            this.CanShutdown = true;
        }

        protected override void OnStart(string[] args)
        {
            try
            {
                alModules = new ArrayList(5);
                alThreads = new ArrayList(5);
                thrdManager = new Thread(new ThreadStart(startAllSubProcesses));
                thrdManager.Priority = ThreadPriority.Normal;
                thrdManager.IsBackground = true;
                thrdManager.Name = "FOG Service";
                thrdManager.Start();
            }
            catch( Exception ex )
            {
                lg(ex.Message);
            }
        }

        public void lg(String str)
        {
                
                    StreamWriter objReader;
                    try
                    {
                        if (maxLogSize > 0 && strLogPath != null && strLogPath.Length > 0)
                        {
                            FileInfo f = new FileInfo(strLogPath);
                            if (f.Exists && f.Length > maxLogSize)
                            {
                                f.Delete();
                            }

                            objReader = new StreamWriter(strLogPath, true);
                            objReader.WriteLine(" " + DateTime.Now.ToShortDateString() + " " + DateTime.Now.ToShortTimeString() + " " + str);
                            objReader.Close();
                        }
                    }
                    catch 
                    {

                    }
                
            
        }

        private Boolean loadIniFile()
        {
            try
            {
                ini = new IniReader(AppDomain.CurrentDomain.BaseDirectory + @"etc/config.ini");
                if (ini.isFileOk())
                {
                    strLogPath = ini.readSetting("fog_service", "logfile");
                    String strMaxSize = ini.readSetting("fog_service", "maxlogsize");
                    long output;
                    if (long.TryParse(strMaxSize, out output))
                    {
                        try
                        {
                            maxLogSize = long.Parse(strMaxSize);
                        }
                        catch (Exception)
                        { }
                    }
                    return true;
                }
            }
            catch 
            {
            }
            return false;
        }

        private ArrayList getIPAddress()
        {
            ArrayList arIPs = new ArrayList();
            try
            {
                String strHost = null;
                strHost = Dns.GetHostName();

                IPHostEntry ip = Dns.GetHostEntry(strHost);
                IPAddress[] ipAddys = ip.AddressList;

                if (ipAddys.Length > 0)
                    arIPs.Add( ipAddys[0].ToString() );
            }
            catch
            { }
            return arIPs;
        }

        /*
         * Checks if we should run the setup wizard. 
         */
        private void startAllSubProcesses()
        {
            if (loadIniFile())
            {
                lg("FOG Service Engine Version: " + VERSION);
                lg("Starting all sub processes");
                if (Directory.Exists(AppDomain.CurrentDomain.BaseDirectory ))
                {
                        String[] files = Directory.GetFiles(AppDomain.CurrentDomain.BaseDirectory );
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
                                                        alModules.Add(module);
                                                    }
                                                }
                                                catch
                                                {
                                                }
                                            }
                                        }
                                    }
                                }
                                catch
                                {
                                }
                            }
                        }
                        lg(alModules.Count + " modules loaded");

                        if (alModules.Count > 0)
                        {
                            

                            for (int i = 0; i < alModules.Count; i++)
                            {
                                try
                                {
                                    AbstractFOGService genericModule = (AbstractFOGService)alModules[i];
                                    genericModule.setINIReader(ini);
                                    lg(" * Starting " + genericModule.GetType().FullName);
                                    Thread tmp = new Thread(genericModule.mStart);
                                    tmp.Priority = ThreadPriority.AboveNormal;
                                    tmp.IsBackground = true;
                                    tmp.Start();
                                    alThreads.Add(tmp);
                                }
                                catch (Exception ex)
                                {
                                    lg(ex.Message);
                                    lg(ex.StackTrace);
                                    lg(ex.InnerException.ToString());
                                    lg(ex.ToString());
                                }

                            }
                            
                        }
                    
                }
                else
                {
                    lg("Module directory not found");
                }
            }
            else
            {
                lg("Unable to load settings");
            }
        }

        protected override void OnStop()
        {
            lg("Service Stop requested");
            for (int i = 0; i < alModules.Count; i++)
            {
                try
                {
                    AbstractFOGService genericModule = (AbstractFOGService)alModules[i];
                    genericModule.mStop();
                }
                catch
                {
                }
            }
        }
    }
}
