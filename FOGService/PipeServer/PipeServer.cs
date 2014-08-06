
using System;
using System.IO;
using System.Text;
using System.Threading;
using System.Collections.Generic;
using Microsoft.Win32.SafeHandles;
using System.Runtime.InteropServices;

namespace FOG {
	/// <summary>
	/// Inter-proccess communication server
	/// </summary>
	public class PipeServer {
		//Import kernel32 for safe file handling
		[DllImport("kernel32.dll", SetLastError = true)]
		public static extern SafeFileHandle CreateNamedPipe(
			String pipeName,
			uint dwOpenMode,
			uint dwPipeMode,
			uint nMaxInstances,
			uint nOutBufferSize,
			uint nInBufferSize,
			uint nDefaultTimeOut,
			IntPtr lpSecurityAttributes);
		
		[DllImport("kernel32.dll", SetLastError = true)]
		public static extern int ConnectNamedPipe(
			SafeFileHandle hNamedPipe,
			IntPtr lpOverlapped);
		
		private const uint DUPLEX = (0x00000003);
		private const uint FILE_FLAG_OVERLAPPED = (0x40000000);
		private const int BUFFER_SIZE = 4096;
		
		public delegate void messageReceivedHandler(Client client, String message);
		public event messageReceivedHandler messageReceived;
		
		private String pipeName;
		private Thread listenThread;	
		private Boolean running;
		private List<Client> clients;
		
		public PipeServer(String pipeName) {
			this.clients = new List<Client>();
			this.pipeName = pipeName;
			this.listenThread = new Thread(new ThreadStart(listenForClients));
			this.running = false;
		}
		
		public Boolean isRunning() { return this.running; }
		
		public void start() {
			this.running = true;
			this.listenThread.Start();
		}
		
		public void stop() {
			this.running = false;
		}
		
		private void listenForClients() {
			while(true) {
				SafeFileHandle clientHandle = CreateNamedPipe(this.pipeName, DUPLEX | FILE_FLAG_OVERLAPPED, 0, 255, BUFFER_SIZE, BUFFER_SIZE, 0, IntPtr.Zero);
				
				//Failed to create a named pipe
				if(clientHandle.IsInvalid)
					return;
				
				int success = ConnectNamedPipe(clientHandle, IntPtr.Zero);
				
				//Could not connect to the client
				if(success == 0)
					return;
				
				Client client = new Client();
				client.setFileHandle(clientHandle);
				
				lock(clients)
					this.clients.Add(client);
				
				Thread readThread = new Thread(new ParameterizedThreadStart(readMessage));
				readThread.Start(client);
				                                              
			}
		}
		
		private void readMessage(Object clientObject) {
			Client client = (Client)clientObject;
			
			client.setFileStream(new FileStream(client.getSafeFileHandle(), FileAccess.ReadWrite, BUFFER_SIZE, true));
			byte[] buffer = new byte[BUFFER_SIZE];
			ASCIIEncoding encoder = new ASCIIEncoding();
			
			while (true) {
				int bytesRead = 0;
				try {
					bytesRead = client.getFileStream().Read(buffer, 0, BUFFER_SIZE);
				} catch {
					break;
				}
				
				if(bytesRead == 0)
					break;
				
				if(this.messageReceived != null)
					this.messageReceived(client, encoder.GetString(buffer, 0, bytesRead));
			}
			
			client.getFileStream().Close();
			client.getSafeFileHandle().Close();
			
			lock(this.clients)
				this.clients.Remove(client);
		}
		
		public void sendMessage(String message) {
			lock (this.clients) {
				ASCIIEncoding encoder = new ASCIIEncoding();
				byte[] messageBuffer = encoder.GetBytes(message);
				foreach (Client client in this.clients) {
					if(client.getFileStream() != null) {
						client.getFileStream().Write(messageBuffer, 0, messageBuffer.Length);
						client.getFileStream().Flush();
					}
				}
			}
		}
	}
	
	
}