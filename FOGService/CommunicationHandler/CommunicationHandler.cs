
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
	public static class CommunicationHandler
	{
		//Define variables
		private static String serverAddress = "fog-server";
		private static String successCode = "#!ok";
		private static Dictionary<String, String> returnMessages = loadReturnMessages();
			
		private static Dictionary<String, String> loadReturnMessages() {
			
			Dictionary<String, String> messages = new Dictionary<String, String>();
			
			messages.Add(successCode, "Success");
			messages.Add("#!db", "Database error");
			messages.Add("#!im", "Invalid MAC address format");
			messages.Add("#!ih", "Invalid host");		
			messages.Add("#!it", "Invalid task");				
			messages.Add("#!ng", "Module is disabled globablly on the FOG Server");
			messages.Add("#!nh", "Module is diabled on the host");
			messages.Add("#!um", "Unknown module ID");
			messages.Add("#!ns", "No snapins");		
			messages.Add("#!nj", "No jobs");		
			messages.Add("#!er", "General Error");
			
			return messages;
		}

		public static void setServerAddress(String address) { serverAddress = address; }
		public static String getServerAddress() { return serverAddress; }		
		
		
		public static Response getResponse(String postfix) {
			LogHandler.log(System.Reflection.Assembly.GetExecutingAssembly().GetName().Name,
			               "URL: " + getServerAddress() + postfix );
			
			WebClient webClient = new WebClient();
			try {				
				String response = webClient.DownloadString(getServerAddress() + postfix);

				Boolean messageFound = false;
				foreach(String returnMessage in returnMessages.Keys) {
					if(response.StartsWith(returnMessage)) {
						messageFound=true;
						LogHandler.log(System.Reflection.Assembly.GetExecutingAssembly().GetName().Name,
					              	"Response: " + returnMessages[returnMessage]);
					}					
				}
				
				if(!messageFound) {
						LogHandler.log(System.Reflection.Assembly.GetExecutingAssembly().GetName().Name,
						               "Unknown Response: " + response.Replace("\n", ""));					
				}

				                               	               
				return parseResponse(response);
			} catch (Exception ex) {
				LogHandler.log(System.Reflection.Assembly.GetExecutingAssembly().GetName().Name, 
				               "Error contacting FOG");			
				LogHandler.log(System.Reflection.Assembly.GetExecutingAssembly().GetName().Name,
				               "     ERROR: " + ex.Message);				
			}
			return new Response();
		}
		
		public static Boolean contact(String postfix) {
			LogHandler.log(System.Reflection.Assembly.GetExecutingAssembly().GetName().Name,
			               "URL: " + getServerAddress() + postfix);
			WebClient webClient = new WebClient();
			
			try {
				webClient.DownloadString(getServerAddress() + postfix);
				return true;
				
			} catch (Exception ex) {
				LogHandler.log(System.Reflection.Assembly.GetExecutingAssembly().GetName().Name,
				               "Error contacting FOG");		
				LogHandler.log(System.Reflection.Assembly.GetExecutingAssembly().GetName().Name,
				               "ERROR: " + ex.Message);
			}
			return false;
		}
		
		private static Response parseResponse(String rawResponse) {
			
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
				LogHandler.log(System.Reflection.Assembly.GetExecutingAssembly().GetName().Name,
				               "Error parsing response");
				LogHandler.log(System.Reflection.Assembly.GetExecutingAssembly().GetName().Name,
				               "ERROR: " + ex.Message);				
			}
			return response;
		}
		
		public static Boolean downloadFile(String postfix, String fileName) {
			LogHandler.log(System.Reflection.Assembly.GetExecutingAssembly().GetName().Name,
				               "URL: " + serverAddress + postfix);				
			WebClient webClient = new WebClient();
			try {
				if(!Directory.Exists(Path.GetDirectoryName(fileName))) {
					Directory.CreateDirectory(Path.GetDirectoryName(fileName));
				}
				
				webClient.DownloadFile(getServerAddress() + postfix, fileName);
				
				if(File.Exists(fileName))
					return true;
			} catch (Exception ex) {
				LogHandler.log(System.Reflection.Assembly.GetExecutingAssembly().GetName().Name, 
				               "Error downloading file");
				LogHandler.log(System.Reflection.Assembly.GetExecutingAssembly().GetName().Name, 
				               "ERROR: " + ex.Message);				
			}
			return false;
		}
		
		public static String getIPAdress() {
			String hostName = System.Net.Dns.GetHostName();
			
			IPHostEntry ipEntry = System.Net.Dns.GetHostEntry(hostName);
			
			IPAddress[] address = ipEntry.AddressList;
			if(address.Length > 0) //Return the first address listed
				return address[0].ToString();
			
			return "";
		}
		
		public static String getMacAddresses() {
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
				LogHandler.log(System.Reflection.Assembly.GetExecutingAssembly().GetName().Name, 
            	               "Error getting MAC addresses: " + ex.Message);
			}
			
			return macs;
		}
	}
}
