using System;
using System.IO;
using System.Management;
using System.Collections;
using System.Collections.Generic;
using System.Data;
using System.Net;
using System.Runtime.InteropServices;
using System.Diagnostics;
using IniReaderObj;
using System.Net.NetworkInformation;

namespace FOG
{
	/// <summary>
	/// Provide basic methods to FOG Serverice modules
	/// </summary>
	public abstract class AbstractService
	{
		//Define service status constants
		public const int STATUS_RUNNING = 0;
		public const int STATUS_STOPPED = 1;
		public const int STATUS_TASKCOMPLETE = 2;
		public const int STATUS_FAILED = -1;
		
		//Import DLL methods
		[DllImport("user32.dll")]
		private static extern bool IsIconic(IntPtr hWnd);
		[DllImport("user32.dll", ExactSpelling = true, SetLastError = true)]
		internal static extern bool ExitWindowsEx(ExitWindows flg, ShutdownReason rea);
		[DllImport("kernel32.dll", ExactSpelling = true)]
		internal static extern IntPtr GetCurrentProcess();
		[DllImport("advapi32.dll", ExactSpelling = true, SetLastError = true)]
		internal static extern bool OpenProcessToken(IntPtr h, int acc, ref IntPtr phtok);
		[DllImport("advapi32.dll", SetLastError = true)]
		internal static extern bool LookupPrivilegeValue(string host, string name, ref long pluid);
		[DllImport("advapi32.dll", ExactSpelling = true, SetLastError = true)]
		internal static extern bool AdjustTokenPrivileges(IntPtr htok, bool disall,
		ref TokPriv1Luid newst, int len, IntPtr prev, IntPtr relen);
		
		
		[StructLayout(LayoutKind.Sequential, Pack = 1)]
		internal struct TokPriv1Luid
		{
			public int Count;
			public long Luid;
			public int Attr;
		}

		[Flags]
		public enum ExitWindows : uint
		{
			LogOff = 0x00,
			ShutDown = 0x01,
			Reboot = 0x02,
			PowerOff = 0x08,
			RestartApps = 0x40,
			Force = 0x04,
			ForceIfHung = 0x10,
		}

		[Flags]
		internal enum ShutdownReason : uint
		{
			MajorApplication = 0x00040000,
			MajorHardware = 0x00010000,
			MajorLegacyApi = 0x00070000,
			MajorOperatingSystem = 0x00020000,
			MajorOther = 0x00000000,
			MajorPower = 0x00060000,
			MajorSoftware = 0x00030000,
			MajorSystem = 0x00050000,

			MinorBlueScreen = 0x0000000F,
			MinorCordUnplugged = 0x0000000b,
			MinorDisk = 0x00000007,
			MinorEnvironment = 0x0000000c,
			MinorHardwareDriver = 0x0000000d,
			MinorHotfix = 0x00000011,
			MinorHung = 0x00000005,
			MinorInstallation = 0x00000002,
			MinorMaintenance = 0x00000001,
			MinorMMC = 0x00000019,
			MinorNetworkConnectivity = 0x00000014,
			MinorNetworkCard = 0x00000009,
			MinorOther = 0x00000000,
			MinorOtherDriver = 0x0000000e,
			MinorPowerSupply = 0x0000000a,
			MinorProcessor = 0x00000008,
			MinorReconfig = 0x00000004,
			MinorSecurity = 0x00000013,
			MinorSecurityFix = 0x00000012,
			MinorSecurityFixUninstall = 0x00000018,
			MinorServicePack = 0x00000010,
			MinorServicePackUninstall = 0x00000016,
			MinorTermSrv = 0x00000020,
			MinorUnstable = 0x00000006,
			MinorUpgrade = 0x00000003,
			MinorWMI = 0x00000015,

			FlagUserDefined = 0x40000000,
			FlagPlanned = 0x80000000
		}

		internal const int SE_PRIVILEGE_ENABLED = 0x00000002;
		internal const int TOKEN_QUERY = 0x00000008;
		internal const int TOKEN_ADJUST_PRIVILEGES = 0x00000020;
		internal const string SE_SHUTDOWN_NAME = "SeShutdownPrivilege";
		
		
		protected IniReader ini;
		private String strLogPath = @".\fog.log";
		private long maxLogSize = 0;
		private Boolean notificationsEnabled = false;

		private static List<Notification> notifications;

		public abstract void start();

		public abstract Boolean stop();

		public abstract int getStatus();

		public abstract String getDescription();

		public abstract String getName();

		public void pushMessage(String title, String msg)
		{
			// Only allow visual messages if the ini  says it is OK

			
			if (notifications == null)
				notifications = new List<Notification>();

			// Only allow 10 messages in queue
			// This prevents the queue from backing up
			if (notifications.Count < 11  && notificationsEnabled)
				notifications.Add(new Notification(title, msg));
		}
	


		public Boolean hasMessages()
		{
			return (notifications != null && notifications.Count > 0);
		}

		public void setINIReader(IniReader ini)
		{
			this.ini = ini;
			strLogPath = this.ini.readSetting("fog_service", "logfile");
			String strMaxSize = this.ini.readSetting("fog_service", "maxlogsize");
			long output;
			if (long.TryParse(strMaxSize, out output))
			{
				try
				{
					maxLogSize = long.Parse(strMaxSize);
				}
				catch (Exception) { }
			}
			if (ini.readSetting("fog_service", "notificationsEnabled") == "1")
				notificationsEnabled = true;
		}

		public void log(String moduleName, String logFilePath)
		{
			StreamWriter logWriter;
			try {
				if (maxLogSize > 0 && logFilePath != null)
				{
					FileInfo logFile = new FileInfo(logFilePath);
					if (logFile.Exists && logFile.Length > maxLogSize)
						logFile.Delete();

					logWriter = new StreamWriter(logFilePath, true);
					logWriter.WriteLine(" " + DateTime.Now.ToShortDateString() + " " + 
					                    DateTime.Now.ToShortTimeString() + " " + moduleName + " " + logFilePath);
					logWriter.Close();
				}
			} catch { }			
		}

		public String getDateTime()
		{
			return DateTime.Now.ToShortTimeString() + " " + DateTime.Now.ToShortDateString();
		}

		public enum ShutDown
		{
			LogOff = 0,
			Shutdown = 1,
			Reboot = 2,
			ForcedLogOff = 4,
			ForcedShutdown = 5,
			ForcedReboot = 6,
			PowerOff = 8,
			ForcedPowerOff = 12
		}

		public void restartComputer()
		{
			ManagementClass W32_OS = new ManagementClass("Win32_OperatingSystem");
			ManagementBaseObject inParams, outParams;
			int result;
			W32_OS.Scope.Options.EnablePrivileges = true;
			Boolean blActionAttempted = false;
			foreach (ManagementObject obj in W32_OS.GetInstances())
			{
				blActionAttempted = true;
				inParams = obj.GetMethodParameters("Win32Shutdown");
				inParams["Flags"] = ShutDown.ForcedReboot;
				inParams["Reserved"] = 0;
				outParams = obj.InvokeMethod("Win32Shutdown", inParams, null);
				result = Convert.ToInt32(outParams["returnValue"]);
				if (result != 0)
				{
					log("FOG Service", "Mananged restart method failed, attempting unmanaged api call.");
					unmanagedExitWindows(ExitWindows.Reboot | ExitWindows.Force);
				}
			}

			if (!blActionAttempted)
				unmanagedExitWindows(ExitWindows.Reboot | ExitWindows.Force);
			
		}

		private Boolean unmanagedExitWindows(ExitWindows flag)
		{
			
			TokPriv1Luid tp;
			IntPtr hproc = GetCurrentProcess();
			IntPtr htok = IntPtr.Zero;
			
			OpenProcessToken(hproc, TOKEN_ADJUST_PRIVILEGES | TOKEN_QUERY, ref htok);
			
			tp.Count = 1;
			tp.Luid = 0;
			tp.Attr = SE_PRIVILEGE_ENABLED;
			
			LookupPrivilegeValue(null, SE_SHUTDOWN_NAME, ref tp.Luid);
			AdjustTokenPrivileges(htok, false, ref tp, 0, IntPtr.Zero, IntPtr.Zero);
			
			return ExitWindowsEx(flag, ShutdownReason.MinorOther);
		}

		public void shutdownComputer()
		{
			ManagementClass W32_OS = new ManagementClass("Win32_OperatingSystem");
			ManagementBaseObject inParams, outParams;
			W32_OS.Scope.Options.EnablePrivileges = true;
			Boolean shutdownAttempted = false;
			int shutdownResult = 0;
			
			//Attempt to perform a managed shutdown
			foreach (ManagementObject obj in W32_OS.GetInstances()) {
				shutdownAttempted = true;
				inParams = obj.GetMethodParameters("Win32Shutdown");
				inParams["Flags"] = ShutDown.ForcedShutdown;
				inParams["Reserved"] = 0;
				outParams = obj.InvokeMethod("Win32Shutdown", inParams, null);
				shutdownResult = Convert.ToInt32(outParams["returnValue"]);
			}

			//If a managed shutdown fails, try and perform an unmanaged one
			if (!shutdownAttempted || shutdownResult == 0) {
				log(getName(), "Mananged shutdown method failed, attempting unmanaged api call.");
				if(!unmanagedExitWindows(ExitWindows.ShutDown | ExitWindows.Force));
					log(getName(), "Unmanaged shutdown method failed");
			}
		}

		public Boolean isLoggedIn()
		{
			return getAllUsers().Count > 0;
		}

		public String getHostName()
		{
			return System.Environment.MachineName;
		}

		public String getIPAddress()
		{
			String ipAddress = "";
			try {
				String hostName = Dns.GetHostName();

				IPHostEntry ip = Dns.GetHostEntry(hostName);

				if (ip.AddressList.Length > 0)
					ipAddress = ip.AddressList[0].ToString();
			} catch (Exception ex) {
				log(getName(), "Error getting ip addresses: " + ex.Message);
			}
			return ipAddress;
		}

		public List<String> getMacAddress()
		{
            List<String> macs = new List<String>();
			try {
				NetworkInterface[] adapters = NetworkInterface.GetAllNetworkInterfaces();
				
				foreach (NetworkInterface adapter in adapters) {
					IPInterfaceProperties properties = adapter.GetIPProperties();
					macs.Add( adapter.GetPhysicalAddress().ToString() );
				}
				
			} catch (Exception ex) {
				log(getName(), "Error getting MAC addresses: " + ex.Message);
			}
			
			return macs;
		}

		public List<String> getAllUsers()
		{
			List<String> users = new List<String>();
			try {
				ManagementObjectSearcher searcher = new ManagementObjectSearcher("root\\CIMV2", 
				                                                                 "SELECT * FROM Win32_ComputerSystem");

				foreach (ManagementObject queryObj in searcher.Get())
				{
					users.Add( queryObj["UserName"].ToString() );
				}
				
			} catch (Exception ex) {
				log(getName(), "Error geetting all users: " + ex.Message);
			}
			
			return users;
		}
		
	}
}