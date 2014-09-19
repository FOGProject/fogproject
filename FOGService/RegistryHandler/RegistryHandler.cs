
using System;
using Microsoft.Win32;
using System.Collections.Generic;

namespace FOG {
	/// <summary>
	/// Handle all interaction with the registry
	/// </summary>
	public static class RegistryHandler {

		private const String LOG_NAME = "RegistryHandler";
		
		public static String getSystemSetting(String name) {
			if(getRegisitryValue(@"Software\Wow6432Node\FOG\", "Server") != null) {
				return getRegisitryValue(@"Software\Wow6432Node\FOG\", name);
			} else if(getRegisitryValue(@"Software\FOG\", "Server") != null) {
				LogHandler.log(LOG_NAME, "32 bit registry detected");
			}
			
			//If the regisitry keys cannot be found, return null because the program should not procede
			return null; 
		}
		
		public static String getRegisitryValue(String keyPath, String keyName) {
			LogHandler.log(LOG_NAME, "Attempting to get " + keyPath + keyName);
			try {
				RegistryKey key = Registry.LocalMachine.OpenSubKey(keyPath);
	            if (key != null) {
	            	String keyValue = key.GetValue(keyName).ToString();
	            	if (keyValue != null) {
	            		return keyValue.Trim();
	                }
	            }	
			} catch (Exception ex) {
				LogHandler.log(LOG_NAME, "Error getting registry key");
				LogHandler.log(LOG_NAME, "ERROR: " + ex.Message);
			}
			return null;
		}
		
		public static Boolean deleteFolder(String path) {
			LogHandler.log(LOG_NAME, "Attempting to delete " + path);
			try {
				RegistryKey key = Registry.LocalMachine.OpenSubKey(path, true);
				if (key != null) {
					key.DeleteSubKeyTree(path);
					return true;
				}
			} catch (Exception ex) {
				LogHandler.log(LOG_NAME, "Error while trying to remove folder");
				LogHandler.log(LOG_NAME, "ERROR: " + ex.Message);
			}
			
			return false;
		}		
		
	}
}