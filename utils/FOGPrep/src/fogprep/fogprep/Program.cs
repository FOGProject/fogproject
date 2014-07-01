using System;
using System.Collections.Generic;
using System.Text;
using Microsoft.Win32;

namespace fogprep
{
    class FogPrep
    {
        private const string SUBKEY = @"SYSTEM\MountedDevices";

        private Boolean verbose, silent, showHelp;
        private static int exitCode = 0;

        public FogPrep(string[] args)
        {
            parseArgs(args);

            verboseOut("Checking Operating System version..." );
            if (checkOS())
            {
                if (showHelp)
                    printUsage();
                else
                {
                    try
                    {
                        if (prepComputer())
                        {
                            if (!silent)
                                Console.WriteLine("Task complete.");
                            else
                                verboseOut("Task complete.");
                        }
                        else
                        {
                            Console.WriteLine("Process failed.");
                            exitCode = 1;
                        }
                    }
                    catch (Exception e)
                    {
                        Console.WriteLine("An exception occurred");
                        Console.WriteLine("Message: " + e.Message);
                        Console.WriteLine("Stack Trace: ");
                        Console.WriteLine(e.StackTrace);
                    }
                }
            }
            else
            {
                Console.WriteLine("This application is written for Windows version 7 only (6.1.X)");
                exitCode = 1;
            }

            try
            {
                System.Threading.Thread.Sleep(5000);
            }
            catch { }
        }

        private Boolean checkOS()
        {
            verboseOut("OS Version: " + System.Environment.OSVersion.Version.Major + "." + System.Environment.OSVersion.Version.Minor);
            return (System.Environment.OSVersion.Version.Major == 6 && System.Environment.OSVersion.Version.Minor == 1);
        }

        private void verboseOut(String output)
        {
            if (verbose)
                Console.WriteLine(output);
        }

        private Boolean prepComputer()
        {
            Boolean proceed = false;
            if (!silent)
            {
                Console.WriteLine();
                Console.WriteLine("If you restart this computer and boot to Windows after running this command, you will need to run it again.");
                Console.WriteLine();
                Console.Write("Are you sure you wish to prepare this computer for FOG upload? (y/n): ");
                String response = Console.ReadLine();
                if (response.ToLower().Trim().CompareTo("y") == 0)
                    proceed = true;
            }
            else
                proceed = true;

            verboseOut("Verbose output requested.");
            if (proceed)
            {
                verboseOut("Process confirmed.");

                if ( ! silent )
                    Console.WriteLine("Updating Registry...");

                verboseOut("Opening LocalMachine...");
                RegistryKey key = Registry.LocalMachine;
                verboseOut("Opening subkey: " + SUBKEY);
                key = key.OpenSubKey(SUBKEY,true);
                if (key != null)
                {
                    String[] subKey = key.GetValueNames();
                    if (subKey != null)
                    {
                        for (int i = 0; i < subKey.Length; i++)
                        {
                            String name = subKey[i];
                            if ( name != null )
                            {
                                verboseOut("Removing: " + subKey[i]);
                                key.DeleteValue(name);
                            }
                        }
                        return true;
                    }
                }
            }
            else
                verboseOut("process not confirmed, exiting...");
            return false;
        }

        private void parseArgs(string[] args)
        {
            verbose = false;
            silent = false;
            showHelp = false;
            if (args != null)
            {
                for (int i = 0; i < args.Length; i++)
                {
                    string s = args[i];
                    if (s != null)
                    {
                        if (s.ToLower().Trim().CompareTo("--help".ToLower()) == 0)
                        {
                            showHelp = true;
                            return;
                        }
                        else if (s.ToLower().Trim().CompareTo("--verbose".ToLower()) == 0)
                            verbose = true;
                        else if (s.ToLower().Trim().CompareTo("--silent".ToLower()) == 0)
                            silent = true;
                        else
                        {
                            verbose = false;
                            silent = false;
                            showHelp = true;
                            return;
                        }
                    }
                }
            }
        }

        private void printUsage()
        {
            Console.WriteLine();
            Console.WriteLine("Usage: fogprep [option]");
            Console.WriteLine();
            Console.WriteLine(" Options");
            Console.WriteLine(" =======");
            Console.WriteLine("    --help       Prints this screen");
            Console.WriteLine("    --verbose    Show detailed output");
            Console.WriteLine("    --silent     No confirmation");
            Console.WriteLine(" ");
            Console.WriteLine(" Created by SyperiorSoft Inc.");
            Console.WriteLine(" Released under GPL.");

        }

        static int Main(string[] args)
        {
            new FogPrep(args);
            return exitCode;
        }
    }
}
