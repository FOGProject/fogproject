using System;
using System.IO;
using System.Management;
using System.Collections;
using System.Data;
using System.Net;
using System.Runtime.InteropServices;
using System.Diagnostics;
using IniReaderObj;
using System.Net.NetworkInformation;

namespace FOG 
{
	public abstract class AbstractFOGService
	{
		public const int STATUS_RUNNING = 0;
		public const int STATUS_STOPPED = 1;
		public const int STATUS_TASKCOMPLETE = 2;
		public const int STATUS_FAILED = -1;

		public const String VERSION = "3";

		[DllImport("user32.dll")]
		private static extern bool SetForegroundWindow(IntPtr hWnd);
		[DllImport("user32.dll")]
		private static extern bool ShowWindowAsync(IntPtr hWnd, int nCmdShow);
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

		private const int SW_HIDE = 0;
		private const int SW_SHOWNORMAL = 1;
		private const int SW_SHOWMINIMIZED = 2;
		private const int SW_SHOWMAXIMIZED = 3;
		private const int SW_SHOWNOACTIVATE = 4;
		private const int SW_RESTORE = 9;
		private const int SW_SHOWDEFAULT = 10;

		protected IniReader ini;
		private String strLogPath = @".\fog.log";
		private long maxLogSize = 0;

		private FrmUI gui = new FrmUI();
		private static ArrayList alMessages;

		public abstract void mStart();

		public abstract Boolean mStop();

		public abstract int mGetStatus();

		public abstract String mGetDescription();

		public void pushMessage(String strMessage)
		{
			// Only allow visual messages if the ini
			// says its OK.

			Boolean addMessage = false;
			if (ini != null)
			{
				if (ini.isFileOk())
				{
					if (ini.readSetting("fog_service", "guienabled") == "1")
						addMessage = true;
				}
			}

			if (alMessages == null)
				alMessages = new ArrayList();

			if (addMessage)
			{
				// Only allow 10 messages in queue
				// This prevents the queue from backing up if the
				// GUIWatcher Fails or gets deleted
				if ( alMessages.Count < 11 )
					alMessages.Add(strMessage);
			}
		}

		public Boolean hasMessages()
		{
			return (alMessages != null && alMessages.Count > 0);
		}

		public Boolean isGUIActive()
		{
			return gui.Visible;
		}

		public Boolean attemptPushToGUI()
		{
			if ( !isGUIActive()) 
			{
			if (isLoggedIn())
			{
				if (alMessages != null)
				{
					if (alMessages.Count > 0)
					{
						String msg = (String)alMessages[0];
						alMessages.RemoveAt(0);
						gui = new FrmUI();
						gui.Left = System.Windows.Forms.Screen.PrimaryScreen.Bounds.Width - gui.Width;
						
						gui.Top = 0;
						gui.setMessage(msg);
						gui.Height = 150;
						gui.Opacity = 0;
						gui.Show();

						
						while (gui.Opacity < 1)
						{
							gui.Opacity += 0.01;
							gui.Refresh();
							try
							{
								System.Threading.Thread.Sleep(5);
							}
							catch { }
						}

						try
						{
							for (int i = 10; i > 0; i--)
							{
								gui.setTime(i);
								gui.Refresh();
								System.Threading.Thread.Sleep(1000);
								
							}
						}
						catch { }

						while( gui.Opacity > 0 )
						{
							gui.Opacity -= 0.01;
							gui.Refresh();
							try
							{
								System.Threading.Thread.Sleep(5);
							}
							catch { }
						}
						
						gui.Visible = false;
						gui.Refresh();
						gui.Close();
						
						return true;
					}
				}
			}
		}
		return false;
		}

		public void setINIReader(IniReader i)
		{
			this.ini = i;
			strLogPath = ini.readSetting("fog_service", "logfile");
			String strMaxSize = ini.readSetting("fog_service", "maxlogsize");
			long output;
			if (long.TryParse(strMaxSize, out output))
			{
				try
				{
					maxLogSize = long.Parse(strMaxSize);
				}
				catch (Exception)
				{ }
			}
		}

		public void log(String moduleName, String strlog)
		{
			StreamWriter objReader;
			try
			{
				if (maxLogSize > 0 && strLogPath != null && strLogPath.Length > 0)
				{
					FileInfo f = new FileInfo(strLogPath);
					if (f.Exists && f.Length > maxLogSize)
					{
						f.Delete();
					}

					objReader = new StreamWriter(strLogPath, true);
					objReader.WriteLine(" " + DateTime.Now.ToShortDateString() + " " + DateTime.Now.ToShortTimeString() + " " + moduleName + " " + strlog);
					objReader.Close();
				}
			}
			catch
			{

			}			
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

		public void unmanagedExitWindows(ExitWindows flag)
		{

			bool blOK;
			TokPriv1Luid tp;
			IntPtr hproc = GetCurrentProcess();
			IntPtr htok = IntPtr.Zero;
			blOK = OpenProcessToken(hproc, TOKEN_ADJUST_PRIVILEGES | TOKEN_QUERY, ref htok);
			tp.Count = 1;
			tp.Luid = 0;
			tp.Attr = SE_PRIVILEGE_ENABLED;
			blOK = LookupPrivilegeValue(null, SE_SHUTDOWN_NAME, ref tp.Luid);
			blOK = AdjustTokenPrivileges(htok, false, ref tp, 0, IntPtr.Zero, IntPtr.Zero);
			blOK = ExitWindowsEx(flag, ShutdownReason.MinorOther);
		
		}

		public void shutdownComputer()
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
				inParams["Flags"] = ShutDown.ForcedShutdown;
				inParams["Reserved"] = 0;
				outParams = obj.InvokeMethod("Win32Shutdown", inParams, null);
				result = Convert.ToInt32(outParams["returnValue"]);
				if (result != 0)
				{
					log("FOG Service", "Mananged shutdown method failed, attempting unmanaged api call.");
					unmanagedExitWindows(ExitWindows.ShutDown | ExitWindows.Force);
				}
			}

			if (!blActionAttempted)
				unmanagedExitWindows(ExitWindows.ShutDown | ExitWindows.Force);
		}

		public Boolean isLoggedIn()
		{
			String uname = getUserName();
			return (uname != null && uname.Length > 0 );
		}

		public String getHostName()
		{
			return System.Environment.MachineName;
		}

		public ArrayList getIPAddress()
		{
			ArrayList arIPs = new ArrayList();
			try
			{
				String strHost = null;
				strHost = Dns.GetHostName();

				IPHostEntry ip = Dns.GetHostEntry(strHost);
				IPAddress[] ipAddys = ip.AddressList;

				if (ipAddys.Length > 0)
					arIPs.Add(ipAddys[0].ToString());
			}
			catch
			{ }
			return arIPs;
		}

		public ArrayList getMacAddress()
		{
            // Variables
            ArrayList alMacs = new ArrayList();
			
			try
			{
				// Get all network interaces
				NetworkInterface[] adapters = NetworkInterface.GetAllNetworkInterfaces();
				
				// Iterate all network interaces
				foreach (NetworkInterface adapter in adapters)
				{
					// Get IP Properties
					IPInterfaceProperties properties = adapter.GetIPProperties();
					
					// Push MAC address into array
					alMacs.Add( adapter.GetPhysicalAddress().ToString() );
				}
			}
			catch (Exception e)
			{
				log("FOG Service", e.Message);
			}
			
			return alMacs;
		}

		public String[] getAllUsers()
		{
			ArrayList alUsers = new ArrayList();
			try
			{

				try
				{
					ManagementObjectSearcher searcher = new ManagementObjectSearcher("root\\CIMV2", "SELECT * FROM Win32_ComputerSystem");

					foreach (ManagementObject queryObj in searcher.Get())
					{
						alUsers.Add( queryObj["UserName"].ToString() );
					}
				}
				catch
				{
					return null;
				}

			}
			catch
			{
				return null;
			}
			return (String[])(alUsers.ToArray(typeof(String)));
		}

		// depracted as of version 0.12 of FOG
		// please use getAllUsers()
		public String getUserName()
		{
			String username = null;
			try
			{

					try
					{
						ManagementObjectSearcher searcher = new ManagementObjectSearcher("root\\CIMV2", "SELECT * FROM Win32_ComputerSystem");

						foreach (ManagementObject queryObj in searcher.Get())
						{
							return queryObj["UserName"].ToString();
						}						
					}
					catch 
					{
						return null;
					}
				
			}
			catch 
			{
				return null;
			}
			return username;
		}
	}
}
