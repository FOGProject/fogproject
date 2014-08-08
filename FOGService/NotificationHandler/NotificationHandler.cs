
using System;
using System.Collections.Generic;

namespace FOG {
	/// <summary>
	/// Handle all notifications
	/// </summary>
	public static class NotificationHandler
	{
		//Define variable
		private static List<Notification> notifications = new List<Notification>();
		
		public static void createNotification(Notification notification) { getNotifications().Add(notification); }
		public static List<Notification> getNotifications() { return notifications; }
		public static void clearNotifications() { getNotifications().Clear(); }
		public static void removeNotification(Notification notification) { getNotifications().Remove(notification); }
		public static void removeNotification(int index) { getNotifications().RemoveAt(index); }
		
	}
}