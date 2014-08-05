
using System;

namespace FOG
{
	/// <summary>
	/// Reboot the computer if a task needs to
	/// </summary>
	public class TaskReboot : AbstractModule
	{
		
		public TaskReboot(LogHandler logHandler, NotificationHandler notificationHandler, ShutdownHandler shutdownHandler, 
		                         CommunicationHandler communicationHandler, UserHandler userHandler, 
		                         EncryptionHandler encryptionHandler):base(logHandler,notificationHandler, shutdownHandler,
		                                          communicationHandler, userHandler, encryptionHandler){
			
			setName("TaskReboot");
			setDescription("Reboot if a task is scheduled");
		}
		
		protected override void doWork() {
			
			if(isEnabled()) {
				//Get task info
				Response taskResponse = this.communicationHandler.getResponse("/fog/service/jobs.php?mac=" +
				                                                         communicationHandler.getMacAddresses());
				
				//Shutdown if a task is avaible and the user is logged out or it is forced
				if(!taskResponse.wasError() && (!userHandler.isUserLoggedIn() || taskResponse.getField("#force").Equals("1") )) {
					this.shutdownHandler.restart(getName(), 30);
				}
			} else {
				this.logHandler.log(getName(), "Disabled on server");
			}
			
		}
		
	}
}