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
		private Thread threadManager;
		private List<AbstractModule> modules;
		private List<Thread> moduleThreads;
		
		private CommunicationHandler communicationHandler;
		private LogHandler logHandler;
		private NotificationHandler notificationHandler;
		private ShutdownHandler shutdownHander;
		private UserHandler userHandler;
        
		public FOGService() {
			initializeHandlers();
			initializeModules();
			this.threadManager = new Thread(new ThreadStart(startModules));
		}
		
		protected override void OnStart(string[] args) {
			this.threadManager.Priority = ThreadPriority.Normal;
			this.threadManager.IsBackground = true;
			this.threadManager.Name = "FOG Service";
			this.threadManager.Start();
        }
		
		private void initializeHandlers() {
			this.logHandler = new LogHandler(@"\fog.log", 102400);
			this.communicationHandler = new CommunicationHandler(logHandler, "http://10.0.7.1");
			this.notificationHandler = new NotificationHandler();
			this.shutdownHander = new ShutdownHandler(logHandler);
			this.userHandler = new UserHandler(logHandler);
		}
		
		private void initializeModules() {
			this.modules = new List<AbstractModule>();
			this.modules.Add(new TaskReboot(communicationHandler, logHandler, notificationHandler, 
			                                shutdownHander, userHandler));
		}

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
