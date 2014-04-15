namespace FOG
{
    partial class ALOForm
    {
        /// <summary>
        /// Required designer variable.
        /// </summary>
        private System.ComponentModel.IContainer components = null;

        /// <summary>
        /// Clean up any resources being used.
        /// </summary>
        /// <param name="disposing">true if managed resources should be disposed; otherwise, false.</param>
        protected override void Dispose(bool disposing)
        {
            if (disposing && (components != null))
            {
                components.Dispose();
            }
            base.Dispose(disposing);
        }

        #region Windows Form Designer generated code

        /// <summary>
        /// Required method for Designer support - do not modify
        /// the contents of this method with the code editor.
        /// </summary>
        private void InitializeComponent()
        {
            this.components = new System.ComponentModel.Container();
            System.ComponentModel.ComponentResourceManager resources = new System.ComponentModel.ComponentResourceManager(typeof(ALOForm));
            this.tmrWatcher = new System.Windows.Forms.Timer(this.components);
            this.pnlFloater = new System.Windows.Forms.Panel();
            this.pnlImg = new System.Windows.Forms.Panel();
            this.pnlCntDwn = new System.Windows.Forms.Panel();
            this.lblMsg = new System.Windows.Forms.Label();
            this.lblTimeRemaining = new System.Windows.Forms.Label();
            this.tmrMover = new System.Windows.Forms.Timer(this.components);
            this.pnlFloater.SuspendLayout();
            this.pnlCntDwn.SuspendLayout();
            this.SuspendLayout();
            // 
            // tmrWatcher
            // 
            this.tmrWatcher.Enabled = true;
            this.tmrWatcher.Interval = 1000;
            this.tmrWatcher.Tick += new System.EventHandler(this.tmrWatcher_Tick);
            // 
            // pnlFloater
            // 
            this.pnlFloater.BackColor = System.Drawing.Color.DarkGray;
            this.pnlFloater.Controls.Add(this.pnlImg);
            this.pnlFloater.Controls.Add(this.pnlCntDwn);
            this.pnlFloater.Location = new System.Drawing.Point(106, 64);
            this.pnlFloater.Name = "pnlFloater";
            this.pnlFloater.Size = new System.Drawing.Size(301, 203);
            this.pnlFloater.TabIndex = 0;
            // 
            // pnlImg
            // 
            this.pnlImg.Anchor = ((System.Windows.Forms.AnchorStyles)((System.Windows.Forms.AnchorStyles.Top | System.Windows.Forms.AnchorStyles.Right)));
            this.pnlImg.BackColor = System.Drawing.Color.Transparent;
            this.pnlImg.BackgroundImage = ((System.Drawing.Image)(resources.GetObject("pnlImg.BackgroundImage")));
            this.pnlImg.BackgroundImageLayout = System.Windows.Forms.ImageLayout.None;
            this.pnlImg.Location = new System.Drawing.Point(224, -3);
            this.pnlImg.Name = "pnlImg";
            this.pnlImg.Size = new System.Drawing.Size(77, 43);
            this.pnlImg.TabIndex = 1;
            // 
            // pnlCntDwn
            // 
            this.pnlCntDwn.BackColor = System.Drawing.Color.Transparent;
            this.pnlCntDwn.BackgroundImage = ((System.Drawing.Image)(resources.GetObject("pnlCntDwn.BackgroundImage")));
            this.pnlCntDwn.Controls.Add(this.lblMsg);
            this.pnlCntDwn.Controls.Add(this.lblTimeRemaining);
            this.pnlCntDwn.Location = new System.Drawing.Point(7, 126);
            this.pnlCntDwn.Name = "pnlCntDwn";
            this.pnlCntDwn.Size = new System.Drawing.Size(288, 72);
            this.pnlCntDwn.TabIndex = 0;
            // 
            // lblMsg
            // 
            this.lblMsg.Font = new System.Drawing.Font("Arial", 11.25F, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, ((byte)(0)));
            this.lblMsg.ForeColor = System.Drawing.Color.Black;
            this.lblMsg.Location = new System.Drawing.Point(7, 6);
            this.lblMsg.Name = "lblMsg";
            this.lblMsg.Size = new System.Drawing.Size(268, 23);
            this.lblMsg.TabIndex = 1;
            this.lblMsg.Text = "User log off in: ";
            this.lblMsg.TextAlign = System.Drawing.ContentAlignment.TopCenter;
            // 
            // lblTimeRemaining
            // 
            this.lblTimeRemaining.Font = new System.Drawing.Font("Arial", 14.25F, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, ((byte)(0)));
            this.lblTimeRemaining.ForeColor = System.Drawing.Color.Black;
            this.lblTimeRemaining.Location = new System.Drawing.Point(3, 31);
            this.lblTimeRemaining.Name = "lblTimeRemaining";
            this.lblTimeRemaining.Size = new System.Drawing.Size(282, 34);
            this.lblTimeRemaining.TabIndex = 0;
            this.lblTimeRemaining.TextAlign = System.Drawing.ContentAlignment.MiddleCenter;
            // 
            // tmrMover
            // 
            this.tmrMover.Enabled = true;
            this.tmrMover.Interval = 5;
            this.tmrMover.Tick += new System.EventHandler(this.tmrMover_Tick);
            // 
            // ALOForm
            // 
            this.AutoScaleMode = System.Windows.Forms.AutoScaleMode.None;
            this.BackColor = System.Drawing.Color.Black;
            this.ClientSize = new System.Drawing.Size(763, 379);
            this.ControlBox = false;
            this.Controls.Add(this.pnlFloater);
            this.DoubleBuffered = true;
            this.Font = new System.Drawing.Font("Arial", 9.75F, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, ((byte)(0)));
            this.ForeColor = System.Drawing.Color.White;
            this.FormBorderStyle = System.Windows.Forms.FormBorderStyle.FixedToolWindow;
            this.Name = "ALOForm";
            this.ShowIcon = false;
            this.ShowInTaskbar = false;
            this.StartPosition = System.Windows.Forms.FormStartPosition.Manual;
            this.TopMost = true;
            this.MouseClick += new System.Windows.Forms.MouseEventHandler(this.ALOForm_MouseClick);
            this.KeyPress += new System.Windows.Forms.KeyPressEventHandler(this.ALOForm_KeyPress);
            this.KeyDown += new System.Windows.Forms.KeyEventHandler(this.ALOForm_KeyDown);
            this.Load += new System.EventHandler(this.ALOForm_Load);
            this.pnlFloater.ResumeLayout(false);
            this.pnlCntDwn.ResumeLayout(false);
            this.ResumeLayout(false);

        }

        #endregion

        private System.Windows.Forms.Timer tmrWatcher;
        private System.Windows.Forms.Panel pnlFloater;
        private System.Windows.Forms.Timer tmrMover;
        private System.Windows.Forms.Panel pnlCntDwn;
        private System.Windows.Forms.Label lblTimeRemaining;
        private System.Windows.Forms.Label lblMsg;
        private System.Windows.Forms.Panel pnlImg;
    }
}