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
		private List<AbstractModule> modules;
		private Status status;
		private int sleepDefaultTime = 60;
		
		//Module status -- used for stopping/starting
		public enum Status {
			Running = 1,
			Stopped = 0
		}
				
		//Define handlers
		private LogHandler logHandler;
		private NotificationHandler notificationHandler;
		private ShutdownHandler shutdownHandler;
		private CommunicationHandler communicationHandler;
		private UserHandler userHandler;
		private EncryptionHandler encryptionHandler;

		
		public FOGService() {
			//Initialize everything
			this.logHandler = new LogHandler(@"\fog.log", 502400);
			this.notificationHandler = new NotificationHandler();
			this.shutdownHandler = new ShutdownHandler(logHandler);
			this.communicationHandler = new CommunicationHandler(logHandler, "http://10.0.7.1");
			this.userHandler = new UserHandler(logHandler);
			this.encryptionHandler = new EncryptionHandler(logHandler);
			
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
			this.modules.Add(new TaskReboot(this.logHandler, this.notificationHandler, this.shutdownHandler, 
			                                this.communicationHandler, this.userHandler, this.encryptionHandler));
			this.modules.Add(new SnapinClient(this.logHandler, this.notificationHandler, this.shutdownHandler, 
			                                  this.communicationHandler, this.userHandler, this.encryptionHandler));
		}


		protected override void OnStop() {
			this.status = Status.Stopped;
		}
		
		//Run each service
		private void serviceLooper() {
			//Only run the service if there wasn't a stop or shutdown request
			while (status.Equals(Status.Running) && !this.shutdownHandler.isShutdownPending()) {
				foreach(AbstractModule module in modules) {
					if(this.shutdownHandler.isShutdownPending())
						break;
					this.logHandler.newLine();
					this.logHandler.newLine();
					this.logHandler.divider();
					try {
						module.start();
						
					} catch (Exception ex) {
						this.logHandler.log(System.Reflection.Assembly.GetExecutingAssembly().GetName().Name, 
						               "Failed to start " + module.getName());
						this.logHandler.log(System.Reflection.Assembly.GetExecutingAssembly().GetName().Name, 
						               "ERROR: " + ex.Message);
					}
					this.logHandler.divider();
					this.logHandler.newLine();
				}
				
				int sleepTime = getSleepTime();
				this.logHandler.log(System.Reflection.Assembly.GetExecutingAssembly().GetName().Name, 
				               "Sleeping for " + sleepTime.ToString() + " seconds");
				System.Threading.Thread.Sleep(sleepTime * 1000);
			}
		}
		
		private int getSleepTime() {
			this.logHandler.log(System.Reflection.Assembly.GetExecutingAssembly().GetName().Name, 
				               "Getting sleep duration...");
			
			Response sleepResponse = this.communicationHandler.getResponse("/fog/service/servicemodule-active.php?sleeptime=1");
			//Default time
			try {
				if(!sleepResponse.wasError() && !sleepResponse.getField("#sleep").Equals("")) {
					int sleepTime = int.Parse(sleepResponse.getField("#sleep"));
					if(sleepTime >= sleepDefaultTime) 
						return sleepTime;
				}
			} catch (Exception ex) {
				this.logHandler.log(System.Reflection.Assembly.GetExecutingAssembly().GetName().Name,
			 	                    "Failed to parse sleep time");
				this.logHandler.log(System.Reflection.Assembly.GetExecutingAssembly().GetName().Name,
			 	                    "ERROR: " + ex.Message);				
			}
			this.logHandler.log(System.Reflection.Assembly.GetExecutingAssembly().GetName().Name,
			 	                    "Using default sleep time");	
			return sleepDefaultTime;			
		}

	}
}
