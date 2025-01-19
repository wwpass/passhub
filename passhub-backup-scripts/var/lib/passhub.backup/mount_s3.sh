#!/bin/bash
folder=/var/lib/passhub.backup

s3fs phub $folder/s3bucket -o passwd_file=$folder/s3_key -o url=https://fra1.digitaloceanspaces.com/ -o use_path_request_style

