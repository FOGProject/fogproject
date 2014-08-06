
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
	public class PipeClient {
		[DllImport("kernel32.dll", SetLastError = true)]
		public static extern SafeFileHandle CreateFile(
			String pipeName,
			uint dwDesiredAccess,
			uint dwShareMode,
			IntPtr lpSecurityAttributes,
			uint dwCreationDisposition,
			uint dwFlagsAndAttributes,
			IntPtr hTemplate);
		
		private const uint GENERIC_READ = (0x80000000);
		private const uint GENERIC_WRITE = (0x40000000);
		private const uint OPEN_EXISTING = 3;
		private const uint FILE_FLAG_OVERLAPPED = (0x40000000);
		private const int BUFFER_SIZE = 4096;
			
		public delegate void messageReceivedHandler(String message);
		public event messageReceivedHandler messageReceived;
		
		private String pipeName;
		private FileStream fileStream;
		private SafeFileHandle safeFileHandle;
		private Thread readThread;
		private Boolean running;
		
		public Boolean isRunning() { return this.running; }
		
		public PipeClient(String pipeName) {
			this.pipeName = pipeName;
			this.running = false;
			this.readThread = new Thread(new ThreadStart(readMessage));
		}
		
		public Boolean start() {
			this.safeFileHandle = CreateFile(this.pipeName, GENERIC_READ | GENERIC_WRITE, 0, IntPtr.Zero, 
			                                 OPEN_EXISTING, FILE_FLAG_OVERLAPPED, IntPtr.Zero);
			
			if(this.safeFileHandle.IsInvalid)
				return false;
			
			this.running = true;
			this.readThread.Start();
			return true;
		}
		
		public void stop() {
			this.running = false;
		}
		
		private void readMessage() {
			this.fileStream = new FileStream(this.safeFileHandle, FileAccess.ReadWrite, BUFFER_SIZE, true);
			byte[] readBuffer = new byte[BUFFER_SIZE];
			ASCIIEncoding encoder = new ASCIIEncoding();
			
			while (true) {
				int bytesRead = 0;
				
				try { 
					bytesRead = this.fileStream.Read(readBuffer, 0, BUFFER_SIZE);
				} catch {
					break;
				}
				
				if(bytesRead == 0) 
					break;
				
				if(this.messageReceived != null)
					this.messageReceived(encoder.GetString(readBuffer, 0, bytesRead));
			}
			
			this.fileStream.Close();
			this.safeFileHandle.Close();
		}
		
		public void sendMessage(String message) {
			ASCIIEncoding encoder = new ASCIIEncoding();
			byte[] messageBuffer = encoder.GetBytes(message);
			
			this.fileStream.Write(messageBuffer, 0, messageBuffer.Length);
			this.fileStream.Flush();
		}
		
		
	}
}