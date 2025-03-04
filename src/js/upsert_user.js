import axios from 'axios';
import forge from 'node-forge';
import passhubCrypto from './crypto';

import { doRestoreXML, importTemplate } from './import';

/*

// https://stackoverflow.com/questions/41529138/import-pem-keys-using-web-crypto
// https://github.com/digitalbazaar/forge/issues/255

// var wwpassCrypto = WWPassCrypto.initWithTicket(page_args.wwpassTicket);
*/



const crypto = window.crypto || window.msCrypto;
const subtle = crypto ? (crypto.webkitSubtle || crypto.subtle) : null;

const start_time = new Date();
const credentials_to_send = { publicKey: null, encryptedPrivateKey: null };

function sendWhenReady() {
  const { upgrade } = page_args;
  const ajaxUrl = upgrade ? 'upgrade_user.php' : 'create_user.php';
  if ((credentials_to_send.publicKey != null)
    && (credentials_to_send.encryptedPrivateKey != null)) {
    const user = {
      publicKey: credentials_to_send.publicKey,
      encryptedPrivateKey: credentials_to_send.encryptedPrivateKey,
    };

    if (!upgrade) {
      const publicKey = forge.pki.publicKeyFromPem(credentials_to_send.publicKey);
      const { template_safes } = page_args;
      const rootSafe = doRestoreXML(template_safes);
      user.import = importTemplate(rootSafe.folders, publicKey);
    }

    axios.post(ajaxUrl, user
    )
      .then((reply) => {
        const result = reply.data;
        if (result.status === 'Ok') {
          window.location.href = 'index.php';
          return;
        }
        alert(result.status);
      })
      .catch((error) => {
        const hdr = '';
        const status = '';
        const err = error;
        // ----  $('#backup_button').hide();
        // ----  modalAjaxError($('#backup_alert'), hdr, status, err);
      });
  }
}

function privateKeyEncrypted(encryptedPrivateKey) {
  credentials_to_send.encryptedPrivateKey = encryptedPrivateKey;
  sendWhenReady();
}

function cryptoapi_catch(err) {
  console.log(err);
  // alert('X ' + err);
  window.location.href = `error_page.php?js=387&error=${err}`;
}

// forge: pkcs8 to PEM

function pkcs82pem(pkcs8_ab) {
  const pkcs8_bytes = new Uint8Array(pkcs8_ab);
  let pkcs8_b64 = btoa(String.fromCharCode.apply(null, pkcs8_bytes));
  let pem = '-----BEGIN PRIVATE KEY-----\n';
  while (pkcs8_b64.length > 0) {
    pem += pkcs8_b64.slice(0, 64) + '\n';
    pkcs8_b64 = pkcs8_b64.slice(64);
  }
  pem += '-----END PRIVATE KEY-----\n';
  return pem;
}

function privateKeyExported_pkcs8(exportedPrivateKey) {
  const pem = pkcs82pem(exportedPrivateKey);

  passhubCrypto.encryptPrivateKey(pem, page_args.wwpassTicket)
    .then(privateKeyEncrypted).catch(cryptoapi_catch);
}

function spki2pem(spkiAb) {
  const spkiBytes = new Uint8Array(spkiAb);
  let spkiB64 = btoa(String.fromCharCode.apply(null, spkiBytes));
  let pem = '-----BEGIN PUBLIC KEY-----\n';
  while (spkiB64.length > 0) {
    pem += spkiB64.slice(0, 64) + '\n';
    spkiB64 = spkiB64.slice(64);
  }
  return pem + '-----END PUBLIC KEY-----\n';
}

function publicKeyExported_spki(key) {
  const pem = spki2pem(key);
  credentials_to_send.publicKey = pem;
  sendWhenReady();
}

function keyPairGenerated(keypair) {
  let elapsed = new Date();
  elapsed -= start_time;
  document.querySelector('#create_userwait').textContent = `KeyPair generated in ${elapsed} ms (cryptoAPI)`;

  subtle.exportKey('pkcs8', keypair.privateKey).then(privateKeyExported_pkcs8).catch(cryptoapi_catch);
  subtle.exportKey('spki', keypair.publicKey).then(publicKeyExported_spki).catch(cryptoapi_catch);
}


if (subtle && !((navigator.userAgent.match('Version/10') && navigator.userAgent.match('iPhone')))) {
  subtle.generateKey({
    name: 'RSA-OAEP',
    modulusLength: 2048,
    publicExponent: new Uint8Array([0x01, 0x00, 0x01]),
    hash: { name: 'SHA-1' },
  },
    true, ['encrypt', 'decrypt']).then(keyPairGenerated).catch(cryptoapi_catch);
} else {
  const state = forge.rsa.createKeyPairGenerationState(2048, 0x10001);
  let waitMessage = '>';
  let secondsPassed = 0;
  const createUserWaitElement = document.querySelector('#create_userwait');
  createUserWaitElement.textContent = `Wait ${waitMessage}`;
  const step = () => {
    // run for 100 ms
    if (!forge.rsa.stepKeyPairGenerationState(state, 1000)) {
      setTimeout(step, 1);
      waitMessage = '.' + waitMessage;
      secondsPassed += 1;
      createUserWaitElement.textContent = `Wait ${waitMessage}`;
      document.querySelector('#seconds_passsed').textContent = `seconds passed ${secondsPassed}`;
    } else {
      let elapsed = new Date();
      elapsed -= start_time;
      createUserWaitElement.textContent = `KeyPair generated in ${elapsed} ms (forge)`;
      const publicPem = forge.pki.publicKeyToPem(state.keys.publicKey);

      // const privatePem = forge.pki.privateKeyToPem(state.keys.privateKey);

      const rsaPrivateKey = forge.pki.privateKeyToAsn1(state.keys.privateKey);
      const privateKeyInfo = forge.pki.wrapRsaPrivateKey(rsaPrivateKey);
      const privatePem = forge.pki.privateKeyInfoToPem(privateKeyInfo);

      credentials_to_send.publicKey = publicPem;
      sendWhenReady();

      passhubCrypto.encryptPrivateKey(privatePem, page_args.wwpassTicket)
        .then(privateKeyEncrypted).catch(cryptoapi_catch);

      // done, turn off progress indicator, use state.keys
    }
  };
  setTimeout(step);
}
