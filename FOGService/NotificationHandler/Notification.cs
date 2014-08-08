
using System;

namespace FOG
{
	/// <summary>
	/// Store neccesary notification information
	/// </summary>
	public class Notification {
		//Define variables
		private String title;
		private String message;
		private int duration;
		
		public Notification() {
			this.title = "";
			this.message = "";
			this.duration = 10;
		}
		
		public Notification(String title, String message, int duration) {
			this.title = title;
			this.message = message;
			this.duration = duration;
		}
		
		public String getTitle() { return this.title; }
		public void setTitle(String title) { this.title = title; }		
		
		public String getMessage() { return this.message; }
		public void setMessage(String message) { this.message = message; }		
		
		public int getDuration() { return duration; }
		public void setDuration(int duration) { this.duration = duration; }		
	}
}
