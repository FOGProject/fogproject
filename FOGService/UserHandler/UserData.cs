
using System;

namespace FOG
{
	/// <summary>
	/// Hold information about a specific user account
	/// </summary>
	public class UserData {
		private String name;
		private String sid;

		public UserData(String name, String sid) {
			this.name = name;
			this.sid = sid;
		}
		
		public String getName() { return this.name; }
		public String getSID() { return this.sid; }
	}
}
