using System;
using System.Collections.Generic;
using System.Text;
using System.Security.Cryptography;
using System.IO;

namespace FOGCrypt
{
    class FOGCrypt
    {
        private String pw;

        public FOGCrypt(String passphrase)
        {
            pw = passphrase;
        }

        public String decryptHex(String hex)
        {
            return UnicodeEncoding.ASCII.GetString(decrypt(hexToByte(hex), pw));
        }

        public String encryptHex(String str)
        {
            return byteToHex(encrypt(UnicodeEncoding.ASCII.GetBytes(str), pw));
        }


        private String byteToHex(Byte[] b)
        {
            StringBuilder sb = new StringBuilder(b.Length * 2);
            foreach (byte ba in b)
            {
                sb.AppendFormat("{0:x2}", ba);
            }
            return sb.ToString();
        }

        private byte[] hexToByte(String hex)
        {
            int intChars = hex.Length;
            byte[] bytes = new byte[intChars / 2];
            for (int i = 0; i < intChars; i += 2)
            {
                bytes[i / 2] = Convert.ToByte(hex.Substring(i, 2), 16);
            }
            return bytes;
        }


        private byte[] encrypt(byte[] clearData, byte[] Key, byte[] IV)
        {

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

        private byte[] encrypt(byte[] clearData, string Password)
        {

            PasswordDeriveBytes pdb = new PasswordDeriveBytes(Password, new byte[] { 0x49, 0x76, 0x61, 0x6e, 0x20, 0x4d, 0x65, 0x64, 0x76, 0x65, 0x64, 0x65, 0x76 });
            return encrypt(clearData, pdb.GetBytes(32), pdb.GetBytes(16));
        }



        private byte[] decrypt(byte[] cipherData, byte[] Key, byte[] IV)
        {
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

        private byte[] decrypt(byte[] cipherData, string Password)
        {
            PasswordDeriveBytes pdb = new PasswordDeriveBytes(Password, new byte[] { 0x49, 0x76, 0x61, 0x6e, 0x20, 0x4d, 0x65, 0x64, 0x76, 0x65, 0x64, 0x65, 0x76 });
            return decrypt(cipherData, pdb.GetBytes(32), pdb.GetBytes(16));
        }
    }
}
