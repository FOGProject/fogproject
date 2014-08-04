
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
		
		private CommunicationHandler communicationHandler;
		private LogHandler logHandler;
		private NotificationHandler notificationHandler;
		private ShutdownHandler shutdownHander;
		private UserHandler userHandler;
		
		public AbstractModule(CommunicationHandler communicationHandler,
		                      LogHandler logHandler,
		                      NotificationHandler notificationHandler,
		                      ShutdownHandler shutdownHander,
		                      UserHandler userHandler ) {
			this.moduleName = "";
			this.moduleDescription = "";
			
			this.communicationHandler = communicationHandler;
			this.logHandler = logHandler;
			this.notificationHandler = notificationHandler;
			this.shutdownHander = shutdownHander;
			this.userHandler = userHandler;
		}
		
		
		public abstract void start();
		public abstract void stop();
		
		public String getName() {
			return this.moduleName;
		}
		
		public String getDescription() {
			return this.moduleDescription;
		}
		
	}
}
