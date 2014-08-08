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


		protected AbstractModule() {
			//Define variables
			setName("Generic Module");
			setDescription("Generic Description");
			setIsActiveURL("/fog/service/servicemodule-active.php");
		}

		//Default start method
		public virtual void start() {
			LogHandler.log(getName(), "Running...");
			if(isEnabled()) {
				doWork();
			} else {
				LogHandler.log(getName(), "Module is disabled");
			}
		}
		
		//Perform the module's task
		protected abstract void doWork();

		//Getters and setters
		public String getName() { return this.moduleName; }
		protected void setName(String name) { this.moduleName = name; }

		public String getDescription() { return this.moduleDescription; }
		protected void setDescription(String description) { this.moduleDescription = description; }

		public String getIsActiveURL() { return this.isActiveURL; }
		protected void setIsActiveURL(String isActiveURL) { this.isActiveURL = isActiveURL; }

		//Check if the module is enabled, also set the sleep duration
		public Boolean isEnabled() {

			Response moduleActiveResponse = CommunicationHandler.getResponse(getIsActiveURL() + "?mac=" + 
			                                                                      CommunicationHandler.getMacAddresses() +
			                                								"&moduleid=" + getName().ToLower());
			return !moduleActiveResponse.wasError();
		}

	}
}