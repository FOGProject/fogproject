
using System;
using System.Collections.Generic;
using Microsoft.Win32.TaskScheduler;

namespace FOG {
	/// <summary>
	/// Perform cron style power tasks
	/// </summary>
	public class GreenFOG : AbstractModule {
		
		public GreenFOG():base(){
			setName("GreenFOG");
			setDescription("Perform cron style power tasks");
		}
		
		protected override void doWork() {
			//Get actions
			Response actionsResponse = CommunicationHandler.getResponse("/service/greenfog.php?mac=" + CommunicationHandler.getMacAddresses());

			//Shutdown if a task is avaible and the user is logged out or it is forced
			if(!actionsResponse.wasError()) {
				//Remove old actions
				
				//Check if current actions exist, if not add them
			}
			
		}		
	}
}