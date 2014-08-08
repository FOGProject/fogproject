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
		private Thread pipeThread;
		private List<AbstractModule> modules;
		private Status status;
		private int sleepDefaultTime = 60;
		
		private PipeServer pipeServer;
		
		private const String LOG_NAME = "Service";
		
		//Module status -- used for stopping/starting
		public enum Status {
			Running = 1,
			Stopped = 0
		}
		
		public FOGService() {
			//Initialize everything
			CommunicationHandler.setServerAddress("http://10.0.7.1");

			initializeModules();
			this.threadManager = new Thread(new ThreadStart(serviceLooper));
			this.status = Status.Stopped;
			
			//Setup the piper server
			this.pipeThread = new Thread(new ThreadStart(pipeHandler));
			this.pipeServer = new PipeServer("fog_pipe");
			this.pipeServer.MessageReceived += new PipeServer.MessageReceivedHandler(pipeServer_MessageReceived);
		}
		
		//This is run by the pipe thread, it will send out notifications to the tray
		private void pipeHandler() {
			while (true) {
				if(!this.pipeServer.isRunning()) 
					this.pipeServer.start();
				
				int duration = 1;
				
				if(NotificationHandler.getNotifications().Count > 0) {
					//Split up the notification into 3 messages: Title, Message, and Duration
					this.pipeServer.sendMessage("TLE:" + NotificationHandler.getNotifications()[0].getTitle());
					Thread.Sleep(750);
					this.pipeServer.sendMessage("MSG:" + NotificationHandler.getNotifications()[0].getMessage());
					Thread.Sleep(750);
					duration = NotificationHandler.getNotifications()[0].getDuration();
					this.pipeServer.sendMessage("DUR:" + NotificationHandler.getNotifications()[0].getDuration().ToString());
					NotificationHandler.removeNotification(0);
				} 
				//Sleep the time the notification should display for + 1 second (in order to let the notification disapear before sending another
				Thread.Sleep((duration+1) * 1000);
			}

		}
		
		//Handle recieving a message
		private void pipeServer_MessageReceived(Client client, String message) {
			LogHandler.log("PipeServer", "Message recieved");
			LogHandler.log("PipeServer",message);
		}

		//Called when the service starts
		protected override void OnStart(string[] args) {
			this.status = Status.Running;
			
			//Start the pipe server
			this.pipeThread.Priority = ThreadPriority.Normal;
			this.pipeThread.Start();
			
			//Start the main thread that handles all modules
			this.threadManager.Priority = ThreadPriority.Normal;
			this.threadManager.IsBackground = true;
			this.threadManager.Name = "FOGService";
			this.threadManager.Start();
        }
		
		//Load all of the modules
		private void initializeModules() {
			this.modules = new List<AbstractModule>();
			this.modules.Add(new TaskReboot());
			this.modules.Add(new SnapinClient());
		}
		
		//Called when the service stops
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
					
					//Log file formatting
					LogHandler.newLine();
					LogHandler.newLine();
					LogHandler.divider();
					
					try {
						module.start();
					} catch (Exception ex) {
						LogHandler.log(LOG_NAME, "Failed to start " + module.getName());
						LogHandler.log(LOG_NAME, "ERROR: " + ex.Message);
					}
					
					//Log file formatting
					LogHandler.divider();
					LogHandler.newLine();
				}
				
				//Once all modules have been run, sleep for the set time
				int sleepTime = getSleepTime();
				LogHandler.log(LOG_NAME, "Sleeping for " + sleepTime.ToString() + " seconds");
				System.Threading.Thread.Sleep(sleepTime * 1000);
			}
		}
		
		//Get the time to sleep from the FOG server, if it cannot it will use the default time
		private int getSleepTime() {
			LogHandler.log(LOG_NAME, "Getting sleep duration...");
			
			Response sleepResponse = CommunicationHandler.getResponse("/fog/service/servicemodule-active.php");
			
			try {
				if(!sleepResponse.wasError() && !sleepResponse.getField("#sleep").Equals("")) {
					int sleepTime = int.Parse(sleepResponse.getField("#sleep"));
					if(sleepTime >= this.sleepDefaultTime) {
						return sleepTime;
					} else {
						LogHandler.log(LOG_NAME, "Sleep time set on the server is below the minimum of " + this.sleepDefaultTime.ToString());
					}
				}
			} catch (Exception ex) {
				LogHandler.log(LOG_NAME,"Failed to parse sleep time");
				LogHandler.log(LOG_NAME,"ERROR: " + ex.Message);				
			}
			
			LogHandler.log(LOG_NAME,"Using default sleep time");	
			
			return this.sleepDefaultTime;			
		}

	}
}
