using System;
using System.Collections.Generic;
using System.ComponentModel;
using System.Data;
using System.Drawing;
using System.Text;
using System.Windows.Forms;

namespace FOG
{
    public partial class FrmUI : Form
    {
        public FrmUI()
        {
            InitializeComponent();
        }

        public void setMessage(String msg)
        {
            lblMessage.Text = msg;
        }

        public void setTime(int secs)
        {
            lblTime.Text = secs + " Secs.";
        }

        
    }
}