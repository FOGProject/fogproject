
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
		private static long maxLogSizeDefault = 502400;
		private static long maxLogSize = maxLogSizeDefault;
		private const String LOG_NAME = "LogHandler";

		public static void setFilePath(String fPath) { filePath = fPath; }		
		public static String getFilePath() { return filePath; }
		public static void setMaxLogSize(long mLogSize) { maxLogSize = mLogSize; }	
		public static long getMaxLogSize() { return maxLogSize; }
		public static void defaultMaxLogSize() { maxLogSize = maxLogSizeDefault; }
		
		//Log a message
		public static void log(String moduleName, String message) {
			writeLine(" " + DateTime.Now.ToShortDateString() + " " + DateTime.Now.ToShortTimeString() + 
			          " " + moduleName + " " + message);
		}
		
		//Make a new line in the log file
		public static void newLine() {
			writeLine("");
		}
		
		//Make a divider in the log file
		public static void divider() {
			writeLine("---------------------------------------------------------------");		
		}
		
		//Write a string to a line, other classes should not call this function directly for formatting purposes
		private static void writeLine(String line) {
			StreamWriter logWriter;
			FileInfo logFile = new FileInfo(getFilePath());

			//Delete the log file if it excedes the max log size
			if (logFile.Exists && logFile.Length > maxLogSize)
				cleanLog(logFile);
			
			try {
				//Write message to log file
				logWriter = new StreamWriter(getFilePath(), true);
				logWriter.WriteLine(line);
				logWriter.Close();
			} catch {
				//If logging fails then nothing can really be done to silently notify the user
			} 					
		}
		
		//Delete the log file and create a new one
		private static void cleanLog(FileInfo logFile) {
			try {
				logFile.Delete();
			} catch(Exception ex) {
				log(LOG_NAME, "Failed to delete log file: " + ex.Message);
			}
		}
	}
}