
using System;
using System.Collections;
using System.Collections.Generic;
using System.Runtime.InteropServices;

namespace FOG {
	/// <summary>
	///Change the resolution of the display
	/// </summary>
	public class DisplayChanger {
		[StructLayout(LayoutKind.Sequential)]
		public struct DEVMODE1 {
			[MarshalAs(UnmanagedType.ByValTStr, SizeConst = 32)]
			public string dmDeviceName;
			public short dmSpecVersion;
			public short dmDriverVersion;
			public short dmSize;
			public short dmDriverExtra;
			public int dmFields;
		
			public short dmOrientation;
			public short dmPaperSize;
			public short dmPaperLength;
			public short dmPaperWidth;
		
			public short dmScale;
			public short dmCopies;
			public short dmDefaultSource;
			public short dmPrintQuality;
			public short dmColor;
			public short dmDuplex;
			public short dmYResolution;
			public short dmTTOption;
			public short dmCollate;
			[MarshalAs(UnmanagedType.ByValTStr, SizeConst = 32)]
			public string dmFormName;
			public short dmLogPixels;
			public short dmBitsPerPel;
			public int dmPelsWidth;
			public int dmPelsHeight;
		
			public int dmDisplayFlags;
			public int dmDisplayFrequency;
		
			public int dmICMMethod;
			public int dmICMIntent;
			public int dmMediaType;
			public int dmDitherType;
			public int dmReserved1;
			public int dmReserved2;
		
			public int dmPanningWidth;
			public int dmPanningHeight;
		};
		
		
		public DEVMODE1[] getSupportedModes() {
			ArrayList alModes = new ArrayList();
			int intRet = 1;
			int intNum = 0;
	
			while (intRet != 0) {
				DEVMODE1 dm = new DEVMODE1();
				dm.dmDeviceName = new String(new char[32]);
				dm.dmFormName = new String(new char[32]);
				dm.dmSize = (short)Marshal.SizeOf(dm);
	
				intRet = UnmanagedWin32.EnumDisplaySettings(null, intNum++, ref dm);
				if (intRet != 0) {
					alModes.Add(dm);
				}
			}
			return (DEVMODE1[])(alModes.ToArray(typeof(DEVMODE1)));
		}
	
		public Boolean changeDisplaySettings(int X, int Y, int refresh, int orientation) {
			DEVMODE1[] arDM = getSupportedModes();
			
			if (arDM != null && arDM.Length > 0) {
				
				for (int i = 0; i < arDM.Length; i++) {
					if (arDM[i].dmPelsWidth == X && arDM[i].dmPelsHeight == Y && arDM[i].dmDisplayFrequency == refresh && arDM[i].dmOrientation == orientation) {
						
						DEVMODE1 dmset = new DEVMODE1();
						dmset.dmDeviceName = new String(new char[32]);
						dmset.dmFormName = new String(new char[32]);
						dmset.dmSize = (short)Marshal.SizeOf(dmset);
						if (UnmanagedWin32.EnumDisplaySettings(null, UnmanagedWin32.ENUM_CURRENT_SETTINGS, ref dmset) != 0) {
							
							dmset.dmPelsWidth = X;
							dmset.dmPelsHeight = Y;
							dmset.dmOrientation = (short)orientation;
							dmset.dmDisplayFrequency = refresh;
							
							int intTest = UnmanagedWin32.ChangeDisplaySettings(ref dmset, UnmanagedWin32.CDS_TEST);
							
							if (intTest != UnmanagedWin32.DISP_CHANGE_FAILED) {
								
								intTest = UnmanagedWin32.ChangeDisplaySettings(ref dmset, UnmanagedWin32.CDS_UPDATEREGISTRY);
								if (intTest == UnmanagedWin32.DISP_CHANGE_SUCCESSFUL)
									return true;
								else if (intTest == UnmanagedWin32.DISP_CHANGE_RESTART)
									return true;
							}
							
						}
						
					}
				}
			}
			
			return false;
		}
	}
}
