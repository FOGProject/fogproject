
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
		FOGCrypt fogCrypt;
		
		public EncryptionHandler(LogHandler logHandler) {
			this.logHandler = logHandler;
			this.fogCrypt = new FOGCrypt();
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
				                    "Error decoding base64");
				this.logHandler.log(System.Reflection.Assembly.GetExecutingAssembly().GetName().Name,
				                    "ERROR: " + ex.Message);				
			}
			return "";
		}
		
		public String decodeFOGCrypt(String toDecode, String passPhrase) {
			this.logHandler.log(System.Reflection.Assembly.GetExecutingAssembly().GetName().Name + ":FOGCrypt",
				                   "Decoding...");
			try {
				return this.fogCrypt.decrypt(toDecode, passPhrase);
			} catch (Exception ex) {
				this.logHandler.log(System.Reflection.Assembly.GetExecutingAssembly().GetName().Name + ":FOGCrypt",
				                   "Error decoding");
				this.logHandler.log(System.Reflection.Assembly.GetExecutingAssembly().GetName().Name + ":FOGCrypt",
				                    "ERROR: " + ex.Message);
				
			}
			return "";
		}
		
		public String encodeFOGCrypt(String toEncode, String passPhrase) {
			this.logHandler.log(System.Reflection.Assembly.GetExecutingAssembly().GetName().Name + ":FOGCrypt",
				                   "Encoding...");
			try {
				return this.fogCrypt.encrypt(toEncode, passPhrase);
			} catch (Exception ex) {
				this.logHandler.log(System.Reflection.Assembly.GetExecutingAssembly().GetName().Name + ":FOGCrypt",
				                   "Error encoding");
				this.logHandler.log(System.Reflection.Assembly.GetExecutingAssembly().GetName().Name + ":FOGCrypt",
				                    "ERROR: " + ex.Message);
				
			}	
			return "";
		}
		
	}
}