
using System;
using System.Collections.Generic;
using System.Text.RegularExpressions;
using Microsoft.Win32.TaskScheduler;

namespace FOG {
	/// <summary>
	/// Record user login-logout events
	/// </summary>
	public class UserTracker : AbstractModule {
		
		private const String LOGIN_TASK_NAME = "FOGUserLogin";
		private const String LOGOUT_TASK_NAME = "FOGUserLogout";
		
		
		public UserTracker():base(){
			setName("UserTracker");
			setDescription("Record user login-logout events");
		}
		
		protected override void doWork() {
			Response taskResponse = CommunicationHandler.getResponse("/service/jobs.php?mac=" + CommunicationHandler.getMacAddresses());

			if(!taskResponse.wasError()) {
				
			}
			
		}
		
		private void enable() {
			TaskService taskService = new TaskService();
			if(taskService.RootFolder.GetTasks(new Regex(LOGIN_TASK_NAME)).Count < 1) {
				TaskDefinition userLoginDefinition = taskService.NewTask();
				
				userLoginDefinition.RegistrationInfo.Description = "FOG Login Recorder";
				userLoginDefinition.Triggers.Add(new LogonTrigger());
				
				userLoginDefinition.Actions.Add(new ExecAction("FILE.exe", "login", null));
				
				taskService.RootFolder.RegisterTaskDefinition(LOGIN_TASK_NAME, userLoginDefinition);
			}
			
			if(taskService.RootFolder.GetTasks(new Regex(LOGOUT_TASK_NAME)).Count < 1) {
				TaskDefinition userLogoutDefinition = taskService.NewTask();
				
				userLogoutDefinition.RegistrationInfo.Description = "FOG Logout Recorder";
				userLogoutDefinition.Triggers.Add(new LogonTrigger());
				
				userLogoutDefinition.Actions.Add(new ExecAction("FILE.exe", "logout", null));
				
				taskService.RootFolder.RegisterTaskDefinition(LOGOUT_TASK_NAME, userLogoutDefinition);
			}			
			
			
		}
		
		private Boolean isConfigured() {
			TaskService taskService = new TaskService();
			return taskService.RootFolder.GetTasks(new Regex(LOGIN_TASK_NAME)).Count > 0;
		}
		
		private void disable() {
			TaskService taskService = new TaskService();
			taskService.RootFolder.DeleteTask(LOGIN_TASK_NAME, false);						
			taskService.RootFolder.DeleteTask(LOGOUT_TASK_NAME, false);
		}
		
	}
}