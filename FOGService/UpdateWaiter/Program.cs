
using System;
using System.IO;
using System.Threading;
using System.Diagnostics;

namespace FOG {
	class Program {
		
		public static void Main(string[] args) {
			//Update Line
			//Check if an parameter was passed
			if(args.Length > 0) {
				//Wait for all update files to be applied
				while(updateFilePresent()) { }				
				//Spawn the process that originally called this program
				if(File.Exists(args[0]))
					spawnParentProgram(args[0]);
			}
			
		}
		
		private static Boolean updateFilePresent() {
			Boolean fileFound = false;
			foreach(String fileName in Directory.GetFiles(AppDomain.CurrentDomain.BaseDirectory)) {
				if(fileName.EndsWith("updating.info")) 
					fileFound =  true;
			}
			Thread.Sleep(10 * 1000);
			
			return fileFound;
		}
		
		private static void spawnParentProgram(String fileName) {
			Process process = new Process();
			process.StartInfo.UseShellExecute = false;
			process.StartInfo.FileName = fileName;
			process.Start();				
		}
		
	}
}