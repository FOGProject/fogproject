
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
	public class PipeServer
	{
		[DllImport("kernel32.dll", SetLastError = true)]
		public static extern SafeFileHandle CreateNamedPipe(String pipeName, uint dwOpenMode, uint dwPipeMode, uint nMaxInstances, uint nOutBufferSize, uint nInBufferSize, uint nDefaultTimeOut, IntPtr lpSecurityAttributes);
	
		[DllImport("kernel32.dll", SetLastError = true)]
		public static extern int ConnectNamedPipe(SafeFileHandle hNamedPipe, IntPtr lpOverlapped);
	
		[DllImport("Advapi32.dll", SetLastError = true)]
		public static extern bool InitializeSecurityDescriptor(out SECURITY_DESCRIPTOR sd, int dwRevision);
	
		[DllImport("Advapi32.dll", SetLastError = true)]
		public static extern bool SetSecurityDescriptorDacl(ref SECURITY_DESCRIPTOR sd, bool bDaclPresent, IntPtr Dacl, bool bDaclDefaulted);
	
		[StructLayout(LayoutKind.Sequential)]
		public struct SECURITY_ATTRIBUTES
		{
			public int nLength;
			public IntPtr lpSecurityDescriptor;
			public bool bInheritHandle;
		}
	
		[StructLayout(LayoutKind.Sequential)]
		public struct SECURITY_DESCRIPTOR
		{
			private byte Revision;
			private byte Sbz1;
			private ushort Control;
			private IntPtr Owner;
			private IntPtr Group;
			private IntPtr Sacl;
			private IntPtr Dacl;
		}
	
		public const uint DUPLEX = (0x00000003);
		public const uint FILE_FLAG_OVERLAPPED = (0x40000000);
	
		public delegate void MessageReceivedHandler(Client client, string message);
	
		public event MessageReceivedHandler MessageReceived;
		public const int BUFFER_SIZE = 4096;
	
		String strPipeName;
		Thread listenThread;
		Boolean blRunning;
		List<Client> clients;
	
		public PipeServer(String pipeName)
		{
			blRunning = false;
			strPipeName = pipeName;
			clients = new List<Client>();
		}
	
		public void start()
		{
			listenThread = new Thread(new ThreadStart(listenForClients));
			listenThread.IsBackground = true;
			listenThread.Start();
			blRunning = true;
		}
	
		private void listenForClients()
		{
			// Do the security stuff to allow any user to connect.
			// This was fixed in version 0.16 to allow users in the group
			// "users" to interact with the backend service.
			Boolean blSecOk = false;
	
			IntPtr ptrSec = IntPtr.Zero;
			SECURITY_ATTRIBUTES secAttrib = new SECURITY_ATTRIBUTES();
			SECURITY_DESCRIPTOR secDesc;
	
			if (InitializeSecurityDescriptor(out secDesc, 1))
			{
				if (SetSecurityDescriptorDacl(ref secDesc, true, IntPtr.Zero, false))
				{
					secAttrib.lpSecurityDescriptor = Marshal.AllocHGlobal(Marshal.SizeOf(typeof(SECURITY_DESCRIPTOR)));
					Marshal.StructureToPtr(secDesc, secAttrib.lpSecurityDescriptor, false);
					secAttrib.bInheritHandle = false;
					secAttrib.nLength = Marshal.SizeOf(typeof(SECURITY_ATTRIBUTES));
					ptrSec = Marshal.AllocHGlobal(Marshal.SizeOf(typeof(SECURITY_ATTRIBUTES)));
					Marshal.StructureToPtr(secAttrib, ptrSec, false);
					blSecOk = true;
				}
			}
	
			if (blSecOk)
			{
				while (true)
				{
					SafeFileHandle clientHandle = CreateNamedPipe(@"\\.\pipe\" + strPipeName, DUPLEX | FILE_FLAG_OVERLAPPED, 0, 255, BUFFER_SIZE, BUFFER_SIZE, 0, ptrSec);
	
					if (clientHandle.IsInvalid)
						return;
	
					int success = ConnectNamedPipe(clientHandle, IntPtr.Zero);
	
					if (success == 0)
						return;
	
					Client client = new Client();
					client.setFileHandle(clientHandle);
	
					lock (clients)
						this.clients.Add(client);
	
					Thread readThread = new Thread(new ParameterizedThreadStart(read));
					readThread.IsBackground = true;
					readThread.Start(client);
				}
			}
		}
	
		private void read(object objClient)
		{
			Client client = (Client)objClient;
			client.setFileStream(new FileStream(client.getSafeFileHandle(), FileAccess.ReadWrite, BUFFER_SIZE, true));
	
			byte[] buffer = new byte[BUFFER_SIZE];
			ASCIIEncoding encoder = new ASCIIEncoding();
	
			while (true)
			{
				int bRead = 0;
	
				try
				{
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
			lock (clients)
				clients.Remove(client);
		}
	
		public void sendMessage(String msg)
		{
			lock (this.clients)
			{
				ASCIIEncoding encoder = new ASCIIEncoding();
				byte[] mBuf = encoder.GetBytes(msg);
				foreach (Client c in clients)
				{
					c.getFileStream().Write(mBuf, 0, mBuf.Length);
					c.getFileStream().Flush();
				}
			}
		}
	
		public Boolean isRunning() { return blRunning; }
	
		public String getPipeName() { return strPipeName; }
	}
	
	
}