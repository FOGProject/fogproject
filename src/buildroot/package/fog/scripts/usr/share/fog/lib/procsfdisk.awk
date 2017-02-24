#!/usr/bin/awk -f

# $data is the filename of the output of sfdisk -d
# cat $data | awk -F, '\

function check_all_partitions(partition_names, partitions, pName, p_start, p_size) {
    for (pName in partition_names) {
        p_start = partitions[pName, "start"];
        p_size = partitions[pName, "size"];
        overlap = check_overlap(partition_names, partitions, pName, p_start, p_size, 0);
        # If overlap is ok, skip.
        if (overlap == 0) {
            continue;
        }
        printf("# ERROR in new partition table, quitting.\n");
        printf("# ERROR: %s has an overlap.\n", pName);
        break;
    }
    # Only print consistent overlap is safe.
    if (overlap == 0) {
        printf("# Partition table is consistent.\n");
    }
    return 1;
}

function check_overlap(partition_names, partitions, new_part_name, new_start, new_size, capture, extended_margin, new_type, new_part_number, pName, p_type, p_start, p_size, p_part_number) {
    # Used for extended volumes (logical disks)
    extended_margin = 2;
    new_type = partitions[new_part_name, "type"];
    new_part_number = partitions[new_part_name, "number"];
    for (pName in partition_names) {
        p_type = partitions[pName, "type"];
        p_size = partitions[pName, "size"];
        p_start = partitions[pName, "start"];
        p_part_number = partitions[pName, "number"];
        # No overlap with self.
        if (new_part_name == pName) {
            continue;
        }
        # Ignore empty partitions.
        if (p_size == 0) {
            continue;
        }
        # Extended partitions must overlap logical partitions.
        # But leave room for the extended partition table.
        if (label == "dos") {
            if (p_type == "5" || p_type == "f") {
                if (new_start < p_start + extended_margin) {
                    return 1;
                } else if (new_start >= p_start + p_size) {
                    return 1;
                } else if (new_start + new_size <= p_start + extended_margin) {
                    return 1;
                } else if (new_start + new_size > p_start + p_size) {
                    return 1;
                }
            } else if (new_type == "5" || new_type == "f") {
                # If part number is 1-4 skip.
                if (new_part_number < 5) {
                    continue;
                } else if (p_start < new_start + extended_margin) {
                    return 1;
                } else if (p_start >= new_start + new_size) {
                    return 1;
                } else if (p_start + p_size <= new_start + extended_margin) {
                    return 1;
                } else if (p_start + p_size > new_start + new_size) {
                    return 1;
                }
            }
        } else {
            # We need to adjust the start positions for smaller/larger
            # drives to test overlap accurately.
            # Otherwise, captures would almost always have an overlap.
            # Equal partitions will remain untouched.
            if (capture > 0) {
                if (new_size < p_size) {
                    p_start -= new_size;
                } else if (new_size > p_size) {
                    p_start += new_size;
                }
            }
            if (new_start >= p_start) {
                if (new_start < p_start + p_size) {
                    return 1;
                }
            }
        }
    }
    return 0;
}

function display_output(partition_names, partitions, pName) {
    if (!unit) {
        unit = "sectors";
    }
    typelabel = "type=";
    if (!label) {
        typelabel = "Id=";
    }
    if (label && labelid && device) {
        printf("label: %s\nlabel-id: %s\ndevice: %s\n", label, labelid, device);
    }
    printf("unit: %s\n\n", unit);
    for (pName in partition_names) {
        dev = partitions[pName, "device"];
        start = partitions[pName, "start"];
        size = partitions[pName, "size"];
        type = partitions[pName, "type"];
        printf("%s : start=%10d, size=%10d, %s%2s", dev, start, size, typelabel, type);
        # If label is gpt do these things.
        # else we need the flags.
        if (label == "gpt") {
            uuid = partitions[pName, "uuid"];
            name = partitions[pName, "name"];
            attrs = partitions[pName, "attrs"];
            if (uuid != "") {
                printf(", uuid=%s", uuid);
            }
            if (name != "") {
                printf(", name=%s", name);
            }
            if (attrs != "") {
                printf(", attrs=%s", attrs);
            }
        } else {
            flag = partitions[pName, "flags"];
            if (flag != "") {
                printf("%s", flag);
            }
        }
        printf("\n");
    }
}

function resize_partition(partition_names, partitions, args, pName, new_start, new_size) {
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
            if (var == 0) {
                var = int(p_size);
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
