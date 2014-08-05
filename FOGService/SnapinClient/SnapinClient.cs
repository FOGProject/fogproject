
using System;
using System.Diagnostics;

namespace FOG {
	/// <summary>
	/// Installs snapins on client computers
	/// </summary>
	public class SnapinClient : AbstractModule {
		
		public SnapinClient(LogHandler logHandler, NotificationHandler notificationHandler, ShutdownHandler shutdownHandler, 
		                         CommunicationHandler communicationHandler, UserHandler userHandler):base(logHandler, 
		                                                                         notificationHandler, shutdownHandler,
		                                                                         communicationHandler, userHandler){
			
			setName("SnapinClient");
			setDescription("Installs snapins on client computers");
		}
		
		protected override void doWork() {

			if(isEnabled()) {
				//Get task info
				Response taskResponse = this.communicationHandler.getResponse("/fog/service/snapins.checkin.php?mac=" +
				                                                         communicationHandler.getMacAddresses());
				
				//Download the snapin file if there was a response and run it
				if(!taskResponse.wasError()) {
					this.logHandler.log(getName(), "Snapin Found:");
					this.logHandler.log(getName(), "    ID: " + taskResponse.getField("JOBTASKID"));
					this.logHandler.log(getName(), "    RunWith: " + taskResponse.getField("SNAPINRUNWITH"));
					this.logHandler.log(getName(), "    RunWithArgs: " + taskResponse.getField("SNAPINRUNWITHARGS"));
					this.logHandler.log(getName(), "    Name: " + taskResponse.getField("SNAPINNAME"));
					this.logHandler.log(getName(), "    File: " + taskResponse.getField("SNAPINFILENAME"));					
					this.logHandler.log(getName(), "    Created: " + taskResponse.getField("JOBCREATION"));
					this.logHandler.log(getName(), "    Args: " + taskResponse.getField("SNAPINARGS"));
					this.logHandler.log(getName(), "    Reboot: " + taskResponse.getField("SNAPINBOUNCE"));
					
					String snapinFilePath = AppDomain.CurrentDomain.BaseDirectory + @"tmp\" + taskResponse.getField("SNAPINFILENAME");
					
					Boolean downloaded = communicationHandler.downloadFile("/fog/service/snapins.file.php?mac=" +  
					                                                       communicationHandler.getMacAddresses() + 
					                                                       "&taskid=" + taskResponse.getField("JOBTASKID"),
					                                                       snapinFilePath);
					String exitCode = "-1";
					if(downloaded) {
						exitCode = startSnapin(taskResponse, snapinFilePath);
						communicationHandler.contact("/fog/service/snapins.checkin.php?mac=" +
					                             communicationHandler.getMacAddresses() +
					                             "&taskid=" + taskResponse.getField("JOBTASKID") +
					                             "&exitcode=" + exitCode);
						
						if (taskResponse.getField("SNAPINBOUNCE").Equals("1")) {
								this.shutdownHandler.restart("Snapin requested shutdown", 30);
						} else if(!shutdownHandler.isShutdownPending()) {
							doWork();
						}
					} else {
						communicationHandler.contact("/fog/service/snapins.checkin.php?mac=" +
					                             communicationHandler.getMacAddresses() +
					                             "&taskid=" + taskResponse.getField("JOBTASKID") +
					                             "&exitcode=" + exitCode);
					}
					
				}
			} else {
				this.logHandler.log(getName(), "Disabled on server");
			}
			
		}
		
		private String startSnapin(Response taskResponse, String snapinPath) {
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
				logHandler.log(getName(), "Starting snapin...");
				proccess.Start();
				proccess.WaitForExit();
				logHandler.log(getName(), "Snapin finished");
				logHandler.log(getName(), "Return Code: " + proccess.ExitCode.ToString());
			
				return proccess.ExitCode.ToString();
			} catch (Exception ex) {
				logHandler.log(getName(), "Error starting snapin");
				logHandler.log(getName(), "ERROR: " + ex.Message);
			}
			
			return "-1";
		}
	}
}