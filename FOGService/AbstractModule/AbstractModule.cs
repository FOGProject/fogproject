
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
		
		private ConfigHandler configHandler;
		private LogHandler loghandler;
		
		public AbstractModule(ConfigHandler configHandler, LogHandler logHandler) {
			this.moduleName = "";
			this.moduleDescription = "";
			
			this.configHandler = configHandler;
			this.loghandler = logHandler;
		}
		
		
		public abstract void start();
		public abstract void stop();
		
		public String getName() {
			return this.moduleName;
		}
		
		public String getDescription() {
			return this.moduleDescription;
		}
		
		
		public Boolean isUserLoggedIn() {
			return getUsersLoggedIn().Count > 0;
		}
		
		
		public List<String> getUsersLoggedIn() {
			return new List<String>();
		}
		
		public void notify(String title, String message) {
			
		}
		
		public Dictionary<String,String> contactFOG(String postFix) {
			return new Dictionary<String,String>();
		}
		
		public void shutdown(Boolean restart) {
			
		}
		
		public Boolean isShuttingDown() {
			return false;
		}
		
	}
}
