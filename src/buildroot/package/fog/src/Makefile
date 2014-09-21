#
# Makefile for the fog initrd binaries
#
#

all: fogmbrfix fogpartinfo

fogmbrfix:
	$(CXX) $(CXXFLAGS) -o fogmbrfix fogmbrfix.cpp

fogpartinfo: 
	$(CXX) $(CXXFLAGS) -o fogpartinfo fogpartinfo.cpp -lparted

clean:
	rm -f *.o fogmbrfix fogpartinfo

