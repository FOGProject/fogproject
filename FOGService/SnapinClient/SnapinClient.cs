
using System;
using System.Diagnostics;

namespace FOG {
	/// <summary>
	/// Installs snapins on client computers
	/// </summary>
	public class SnapinClient : AbstractModule {
		
		public SnapinClient():base(){
			
			setName("SnapinClient");
			setDescription("Installs snapins on client computers");
		}
		
		protected override void doWork() {

			if(isEnabled()) {
				//Get task info
				Response taskResponse = CommunicationHandler.getResponse("/fog/service/snapins.checkin.php?mac=" +
				                                                         CommunicationHandler.getMacAddresses());
				
				//Download the snapin file if there was a response and run it
				if(!taskResponse.wasError()) {
					LogHandler.log(getName(), "Snapin Found:");
					LogHandler.log(getName(), "    ID: " + taskResponse.getField("JOBTASKID"));
					LogHandler.log(getName(), "    RunWith: " + taskResponse.getField("SNAPINRUNWITH"));
					LogHandler.log(getName(), "    RunWithArgs: " + taskResponse.getField("SNAPINRUNWITHARGS"));
					LogHandler.log(getName(), "    Name: " + taskResponse.getField("SNAPINNAME"));
					LogHandler.log(getName(), "    File: " + taskResponse.getField("SNAPINFILENAME"));					
					LogHandler.log(getName(), "    Created: " + taskResponse.getField("JOBCREATION"));
					LogHandler.log(getName(), "    Args: " + taskResponse.getField("SNAPINARGS"));
					LogHandler.log(getName(), "    Reboot: " + taskResponse.getField("SNAPINBOUNCE"));
					
					String snapinFilePath = AppDomain.CurrentDomain.BaseDirectory + @"tmp\" + taskResponse.getField("SNAPINFILENAME");
					
					Boolean downloaded = CommunicationHandler.downloadFile("/fog/service/snapins.file.php?mac=" +  
					                                                       CommunicationHandler.getMacAddresses() + 
					                                                       "&taskid=" + taskResponse.getField("JOBTASKID"),
					                                                       snapinFilePath);
					String exitCode = "-1";
					if(downloaded) {
						exitCode = startSnapin(taskResponse, snapinFilePath);
						CommunicationHandler.contact("/fog/service/snapins.checkin.php?mac=" +
					                             CommunicationHandler.getMacAddresses() +
					                             "&taskid=" + taskResponse.getField("JOBTASKID") +
					                             "&exitcode=" + exitCode);
						
						if (taskResponse.getField("SNAPINBOUNCE").Equals("1")) {
								ShutdownHandler.restart("Snapin requested shutdown", 30);
						} else if(!ShutdownHandler.isShutdownPending()) {
							doWork();
						}
					} else {
						CommunicationHandler.contact("/fog/service/snapins.checkin.php?mac=" +
					                             CommunicationHandler.getMacAddresses() +
					                             "&taskid=" + taskResponse.getField("JOBTASKID") +
					                             "&exitcode=" + exitCode);
					}
					
				}
			} else {
				LogHandler.log(getName(), "Disabled on server");
			}
			
		}
		
		private String startSnapin(Response taskResponse, String snapinPath) {
			NotificationHandler.createNotification(new Notification(taskResponse.getField("SNAPINNAME"), getName() +
			                                                        " is installing " + taskResponse.getField("SNAPINNAME"), 60));
			Process proccess = new Process();
			proccess.StartInfo.CreateNoWindow = true;
			proccess.StartInfo.UseShellExecute = false;
			proccess.StartInfo.WindowStyle = ProcessWindowStyle.Hidden;
			
			//Check if the snapin run with field was  specified
			if(!taskResponse.getField("SNAPINRUNWITH").Equals("")) {
				proccess.StartInfo.FileName = Environment.ExpandEnvironmentVariables(
					taskResponse.getField("SNAPINRUNWITH"));
				
				proccess.StartInfo.Arguments = Environment.ExpandEnvironmentVariables(
					taskResponse.getField("SNAPINRUNWITHARGS"));
	
				proccess.StartInfo.Arguments = Environment.ExpandEnvironmentVariables(
					taskResponse.getField("SNAPINRUNWITHARGS") + " \"" + snapinPath + " \"" + 
					Environment.ExpandEnvironmentVariables(taskResponse.getField("SNAPINARGS")));
			} else {
				proccess.StartInfo.FileName = Environment.ExpandEnvironmentVariables(snapinPath);
				
				proccess.StartInfo.Arguments = Environment.ExpandEnvironmentVariables(
					taskResponse.getField("SNAPINARGS"));
			}
			
			try {
				LogHandler.log(getName(), "Starting snapin...");
				proccess.Start();
				proccess.WaitForExit();
				LogHandler.log(getName(), "Snapin finished");
				LogHandler.log(getName(), "Return Code: " + proccess.ExitCode.ToString());
				NotificationHandler.createNotification(new Notification("Finished " + taskResponse.getField("SNAPINNAME"), 
				                                                        taskResponse.getField("SNAPINNAME") + " finished installing" , 60));
				return proccess.ExitCode.ToString();
			} catch (Exception ex) {
				LogHandler.log(getName(), "Error starting snapin");
				LogHandler.log(getName(), "ERROR: " + ex.Message);
			}
			
			return "-1";
		}
	}
}