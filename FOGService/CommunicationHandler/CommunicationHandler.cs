
using System;
using System.Collections.Generic;
using System.Net;
using System.IO;
using System.Linq;
using System.Net.NetworkInformation;

namespace FOG
{
	/// <summary>
	/// Handle all communication with the FOG Server
	/// </summary>
	public class CommunicationHandler
	{
		private String serverAddress;
		private WebClient webClient;
		private LogHandler logHandler;
		private String successCode;
			
		public CommunicationHandler(LogHandler logHandler, String serverAddress) {
			this.serverAddress = serverAddress;
			this.webClient = new WebClient();
			this.logHandler = logHandler;
			this.successCode = "#!ok";
			
		}
		
		public Response getResponse(String postfix) {
			
			String dataRecieved = this.webClient.DownloadString(this.serverAddress + postfix);
			return parseResponse(dataRecieved);
		}
		
		private Response parseResponse(String rawResponse) {
			String[] data = rawResponse.Split('\n');
			Dictionary<String, String> parsedData = new Dictionary<String, String>();
			Response response = new Response();
			
			try {
				String returnCode = data[0];
				response.setError(returnCode.Equals(successCode));
				
				foreach(String element in data) {
					if(element.Contains("=")) {
						logHandler.log(System.Reflection.Assembly.GetExecutingAssembly().GetName().Name,
						               element.Substring(0, element.IndexOf("=")).Trim() + " = " +
						               element.Substring(element.IndexOf("=")+1).Trim());
						               
						parsedData.Add(element.Substring(0, element.IndexOf("=")).Trim(),
						               element.Substring(element.IndexOf("=")+1).Trim());
					}
				}
				
				response.setData(parsedData);
			} catch (Exception ex) {
				logHandler.log(System.Reflection.Assembly.GetExecutingAssembly().GetName().Name,
				               "Error parsing response: " + ex.Message);
			}
			return response;
		}
		
		public Boolean downloadFile(String postfix, String fileName) {
			try {
				this.webClient.DownloadFile(this.serverAddress + postfix, fileName);
				return true;
			} catch (Exception ex) {
				logHandler.log(System.Reflection.Assembly.GetExecutingAssembly().GetName().Name, 
				               "Failed to download: " + this.serverAddress + postfix);
				logHandler.log(System.Reflection.Assembly.GetExecutingAssembly().GetName().Name, 
				               "Error: " + ex.Message);				
			}
			return false;
		}
		
		public String getIPAdress() {
			String hostName = System.Net.Dns.GetHostName();
			
			IPHostEntry ipEntry = System.Net.Dns.GetHostEntry(hostName);
			
			IPAddress[] address = ipEntry.AddressList;
			if(address.Length > 0)
				return address[0].ToString();
			return "";
		}
		
		public String getMacAddresses()
		{
            String macs = "";
			try {
				NetworkInterface[] adapters = NetworkInterface.GetAllNetworkInterfaces();
				
				foreach (NetworkInterface adapter in adapters) {
					IPInterfaceProperties properties = adapter.GetIPProperties();
					macs = macs + "|" + string.Join (":", (from z in adapter.GetPhysicalAddress().GetAddressBytes() select z.ToString ("X2")).ToArray());
					
				}
				macs = macs.Substring(1); // Remove the first |
				
			} catch (Exception ex) {
				logHandler.log(System.Reflection.Assembly.GetExecutingAssembly().GetName().Name, 
            	               "Error getting MAC addresses: " + ex.Message);
			}
			
			return macs;
		}
	}
}
