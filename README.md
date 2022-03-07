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

- PHP 7.4+
- PHP composer
- MongoDB 4.4+

## Preferred run-time environment

- Ubuntu 20.04
- NGINX Web server

## Getting passhub.business archive

You may either download the latest release of the PassHub archive [passhub.business.\*.tgz](https://github.com/wwpass/passhub/releases/download/v2.0/passhub.business.20220307.tgz) on [GitHub Releases](https://github.com/wwpass/passhub/releases/) page or build the tarball from source.

To build the tarball, install `nodejs` package on your development computer (nmp version **6.9+**)

To compile the project from scratch, download the git repository and run

```sh
npm install
npm run build
publish_tarball_business.sh
```

You get a tarball named as `passhub.business.YYYYMMDD.tgz`

## Service deployment

With the tarball in hands, deploy your own instance of PassHub.

Follow the [Installation manual](https://github.com/wwpass/passhub/blob/master/InstallingPassHubOnUbuntu20.04.md). The document is mainly oriented on Ubuntu 20.04, but any modern Linux distribution should work.

## Feedback and Support

Should you experience any difficulties during the installation of PassHub, please feel free to contact our support team at support@wwpass.com.
