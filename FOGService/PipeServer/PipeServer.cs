
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
		[DllImport("kernel32.dll", SetLastError = true)]
		public static extern SafeFileHandle CreateNamedPipe(String pipeName, uint dwOpenMode, uint dwPipeMode, uint nMaxInstances, uint nOutBufferSize, uint nInBufferSize, uint nDefaultTimeOut, IntPtr lpSecurityAttributes);
	
		[DllImport("kernel32.dll", SetLastError = true)]
		public static extern int ConnectNamedPipe(SafeFileHandle hNamedPipe, IntPtr lpOverlapped);
	
		[DllImport("Advapi32.dll", SetLastError = true)]
		public static extern bool InitializeSecurityDescriptor(out SECURITY_DESCRIPTOR sd, int dwRevision);
	
		[DllImport("Advapi32.dll", SetLastError = true)]
		public static extern bool SetSecurityDescriptorDacl(ref SECURITY_DESCRIPTOR sd, bool bDaclPresent, IntPtr Dacl, bool bDaclDefaulted);
	
		[StructLayout(LayoutKind.Sequential)]
		public struct SECURITY_ATTRIBUTES {
			public int nLength;
			public IntPtr lpSecurityDescriptor;
			public bool bInheritHandle;
		}
	
		[StructLayout(LayoutKind.Sequential)]
		public struct SECURITY_DESCRIPTOR {
			private byte Revision;
			private byte Sbz1;
			private ushort Control;
			private IntPtr Owner;
			private IntPtr Group;
			private IntPtr Sacl;
			private IntPtr Dacl;
		}
	
		private const uint DUPLEX = (0x00000003);
		private const uint FILE_FLAG_OVERLAPPED = (0x40000000);
	
		public delegate void MessageReceivedHandler(Client client, string message);
	
		public event MessageReceivedHandler MessageReceived;
		public const int BUFFER_SIZE = 4096;
	
		private String pipeName;
		private Thread listenThread;
		private Boolean running;
		private List<Client> clients;
	
		public PipeServer(String pipeName) {
			this.running = false;
			this.pipeName = pipeName;
			clients = new List<Client>();
		}
	
		public void start() {
			this.listenThread = new Thread(new ThreadStart(listenForClients));
			this.listenThread.IsBackground = true;
			this.listenThread.Start();
			this.running = true;
		}
	
		private void listenForClients() {
			// Do the security stuff to allow any user to connect.
			// This was fixed in version 0.16 to allow users in the group
			// "users" to interact with the backend service.
			Boolean security = false;
	
			IntPtr ptrSec = IntPtr.Zero;
			SECURITY_ATTRIBUTES securityAttribute = new SECURITY_ATTRIBUTES();
			SECURITY_DESCRIPTOR securityDescription;
	
			if (InitializeSecurityDescriptor(out securityDescription, 1)) {
				if (SetSecurityDescriptorDacl(ref securityDescription, true, IntPtr.Zero, false)) {
					securityAttribute.lpSecurityDescriptor = Marshal.AllocHGlobal(Marshal.SizeOf(typeof(SECURITY_DESCRIPTOR)));
					Marshal.StructureToPtr(securityDescription, securityAttribute.lpSecurityDescriptor, false);
					securityAttribute.bInheritHandle = false;
					securityAttribute.nLength = Marshal.SizeOf(typeof(SECURITY_ATTRIBUTES));
					ptrSec = Marshal.AllocHGlobal(Marshal.SizeOf(typeof(SECURITY_ATTRIBUTES)));
					Marshal.StructureToPtr(securityAttribute, ptrSec, false);
					security = true;
				}
			}
	
			if (security) {
				while (true) {
					SafeFileHandle clientHandle = CreateNamedPipe(@"\\.\pipe\" + this.pipeName, DUPLEX | FILE_FLAG_OVERLAPPED, 0, 255, BUFFER_SIZE, BUFFER_SIZE, 0, ptrSec);
	
					if (clientHandle.IsInvalid)
						return;
	
					int success = ConnectNamedPipe(clientHandle, IntPtr.Zero);
	
					if (success == 0)
						return;
	
					Client client = new Client();
					client.setFileHandle(clientHandle);
	
					lock (this.clients)
						this.clients.Add(client);
	
					Thread readThread = new Thread(new ParameterizedThreadStart(read));
					readThread.IsBackground = true;
					readThread.Start(client);
				}
			}
		}
	
		private void read(object objClient) {
			Client client = (Client)objClient;
			client.setFileStream(new FileStream(client.getSafeFileHandle(), FileAccess.ReadWrite, BUFFER_SIZE, true));
	
			byte[] buffer = new byte[BUFFER_SIZE];
			ASCIIEncoding encoder = new ASCIIEncoding();
	
			while (true) {
				int bRead = 0;
	
				try {
					bRead = client.getFileStream().Read(buffer, 0, BUFFER_SIZE);
				}
				catch { }
	
				if (bRead == 0)
					break;
	
				if (MessageReceived != null)
					MessageReceived(client, encoder.GetString(buffer, 0, bRead));
			}
	
			client.getFileStream().Close();
			client.getFileStream().Close();
			lock (this.clients)
				this.clients.Remove(client);
		}
	
		public void sendMessage(String msg) {
			lock (this.clients) {
				ASCIIEncoding encoder = new ASCIIEncoding();
				byte[] mBuf = encoder.GetBytes(msg);
				foreach (Client client in this.clients) {
					client.getFileStream().Write(mBuf, 0, mBuf.Length);
					client.getFileStream().Flush();
				}
			}
		}
	
		public Boolean isRunning() { return this.running; }
	
		public String getPipeName() { return this.pipeName; }
	}
	
	
}