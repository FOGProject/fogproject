
using System;
using System.Collections.Generic;
using System.Management;


namespace FOG {
	/// <summary>
	/// Change the resolution of the display
	/// </summary>
	public class DisplayManager : AbstractModule {
		private Display display;
		
			
		public DisplayManager() : base() {
			setName("DisplayManager");
			setDescription("hange the resolution of the display");	
			this.display = new Display();
		}
		
		protected override void doWork() {
			display.updateSettings();
			if(display.settingsLoaded()) {
				//Get task info
				Response taskResponse = CommunicationHandler.getResponse("/service/displaymanager.php?mac=" + CommunicationHandler.getMacAddresses());
	
				if(!taskResponse.wasError()) {
	
					try {
						int x = int.Parse(taskResponse.getField("#x"));
						int y = int.Parse(taskResponse.getField("#y"));
						int r = int.Parse(taskResponse.getField("#r"));
						if(getDisplays().Count > 0)
							changeResolution(getDisplays()[0], x, y, r);
						else
							changeResolution("", x, y, r);
					} catch (Exception ex) {
						LogHandler.log(getName(), "ERROR");
						LogHandler.log(getName(), ex.Message);
					}
				}
			} else {
				LogHandler.log(getName(), "Settings are not populated; will not attempt to change resolution");
			}
		}
		
		//Change the resolution of the screen
		private void changeResolution(String device, int width, int height, int refresh) {
			if(!(width.Equals(display.getSettings().dmPelsWidth) && height.Equals(display.getSettings().dmPelsHeight) && refresh.Equals(display.getSettings().dmDisplayFrequency))) {
				LogHandler.log(getName(), "Current Resolution: " + display.getSettings().dmPelsWidth.ToString() + " x " + 
				               display.getSettings().dmPelsHeight.ToString() + " " + display.getSettings().dmDisplayFrequency + "hz");
				LogHandler.log(getName(), "Attempting to change resoltution to " + width.ToString() + " x " + height.ToString() + " " + refresh.ToString() + "hz");
				LogHandler.log(getName(), "Display name: " + device);
				
				display.changeResolution(device, width, height, refresh);
				
			} else {
				LogHandler.log(getName(), "Current resolution is already set correctly");
			}
		}
		
		private List<String> getDisplays() {
			List<String> displays = new List<String>();			
			ManagementObjectSearcher monitorSearcher = new ManagementObjectSearcher("SELECT * FROM Win32_DesktopMonitor");
			
			foreach (ManagementObject monitor in monitorSearcher.Get()) {
				displays.Add(monitor["Name"].ToString());
			}
			return displays;
		}
		
		
	}
}