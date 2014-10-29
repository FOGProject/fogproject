#include <iostream>
#include <parted/parted.h>
#include <parted/device.h>

/* g++ -o fogpartinfo main.cpp -lparted */

using namespace std;

void usage();
void listAllDevices();
void listAllParts( char *strDev );

int main(int argc, char *argv[] )
{
	bool blFound = false;
	
	if ( argc == 2 )
	{
		if ( strcmp(argv[1], "--list-devices" ) == 0 )
		{
			listAllDevices();
			blFound = true;
		}	
	}
	else if ( argc == 3 )
	{
		if ( strcmp(argv[1], "--list-parts" ) == 0 )
		{
			listAllParts( argv[2] );
			blFound = true;
		}

		
	}

	
	cout << "\n"; 
	
	if ( blFound )
		return 0;
	else
	{
		usage();
		return 1;
	}
}



void listAllParts( char *strDev )
{
	PedDevice *dev = 0;
        PedPartition *part = 0;
	ped_device_probe_all();
	
        do 
        {
                dev = ped_device_get_next(dev);
                if ( !dev )
                        break;

                PedDisk *disk = 0;
                if ( (disk = ped_disk_new(dev)) == 0 ) 
                {
                        continue;
                }

		if ( strcmp( strDev, dev->path ) == 0 )
		{
	                do 
	                {
	                        part = ped_disk_next_partition(disk, part);
	                        if ( !part )
	                                break;

	                        if ( part->type == PED_PARTITION_NORMAL || part->type == PED_PARTITION_LOGICAL || part->type == PED_PARTITION_EXTENDED ) 
	                        {
	                                cout << ped_partition_get_path(part) << " ";                        
	                        }
	                } while (part);
	                
	                return;
                }
        } while ( dev );
}

void listAllDevices()
{
	PedDevice *dev = 0;
	ped_device_probe_all();
	
        do 
        {       
                dev = ped_device_get_next(dev);
                if ( !dev )
                        break;
                        
                PedDisk *disk = 0;
                if ( (disk = ped_disk_new(dev)) == 0 ) 
                {
                        continue;
                }
                        

                cout << dev->path << " ";
        } while ( dev );
}

void usage()
{
	cout << " FOG Partition Information\n\n";
	cout << " ./fogpartinfo command [arguments]\n\n"; 
	cout << "               Commands: \n";
	cout << "                  --list-devices\n";
	cout << "                           This will list all devices\n";
	cout << "                           known to the computer.\n\n";
	cout << "                  --list-parts /dev/xxx\n";	
	cout << "                           This will list all partitions\n";
	cout << "                           on the device.\n\n";	
}
