
using System;
using System.IO;
using System.Threading;
using System.ServiceProcess;
using System.Diagnostics;

namespace FOG {
	class Program {
		
		public static void Main(string[] args) {
			ServiceController service = new ServiceController("fogservice");
			
			//Stop the service
			service.Stop();
			service.WaitForStatus(ServiceControllerStatus.Stopped);
			
			if( Process.GetProcessesByName("FOGService").Length > 0) {
				foreach(Process process in Process.GetProcessesByName("FOGService")) {
					process.Kill();
				}
			}
			
			for(int i=0; i <5; i++) {
				if(applyUpdates())
					break;
				Thread.Sleep(5000);
			}
			
			//Start the service

			service.Start();
			service.WaitForStatus(ServiceControllerStatus.Running);
			
			if(File.Exists(AppDomain.CurrentDomain.BaseDirectory + @"\updating.info"))
				File.Delete(AppDomain.CurrentDomain.BaseDirectory + @"\updating.info");
			
		}
		
		private static Boolean applyUpdates() {
			Boolean success = false;
			
			foreach(String updateFile in Directory.GetFiles(AppDomain.CurrentDomain.BaseDirectory)) {
				if(updateFile.EndsWith(".update")) {
					String postUpdateFile = updateFile.Substring(0, updateFile.Length-(".update").Length);
					
					try {
						File.Delete(postUpdateFile);
						File.Move(updateFile, postUpdateFile);
					} catch (Exception) {
						success = false;
					}
				}
			}
			return success;
			
		}
	}
}