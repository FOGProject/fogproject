using System;
using System.Collections.Generic;
using System.Text;

namespace FOG
{
    class SubClassMenuItem
    {
        private String strFile;
        private String strDesc;

        public SubClassMenuItem(string file, string desc)
        {
            strFile = file;
            strDesc = desc;
        }

        public string getFile()
        {
            return strFile;
        }

        public String getDescription()
        {
            return strDesc;
        }

    }
}
