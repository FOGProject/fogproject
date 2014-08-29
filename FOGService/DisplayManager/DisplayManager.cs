
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
				if(!taskResponse.getField("#x").Equals("") && !taskResponse.getField("#y").Equals("") && !taskResponse.getField("#r").Equals("")) {
					changeResolution(taskResponse.getField("#x"), taskResponse.getField("#y"), taskResponse.getField("#r"));
				} else {
					LogHandler.log(getName(), "ERROR");
					LogHandler.log(getName(), "Not all values set: " + "x=" + taskResponse.getField("#x") + " y=" + taskResponse.getField("#y") + " r=" + taskResponse.getField("#r"));
				}
			}
		}
		
		//Change the resolution of the screen
		private void changeResolution(int width, int height, int refresh) {
			if(!(width.Equals(Screen.PrimaryScreen.Bounds.Width) && height.Equals(Screen.PrimaryScreen.Bounds.Height))) {
				LogHandler.log(getName(), "Current Resolution: " + Screen.PrimaryScreen.Bounds.Width.ToString() + " x " + Screen.PrimaryScreen.Bounds.Height.ToString()).
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