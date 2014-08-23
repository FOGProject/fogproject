
using System;
using System.Collections.Generic;


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
					
				} else {
					LogHandler.log(getName(), "ERROR");
					LogHandler.log(getName(), "Not all values set: " + "x=" + taskResponse.getField("#x") + " y=" + taskResponse.getField("#y") + " r=" + taskResponse.getField("#r"));
				}
			}
		}
		
	}
}