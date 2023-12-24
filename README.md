# PassHub Password Manager

**PassHub** password manager is a collaboration tool to securely store and share login/password pairs, notes, files, certificates, and documents. Passhub relies on WWPass technology: authentication and client-side encryption use WWPass services

## Features

- No usernames/passwords to login
- Platform/OS-neutral, web-only solution: works in any browser
- works across all user devices
- no need to download software on each user device or browser
- when used with smartcard/USB/Bluetooth Passkey: automatic logout when the key is disconnected. For Bluetooth connection automatic logout when the phone is moved away from the computer.
- Open-source (No backdoors, no registration)
- PassHub may be deployed on company servers - provides full control over company sensitive information
- when deployed on your server - configurable second factor (PIN)
- https://passhub.net is free for all

## Server Requirements

- PHP 7.4+ (8.2 preferred)
- PHP composer
- MongoDB 4.4+ (6.0+ preferred)

## Preferred run-time environment

- Ubuntu 22.04
- NGINX Web server

## Getting passhub.business archive

You may either download the latest release of the PassHub archive [passhub.business.\*.tgz](https://github.com/wwpass/passhub/releases/download/v2.0/passhub.business.20220307.tgz) on [GitHub Releases](https://github.com/wwpass/passhub/releases/) page or build the tarball from source.

## Build from source


The project consistes of two github repositories: main, [wwpass/passhub](https://github.com/wwpass/passhub), and frontend, [wwpass/passhub-frontend-v2](https://github.com/wwpass/passhub-frontend-v2)  


**Note** The first project of PassHub client-side is no more supported, please use version2: [wwpass/passhub-frontend-v2](https://github.com/wwpass/passhub-frontend-v2)  


To build the tarball, install `nodejs` package on your development computer (nmp version **6.9+**)

To compile the project from scratch, download the git repository wwpass/passhub and run

```sh
npm install
npm run build
```

Now clone frontend code wwpass/passhub-frontend-v2 and do the same

```sh
npm install
npm run build
```

Create `/frontend` directory in the main passhub project and copy wwpass/frontend build content in it.

## Service deployment

With the tarball in hands, deploy your own instance of PassHub.

Follow the [Installation manual](https://github.com/wwpass/passhub/blob/master/InstallingPassHubOnUbuntu20.04.md). The document is mainly oriented on Ubuntu 20.04, but any modern Linux distribution should work.

## Feedback and Support

Should you experience any difficulties during the installation of PassHub, please feel free to contact our support team at support@wwpass.com.



Config variables

SUPPORT_MAIL_ADDRESS', 'passhub@wwpass.com'
MAX_STORAGE_PER_USER
FREE_ACCOUNT_MAX_STORAGE ( if defined and if plan FREE)
MAX_FILE_SIZE =  1024 * 1024
PUBLIC_SERVICE = flase
EMAIL_BLACKLIST = []
SHARING_CODE_TTL - 48*60*60

WWPASS_PIN_REQUIRED
WWPASS_KEY_FILE
WWPASS_CERT_FILE
WWPASS_CA_FILE
WWPASS_TICKET_TTL

IDLE_TIMEOUT', 540

FILE_DIR
GOOGLE_CREDS
LDAP

DISCOURSE_SECRET
MAIL_DOMAIN

// index twig args, not used?
MAX_SAFENAME_LENGTH, 20
MAX_FILENAME_LENGTH, 40
MAX_NOTES_SIZE, 2048
MAX_URL_LENGTH, 2500

FREE_ACCOUNT_MAX_RECORDS
LOGIN_PAGE

PREMIUM

WEBSOCKET false

MAX_RECORDS_PER_USER
FREE_ACCOUNT_MAX_RECORDS

SENDMAIL_FROM
SMTP_SERVER
LOG_DIR
SYSLOG

IP_BLACKLIST
FILE_DIR
GOOGLE_CREDS
S3_CONFIG
MAX_FILE_SIZE





