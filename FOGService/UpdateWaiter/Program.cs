
using System;
using System.IO;
using System.Threading;
using System.Diagnostics;

namespace FOG {
	class Program {
		
		public static void Main(string[] args) {
			
			//Check if an parameter was passed
			if(args.Length > 0) {
				//Wait for all update files to be applied
				while(updateFilesPresent()) { }
				
				//Wait 5 seconds to ensure the update process is complete
				Thread.Sleep(5 * 1000);
				
				//Spawn the process that originally called this program
				if(File.Exists(args[0]))
					spawnParentProgram(args[0]);
			}
			
		}
		
		private static Boolean updateFilesPresent() {
			foreach(String fileName in Directory.GetFiles(AppDomain.CurrentDomain.BaseDirectory)) {
				if(fileName.EndsWith(".update"))
					return true;
			}
			
			return false;
		}
		
		private static void spawnParentProgram(String fileName) {
			Process process = new Process();
			process.StartInfo.UseShellExecute = false;
			process.StartInfo.FileName = fileName;
			process.Start();				
		}
		
	}
}