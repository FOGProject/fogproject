
using System;
using System.Collections.Generic;

namespace FOG
{
	/// <summary>
	/// The base of all FOG Modules
	/// </summary>
	public abstract class AbstractModule {
		
		
		public AbstractModule() {
			
		}
		
		
		public abstract void start();
		public abstract void stop();
		
		
		public Boolean isUserLoggedIn() {
			return getUsersLoggedIn().Count > 0;
		}
		
		
		public List<String> getUsersLoggedIn() {
			return new List<String>();
		}
		
		
		public void log(String moduleName, String message) {
			
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
		
		public String getSetting(String settingID) {
			return "";
		}
		
		public String getName() {
			return "";
		}
		
		public String getDescription() {
			return "";
		}
		
	}
}
