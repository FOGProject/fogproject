
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
	
		private const uint GENERIC_READ = (0x80000000);
		private const uint GENERIC_WRITE = (0x40000000);
		private const uint OPEN_EXISTING = 3;
		private const uint FILE_FLAG_OVERLAPPED = (0x40000000);
	
		public delegate void MessageReceivedHandler(string message);
		public event MessageReceivedHandler MessageReceived;
	
		private const int BUFFER_SIZE = 4096;
	
		private Boolean blConnected;
		private string strPipeName;
		private FileStream stream;
		private SafeFileHandle handle;
		private Thread readThread;
	
		public PipeClient(String pipe) {
			blConnected = false;
			strPipeName = pipe;
		}
	
		public Boolean isConnected() { return blConnected; }
	
		public String getPipeName() { return strPipeName; }
	
		public Boolean Connect() {
			try {
				handle = CreateFile(@"\\.\pipe\" + strPipeName, GENERIC_READ | GENERIC_WRITE, 0, IntPtr.Zero, OPEN_EXISTING, FILE_FLAG_OVERLAPPED, IntPtr.Zero);
	
				if (handle == null) return false;
	
				if (handle.IsInvalid) {
					blConnected = false;
					return false;
				}
	
				blConnected = true;
	
				readThread = new Thread(new ThreadStart(readFromPipe));
				readThread.Start();
				return true;
			} catch  {
				return false;
			}
		}
	
		public void kill() {
			try {
				if (stream != null) 
					stream.Close();
	
				if (handle != null)
					handle.Close();
	
				readThread.Abort();
			} catch { }
		}
	
		public void readFromPipe() {
			stream = new FileStream(handle, FileAccess.ReadWrite, BUFFER_SIZE, true);
			byte[] readBuffer = new byte[BUFFER_SIZE];
	
			ASCIIEncoding encoder = new ASCIIEncoding();
			while (true) {
				int bRead = 0;
	
				try {
					bRead = stream.Read(readBuffer, 0, BUFFER_SIZE);
				} catch {
					break;
				}
	
				if (bRead == 0) break;
	
				if (MessageReceived != null) MessageReceived(encoder.GetString(readBuffer, 0, bRead));
			}
			stream.Close();
			handle.Close();
		}
	
		public void sendMessage(String message) {
			try {
				ASCIIEncoding encoder = new ASCIIEncoding();
				byte[] messageBuffer = encoder.GetBytes(message);
	
				stream.Write(messageBuffer, 0, messageBuffer.Length);
				stream.Flush();
			} catch {
			}
		}
	
	}
}