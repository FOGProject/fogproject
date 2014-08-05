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

using FOG;

namespace FOG
{
	/// <summary>
	/// Coordinate all FOG modules
	/// </summary>
	public partial class FOGService  : ServiceBase {
		//Define variables
		private Thread threadManager;
		private int sleepTime = 60;
		private List<AbstractModule> modules;
		private Status status;
		
		//Module status -- used for stopping/starting
		public enum Status {
			Running = 1,
			Stopped = 0
		}
				
		//Define handlers
		private LogHandler logHandler;
		private NotificationHandler notificationHandler;
        
		//service/servicemodule-active.php?sleeptime=1
		
		public FOGService() {
			//Initialize everything
			this.logHandler = new LogHandler(@"\fog.log", 502400);
			initializeModules();
			this.threadManager = new Thread(new ThreadStart(serviceLooper));
			this.status = Status.Stopped;
		}
		
		protected override void OnStart(string[] args) {
			this.status = Status.Running;
			this.threadManager.Priority = ThreadPriority.Normal;
			this.threadManager.IsBackground = true;
			this.threadManager.Name = "FOG Service"; //TODO change this to FOGService (need old format for current testing system)
			this.threadManager.Start();
        }
		
		private void initializeModules() {
			this.modules = new List<AbstractModule>();
			this.modules.Add(new TaskReboot(logHandler));
			this.modules.Add(new SnapinClient(logHandler));
		}


		protected override void OnStop() {
			this.status = Status.Stopped;
		}
		
		private void serviceLooper() {
			while (status.Equals(Status.Running)) {
				foreach(AbstractModule module in modules) {
					logHandler.newLine();
					logHandler.newLine();
					logHandler.divider();
					try {
						module.start();
						
					} catch (Exception ex) {
						logHandler.log(System.Reflection.Assembly.GetExecutingAssembly().GetName().Name, 
						               "Failed to start " + module.getName());
						logHandler.log(System.Reflection.Assembly.GetExecutingAssembly().GetName().Name, 
						               "ERROR: " + ex.Message);
					}
					logHandler.divider();
					logHandler.newLine();
				}
				logHandler.log(System.Reflection.Assembly.GetExecutingAssembly().GetName().Name, 
				               "Sleeping for " + sleepTime.ToString() + " seconds");
				System.Threading.Thread.Sleep(sleepTime * 1000);
			}
		}

	}
}
