
using System;
using System.Collections.Generic;
using System.DirectoryServices;

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
					foreach(String user in UserHandler.getUsers()) {
						if(!protectedUsers.Contains(user) && !UserHandler.getUsersLoggedIn().Contains(user)) {
							deleteUser(user);
						}
					}
				}
			}
			
		}
		
		//Delete the specified user
		private void deleteUser(String user) {
			LogHandler.log(getName(), "Attempting to delete " + user);
			try {
				DirectoryEntry userDir = new DirectoryEntry("WinNT://" + Environment.MachineName + ",computer");
				DirectoryEntry userToDelete = userDir.Children.Find(user);
				
				userDir.Children.Remove(userToDelete);
				LogHandler.log(getName(), "Success");
				
			} catch (Exception ex) {
				LogHandler.log(getName(), "ERROR" + ex.Message);
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