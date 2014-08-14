using System;
using System.Collections.Generic;
using Microsoft.Win32;
using System.ComponentModel;
using System.Data;
using System.Diagnostics;
using System.Threading;
using System.Configuration;
using System.IO;
using System.Collections;
using System.Reflection;
using System.Net;

namespace FOG {
	
	/// <summary>
	/// Coordinate all user specific FOG modules
	/// </summary>	

	class FOGUserService {
		
		//Define variables		
		private static Thread threadManager;
		private static List<AbstractModule> modules;
		private static Thread notificationPipeThread;	
		private static PipeServer notificationPipe;		
		private const String LOG_NAME = "UserService";
		private static int sleepDefaultTime = 60;		
		private static Status status;		
		
		public static void Main(string[] args) {
			//Initialize everything
			LogHandler.setFilePath(Environment.ExpandEnvironmentVariables("%userprofile%") + @"\fog_user.log");
			LogHandler.log(LOG_NAME, "Initializing");
			if(RegistryHandler.getSystemSetting("Server") != null && RegistryHandler.getSystemSetting("WebRoot") != null && 
			   RegistryHandler.getSystemSetting("Tray") != null && RegistryHandler.getSystemSetting("HTTPS") != null) {
				
				CommunicationHandler.setServerAddress(RegistryHandler.getSystemSetting("HTTPS"), 
				                                      RegistryHandler.getSystemSetting("Server"), 
				                                      RegistryHandler.getSystemSetting("WebRoot"));
	
				initializeModules();
				threadManager = new Thread(new ThreadStart(serviceLooper));
				status = Status.Stopped;
				
				//Setup the piper server
				notificationPipeThread = new Thread(new ThreadStart(notificationPipeHandler));
				notificationPipe = new PipeServer("fog_pipe_user_" +  UserHandler.getCurrentUser());
				notificationPipe.MessageReceived += new PipeServer.MessageReceivedHandler(notificationPipeServer_MessageReceived);			
			
				
				status = Status.Running;
				
				//Start the pipe server
				notificationPipeThread.Priority = ThreadPriority.Normal;
				notificationPipeThread.Start();
			
				
				//Start the main thread that handles all modules
				threadManager.Priority = ThreadPriority.Normal;
				threadManager.IsBackground = true;
				threadManager.Start();
				if(RegistryHandler.getSystemSetting("Tray").Trim().Equals("1")) {
					startTray();
				}
			} else {
				LogHandler.log(LOG_NAME, "Regisitry keys are not set");
			}
		}

		//Module status -- used for stopping/starting
		public enum Status {
			Running = 1,
			Stopped = 0
		}
		
		//This is run by the pipe thread, it will send out notifications to the tray
		private static void notificationPipeHandler() {
			while (true) {
				if(!notificationPipe.isRunning()) 
					notificationPipe.start();			
				
				
				if(NotificationHandler.getNotifications().Count > 0) {
					//Split up the notification into 3 messages: Title, Message, and Duration
					notificationPipe.sendMessage("TLE:" + NotificationHandler.getNotifications()[0].getTitle());
					Thread.Sleep(750);
					notificationPipe.sendMessage("MSG:" + NotificationHandler.getNotifications()[0].getMessage());
					Thread.Sleep(750);
					notificationPipe.sendMessage("DUR:" + NotificationHandler.getNotifications()[0].getDuration().ToString());
					NotificationHandler.removeNotification(0);
				} 
				
				Thread.Sleep(3000);
			}

		}
		
		//Handle recieving a message
		private static void notificationPipeServer_MessageReceived(Client client, String message) {
			LogHandler.log("PipeServer", "Message recieved");
			LogHandler.log("PipeServer",message);
		}	
		
		//Load all of the modules
		private static void initializeModules() {
			modules = new List<AbstractModule>();
			modules.Add(new AutoLogOut());
			
		}
		
		//Run each service
		private static void serviceLooper() {
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
				Thread.Sleep(sleepTime * 1000);
			}
		}
		
		
		//Get the time to sleep from the FOG server, if it cannot it will use the default time
		private static int getSleepTime() {
			LogHandler.log(LOG_NAME, "Getting sleep duration...");
			
			Response sleepResponse = CommunicationHandler.getResponse("/service/servicemodule-active.php");
			
			try {
				if(!sleepResponse.wasError() && !sleepResponse.getField("#sleep").Equals("")) {
					int sleepTime = int.Parse(sleepResponse.getField("#sleep"));
					if(sleepTime >= sleepDefaultTime) {
						return sleepTime;
					} else {
						LogHandler.log(LOG_NAME, "Sleep time set on the server is below the minimum of " + sleepDefaultTime.ToString());
					}
				}
			} catch (Exception ex) {
				LogHandler.log(LOG_NAME,"Failed to parse sleep time");
				LogHandler.log(LOG_NAME,"ERROR: " + ex.Message);				
			}
			
			LogHandler.log(LOG_NAME,"Using default sleep time");	
			
			return sleepDefaultTime;			
		}
		
		private static void startTray() {
			Process process = new Process();
			process.StartInfo.UseShellExecute = false;
			process.StartInfo.FileName = Path.GetDirectoryName(System.Reflection.Assembly.GetExecutingAssembly().Location) + @"\FOGTray.exe";
			process.Start();
		}

	}
}