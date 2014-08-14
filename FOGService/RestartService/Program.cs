
using System;
using System.ServiceProcess;

namespace FOG {
	class Program {
		private static ServiceController service;
		
		public static void Main(string[] args) {
			service = new ServiceController("fogservice");
			stopService();
			startService();
		}
		
		private static void stopService() {
			service.Stop();
			service.WaitForStatus(ServiceControllerStatus.Stopped);
		}
		
		private static void startService() {
			service.Start();
			service.WaitForStatus(ServiceControllerStatus.Running);
		}
	}
}