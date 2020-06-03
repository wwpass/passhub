<?php

// Path to your WWPass service provider CRT and KEY files.
define('WWPASS_CERT_FILE', "/etc/ssl/yourcompany.com.crt");
define('WWPASS_KEY_FILE', "/etc/ssl/yourcompany.com.key");

// Path to the WWPass certificate authority file.
define('WWPASS_CA_FILE', "config/wwpass_sp_ca.crt");

// Session expiration timeout in seconds, prolonged automatically by user activity.
define('WWPASS_TICKET_TTL', 1200);

// Set to true to request PIN or biometrics each time user signs in, set to false otherwise.
define('WWPASS_PIN_REQUIRED', true);

// Log out on hardware PassKey removal, default true
define('WWPASS_LOGOUT_ON_KEY_REMOVAL', true);

// MAX allocated resources
define('MAX_RECORDS_PER_USER', 10000);
define('MAX_STORAGE_PER_USER', 1024 * 1024 * 1024);

// Some upper limits
define('MAX_SAFENAME_LENGTH', 20);
define('MAX_FILENAME_LENGTH', 40);
define('MAX_URL_LENGTH', 2048);
define('MAX_NOTES_SIZE', 2000);

// User inactivity reminder, set to 9 min. After another minute (total 10 minutes) a user will be logged out automatically
define('IDLE_TIMEOUT', 540);

// Path to PassHub log directory
define('LOG_DIR', '/var/log/passhub');

// ** Database **

// Database name
define('DB_NAME', 'passhub');
// Mongodb connection line (unsafe!)
define('MONGODB_CONNECTION_LINE', 'mongodb://localhost');

//Example connection line with username, password, and non-default port
//define('MONGODB_CONNECTION_LINE', "mongodb://username:password@localhost:port");

//Example connection line for distributed Mongodb.
//define('MONGODB_CONNECTION_LINE', "mongodb://username:password@phub-srv1:port,phub-srv2:port,phub-arbiter:port/phub?replicaSet=rsphub&ssl=true");


// ** FILE storage** all sizes in Bytes 
define('MAX_FILE_SIZE', 5 * 1024 * 1024);

// local file storage: FILE_DIR should be created in advance
define('FILE_DIR', '/var/lib/passhub');

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

// or,Google drive
//define('GOOGLE_CREDS', 'google_drive_credentials.json');

// ** Mail **

// Email address to handle end-user support requests.
define('SUPPORT_MAIL_ADDRESS', 'support@yourcompany.com');

// local SMTP on Unix server, sendmail_from defaults to noreply@<host domain name>
// to override the setting:
define('SENDMAIL_FROM', "noreply@yourcompany.com");

//  or, mail client of the external server, requires  "sudo apt install php-mail"
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

// ** Access control

define(
    'LDAP', [
      // Active directory server schema, name and port
      'url' => 'ldaps://ad.wwpass.lan:636',

      'base_dn' => "ou=office,dc=wwpass,dc=lan", 

      // When creating new user account, Passhub identifies a user by UserPrincipal name, which consists of user name (logon name), separator (the @ symbol), and domain name (UPN suffix). In case the user provides only username, without @-symbol and domain, the `domain` parameter is added to obtain UPN

      'domain' => "wwpass.lan",

      // Group, which allows to access PassHub:
      'group' => "CN=Passhub Users,OU=Security,OU=Groups,OU=Office,DC=wwpass,DC=lan",

      // cerdentials used by Passhub itself when cheking user membership to the above group
      'bind_dn' => "cn=xxxxx,ou=xxxxx,dc=wwpass,dc=lan",
      'bind_pwd' => "xxxxx"
    ]
);

// if LDAP is not defined: allowed mail domains, space separated
define('MAIL_DOMAIN', "yourcompany.com domain2.com ");

// or use your mail only to start
// define('MAIL_DOMAIN', "you@yourcompany.com");
// 
// define('MAIL_DOMAIN', "any");

// Sharing invitation expiration timeout, default 48 hours (anonimous accounts only)
define('SHARING_CODE_TTL', 48*60*60);



// ** 

// white-label login page 
// define('LOGIN_PAGE', "views/login.html");
