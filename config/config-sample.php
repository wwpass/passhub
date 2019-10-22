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
define('MAX_RECORDS_PER_USER', 8);
define('MAX_STORAGE_PER_USER', 1 * 1024 * 1024);

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

