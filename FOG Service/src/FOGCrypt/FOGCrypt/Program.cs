using System;
using System.Collections.Generic;
using System.Text;
using IniReaderObj;

namespace FOGCrypt
{
    class Program
    {
        public Program(String strData)
        {
            IniReader ini = new IniReader( @"./etc/config.ini" );
            if ( ini != null && ini.isFileOk() )
            {
                String passkey = ini.readSetting("main", "passkey");
                Console.WriteLine();
                Console.WriteLine("  Input string: " + strData  );
                Console.WriteLine("  Passkey:      " + passkey);
                FOGCrypt c = new FOGCrypt(passkey);
                Console.WriteLine("  Output:       " + c.encryptHex(strData));
                Console.WriteLine();
            }
            else
            {
                Console.WriteLine( "Error:  INI File error!" );
            }
        }

        static void Main(string[] args)
        {
            if (args != null)
            {
                if (args.Length == 1)
                {
                    new Program(args[0].Trim());
                }
                else
                {
                    displayHelp();
                }
            }
        }

        private static void displayHelp()
        {
            Console.WriteLine();
            Console.WriteLine(  "  Usage:" );
            Console.WriteLine(  "  FOGCrypt.exe stringtoencrypt");
            
        }
    }
}
