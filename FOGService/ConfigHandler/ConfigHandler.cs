
using System;
using System.Collections.Generic;

using IniReaderObj;

namespace FOG
{
	/// <summary>
	/// Handle all interaction with the config file
	/// </summary>
	public class ConfigHandler
	{
		private String filePath;
		private IniReader configReader;
		
		public ConfigHandler(String filePath) {
			this.filePath = filePath;
			this.configReader = new IniReader(filePath);
		}
		
		public String getSetting(String section, String setting) {
			if(isConfigFileOK()) {
				return configReader.readSetting(section, setting);
			}
			
			return "";
		}
		
		public Boolean isConfigFileOK() {
			return this.configReader.isFileOk();
		}
		
	}
}