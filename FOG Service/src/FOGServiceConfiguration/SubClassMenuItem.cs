using System;
using System.Collections.Generic;
using System.Text;
using System.IO;

namespace FOG
{
    class SubClassMenuItem
    {
        private String strFile;
        private String strDesc;
        private Boolean active;

        public SubClassMenuItem(string file, string desc)
        {
            strFile = file;
            strDesc = desc;
            this.active = true;
        }

        public string getFile()
        {
            return strFile;
        }
        
        public string getFileName() {
        	return Path.GetFileNameWithoutExtension(getFile());
        }

        public String getDescription()
        {
            return strDesc;
        }
        
        public Boolean getActive() {
        	return active;
        }
        
        public void setActive(Boolean active) {
        	this.active = active;
        }

    }
}
