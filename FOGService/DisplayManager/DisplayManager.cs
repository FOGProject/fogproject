
using System;
using System.Collections.Generic;
using System.Windows.Forms;


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
						changeResolution(x, y, r);
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
		private void changeResolution(int width, int height, int refresh) {
			if(!(width.Equals(display.getSettings().dmPelsWidth) && height.Equals(display.getSettings().dmPelsHeight) && refresh.Equals(display.getSettings().dmDisplayFrequency))) {
				LogHandler.log(getName(), "Current Resolution: " + display.getSettings().dmPelsWidth.ToString() + " x " + 
				               display.getSettings().dmPelsHeight.ToString() + " " + display.getSettings().dmDisplayFrequency + "hz");
				LogHandler.log(getName(), "Attempting to change resoltution to " + width.ToString() + " x " + height.ToString() + " " + refresh.ToString() + "hz");
				
				display.changeResolution(width, height, refresh);
				
			} else {
				LogHandler.log(getName(), "Current resolution is already set correctly");
			}
		}
		
	}
}