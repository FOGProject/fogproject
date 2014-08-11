
using System;
using Microsoft.Win32;
using System.Runtime.InteropServices;
using System.Diagnostics;
using System.Collections.Generic;

namespace FOG {
	/// <summary>
	/// Rename a host, register with AD, and activate the windows key
	/// </summary>
	public class HostnameChanger:AbstractModule {
		
		//Import dll methods
		[DllImport("netapi32.dll", CharSet=CharSet.Unicode)] 
		private static extern int NetJoinDomain( string lpServer, string lpDomain, string lpAccountOU, 
		                                        string lpAccount, string lpPassword, JoinOptions NameType);
		
		[DllImport("netapi32.dll", CharSet=CharSet.Unicode)]
		private static extern int NetUnjoinDomain(string lpServer, string lpAccount, string lpPassword, UnJoinOptions fUnjoinOptions);

		[Flags]
		private enum UnJoinOptions {
			NONE = 0x00000000,
			NETSETUP_ACCOUNT_DELETE = 0x00000004
		}
		
		[Flags]
		private enum JoinOptions {
			NETSETUP_JOIN_DOMAIN = 0x00000001,
			NETSETUP_ACCT_CREATE = 0x00000002,
			NETSETUP_ACCT_DELETE = 0x00000004,
			NETSETUP_WIN9X_UPGRADE = 0x00000010,
			NETSETUP_DOMAIN_JOIN_IF_JOINED = 0x00000020,
			NETSETUP_JOIN_UNSECURE = 0x00000040,
			NETSETUP_MACHINE_PWD_PASSED = 0x00000080,
			NETSETUP_DEFER_SPN_SET = 0x10000000
		}
		
		private Dictionary<int, String> adErrors;
		private int successIndex;
		private Boolean notifiedUser; //This variable is used to detect if the user has been told their is a pending shutdown
		
		private const String PASSKEY = "jPlUQRw5vLsrz8I1TuZdWDSiMFqXHtcm";
		 //Change this to match your passkey for the active directory password
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
    
		public HostnameChanger():base() {
			setName("HostnameChanger");
			setDescription("Rename a host, register with AD, and activate the windows key");		
			
			setADErrors();
			this.notifiedUser = false;
		}
	 
	    
		private void setADErrors() {
	      	this.adErrors = new Dictionary<int, String>();
	      	this.successIndex = 0;
	      	
	      	this.adErrors.Add(this.successIndex,"Success");
	      	this.adErrors.Add(5, "Access Denied");
	      	
		}
		
		protected override void doWork() {
			//Get task info
			Response taskResponse = CommunicationHandler.getResponse("/service/hostname.php?mac=" + CommunicationHandler.getMacAddresses() + 
			                                                         "&moduleid=" + getName().ToLower());
			
			if(!taskResponse.wasError()) {
				renameComputer(taskResponse);
				if(!ShutdownHandler.isShutdownPending())
					registerComputer(taskResponse);
				if(!ShutdownHandler.isShutdownPending())
					activateComputer(taskResponse);
			}
		}
		
		//Rename the computer and remove it from active directory
		private void renameComputer(Response taskResponse) {
			if(!taskResponse.getField("#hostname").Equals("")) {
				if(!System.Environment.MachineName.ToLower().Equals(taskResponse.getField("#hostname").ToLower())) {
				
					LogHandler.log(getName(), "Attempting to rename host to " + taskResponse.getField("#hostname"));
					if(!UserHandler.isUserLoggedIn() || taskResponse.getField("#force").Equals("1")) {
					
						//First unjoin it from active directory
			      		unRegisterComputer(taskResponse);		
		
			      		LogHandler.log(getName(), "Updating registry");
						RegistryKey regKey;
			
						regKey = Registry.LocalMachine.OpenSubKey(@"SYSTEM\CurrentControlSet\Services\Tcpip\Parameters", true);
						regKey.SetValue("NV Hostname", taskResponse.getField("#hostname"));
						regKey = Registry.LocalMachine.OpenSubKey(@"SYSTEM\CurrentControlSet\Control\ComputerName\ActiveComputerName", true);
						regKey.SetValue("ComputerName", taskResponse.getField("#hostname"));
						regKey = Registry.LocalMachine.OpenSubKey(@"SYSTEM\CurrentControlSet\Control\ComputerName\ComputerName", true);
						regKey.SetValue("ComputerName", taskResponse.getField("#hostname"));	
						
						ShutdownHandler.restart(NotificationHandler.getCompanyName() + " needs to rename your computer", 10);
					} else if(!this.notifiedUser) {
						LogHandler.log(getName(), "User is currently logged in, will try again later");
						//Notify the user they should log off if it is not forced
						NotificationHandler.createNotification(new Notification("Please log off", NotificationHandler.getCompanyName() +
					                                                        " is attemping to service your computer, please log off at the soonest available time",
					                                                        120));
						
						this.notifiedUser = true;
					}
				} else {
					LogHandler.log(getName(), "Hostname is correct");
				}
			} 
	      	
		}
		
		//Add a host to active directory
		private void registerComputer(Response taskResponse) {
			if(taskResponse.getField("#AD").Equals("1")) { 
				LogHandler.log(getName(), "Attempting to add host to active directory");
				if(!taskResponse.getField("#ADDom").Equals("") && !taskResponse.getField("#ADUser").Equals("") && 
				   !taskResponse.getField("#ADPass").Equals("")) {
				
					String userPassword = EncryptionHandler.decodeAESResponse(taskResponse.getField("#ADPass"), PASSKEY);
					LogHandler.log(getName(), "Decrypted AD Pass: " + userPassword);
					int returnCode = NetJoinDomain(null, taskResponse.getField("#ADDom"), taskResponse.getField("#ADOU"), 
					                               taskResponse.getField("#ADUser"), userPassword, 
					                               (JoinOptions.NETSETUP_JOIN_DOMAIN | JoinOptions.NETSETUP_ACCT_CREATE));
					if(returnCode == 2224) {
						returnCode = NetJoinDomain(null, taskResponse.getField("#ADDom"), taskResponse.getField("#ADOU"), 
						                           taskResponse.getField("#ADUser"), userPassword, JoinOptions.NETSETUP_JOIN_DOMAIN);				
					}
					
					//Log the response
					if(this.adErrors.ContainsKey(returnCode)) {
						LogHandler.log(getName(), this.adErrors[returnCode] + " Return code: " + returnCode.ToString());
					} else {
						LogHandler.log(getName(), "Unknown return code: " + returnCode.ToString());
					}	
					
					if(returnCode.Equals(this.successIndex))
						ShutdownHandler.restart("Host joined to active directory, restart needed", 20);
					
				} else {
					LogHandler.log(getName(), "Unable to remove host from active directory");
					LogHandler.log(getName(), "ERROR: Not all active directory fields are set");
				}
			} else {
				LogHandler.log(getName(), "Active directory is disabled");
			}
		}
		
		//Remove the host from active directory
		private void unRegisterComputer(Response taskResponse) {
			LogHandler.log(getName(), "Attempting to remove host from active directory");
			if(!taskResponse.getField("#ADUser").Equals("") && !taskResponse.getField("#ADPass").Equals("")) {
				
				String userPassword = EncryptionHandler.decodeAESResponse(taskResponse.getField("#ADPass"), PASSKEY);
				int returnCode = NetUnjoinDomain(null, taskResponse.getField("#ADUser"), userPassword, UnJoinOptions.NETSETUP_ACCOUNT_DELETE);
				
				//Log the response
				if(this.adErrors.ContainsKey(returnCode)) {
					LogHandler.log(getName(), this.adErrors[returnCode] + " Return code: " + returnCode.ToString());
				} else {
					LogHandler.log(getName(), "Unknown return code: " + returnCode.ToString());
				}
				
				if(returnCode.Equals(this.successIndex))
					ShutdownHandler.restart("Host joined to active directory, restart needed", 20);
			} else {
				LogHandler.log(getName(), "Unable to remove host from active directory, some settings are empty");
			}
		}
		
		//Active a computer with a product key
		private void activateComputer(Response taskResponse) {
			if(taskResponse.getData().ContainsKey("#Key")) {
				LogHandler.log(getName(), "Attempting to active host");
				
				//The standard windows key is 29 characters long -- 5 sections of 5 characters with 4 dashes (5*5+4)
				if(taskResponse.getField("#Key").Length == 29) {
					Process process = new Process();
					
					//Give windows the new key
					process.StartInfo.FileName = @"cscript";
					process.StartInfo.Arguments ="//B //Nologo "  + Environment.SystemDirectory + @"\slmgr.vbs /ipk " + taskResponse.getField("#Key");
					process.StartInfo.WindowStyle = ProcessWindowStyle.Hidden;
					process.Start();
					process.WaitForExit();
					process.Close();
					
					//Try and activate the new key
					process.StartInfo.Arguments ="//B //Nologo " + Environment.SystemDirectory + @"\slmgr.vbs /ato";
					process.Start();
					process.WaitForExit();
					process.Close();
				} else {
					LogHandler.log(getName(), "Unable to activate windows");
					LogHandler.log(getName(), "ERROR: Invalid product key");
				}
			} else {
				LogHandler.log(getName(), "Windows activation disabled");				
			}
		}
		
	}
}