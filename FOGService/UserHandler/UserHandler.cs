
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
		
		[DllImport("Wtsapi32.dll")]
	    private static extern bool WTSQuerySessionInformation(IntPtr hServer, int sessionId, WtsInfoClass wtsInfoClass, out System.IntPtr ppBuffer, out int pBytesReturned);
	    
	    [DllImport("Wtsapi32.dll")]
	    private static extern void WTSFreeMemory(IntPtr pointer);
	    
		enum WtsInfoClass {
		     WTSInitialProgram,
		     WTSApplicationName,
		     WTSWorkingDirectory,
		     WTSOEMId,
		     WTSSessionId,
		     WTSUserName,
		     WTSWinStationName,
		     WTSDomainName,
		     WTSConnectState,
		     WTSClientBuildNumber,
		     WTSClientName,
		     WTSClientDirectory,
		     WTSClientProductId,
		     WTSClientHardwareId,
		     WTSClientAddress,
		     WTSClientDisplay,
		     WTSClientProtocolType,
		     WTSIdleTime,
		     WTSLogonTime,
		     WTSIncomingBytes,
		     WTSOutgoingBytes,
		     WTSIncomingFrames,
		     WTSOutgoingFrames,
		     WTSClientInfo,
		     WTSSessionInfo
		};
    
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
		
		//Get a list of all users logged in
		public static List<String> getUsersLoggedIn() {
			List<String> users = new List<String>();
			List<int> sids = getSIDs();
			
			foreach(int sid in sids) {
				users.Add(getUserNameFromSID(sid, false));
			}
			
			return users;
		}	
		
		//Get all session IDs from running processes
		public static List<int> getSIDs() {
			List<int> sids = new List<int>();
			String[] properties = new[] {"SessionId"};
			
			SelectQuery query = new SelectQuery("Win32_Process", "", properties); //SessionId
			ManagementObjectSearcher searcher = new ManagementObjectSearcher(query);
            
			foreach (ManagementObject envVar in searcher.Get()) {
				try {
					if(!sids.Contains(int.Parse(envVar["SessionId"].ToString()))) {
						sids.Add(int.Parse(envVar["SessionId"].ToString()));
					}
				} catch (Exception ex) {
					LogHandler.log(LOG_NAME, "Unable to parse SID");
					LogHandler.log(LOG_NAME, "ERROR: " + ex.Message);
				}
			}	
			return sids;			
		}
		
		//Convert a session ID to a username
		//https://stackoverflow.com/questions/19487541/get-windows-user-name-from-sessionid
		public static string getUserNameFromSID(int sessionId, bool prependDomain) {
			IntPtr buffer;
			int strLen;
			string username = "SYSTEM";
			if (WTSQuerySessionInformation(IntPtr.Zero, sessionId, WtsInfoClass.WTSUserName, out buffer, out strLen) && strLen > 1) {
				username = Marshal.PtrToStringAnsi(buffer);
				WTSFreeMemory(buffer);
				if (prependDomain) {
					if (WTSQuerySessionInformation(IntPtr.Zero, sessionId, WtsInfoClass.WTSDomainName, out buffer, out strLen) && strLen > 1) {
						username = Marshal.PtrToStringAnsi(buffer) + "\\" + username;
						WTSFreeMemory(buffer);
					}
				}
			}
			return username;
		}

	}
}