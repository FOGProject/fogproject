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
		private static PipeClient servicePipe;
		private const String LOG_NAME = "UserService";
		private static int sleepDefaultTime = 60;		
		private static Status status;
		
		
		public static void Main(string[] args) { 
			//Initialize everything
			AppDomain.CurrentDomain.ProcessExit += new EventHandler (OnProcessExit);
			
			LogHandler.setFilePath(Environment.ExpandEnvironmentVariables("%userprofile%") + @"\fog_user.log");
			LogHandler.log(LOG_NAME, "Initializing");
			if(CommunicationHandler.getAndSetServerAddress()) {
	
				initializeModules();
				threadManager = new Thread(new ThreadStart(serviceLooper));
				status = Status.Stopped;
				
				//Setup the notification pipe server
				notificationPipeThread = new Thread(new ThreadStart(notificationPipeHandler));
				notificationPipe = new PipeServer("fog_pipe_notification_user_" +  UserHandler.getCurrentUser());
				notificationPipe.MessageReceived += new PipeServer.MessageReceivedHandler(pipeServer_MessageReceived);			
				notificationPipe.start();
				
				//Setup the service pipe client
				servicePipe = new PipeClient("fog_pipe_service");
				servicePipe.MessageReceived += new PipeClient.MessageReceivedHandler(pipeClient_MessageReceived);
				servicePipe.connect();
				
				
				status = Status.Running;
				
				

				
				if(File.Exists(AppDomain.CurrentDomain.BaseDirectory + @"\updating.info")) {
					LogHandler.log(LOG_NAME, "Update.info found, exiting program");
					ShutdownHandler.spawnUpdateWaiter(System.Reflection.Assembly.GetExecutingAssembly().Location);
					Environment.Exit(0);
				}
				
				
				//Start the main thread that handles all modules
				threadManager.Priority = ThreadPriority.Normal;
				threadManager.IsBackground = false;
				threadManager.Start();

				if(RegistryHandler.getSystemSetting("Tray").Trim().Equals("1")) {
					startTray();
				}
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
		private static void pipeServer_MessageReceived(Client client, String message) {
			LogHandler.log(LOG_NAME, "Message recieved from tray");
			LogHandler.log(LOG_NAME, "MSG:" + message);
		}	
		
		//Handle recieving a message
		private static void pipeClient_MessageReceived(String message) {
			LogHandler.log(LOG_NAME, "Message recieved from service");
			LogHandler.log(LOG_NAME, "MSG: " + message);
			
			if(message.Equals("UPD")) {
				ShutdownHandler.spawnUpdateWaiter(System.Reflection.Assembly.GetExecutingAssembly().Location);
				ShutdownHandler.scheduleUpdate();
			}
		}			
		
		
		//Load all of the modules
		private static void initializeModules() {
			modules = new List<AbstractModule>();
			modules.Add(new AutoLogOut());
			modules.Add(new DisplayManager());			
			
		}
		
		//Run each service
		private static void serviceLooper() {
			//Only run the service if there wasn't a stop or shutdown request
			while (status.Equals(Status.Running) && !ShutdownHandler.isShutdownPending() && !ShutdownHandler.isUpdatePending()) {
				foreach(AbstractModule module in modules) {
					if(ShutdownHandler.isShutdownPending() || ShutdownHandler.isUpdatePending())
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
					
				if(ShutdownHandler.isShutdownPending() || ShutdownHandler.isUpdatePending())
					break;				
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
		
		static void OnProcessExit(object sender, EventArgs e) {
			
		}

	}
}