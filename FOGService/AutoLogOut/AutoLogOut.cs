
using System;
using System.Collections.Generic;

namespace FOG {
	/// <summary>
	/// Automatically log out the user after X seconds of inactivity
	/// </summary>
	public class AutoLogOut : AbstractModule {
		
		private Boolean notifieduser;
		
		public AutoLogOut():base() {
			setName("AutoLogOut");
			setDescription("Automatically log out the user if they are inactive");
			this.notifiedUser = false;
		}
		
		protected override void doWork() {
			if(UserHandler.isUserLoggedIn()) {
				//Get task info
				Response taskResponse = CommunicationHandler.getResponse("/fog/service/autologout.php?mac=" + CommunicationHandler.getMacAddresses());
					

				if(!taskResponse.wasError()) {

				}
			} else {
				LogHandler.log(getName(), "No user logged in");
			}
			
		}
		
		
	}
}