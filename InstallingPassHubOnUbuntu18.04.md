# Installing PassHub on Ubuntu 18.04

## About This Document

PassHub is a web-based password manager for individuals and teams with support for client-side encryption. PassHub relies on WWPass authentication and data encryption technology and can work both with hardware WWPass PassKey and WWPass PassKey Lite smartphone application.

In this guide, we'll discuss how to get PassHub installed on your Ubuntu 18.04
server.

Practical knowledge of web server deployment is required, including DNS configuration and  SSL certificates.  

## Prerequisites

To deploy Passhub, you should set up Ubuntu 18.04 Server and
configure a regular, non-root user with `sudo` privileges. The following hardware requirements should be met:

- CPU: 1 Core
- RAM: 2 GB
- Storage: 30 GB (SSD is preferred, but optional)

These requirements may need to be adjusted depending on the number
of PassHub users.

Your server should be accessible either publicly or within your organization network and have a DNS name configured. An internet connection is needed both for the purposes of this guide and regular operation. A valid (not self-signed) SSL certificate is required so that your server can be accessed via HTTPS.

PassHub requires a WWPass Service Provider certificate, which can be obtained at https://developers.wwpass.com.

## Step 1: Install the Nginx Web Server

Since this is our first interaction with the apt packaging system in this session, we should update our local package index, so that we have access to the most recent versions of packages. After that, Nginx can be installed.

```sh
sudo apt update
sudo apt install -y nginx
```

On Ubuntu 18.04, Nginx is configured to start running upon installation.

## Step 2.1: Install MongoDB Database

```sh
sudo apt install mongodb
```

## Step 3: Install PHP

Type the following command to install PHP and additional modules required by PassHub:

```sh
sudo apt install -y php-fpm php-curl php-mbstring php-mongodb php-mail php-pear php-net-smtp
```

Finally, you need to restart php7.2-fpm so that your configuration changes take effect:

```sh
sudo service php7.2-fpm restart
```

## Step 3.1: Install PHP Composer

```sh
sudo apt install composer
```

Some VPS distributions of Ubuntu 18.04 come without zip/unzip tools, required by composer. Install them as follows:

```sh
sudo apt install zip unzip
```

## Step 4: Extract PassHub Files

Download [passhub](https://github.com/wwpass/passhub/releases/download/v1.0.0/passhub.business.20190514.tgz) archive and put it to your server home directory.

We need to extract the contents of the archive into the `/var/www` directory:

```sh
cd /var/www
sudo tar xvzf ~/passhub.tgz
```

Change ownership of the extracted files:

```sh
sudo chown -R username:www-data /var/www/passhub
```

**Note**: don't forget to change the `username` with the actual name of your account.

## Step 4.1 Install PHP libraries

In the _/var/www/passhub_ directory run composer:

```sh
sudo composer install
```

## Step 4.2 Create working directories

We also need to create PassHub log and working directories:

```sh
sudo mkdir /var/log/passhub
sudo chown www-data:www-data /var/log/passhub
sudo mkdir /var/lib/passhub
sudo chown www-data:www-data /var/lib/passhub
```

## Step 5: Configure Nginx Server

To configure Nginx web server, we need to obtain two SSL certificates: first, the HTTPS certificate to protect web connection and second - WWPass Service Provider certificate for PassHub.

Final Nginx configuration depends on many factors, particularly if the PassHub is the only service or there are more then one already existing URLs served by Nginx. If PassHub is not the first destination, you are probably already experienced enough to adapt following instructions to your needs.

Here are the steps for freshly installed Nginx.

### 5.1 PassHub URL

Start with selecting URL for the PassHub service, e.g. 'passhub.yourcompany.com'. Set your DNS accordingly.

### 5.1 SSL certificates

Obtain the SSL certificate from Certificate authority of your choice. We recommend [Let's Encrypt CA](https://letsencrypt.org/).

### 5.2 WWPass certificates

PassHub requires a WWPass Service Provider certificate, which can be obtained at [WWPass developer](https://developers.wwpass.com) site. For new Nginx deployment, you can use `/var/www/html` directory to store the verification file.

### 5.3 Nginx configuration  

We need to create a new Nginx configuration file for our PassHub installation.

Create a new configuration file by typing:

```sh
sudo nano /etc/nginx/sites-available/passhub.conf
```

Add the following content to the file:

```
server {
  listen 80;
  listen [::]:80;
  server_name example.com;
  location / {
    rewrite ^(.*)$ https://example.com$1;
  }
}
server {
  listen 443 ssl http2;
  listen [::]:443 ssl http2;
  server_name example.com;
  ssl on;
  ssl_certificate /path/to/ssl/certificate/fullchain.pem;
  ssl_certificate_key /path/to/ssl/certificate/privkey.pem;
  root /var/www/passhub;
  index index.php index.html index.htm;
  location ~/(config|helpers|src) {
    deny all;
    return 404;
  }
  location ~ \.php$ {
    include snippets/fastcgi-php.conf;
    fastcgi_pass unix:/run/php/php7.2-fpm.sock;
  }
}
```

**Notes**:

1. Change ```example.com``` to the DNS name of your server;
2. Make sure ```ssl_certificate``` and ```ssl_certificate_key``` point to
existing SSL certificate files;

Save and close the file when you are finished.

Next, create a symbolic link in the ```/etc/nginx/sites-enabled/```
directory so that Nginx can pick up the configuration file we just created:

```sh
sudo ln -s /etc/nginx/sites-available/passhub.conf /etc/nginx/sites-enabled/passhub.conf
```

Now it is time to test our Nginx configuration for possible errors:

```sh
sudo nginx -t
```

If everything is correct, you will see the following output:

```sh
nginx: the configuration file /etc/nginx/nginx.conf syntax is ok
nginx: configuration file /etc/nginx/nginx.conf test is successful
```

If Nginx reports configuration errors and you see output like this:

```sh
nginx: configuration file /etc/nginx/nginx.conf test failed
```

Revise your Nginx configuration and re-test.

Once your Nginx configuration contains no errors, it is time to
tell Nginx to reload configuration data:

```sh
sudo nginx -s reload
```

**Tip**: to temporarily disable your PassHub instance in Nginx, remove the symbolic link in the ```/etc/nginx/sites-enabled/```
directory and reload Nginx configuration like this:

```sh
sudo rm /etc/nginx/sites-enabled/passhub.conf
sudo nginx -s reload
```

To re-enable Nginx, just re-create the symbolic link and reload Nginx configuration:

```sh
sudo ln -s /etc/nginx/sites-available/passhub.conf /etc/nginx/sites-enabled/passhub.conf
sudo nginx -s reload
```

## Step 6: Adjust PassHub Configuration

We now need to create a new PassHub configuration using the sample configuration file bundled with the PassHub distribution.

Create a new configuration file by copying ```config-sample.php```:

```sh
sudo cp /var/www/passhub/config/config-sample.php /var/www/passhub/config/config.php
```

And open it in a text editor:

```sh
sudo nano /var/www/passhub/config/config.php
```

The configuration file has the following content by default:

```php
<?php

// Path to your WWPass service provider CRT and KEY files.
define('WWPASS_CERT_FILE', "/etc/ssl/yourcompany.com.crt");
define('WWPASS_KEY_FILE', "/etc/ssl/yourcompany.com.key");

// Path to the WWPass certificate authority file.
define('WWPASS_CA_FILE', "config/wwpass_sp_ca.crt");

// Set to true to request PIN or biometrics each time user signs in, set to false otherwise.
define('WWPASS_PIN_REQUIRED', true);

// Session expiration timeout in seconds, prolonged automatically by user activity.
define('WWPASS_TICKET_TTL', 1200);

// Log out on hardware PassKey removal, default true
define('WWPASS_LOGOUT_ON_KEY_REMOVAL', true);

// User inactivity reminder, set to 9 min. After another minute (total 10 minutes) a user will be logged out automatically
define('IDLE_TIMEOUT', 540);


// MAX allocated resources
define('MAX_RECORDS_PER_USER', 100);
define('MAX_STORAGE_PER_USER', 100 * 1024 * 1024);

// Some upper limits
define('MAX_VAULTS_PER_USER',2048);
define('MAX_SAFENAME_LENGTH',20);
define('MAX_FILENAME_LENGTH',40);
define('MAX_URL_LENGTH',2048);
define('MAX_NOTES_SIZE', 2000);

// Sharing invitation expiration timeout, default 48 hours (anonimous accounts only)
define('SHARING_CODE_TTL', 48*60*60);

// Path to PassHub log directory
define('LOG_DIR', '/var/log/passhub');


// Database Connection Parameters

// Database name
define('DB_NAME', 'passhub');
// Mongodb connection line (unsafe!)
define('MONGODB_CONNECTION_LINE', 'mongodb://localhost');

//Example connection line with username, password, and non-default port
//define('MONGODB_CONNECTION_LINE', "mongodb://username:password@localhost:port");

//Example connection line for distributed Mongodb.
//define('MONGODB_CONNECTION_LINE', "mongodb://username:password@phub-srv1:port,phub-srv2:port,phub-arbiter:port/phub?replicaSet=rsphub&ssl=true");


//FILE store. all sizes in Bytes 
define('MAX_FILE_SIZE', 5 * 1024 * 1024);

// FILE_DIR should be created in advance
define('FILE_DIR', '/var/lib/passhub');

// or,Google drive
//define('GOOGLE_CREDS', 'google_drive_credentials.json');

// or, S3-compatible file storage:
/*
define(
  'S3_CONFIG', [
      'version' => 'latest',
      'region'  => 'sfo2',
      'endpoint' => 'https://sfo2.digitaloceanspaces.com',
      'credentials' => [
          'key'    => 'some_key',
          'secret' => 'some_secret',
      ],
  ] 
); 
define('S3_BUCKET', 'phub');
*/

// access policy, space separated
define('MAIL_DOMAIN', "yourcompany.com domain2.com ");

// Email address to handle end-user support requests.
define('SUPPORT_MAIL_ADDRESS', 'support@yourcompany.com');

// lazy send mail, requires  "sudo apt install php-mail"
/*
define(
    'SMTP_SERVER', [
      'host' => 'ssl://smtp.gmail.com',
      'port' => '465',
      'auth' => true,
      'username' => 'mycompanylazymail@gmail.com',
      'password' => 'ppppp'
    ]
);
*/


```

We need to perform the following adjustments:

1. Set ```WWPASS_CERT_FILE``` to the absolute path to your WWPass service provider certificate file (eg. /etc/ssl/yourcompany.com.crt);
2. Set ```WWPASS_KEY_FILE``` to the absolute path to your WWPass service provider key file (e.g. /etc/ssl/yourcompany.com.key);
3. Set ```WWPASS_CA_FILE``` to the absolute path to the WWPass certificate authority file (e.g. /etc/ssl/wwpass_sp_ca.crt). You can download ```wwpass_ca.cer``` here:
https://developers.wwpass.com/downloads/wwpass.ca
4. Set ```SUPPORT_MAIL_ADDRESS``` to an email address you are going to use for handling user support requests;

Additionally, you may want to adjust the ```WWPASS_PIN_REQUIRED``` parameter, which controls whether PassHub should request PIN during authentication. Set it to ```false``` if you want to disable PIN requests, leave the default ```true``` value otherwise.

Save and close the file when you are finished.

## Step 7: Setting up email

Passhub uses email service for feedback messages and user email address verification. Setting up a full-featured modern email server may be a tricky task. Depending on your resources, choose one out of the three options to configure PassHub mail.

### Option 1. Mail server on the same computer where PassHub is running

 If you have one, you are all set: Passhub uses it by default.

### Option 2

Create or use a dedicated mail account on your company mail server. This case you need to install PHP PEAR/mail package on the Passhub server:

  `sudo apt install php-mail`

 Now add the account data to the config.php file, for example

```php
 define(
    'SMTP_SERVER', [
      'host' => 'ssl://your.mail.server.com',
      'port' => '465',
      'auth' => true,
      'username' => 'passhub@your.mail.server.com',
      'password' => 'dedicated_account_password'
    ]
);
```

### Option 3

Create a dedicated gmail account. Basically, it is a variant of **Option 2**. Install PHP PEAR/mail package:

  `sudo apt install php-mail`

Add account data to the config.php, for example

```php
 define(
    'SMTP_SERVER', [
      'host' => 'ssl://smtp.gmail.com',
      'port' => '465',
      'auth' => true,
      'username' => 'dedicated_account@gmail.com',
      'password' => 'dedicated_account_password'
    ]
);
```

You will need to tweak the security settings of the gmail account. In the account settings choose 'Security' and turn on **Less secure app access** switch

## Step 8: Test PassHub

Open your web browser and navigate to the address of your PassHub server. You should see the PassHub main page with the authentication QR code. If your computer has WWPass Security Pack installed, you will also see a button to log in with hardware WWPass PassKey under the QR code.

## Step 9: Site administrator

For corporate use, a PassHub administrator should be assigned. The administrator has rights to monitor user activities, delete users or grant PassHub administrator role to other users. PassHub administrator also controls the white list of email addresses of external users allowed to create an account.

The first logged-in user who visits `/iam.php` page of the site:  `https://yourpasshub.com/iam.php` is granted site administrator rights automatically. Other users only become site administrators by permission of the existing site administrators.

## Advanced: store your encryptes files in the cloud

It is well possible to keep all your encrypted files in the Amazon S3 compatible object storage service. This way you increase the availability of your data and simplify storage configuration for distributed deployments of PassHub.

Good news is that Amason S3 API becomes a standard de-facto and the sam code works for many object storage providers, like Google Cloud Platform or Digital Ocean Spaces, Vultr and Linode.

With `s3fs` solution, available for Linux, it is also possible just to mount S3-compatible storage to the filesystem, as if it was an NFS external storage. This way you do not need to write a sigle line of code.

PassHub supports S3-compatible storage. To configure this option, create an Object storage account in one of the cloud service providers change  

```php
// Comment out other storage methods 
// define('FILE_DIR', '/var/lib/passhub');

// Provide S3 account data, like that for example

define(
    'S3_CONFIG', [
        'version' => 'latest',
        'region'  => 'sfo2',
        'endpoint' => 'https://sfo2.digitaloceanspaces.com',
        'credentials' => [
            'key'    => 'kkkkkkkkk',
            'secret' => 'ssssssssss',
        ],
    ] 
); 
define('S3_BUCKET', 'phub');
```

## Feedback and Support

Should you experience any difficulties during the installation of PassHub, please feel free to contact our support team at support@wwpass.com.
