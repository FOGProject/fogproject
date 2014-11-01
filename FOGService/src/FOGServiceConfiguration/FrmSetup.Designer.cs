namespace FOG
{
    partial class FrmSetup
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
            System.ComponentModel.ComponentResourceManager resources = new System.ComponentModel.ComponentResourceManager(typeof(FrmSetup));
            this.pnlTop = new System.Windows.Forms.Panel();
            this.pbTitle = new System.Windows.Forms.PictureBox();
            this.pnlIP = new System.Windows.Forms.Panel();
            this.pnlServices = new System.Windows.Forms.FlowLayoutPanel();
            this.btnSave = new System.Windows.Forms.Button();
            this.lblSubServices = new System.Windows.Forms.Label();
            this.txtIP = new System.Windows.Forms.TextBox();
            this.lblIPMessage = new System.Windows.Forms.Label();
            this.pnlDone = new System.Windows.Forms.Panel();
            this.btnDone = new System.Windows.Forms.Button();
            this.lblDone = new System.Windows.Forms.Label();
            this.pnlTop.SuspendLayout();
            ((System.ComponentModel.ISupportInitialize)(this.pbTitle)).BeginInit();
            this.pnlIP.SuspendLayout();
            this.pnlDone.SuspendLayout();
            this.SuspendLayout();
            // 
            // pnlTop
            // 
            this.pnlTop.BackColor = System.Drawing.Color.White;
            this.pnlTop.BorderStyle = System.Windows.Forms.BorderStyle.FixedSingle;
            this.pnlTop.Controls.Add(this.pbTitle);
            this.pnlTop.Dock = System.Windows.Forms.DockStyle.Top;
            this.pnlTop.Location = new System.Drawing.Point(0, 0);
            this.pnlTop.Name = "pnlTop";
            this.pnlTop.Size = new System.Drawing.Size(674, 87);
            this.pnlTop.TabIndex = 0;
            // 
            // pbTitle
            // 
            this.pbTitle.Image = ((System.Drawing.Image)(resources.GetObject("pbTitle.Image")));
            this.pbTitle.Location = new System.Drawing.Point(367, 3);
            this.pbTitle.Name = "pbTitle";
            this.pbTitle.Size = new System.Drawing.Size(304, 81);
            this.pbTitle.TabIndex = 0;
            this.pbTitle.TabStop = false;
            // 
            // pnlIP
            // 
            this.pnlIP.Controls.Add(this.pnlServices);
            this.pnlIP.Controls.Add(this.btnSave);
            this.pnlIP.Controls.Add(this.lblSubServices);
            this.pnlIP.Controls.Add(this.txtIP);
            this.pnlIP.Controls.Add(this.lblIPMessage);
            this.pnlIP.Location = new System.Drawing.Point(0, 87);
            this.pnlIP.Name = "pnlIP";
            this.pnlIP.Size = new System.Drawing.Size(628, 381);
            this.pnlIP.TabIndex = 1;
            // 
            // pnlServices
            // 
            this.pnlServices.AutoScroll = true;
            this.pnlServices.BackColor = System.Drawing.Color.Transparent;
            this.pnlServices.Location = new System.Drawing.Point(68, 107);
            this.pnlServices.Name = "pnlServices";
            this.pnlServices.Size = new System.Drawing.Size(592, 220);
            this.pnlServices.TabIndex = 5;
            // 
            // btnSave
            // 
            this.btnSave.Location = new System.Drawing.Point(552, 333);
            this.btnSave.Name = "btnSave";
            this.btnSave.Size = new System.Drawing.Size(109, 37);
            this.btnSave.TabIndex = 4;
            this.btnSave.Text = "Save Changes";
            this.btnSave.UseVisualStyleBackColor = true;
            this.btnSave.Click += new System.EventHandler(this.btnSave_Click);
            // 
            // lblSubServices
            // 
            this.lblSubServices.Location = new System.Drawing.Point(15, 79);
            this.lblSubServices.Name = "lblSubServices";
            this.lblSubServices.Size = new System.Drawing.Size(389, 25);
            this.lblSubServices.TabIndex = 2;
            this.lblSubServices.Text = "What sub services would you like to run on this client?";
            // 
            // txtIP
            // 
            this.txtIP.Location = new System.Drawing.Point(72, 43);
            this.txtIP.Name = "txtIP";
            this.txtIP.Size = new System.Drawing.Size(167, 22);
            this.txtIP.TabIndex = 1;
            // 
            // lblIPMessage
            // 
            this.lblIPMessage.Location = new System.Drawing.Point(15, 15);
            this.lblIPMessage.Name = "lblIPMessage";
            this.lblIPMessage.Size = new System.Drawing.Size(647, 25);
            this.lblIPMessage.TabIndex = 0;
            this.lblIPMessage.Text = "What is the IP address or host name of the FOG Web Server? (Blank for default \"fo" +
                "gserver\")";
            // 
            // pnlDone
            // 
            this.pnlDone.Controls.Add(this.btnDone);
            this.pnlDone.Controls.Add(this.lblDone);
            this.pnlDone.Location = new System.Drawing.Point(18, 87);
            this.pnlDone.Name = "pnlDone";
            this.pnlDone.Size = new System.Drawing.Size(654, 381);
            this.pnlDone.TabIndex = 2;
            this.pnlDone.Visible = false;
            // 
            // btnDone
            // 
            this.btnDone.Location = new System.Drawing.Point(535, 333);
            this.btnDone.Name = "btnDone";
            this.btnDone.Size = new System.Drawing.Size(109, 37);
            this.btnDone.TabIndex = 5;
            this.btnDone.Text = "Done";
            this.btnDone.UseVisualStyleBackColor = true;
            this.btnDone.Click += new System.EventHandler(this.btnDone_Click);
            // 
            // lblDone
            // 
            this.lblDone.BackColor = System.Drawing.Color.Transparent;
            this.lblDone.Location = new System.Drawing.Point(21, 22);
            this.lblDone.Name = "lblDone";
            this.lblDone.Size = new System.Drawing.Size(610, 82);
            this.lblDone.TabIndex = 0;
            this.lblDone.Text = "The FOG Service has been configured, in order for the service modules to load cor" +
                "rectly, you MUST restart this computer.  ";
            // 
            // FrmSetup
            // 
            this.AutoScaleDimensions = new System.Drawing.SizeF(7F, 16F);
            this.AutoScaleMode = System.Windows.Forms.AutoScaleMode.Font;
            this.ClientSize = new System.Drawing.Size(674, 468);
            this.Controls.Add(this.pnlDone);
            this.Controls.Add(this.pnlIP);
            this.Controls.Add(this.pnlTop);
            this.DoubleBuffered = true;
            this.Font = new System.Drawing.Font("Arial", 9.75F, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, ((byte)(0)));
            this.FormBorderStyle = System.Windows.Forms.FormBorderStyle.FixedSingle;
            this.Margin = new System.Windows.Forms.Padding(3, 4, 3, 4);
            this.MaximizeBox = false;
            this.Name = "FrmSetup";
            this.ShowIcon = false;
            this.StartPosition = System.Windows.Forms.FormStartPosition.CenterScreen;
            this.Text = "FOG Service :: Configuration Tool";
            this.TopMost = true;
            this.Load += new System.EventHandler(this.FrmSetup_Load);
            this.pnlTop.ResumeLayout(false);
            ((System.ComponentModel.ISupportInitialize)(this.pbTitle)).EndInit();
            this.pnlIP.ResumeLayout(false);
            this.pnlIP.PerformLayout();
            this.pnlDone.ResumeLayout(false);
            this.ResumeLayout(false);

        }

        #endregion

        private System.Windows.Forms.Panel pnlTop;
        private System.Windows.Forms.PictureBox pbTitle;
        private System.Windows.Forms.Panel pnlIP;
        private System.Windows.Forms.TextBox txtIP;
        private System.Windows.Forms.Label lblIPMessage;
        private System.Windows.Forms.Label lblSubServices;
        private System.Windows.Forms.Button btnSave;
        private System.Windows.Forms.FlowLayoutPanel pnlServices;
        private System.Windows.Forms.Panel pnlDone;
        private System.Windows.Forms.Label lblDone;
        private System.Windows.Forms.Button btnDone;
    }
}

