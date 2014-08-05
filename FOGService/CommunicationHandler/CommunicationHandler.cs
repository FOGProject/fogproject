
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
		//Define variables
		private String serverAddress;
		private WebClient webClient;
		private LogHandler logHandler;
		private String successCode;
		private Dictionary<String, String> returnMessages;

		public CommunicationHandler(LogHandler logHandler, String serverAddress) {
			this.serverAddress = serverAddress;
			this.webClient = new WebClient();
			this.logHandler = logHandler;
			
			this.successCode = "#!ok";
			
			this.returnMessages = new Dictionary<String, String>();
			this.returnMessages.Add(this.successCode, "Success");			
			this.returnMessages.Add("#!db", "Database error");
			this.returnMessages.Add("#!im", "Invalid MAC address format");
			this.returnMessages.Add("#!ih", "Invalid host");		
			this.returnMessages.Add("#!it", "Invalid task");				
			this.returnMessages.Add("#!ng", "Module is disabled globablly on the FOG Server");
			this.returnMessages.Add("#!nh", "Module is diabled on the host");
			this.returnMessages.Add("#!um", "Unknown module ID");
			this.returnMessages.Add("#!ns", "No snapins");		
			this.returnMessages.Add("#!nj", "No jobs");		
			this.returnMessages.Add("#!er", "General Error");			

		}
		
		public Response getResponse(String postfix) {
			try {
				logHandler.log(System.Reflection.Assembly.GetExecutingAssembly().GetName().Name,
				               "URL: " + this.serverAddress + postfix );				
				
				String response = this.webClient.DownloadString(this.serverAddress + postfix);

				Boolean messageFound = false;
				foreach(String returnMessage in returnMessages.Keys) {
					if(response.StartsWith(returnMessage)) {
						messageFound=true;
						logHandler.log(System.Reflection.Assembly.GetExecutingAssembly().GetName().Name,
					              	"Response: " + returnMessages[returnMessage]);
					}					
				}
				
				if(!messageFound) {
						logHandler.log(System.Reflection.Assembly.GetExecutingAssembly().GetName().Name,
						               "Unknown Response: " + response.Replace("\n", ""));					
				}

				                               	               
				return parseResponse(response);
			} catch (Exception ex) {
				logHandler.log(System.Reflection.Assembly.GetExecutingAssembly().GetName().Name, 
				               "Error contacting FOG");
				logHandler.log(System.Reflection.Assembly.GetExecutingAssembly().GetName().Name,
				               "     URL: " + serverAddress + postfix);					
				logHandler.log(System.Reflection.Assembly.GetExecutingAssembly().GetName().Name,
				               "     ERROR: " + ex.Message);				
				return new Response();
			}
		}
		
		public Boolean contact(String postfix) {
			try {
				this.webClient.DownloadString(this.serverAddress + postfix);
				return true;
				
			} catch (Exception ex) {
				logHandler.log(System.Reflection.Assembly.GetExecutingAssembly().GetName().Name,
				               "Error contacting FOG");
				logHandler.log(System.Reflection.Assembly.GetExecutingAssembly().GetName().Name,
				               "     URL: " + serverAddress + postfix);				
				logHandler.log(System.Reflection.Assembly.GetExecutingAssembly().GetName().Name,
				               "     ERROR: " + ex.Message);
			}
			return false;
		}
		
		private Response parseResponse(String rawResponse) {
			
			String[] data = rawResponse.Split('\n'); //Split the response at every new line
			
			Dictionary<String, String> parsedData = new Dictionary<String, String>();
			Response response = new Response();
			
			try {
				//Get and set the error boolean
				String returnCode = data[0];
				response.setError(!returnCode.Trim().StartsWith(successCode));
				
				//Loop through each line returned and if it contains an '=' add it to the dictionary
				foreach(String element in data) {
					if(element.Contains("=")) {
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
				if(!Directory.Exists(Path.GetDirectoryName(fileName))) {
					Directory.CreateDirectory(Path.GetDirectoryName(fileName));
				}
				
				this.webClient.DownloadFile(this.serverAddress + postfix, fileName);
				
				if(File.Exists(fileName))
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
			if(address.Length > 0) //Return the first address listed
				return address[0].ToString();
			
			return "";
		}
		
		public String getMacAddresses() {
            String macs = "";
			try {
				NetworkInterface[] adapters = NetworkInterface.GetAllNetworkInterfaces();
				
				foreach (NetworkInterface adapter in adapters) {
					//Get the mac address for the adapter and add it to the String 'macs', adding ':' as needed
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
