
using System;
using System.Collections.Generic;
using System.Runtime.InteropServices;
using System.Management;

namespace FOG {
	/// <summary>
	/// Detect the current user
	/// </summary>
	public static class UserHandler {
		
		[DllImport("user32.dll")]
		static extern bool GetLastInputInfo(ref LASTINPUTINFO plii);
		
		internal struct LASTINPUTINFO {
			public uint cbSize;
			public uint dwTime;
		}
		
		private const String LOG_NAME = "UserHandler";

		//Check if a user is loggin in, do this by getting a list of all users, and check if the list has any elements
		public static Boolean isUserLoggedIn() {
			return getUsersLoggedIn().Count > 0;
		}
		
		public static String getCurrentUser() {
			return System.Security.Principal.WindowsIdentity.GetCurrent().Name;;
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
		
		//Return local users
		public static List<String> getUsers() {
			List<String> users = new List<String>();
			
			SelectQuery query = new SelectQuery("Win32_UserAccount");
			ManagementObjectSearcher searcher = new ManagementObjectSearcher(query);
            
			foreach (ManagementObject envVar in searcher.Get()) {
				users.Add(envVar["Name"].ToString());
			}
			
			return users;
		}
		
		//Return how long the logged in user is inactive for in seconds
		public static int getUserInactivityTime() {
			uint idleTime = 0;
			LASTINPUTINFO lastInputInfo = new LASTINPUTINFO();
			lastInputInfo.cbSize = (uint)Marshal.SizeOf( lastInputInfo );
			lastInputInfo.dwTime = 0;
	
			uint envTicks = (uint)Environment.TickCount;
	
	        if ( GetLastInputInfo( ref lastInputInfo ) ) {
				uint lastInputTick = lastInputInfo.dwTime;
	
				idleTime = envTicks - lastInputTick;
			}
			
			return (int)idleTime/1000;
		}

	}
}