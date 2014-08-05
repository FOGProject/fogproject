
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
		
		//Define the handlers
		protected LogHandler logHandler;
		protected NotificationHandler notificationHandler;
		protected ShutdownHandler shutdownHandler;
		protected CommunicationHandler communicationHandler;
		protected UserHandler userHandler;
		protected EncryptionHandler encryptionHandler;
		
		
		protected AbstractModule(LogHandler logHandler, NotificationHandler notificationHandler, ShutdownHandler shutdownHandler, 
		                         CommunicationHandler communicationHandler, UserHandler userHandler, EncryptionHandler encryptionHandler) {
			
			//Define variables
			setName("Generic Module");
			setDescription("Generic Description");
			setIsActiveURL("/fog/service/servicemodule-active.php");

			this.logHandler = logHandler;
			this.notificationHandler = notificationHandler;
			this.shutdownHandler = shutdownHandler;
			this.communicationHandler = communicationHandler;
			this.userHandler = userHandler;
			this.encryptionHandler = encryptionHandler;
		}
		
		protected abstract void doWork();
		
		
		//Default start method
		public virtual void start() {
			this.logHandler.log(getName(), "Running...");
			doWork();
		}

		
		//Getters and setters
		public String getName() { return this.moduleName; }
		protected void setName(String name) { this.moduleName = name; }
		
		public String getDescription() { return this.moduleDescription; }
		protected void setDescription(String description) { this.moduleDescription = description; }
	
		public String getIsActiveURL() { return this.isActiveURL; }
		protected void setIsActiveURL(String isActiveURL) { this.isActiveURL = isActiveURL; }
		
		//Check if the module is enabled, also set the sleep duration
		public Boolean isEnabled() {
			
			Response moduleActiveResponse = this.communicationHandler.getResponse(getIsActiveURL() + "?mac=" + 
			                                                                      this.communicationHandler.getMacAddresses() +
			                                								"&moduleid=" + getName().ToLower());
			return !moduleActiveResponse.wasError();
		}
		
	}
}
