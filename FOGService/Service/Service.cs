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

namespace FOGService
{
	/// <summary>
	/// Coordinate all FOG modules
	/// </summary>
	public partial class Service  : ServiceBase
	{
		private Thread threadManager;
		private List<AbstractService.AbstractService> modules;
		private ArrayList threads;
		private IniReader ini;
		private String logFilePath;
		private long maxLogSize;
        
		public Service()
		{
		
			maxLogSize = 102400;
			logFilePath = @".\fog.log";
			ini = null;
		
			this.CanHandlePowerEvent = true;
			this.CanShutdown = true;
		}
		
		protected override void OnStart(string[] args)
        {
			try {
				modules = new List<AbstractService.AbstractService>();
			    threads = new ArrayList(5);
			    
			    //Make a thread manager to handle the modules
				threadManager = new Thread(new ThreadStart(startAllSubProcesses));
				threadManager.Priority = ThreadPriority.Normal;
				threadManager.IsBackground = true;
				threadManager.Name = "FOGService";
				threadManager.Start();
				
			} catch( Exception ex ) {
				//Log any errors
				log(ex.Message);
			}
        }

		public void log(String msg)
        {  
			StreamWriter objReader;
			try {
				if (maxLogSize > 0 && logFilePath != null && logFilePath.Length > 0) {
					FileInfo logFile = new FileInfo(logFilePath);
					
					//Delete the log file if it excedes the max log size
					if (logFile.Exists && logFile.Length > maxLogSize)
						logFile.Delete();
					
					//Write message to log file
					objReader = new StreamWriter(logFilePath, true);
					objReader.WriteLine(" " + DateTime.Now.ToShortDateString() + " " + DateTime.Now.ToShortTimeString() + " " + msg);
					objReader.Close();
				}
			} catch {
			} 
        }
		
		private Boolean loadIniFile()
        {
			try {
				ini = new IniReader(AppDomain.CurrentDomain.BaseDirectory + @"etc/config.ini");
				if (ini.isFileOk()) {
					
					//Read and set the max log size
					logFilePath = ini.readSetting("fog_service", "logfile");
					String maxLogSize = ini.readSetting("fog_service", "maxlogsize");
					long output;
					
					if (long.TryParse(maxLogSize, out output)) {
						try {
							this.maxLogSize = long.Parse(maxLogSize);
						} catch (Exception) { 
						}
					}
					return true;
				}
			} catch {
			}
			
			return false;
		}
		
		private String getIPAddress()
		{
			String ipAddress = "";
			try {

				String hostName = Dns.GetHostName();
		
				IPAddress[] ipAddresses = Dns.GetHostEntry(hostName).AddressList;
		
				if (ipAddresses.Length > 0)
					ipAddress = ipAddresses[0].ToString();
			} catch { 
			}
			return ipAddress;
		}		

		private void startAllSubProcesses()
		{
			if (loadIniFile()) {
				log("Starting all sub processes");
				if (Directory.Exists(AppDomain.CurrentDomain.BaseDirectory )) {
					String[] files = Directory.GetFiles(AppDomain.CurrentDomain.BaseDirectory );
					foreach (String file in files) {
						if (file.EndsWith(".dll")) {
							try {
								byte[] buffer = File.ReadAllBytes(file);
								Assembly assembly = Assembly.Load(buffer);
								if (assembly != null) {
									Type[] types = assembly.GetTypes();
									foreach (Type type in types) {
										if (type != null) {
											try {
												Object module = Activator.CreateInstance(type);
												Assembly abstractA = Assembly.LoadFrom(AppDomain.CurrentDomain.BaseDirectory + @"AbstractService.dll");
												Type abstractType = abstractA.GetTypes()[0];
		
												if (module.GetType().IsSubclassOf(abstractType))
													modules.Add((AbstractService.AbstractService)module);
											} catch {
											}
										}
									}
								}
							} catch {
							}
						}
					}
					log(modules.Count + " modules loaded");
		
					if (modules.Count > 0) {
						foreach(AbstractService.AbstractService module in modules) {
							try {
								module.setINIReader(ini);
								
								log(" * Starting " + module.GetType().FullName);
								Thread tmp = new Thread(module.mStart);
								tmp.Priority = ThreadPriority.AboveNormal;
								tmp.IsBackground = true;
								tmp.Start();
								
								threads.Add(tmp);
							} catch (Exception ex) {
								log(ex.Message);
								log(ex.StackTrace);
								log(ex.InnerException.ToString());
								log(ex.ToString());
							}
		
						}
		    
					}
		    
				} else {
					log("Module directory not found");
				}
			} else {
				log("Unable to load settings");
			}
		}
		
		protected override void OnStop()
		{
			log("Service Stop requested");
			foreach (AbstractService.AbstractService module in modules) {
				try {
					module.mStop();
				} catch {
				}
			}
		}

	}
}
