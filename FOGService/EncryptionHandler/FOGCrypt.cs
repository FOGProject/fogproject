
using System;
using System.Security.Cryptography;
using System.Text;
using System.IO;

namespace FOG {
	/// <summary>
	/// FOGCrypt encryption method
	/// </summary>
	public static class FOGCrypt {
				
		public static String decrypt(String toDecode, String passPhrase) {
			return UnicodeEncoding.ASCII.GetString(decrypt(hexToBytes(toDecode), passPhrase));
		}
		
		public static String encrypt(String toEncode, String passPhrase) {
			return bytesToHex(encrypt(UnicodeEncoding.ASCII.GetBytes(toEncode), passPhrase));
		}
		
		private static String bytesToHex(Byte[] bytes) {
			StringBuilder stringBuilder = new StringBuilder(bytes.Length * 2);
			foreach (byte convertedByte in bytes) {
				stringBuilder.AppendFormat("{0:x2}", convertedByte);
			}
			return stringBuilder.ToString();
		}
	
		private static byte[] hexToBytes(String hex) {
			int intChars = hex.Length;
			byte[] bytes = new byte[intChars / 2];
			
			for (int i = 0; i < intChars; i += 2) {
				bytes[i / 2] = Convert.ToByte(hex.Substring(i, 2), 16);
			}
			
			return bytes;
		}
	
	
		private static byte[] encrypt(byte[] clearData, byte[] Key, byte[] IV){
			MemoryStream ms = new MemoryStream();
			Rijndael alg = Rijndael.Create();
			alg.Key = Key;
			alg.IV = IV;
	
			CryptoStream cs = new CryptoStream(ms, alg.CreateEncryptor(), CryptoStreamMode.Write);
	
			cs.Write(clearData, 0, clearData.Length);
			cs.Close();
	
			byte[] encryptedData = ms.ToArray();
	
			return encryptedData;
		}
	
		private static byte[] encrypt(byte[] clearData, string password){
			PasswordDeriveBytes pdb = new PasswordDeriveBytes(password, new byte[] { 0x49, 0x76, 0x61, 0x6e, 0x20, 0x4d, 0x65, 0x64, 0x76, 0x65, 0x64, 0x65, 0x76 });
			
			return encrypt(clearData, pdb.GetBytes(32), pdb.GetBytes(16));
		}
	
	
	
		private static byte[] decrypt(byte[] cipherData, byte[] Key, byte[] IV) {
			MemoryStream ms = new MemoryStream();
			Rijndael alg = Rijndael.Create();
	
			alg.Key = Key;
			alg.IV = IV;
	
			CryptoStream cs = new CryptoStream(ms, alg.CreateDecryptor(), CryptoStreamMode.Write);
	
			cs.Write(cipherData, 0, cipherData.Length);
			cs.Close();
	
			byte[] decryptedData = ms.ToArray();
			
			return decryptedData;
		}
	
		private static byte[] decrypt(byte[] cipherData, string password) {
			PasswordDeriveBytes pdb = new PasswordDeriveBytes(password, new byte[] { 0x49, 0x76, 0x61, 0x6e, 0x20, 0x4d, 0x65, 0x64, 0x76, 0x65, 0x64, 0x65, 0x76 });
			
			return decrypt(cipherData, pdb.GetBytes(32), pdb.GetBytes(16));
		}
	}
}
