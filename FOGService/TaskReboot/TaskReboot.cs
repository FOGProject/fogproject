
using System;

namespace FOG
{
	/// <summary>
	/// Reboot the computer if a task needs to
	/// </summary>
	public class TaskReboot : AbstractModule
	{
		private CommunicationHandler communicationHandler;
		private ShutdownHandler shutdownHander;	
		private UserHandler userHandler;
		
		public TaskReboot(LogHandler logHandler) : base(logHandler){
			this.communicationHandler = new CommunicationHandler(logHandler);
			this.shutdownHander = new ShutdownHandler(logHandler);
			this.userHandler = new UserHandler(logHandler);
			
			setName("TaskReboot");
			setDescription("Reboot if a task is scheduled");
		}
		
		protected override void doWork() {
			
			if(isEnabled(communicationHandler)) {
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