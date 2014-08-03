
using System;
using System.Collections.Generic;

namespace FOG
{
	/// <summary>
	/// Handle all notifications
	/// </summary>
	public class NotificationHandler
	{
		private List<Notification> notifications;
		
		public NotificationHandler() {
			this.notifications = new List<Notification>();
		}
		
		public void createNotification(Notification notification) {
			this.notifications.Add(notification);
		}
		
		public List<Notification> getNotifications() {
			return this.notifications;
		}
		
		public void clearNotifications() {
			this.notifications.Clear();
		}
		
		public void removeNotification(Notification notification) {
			this.notifications.Remove(notification);
		}
		
		public void removeNotification(int index) {
			this.notifications.RemoveAt(index);
		}
		
		
	}
}