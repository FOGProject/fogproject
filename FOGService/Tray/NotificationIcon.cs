
using System;
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
		
		#region Initialize icon and menu
		public NotificationIcon()
		{
			notifyIcon = new NotifyIcon();
			notificationMenu = new ContextMenu(InitializeMenu());
			
			notifyIcon.DoubleClick += IconDoubleClick;
			System.ComponentModel.ComponentResourceManager resources = new System.ComponentModel.ComponentResourceManager(typeof(NotificationIcon));
			notifyIcon.Icon = (Icon)resources.GetObject("icon");
			notifyIcon.ContextMenu = notificationMenu;
			this.pipeClient = new PipeClient(@"\\.\pipe\FOG_PIPE5");
			this.pipeClient.messageReceived += new PipeClient.messageReceivedHandler(pipeClient_MessageReceived);
			this.pipeClient.start();
		}
		
		private void pipeClient_MessageReceived(String message) {
			this.notifyIcon.BalloonTipTitle = "Pipe Message";
			this.notifyIcon.BalloonTipText = message;
			this.notifyIcon.ShowBalloonTip(10);
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
