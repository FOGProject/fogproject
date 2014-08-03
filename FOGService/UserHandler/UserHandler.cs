
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
		public Boolean isUserLoggedIn(LogHandler logHandler) {
			return getUsersLoggedIn(logHandler).Count > 0;
		}
		
		public List<String> getUsersLoggedIn(LogHandler logHandler) {
			List<String> users = new List<String>();
			try {
				ManagementObjectSearcher searcher = new ManagementObjectSearcher("root\\CIMV2", 
				                                                                 "SELECT * FROM Win32_ComputerSystem");

				foreach (ManagementObject queryObj in searcher.Get()) {
					users.Add( queryObj["UserName"].ToString() );
				}
				
			} catch (Exception ex) {
				logHandler.log("User Handler", "Error geetting all users: " + ex.Message);
			}
			
			return users;
		}
	}
}