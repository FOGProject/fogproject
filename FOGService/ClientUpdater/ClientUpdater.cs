
using System;
using System.IO;
using System.Threading;
using System.Collections.Generic;


namespace FOG
{
	/// <summary>
	/// Update the FOG Service
	/// </summary>
	public class ClientUpdater : AbstractModule {
		
		private Boolean updatePending;
		
		public ClientUpdater() : base() {
			setName("ClientUpdater");
			setDescription("Update the FOG Service");
			this.updatePending = false;
		}
		
		protected override void doWork() {
			this.updatePending = false;
			//Get task info
			Response updateResponse = CommunicationHandler.getResponse("/service/updates.php?action=list");	
			if(!updateResponse.wasError()) {
				List<String> updates = getUpdateFiles(updateResponse);
				
				//Loop through each update file and compare its hash to the local copy
				foreach(String updateFile in updates) {
					LogHandler.log(getName(), "Possible update for " + updateFile + " found");
					Response askResponse = CommunicationHandler.getResponse("/service/updates.php?action=ask&file=" + 
					                                                         EncryptionHandler.encodeBase64(updateFile));
					
					//Check if the response is correct
					if(!askResponse.wasError() && !askResponse.getField("#md5").Equals("")) {
						String updateFileHash = askResponse.getField("#md5");;
						
						//Check if the MD5 hashes are note equal
						if(!EncryptionHandler.generateMD5Hash(AppDomain.CurrentDomain.BaseDirectory 
						                                      + @"\" + updateFile).Equals(updateFileHash)) {
							
							LogHandler.log(getName(), "Remote file is newer, attempting to update");
							
							if(generateUpdateFile(askResponse.getField("#md5"), updateFile))
								applyUpdateFile(updateFile);
						} else {
							LogHandler.log(getName(), "Remote file is the same as this local copy");
						}
					}
				}
				if(updatePending) {
					ShutdownHandler.restartService();
				}
			}
		}
		
		//Generate the update file from the parsed response
		private Boolean generateUpdateFile(String md5, String updateFile) {
			LogHandler.log(getName(), "Downloading update file");
			//Download the new file
			Response updateFileResponse = CommunicationHandler.getResponse("/service/updates.php?action=get&file=" + 
			                                                               EncryptionHandler.encodeBase64(updateFile));
					                                                         
			if(!updateFileResponse.getField("#updatefile").Equals("")) {
				
				try {
					
					//Create the directory that the file will go in if it doesn't already exist
					if(!Directory.Exists(AppDomain.CurrentDomain.BaseDirectory + @"tmp\")) {
						Directory.CreateDirectory(AppDomain.CurrentDomain.BaseDirectory + @"tmp\" MulticastNotSupportedException );
					}
					
					if(File.Exists(AppDomain.CurrentDomain.BaseDirectory + @"tmp\" + updateFile))
						File.Delete(AppDomain.CurrentDomain.BaseDirectory + @"tmp\" + updateFile);
					
					File.WriteAllBytes(AppDomain.CurrentDomain.BaseDirectory + @"tmp\" + updateFile, EncryptionHandler.StringToByteArray(updateFileResponse.getField("#updatefile")));
					LogHandler.log(getName(), "Success");
					LogHandler.log(getName(), "Verifying MD5 hash");
					
					if(EncryptionHandler.generateMD5Hash(AppDomain.CurrentDomain.BaseDirectory + @"tmp\" + updateFile).Equals(md5)) {
						LogHandler.log(getName(), "Success");
						return true;
					} else {
						LogHandler.log(getName(), "Failure");
						LogHandler.log(getName(), "SVR: " + md5);
						LogHandler.log(getName(), "DWD: " + EncryptionHandler.generateMD5Hash(AppDomain.CurrentDomain.BaseDirectory + @"tmp\" + updateFile));
						//File.Delete(AppDomain.CurrentDomain.BaseDirectory + @"tmp\" + updateFile);
					}
				} catch (Exception ex) {
					LogHandler.log(getName(), "Unable to generate update file");
					LogHandler.log(getName(), "ERROR: " + ex.Message);
				}

			}
			return false;
		}
		
		//Apply the downloaded update
		private void applyUpdateFile(String updateFile) {
			if(File.Exists(AppDomain.CurrentDomain.BaseDirectory + @"tmp\" + updateFile)) {
				try { 
					//Try and move the file, if it fails try again for a few times
					for(int i=0; i < 5; i++) {
						try {
							//Delete old version
							if(File.Exists(AppDomain.CurrentDomain.BaseDirectory + @"\" + updateFile))
								File.Delete(AppDomain.CurrentDomain.BaseDirectory + @"\" + updateFile);
							
							File.Move(AppDomain.CurrentDomain.BaseDirectory + @"tmp\" + updateFile,
							          AppDomain.CurrentDomain.BaseDirectory + @"\" + updateFile);
							this.updatePending = true;		
							LogHandler.log(getName(), "Successfully updated " + updateFile);
							break;
						} catch (Exception ex) {
							LogHandler.log(getName(), "Unable to replace " + updateFile);
							LogHandler.log(getName(), "ERROR: " + ex.Message);
						}
						if(i < 4) {
							LogHandler.log(getName(), "Will attempt to update again in 2 seconds");
							Thread.Sleep(2000);
						}
					}
				} catch (Exception ex) {
					LogHandler.log(getName(), "Unable to apply update file");
					LogHandler.log(getName(), "ERROR: " + ex.Message);
				}
			} else {
				LogHandler.log(getName(), "Unable to locate downloaded update file");
			}
		}
		
		
		//Get a list of update file's names
		private List<String> getUpdateFiles(Response updateResponse) {
			List<String> updates = new List<String>();

			foreach(String encodedFileName in updateResponse.getData().Values) {
				updates.Add(EncryptionHandler.decodeBase64(encodedFileName));
			}
			
			return updates;
		}
	}
}