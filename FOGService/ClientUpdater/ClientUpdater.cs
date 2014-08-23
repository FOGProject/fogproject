
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
			//Get task info
			Response updateResponse = CommunicationHandler.getResponse("/service/updates.php?action=list");	
			if(!updateResponse.wasError()) {
				List<String> updates = getUpdateFiles(updateResponse);
				
				//Loop through each update file and compare its hash to the local copy
				foreach(String updateFile in updates) {
					LogHandler.log(getName(), "Possible update for " + updateFile + " found");
					Response updateFileResponse = CommunicationHandler.getResponse("/service/updates.php?action=ask&file=" + 
					                                                         EncryptionHandler.encodeBase64(updateFile));
					
					//Check if the response is correct
					if(!updateFileResponse.wasError() && !updateFileResponse.getField("#md5").Equals("")) {
						String updateFileHash = updateFileResponse.getField("#md5");;
						
						//Check if the MD5 hashes are note equal
						if(!EncryptionHandler.generateMD5Hash(AppDomain.CurrentDomain.BaseDirectory 
						                                      + @"\" + updateFile).Equals(updateFileHash)) {
							
							LogHandler.log(getName(), "Remote file is newer, attempting to update");
							
							//Download the new file
							if(!updateFileResponse.getField("#updatefile").Equals("")) {
								
								try {
									
									if(File.Exists(AppDomain.CurrentDomain.BaseDirectory + @"tmp\" + updateFile))
										File.Delete(AppDomain.CurrentDomain.BaseDirectory + @"tmp\" + updateFile);
									
									File.WriteAllText(AppDomain.CurrentDomain.BaseDirectory + @"tmp\" + updateFile, EncryptionHandler.decodeBase64(updateFileResponse.getField("#updatefile")));
									
									
									//Try and move the file, if it fails try again for a few times
									for(int i=0; i < 5; i++) {
										try {
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
									LogHandler.log(getName(), "Unable to create update file");
									LogHandler.log(getName(), "ERROR: " + ex.Message);
								}

						   	}
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
		
		private List<String> getUpdateFiles(Response updateResponse) {
			List<String> updates = new List<String>();

			foreach(String encodedFileName in updateResponse.getData().Values) {
				updates.Add(EncryptionHandler.decodeBase64(encodedFileName));
			}
			
			return updates;
		}
	}
}