
using System;
using System.Runtime.InteropServices;

namespace FOG {
	/// <summary>
	/// Get the state of the attempted display change
	/// </summary>
	public class UnmanagedWin32 {
		[DllImport("user32.dll")]
		public static extern int EnumDisplaySettings(string deviceName, int modeNum, ref DisplayChanger.DEVMODE1 devMode);
	
		[DllImport("user32.dll")]
		public static extern int ChangeDisplaySettings(ref DisplayChanger.DEVMODE1 devMode, int flags);
	
		public const int ENUM_CURRENT_SETTINGS = -1;
	
		public const int CDS_UPDATEREGISTRY = 0x01;
		public const int CDS_TEST = 0x02;
	
		public const int DISP_CHANGE_SUCCESSFUL = 0;
		public const int DISP_CHANGE_RESTART = 1;
		public const int DISP_CHANGE_FAILED = -1;
	}
}
