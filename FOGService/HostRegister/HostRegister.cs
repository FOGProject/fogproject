
using System;
using System.Net;

namespace FOG {
	/// <summary>
	/// Register the host with FOG
	/// </summary>
	public class HostRegister: AbstractModule {
		public HostRegister():base(){
			setName("HostRegister");
			setDescription("Register the host with the FOG server");
		}
	
		protected override void doWork() {
			LogHandler.log(getName(), "Sending computer info to FOG");
			CommunicationHandler.contact("/service/register.php?mac=" + CommunicationHandler.getMacAddresses() + "&hostname=" + Dns.GetHostName());
			
		}
		
	}
	
}