
using System;

namespace FOG
{
	/// <summary>
	/// Reboot the computer if a task needs to
	/// </summary>
	public class TaskReboot : AbstractModule
	{
		
		public TaskReboot(CommunicationHandler communicationHandler,
		                      LogHandler logHandler,
		                      NotificationHandler notificationHandler,
		                      ShutdownHandler shutdownHander,
		                      UserHandler userHandler) : base(communicationHandler, logHandler,
			     												notificationHandler, shutdownHander,
			    												userHandler){
			setName("TaskReboot");
			setDescription("Reboot if a task is scheduled");
		}
		
		protected override void doWork() {
			logHandler.log(getName(), "----------------------------------");
			logHandler.log(getName(), "Running...");
			
			if(isEnabled()) {
				//Get task info
				Response taskResponse = communicationHandler.getResponse("/fog/service/jobs.php?mac=" +
				                                                         communicationHandler.getMacAddresses());
				
				//Shutdown if a task is avaible and the user is logged out or it is forced
				if(!taskResponse.wasError() && (!userHandler.isUserLoggedIn() || taskResponse.getField("#force").Equals("1") )) {
					shutdownHander.restart(getName(), 30);
				}
			} else {
				logHandler.log(getName(), "Disabled on server");
			}
			
		}
		
	}
}