
using System;
using System.Collections.Generic;
using System.Net;
using System.IO;

namespace FOG
{
	/// <summary>
	/// Handle all communication with the FOG Server
	/// </summary>
	public class CommunicationHandler
	{
		private String serverAddress;
		private Dictionary<String, String> returnMessages;
		private WebClient webClient;
		private LogHandler logHandler;
			
		public CommunicationHandler(LogHandler logHandler, String serverAddress) {
			this.serverAddress = serverAddress;
			this.webClient = new WebClient();
			this.logHandler = logHandler;
			
			this.returnMessages = new Dictionary<String, String>();
			this.returnMessages.Add("#!ok", "Success");
			this.returnMessages.Add("#!db", "Database error");
			this.returnMessages.Add("#!im", "Invalid MAC address format");
			this.returnMessages.Add("#!ng", "Module is disabled globablly on the FOG Server");
			this.returnMessages.Add("#!nh", "Module is diabled on the host");
			this.returnMessages.Add("#!um", "Unknown module ID");
			this.returnMessages.Add("#!er", "General Error");
			this.returnMessages.Add("#!er", "General Error");
			
		}
		
		public Dictionary<String, String> getResponse(String postfix) {
			
			String response = this.webClient.DownloadString(this.serverAddress + postfix);
			
			
			foreach(String returnMessage in returnMessages.Keys) {
				if(response.StartsWith(returnMessage)) {
					
					if(returnMessages[returnMessage].Equals("Success")) {
						return parseResponse(response);
					}
					
					logHandler.log(System.Reflection.Assembly.GetExecutingAssembly().GetName().Name,
									"Error getting response from: " + this.serverAddress + postfix + 
									"error: " + returnMessages[returnMessage]);
					break;				
				}
			}
			
			return new Dictionary<String, String>();
		}
		
		public Boolean downloadFile(String postfix, String fileName) {
			try {
				this.webClient.DownloadFile(this.serverAddress + postfix, fileName);
				return true;
			} catch (Exception ex) {
				logHandler.log(System.Reflection.Assembly.GetExecutingAssembly().GetName().Name, 
				               "Failed to download: " + this.serverAddress + postfix + " because: " + ex.Message);
			}
			return false;
		}
		
		private Dictionary<String, String> parseResponse(String response) {
			String[] data = response.Split('\n');
			
			Dictionary<String, String> parsedData = new Dictionary<String, String>();
			
			foreach(String element in data) {
				parsedData.Add(element.Substring(0, element.IndexOf("=")-1), 
				               element.Substring(element.IndexOf("=")+1));
			}
			
			return parsedData;
		}
	}
}