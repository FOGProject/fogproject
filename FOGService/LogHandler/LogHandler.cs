
using System;
using System.IO;
using System.Collections.Generic;

namespace FOG
{
	/// <summary>
	/// Handle all interaction with the log file
	/// </summary>
	public static class LogHandler
	{
		//Define variables
		private static String filePath = @"\fog.log";
		private static long maxLogSize = 502400;

		public static void setFilePath(String fPath) { filePath = fPath; }		
		public static String getFilePath() { return filePath; }
		public static void setMaxLogSize(long mLogSize) { maxLogSize = mLogSize; }	
		public static long getMaxLogSize() { return maxLogSize; }
		
		
		public static void log(String moduleName, String message) {
			StreamWriter logWriter;
			
			try {
				FileInfo logFile = new FileInfo(getFilePath());

				//Delete the log file if it excedes the max log size
				if (logFile.Exists && logFile.Length > getMaxLogSize())
					cleanLog();
				
				//Write message to log file
				logWriter = new StreamWriter(getFilePath(), true);
				logWriter.WriteLine(" " + DateTime.Now.ToShortDateString() + " " + DateTime.Now.ToShortTimeString() + 
				                    " " + moduleName + " " + message);
				logWriter.Close();
			} catch {
				//If logging fails then nothing can really be done to silently notify the user
			} 
		}
		
		public static void newLine() {
			StreamWriter logWriter;
			FileInfo logFile = new FileInfo(getFilePath());
			
					
			//Delete the log file if it excedes the max log size
			if (logFile.Exists && logFile.Length > maxLogSize)
				cleanLog();
			
			try {
				//Write message to log file
				logWriter = new StreamWriter(getFilePath(), true);
				logWriter.WriteLine("");
				logWriter.Close();
			} catch {
				//If logging fails then nothing can really be done to silently notify the user
			} 			
		}
		
		public static void divider() {
			StreamWriter logWriter;
			FileInfo logFile = new FileInfo(getFilePath());
			
					
			//Delete the log file if it excedes the max log size
			if (logFile.Exists && logFile.Length > maxLogSize)
				cleanLog();
			
			try {
				//Write message to log file
				logWriter = new StreamWriter(getFilePath(), true);
				logWriter.WriteLine("---------------------------------------------------------------");
				logWriter.Close();
			} catch {
				//If logging fails then nothing can really be done to silently notify the user
			} 			
		}
				
		private static void cleanLog() {
			try {
				FileInfo logFile = new FileInfo(getFilePath());
				logFile.Delete();
			} catch(Exception ex) {
				log(System.Reflection.Assembly.GetExecutingAssembly().GetName().Name, 
				    "Failed to delete log file: " + ex.Message);
			}
		}
	}
}