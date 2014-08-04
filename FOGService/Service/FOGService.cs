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
		private List<Thread> moduleThreads; //TODO remove this variable if it is never used
		
		//Define handlers
		private CommunicationHandler communicationHandler;
		private LogHandler logHandler;
		private NotificationHandler notificationHandler;
		private ShutdownHandler shutdownHander;
		private UserHandler userHandler;
        
		public FOGService() {
			//Initialize everything
			initializeHandlers();
			initializeModules();
			this.threadManager = new Thread(new ThreadStart(startModules));
		}
		
		protected override void OnStart(string[] args) {
			this.threadManager.Priority = ThreadPriority.Normal;
			this.threadManager.IsBackground = true;
			this.threadManager.Name = "FOG Service"; //TODO change this to FOGService (need old format for current testing system)
			this.threadManager.Start();
        }
		
		private void initializeHandlers() {
			this.logHandler = new LogHandler(@"\fog.log", 102400); //TODO make theses come from the registry
			this.communicationHandler = new CommunicationHandler(logHandler, "http://10.0.7.1"); //TODO make this come from the registry
			this.notificationHandler = new NotificationHandler();
			this.shutdownHander = new ShutdownHandler(logHandler);
			this.userHandler = new UserHandler(logHandler);
		}
		
		private void initializeModules() {
			this.modules = new List<AbstractModule>();
			this.modules.Add(new TaskReboot(communicationHandler, logHandler, notificationHandler, shutdownHander, userHandler));
		}

		//Loop through all modules and attempt to start them
		private void startModules() {
			foreach(AbstractModule module in modules) {
				try {
					Thread moduleThread = new Thread(module.start);
					moduleThread.Priority = ThreadPriority.AboveNormal;
					moduleThread.IsBackground = true;
					moduleThread.Start();
					
					moduleThreads.Add(moduleThread);
				} catch (Exception ex) {
					logHandler.log(System.Reflection.Assembly.GetExecutingAssembly().GetName().Name, 
					               "Failed to start " + module.getName() + ", ERROR: " + ex.Message);
				}
			}
		}
		
		//Loop through all modules and attempt to stop them
		protected override void OnStop() {
			foreach(AbstractModule module in modules) {
				try {
					module.stop();
				} catch (Exception ex) {
					logHandler.log(System.Reflection.Assembly.GetExecutingAssembly().GetName().Name, 
					               "Failed to stop " + module.getName() + ", ERROR: " + ex.Message);
				}
			}
		}

	}
}
