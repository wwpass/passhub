# PassHub Password Manager

**PassHub** password manager is a collaboration tool to securely store and share login/password pairs, notes, files, certicates, and documents. Passhub relies on WWPass technology: authentication and client-side encryption use WWPass services

## Features

- No usernames/passwords to login
- Platform/OS-neutral, web-only solution: works in any browser
- no need to download software
- works across all user devices
- when used with smartcard/USB/Bluetooth Passkey: automatic logout when the key is disconnected. For Bluetooth connection automatic logout when the phone is moved away from the computer.
- Open-source (No backdoors, no registration)
- PassHub may be deployed on company servers - provides full control over company sensitive information
- when deployed on your server - configurable second factor (PIN)
- https://passhub.net is free for all

## Server Requirements

- PHP 7.0+
- PHP composer
- MongoDB 3.2+

## Preferred run-time environment

- Ubuntu 18.04
- NGINX Web server

## Development environment

- nmp 6.9+

To compile the project from scratch, download the git repository and run

```sh
npm install
npm run build
publish_tarball_business.sh
```

You get a tarball named as `passhub.business.YYYYMMDD.tgz`

## Service deployment

With the tarball in hands, deploy your own instance of PassHub.

Follow the [Installation manual](https://github.com/wwpass/passhub/blob/master/InstallationManualUbuntu18.04.md). The document is mainly oriented on Ubuntu 18.04, but any modern Linux distribution should work.

## Feedback and Support

Should you experience any difficulties during installation of PassHub, please feel free to contact our support team at support@wwpass.com.
