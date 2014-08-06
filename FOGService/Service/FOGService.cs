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
		
		private PipeServer pipeServer;
		
		//Module status -- used for stopping/starting
		public enum Status {
			Running = 1,
			Stopped = 0
		}
		
		public FOGService() {
			//Initialize everything
			//CommunicationHandler.setServerAddress("http://10.0.7.1");
			CommunicationHandler.setServerAddress("http://192.168.4.111");
			initializeModules();
			this.threadManager = new Thread(new ThreadStart(serviceLooper));
			this.status = Status.Stopped;
			this.pipeServer = new PipeServer(@"\\.\pipe\FOG_PIPE5");
			this.pipeServer.messageReceived += new PipeServer.messageReceivedHandler(pipeServer_MessageReceived);
			this.pipeServer.start();
			Thread.Sleep(2000);
			this.pipeServer.sendMessage("Heeeey there!");
			NotificationHandler.createNotification(new Notification("Test", "Terer", 60));
		}
		
		private void pipeServer_MessageReceived(Client client, String message) {
			LogHandler.log("PipeServer", "Message recieved");
			LogHandler.log("PipeServer",message);
		}

		protected override void OnStart(string[] args) {
			this.status = Status.Running;
			
			this.threadManager.Priority = ThreadPriority.Normal;
			this.threadManager.IsBackground = true;
			this.threadManager.Name = "FOGService";
			this.threadManager.Start();
        }
		
		private void initializeModules() {
			this.modules = new List<AbstractModule>();
			this.modules.Add(new TaskReboot());
			this.modules.Add(new SnapinClient());
		}


		protected override void OnStop() {
			this.status = Status.Stopped;
		}
		
		//Run each service
		private void serviceLooper() {
			//Only run the service if there wasn't a stop or shutdown request
			while (status.Equals(Status.Running) && !ShutdownHandler.isShutdownPending()) {
				foreach(AbstractModule module in modules) {
					if(ShutdownHandler.isShutdownPending())
						break;
					LogHandler.newLine();
					LogHandler.newLine();
					LogHandler.divider();
					try {
						module.start();
						
					} catch (Exception ex) {
						LogHandler.log(System.Reflection.Assembly.GetExecutingAssembly().GetName().Name, 
						               "Failed to start " + module.getName());
						LogHandler.log(System.Reflection.Assembly.GetExecutingAssembly().GetName().Name, 
						               "ERROR: " + ex.Message);
					}
					LogHandler.divider();
					LogHandler.newLine();
				}
				
				int sleepTime = getSleepTime();
				LogHandler.log(System.Reflection.Assembly.GetExecutingAssembly().GetName().Name, 
				               "Sleeping for " + sleepTime.ToString() + " seconds");
				System.Threading.Thread.Sleep(sleepTime * 1000);
			}
		}
		
		private int getSleepTime() {
			LogHandler.log(System.Reflection.Assembly.GetExecutingAssembly().GetName().Name, 
				               "Getting sleep duration...");
			
			Response sleepResponse = CommunicationHandler.getResponse("/fog/service/servicemodule-active.php?sleeptime=1");
			//Default time
			try {
				if(!sleepResponse.wasError() && !sleepResponse.getField("#sleep").Equals("")) {
					int sleepTime = int.Parse(sleepResponse.getField("#sleep"));
					if(sleepTime >= sleepDefaultTime) 
						return sleepTime;
				}
			} catch (Exception ex) {
				LogHandler.log(System.Reflection.Assembly.GetExecutingAssembly().GetName().Name,
			 	                    "Failed to parse sleep time");
				LogHandler.log(System.Reflection.Assembly.GetExecutingAssembly().GetName().Name,
			 	                    "ERROR: " + ex.Message);				
			}
			LogHandler.log(System.Reflection.Assembly.GetExecutingAssembly().GetName().Name,
			 	                    "Using default sleep time");	
			return sleepDefaultTime;			
		}

	}
}
