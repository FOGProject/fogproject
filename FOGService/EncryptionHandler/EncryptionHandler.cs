
using System;
using System.IO;
using System.Text;
using System.Collections.Generic;
using System.Security.Cryptography;

namespace FOG {
	/// <summary>
	/// Handle all encryption/decryption
	/// </summary>
	public static class EncryptionHandler {
		
		private const String LOG_NAME = "EncryptionHandler";
		
		//Encode a string to base64
		public static String encodeBase64(String toEncode) {
			try {
				Byte[] bytes = System.Text.ASCIIEncoding.ASCII.GetBytes(toEncode);
				return System.Convert.ToBase64String(bytes);
			} catch (Exception ex) {
				LogHandler.log(LOG_NAME, "Error encoding base64");
				LogHandler.log(LOG_NAME, "ERROR: " + ex.Message);				
			}
			return "";
		}
		
		//Decode a string from base64
		public static String decodeBase64(String toDecode) {
			try {
				Byte[] bytes = Convert.FromBase64String(toDecode);
				return Encoding.ASCII.GetString(bytes);
			} catch (Exception ex) {
				LogHandler.log(LOG_NAME, "Error decoding base64");
				LogHandler.log(LOG_NAME, "ERROR: " + ex.Message);				
			}
			return "";
		}
		
		//Decode AES256
		private static String decodeAES(String toDecode, String passKey, String ivString) {
		    //Conver the initialization vector and key into a byte array
			byte[] key = Encoding.UTF8.GetBytes(passKey);
		    byte[] iv  = Encoding.UTF8.GetBytes(ivString);
		
		    try {
		        using (var rijndaelManaged = new RijndaelManaged {Key = key, IV = iv, Mode = CipherMode.CBC, Padding = PaddingMode.Zeros})
		        	
		        using (var memoryStream = 
		               new MemoryStream(Convert.FromBase64String(toDecode)))
		        using (var cryptoStream = new CryptoStream(memoryStream, rijndaelManaged.CreateDecryptor(key, iv), CryptoStreamMode.Read)) {
		        	return new StreamReader(cryptoStream).ReadToEnd().Replace("\0", String.Empty).Trim();
		        }
		    } catch (Exception ex) {
		        LogHandler.log(LOG_NAME, "Error decoding AES");
		        LogHandler.log(LOG_NAME, "ERROR: " + ex.Message);		    	
		    }
			return "";
		}
		
		public static String decodeAESResponse(String response, String passKey) {
			LogHandler.log(LOG_NAME, "Attempting to decrypt response");
			//The first set of 15 characters is the initialization vector, the rest is the encrypted message
			if(response.Length > 16) {
				return EncryptionHandler.decodeAES(response.Substring(16), passKey, response.Substring(0,16)).Trim();
			} else {
				LogHandler.log(LOG_NAME, "Unable to decrypt response");
				LogHandler.log(LOG_NAME, "ERROR: Encrypted data is corrupt");
			}
			return "";
		}		
		
	}
}