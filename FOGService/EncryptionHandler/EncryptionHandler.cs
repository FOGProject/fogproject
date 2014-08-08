
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
		
		public static String decodeAES(String toDecode, String passPhrase, String ivString) {
		    byte[] key = Encoding.UTF8.GetBytes(passPhrase);
		    byte[] iv  = Encoding.UTF8.GetBytes(ivString);
		
		    try {
		        using (var rijndaelManaged = new RijndaelManaged {Key = key, IV = iv, Mode = CipherMode.CBC, Padding = PaddingMode.Zeros})
		        	
		        using (var memoryStream = 
		               new MemoryStream(Convert.FromBase64String(toDecode)))
		        using (var cryptoStream = new CryptoStream(memoryStream, rijndaelManaged.CreateDecryptor(key, iv), CryptoStreamMode.Read)) {
		            return new StreamReader(cryptoStream).ReadToEnd();
		        }
		    } catch (Exception ex) {
		        LogHandler.log(System.Reflection.Assembly.GetExecutingAssembly().GetName().Name,
		    	               "Error decoding AES");
		        LogHandler.log(System.Reflection.Assembly.GetExecutingAssembly().GetName().Name,
		    	               "ERROR: " + ex.Message);		    	
		    }
			return "";
		}	
		
	}
}