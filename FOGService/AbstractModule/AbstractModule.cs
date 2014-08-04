
using System;
using System.Collections.Generic;

namespace FOG
{
	/// <summary>
	/// The base of all FOG Modules
	/// </summary>
	public abstract class AbstractModule {
		
		private String moduleName;
		private String moduleDescription;
		private Status status;
		private String checkIfEnabledURL;
		private int defaultSleepDuration;
		private int sleepDuration;
		
		protected CommunicationHandler communicationHandler;
		protected LogHandler logHandler;
		protected NotificationHandler notificationHandler;
		protected ShutdownHandler shutdownHander;
		protected UserHandler userHandler;
		
		public enum Status {
			Running = 1,
			Stopped = 0
		}
		
		protected AbstractModule(CommunicationHandler communicationHandler,
		                      LogHandler logHandler,
		                      NotificationHandler notificationHandler,
		                      ShutdownHandler shutdownHander,
		                      UserHandler userHandler) {
			
			this.moduleName = "";
			this.moduleDescription = "";
			this.status = Status.Stopped;
			this.defaultSleepDuration = 320;
			this.sleepDuration = this.defaultSleepDuration;
			this.checkIfEnabledURL = "/fog/service/servicemodule-active.php";

			this.communicationHandler = communicationHandler;
			this.logHandler = logHandler;
			this.notificationHandler = notificationHandler;
			this.shutdownHander = shutdownHander;
			this.userHandler = userHandler;
		}
		
		protected abstract void doWork();
		
		public virtual void start() {
			logHandler.log(getName(), "Starting...");
			setStatus(Status.Running);
			
			while(getStatus().Equals(Status.Running)) {
				doWork();
			
				logHandler.log(getName(), "Sleeping for " + this.sleepDuration.ToString() + " seconds");
				System.Threading.Thread.Sleep(getSleepDuration() * 1000);
			}
		}
		
		public virtual void stop() {
			logHandler.log(getName(), "Stopping...");
			setStatus(Status.Stopped);
		}

		
		protected void setName(String name) {
			this.moduleName = name;
		}

		protected void setDescription(String description) {
			this.moduleDescription = description;
		}
		
		protected void setStatus(Status status) {
			this.status = status;
		}
		
		public String getName() {
			return this.moduleName;
		}
		
		public String getDescription() {
			return this.moduleDescription;
		}
		
		public Status getStatus() {
			return this.status;
		}
		
		public int getDefaultSleepDuration() {
			return this.defaultSleepDuration;
		}
	
		public int getSleepDuration() {
			return this.sleepDuration;
		}
		
		protected void setSleepDuration(int sleepDuration) {
			this.sleepDuration = sleepDuration;
		}	
		
		public Boolean isEnabled() {
			
			Response moduleActiveResponse = communicationHandler.getResponse(checkIfEnabledURL + "?mac=" + communicationHandler.getMacAddresses() +
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
