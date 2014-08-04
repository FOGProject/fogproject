
using System;
using System.Collections.Generic;
using System.Management;

namespace FOG
{
	/// <summary>
	/// Detect the current user
	/// </summary>
	public class UserHandler
	{
		//Define variables
		private LogHandler logHandler;
		
		public UserHandler(LogHandler logHandler) {
			this.logHandler = logHandler;
		}
		
		//Check if a user is loggin in, do this by getting a list of all users, and check if the list has any elements
		public Boolean isUserLoggedIn() {
			return getUsersLoggedIn().Count > 0;
		}
		
		//Get al ist of all users logged in
		public List<String> getUsersLoggedIn() {
			List<String> users = new List<String>();
			try {
				ManagementObjectSearcher searcher = new ManagementObjectSearcher("root\\CIMV2", 
				                                                                 "SELECT * FROM Win32_ComputerSystem");

				foreach (ManagementObject queryObj in searcher.Get()) {
					users.Add( queryObj["UserName"].ToString() );
				}
				
			} catch (Exception ex) {
				logHandler.log(System.Reflection.Assembly.GetExecutingAssembly().GetName().Name,
			              "Error geetting all users: " + ex.Message);
			}
			
			return users;
		}
	}
}