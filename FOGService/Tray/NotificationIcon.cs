
using System;
using System.Collections.Generic;
using System.Diagnostics;
using System.Drawing;
using System.Threading;
using System.Windows.Forms;

namespace FOG {
	public sealed class NotificationIcon {
		
		//Define variables
		private NotifyIcon notifyIcon;
		private ContextMenu notificationMenu;
		private PipeClient pipeClient;
		private Notification notification;
		private Boolean isNotificationReady;
		
		#region Initialize icon and menu
		public NotificationIcon() {
			notifyIcon = new NotifyIcon();
			notificationMenu = new ContextMenu(InitializeMenu());
			
			notifyIcon.DoubleClick += IconDoubleClick;
			System.ComponentModel.ComponentResourceManager resources = new System.ComponentModel.ComponentResourceManager(typeof(NotificationIcon));
			notifyIcon.Icon = (Icon)resources.GetObject("icon");
			notifyIcon.ContextMenu = notificationMenu;
			
			this.notification = new Notification();
			this.isNotificationReady = false;
			
			// Setup the pipe client
			this.pipeClient = new PipeClient("fog_pipe");
			this.pipeClient.MessageReceived += new PipeClient.MessageReceivedHandler(pipeClient_MessageReceived);
			this.pipeClient.connect();
		}
		
		//Called when a message is recieved from the pipe server
		private void pipeClient_MessageReceived(String message) {
			
			if(message.Contains("TLE:")) {
				message = message.Substring(4);
				this.notification.setTitle(message);
			} else if(message.Contains("MSG:")) {
				message = message.Substring(4);
				this.notification.setMessage(message);
			} else if(message.Contains("DUR:")) {
				message = message.Substring(4);
				try {
					this.notification.setDuration(int.Parse(message));
				} catch {}
				this.isNotificationReady = true;
			}
			
			if(this.isNotificationReady) {
				this.notifyIcon.BalloonTipTitle = this.notification.getTitle();
				this.notifyIcon.BalloonTipText = this.notification.getMessage();
				this.notifyIcon.ShowBalloonTip(this.notification.getDuration());
				this.isNotificationReady = false;
				this.notification = new Notification();
			}
		}
		
		private MenuItem[] InitializeMenu() {
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
		public static void Main(string[] args) {
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
			
		private void menuAboutClick(object sender, EventArgs e) {
			Process.Start("http://fogproject.org/?q=node/1");
		}
		
		private void menuExitClick(object sender, EventArgs e) {
			if(pipeClient.isConnected())
				pipeClient.kill();
			Application.Exit();
		}
		
		private void IconDoubleClick(object sender, EventArgs e) {
		}
		#endregion
	}
}
