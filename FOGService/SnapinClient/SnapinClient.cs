
using System;
using System.IO;
using System.Diagnostics;

namespace FOG {
	/// <summary>
	/// Installs snapins on client computers
	/// </summary>
	public class SnapinClient : AbstractModule {
		
		public SnapinClient():base(){
			setName("SnapinClient ----new----");
			setDescription("Installs snapins on client computers");
		}
		
		protected override void doWork() {
			//Get task info
			Response taskResponse = CommunicationHandler.getResponse("/service/snapins.checkin.php?mac=" +
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
				
				Boolean downloaded = CommunicationHandler.downloadFile("/service/snapins.file.php?mac=" +  
				                                                       CommunicationHandler.getMacAddresses() + 
				                                                       "&taskid=" + taskResponse.getField("JOBTASKID"),
				                                                       snapinFilePath);
				String exitCode = "-1";
				
				//If the file downloaded successfully then run the snapin and report to FOG what the exit code was
				if(downloaded) {
					exitCode = startSnapin(taskResponse, snapinFilePath);
					if(File.Exists(snapinFilePath))
						File.Delete(snapinFilePath);
					
					CommunicationHandler.contact("/service/snapins.checkin.php?mac=" +
				                             CommunicationHandler.getMacAddresses() +
				                             "&taskid=" + taskResponse.getField("JOBTASKID") +
				                             "&exitcode=" + exitCode);
					
					if (taskResponse.getField("SNAPINBOUNCE").Equals("1")) {
							ShutdownHandler.restart("Snapin requested shutdown", 30);
					} else if(!ShutdownHandler.isShutdownPending()) {
						//Rerun this method to check for the next snapin
						doWork();
					}
				} else {
					
					CommunicationHandler.contact("/service/snapins.checkin.php?mac=" +
				                             CommunicationHandler.getMacAddresses() +
				                             "&taskid=" + taskResponse.getField("JOBTASKID") +
				                             "&exitcode=" + exitCode);
				}
				
			}
		}
		
		
		//Execute the snapin once it has been downloaded
		private String startSnapin(Response taskResponse, String snapinPath) {
			NotificationHandler.createNotification(new Notification(taskResponse.getField("SNAPINNAME"), "FOG is installing " + 
			                                                        taskResponse.getField("SNAPINNAME"), 10));
			
			Process process = generateProcess(taskResponse, snapinPath);
			
			try {
				LogHandler.log(getName(), "Starting snapin...");
				process.Start();
				process.WaitForExit();
				LogHandler.log(getName(), "Snapin finished");
				LogHandler.log(getName(), "Return Code: " + process.ExitCode.ToString());
				NotificationHandler.createNotification(new Notification("Finished " + taskResponse.getField("SNAPINNAME"), 
				                                                        taskResponse.getField("SNAPINNAME") + " finished installing" , 10));
				return process.ExitCode.ToString();
			} catch (Exception ex) {
				LogHandler.log(getName(), "Error starting snapin");
				LogHandler.log(getName(), "ERROR: " + ex.Message);
			}
			
			return "-1";
		}
		
		//Create a proccess to run the snapin with
		private Process generateProcess(Response taskResponse, String snapinPath) {
			Process process = new Process();
			process.StartInfo.CreateNoWindow = true;
			process.StartInfo.UseShellExecute = false;
			process.StartInfo.WindowStyle = ProcessWindowStyle.Hidden;
			
			//Check if the snapin run with field was specified
			if(!taskResponse.getField("SNAPINRUNWITH").Equals("")) {
				process.StartInfo.FileName = Environment.ExpandEnvironmentVariables(
					taskResponse.getField("SNAPINRUNWITH"));
				
				process.StartInfo.Arguments = Environment.ExpandEnvironmentVariables(
					taskResponse.getField("SNAPINRUNWITHARGS"));
	
				process.StartInfo.Arguments = Environment.ExpandEnvironmentVariables(
					taskResponse.getField("SNAPINRUNWITHARGS") + " \"" + snapinPath + " \"" + 
					Environment.ExpandEnvironmentVariables(taskResponse.getField("SNAPINARGS")));
			} else {
				process.StartInfo.FileName = Environment.ExpandEnvironmentVariables(snapinPath);
				
				process.StartInfo.Arguments = Environment.ExpandEnvironmentVariables(
					taskResponse.getField("SNAPINARGS"));
			}
			
			return process;
		}
	}
}