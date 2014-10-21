#!/bin/sh

#
# These functions are for dealing with resizing of partitions.
# They currently work for MBR and Extended partition tables.
# THE DO NOT WORK FOR GPT.
# It is assumed that at most 1 extended partition will exist,
# with any number of logical partitions.
# Requires the sfdisk tool.
# Assumes that sfdisk's "unit: sectors" means 512 byte sectors.
#

# $1 is the name of the disk drive
# $2 is name of file to save to.
saveSfdiskPartitions() {
	sfdisk -d "$1" 2>/dev/null > "$2";
}

# $1 is the name of the disk drive
# $2 is name of file to load from.
applySfdiskPartitions() {
	sfdisk "$1" >&/dev/null < "$2";
}


# $1 is the name of the disk drive
hasExtendedPartition() {
	sfdisk -d "$1" 2>/dev/null | egrep 'Id= [5f]' | wc -l
}

# $1 is the name of the partition device (e.g. /dev/sda3)
saveEBR() {
	local part_number=`echo $1 | sed -r 's/^[^0-9]+//g'`;
	local disk=`echo $part | sed -r 's/[0-9]+$//g'`;
	local part_type=`sfdisk -d "$disk" 2>/dev/null | egrep ^$1 | awk -F, '{print $3;}' | awk -F= '{print $2;}' | sed -r 's/\s//g'`;
	if [ "$part_type" == "5" -o "$part_type" == "f" -o "$part_number" -ge 5 ]; then
		dots "Saving EBR for ($1)";
		dd if=$1 of=/tmp/d1p${part_number}.ebr bs=512 count=1 &> /dev/null;
		echo "Done";
	fi
}

# $1 is the name of the partition device (e.g. /dev/sda3)
restoreEBR() {
	local part_number=`echo $1 | sed -r 's/^[^0-9]+//g'`;
	local disk=`echo $part | sed -r 's/[0-9]+$//g'`;
	local part_type=`sfdisk -d "$disk" 2>/dev/null | egrep ^$1 | awk -F, '{print $3;}' | awk -F= '{print $2;}' | sed -r 's/\s//g'`;
	if [ "$part_type" == "5" -o "$part_type" == "f" -o "$part_number" -ge 5 ]; then
		if [ -e "/tmp/d1p${part_number}.ebr" ]; then
			dots "Restoring EBR for ($1)";
			dd of=$1 if=/tmp/d1p${part_number}.ebr bs=512 count=1 &> /dev/null;
			echo "Done";
		fi
	fi
}

# $1 is the location of the file
makeFstypesFile() {
	rm -f "$1"
	touch "$1"
}
# $1 is the location of the file
# $2 is the partition device name
# $3 is the fstype
addToFstypesFile() {
	echo "$2 $3" >> $1;
}
# $1 is the location of the file to store uuids in
makeSwapUUIDFile() {
	rm -f "$1"
	touch "$1"
}
# $1 is the location of the file to store uuids in
# $2 is the partition device name
saveSwapUUID() {
	local uuid=`blkid $2 | awk '{print $2;}' | sed 's/"//g' | awk -F= '{print $2;}'`;
	if [ -n "$uuid" ]; then
		echo " * Saving UUID ($uuid) for ($2)";
		echo "$2 $uuid" >> $1;
	fi
}
# $1 is the location of the file uuids are stored in
# $2 is the partition device name
makeSwapSystem() {
	local uuid="";
	local option="";
	local disk=`echo $2 | sed -r 's/[0-9]+$//g'`;
	local part_type="0";
	local hasgpt=`hasGPT $disk`;
	if [ "$hasgpt" == "1" ]; then
		# don't have a good way to test, as ubuntu installer
		# doesn't set the GPT partition type correctly.
		# so, only format as swap if uuid exists.
		if [ -e "$1" ]; then
			uuid=`egrep "^$2" "$1" | awk '{print $2;}'`;
		fi
		if [ -n "$uuid" ]; then
			part_type="82";
		fi
	else
		part_type=`sfdisk -d "$disk" 2>/dev/null | egrep "^$2" | awk -F, '{print $3;}' | awk -F= '{print $2;}' | sed -r 's/\s//g'`;
	fi
	if [ "$part_type" == "82" ]; then
		if [ -e "$1" ]; then
			uuid=`egrep "^$2" "$1" | awk '{print $2;}'`;
		fi
		if [ -n "$uuid" ]; then
			option="-U $uuid";
		fi
		echo " * Restoring swap partition: $2";
		mkswap $option $2 &> /dev/null;
	fi
}

# $1 is the partition device (e.g. /dev/sda1)
# $2 is the new desired size in 1024 (1k) blocks
resizePartition() {
	local part="$1";
	local size="$2";
	local disk=`echo $part | sed -r 's/[0-9]+$//g'`;
	local tmp_file="/tmp/sfdisk.$$";
	local tmp_file2="/tmp/sfdisk2.$$";
	saveSfdiskPartitions $disk $tmp_file;
	processSfdisk $tmp_file resize $part $size > $tmp_file2;
	if [ "$?" == "0" ]; then
		applySfdiskPartitions $disk $tmp_file2;
	fi
	rm -f $tmp_file;
	rm -f $tmp_file2;
}

# $1 is the disk device (e.g. /dev/sda)
# $2 is the name of the original sfdisk -d output file used as a template
# $3 is the : separated list of fixed size partitions (e.g. 1:2)
#	 swap partitions are automatically added.  Empty string is
#	 ok.
fillDiskWithPartitions() {
	local disk_size=`blockdev --getsize64 "$1" | awk '{printf("%d\n",$1/1024);}'`;
	local tmp_file2="/tmp/sfdisk2.$$";
	processSfdisk "$2" filldisk "$1" "$disk_size" "$3" > $tmp_file2;
	if [ "$?" == "0" ]; then
		applySfdiskPartitions $1 $tmp_file2;
	fi
	rm -f $tmp_file2;
}


#
#  processSfdisk() processes the output of sfdisk -d
#  and creates a new sfdisk -d like output, applying
#  the requested action.  Read below to see the actions
#
# $1 the name of a file that is the output of sfdisk -d
# $2 is the action "resize|other?"
# $3 is the first parameter
# $4 is the second parameter
# ...
#
# actions:
# processSfdisk foo.sfdisk resize /dev/sda1 100000
#	foo.sfdisk = sfdisk -d output
#	resize = action
#	/dev/sda1 = partition to modify
#	100000 = 1024 byte blocks size to make it
#	output: new sfdisk -d like output
#
# processSfdisk foo.sfdisk move /dev/sda1 100000
#	foo.sfdisk = sfdisk -d output
#	move = action
#	/dev/sda1 = partition to modify
#	100000 = 1024 byte blocks size to move it to
#	output: new sfdisk -d like output
#
# processSfdisk foo.sfdisk filldisk /dev/sda 100000 1:3:6
#	foo.sfdisk = sfdisk -d output
#	filldisk = action
#	/dev/sda = disk to modify
#	100000 = 1024 byte blocks size of disk
#	1:3:6 = partition numbers that are fixed in size, : separated
#	output: new sfdisk -d like output
#
# example file data	   
# /dev/sda1 : start=	 2048, size=   204800, Id= 7, bootable
# /dev/sda2 : start=   206848, size= 50573312, Id= 7
# /dev/sda3 : start= 50780160, size=	 2048, Id=83
# /dev/sda4 : start= 50784254, size= 16322562, Id= 5
# /dev/sda5 : start= 50784256, size=  7811072, Id=83
# /dev/sda6 : start= 58597376, size=  8509440, Id=82
#
processSfdisk()
{
	if [ "$osid" == "1" -o "$osid" == "2" ]; then
		local chunksize="512";
		local minstart="63";
	else
		local minstart=`cat $data  | grep start | awk -F, '{print $1;}' | awk -F: '{print $2}' | awk -F= '{print $2;}' | sort -n | head -1`;
		if [ "$minstart" == "63" ]; then
			local chunksize="512";
		else
			local chuncksize="2048";
		fi
	fi
	local data="$1";
	cat $data | awk -F, '\
function display_output(partition_names, partitions,	 part_device) { \
		printf("unit: %s\n\n", unit); \
		for(part_device in partition_names) { \
		printf("%s : start=%9d, size=%9d, Id= %2s", \
		partitions[part_device, "device"], \
		partitions[part_device, "start"], \
		partitions[part_device, "size"], \
		partitions[part_device, "id"] \
		); \
		if(partitions[part_device, "flags"] != "") { \
		printf(", %s\n", partitions[part_device, "flags"]); \
		} else { \
		printf("\n"); \
		} \
		} \
		} \
		function check_overlap(partition_names, partitions, new_part_device, new_start, new_size,	  \
		extended_margin, new_type, new_part_number, part_device, \
		p_type, p_start, p_size, p_part_number) { \
		extended_margin = 2; \
		new_type = partitions[new_part_device, "id"]; \
		new_start = new_start + 0; \
		new_size = new_size + 0; \
		new_part_number = partitions[new_part_device, "number"] + 0; \
		for(part_device in partition_names) { \
		p_type = partitions[part_device, "id"]; \
		p_start = partitions[part_device, "start"] + 0; \
		p_size = partitions[part_device, "size"] + 0; \
		p_part_number = partitions[part_device, "number"] + 0; \
		# no overlap with self \
		if(new_part_device == part_device) { continue; } \
			# ignore empty partitions \
			if(p_size == 0) { continue; } \
				# extended partitions must overlap logical partitions, but leave room for the extended partition table \
				if((p_type == "5" || p_type == "f") && (new_part_number >= 5)) { \
					# new_start is outside of [p_start+margin, p_start + p_size) OR \
	  # new_start + new_size is outside of (p_start+margin, p_start + p_size] \
						if((new_start < p_start + extended_margin || new_start >= p_start + p_size) || \
							(new_start + new_size <= p_start + extended_margin || new_start + new_size > p_start + p_size)) { \
							return 1; \
							} \
							} \
							# extended partitions must overlap logical partitions, but leave room for the extended partition table \
				else if((new_type == "5" || new_type == "f") && (p_part_number >= 5)) { \
					# logical partition must be contained in extended partition \
	  # p_start is outside of [new_start+margin, new_start + new_size) OR \
	  # p_start + p_size is outside of (new_start+margin, new_start + new_size] \
							if((p_start < new_start + extended_margin || p_start >= new_start + new_size) || \
								(p_start + p_size <= new_start + extended_margin || p_start + p_size > new_start + new_size)) { \
								return 1; \
								} \
								} \
								# all other overlap possibilities \
				else { \
					# new_start is inside of [p_start, p_start + p_size)	OR \
	  # new_start + new_size is inside of (p_start, p_start + p_size] \
						if((new_start >= p_start && new_start < p_start + p_size) || \
							(new_start + new_size > p_start && new_start + new_size <= p_start + p_size)) { \
							return 1; \
							} \
							# p_start is inside of [new_start, new_start + new_size)	OR \
	  # p_start + p_size is inside of (new_start, new_start + new_size] \
							if((p_start >= new_start && p_start < new_start + new_size) || \
								(p_start + p_size > new_start && p_start + p_size <= new_start + new_size)) { \
								return 1; \
								} \
								} \
								} \
								return 0; \
								} \
								function check_all_partitions(partition_names, partitions, \
								part_device, p_start, p_size) { \
								for(part_device in partition_names) { \
								p_start = partitions[part_device, "start"] + 0; \
								p_size = partitions[part_device, "size"] + 0; \
								if(check_overlap(partition_names, partitions, part_device, p_start, p_size) != 0) { \
								printf("ERROR in new partition table, quitting.\n"); \
								printf("ERROR: %s has an overlap.\n", part_device);
	  #exit(1); \
								} \
									} \
									printf("# Partition table is consistent.\n"); \
									} \
									function resize_partition(partition_names, partitions, args, unit, \
									part_device, new_start, new_size) { \
									for(part_device in partition_names) { \
									if(part_device == args[1]) { \
									if(unit == "sectors") { \
									new_start =  partitions[part_device, "start"]; \
									new_size = args[2]*2; \
									if(check_overlap(partition_names, partitions, args[1], new_start, new_size) == 0) { \
									partitions[args[1], "start"] = new_start; \
									partitions[args[1], "size"] = new_size; \
									} \
									} \
									} \
									} \
									}\
function move_partition(partition_names, partitions, args, unit, \
									part_device, new_start, new_size) { \
									for(part_device in partition_names) { \
									if(part_device == args[1]) { \
									if(unit == "sectors") { \
									new_start = (args[2]*2); \
									new_start = new_start - new_start % CHUNK_SIZE; \
									if(new_start < MIN_START) { new_start = MIN_START; } \
									new_size = partitions[part_device, "size"]; \
									if(check_overlap(partition_names, partitions, args[1], new_start, new_size) == 0) { \
									partitions[args[1], "start"] = new_start; \
									partitions[args[1], "size"] = new_size; \
									} \
									} \
									} \
									} \
									} \
									function fill_disk(partition_names, partitions, args, unit, \
									disk, disk_size, n, fixed_partitions, \
									original_variable, original_fixed, \
									new_variable, new_fixed, new_logical, \
									part_device, p_type, p_number, p_size, found, i, \
									partition_starts, ordered_starts, old_sorted_in, \
									curr_start) { \
									# processSfdisk foo.sfdisk filldisk /dev/sda 100000 1:3:6 \
#	foo.sfdisk = sfdisk -d output \
#	filldisk = action \
#	/dev/sda = disk to modify \
#	100000 = 1024 byte blocks size of disk \
#	1:3:6 = partition numbers that are fixed in size, : separated \
								disk		= args[1]; \
									disk_size = args[2]*2; \
									# add swap partitions to the fixed list
									for(part_device in partition_names) { \
										p_type = partitions[part_device, "id"]; \
										p_number = partitions[part_device, "number"] + ""; \
										if(p_type == "82") { \
										args[3] = args[3] ":" p_number; \
										} \
										} \
										n = split(args[3], fixed_partitions, ":"); \
										# \
  # Find the total fixed and variable space \
  # \
										original_variable = 0; \
											original_fixed	= MIN_START; \
											for(part_device in partition_names) { \
											p_type = partitions[part_device, "id"]; \
											p_number = partitions[part_device, "number"] + 0; \
											p_size = partitions[part_device, "size"] + 0; \
											partition_starts[partitions[part_device, "start"] + 0] = part_device; \
											# skip extended partition, only count its logicals and the CHUNK for its partition table \
											if(p_type == "5" || p_type == "f") { \
												original_fixed += CHUNK_SIZE; \
												continue; \
												} \
												if(p_size == 0) { fixed_partitions[part_device] = p_number; }; \
												found = 0; for(i in fixed_partitions) { if(fixed_partitions[i] == p_number) { found = 1; } }; \
												if(found) { \
												original_fixed += partitions[part_device, "size"]; \
												} else { \
												original_variable += partitions[part_device, "size"]; \
												} \
												} \
												# \
  # Assign the new sizes to partitions \
  # \
												new_fixed = original_fixed; \
													new_variable = disk_size - original_fixed; \
													new_logical = 0; \
													for(part_device in partition_names) { \
													p_type = partitions[part_device, "id"]; \
													p_number = partitions[part_device, "number"] + 0; \
	p_size = partitions[part_device, "size"] + 0; \
	found = 0; for(i in fixed_partitions) { if(fixed_partitions[i] == p_number) { found = 1; } }; \
	if(p_type == "5" || p_type == "f") { \
	  partitions[part_device, "newsize"] = CHUNK_SIZE; \
	} else if(found) { \
	  partitions[part_device, "newsize"] = p_size; \
	} else { \
	  partitions[part_device, "newsize"] = (new_variable*p_size/original_variable); \
	} \
	partitions[part_device, "size"] = partitions[part_device, "newsize"] - partitions[part_device, "newsize"] % CHUNK_SIZE; \
	if(p_number >= 5) { \
	  new_logical += partitions[part_device, "size"]; \
	} \
  } \
  # \
  # Assign the new size to the extended partition \
  # \
  for(part_device in partition_names) { \
	p_type = partitions[part_device, "id"]; \
	p_number = partitions[part_device, "number"] + 0; \
	p_size = partitions[part_device, "size"] + 0; \
	if(p_type == "5" || p_type == "f") { \
	  partitions[part_device, "newsize"] += new_logical; \
	  partitions[part_device, "size"] = partitions[part_device, "newsize"] - partitions[part_device, "newsize"] % CHUNK_SIZE; \
	} \
  } \
  # \
  # Assign the new start positions \
  # \
  asort(partition_starts, ordered_starts, "@ind_num_asc"); \
  old_sorted_in = PROCINFO["sorted_in"]; \
  PROCINFO["sorted_in"] = "@ind_num_asc"; \
  curr_start = MIN_START; \
  for(i in ordered_starts) { \
	part_device = ordered_starts[i]; \
	p_type = partitions[part_device, "id"]; \
	p_size = partitions[part_device, "size"] + 0; \
	if(p_size > 0) { \
	  partitions[part_device, "start"] = curr_start; \
	} \
	if(p_type == "5" || p_type == "f") { \
	  curr_start += CHUNK_SIZE; \
	} else { \
	  curr_start += p_size; \
	} \
  } \
  PROCINFO["sorted_in"] = old_sorted_in; \
  check_all_partitions(partition_names, partitions); \
} \
BEGIN{ \
  SUBSEP = ":"; \
  action="'$2'"; \
  args[1]="'$3'"; \
  args[2]="'$4'"; \
  args[3]="'$5'"; \
  unit = ""; \
  partitions[0] = ""; \
  partition_names[0] = ""; \
  CHUNK_SIZE = '$chunksize'; \
  MIN_START = '$minstart'; \
} \
$1 ~ /^unit:/{ \
  n = split($1, fields, " "); \
  unit = fields[2]; \
} \
$1 ~ /start=/{ \
  n = split($1, fields, ":"); \
  \
  n = split(fields[1], values, " "); \
  gsub(/ /, "", values[1]); \
  part_device = values[1]; \
  partitions[part_device, "device"] = part_device; \
  partition_names[part_device] = part_device; \
  \
  part_number = gensub(/^[^0-9]*/,"",1,part_device); \
  partitions[part_device, "number"] = part_number; \
  \
  n = split(fields[2], values, "="); \
  gsub(/ /, "", values[2]); \
  part_start = values[2]; \
  partitions[part_device, "start"] = part_start; \
  \
  n = split($2, values, "="); \
  gsub(/ /, "", values[2]); \
  part_size = values[2]; \
  partitions[part_device, "size"] = part_size; \
  \
  n = split($3, values, "="); \
  gsub(/ /, "", values[2]); \
  part_id = values[2]; \
  partitions[part_device, "id"] = part_id; \
  \
  if(NF > 3) { \
	part_flags = $4; \
	gsub(/ /, "", part_flags); \
  } else { \
	part_flags = ""; \
  } \
  partitions[part_device, "flags"] = part_flags; \
} \
END{ \
  delete partitions[0]; \
  delete partition_names[0]; \
  if(action == "resize") { \
	resize_partition(partition_names, partitions, args, unit);
  } else if(action == "move") { \
	move_partition(partition_names, partitions, args, unit);
  } else if(action == "filldisk") { \
	fill_disk(partition_names, partitions, args, unit);
  } \
  display_output(partition_names, partitions); \
} \
';
}

#
# GPT Functions below
#

# $1 : device name of drive
getPartitionTableType() {
	local disk="$1";
	local mbr=`gdisk -l $disk | egrep '^ *MBR:' | awk '{print $2;}'`;
	local gpt=`gdisk -l $disk | egrep '^ *GPT:' | awk '{print $2;}'`;
	local type="";
	local mbrtype="";
	local gpttype="";
	if [ "$mbr" == "present" ]; then
		mbrtype="MBR";
	elif [ "$mbr" == "hybrid" ]; then
		mbrtype="HYBRID";
	elif [ "$mbr" == "protective" ]; then
		mbrtype="";
	elif [ "$mbr" == "not" ]; then
		mbrtype="";
	fi

	if [ "$gpt" == "present" ]; then
		gpttype="GPT";
	elif [ "$gpt" == "not" ]; then
		gpttype="";
	fi
	if [ -n "$gpttype" -a -n "$mbrtype" ]; then
		type="${gpttype}-${mbrtype}"
	elif [ -n "$gpttype" ]; then
		type="${gpttype}"
	elif [ -n "$mbrtype" ]; then
		type="${gpttype}"
	else
		type="";
	fi
	echo "$type"
}

# $1 : device name of drive
hasHybridMBR() {
	local disk="$1";
	local mbr=`gdisk -l $disk | egrep '^ *MBR:' | awk '{print $2;}'`;
	if [ "$mbr" == "hybrid" ]; then
		echo "1";
	else
		echo "0";
	fi
}

# $1 : device name of drive
hasGPT() {
	local disk="$1";
	local gpt=`gdisk -l $disk | egrep '^ *GPT:' | awk '{print $2;}'`;
	if [ "$gpt" == "present" ]; then
		echo "1";
	elif [ "$gpt" == "not" ]; then
		echo "0";
	fi
}

# Local Variables:
# indent-tabs-mode: t
# sh-basic-offset: 4
# sh-indentation: 4
# tab-width: 4
# End:
