using System;
using System.Collections.Generic;
using System.ComponentModel;
using System.Data;
using System.Drawing;
using System.Text;
using System.Windows.Forms;

namespace FOG
{
    public partial class ALOForm : Form
    {
        private int intTimeout;
        private Image bgImage;
        private AutoLogOut parentRef;
        private Point ptLastMouse;
        private DateTime lastActivity;

        private Boolean blActive;
        private Boolean blNorth;
        private Boolean blWest;

        private Boolean blLoggedIn;

        

        public ALOForm(int intTimeout, Image image, AutoLogOut parentRef)
        {
            InitializeComponent();

            this.intTimeout = intTimeout;
            this.bgImage = image;
            this.parentRef = parentRef;

            blActive = false;
            blLoggedIn = false;

            blNorth = false;
            blWest = false;

            prepareDisplay();

            if (image != null)
                pnlFloater.BackgroundImage = image;

        }

        private void prepareDisplay()
        {
            pnlFloater.Top = 1;
            pnlFloater.Left = 1;
            pnlFloater.Width = 300;
            pnlFloater.Height = 300;

            pnlCntDwn.Height = 75;
            pnlCntDwn.Top = pnlFloater.Height - pnlCntDwn.Height;
            pnlCntDwn.Left = 5;
        }

        private void display()
        {
            this.TopMost = true;
            tmrMover.Enabled = true;
            blActive = true;

            Cursor.Hide();

            Top = 0;
            Left = 0;
            Width = Screen.PrimaryScreen.Bounds.Width;
            Height = Screen.PrimaryScreen.Bounds.Height;
        }

        private void resetActivityStats()
        {
            tmrMover.Enabled = false;
            blActive = false;
            Cursor.Show();
            Width = 0;
            Height = 0;
            Top = -1;
            Left = -1;
            ptLastMouse = MousePosition;
            lastActivity = DateTime.Now;
        }

        private void ALOForm_KeyPress(object sender, KeyPressEventArgs e)
        {
            resetActivityStats();
        }

        private void ALOForm_MouseClick(object sender, MouseEventArgs e)
        {
            resetActivityStats();
        }

        private void tmrWatcher_Tick(object sender, EventArgs e)
        {
            takeAction();
        }

        public void setLoggedIn(Boolean loggedIn)
        {
            
            blLoggedIn = loggedIn;
            if (blLoggedIn)
            {
                resetActivityStats();
                tmrWatcher.Enabled = true;
            }
            else
            {
                resetActivityStats();
            }
        }

        private void takeAction()
        {
            
            if (blLoggedIn)
            {
                if (Math.Abs(MousePosition.X - ptLastMouse.X) > 10 || Math.Abs(MousePosition.Y - ptLastMouse.Y) > 10)
                {
                    resetActivityStats();
                }

                if (!blActive)
                {
                    Double dblCutOff = ((double)intTimeout * ( .75 ) );                    
                    
                    TimeSpan ts = DateTime.Now - lastActivity;
                    
                    if (ts.TotalMinutes >= dblCutOff)
                    {
                        display();
                    }
                }
                else
                {
                    TimeSpan ts = DateTime.Now - lastActivity;
                    if ((int)((intTimeout * 60) - ts.TotalSeconds) <= 0)
                    {
                        resetActivityStats();
                        parentRef.ALOLogOffUser();
                    }
                }
            }
        }

        private void tmrMover_Tick(object sender, EventArgs e)
        {
            if (blLoggedIn)
            {
                if (pnlFloater.Top <= 0)
                {
                    blNorth = false;
                }
                else if (pnlFloater.Top + pnlFloater.Height >= this.Height)
                {
                    blNorth = true;
                }


                if (pnlFloater.Left <= 0)
                {
                    blWest = false;
                }
                else if (pnlFloater.Left + pnlFloater.Width >= this.Width)
                {
                    blWest = true;
                }


                if (blNorth)
                    pnlFloater.Top -= 1;
                else
                    pnlFloater.Top += 1;


                if (blWest)
                    pnlFloater.Left -= 1;
                else
                    pnlFloater.Left += 1;

                TimeSpan ts = DateTime.Now - lastActivity;
                int intT = (int)(intTimeout - ts.TotalMinutes);
                String strUnits = "minutes";
                Color clr = Color.Black;

                if (intT <= 0)
                {
                    intT = (int)((intTimeout * 60) - ts.TotalSeconds);
                    strUnits = "seconds";
                    clr = Color.DarkRed;
                    if (intT < 0) intT = 0;
                }

                lblTimeRemaining.ForeColor = clr;
                lblTimeRemaining.Text = intT + " " + strUnits;

                TopMost = true;
                BringToFront();
            }
        }   
        

        private void ALOForm_Load(object sender, EventArgs e)
        {
            resetActivityStats();
        }

        private void ALOForm_KeyDown(object sender, KeyEventArgs e)
        {
            resetActivityStats();
        }
    }
}