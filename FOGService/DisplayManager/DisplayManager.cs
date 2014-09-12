
using System;
using System.Collections.Generic;
using System.Windows.Forms;


namespace FOG {
	/// <summary>
	/// Change the resolution of the display
	/// </summary>
	public class DisplayManager : AbstractModule {
		private DisplayChanger display;
		
			
		public DisplayManager() : base() {
			setName("DisplayManager");
			setDescription("hange the resolution of the display");	
			this.display = new DisplayChanger();
		}
		
		protected override void doWork() {
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
		}
		
		//Change the resolution of the screen
		private void changeResolution(int width, int height, int refresh) {
			if(!(width.Equals(Screen.PrimaryScreen.Bounds.Width) && height.Equals(Screen.PrimaryScreen.Bounds.Height))) {
				LogHandler.log(getName(), "Current Resolution: " + Screen.PrimaryScreen.Bounds.Width.ToString() + " x " + Screen.PrimaryScreen.Bounds.Height.ToString());
				LogHandler.log(getName(), "Attempting to change resoltution");
				
				if(display.changeDisplaySettings(width, height, refresh, 0)) {
					LogHandler.log(getName(), "Success");
				} else {
					LogHandler.log(getName(), "Unable to change resolution");
					LogHandler.log(getName(), "ERROR: " + "unkown");
				}
				
			} else {
				LogHandler.log(getName(), "Current resolution is already set correctly");
			}
		}
		
	}
}