
using System;

namespace FOG
{
	/// <summary>
	/// Store neccesary notification information
	/// </summary>
	public class Notification {
		private String title;
		private String message;
		private int duration;
		
		public Notification(String title, String message, int duration) {
			this.title = title;
			this.message = message;
			this.duration = duration;
		}
		
		public String getTitle() {
			return this.title;
		}
		
		public String getMessage() {
			return this.message;
		}
		
		public int getDuration() {
			return duration;
		}
	}
}
