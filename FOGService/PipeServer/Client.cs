
using System;
using System.IO;
using Microsoft.Win32.SafeHandles;

namespace FOG
{
	/// <summary>
	/// A generic pipe server client
	/// </summary>
	public class Client {
		private SafeFileHandle safeFileHandle;
		private FileStream fileStream;
		
		public Client(SafeFileHandle safeFileHandle, FileStream fileStream) {
			this.safeFileHandle = safeFileHandle;
			this.fileStream = fileStream;
		}
		public Client() { }
		
		public SafeFileHandle getSafeFileHandle() { return this.safeFileHandle; }
		public void setFileHandle(SafeFileHandle safeFilHandle) { this.safeFileHandle = safeFilHandle; }
		
		public FileStream getFileStream() { return this.fileStream; }
		public void setFileStream(FileStream fileStream) { this.fileStream = fileStream; }		
		
		
	}
}
