
using System;
using System.IO;
using System.Threading;

namespace FOG {
	class Program {
		
		public static void Main(string[] args) {
			for(int i=0; i <5; i++) {
				if(applyUpdates())
					break;
				Thread.Sleep(5000);
			}
		}
		
		private static Boolean applyUpdates() {
			Boolean success = false;
			
			foreach(String updateFile in Directory.GetFiles(AppDomain.CurrentDomain.BaseDirectory)) {
				if(updateFile.EndsWith(".update")) {
					String postUpdateFile = updateFile.Substring(0, updateFile.Length-(".update").Length);
					
					try {
						File.Delete(postUpdateFile);
						File.Move(updateFile, postUpdateFile);
					} catch (Exception ex) {
						success = false;
					}
				}
			}
			return success;
			
		}
	}
}