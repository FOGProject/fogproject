
using System;
using System.IO;
using System.Diagnostics;
using System.Threading;

namespace FOG {
	/// <summary>
	/// Description of Updater.
	/// </summary>
	public static class UpdateHandler {
		
		private const String LOG_NAME = "Service-Update";
				
		private static void killSubProcesses() {
			//If the User Service is still running, wait 120 seconds and kill it
			
			while( Process.GetProcessesByName("FOGUserService").Length > 0) {
				Thread.Sleep(12 * 1000);
				foreach(Process process in Process.GetProcessesByName("FOGUserService")) {
					process.Kill();
				}
			}
		}
		
		public static void beginUpdate(PipeServer servicePipe) {
			try {
				//Create updating.info which will warn any sub-processes currently initializing that they should halt
				File.WriteAllText(AppDomain.CurrentDomain.BaseDirectory + @"\updating.info", "");
				
				//Give time for any sub-processes that may be in the middle of initializing and missed the updating.info file so they can recieve the update pipe notice
				Thread.Sleep(1000);
				
				//Notify all FOG sub processes that an update is about to occu
				servicePipe.sendMessage("UPD");
				
				//Kill any FOG sub processes still running after the notification
				killSubProcesses();
				
				//Launch the updater
				LogHandler.log(LOG_NAME, "Spawning update helper");
				
				Process process = new Process();
				process.StartInfo.UseShellExecute = false;
				process.StartInfo.FileName = Path.GetDirectoryName(System.Reflection.Assembly.GetExecutingAssembly().Location) + @"\FOGUpdateHelper.exe";
				process.Start();
			} catch (Exception ex) {
				LogHandler.log(LOG_NAME, "Unable to perform update");
				LogHandler.log(LOG_NAME, "ERROR: " + ex.Message);				
			}
		} 
	}
}
