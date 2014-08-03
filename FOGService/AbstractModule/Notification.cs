
using System;

namespace FOG
{
	/// <summary>
	/// Basic notification structure
	/// </summary>
	public class Notification
	{
		private String title;
		private String msg;
		
		public Notification(String title, String msg)
		{
			this.title = title;
			this.msg = msg;
		}
		
		public String getMessage() {
			return this.msg;
		}
			
		public String getTitle() {
			return this.title;
		}
	}
}
