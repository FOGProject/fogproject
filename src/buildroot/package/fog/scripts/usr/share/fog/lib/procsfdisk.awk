#!/usr/bin/awk -f
#
# This script will shrink (resize) partitions based on the new partition size passed in.
# NOTE: This does not shrink the volume, just the parititon layout information.
# This script will fill disks (expand) with the relevant data passed on based on the disk size passed in.
#
# Usage Passed in Variables:
#
# CHUNK_SIZE  The default block size for the disk/partition being used. Typically 512
# MIN_START   The minimum start position for the first partition. Typically 2048.
# action      The action to perform with the script.
#  1. filldisk Fills the disk.
#  2. move     Moves partitions.
#  3. resize   Shrinks partitions.
# target      The target to work with.
#  1. filldisk The target is the disk in whole.
#  2. move     The target is the current partition.
#  3. resize   The target is the current partition.
# sizePos     The size to change.
#  This is only used for move/resize. Used to tell
#   the new size of the disk.
# diskSize    The disk size.
# fixedList   The partition sizes that should remain untouched in size.

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
        p_start = int(partitions[pName, "start"]);
        # The current iteration's size value.
        p_size = int(partitions[pName, "size"]);
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
# p_number is locally scoped, but could be set here if wanted. Will be overwritten anyway.
# Global Scoped variables (meaning not needed to pass to the function:
# label the device label.
function check_overlap(partition_names, partitions, new_part_name, new_start, new_size, capture, extended_margin, new_type, new_part_number, pName, p_type, p_start, p_size, p_number) {
    # Used for extended volumes (logical disks)
    extended_margin = 2;
    # Set new_type variable. (Partition Type -- extended, ntfs, ext4, etc...)
    new_type = int(partitions[new_part_name, "type"]);
    # Set new_part_number variable. Self explanatory.
    new_part_number = int(partitions[new_part_name, "number"]);
    # Iterate our aprtitions.
    for (pName in partition_names) {
        # Partitions will not overlap themselves, so skip.
        if (new_part_name == pName) {
            continue;
        }
        # Set the type variable from original layout.
        p_type = partitions[pName, "type"];
        # Set the size of the original drive.
        p_size = int(partitions[pName, "size"]);
        # Set the start of the original drive.
        p_start = int(partitions[pName, "start"]);
        # Set the partition number.
        p_number = int(partitions[pName, "number"]);
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
            # partitions[pName, "start"] = p_start;
            # If the new type is an extended type:
            # Extended only happens on non-gpt disks.
            # we need to add at least the extended element.
            # If partition is 5 or more, we need to add the start
            # plus the chunk size.
            if (label != "gpt") {
                if (new_type == "5" || new_type == "f") {
                    p_start += extended_margin + int(CHUNK_SIZE);
                } else if (p_number > 4) {
                    p_start += int(CHUNK_SIZE);
                }
            }
        }
        if (label != "gpt") {
            if (p_type == 5 || p_type == "f") {
                if (new_start >= p_start) {
                    if (new_start < p_start + extended_margin) {
                        return 1;
                    }
                }
            }
        } else if (new_start >= p_start) {
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
# Global Scoped variables (meaning not needed to pass to the function:
# unit the unit type
# label the device label
# labelid the device label id
# device the device itself
function display_output(partition_names, partitions, pName, p_device, p_start, p_size, p_type, p_flag, p_uuid, p_name, p_attrs, typelabel) {
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
    printf("unit: %s\n", unit);
    # If the first lba field is set store it too.
    if (firstlba) {
        printf("first-lba: %d\n", firstlba);
    }
    if (lastlba) {
        printf("last-lba: %d\n", lastlba);
    }
    printf("\n");
    # Iterate our partition names.
    for (pName in partition_names) {
        # Set our p_device variable.
        p_device = partitions[pName, "device"];
        # Set our p_start variable.
        p_start = int(partitions[pName, "start"]);
        # set our p_size variable.
        p_size = int(partitions[pName, "size"]);
        # set our p_type variable.
        p_type = partitions[pName, "type"];
        # Write our data into the sfdisk file.
        printf("%s : start=%12d, size=%12d, %s%s", p_device, p_start, p_size, typelabel, p_type);
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

# Resizes the partition, currently really just shrinks.
# Requires partition_names, partitions, and args.
# partition_names is an array of all the partition names.
# partitions is an array containing the data we need.
#  Partitions are sent in an array of form:
#   partitions['name']['start'] = value;
#   partitions['name']['size'] = value;
# args are the arguments from the caller.
# pName is locally scoped, but could be set here if wanted. Will be overwritten anyway.
# new_size is locally scoped, but could be set here if wanted. Will be overwritten anyway.
# p_start is locally scoped, but could be set here if wanted. Will be overwritten anyway.
# Global Scoped variables (meaning not needed to pass to the function:
# target the device to work off of
# unit the unit type
function resize_partition(partition_names, partitions, args, pName, new_size, p_start) {
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
        # Set our p_start position to the current start.
        p_start = int(partitions[pName, "start"]);
        # Ensure start postition is aligned properly.
        new_size = int(sizePos) / int(CHUNK_SIZE);
        # Check the overlap.
        overlap = check_overlap(partition_names, partitions, target, new_start, new_size, 1);
        # If there was an issue in checking overlap, skip.
        if (overlap != 0) {
            continue;
        }
        # Sets the new start position.
        # This function currently is only called
        # for capture, so this shouldn't change.
        # Left here for the future to allow changing
        # if we needed to.
        # Ultimately to switch the start position, we
        # could do something like below from check_overlap:
        # partitions[target, "start"] = p_start;
        # partitions[target, "start"] = p_start;
        # Sets the new size which is passed into the script
        # directly. As long as no overlap we know the shrunk
        # size is safe.
        partitions[target, "size"] = new_size;
    }
    if (lastlba) {
        lastlba = int(diskSize) - int(firstlba);
    }
    return 0;
}

# Moves the partitions around as needed.
# Requires partition_names, partitions, and args.
# partition_names is an array of all the partition names.
# partitions is an array containing the data we need.
#  Partitions are sent in an array of form:
#   partitions['name']['start'] = value;
#   partitions['name']['size'] = value;
# args are the arguments from the caller.
# pName is locally scoped, but could be set here if wanted. Will be overwritten anyway.
# new_start is locally scoped, but could be set here if wanted. Will be overwritten anyway.
# new_size is locally scoped, but could be set here if wanted. Will be overwritten anyway.
# Global Scoped variables (meaning not needed to pass to the function:
# target the device to work off of
# unit the unit type
# MIN_START is the minimum start point of the drive
function move_partition(partition_names, partitions, args, pName, new_start, new_size) {
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
        # Ensure start postition is aligned properly.
        new_start = int(sizePos) / int(CHUNK_SIZE);
        # If the new_start is less than the MIN_START
        # ensure the new_start is equal to the min start point.
        if (new_start < int(MIN_START)) {
            new_start = int(MIN_START);
        }
        # Set the new size variable.
        new_size = int(partitions[pName, "size"]);
        # Ensure our values are adjusted.
        partitions[target, "start"] = new_start;
    }
    return 0;
}

# Fill the disk space.
# Requires partition_names, partitions, and args.
# partition_names is an array of all the partition names.
# partitions is an array containing the data we need.
#  Partitions are sent in an array of form:
#   partitions['name']['start'] = value;
#   partitions['name']['size'] = value;
# args are the arguments from the caller.
# fixed_partitions is locally scoped and will be overwritten. Used to contain our fixed partitions.
# original_variable is locally scoped and will be overwritten. Used to figure originating resizable space.
# original_fixed is locally scoped and will be overwritten. Used to contain the fixed partition space.
# new_variable is locally scoped and will be overwritten. Used to contain the new disks resizable space.
# new_logical is locally scoped and will be overwritten. Used to contain the logical volume space.
# extended_margin is locally scoped and will be overwritten. Used to give logic start + extended which is typically 2.
# pName is locally scoped, but could be set here if wanted. Will be overwritten anyway.
# p_type is locally scoped, but could be set here if wanted. Will be overwritten anyway.
# p_number is locally scoped, but could be set here if wanted. Will be overwritten anyway.
# p_size is locally scoped, but could be set here if wanted. Will be overwritten anyway.
# p_fixed is locally scoped, but could be set here if wanted. Will be overwritten anyway.
# new_start is locally scoped, but could be set here if wanted. Will be overwritten anyway.
# new_size is locally scoped, but could be set here if wanted. Will be overwritten anyway.
function fill_disk(partition_names, partitions, args, n, fixed_partitions, original_variable, original_fixed, new_variable, new_logical, extended_margin, pName, p_type, p_number, p_size, p_fixed, i, partition_starts, ordered_starts, old_sorted_in, curr_start) {
    # Set p_fixed to 0.
    p_fixed = 0;
    # Used for extended volumes (logical disks)
    extended_margin = 2;
    # Variable should be 0.
    original_variable = 0;
    new_logical = 0;
    # Fixed should be MIN_START.
    original_fixed = int(MIN_START);
    # Iterate partitions. This loop checks for swap
    # partitions. A fail safe to ensure swap is fixed.
    for (pName in partition_names) {
        # Set p_type variable.
        p_type = partitions[pName, "type"];
        # Set p_number variable.
        p_number = int(partitions[pName, "number"]);
        # Set p_start variable.
        p_start = int(partitions[pName, "start"]);
        # Set partition starts.
        partition_starts[p_start] = pName;
        # Regex setter.
        regex = "/^"p_number"$|^"p_number":|:"p_number":|:"p_number"$/";
        # Set p_size variable.
        p_size = int(partitions[pName, "size"]);
        if (label != "gpt") {
            if (p_type == 5 || p_type == "f") {
                new_logical = p_size - int(MIN_START);
                continue;
            } else if (p_number > 4) {
                new_logical -= int(MIN_START);
                if (p_type == 82) {
                    if (fixedList ~ regex) {
                        new_logical -= p_size;
                        continue;
                    }
                    fixedList = fixedList":"p_number;
                }
                if (fixedList ~ regex) {
                    new_logical -= p_size;
                    continue;
                }
            }
        }
        # If p_type is not 82 (swap), check next partition.
        if (p_type == 82) {
            original_fixed += p_size;
            if (fixedList ~ regex) {
                continue;
            }
            fixedList = fixedList":"p_number;
            continue;
        }
        # If partition is in fixed list set fixed size and continue;
        if (fixedList ~ regex) {
            original_fixed += p_size;
        } else {
            original_variable += p_size;
        }
    }
    # Assign the new sizes.
    new_variable = int(diskSize) - original_fixed;
    # We will loop the partitions again to get sizes.
    for (pName in partition_names) {
        # Reset our p_type variable.
        p_type = partitions[pName, "type"];
        # Reset our p_number variable.
        p_number = int(partitions[pName, "number"]);
        # Reset our p_start variable.
        p_start = int(partitions[pName, "start"]);
        # Reset our p_size variable.
        p_size = int(partitions[pName, "size"]);
        # Regex setter.
        regex = "/^"p_number"$|^"p_number":|:"p_number":|:"p_number"$/";
        # No worrying about 0 sized partitions.
        if (p_size == 0) {
            continue;
        }
        # Extended/Logical partition processing.
        if (label != "gpt") {
            # Logical partitions are any greater than 4.
            # The extendended partition is of p_types 5 or f.
            if (p_type == "5" || p_type == "f") {
                new_adjusted = new_variable * p_size / original_variable;
                p_size = new_adjusted - new_adjusted % int(CHUNK_SIZE);
                partitions[pName, "size"] = p_size;
                continue;
            } else if (p_number > 4) {
                if (fixedList ~ regex) {
                    continue;
                }
                new_adjusted = new_logical;
                p_size = new_adjusted - new_adjusted % int(CHUNK_SIZE);
                partitions[pName, "size"] = p_size;
                continue;
            }
        }
        # If a fixed partition, go to next.
        if (fixedList ~ regex) {
            continue;
        }
        # Get's the percentage increase/decrease and makes adjustment.
        new_adjusted = new_variable * p_size / original_variable;
        # Ensure we're aligned.
        p_size = new_adjusted - new_adjusted % int(CHUNK_SIZE);
        # Ensure the partition size is setup.
        partitions[pName, "size"] = p_size;
    }
    # Assign the new start positions.
    asort(partition_starts, ordered_starts, "@ind_num_asc");
    # sort our stuff.
    old_sorted_in = PROCINFO["sorted_in"];
    # curr-start must be set to MIN_START initially.
    curr_start = int(MIN_START);
    # Iterate the ordered start positions.
    for (i in ordered_starts) {
        # pName will be the ordered start position.
        pName = ordered_starts[i];
        # Set p_type.
        p_type = partitions[pName, "type"];
        # Set p_number.
        p_number = int(partitions[pName, "number"]);
        # Set p_size.
        p_size = int(partitions[pName, "size"]);
        # Set p_start.
        p_start = int(partitions[pName, "start"]);
        # Skip empty sized partitions.
        if (p_size == 0) {
            continue;
        }
        # p_start is adjusted to whatever curr_start is.
        p_start = curr_start;
        # If we are not GPT test for logical/extended partitions
        # And ensure our current start is give just the chunk size
        # for an even balance.
        if (label != "gpt") {
            # The start position for the extended/logical partitions
            # needs to be increased by the chunk size.
            # Otherwise increase it by the p_size.
            if (p_type == "5" || p_type == "f") {
                partitions[pName, "start"] = p_start;
                curr_start += int(MIN_START);
                continue;
            } else if (p_number > 4) {
                p_start += int(MIN_START);
            }
        }
        curr_start += p_size;
        # Set the partitions start to our adjusted start.
        partitions[pName, "start"] = p_start;
    }
    # Sorted in setter.
    PROCINFO["sorted_in"] = old_sorted_in;
    # Set our lastlba
    if (firstlba) {
        lastlba = int(diskSize) - int(firstlba);
    }
    # Check for any overlaps.
    return check_all_partitions(partition_names, partitions);
}
# This is where it all begins (See->BEGIN) :)
BEGIN {
    # Arguments - Use "-v var=val" when calling this script
    # CHUNK_SIZE;
    # MIN_START;
    # action;
    # target;
    # sizePos;
    # diskSize;
    # fixedList;
    label = "";
    unit = "";
    partitions[0] = "";
    partition_names[0] = "";
}
# Set label global variable
/^label:/{label = $2}
# Set labelid global variable
/^label-id:/{labelid = $2}
# Set device global variable
/^device:/{device = $2}
# Set unit global variable
/^unit:/{unit = $2}
# Get the first lba sector.
/^first-lba:/{firstlba = $2}
# Get the last lba sector.
/^last-lba:/{lastlba = $2}
# Get the start positions
/start=/{
    # Get Partition Name
    part_name = $1;
    # Start setup of partitions.
    partitions[part_name, "device"] = part_name;
    # Start setup of partition_names.
    partition_names[part_name] = part_name;
    # Isolate Partition Number.
    # The regex can handle devices like mmcblk0p3
    part_number = gensub(/^[^0-9]*[0-9]*[^0-9]+/, "", 1, part_name);
    # Set the partitions number.
    partitions[part_name, "number"] = part_number;
    # Separate attributes
    split($0, fields, ",");
    # Get start value
    gsub(/.*start= */, "", fields[1]);
    # Set start point.
    partitions[part_name, "start"] = fields[1];
    # Get size value
    gsub(/.*size= */, "", fields[2]);
    # Set size.
    partitions[part_name, "size"] = fields[2];
    # Get type/id value
    gsub(/.*(type|Id)= */, "", fields[3]);
    # Set type.
    partitions[part_name, "type"] = fields[3];
    # Sets up based on dos/gpt label types.
    # Sets defaults if unit type is neither.
    if (label == "dos") {
        split($0, typeList, "type=");
        # Sets the partition flags.
        part_flags = gensub(/^[^\,$]*/, "",1,typeList[2]);
        # Ensure object has the flags defined.
        partitions[part_name, "flags"] = part_flags;
    } else if (label == "gpt") {
        # Get uuid value
        gsub(/.*uuid= */, "", fields[4]);
        # Set the uuid in the object
        partitions[part_name, "uuid"] = fields[4];
        # Get name value
        gsub(/.*name= */, "", fields[5]);
        # Set the name in the object
        partitions[part_name, "name"] = fields[5];
        # Get attrs value
        if (fields[6]) {
            gsub(/.*attrs= */, "", fields[6]);
            # Sets the attrs int the object.
            partitions[part_name, "attrs"] = fields[6];
        }
    } else {
        split($0, typeList, "Id=");
        # Gets the partition flags.
        part_flags = gensub(/^[^\,$]*/, "",1,typeList[2]);
        # Sets the flags to the object.
        partitions[part_name, "flags"] = part_flags;
    }
} END {
    # Clean up.
    delete partitions[0];
    delete partition_names[0];
    # If the action value is resize run resize_partition function.
    # If the action value is move run the move_partition function.
    # If the action value is filldisk run the fill_disk function.
    # If the action value is neither of the above fail out.
    switch (action) {
        case "resize":
            resize_partition(partition_names, partitions, args);
            break;
        case "move":
            move_partition(partition_names, partitions, args);
            break;
        case "filldisk":
            fill_disk(partition_names, partitions, args);
            break;
        default:
            printf("Please enter a proper action.\n");
            exit(1);
    }
    # Display output.
    display_output(partition_names, partitions);
}
