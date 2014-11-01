
using System;
using System.Collections.Generic;
using System.Text;
using System.Runtime.InteropServices;
using Microsoft.Win32.SafeHandles;
using System.Threading;
using System.IO;

namespace FOG {
	/// <summary>
	///  Inter-proccess communication client
	/// </summary>
	public class PipeClient
	{
		[DllImport("kernel32.dll", SetLastError = true)]
		public static extern SafeFileHandle CreateFile(String pipeName, uint dwDesiredAccess, uint dwShareMode,IntPtr lpSecurityAttributes, uint dwCreationDisposition, uint dwFlagsAndAttributes, IntPtr hTemplate);
	
		//Define variables
		private const uint GENERIC_READ = (0x80000000);
		private const uint GENERIC_WRITE = (0x40000000);
		private const uint OPEN_EXISTING = 3;
		private const uint FILE_FLAG_OVERLAPPED = (0x40000000);
	
		public delegate void MessageReceivedHandler(string message);
		public event MessageReceivedHandler MessageReceived;
	
		private const int BUFFER_SIZE = 4096;
	
		private Boolean connected;
		private string pipeName;
		private FileStream stream;
		private SafeFileHandle handle;
		private Thread readThread;
	
		
		public PipeClient(String pipeName) {
			this.connected = false;
			this.pipeName = pipeName;
		}
	
		public Boolean isConnected() { return this.connected; }
		public String getPipeName() { return this.pipeName; }
	
		//Connect to a server using the same pipe
		public Boolean connect() {
			try {
				this.handle = CreateFile(@"\\.\pipe\" + this.pipeName, GENERIC_READ | GENERIC_WRITE, 0, IntPtr.Zero, 
				                         OPEN_EXISTING, FILE_FLAG_OVERLAPPED, IntPtr.Zero);
	
				if (this.handle == null) 
					return false;
	
				if (this.handle.IsInvalid) {
					this.connected = false;
					return false;
				}
	
				this.connected = true;
	
				this.readThread = new Thread(new ThreadStart(readFromPipe));
				this.readThread.Start();
				
				return true;
			} catch  {
				return false;
			}
		}
	
		//Stop the pipe client
		public void kill() {
			try {
				if (this.stream != null) 
					this.stream.Close();
	
				if (this.handle != null)
					this.handle.Close();
	
				this.readThread.Abort();
			} catch { }
		}
	
		//Read a message sent over from the pipe server
		public void readFromPipe() {
			this.stream = new FileStream(handle, FileAccess.ReadWrite, BUFFER_SIZE, true);
			byte[] readBuffer = new byte[BUFFER_SIZE];
	
			ASCIIEncoding encoder = new ASCIIEncoding();
			while (true) {
				int bytesRead = 0;
	
				try {
					bytesRead = stream.Read(readBuffer, 0, BUFFER_SIZE);
				} catch {
					break;
				}
	
				if (bytesRead == 0) break;
	
				if (MessageReceived != null) MessageReceived(encoder.GetString(readBuffer, 0, bytesRead));
			}
			this.stream.Close();
			this.handle.Close();
		}
	
		//Send a message across the pipe
		public void sendMessage(String message) {
			try {
				ASCIIEncoding encoder = new ASCIIEncoding();
				byte[] messageBuffer = encoder.GetBytes(message);
	
				this.stream.Write(messageBuffer, 0, messageBuffer.Length);
				this.stream.Flush();
			} catch { }
		}
	
	}
}