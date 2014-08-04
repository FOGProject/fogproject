using System;
using System.Collections.Generic;
using System.ComponentModel;
using System.Data;
using System.Diagnostics;
using System.ServiceProcess;

using System.Threading;
using System.Configuration;
using System.IO;
using System.Collections;
using System.Reflection;
using System.Net;

using FOG;

namespace FOG
{
	/// <summary>
	/// Coordinate all FOG modules
	/// </summary>
	public partial class FOGService  : ServiceBase
	{
		private Thread threadManager;
		private List<AbstractModule> modules;
		private List<Thread> threads;
		
		//Create an instance of each handler so all modules share the same one
        
		public FOGService()
		{
		}
		
		protected override void OnStart(string[] args)
        {
        }
			

		private void startAllSubProcesses()
		{
		}
		
		private Boolean loadModules() {
			return false;
		}
		
		protected override void OnStop()
		{
		}

	}
}
