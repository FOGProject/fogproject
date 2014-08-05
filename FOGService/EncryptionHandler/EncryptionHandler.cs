
using System;
using System.Text;
using System.Collections.Generic;

namespace FOG {
	/// <summary>
	/// Handle all encryption/decryption
	/// </summary>
	public class EncryptionHandler {
		
		//Define handler
		LogHandler logHandler;
		
		public EncryptionHandler(LogHandler logHandler) {
			this.logHandler = logHandler;
		}
		
		public String encodeBase64(String toEncode) {
			try {
				Byte[] bytes = System.Text.ASCIIEncoding.ASCII.GetBytes(toEncode);
				return System.Convert.ToBase64String(bytes);
			} catch (Exception ex) {
				this.logHandler.log(System.Reflection.Assembly.GetExecutingAssembly().GetName().Name,
				                    "Error encoding base64");
				this.logHandler.log(System.Reflection.Assembly.GetExecutingAssembly().GetName().Name,
				                    "ERROR: " + ex.Message);				
			}
			return "";
		}
		
		public String decodeBase64(String toDecode) {
			try {
				Byte[] bytes = Convert.FromBase64String(toDecode);
				return Encoding.ASCII.GetString(bytes);
			} catch (Exception ex) {
				this.logHandler.log(System.Reflection.Assembly.GetExecutingAssembly().GetName().Name,
				                    "Error encoding base64");
				this.logHandler.log(System.Reflection.Assembly.GetExecutingAssembly().GetName().Name,
				                    "ERROR: " + ex.Message);				
			}
			return "";
		}
		
	}
}