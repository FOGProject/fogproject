
using System;
using System.Collections.Generic;
using System.Diagnostics;
using System.Drawing;
using System.Threading;
using System.Windows.Forms;

namespace FOG
{
	public sealed class NotificationIcon
	{
		private NotifyIcon notifyIcon;
		private ContextMenu notificationMenu;
		private PipeClient pipeClient;
		private List<Notification> notifications;
		private String splitter;
		
		#region Initialize icon and menu
		public NotificationIcon()
		{
			notifyIcon = new NotifyIcon();
			notificationMenu = new ContextMenu(InitializeMenu());
			
			notifyIcon.DoubleClick += IconDoubleClick;
			System.ComponentModel.ComponentResourceManager resources = new System.ComponentModel.ComponentResourceManager(typeof(NotificationIcon));
			notifyIcon.Icon = (Icon)resources.GetObject("icon");
			notifyIcon.ContextMenu = notificationMenu;
			
			this.notifications = new List<Notification>();
			this.splitter = "---||---";
			
			this.pipeClient = new PipeClient("fog_pipe");
			this.pipeClient.MessageReceived += new PipeClient.MessageReceivedHandler(pipeClient_MessageReceived);
			this.pipeClient.Connect();
		}
		
		private void pipeClient_MessageReceived(String message) {
			
			String title = "";
			String msg = "";
			int duration = 10;
			
			if(message.Contains(this.splitter)) {
				int titleSplitIndex = message.IndexOf(this.splitter);
				title = message.Substring(0,titleSplitIndex);
				
				if(message.Length > titleSplitIndex+this.splitter.Length && 
				   message.Substring(titleSplitIndex+this.splitter.Length).Contains(this.splitter)) {
						
						int messageSplitIndex = message.LastIndexOf(this.splitter);
						msg = message.Substring(titleSplitIndex+this.splitter.Length, messageSplitIndex);
						
						if(message.Length > messageSplitIndex+this.splitter.Length) {
							String strDuration = message.Substring(messageSplitIndex+this.splitter.Length);
							duration = int.Parse(strDuration);
						}
				}

			}
			
			Notification notification = new Notification(title, msg, duration);
			
			
			this.notifyIcon.BalloonTipTitle = notification.getTitle();
			this.notifyIcon.BalloonTipText = notification.getMessage();
			this.notifyIcon.ShowBalloonTip(notification.getDuration());
		}
		
		private MenuItem[] InitializeMenu()
		{
			MenuItem[] menu = new MenuItem[] {
				new MenuItem("Restart Module Cycle", menuRestartModuleCycleClick),
				new MenuItem("About", menuAboutClick),
				new MenuItem("Exit", menuExitClick)
			};
			return menu;
		}
		#endregion
		
		#region Main - Program entry point
		/// <summary>Program entry point.</summary>
		/// <param name="args">Command Line Arguments</param>
		[STAThread]
		public static void Main(string[] args)
		{
			Application.EnableVisualStyles();
			Application.SetCompatibleTextRenderingDefault(false);
			
			bool isFirstInstance;
			// Please use a unique name for the mutex to prevent conflicts with other programs
			using (Mutex mtx = new Mutex(true, "Tray", out isFirstInstance)) {
				if (isFirstInstance) {
					NotificationIcon notificationIcon = new NotificationIcon();
					notificationIcon.notifyIcon.Visible = true;
					Application.Run();
					notificationIcon.notifyIcon.Dispose();
				} else {
					// The application is already running
					// TODO: Display message box or change focus to existing application instance
				}
			} // releases the Mutex
		}
		#endregion
		
		#region Event Handlers
		
		private void menuRestartModuleCycleClick(object sender, EventArgs e) {
			if(pipeClient.isConnected())
				pipeClient.sendMessage("Rebooting cycle...");
		}
			
		private void menuAboutClick(object sender, EventArgs e)
		{
			MessageBox.Show("About This Application");
		}
		
		private void menuExitClick(object sender, EventArgs e)
		{
			Application.Exit();
		}
		
		private void IconDoubleClick(object sender, EventArgs e)
		{
		}
		#endregion
	}
}
