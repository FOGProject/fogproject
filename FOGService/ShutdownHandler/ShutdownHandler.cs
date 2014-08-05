
using System;
using System.Collections.Generic;
using System.Diagnostics;
using System.Runtime.InteropServices;

using FOG;

namespace FOG
{
	/// <summary>
	/// Handle all shutdown requests
	/// The windows shutdown command is used over the win32 api because it notifies the user prior
	/// </summary>
	public class ShutdownHandler {

		//Define variables
		private Boolean shutdownPending;
		private LogHandler logHandler;

		public ShutdownHandler(LogHandler logHandler) {
			this.shutdownPending = false;
			this.logHandler = logHandler;
		}
		
		//Load the ability to lock the computer from the native user32 dll
		[DllImport("user32")]
		private static extern void LockWorkStation();
		
		//List all possible shutdown types
		public enum ShutDownType {
			LogOff = 0,
			Shutdown = 1,
			Reboot = 2,
			ForcedLogOff = 4,
			ForcedShutdown = 5,
			ForcedReboot = 6,
			PowerOff = 8,
			ForcedPowerOff = 12
		}
		
		//List options on how to exit windows
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
		
		public Boolean isShutdownPending() { return shutdownPending; }
		
		private void createShutdownCommand(String parameters) {
			logHandler.log(System.Reflection.Assembly.GetExecutingAssembly().GetName().Name, 
			               "Creating shutdown request");
			logHandler.log(System.Reflection.Assembly.GetExecutingAssembly().GetName().Name, 
			               "Parameters: " + parameters);
			Process.Start("shutdown", parameters);
		}
		
		public void shutdown(String comment, int seconds) {
			this.shutdownPending = true;
			
			createShutdownCommand("/s /c \"" + comment + "\" /t " + seconds);
		}
		
		public void restart(String comment, int seconds) {
			this.shutdownPending = true;
			
			createShutdownCommand("/r /c \"" + comment + "\" /t " + seconds);
		}		
		
		public void logOffUser(String comment, int seconds) {
			createShutdownCommand("/l /c \"" + comment + "\" /t " + seconds);
		}
		
		public void hibernate(String comment, int seconds) {
			createShutdownCommand("/h" );
		}
		
		public void lockWorkStation() {			
			LockWorkStation();
		}
		
		public void abortShutdown() {		
			this.shutdownPending = false;
			createShutdownCommand("/a");
		}
		
	}
}