
using System;
using System.Collections.Generic;

namespace FOG
{
	/// <summary>
	/// The base of all FOG Modules
	/// </summary>
	public abstract class AbstractModule {
		
		//Basic variables every module needs
		private String moduleName;
		private String moduleDescription;
		private Status status;
		private String isActiveURL;
		private int defaultSleepDuration;
		private int sleepDuration;
		
		//Define the handlers
		protected CommunicationHandler communicationHandler;
		protected LogHandler logHandler;
		protected NotificationHandler notificationHandler;
		protected ShutdownHandler shutdownHander;
		protected UserHandler userHandler;
		
		//Module status -- used for stopping/starting
		public enum Status {
			Running = 1,
			Stopped = 0
		}
		
		protected AbstractModule(CommunicationHandler communicationHandler,
		                      LogHandler logHandler,
		                      NotificationHandler notificationHandler,
		                      ShutdownHandler shutdownHander,
		                      UserHandler userHandler) {
			
			//Define variables
			setName("Generic Module");
			setDescription("Generic Description");
			setStatus(Status.Stopped);
			setDefaultSleepDuration(320);
			setSleepDuration(getDefaultSleepDuration());
			setIsActiveURL("/fog/service/servicemodule-active.php");

			this.communicationHandler = communicationHandler;
			this.logHandler = logHandler;
			this.notificationHandler = notificationHandler;
			this.shutdownHander = shutdownHander;
			this.userHandler = userHandler;
		}
		
		protected abstract void doWork();
		
		
		//Default start method
		public virtual void start() {
			logHandler.log(getName(), "Starting...");
			setStatus(Status.Running);
			
			while(getStatus().Equals(Status.Running)) {
				doWork();
			
				logHandler.log(getName(), "Sleeping for " + getSleepDuration().ToString() + " seconds");
				System.Threading.Thread.Sleep(getSleepDuration() * 1000);
			}
		}
		
		//Default stop method
		public virtual void stop() {
			logHandler.log(getName(), "Stopping...");
			setStatus(Status.Stopped);
		}

		
		//Getters and setters
		public String getName() { return this.moduleName; }
		protected void setName(String name) { this.moduleName = name; }
		
		public String getDescription() { return this.moduleDescription; }
		protected void setDescription(String description) { this.moduleDescription = description; }
		
		public Status getStatus() { return this.status; }
		protected void setStatus(Status status) { this.status = status; }
		
		public int getDefaultSleepDuration() { return this.defaultSleepDuration; }
		protected void setDefaultSleepDuration(int defaultSleepDuration) { this.defaultSleepDuration = defaultSleepDuration; }	
	
		public int getSleepDuration() { return this.sleepDuration; }
		protected void setSleepDuration(int sleepDuration) { this.sleepDuration = sleepDuration; }

		public String getIsActiveURL() { return this.isActiveURL; }
		protected void setIsActiveURL(String isActiveURL) { this.isActiveURL = isActiveURL; }
		
		//Check if the module is enabled, also set the sleep duration
		public Boolean isEnabled() {
			
			Response moduleActiveResponse = communicationHandler.getResponse(getIsActiveURL() + "?mac=" + communicationHandler.getMacAddresses() +
			                                								"&moduleid=" + getName().ToLower());

			//Update the sleep duration between cycles
			if(!moduleActiveResponse.getField("#sleep").Equals("")) {
				try {
					setSleepDuration(int.Parse(moduleActiveResponse.getField("#sleep")));
				} catch {
					logHandler.log(getName(), "Could not parse how long to sleep, using default value");
					setSleepDuration(getDefaultSleepDuration());
				}
			} else {
				setSleepDuration(getDefaultSleepDuration());
			}
			
			return !moduleActiveResponse.wasError();
		}
		
	}
}
