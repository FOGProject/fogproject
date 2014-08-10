
using System;
using System.Threading;
using System.Collections.Generic;

namespace FOG {
	/// <summary>
	/// Automatically log out the user after X seconds of inactivity
	/// </summary>
	public class AutoLogOut : AbstractModule {
		
		private int defaultTime;

		public AutoLogOut():base() {
			setName("AutoLogOut");
			setDescription("Automatically log out the user if they are inactive");
			this.defaultTime = 1800;
		}
		
		protected override void doWork() {
			if(UserHandler.isUserLoggedIn()) {
				//Get task info
				Response taskResponse = CommunicationHandler.getResponse("/fog/service/autologout.php?mac=" + CommunicationHandler.getMacAddresses());

				if(!taskResponse.wasError()) {
					int timeOut = getTimeOut(taskResponse);
					LogHandler.log(getName(), "Time set to " + timeOut.ToString());
					
					if(UserHandler.getUserInactivityTime() >= timeOut) {
						NotificationHandler.createNotification(new Notification("You are about to be logged off",
						                                                        "Due to inactivity you will be logged off if you remain inactive", 20));
						//Wait 20 seconds and check if the user is no longer inactive
						Thread.Sleep(20);
						if(UserHandler.getUserInactivityTime() >= timeOut)
							ShutdownHandler.logOffUser();
					}
					
				}
			} else {
				LogHandler.log(getName(), "No user logged in");
			}
			
		}
		
		//Get how long a user must be inactive before logging them out
		private int getTimeOut(Response taskResponse) {
			try {
				int timeOut = int.Parse(taskResponse.getField("#time"));
				if(timeOut > 60) {
					return timeOut;
				} else {
					LogHandler.log(getName(), "Time set is less than 1 minute");
				}
				
			} catch (Exception ex) {
				LogHandler.log(getName(), "Unable to parsing time set");
				LogHandler.log(getName(), "ERROR: " + ex.Message);
			}
			LogHandler.log(getName(), "Using default time");
			
			return this.defaultTime;
			
		}
		
		
	}
}