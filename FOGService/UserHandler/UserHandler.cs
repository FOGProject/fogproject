
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
		private LogHandler logHandler;
		
		public UserHandler(LogHandler logHandler) {
			this.logHandler = logHandler;
		}
		
		public Boolean isUserLoggedIn() {
			return getUsersLoggedIn().Count > 0;
		}
		
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