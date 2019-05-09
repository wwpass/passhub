# Installing PassHub on Ubuntu 16.04

## About This Document

PassHub is a web-based password manager for individuals and teams with support for client-side encryption. PassHub relies on WWPass authentication and data encryption technology and can work both with hardware WWPass PassKey and WWPass PassKey Lite smartphone application.

In this guide, we'll discuss how to get PassHub installed on your Ubuntu 16.04
server.

## Prerequisites

To deploy Passhub, you should set up Ubuntu 16.04 Server and
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
```
sudo apt-get update
sudo apt-get install -y nginx
```

On Ubuntu 16.04, Nginx is configured to start running upon installation.

## Step 2: Install MongnDB Database

PassHub requires a newer version of MongoDB than Ubuntu 16.04 provides by default, so we need to use the official MongoDB repository, which hosts the most up-to-date versions of the database.

First, we need to import the key for the official MongoDB repository:
```
sudo apt-key adv --keyserver hkp://keyserver.ubuntu.com:80 --recv 0C49F3730359A14518585931BC711F9BA15703C6
```

Once the key is imported, you will see:
```
gpg: Total number processed: 1
gpg:               imported: 1  (RSA: 1)
```

Next, issue the following command to create a list file for MongoDB:
```
echo "deb [ arch=amd64,arm64 ] http://repo.mongodb.org/apt/ubuntu xenial/mongodb-org/3.4 multiverse" | sudo tee /etc/apt/sources.list.d/mongodb-org-3.4.list
```

After adding the repository details, we need to update the packages list:
```
sudo apt-get update
```

Now we can install the MongoDB package:
```
sudo apt-get install -y mongodb-org
```

Next, start MongoDB with ```systemctl```:
```
sudo systemctl start mongod
```

The last step is to configure MongoDB to start automatically when your server boots:
```
sudo systemctl enable mongod
```

## Step 3: Install PHP

Type the following command to install PHP and additional modules required by PassHub:
```
sudo apt-get install -y php-fpm php-curl php-mbstring php-mcrypt php-mail
```

PHP MongoDB module that comes with Ubuntu 16.04 does not work with the newer version of MongoDB we installed in Step 2. We need to install php-mongodb from the PHP extension community library:
```
sudo apt-get install -y php-pear php-dev pkg-config
sudo pecl install mongodb
sudo bash -c "echo 'extension=mongodb.so' > /etc/php/7.0/mods-available/mongodb.ini"
sudo ln -s /etc/php/7.0/mods-available/mongodb.ini /etc/php/7.0/cli/conf.d/30-mongodb.ini
sudo ln -s /etc/php/7.0/mods-available/mongodb.ini /etc/php/7.0/fpm/conf.d/30-mongodb.ini
```

Finally, you need to restart php7.0-fpm so that your configuration changes will take effect:
```
sudo service php7.0-fpm restart
```

##  Step 4: Extract PassHub Files

Upload ```passhub.tgz``` archive to your home directory via SCP or any other method.

We need to extract the contents of the archive into the ```/var/www```
directory:
```
cd /var/www
sudo tar xvzf ~/passhub.tgz
```

And change ownership of the extracted files:
```
sudo chown -R username:www-data /var/www/passhub
```

**Note**: don't forget to change `username` with the actual name of your account.

We also need to create a directory for PassHub log files:
```
sudo mkdir /var/log/passhub
sudo chown www-data:www-data /var/log/passhub
```

## Step 5: Adjust PassHub Configuration

We now need to create a new PassHub configuration using the sample configuration file bundled with the PassHub distribution.

Create a new configuration file by copying ```config-sample.php```:
```
sudo cp /var/www/passhub/config/config-sample.php /var/www/passhub/config/config.php
```

And open it in a text editor:
```
sudo nano /var/www/passhub/config/config.php
```

The configuration file has the following content by default:
```
<?php

// Path to your WWPass service provider CRT and KEY files.
define('WWPASS_CERT_FILE', "/etc/ssl/yourcompany.com.crt");
define('WWPASS_KEY_FILE', "/etc/ssl/yourcompany.com.key");

// Name of your service provider as registered at developers.wwpass.com (e.g. yourcompany.com).
define('WWPASS_SP_NAME',"yourcompany.com");

// Path to the WWPass certificate authority file.
define('WWPASS_CA_FILE', "config/wwpass_sp_ca.crt");

// Email address to handle end-user support requests.
define('SUPPORT_MAIL_ADDRESS', 'support@yourcompany.com');

// Set to true to request PIN or biometrics each time user signs in, set to false otherwise.
define('WWPASS_PIN_REQUIRED', true);

// Upper limits for allocated resources.
define('MAX_USERS_PER_VAULT',2048);
define('MAX_VAULTS_PER_USER',2048);
define('MAX_VAULT_SIZE',2048);
define('MAX_SAFENAME_LENGTH',20);
define('MAX_FILENAME_LENGTH',40);
define('MAX_URL_LENGTH',2048);
define('MAX_NOTES_SIZE', 2000);

// Session expiration timeout in seconds, prolonged automatically by user activity.
define('WWPASS_TICKET_TTL', 1200);

// User inactivity reminder, set to 9 min. After another minute (total 10 minutes) a user will be logged out automatically
define('IDLE_TIMEOUT', 540);

// Sharing invitation expiration timeout, default 48 hours.
define('SHARING_CODE_TTL', 48*60*60);

// Path to PassHub log directory
define('LOG_DIR', '/var/log/passhub');

// Log out on hardware PassKey removal, default true.
define('WWPASS_LOGOUT_ON_KEY_REMOVAL', true);

// Database Connection Parameters.
// Database name.
define('DB_NAME', 'phub');
// Mongodb connection line.
define('MONGODB_CONNECTION_LINE', 'mongodb://localhost');

//Example connection line with username, password, and non-default port.
//define('MONGODB_CONNECTION_LINE', "mongodb://username:password@localhost:port");

//Example connection line for distributed Mongodb.
//define('MONGODB_CONNECTION_LINE', "mongodb://username:password@phub-srv1:port,phub-srv2:port,phub-arbiter:port/phub?replicaSet=rsphub&ssl=true");

//FILE store. all sizes in MBytes 
define('MAX_FILE_SIZE', 5);
// user Quota 
define('MAX_STORAGE', 100);

// both FILE_DIR and GOOGLE_CREDS should not be defined simultaneously: not more then one
// FILE_DIR should be created in advance
define('FILE_DIR', '/var/lib/passhub');
//define('GOOGLE_CREDS', 'google_drive_credentials.json');

// access and share policy
define('MAIL_DOMAIN', "wwpass.com");
define('SHARE_BY_MAIL', true);

// lazy send mail, requires  "sudo apt install php-mail"
/*
define(
    'SMTP_SERVER', [
      'host' => 'ssl://smtp.gmail.com',
      'port' => '465',
      'auth' => true,
      'username' => 'mycompanylazymail@gmail.com',
      'password' => 'mkhKJHwhqwjklhqd'
    ]
);
*/
```

We need to perform the following adjustments:

1. Set ```WWPASS_CERT_FILE``` to the absolute path to your WWPass service provider certificate file (eg. /etc/ssl/yourcompany.com.crt);
2. Set ```WWPASS_KEY_FILE``` to the absolute path to your WWPass service provider key file (e.g. /etc/ssl/yourcompany.com.key);
3. Set ```WWPASS_SP_NAME``` to the name of your WWPass service provider (e.g. "yourcompany.com");
4. Set ```WWPASS_CA_FILE``` to the absolute path to the WWPass certificate authority file (e.g. /etc/ssl/wwpass_ca.cer). You can download ```wwpass_ca.cer``` here: 
https://developers.wwpass.com/downloads/wwpass.ca
5. Set ```SUPPORT_MAIL_ADDRESS``` to an email address you are going to use for handling user support requests;

Additionally, you may want to adjust the ```WWPASS_PIN_REQUIRED``` parameter, which controls whether PassHub should request PIN during authentication. Set it to ```false``` if you want to disable PIN requests, leave the default ```true``` value otherwise.

Save and close the file when you are finished.

## Step 6: Create Nginx Configuration for PassHub

We need to create a new Nginx configuration file for our PassHub installation.

Create a new configuration file by typing:
```
sudo nano /etc/nginx/sites-available/passhub.conf
```

And add the following content to the file:
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
		fastcgi_pass unix:/run/php/php7.0-fpm.sock;
	}
}
```

**Notes**:
1. Change ```example.com``` to the DNS name of your server;
2. Make sure ```ssl_certificate``` and ```ssl_certificate_key``` point to
existing SSL certificate files;

Save and close the file when you are finished.

Next, we need to create a symbolic link in the ```/etc/nginx/sites-enabled/```
directory so that Nginx can pick up the configuration file we just created:
```
sudo ln -s /etc/nginx/sites-available/passhub.conf /etc/nginx/sites-available/passhub.conf
```

Now it is time to test our Nginx configuration for possible errors:
```
sudo nginx -t
```

If everything is correct, you will see the following output:
```
nginx: the configuration file /etc/nginx/nginx.conf syntax is ok
nginx: configuration file /etc/nginx/nginx.conf test is successful
```

If Nginx reports configuration errors and you see output like this:
```
nginx: configuration file /etc/nginx/nginx.conf test failed
```

You should revise your Nginx configuration and re-test.

Once your Nginx configuration contains no errors, it is time to
tell Nginx to reload configuration data:
```
sudo nginx -s reload
```

**Tip**: if you need to temporarily disable your PassHub instance in Nginx, you can simply remove the symbolic link in the ```/etc/nginx/sites-enabled/```
directory and reload Nginx configuration like this:

```sh
sudo rm /etc/nginx/sites-enabled/passhub.conf
sudo nginx -s reload
```

When you need to re-enable Nginx, just re-create the symbolic link and reload Nginx configuration:

```sh
sudo ln -s /etc/nginx/sites-available/passhub.conf /etc/nginx/sites-enabled/passhub.conf
sudo nginx -s reload
```

## Step 7: Setting up mail

Passhub uses email service for feedback messages and user email address verification. Setting up a full-featured modern email server may be a tricky task. Depending on your resources, choose one out of 3 options to configure passhub mail.

### Option 1

Mail server on the same computer where Passhub is running. If you have one, you are all set: Passhub will use it by default.

### Option 2

Create or use a decicated mail account on your company mail server. This case you need to install PHP PEAR/mail package on the Passhub server:

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

Create a dedicated gmail account. Basically it is a variant of **Option 2**. Install PHP PEAR/mail package:

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

You will need to twaek security settings of the gmail account. In the account settings choose 'Security' and turn on **Less secure app access** switch

## Step 8: Test PassHub

Open your web browser and navigate to the address of your PassHub server. You should see the PassHub main page with the authentication QR code. If your computer has WWPass Security Pack installed, you will also see a button to log in with hardware WWPass PassKey under the QR code.

Should you experience any difficulties during installation of PassHub, please feel free to contact our support team at support@wwpass.com.

## Step 9: Site administrator

For corporate use a site administrator should be assigned. Site administrator has rights to see user activities, delete users or grant site administrator role to other users. Site administrator also controls the white list of email adresses of external users allowed to create an account.

The first logged-in user who visits iam.php page of the site

```
https://yourpasshub.com/iam.php
```

is granted site administrator rights automatically. Other users get only become site administrators by permission of existing site administrators.
