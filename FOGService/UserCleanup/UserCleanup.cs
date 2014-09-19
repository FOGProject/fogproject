
using System;
using System.Linq;
using System.Collections.Generic;

namespace FOG {
	/// <summary>
	/// Remove specified users
	/// </summary>

	public class UserCleanup : AbstractModule {
		
		public UserCleanup():base(){
			setName("UserCleanup");
			setDescription("Remove specified users");
		}
		
		protected override void doWork() {
			//Get task info
			Response usersResponse = CommunicationHandler.getResponse("/service/usercleanup-users.php?mac=" + CommunicationHandler.getMacAddresses());

			if(!usersResponse.wasError()) {
				List<String> protectedUsers = getProtectedUsers(usersResponse);
				
				if(protectedUsers.Count > 0) {
					foreach(UserData user in UserHandler.getAllUserData()) {
						if(!protectedUsers.Contains(user.getName(), StringComparer.OrdinalIgnoreCase) && !UserHandler.getUsersLoggedIn().Contains(user.getName(), StringComparer.OrdinalIgnoreCase)) {
							UserHandler.purgeUser(user, true);
						} else {
							LogHandler.log(getName(), user.getName() + " is either logged in or protected, skipping");
						}
					}
				}
			}
			
		}
		
		//Get a list of protected users
		private List<String> getProtectedUsers(Response usersResponse) {
			List<String> protectedUsers = new List<String>();

			foreach(String user in usersResponse.getData().Values) {
				protectedUsers.Add(user);
			}

			return protectedUsers;
		}	
		
	}
}