#!/usr/bin/awk -f

# $data is the filename of the output of sfdisk -d
# cat $data | awk -F, '\

# Checks all partitions for any overlap possibilities.
# Requires partition_names and partitions.
# partition_names is an array of all the partition names.
# partitions is an array containing the data we need.
#  Partitions are sent in an array of form:
#   partitions['name']['start'] = value;
#   partitions['name']['size'] = value;
# pName is locally scoped, but could be set here if wanted. Will be overwritten anyway.
# p_start is locally scoped, but could be set here if wanted. Will be overwritten anyway.
# p_size is locally scoped, but could be set here if wanted. Will be overwritten anyway.
function check_all_partitions(partition_names, partitions, pName, p_start, p_size) {
    # Iterate through our parittion names.
    for (pName in partition_names) {
        # The current iteration's start value.
        p_start = partitions[pName, "start"];
        # The current iteration's size value.
        p_size = partitions[pName, "size"];
        # Store the overlap variable.
        overlap = check_overlap(partition_names, partitions, pName, p_start, p_size, 0);
        # If overlap is ok, skip.
        if (overlap == 0) {
            continue;
        }
        # If the overlap didn't skip over print nice message
        # break from loop, as no need to do anything else, it's bad.
        printf("# ERROR in new partition table, quitting.\n");
        printf("# ERROR: %s has an overlap.\n", pName);
        return 1;
    }
    # Only print consistent overlap if overlap is not set safe.
    printf("# Partition table is consistent.\n");
    # Return with success.
    return 0;
}

# Checks the overlap.
# Requires partition_names, partitions, new_part_name, new_start, new_size, capture.
# partition_names is an array of all the partition names.
# partitions is an array containing the data we need.
#  Partitions are sent in an array of form:
#   partitions['/dev/sda1']['start'] = value;
#   partitions['/dev/sda1']['size'] = value;
# new_part_name is the target we're checking.
# new_start The new start position. Could be locally scoped from new_part_name,
#  but is set for simplicity.
# new_size the new size of the partition. Could be locally scoped from
#  new_part_name, but is set for simplicity.
# capture is a flag telling us the type of tasking we're doing. Filling the disk
#  sets our new start positions automatically. Resizing (shrinking really) does
#  not adjust the original start positions for us. We can't set the start position
#  during shrinking because the data hasn't been captured yet. If we move the
#  start position, it's possible to lose the data and/or end up with corrupt images.
# extended_margin locally scoped, but could be set. Default is 2 as is normal. Currently
#  will be overwritten regardless.
# new_type is locally scoped, but could be set here if wanted. Will be overwritten anyway.
# new_part_number is locally scoped, but could be set here if wanted. Will be overwritten anyway.
# pName is locally scoped, but could be set here if wanted. Will be overwritten anyway.
# p_type is locally scoped, but could be set here if wanted. Will be overwritten anyway.
# p_start is locally scoped, but could be set here if wanted. Will be overwritten anyway.
# p_size is locally scoped, but could be set here if wanted. Will be overwritten anyway.
# p_part_number is locally scoped, but could be set here if wanted. Will be overwritten anyway.
function check_overlap(partition_names, partitions, new_part_name, new_start, new_size, capture, extended_margin, new_type, new_part_number, pName, p_type, p_start, p_size, p_part_number) {
    # Used for extended volumes (logical disks)
    extended_margin = 2;
    # Set new_type variable. (Partition Type -- extended, ntfs, ext4, etc...)
    new_type = partitions[new_part_name, "type"];
    # Set new_part_number variable. Self explanatory.
    new_part_number = partitions[new_part_name, "number"];
    # Iterate our aprtitions.
    for (pName in partition_names) {
        # Set the type variable from original layout.
        p_type = partitions[pName, "type"];
        # Set the size of the original drive.
        p_size = partitions[pName, "size"];
        # Set the start of the original drive.
        p_start = partitions[pName, "start"];
        # Set the partition number.
        p_part_number = partitions[pName, "number"];
        # Partitions will not overlap themselves, so skip.
        if (new_part_name == pName) {
            continue;
        }
        # Empty partitions aren't going to have any overlap, so skip.
        if (p_size == 0) {
            continue;
        }
        # We need to adjust the start positions for smaller/larger
        # drives to test overlap accurately.
        # Otherwise, captures would almost always have an overlap.
        # Equal partitions will remain untouched.
        if (capture > 0) {
            # If the new_size is < p_size, subtract the new size amount
            # from the p_start.
            # If the new_size is > p_size, add the new size amount
            # from the p_start.
            if (new_size < p_size) {
                p_start -= new_size;
            } else if (new_size > p_size) {
                p_start += new_size;
            }
            # If the new type is an extended type:
            # Extended only happens on non-gpt disks.
            # we need to add at least the extended element.
            if (label != "gpt") {
                if (new_type == "5" || new_type == "f") {
                    p_start += extended_margin
                }
            }
        }
        # If the new start is greater than, or equal to
        # the p_start value, there is an overlap possible.
        if (new_start >= p_start) {
            # If the new star tis greater than the
            # p_start + the p_size value, we are in overlap.
            if (new_start < p_start + p_size) {
                return 1;
            }
        }
    }
    return 0;
}

# Displays the information in sfdisk type format for us.
# Requires partition_names and partitions.
# partition_names is an array of all the partition names.
# partitions is an array containing the data we need.
#  Partitions are sent in an array of form:
#   partitions['name']['start'] = value;
#   partitions['name']['size'] = value;
# pName is locally scoped, but could be set here if wanted. Will be overwritten anyway.
# p_device is locally scoped, but could be set here if wanted. Will be overwritten anyway.
# p_start is locally scoped, but could be set here if wanted. Will be overwritten anyway.
# p_size is locally scoped, but could be set here if wanted. Will be overwritten anyway.
# p_type is locally scoped, but could be set here if wanted. Will be overwritten anyway.
# p_flag is locally scoped, but could be set here if wanted. Will be overwritten anyway.
# p_uuid is locally scoped, but could be set here if wanted. Will be overwritten anyway.
# p_name is locally scoped, but could be set here if wanted. Will be overwritten anyway.
# p_attrs is locally scoped, but could be set here if wanted. Will be overwritten anyway.
# typelabel is locally scoped, but could be set here if wanted. Will be overwritten anyway.
function display_output(partition_names, partitions, pName, p_device, p_start, p_size, p_type, p_flag, p_uuid, p_name, p_attrs, typelabel) {
    #
    # unit, label, labelid, device are all globally scoped variables.
    #
    # If unit is not set, or has no value, set to sectors.
    if (!unit) {
        unit = "sectors";
    }
    # Type label can shift, by default set to Id=
    typelabel = "Id=";
    # If label is set, set label to type=
    if (label) {
        typelabel = "type=";
    }
    # If label, labelid, and device are all set,
    # add to the sfdisk file.
    if (label && labelid && device) {
        printf("label: %s\nlabel-id: %s\ndevice: %s\n", label, labelid, device);
    }
    # Add unit to the sfdisk file.
    printf("unit: %s\n\n", unit);
    # Iterate our partition names.
    for (pName in partition_names) {
        # Set our p_device variable.
        p_device = partitions[pName, "device"];
        # Set our p_start variable.
        p_start = partitions[pName, "start"];
        # set our p_size variable.
        p_size = partitions[pName, "size"];
        # set our p_type variable.
        p_type = partitions[pName, "type"];
        # Write our data into the sfdisk file.
        printf("%s : start=%10d, size=%10d, %s%2s", p_device, p_start, p_size, typelabel, p_type);
        # If label is gpt do these things.
        # else we need the flags.
        if (label == "gpt") {
            # Set our p_uuid variable.
            p_uuid = partitions[pName, "uuid"];
            # Set our p_name variable. (probably could be pName)
            p_name = partitions[pName, "name"];
            # Set our p_attrs variable.
            p_attrs = partitions[pName, "attrs"];
            # If uuid is set, append to line. (Printf before if statement)
            if (p_uuid != "") {
                printf(", uuid=%s", p_uuid);
            }
            # If name is set, append to line. (Printf before if statement)
            if (p_name != "") {
                printf(", name=%s", p_name);
            }
            # If attrs is set, append to line. (Printf before if statement)
            if (p_attrs != "") {
                printf(", attrs=%s", p_attrs);
            }
        } else {
            # Set our p_flag variable.
            p_flag = partitions[pName, "flags"];
            # If flag is set, append to line. (Printf before the if statement)
            if (p_flag != "") {
                printf("%s", p_flag);
            }
        }
        # Write new line from partition info.
        printf("\n");
    }
    return 0;
}

function resize_partition(partition_names, partitions, args, pName, new_start, new_size) {
    #
    # target is a glboally scoped variable.
    #
    # Iterate our partitions.
    for (pName in partition_names) {
        # If pName is not the target, skip.
        if (pName != target) {
            continue;
        }
        # If unit is not sectors, skip.
        if (unit != "sectors") {
            continue;
        }
        new_start = partitions[pName, "start"];
        new_size = sizePos / CHUNK_SIZE;
        overlap = check_overlap(partition_names, partitions, target, new_start, new_size, 1);
        printf("# Overlap %s\n", overlap);
        # If there was an issue in checking overlap, skip.
        if (overlap != 0) {
            continue;
        }
        partitions[target, "start"] = new_start;
        partitions[target, "size"] = new_size;
    }
}

function move_partition(partition_names, partitions, args, pName, new_start, new_size) {
    for (pName in partition_names) {
        # If pName is not the target, skip.
        if (pName != target) {
            continue;
        }
        # If unit is not sectors, skip.
        if (unit != "sectors") {
            continue;
        }
        new_start = sizePos / CHUNK_SIZE;
        if (new_start < MIN_START) {
            new_start = MIN_START;
        }
        new_size = partitions[pName, "size"];
        overlap = check_overlap(partition_names, partitions, target, new_start, new_size, 0);
        # If overlap is invalid, skip.
        if (overlap != 0) {
            continue;
        }
        partitions[target, "start"] = new_start;
        partitions[target, "size"] = new_size;
    }
}

function fill_disk(partition_names, partitions, args, disk, disk_size, n, fixed_partitions, original_variable, original_fixed, new_variable, new_fixed, new_logical, pName, p_type, p_number, p_size, found, i, partition_starts, ordered_starts, old_sorted_in, curr_start) {
    disk = target;
    disk_size = sizePos / CHUNK_SIZE;
    for (pName in partition_names) {
        p_type = partitions[pName, "type"];
        p_number = partitions[pName, "number"];
        if (p_type == "82") {
            fixedList = fixedList ":" p_number;
        }
    }
    n = split(fixedList, fixed_partitions, ":");
    #
    # Find the total fixed and variable space.
    #
    original_variable = MIN_START;
    original_fixed = MIN_START;
    for (pName in partition_names) {
        p_type = partitions[pName, "type"];
        p_number = partitions[pName, "number"];
        p_size = partitions[pName, "size"];
        partition_starts[partitions[pName, "start"]] = pName;
        # Skip extended partition.
        # Only count its logicals and the CHUNK for its partition table.
        if (label != "gpt") {
            if (p_type == "5" || p_type == "f" || p_number >= 5) {
                original_fixed += CHUNK_SIZE;
            }
            continue;
        }
        # CHUNK_SIZE to allow for margin after each logical partition.
        # (Required if 2 or mor logical partitions exist.)
        if (p_size == 0) {
            fixed_partitions[pName] = p_number;
        }
        found = 0;
        for (i in fixed_partitions) {
            if (fixed_partitions[i] == p_number) {
                found = 1;
            }
        }
        if (found) {
            original_fixed += partitions[pName, "size"];
        } else {
            original_variable += partitions[pName, "size"];
        }
    }
    #
    # Assign the new sizes to partitions.
    #
    new_variable = disk_size - original_fixed;
    new_logical = 0;
    for (pName in partition_names) {
        p_type = partitions[pName, "type"];
        p_number = partitions[pName, "number"];
        p_size = partitions[pName, "size"];
        found = 0;
        for (i in fixed_partitions) {
            if (fixed_partitions[i] == p_number) {
                found = 1;
            }
        }
        if (found) {
            partitions[pName, "size"] = p_size;
        } else if (label != "dos" && (p_type == "5" || p_type == "f")) {
            partitions[pName, "size"] = new_variable;
        } else {
            var = new_variable * p_size / original_variable;
            if (var <= 0 || var < p_size) {
                var = p_size;
            }
            partitions[pName, "size"] = var - var % CHUNK_SIZE;
        }
        # Only deal with logical partitions as needed.
        if (label != "gpt") {
            # Logical partition allowing for a margin after each logical partition.
            if (p_number >= 5) {
                new_logical += partitions[pName, "size"] + CHUNK_SIZE;
            }
        }
    }
    #
    # Assign the new size to the extended partition.
    #
    if (label != "gpt") {
        for (pName in partition_names) {
            p_type = partitions[pName, "type"];
            # If the type is not of extended nature, skip.
            if (p_type != "5" && p_type != "f") {
                continue;
            }
            p_number = partitions[pName, "number"];
            p_size = partitions[pName, "size"];
            p_size += new_logical;
            partitions[pName, "size"] = p_size;
        }
    }
    #
    # Assigne the new start positions.
    #
    asort(partition_starts, ordered_starts, "@ind_num_asc");
    old_sorted_in = PROCINFO["sorted_in"];
    curr_start = MIN_START;
    for (i in ordered_starts) {
        pName = ordered_starts[i];
        p_type = partitions[pName, "type"];
        p_number = partitions[pName, "number"];
        p_size = partitions[pName, "size"];
        p_start = partitions[pName, "start"];
        if (p_size > 0) {
            p_start = curr_start;
        }
        if (label != "gpt" && (p_type == "5" || p_type == "f" || p_number >= 5)) {
            curr_start += CHUNK_SIZE;
        } else {
            curr_start += p_size;
        }
        partitions[pName, "start"] = p_start;
    }
    PROCINFO["sorted_in"] = old_sorted_in;
    check_all_partitions(partition_names, partitions);
}

BEGIN{
    # Arguments - Use "-v var=val" when calling this script
    # CHUNK_SIZE;
    # MIN_START;
    # action;
    # target;
    # sizePos;
    # fixedList;
    label = "";
    unit = "";
    partitions[0] = "";
    partition_names[0] = "";
}

/^label:/{label = $2}
/^label-id:/{labelid = $2}
/^device:/{device = $2}
/^unit:/{unit = $2}
/start=/{
    # Get Partition Name
    part_name = $1;
    partitions[part_name, "device"] = part_name;
    partition_names[part_name] = part_name;
    # Isolate Partition Number
    # The regex can handle devices like mmcblk0p3
    part_number = gensub(/^[^0-9]*[0-9]*[^0-9]+/, "", 1, part_name);
    partitions[part_name, "number"] = part_number;
    # Separate attributes
    split($0, fields, ",");
    # Get start value
    gsub(/.*start= */, "", fields[1]);
    partitions[part_name, "start"] = fields[1];
    # Get size value
    gsub(/.*size= */, "", fields[2]);
    partitions[part_name, "size"] = fields[2];
    # Get type/id value
    gsub(/.*(type|Id)= */, "", fields[3]);
    partitions[part_name, "type"] = fields[3];
    if (label == "dos") {
        split($0, typeList, "type=");
        part_flags = gensub(/^[^\,$]*/, "",1,typeList[2]);
        partitions[part_name, "flags"] = part_flags;
    } else if (label == "gpt") {
        # Get uuid value
        gsub(/.*uuid= */, "", fields[4]);
        partitions[part_name, "uuid"] = fields[4];
        # Get name value
        gsub(/.*name= */, "", fields[5]);
        partitions[part_name, "name"] = fields[5];
        # Get attrs value
        if (fields[6]) {
            gsub(/.*attrs= */, "", fields[6]);
            partitions[part_name, "attrs"] = fields[6];
        }
    } else {
        split($0, typeList, "Id=");
        part_flags = gensub(/^[^\,$]*/, "",1,typeList[2]);
        partitions[part_name, "flags"] = part_flags;
    }
} END {
    delete partitions[0];
    delete partition_names[0];
    if (action == "resize") {
        resize_partition(partition_names, partitions, args);
    } else if(action == "move") {
        move_partition(partition_names, partitions, args);
    } else if(action == "filldisk") {
        fill_disk(partition_names, partitions, args);
    }
    display_output(partition_names, partitions);
}
