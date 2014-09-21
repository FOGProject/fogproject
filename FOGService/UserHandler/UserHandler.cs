
using System;
using System.IO;
using System.Collections.Generic;
using System.Runtime.InteropServices;
using System.Management;
using System.DirectoryServices;
using System.Security.Principal;
using System.Security.AccessControl;
using System.Diagnostics;

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
			return System.Security.Principal.WindowsIdentity.GetCurrent().Name;
		}

		
		//Return local users
		public static List<UserData> getAllUserData() {
			List<UserData> users = new List<UserData>();
			
			SelectQuery query = new SelectQuery("Win32_UserAccount");
			ManagementObjectSearcher searcher = new ManagementObjectSearcher(query);
            
			foreach (ManagementObject envVar in searcher.Get()) {
				UserData userData = new UserData(envVar["Name"].ToString(), envVar["SID"].ToString());
				users.Add(userData);
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
			List<int> sessionIds = getSessionIds();
			
			foreach(int sessionId in sessionIds) {
				users.Add(getUserNameFromSessionId(sessionId, false));
			}
			
			return users;
		}	
		
		//Get all session Ids from running processes
		public static List<int> getSessionIds() {
			List<int> sessionIds = new List<int>();
			String[] properties = new[] {"SessionId"};
			
			SelectQuery query = new SelectQuery("Win32_Process", "", properties); //SessionId
			ManagementObjectSearcher searcher = new ManagementObjectSearcher(query);
            
			foreach (ManagementObject envVar in searcher.Get()) {
				try {
					if(!sessionIds.Contains(int.Parse(envVar["SessionId"].ToString()))) {
						sessionIds.Add(int.Parse(envVar["SessionId"].ToString()));
					}
				} catch (Exception ex) {
					LogHandler.log(LOG_NAME, "Unable to parse Session Id");
					LogHandler.log(LOG_NAME, "ERROR: " + ex.Message);
				}
			}	
			return sessionIds;			
		}
		
		//Convert a session ID to a username
		//https://stackoverflow.com/questions/19487541/get-windows-user-name-from-sessionid
		public static String getUserNameFromSessionId(int sessionId, bool prependDomain) {
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
		
		public static String getUserProfilePath(String sid) {
			return RegistryHandler.getRegisitryValue(@"SOFTWARE\Microsoft\Windows NT\CurrentVersion\ProfileList\" + sid + @"\", "ProfileImagePath");
		}		
		
		//Completely purge a user from windows
		public static Boolean purgeUser(UserData user, Boolean deleteData) {
			LogHandler.log(LOG_NAME, "Purging " + user.getName() + " from system");
			if(deleteData) {
				if(unregisterUser(user.getName())) {
					if(removeUserProfile(user.getSID())) {
						return cleanUserRegistryEntries(user.getSID());
					}
				}
				return false;
			} else {
				return unregisterUser(user.getName());
			}
		}
	
		
		//Unregister a user from windows
		public static Boolean unregisterUser(String user) {
			try {
				DirectoryEntry userDir = new DirectoryEntry("WinNT://" + Environment.MachineName + ",computer");
				DirectoryEntry userToDelete = userDir.Children.Find(user);
				
				userDir.Children.Remove(userToDelete);
				return true;
				
			} catch (Exception ex) {
				LogHandler.log(LOG_NAME, "Unable to unregister user");
				LogHandler.log(LOG_NAME, "ERROR: " + ex.Message);
			}
			return false;			
		}
		
		//Delete user profile
		public static Boolean removeUserProfile(String sid) {
			
			try {
				String path = getUserProfilePath(sid);
				LogHandler.log(LOG_NAME, "User path: " + path);
				if(path != null) {
					takeOwnership(path);
					resetRights(path);
					removeWriteProtection(path);
					Directory.Delete(path, true);
					return true;
				}
			} catch (Exception ex) {
				LogHandler.log(LOG_NAME, "Unable to remove user data");
				LogHandler.log(LOG_NAME, "ERROR: " + ex.Message);
			}
			return false;
		}
		
		//Clean all registry entries of a user
		public static Boolean cleanUserRegistryEntries(String sid) {
			return RegistryHandler.deleteFolder(@"HKEY_LOCAL_MACHINE\SOFTWARE\Microsoft\Windows NT\CurrentVersion\ProfileList\" + sid + @"\");
		}
		
		public static void takeOwnership(String path) {

			using (new ProcessPrivileges.PrivilegeEnabler(Process.GetCurrentProcess(), ProcessPrivileges.Privilege.TakeOwnership)){
			    DirectoryInfo directoryInfo = new DirectoryInfo(path);
			    DirectorySecurity directorySecurity = directoryInfo.GetAccessControl();
			    directorySecurity.SetOwner(WindowsIdentity.GetCurrent().User);
			    Directory.SetAccessControl(path, directorySecurity);    
			}

		}
		
		private static DirectorySecurity RemoveExplicitSecurity(DirectorySecurity directorySecurity) {
			AuthorizationRuleCollection rules = directorySecurity.GetAccessRules(true, false, typeof(System.Security.Principal.NTAccount));
			foreach (FileSystemAccessRule rule in rules)
				directorySecurity.RemoveAccessRule(rule);
			return directorySecurity;
		}
		
		public static void resetRights(String path) {
			DirectoryInfo directoryInfo = new DirectoryInfo(path);
			DirectorySecurity directorySecurity = directoryInfo.GetAccessControl();
			directorySecurity = RemoveExplicitSecurity(directorySecurity);
			Directory.SetAccessControl(path, directorySecurity);
		}
		
		public static void removeWriteProtection(String path) {
			 DirectoryInfo directoryInfo = new DirectoryInfo(path);
			 directoryInfo.Attributes &= ~FileAttributes.ReadOnly;
		}		
		

	}
}