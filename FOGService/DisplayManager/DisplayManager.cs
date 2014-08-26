
using System;
using System.Collections.Generic;
using System.Windows.Forms;


namespace FOG {
	/// <summary>
	/// Change the resolution of the display
	/// </summary>
	public class DisplayManager : AbstractModule {

			
		public DisplayManager() : base() {
			setName("DisplayManager");
			setDescription("hange the resolution of the display");			
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
		
		private void getResolution() {
			Rectangle resolution = Screen.PrimaryScreen.GetBounds;
		}
		
		//Change the resolution of the screen
		private Boolean changeResolution(int width, int height, int refresh) {
			return false;
		}
		
	}
}