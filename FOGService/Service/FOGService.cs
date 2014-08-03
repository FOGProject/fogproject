using System;
using System.Collections.Generic;
using System.ComponentModel;
using System.Data;
using System.Diagnostics;
using System.ServiceProcess;

using System.Threading;
using System.Configuration;
using System.IO;
using System.Collections;
using System.Reflection;
using System.Net;
using IniReaderObj;

using FOG;

namespace FOG
{
	/// <summary>
	/// Coordinate all FOG modules
	/// </summary>
	public partial class FOGService  : ServiceBase
	{
		private Thread threadManager;
		private List<AbstractService> modules;
		private List<Thread> threads;
		private IniReader ini;
		private String logFilePath;
		private long maxLogSize;
        
		public FOGService()
		{
			maxLogSize = 102400;
			logFilePath = @".\fog.log";
		
			modules = new List<AbstractService>();
			threads = new List<Thread>();
				
			//Setup the thread manager
			threadManager = new Thread(new ThreadStart(startAllSubProcesses));
			threadManager.Priority = ThreadPriority.Normal;
			threadManager.IsBackground = true;
			threadManager.Name = "FOGService";
			
			this.CanHandlePowerEvent = true;
			this.CanShutdown = true;
		}
		
		protected override void OnStart(string[] args)
        {
			try {
				threadManager.Start();
			} catch(Exception ex) {
				//Log any errors
				log(ex.Message);
			}
        }

		public void log(String msg)
        {  
			StreamWriter logWriter;
			try {
				FileInfo logFile = new FileInfo(logFilePath);
					
				//Delete the log file if it excedes the max log size
				if (logFile.Exists && logFile.Length > maxLogSize)
					logFile.Delete();
					
				//Write message to log file
				logWriter = new StreamWriter(logFilePath, true);
				logWriter.WriteLine(" " + DateTime.Now.ToShortDateString() + " " + DateTime.Now.ToShortTimeString() + " " + msg);
				logWriter.Close();
			} catch {
				//If logging fails then nothing can really be done to silently notify the user
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
						} catch (Exception ex) { 
							log("Error parsing maxlogsize from ini file: " + ex.Message);
						}
					}
					return true;
				}
			} catch (Exception ex) {
				log("Erorr parsing ini file: " + ex.Message);
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
			}  catch (Exception ex) {
				log("Error obtaining host IP address: " + ex.Message);
			}
			
			return ipAddress;
		}		

		private void startAllSubProcesses()
		{
			log("Starting all sub processes");
			if (loadIniFile()) {
				if(loadModules()) {
					log(modules.Count + " modules loaded");
					
					if (modules.Count > 0) {
						foreach(AbstractService module in modules) {
							try {
								module.setINIReader(ini);
								
								log("---> Starting " + module.GetType().FullName);
								
								Thread moduleThread = new Thread(module.start);
								moduleThread.Priority = ThreadPriority.AboveNormal;
								moduleThread.IsBackground = true;
								moduleThread.Start();
								
								threads.Add(moduleThread);
							} catch (Exception ex) {
								log(ex.Message);
								log(ex.StackTrace);
								log(ex.InnerException.ToString());
								log(ex.ToString());
							}
		
						}
		    
					}
		    
				}
			}
		}
		
		private Boolean loadModules() {
			log("Loading modules");
			if (Directory.Exists(AppDomain.CurrentDomain.BaseDirectory )) {
				String[] files = Directory.GetFiles(AppDomain.CurrentDomain.BaseDirectory);
				foreach (String file in files) {
					if (file.EndsWith(".dll")) {
						try {
							Assembly fileAssembly = Assembly.Load(File.ReadAllBytes(file));
							if (fileAssembly != null) {
								Type[] types = fileAssembly.GetTypes();
								foreach (Type type in types) {
									if (type != null) {
										try {
											Object module = Activator.CreateInstance(type);
											Assembly abstractAssembly = 
											Assembly.LoadFrom(AppDomain.CurrentDomain.BaseDirectory + @"AbstractService.dll");
											Type abstractType = abstractAssembly.GetTypes()[0];
				
											if (module.GetType().IsSubclassOf(abstractType)) {
												modules.Add((AbstractService)module);
											}
													
										} catch { }
									}
								}
							}
						} catch { }
					}
				}
				return true;
			} else {
				log("Module directory not found");
			}
			return false;
		}
		
		protected override void OnStop()
		{
			log("Service Stop requested");
			foreach (AbstractService module in modules) {
				try {
					module.stop();
					log("---> Successfully stopped " + module.getName());
				} catch (Exception ex) {
					log("---> Error stopping " + module.getName() + " : " + ex.Message);
				}
			}
		}

	}
}
