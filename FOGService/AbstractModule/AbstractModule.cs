
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
		private String isActiveURL;
		private int defaultSleepDuration;
		private int sleepDuration;
		
		//Define the handler
		protected LogHandler logHandler;
		
		protected AbstractModule(LogHandler logHandler) {
			
			//Define variables
			setName("Generic Module");
			setDescription("Generic Description");
			setDefaultSleepDuration(60);
			setSleepDuration(getDefaultSleepDuration());
			setIsActiveURL("/fog/service/servicemodule-active.php");

			this.logHandler = logHandler;
		}
		
		protected abstract void doWork();
		
		
		//Default start method
		public virtual void start() {
			logHandler.log(getName(), "Running...");
			doWork();
		}

		
		//Getters and setters
		public String getName() { return this.moduleName; }
		protected void setName(String name) { this.moduleName = name; }
		
		public String getDescription() { return this.moduleDescription; }
		protected void setDescription(String description) { this.moduleDescription = description; }
		
		
		public int getDefaultSleepDuration() { return this.defaultSleepDuration; }
		protected void setDefaultSleepDuration(int defaultSleepDuration) { this.defaultSleepDuration = defaultSleepDuration; }	
	
		public int getSleepDuration() { return this.sleepDuration; }
		protected void setSleepDuration(int sleepDuration) { this.sleepDuration = sleepDuration; }

		public String getIsActiveURL() { return this.isActiveURL; }
		protected void setIsActiveURL(String isActiveURL) { this.isActiveURL = isActiveURL; }
		
		//Check if the module is enabled, also set the sleep duration
		public Boolean isEnabled(CommunicationHandler communicationHandler) {
			
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
