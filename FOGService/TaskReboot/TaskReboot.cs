
using System;

namespace FOG
{
	/// <summary>
	/// Reboot the computer if a task needs to
	/// </summary>
	public class TaskReboot : AbstractModule
	{
		
		public TaskReboot():base(){
			
			setName("TaskReboot");
			setDescription("Reboot if a task is scheduled");
		}
		
		protected override void doWork() {
			
			if(isEnabled()) {
				//Get task info
				Response taskResponse = CommunicationHandler.getResponse("/fog/service/jobs.php?mac=" +
				                                                         CommunicationHandler.getMacAddresses());
				
				//Shutdown if a task is avaible and the user is logged out or it is forced
				if(!taskResponse.wasError() && (!UserHandler.isUserLoggedIn() || taskResponse.getField("#force").Equals("1") )) {
					ShutdownHandler.restart(getName(), 30);
				}
			} else {
				LogHandler.log(getName(), "Disabled on server");
			}
			
		}
		
	}
}