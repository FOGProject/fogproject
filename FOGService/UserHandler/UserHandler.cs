
using System;
using System.Collections.Generic;
using System.Management;

namespace FOG {
	/// <summary>
	/// Detect the current user
	/// </summary>
	public static class UserHandler {
		
		private const String LOG_NAME = "UserHandler";
		
		//Check if a user is loggin in, do this by getting a list of all users, and check if the list has any elements
		public static Boolean isUserLoggedIn() {
			return getUsersLoggedIn().Count > 0;
		}
		
		//Get a list of all users logged in
		public static List<String> getUsersLoggedIn() {
			List<String> users = new List<String>();
			
			try {
				ManagementObjectSearcher searcher = new ManagementObjectSearcher("root\\CIMV2", 
				                                                                 "SELECT * FROM Win32_ComputerSystem");

				foreach (ManagementObject queryObj in searcher.Get()) {
					users.Add( queryObj["UserName"].ToString() );
				}
				
			} catch (Exception ex) {
				LogHandler.log(LOG_NAME, "Error geetting all users: " + ex.Message);
			}
			
			return users;
		}
	}
}