
using System;
using System.Text;
using System.Collections.Generic;

namespace FOG {
	/// <summary>
	/// Handle all encryption/decryption
	/// </summary>
	public static class EncryptionHandler {
		
		public static String encodeBase64(String toEncode) {
			try {
				Byte[] bytes = System.Text.ASCIIEncoding.ASCII.GetBytes(toEncode);
				return System.Convert.ToBase64String(bytes);
			} catch (Exception ex) {
				LogHandler.log(System.Reflection.Assembly.GetExecutingAssembly().GetName().Name,
				                    "Error encoding base64");
				LogHandler.log(System.Reflection.Assembly.GetExecutingAssembly().GetName().Name,
				                    "ERROR: " + ex.Message);				
			}
			return "";
		}
		
		public static String decodeBase64(String toDecode) {
			try {
				Byte[] bytes = Convert.FromBase64String(toDecode);
				return Encoding.ASCII.GetString(bytes);
			} catch (Exception ex) {
				LogHandler.log(System.Reflection.Assembly.GetExecutingAssembly().GetName().Name,
				                    "Error decoding base64");
				LogHandler.log(System.Reflection.Assembly.GetExecutingAssembly().GetName().Name,
				                    "ERROR: " + ex.Message);				
			}
			return "";
		}
		
		public static String decodeFOGCrypt(String toDecode, String passPhrase) {
			LogHandler.log(System.Reflection.Assembly.GetExecutingAssembly().GetName().Name + ":FOGCrypt",
				                   "Decoding...");
			try {
				return FOGCrypt.decrypt(toDecode, passPhrase);
			} catch (Exception ex) {
				LogHandler.log(System.Reflection.Assembly.GetExecutingAssembly().GetName().Name + ":FOGCrypt",
				                   "Error decoding");
				LogHandler.log(System.Reflection.Assembly.GetExecutingAssembly().GetName().Name + ":FOGCrypt",
				                    "ERROR: " + ex.Message);
				
			}
			return "";
		}
		
		public static String encodeFOGCrypt(String toEncode, String passPhrase) {
			LogHandler.log(System.Reflection.Assembly.GetExecutingAssembly().GetName().Name + ":FOGCrypt",
				                   "Encoding...");
			try {
				return FOGCrypt.encrypt(toEncode, passPhrase);
			} catch (Exception ex) {
				LogHandler.log(System.Reflection.Assembly.GetExecutingAssembly().GetName().Name + ":FOGCrypt",
				                   "Error encoding");
				LogHandler.log(System.Reflection.Assembly.GetExecutingAssembly().GetName().Name + ":FOGCrypt",
				                    "ERROR: " + ex.Message);
				
			}	
			return "";
		}
		
	}
}