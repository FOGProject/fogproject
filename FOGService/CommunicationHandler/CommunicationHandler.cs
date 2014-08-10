using System;
using System.Collections.Generic;
using System.Net;
using System.IO;
using System.Linq;
using System.Net.NetworkInformation;

namespace FOG {
	/// <summary>
	/// Handle all communication with the FOG Server
	/// </summary>
	public static class CommunicationHandler {
		//Define variables
		private static String serverAddress = "fog-server";
		private static Dictionary<String, String> returnMessages = loadReturnMessages();

		private const String successCode = "#!ok";
		private const String LOG_NAME = "CommunicationHandler";

		private const String PASSKEY = "7NFJUuQTYLZIoea32DsP9V6f0tbWnzMy";
		//Change this to match your passkey for all traffic
		//////////////////////////////////////////////////////////////
		//	     .           .           .           .	           	 //
		//	   .:;:.       .:;:.       .:;:.       .:;:.           	 //
		//	 .:;;;;;:.   .:;;;;;:.   .:;;;;;:.   .:;;;;;:.         	 //
		//	   ;;;;;       ;;;;;       ;;;;;       ;;;;;           	 //
		//	   ;;;;;       ;;;;;       ;;;;;       ;;;;;           	 //
		//	   ;;;;;       ;;;;;       ;;;;;       ;;;;;           	 //
		//	   ;;;;;       ;;;;;       ;;;;;       ;;;;;           	 //
		//	   ;:;;;       ;:;;;       ;:;;;       ;:;;;           	 //
		//	   : ;;;       : ;;;       : ;;;       : ;;;           	 //
		//	     ;:;         ;:;         ;:;         ;:;           	 //
		//	   . :.;       . :.;       . :.;       . :.;           	 //
		//	     . :         . :         . :         . :           	 //
		//	   .   .       .   .       .   .       .   .           	 //
		//////////////////////////////////////////////////////////////


		//Define all return codes
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
			messages.Add("#!time", "Invalid time");	
			messages.Add("#!er", "General Error");

			return messages;
		}

		//Getters and setters
		public static void setServerAddress(String address) { serverAddress = address; }
		public static String getServerAddress() { return serverAddress; }		


		//Return the response form an address
		public static Response getResponse(String postfix) {
			//ID the service as the new one
			if(postfix.Contains(".php?")) {
				postfix = postfix + "&newService=1";
			} else {
				postfix = postfix + "?newService=1";
			}

			LogHandler.log(LOG_NAME, "URL: " + getServerAddress() + postfix );

			WebClient webClient = new WebClient();
			try {
				String response = webClient.DownloadString(getServerAddress() + postfix);
				response = decrypt(response);
				//See if the return code is known
				Boolean messageFound = false;
				foreach(String returnMessage in returnMessages.Keys) {
					if(response.StartsWith(returnMessage)) {
						messageFound=true;
						LogHandler.log(LOG_NAME, "Response: " + returnMessages[returnMessage]);
					}					
				}

				if(!messageFound) {
						LogHandler.log(LOG_NAME, "Unknown Response: " + response.Replace("\n", ""));					
				}
           
				return parseResponse(response);
			} catch (Exception ex) {
				LogHandler.log(LOG_NAME, "Error contacting FOG");			
				LogHandler.log(LOG_NAME, "ERROR: " + ex.Message);				
			}
			return new Response();
		}
		
		private static String decrypt(String response) {
			String encryptedFlag = "#!en=";
			
			LogHandler.log(LOG_NAME, "Attempting to decrypt response");
			
			if(response.StartsWith(encryptedFlag)) {
				String decryptedResponse = response.Substring(encryptedFlag.Length);
				LogHandler.log(LOG_NAME, "Encrypted data: " + decryptedResponse);
				return EncryptionHandler.decodeAESResponse(decryptedResponse, PASSKEY);
				
			} else {
				LogHandler.log(LOG_NAME, "Data is not encrypted");
			}
			return response;
		}

		//Contact FOG at a url, used for submitting data
		public static Boolean contact(String postfix) {
			LogHandler.log(LOG_NAME,
			               "URL: " + getServerAddress() + postfix);
			WebClient webClient = new WebClient();

			try {
				webClient.DownloadString(getServerAddress() + postfix);
				return true;
				
			} catch (Exception ex) {
				LogHandler.log(LOG_NAME, "Error contacting FOG");		
				LogHandler.log(LOG_NAME, "ERROR: " + ex.Message);
			}
			return false;
		}

		//Parse the recieved data
		private static Response parseResponse(String rawResponse) {
			String[] data = rawResponse.Split('\n'); //Split the response at every new line
			Dictionary<String, String> parsedData = new Dictionary<String, String>();
			Response response = new Response();

			try {
				//Get and set the error boolean
				String returnCode = data[0];
				response.setError(!returnCode.ToLower().Trim().StartsWith(successCode));

				//Loop through each line returned and if it contains an '=' add it to the dictionary
				foreach(String element in data) {
					if(element.Contains("=")) {
						parsedData.Add(element.Substring(0, element.IndexOf("=")).Trim(),
						               element.Substring(element.IndexOf("=")+1).Trim());
					}
				}

				response.setData(parsedData);
			} catch (Exception ex) {
				LogHandler.log(LOG_NAME, "Error parsing response");
				LogHandler.log(LOG_NAME, "ERROR: " + ex.Message);				
			}
			return response;
		}

		//Download a file
		public static Boolean downloadFile(String postfix, String fileName) {
			LogHandler.log(LOG_NAME,
				               "URL: " + serverAddress + postfix);				
			WebClient webClient = new WebClient();
			try {
				//Create the directory that the file will go in if it doesn't already exist
				if(!Directory.Exists(Path.GetDirectoryName(fileName))) {
					Directory.CreateDirectory(Path.GetDirectoryName(fileName));
				}

				webClient.DownloadFile(getServerAddress() + postfix, fileName);

				if(File.Exists(fileName))
					return true;
			} catch (Exception ex) {
				LogHandler.log(LOG_NAME, "Error downloading file");
				LogHandler.log(LOG_NAME, "ERROR: " + ex.Message);				
			}
			return false;
		}


		//Get the IP address of the host
		public static String getIPAdress() {
			String hostName = System.Net.Dns.GetHostName();
			
			IPHostEntry ipEntry = System.Net.Dns.GetHostEntry(hostName);
			
			IPAddress[] address = ipEntry.AddressList;
			if(address.Length > 0) //Return the first address listed
				return address[0].ToString();

			return "";
		}

		//Get a string of all mac addresses
		public static String getMacAddresses() {
            String macs = "";
			try {
				NetworkInterface[] adapters = NetworkInterface.GetAllNetworkInterfaces();

				foreach (NetworkInterface adapter in adapters) {
					//Get the mac address for the adapter and add it to the String 'macs', adding ':' as needed
					IPInterfaceProperties properties = adapter.GetIPProperties();
					macs = macs + "|" + string.Join (":", (from z in adapter.GetPhysicalAddress().GetAddressBytes() select z.ToString ("X2")).ToArray());
				}
				
				// Remove the first |
				if(macs.Length > 0)
					macs = macs.Substring(1);
				
			} catch (Exception ex) {
            	LogHandler.log(LOG_NAME, "Error getting MAC addresses");
            	LogHandler.log(LOG_NAME, "ERROR: " + ex.Message);
			}

			return macs;
		}
	}
}