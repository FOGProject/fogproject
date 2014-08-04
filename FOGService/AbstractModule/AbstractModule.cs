
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
		
		private LogHandler logHandler;
		private CommunicationHandler communicationHandler;
		
		public AbstractModule(LogHandler logHandler,
		                      CommunicationHandler communicationHandler) {
			this.moduleName = "";
			this.moduleDescription = "";
			
			this.logHandler = logHandler;
			this.communicationHandler = communicationHandler;
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
