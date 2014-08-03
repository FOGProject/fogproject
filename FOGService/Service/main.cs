
using System;
using System.ServiceProcess;

namespace FOG
{
    static class Program
    {
        /// <summary>
        /// Start the FOG Service
        /// </summary>
        static void Main()
        {
            ServiceBase.Run(new FOGService());
        }
    }
}
