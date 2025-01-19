# Backup PassHub database

## Database backup architecture

- Daily cron calls the `/var/lib/passhub.backup/do_backup.sh` script.

- The script dumps the database with the mongodump utility into `${passhubInstanceUrl}.${date}.archive`
- In order to limit the required disk space, all files older than 30 days are deleted, excluding only one. Namely, files created on a specific date, in our case on the 15th of every month, are left untouched.

- If the S3 access key file is available, the archive file is encrypted with a public asymmetric key, producing the *.enc file.

- S3 space (e.g., AWS, Digital Ocean, etc.) is mounted.
- The *.enc file is copied to the S3 spaces.
- S3 drive is unmounted.

used configuration:

1. mongodump: MongoDB connection string + database name; see examples `simple.passhub.url.cfg` (for a single passwordless localhost server) and `replca_auth.passhub.url.cfg` (replicated database with username/password authentication).
2. `s3_key`: access to the S3 cloud
3. `public.pem`: asymmetric cipher key

## Details: 

For every passhub instance, e.g., "my.passhub.us," create a one-liner file "my.passhub.us.cfg," containing the URL (connection line) of the particular database. E.g.,

- simplest case (no password, no replicas):
 ```
        uri: mongodb://127.0.0.1/phub-pub34
 ```     
  
- password and replica
 
  ```
    uri: mongodb://UUU:PPP@server1:port1,server2:port2,server3:port3/dbname?replicaSet=replicaname&authSource=ssss
  ```
The script do_backup.sh creates all the other subdirs automatically on the run.


## External storage:

It is possible to keep encrypted copies of a database on any AWS S3-compatible storage.

When using an external S3 service, first create an asymmetric key pair, keep private the key `private.pem` in a secure place, and put the `public.pem` into the backup working directory /var/lib/passhub.backup/.

```
openssl genrsa  -out private.pem 2048
openssl rsa -in private.pem -outform PEM -pubout -out public.pem
```

In the unlikely event you need to decode an encrypted backup file, copy it, e.g., to your local computer with the private.pem key, and run

```
./decrypt.py your.passhub.YYMMDD.archive.enc
```

The encrypted backup file is in the `/var/lib/passhub.backup/s3bucket/backup/{passhub-instance}` directory. 

The output of the command is an unencrypted mongodump file `your.passhub.YYMMDD.archive`.

## installation

Just copy files in this directory:


`etc/cron.dayly/passhub-backup` -> `/etc/cron.dayly/passhub-backup`

And all the directory 

`var/lib/passhub.backup/*` `var/lib/passhub.backup/`

Store your private.pem file elsewhere in a safe place.


