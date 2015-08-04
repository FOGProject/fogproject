#include <string>
#include <iostream>
#include <fstream>

using namespace std;

main(int argc, char* argv[])
{
    string strLine;
    int intLineCnt = 0;
    string strWriteBack = "";

    if ( argc != 3 )
    {
        cout << "Usage: " << argv[0] << " input.mbr.txt output.mbr.txt" << endl;
        cout << "Input file must be generated using the xxd application" << endl;
        return 1;
    }

    ifstream inFile(argv[1], ios::in);
    ofstream outFile( argv[2], ios::out);

    if( ! inFile )
    {
        cout << " Unable to locate mbr text file." << endl;
        return 1;
    }

    if( ! outFile )
    {
        cout << " Unable to open output file." << endl;
        return 1;
    }

    while( getline(inFile,strLine) )
    {
        intLineCnt++;
        if( ! strLine.empty() );
        {
            if ( intLineCnt == 28 )
            {
                for( int i = 29; i < 33; i++ )
                    strLine[i] = '0';

                for( int i = 34; i < 38; i++ )
                    strLine[i] = '0';
                strLine[46] = '2';
                strLine[47] = '0';
                strWriteBack += strLine;
            }
            else if (intLineCnt == 29 )
            {
                strLine[9] = '2';

                for( int i = 24; i < 27; i++)
                    strLine[i] = '0';

                strLine[27] = '8';
                strWriteBack += strLine;
            }
            else
            {
                strWriteBack += strLine;
            }

            strWriteBack += "\n";
        }
    }
    outFile<<strWriteBack;
    return 0;
}
