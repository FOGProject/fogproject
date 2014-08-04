
using System;
using System.IO;
using System.Collections.Generic;

namespace FOG
{
	/// <summary>
	/// Handle all interaction with the log file
	/// </summary>
	public class LogHandler
	{
		//Define variables
		private String filePath;
		private long maxLogSize;
		
		public LogHandler(String filePath, long maxLogSize) {
			this.filePath = filePath;
			this.maxLogSize = maxLogSize;
		}
		
		public void log(String moduleName, String message) {
			StreamWriter logWriter;
			FileInfo logFile = new FileInfo(this.filePath);
			
					
			//Delete the log file if it excedes the max log size
			if (logFile.Exists && logFile.Length > maxLogSize)
				cleanLog();
			
			try {
				//Write message to log file
				logWriter = new StreamWriter(this.filePath, true);
				logWriter.WriteLine(" " + DateTime.Now.ToShortDateString() + " " + DateTime.Now.ToShortTimeString() + 
				                    " " + moduleName + " " + message);
				logWriter.Close();
			} catch {
				//If logging fails then nothing can really be done to silently notify the user
			} 
		}
		
		private void cleanLog() {
			try {
				FileInfo logFile = new FileInfo(this.filePath);
				logFile.Delete();
			} catch(Exception ex) {
				log(System.Reflection.Assembly.GetExecutingAssembly().GetName().Name, 
				    "Failed to delete log file: " + ex.Message);
			}
		}
	}
}