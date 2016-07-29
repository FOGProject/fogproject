#!/usr/bin/awk -f

#$data is the filename of the output of sfdisk -d

#cat $data | awk -F, '\

# For readability, function parameters are on the first line. Locally scoped
# variables are on the following lines.

function display_output(partition_names, partitions, \
		pName) {
	if (!unit) {
		unit = "sectors";
	}
	if (!label) {
		type = "Id=";
	} else {
		type = "type=";
	}
	if (label && labelid && device) {
		printf("label: %s\n", label);
		printf("label-id: %s\n", labelid);
		printf("device: %s\n", device);
	}
	printf("unit: %s\n\n", unit);
	for(pName in partition_names) {
		printf("%s : start=%10d, size=%10d, %s%2s", partitions[pName, "device"], partitions[pName, "start"], partitions[pName, "size"],
               type, partitions[pName, "type"]);
		if(label == "dos") {
			if(partitions[pName, "flags"] != "") {
				printf("%s", partitions[pName, "flags"]);
			}
		} else if (label == "gpt") {
			if(partitions[pName, "uuid"] != "") {
				printf(", uuid=%s", partitions[pName, "uuid"]);
			}
			if(partitions[pName, "name"] != "") {
				printf(", name=%s", partitions[pName, "name"]);
			}
			if(partitions[pName, "attrs"] != "") {
				printf(", attrs=%s", partitions[pName, "attrs"]);
			}
		} else {
			if(partitions[pName, "flags"] != "") {
				printf("%s", partitions[pName, "flags"]);
			}
		}
		printf("\n");
	}
}

function check_overlap(partition_names, partitions, new_part_name, new_start, new_size, \
		extended_margin, new_type, new_part_number, pName, p_type, p_start, p_size, p_part_number) {
	extended_margin = 2;
	new_type = partitions[new_part_name, "type"];
	new_start = new_start + 0;
	new_size = new_size + 0;
	new_part_number = partitions[new_part_name, "number"] + 0;
	for(pName in partition_names) {
		p_type = partitions[pName, "type"];
		p_start = partitions[pName, "start"] + 0;
		p_size = partitions[pName, "size"] + 0;
		p_part_number = partitions[pName, "number"] + 0;
		# no overlap with self
		if(new_part_name == pName) { continue; }
		# ignore empty partitions
		if(p_size == 0) { continue; }
		# extended partitions must overlap logical partitions, but leave room for the extended partition table
		if((p_type == "5" || p_type == "f") && (new_part_number >= 5)) {
			# new_start is outside of [p_start+margin, p_start + p_size) OR
			# new_start + new_size is outside of (p_start+margin, p_start + p_size]
			if((new_start < p_start + extended_margin || new_start >= p_start + p_size) || (new_start + new_size <= p_start + extended_margin || new_start + new_size > p_start + p_size)) {
				return 1;
			}
		}
		# extended partitions must overlap logical partitions, but leave room for the extended partition table
		else if((new_type == "5" || new_type == "f") && (p_part_number >= 5)) {
			# logical partition must be contained in extended partition
			# p_start is outside of [new_start+margin, new_start + new_size) OR
			# p_start + p_size is outside of (new_start+margin, new_start + new_size]
			if((p_start < new_start + extended_margin || p_start >= new_start + new_size) || (p_start + p_size <= new_start + extended_margin || p_start + p_size > new_start + new_size)) {
				return 1;
			}
		}
		# all other overlap possibilities
		else {
			# new_start is inside of [p_start, p_start + p_size)	OR
			# new_start + new_size is inside of (p_start, p_start + p_size]
			if((new_start >= p_start && new_start < p_start + p_size) || (new_start + new_size > p_start && new_start + new_size <= p_start + p_size)) {
				return 1;
			}
			# p_start is inside of [new_start, new_start + new_size)	OR
			# p_start + p_size is inside of (new_start, new_start + new_size]
			if((p_start >= new_start && p_start < new_start + new_size) || (p_start + p_size > new_start && p_start + p_size <= new_start + new_size)) {
				return 1;
			}
		}
	}
	return 0;
}

function check_all_partitions(partition_names, partitions, \
		pName, p_start, p_size) {
	for(pName in partition_names) {
		p_start = partitions[pName, "start"] + 0;
		p_size = partitions[pName, "size"] + 0;
		if(check_overlap(partition_names, partitions, pName, p_start, p_size) != 0) {
			printf("ERROR in new partition table, quitting.\n");
			printf("ERROR: %s has an overlap.\n", pName);
			#exit(1);
		}
	}
	printf("# Partition table is consistent.\n");
}

function resize_partition(partition_names, partitions, args, \
		pName, new_start, new_size) {
	for(pName in partition_names) {
		if(pName == target) {
			if(unit == "sectors") {
				new_start =  partitions[pName, "start"];
				new_size = sizePos*2;
				if(check_overlap(partition_names, partitions, target, new_start, new_size) == 0) {
					partitions[target, "start"] = new_start;
					partitions[target, "size"] = new_size;
				}
			}
		}
	}
}

function move_partition(partition_names, partitions, args, \
                        pName, new_start, new_size) {
	for(pName in partition_names) {
		if(pName == target) {
			if(unit == "sectors") {
				new_start = (sizePos*2);
				new_start = new_start - new_start % CHUNK_SIZE;
				if(new_start < MIN_START) { new_start = MIN_START; }
				new_size = partitions[pName, "size"];
				if(check_overlap(partition_names, partitions, target, new_start, new_size) == 0) {
					partitions[target, "start"] = new_start;
					partitions[target, "size"] = new_size;
				}
			}
		}
	}
}

function fill_disk(partition_names, partitions, args, \
		disk, disk_size, n, fixed_partitions, original_variable, \
		original_fixed, new_variable, new_fixed, new_logical, pName, \
		p_type, p_number, p_size, found, i, partition_starts, \
		ordered_starts, old_sorted_in, curr_start) {
	# processSfdisk foo.sfdisk filldisk /dev/sda 100000 1:3:6
	#	foo.sfdisk = sfdisk -d output
	#	filldisk = action
	#	/dev/sda = disk to modify
	#	100000 = 1024 byte blocks size of disk
	#	1:3:6 = partition numbers that are fixed in size, : separated
	disk = target;
	disk_size = sizePos*2;
	# add swap partitions to the fixed list
	for(pName in partition_names) {
		p_type = partitions[pName, "type"];
		p_number = partitions[pName, "number"] + "";
		if(p_type == "82") {
			fixedList = fixedList ":" p_number;
		}
	}
	n = split(fixedList, fixed_partitions, ":");
	#
	# Find the total fixed and variable space
	#
	original_variable = 0;
	original_fixed	= MIN_START;
	for(pName in partition_names) {
		p_type = partitions[pName, "type"];
		p_number = partitions[pName, "number"] + 0;
		p_size = partitions[pName, "size"] + 0;
		partition_starts[partitions[pName, "start"] + 0] = pName;
		# skip extended partition, only count its logicals and the CHUNK for its partition table
		if(p_type == "5" || p_type == "f") {
			original_fixed += CHUNK_SIZE;
			continue;
		}
		# + CHUNK_SIZE to allow for margin after each logical partition (required if 2 or more logical partitions exist)
		if(p_number >= 5) {
			original_fixed += CHUNK_SIZE;
		}
		if(p_size == 0) { fixed_partitions[pName] = p_number; };
		found = 0; for(i in fixed_partitions) { if(fixed_partitions[i] == p_number) { found = 1; } };
		if(found) {
			original_fixed += partitions[pName, "size"];
		} else {
			original_variable += partitions[pName, "size"];
		}
	}
	#
	# Assign the new sizes to partitions
	#
	new_fixed = original_fixed;
	new_variable = disk_size - original_fixed;
	new_logical = 0;
	for(pName in partition_names) {
		p_type = partitions[pName, "type"];
		p_number = partitions[pName, "number"] + 0;
		p_size = partitions[pName, "size"] + 0;
		found = 0;
		for(i in fixed_partitions) {
			if(fixed_partitions[i] == p_number) {
				found = 1;
			}
		};
		if(p_type == "5" || p_type == "f") {
			partitions[pName, "newsize"] = CHUNK_SIZE;
            partitions[pName, "size"] = partitions[pName, "newsize"] - partitions[pName, "newsize"] % CHUNK_SIZE;
		} else if(found) {
			partitions[pName, "newsize"] = p_size;
            partitions[pName, "size"] = partitions[pName, "newsize"];
		} else {
			partitions[pName, "newsize"] = (new_variable*p_size/original_variable);
            partitions[pName, "size"] = partitions[pName, "newsize"] - partitions[pName, "newsize"] % CHUNK_SIZE;
		}
		if(p_number >= 5) {
			# + CHUNK_SIZE to allow for margin after each logical partition (required if 2 or more logical partitions exist)
			new_logical += partitions[pName, "size"] + CHUNK_SIZE;
		}
	}
	#
	# Assign the new size to the extended partition
	#
	for(pName in partition_names) {
		p_type = partitions[pName, "type"];
		p_number = partitions[pName, "number"] + 0;
		p_size = partitions[pName, "size"] + 0;
		if(p_type == "5" || p_type == "f") {
			partitions[pName, "newsize"] += new_logical;
			partitions[pName, "size"] = partitions[pName, "newsize"] - partitions[pName, "newsize"] % CHUNK_SIZE;
		}
	}
	#
	# Assign the new start positions
	#
	asort(partition_starts, ordered_starts, "@ind_num_asc");
	old_sorted_in = PROCINFO["sorted_in"];
	PROCINFO["sorted_in"] = "@ind_num_asc";
	curr_start = MIN_START;
	for(i in ordered_starts) {
		pName = ordered_starts[i];
		p_type = partitions[pName, "type"];
		p_number = partitions[pName, "number"] + 0;
		p_size = partitions[pName, "size"] + 0;
        p_start = partitions[pName, "start"] + 0;
        for (j in fixed_partitions) {
            if (fixed_partitions[j] == p_number) {
                curr_start = p_start;
            }
        }
		if(p_size > 0) {
			partitions[pName, "start"] = curr_start;
		}
		if(p_type == "5" || p_type == "f") {
			curr_start += CHUNK_SIZE;
		} else {
			curr_start += p_size;
		}
		# + CHUNK_SIZE to allow for margin after each logical partition (required if 2 or more logical partitions exist)
		if(p_number >= 5) {
			curr_start += CHUNK_SIZE;
		}
	}
	PROCINFO["sorted_in"] = old_sorted_in;
	check_all_partitions(partition_names, partitions);
}

BEGIN{
	#Arguments - Use "-v var=val" when calling this script
	#CHUNK_SIZE;
	#MIN_START;
	#action;
	#target;
	#sizePos;
	#fixedList;
	label = "";
	unit = "";
	partitions[0] = "";
	partition_names[0] = "";
}

/^label:/{ label = $2 }
/^label-id:/{ labelid = $2 }
/^device:/{ device = $2 }
/^unit:/{ unit = $2; }

/start=/{
	# Get Partition Name
	part_name=$1
	partitions[part_name, "device"] = part_name
	partition_names[part_name] = part_name

	# Isolate Partition Number
	# The regex can handle devices like mmcblk0p3
	part_number = gensub(/^[^0-9]*[0-9]*[^0-9]+/, "", 1, part_name)
	partitions[part_name, "number"] = part_number

	# Separate attributes
	split($0, fields, ",")

	# Get start value
	gsub(/.*start= */, "", fields[1])
	partitions[part_name, "start"] = fields[1]
	# Get size value
	gsub(/.*size= */, "", fields[2])
	partitions[part_name, "size"] = fields[2]
	# Get type/id value
	gsub(/.*(type|Id)= */, "", fields[3])
	partitions[part_name, "type"] = fields[3]

	if ( label == "dos" )
	{
		split($0, typeList, "type=")
		part_flags = gensub(/^[^\,$]*/, "",1,typeList[2])
		partitions[part_name, "flags"] = part_flags;
	}
	# GPT elements
	else if ( label == "gpt" )
	{
		# Get uuid value
		gsub(/.*uuid= */, "", fields[4])
		partitions[part_name, "uuid"] = fields[4]
		# Get name value
		gsub(/.*name= */, "", fields[5])
		partitions[part_name, "name"] = fields[5]
		# Get attrs value
		if (fields[6])
		{
			gsub(/.*attrs= */, "", fields[6])
			partitions[part_name, "attrs"] = fields[6]
		}
	}
	else
	{
		split($0, typeList, "Id=")
		part_flags = gensub(/^[^\,$]*/, "",1,typeList[2])
		partitions[part_name, "flags"] = part_flags;
	}
}

END{
	delete partitions[0];
	delete partition_names[0];
	if(action == "resize") {
		resize_partition(partition_names, partitions, args);
	} else if(action == "move") {
		move_partition(partition_names, partitions, args);
	} else if(action == "filldisk") {
		fill_disk(partition_names, partitions, args);
	}
	display_output(partition_names, partitions);
}
