
using System.Windows.Forms;
using System.Drawing;
using System.Collections.Generic;
using Microsoft.Build.BuildEngine;

namespace Compiler
{
	partial class MainForm {
		/// <summary>
		/// Designer variable used to keep track of non-visual components.
		/// </summary>
		private System.ComponentModel.IContainer components = null;
		
		/// <summary>
		/// Disposes resources used by the form.
		/// </summary>
		/// <param name="disposing">true if managed resources should be disposed; otherwise, false.</param>
		protected override void Dispose(bool disposing)
		{
			if (disposing) {
				if (components != null) {
					components.Dispose();
				}
			}
			base.Dispose(disposing);
		}
		
		/// <summary>
		/// This method is required for Windows Forms designer support.
		/// Do not change the method contents inside the source code editor. The Forms designer might
		/// not be able to load this method if it was changed manually.
		/// </summary>
		private void InitializeComponent()
		{
			System.ComponentModel.ComponentResourceManager resources = new System.ComponentModel.ComponentResourceManager(typeof(MainForm));
			this.compileButton = new System.Windows.Forms.Button();
			this.allTrafficTextBox = new System.Windows.Forms.TextBox();
			this.adPassTextbox = new System.Windows.Forms.TextBox();
			this.allTrafficPasskeyLabel = new System.Windows.Forms.Label();
			this.adPassLabel = new System.Windows.Forms.Label();
			this.organizationNameTextBox = new System.Windows.Forms.TextBox();
			this.organizationNameLabel = new System.Windows.Forms.Label();
			this.organizationLogoLabel = new System.Windows.Forms.Label();
			this.organizationLogoBox = new System.Windows.Forms.PictureBox();
			this.updateOrganizationLogoButton = new System.Windows.Forms.Button();
			this.compilingOutput = new System.Windows.Forms.RichTextBox();
			this.serviceLocationLabel = new System.Windows.Forms.Label();
			this.slnLocationLabel = new System.Windows.Forms.Label();
			this.updateSLNButton = new System.Windows.Forms.Button();
			this.slnOpenFileDialog = new System.Windows.Forms.OpenFileDialog();
			this.iconOpenFileDialog = new System.Windows.Forms.OpenFileDialog();
			((System.ComponentModel.ISupportInitialize)(this.organizationLogoBox)).BeginInit();
			this.SuspendLayout();
			// 
			// compileButton
			// 
			this.compileButton.Location = new System.Drawing.Point(12, 157);
			this.compileButton.Name = "compileButton";
			this.compileButton.Size = new System.Drawing.Size(508, 23);
			this.compileButton.TabIndex = 0;
			this.compileButton.Text = "Compile";
			this.compileButton.UseVisualStyleBackColor = true;
			this.compileButton.Click += new System.EventHandler(this.CompileButtonClick);
			// 
			// allTrafficTextBox
			// 
			this.allTrafficTextBox.Location = new System.Drawing.Point(142, 36);
			this.allTrafficTextBox.Name = "allTrafficTextBox";
			this.allTrafficTextBox.Size = new System.Drawing.Size(378, 20);
			this.allTrafficTextBox.TabIndex = 1;
			// 
			// adPassTextbox
			// 
			this.adPassTextbox.Location = new System.Drawing.Point(142, 62);
			this.adPassTextbox.Name = "adPassTextbox";
			this.adPassTextbox.Size = new System.Drawing.Size(378, 20);
			this.adPassTextbox.TabIndex = 2;
			// 
			// allTrafficPasskeyLabel
			// 
			this.allTrafficPasskeyLabel.Location = new System.Drawing.Point(12, 39);
			this.allTrafficPasskeyLabel.Name = "allTrafficPasskeyLabel";
			this.allTrafficPasskeyLabel.Size = new System.Drawing.Size(123, 23);
			this.allTrafficPasskeyLabel.TabIndex = 3;
			this.allTrafficPasskeyLabel.Text = "All traffic passkey: ";
			// 
			// adPassLabel
			// 
			this.adPassLabel.Location = new System.Drawing.Point(12, 65);
			this.adPassLabel.Name = "adPassLabel";
			this.adPassLabel.Size = new System.Drawing.Size(123, 23);
			this.adPassLabel.TabIndex = 4;
			this.adPassLabel.Text = "AD password passkey: ";
			// 
			// organizationNameTextBox
			// 
			this.organizationNameTextBox.Location = new System.Drawing.Point(142, 92);
			this.organizationNameTextBox.Name = "organizationNameTextBox";
			this.organizationNameTextBox.Size = new System.Drawing.Size(378, 20);
			this.organizationNameTextBox.TabIndex = 5;
			// 
			// organizationNameLabel
			// 
			this.organizationNameLabel.Location = new System.Drawing.Point(12, 92);
			this.organizationNameLabel.Name = "organizationNameLabel";
			this.organizationNameLabel.Size = new System.Drawing.Size(123, 23);
			this.organizationNameLabel.TabIndex = 6;
			this.organizationNameLabel.Text = "Organization\'s name:";
			// 
			// organizationLogoLabel
			// 
			this.organizationLogoLabel.Location = new System.Drawing.Point(12, 128);
			this.organizationLogoLabel.Name = "organizationLogoLabel";
			this.organizationLogoLabel.Size = new System.Drawing.Size(122, 23);
			this.organizationLogoLabel.TabIndex = 7;
			this.organizationLogoLabel.Text = "Organization\'s logo:";
			// 
			// organizationLogoBox
			// 
			this.organizationLogoBox.Location = new System.Drawing.Point(142, 119);
			this.organizationLogoBox.Name = "organizationLogoBox";
			this.organizationLogoBox.Size = new System.Drawing.Size(32, 32);
			this.organizationLogoBox.SizeMode = System.Windows.Forms.PictureBoxSizeMode.StretchImage;
			this.organizationLogoBox.TabIndex = 8;
			this.organizationLogoBox.TabStop = false;
			// 
			// updateOrganizationLogoButton
			// 
			this.updateOrganizationLogoButton.Location = new System.Drawing.Point(180, 123);
			this.updateOrganizationLogoButton.Name = "updateOrganizationLogoButton";
			this.updateOrganizationLogoButton.Size = new System.Drawing.Size(37, 23);
			this.updateOrganizationLogoButton.TabIndex = 9;
			this.updateOrganizationLogoButton.Text = "...";
			this.updateOrganizationLogoButton.UseVisualStyleBackColor = true;
			this.updateOrganizationLogoButton.Click += new System.EventHandler(this.UpdateOrganizationLogoButtonClick);
			// 
			// compilingOutput
			// 
			this.compilingOutput.Location = new System.Drawing.Point(13, 187);
			this.compilingOutput.Name = "compilingOutput";
			this.compilingOutput.ReadOnly = true;
			this.compilingOutput.Size = new System.Drawing.Size(507, 80);
			this.compilingOutput.TabIndex = 10;
			this.compilingOutput.Text = "";
			// 
			// serviceLocationLabel
			// 
			this.serviceLocationLabel.Location = new System.Drawing.Point(13, 9);
			this.serviceLocationLabel.Name = "serviceLocationLabel";
			this.serviceLocationLabel.Size = new System.Drawing.Size(121, 23);
			this.serviceLocationLabel.TabIndex = 11;
			this.serviceLocationLabel.Text = "Service Location:";
			// 
			// slnLocationLabel
			// 
			this.slnLocationLabel.Font = new System.Drawing.Font("Microsoft Sans Serif", 8.25F, System.Drawing.FontStyle.Italic, System.Drawing.GraphicsUnit.Point, ((byte)(0)));
			this.slnLocationLabel.Location = new System.Drawing.Point(141, 9);
			this.slnLocationLabel.Name = "slnLocationLabel";
			this.slnLocationLabel.Size = new System.Drawing.Size(336, 23);
			this.slnLocationLabel.TabIndex = 12;
			this.slnLocationLabel.TextAlign = System.Drawing.ContentAlignment.MiddleLeft;
			// 
			// updateSLNButton
			// 
			this.updateSLNButton.Location = new System.Drawing.Point(483, 9);
			this.updateSLNButton.Name = "updateSLNButton";
			this.updateSLNButton.Size = new System.Drawing.Size(37, 23);
			this.updateSLNButton.TabIndex = 13;
			this.updateSLNButton.Text = "...";
			this.updateSLNButton.UseVisualStyleBackColor = true;
			this.updateSLNButton.Click += new System.EventHandler(this.UpdateSLNButtonClick);
			// 
			// slnOpenFileDialog
			// 
			this.slnOpenFileDialog.FileName = "FOGService.sln";
			this.slnOpenFileDialog.Filter = "C# Projects|*.sln";
			// 
			// iconOpenFileDialog
			// 
			this.iconOpenFileDialog.FileName = "icon.ico";
			this.iconOpenFileDialog.Filter = "Icons|*.ico";
			// 
			// MainForm
			// 
			this.AutoScaleDimensions = new System.Drawing.SizeF(6F, 13F);
			this.AutoScaleMode = System.Windows.Forms.AutoScaleMode.Font;
			this.ClientSize = new System.Drawing.Size(527, 298);
			this.Controls.Add(this.updateSLNButton);
			this.Controls.Add(this.slnLocationLabel);
			this.Controls.Add(this.serviceLocationLabel);
			this.Controls.Add(this.compilingOutput);
			this.Controls.Add(this.updateOrganizationLogoButton);
			this.Controls.Add(this.organizationLogoBox);
			this.Controls.Add(this.organizationLogoLabel);
			this.Controls.Add(this.organizationNameLabel);
			this.Controls.Add(this.organizationNameTextBox);
			this.Controls.Add(this.adPassLabel);
			this.Controls.Add(this.allTrafficPasskeyLabel);
			this.Controls.Add(this.adPassTextbox);
			this.Controls.Add(this.allTrafficTextBox);
			this.Controls.Add(this.compileButton);
			this.FormBorderStyle = System.Windows.Forms.FormBorderStyle.FixedSingle;
			this.Icon = ((System.Drawing.Icon)(resources.GetObject("$this.Icon")));
			this.MaximizeBox = false;
			this.Name = "MainForm";
			this.Text = "FOG Service Compiler";
			((System.ComponentModel.ISupportInitialize)(this.organizationLogoBox)).EndInit();
			this.ResumeLayout(false);
			this.PerformLayout();
		}
		private System.Windows.Forms.OpenFileDialog iconOpenFileDialog;
		private System.Windows.Forms.OpenFileDialog slnOpenFileDialog;
		private System.Windows.Forms.Button updateSLNButton;
		private System.Windows.Forms.Label slnLocationLabel;
		private System.Windows.Forms.Label serviceLocationLabel;
		private System.Windows.Forms.RichTextBox compilingOutput;
		private System.Windows.Forms.Button updateOrganizationLogoButton;
		private System.Windows.Forms.PictureBox organizationLogoBox;
		private System.Windows.Forms.Label organizationLogoLabel;
		private System.Windows.Forms.Label organizationNameLabel;
		private System.Windows.Forms.TextBox organizationNameTextBox;
		private System.Windows.Forms.Label adPassLabel;
		private System.Windows.Forms.Label allTrafficPasskeyLabel;
		private System.Windows.Forms.TextBox adPassTextbox;
		private System.Windows.Forms.TextBox allTrafficTextBox;
		private System.Windows.Forms.Button compileButton;
		
		void CompileButtonClick(object sender, System.EventArgs e) {
			Engine engine = new Engine();
			engine.BinPath = @"C:\Windows\Microsoft.NET\Framework\v3.5";
			BuildPropertyGroup properties = new BuildPropertyGroup();
			properties.SetProperty("Configuration", "Release");
			properties.SetProperty("Platform", "Any CPU");
			bool ok = engine.BuildProjectFile(this.slnLocationLabel.Text, new string[] { "rebuild" }, properties);
			
			
		}	
		
		void UpdateSLNButtonClick(object sender, System.EventArgs e){

			if(this.slnOpenFileDialog.ShowDialog().Equals(DialogResult.OK))
				this.slnLocationLabel.Text = this.slnOpenFileDialog.FileName;

		}
		
		void UpdateOrganizationLogoButtonClick(object sender, System.EventArgs e) {
			if(iconOpenFileDialog.ShowDialog().Equals(DialogResult.OK))
				this.organizationLogoBox.Image = Bitmap.FromHicon(new Icon(iconOpenFileDialog.FileName).Handle);
		}
	}
}
