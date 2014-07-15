using System;
using System.Collections.Generic;
using System.Text;
using System.Collections;
using System.Data;
using System.Management;
using System.Net;
using System.Windows.Forms;

namespace AbstractTrayModule
{
    public abstract class AbstractFOGTrayModule
    {
        public const String VERSION = "1";

        // this is called to start any daemon threads
        // with in the sub process
        public abstract void start();

        public abstract void stop();

        public abstract String getDescription();

        public abstract ToolStripMenuItem getMenuSegment();

        public String getHostName()
        {
            return System.Environment.MachineName;
        }

        public ArrayList getIPAddress()
        {
            ArrayList arIPs = new ArrayList();
            try
            {
                String strHost = null;
                strHost = Dns.GetHostName();

                IPHostEntry ip = Dns.GetHostEntry(strHost);
                IPAddress[] ipAddys = ip.AddressList;

                if (ipAddys.Length > 0)
                    arIPs.Add(ipAddys[0].ToString());
            }
            catch
            { }
            return arIPs;
        }

        public ArrayList getMacAddress()
        {
            ArrayList alMacs = new ArrayList();
            try
            {
                ManagementClass mc = new ManagementClass("Win32_NetworkAdapterConfiguration");
                ManagementObjectCollection moc = mc.GetInstances();
                foreach (ManagementObject mo in moc)
                {
                    if (mo.Properties["IPEnabled"] != null && mo.Properties["IPEnabled"].Value.ToString().ToLower() == "true")
                    {
                        alMacs.Add(mo.Properties["MacAddress"].Value.ToString());
                    }
                }
            }
            catch 
            {
                
            }
            return alMacs;
        }

        public String getUserName()
        {
            String username = null;
            try
            {

                try
                {
                    ManagementObjectSearcher searcher = new ManagementObjectSearcher("root\\CIMV2", "SELECT * FROM Win32_ComputerSystem");

                    foreach (ManagementObject queryObj in searcher.Get())
                    {
                        return queryObj["UserName"].ToString();
                    }
                }
                catch
                {
                    return null;
                }

            }
            catch
            {
                return null;
            }
            return username;
        }

    }
}
