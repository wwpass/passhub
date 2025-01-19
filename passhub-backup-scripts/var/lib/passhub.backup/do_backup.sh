#!/bin/bash

folder=$(dirname "$0")

if [ -f $folder/s3_key ]; then
    chmod 400 $folder/s3_key

    if [ ! -d $folder/s3bucket ]; then
        mkdir $folder/s3bucket
    fi;

    $folder/mount_s3.sh

    if [ ! -d $folder/s3bucket/backup ]; then
        mkdir $folder/s3bucket/backup
    fi;
fi;

for filename in $folder/*.cfg; do
        instance0=$(basename $filename)
        instance=${instance0%.*}

        if [ ! -d $folder/$instance ]; then
            mkdir $folder/$instance
        fi;

        d=`date +%y%m%d`

        mongodump --config=$folder/${instance}.cfg  --excludeCollection phpsession  --gzip --archive=$folder/$instance/$instance.${d}.archive


        if [ -f $folder/s3_key ]; then
	        if [ ! -d $folder/s3bucket/backup/$instance ]; then
		        mkdir $folder/s3bucket/backup/$instance
	        fi;
            $folder/encrypt.py $folder/$instance/$instance.${d}.archive $folder/$instance/$instance.${d}.archive.enc
            cp $folder/$instance/$instance.${d}.archive.enc $folder/s3bucket/backup/$instance
        fi;

        day=`date --date="30 day ago" +%d`

        if [ "$day" -ne 15 ]; then
            d=`date --date="30 day ago" +%y%m%d`
            echo $d;
            rm -f $folder/$instance/$instance.${d}.*

            if [ -f $folder/s3_key ]; then
                rm -f $folder/s3bucket/backup/$instance/$instance.${d}.*
            fi;
            
        fi;
done

if [ -f $folder/s3_key ]; then
    $folder/umount_s3.sh
fi;
