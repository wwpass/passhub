import forge from 'node-forge';

// import { WWPassCryptoPromise } from 'wwpass-frontend';
import * as WWPass from 'wwpass-frontend';

import { serverLog } from './utils';

function createSafe(publicKeyTxt, name, items, folders) {
  const aesKey = forge.random.getBytesSync(32);
  const publicKey = forge.pki.publicKeyFromPem(publicKeyTxt);
  const encryptedAesKey = publicKey.encrypt(aesKey, 'RSA-OAEP');
  const hexEncryptedAesKey = forge.util.bytesToHex(encryptedAesKey);
  return { name, aes_key: hexEncryptedAesKey };
};

function encryptSafeName(newName, aesKey) {
  const iv = forge.random.getBytesSync(12);
  const cipher = forge.cipher.createCipher('AES-GCM', aesKey);
  cipher.start({ iv });
  cipher.update(forge.util.createBuffer(newName, 'utf8')); // already joined by encode_item (
  const result = cipher.finish(); // check 'result' for true/false
  const eName = {
    iv: btoa(iv),
    data: btoa(cipher.output.data),
    tag: btoa(cipher.mode.tag.data),
  };
  console.log(eName);
  return eName;
}

/*
function createSafe1(publicKeyTxt, name) {
  const aesKey = forge.random.getBytesSync(32);
  const publicKey = forge.pki.publicKeyFromPem(publicKeyTxt);
  const encryptedAesKey = publicKey.encrypt(pAesKey, 'RSA-OAEP');
  const hexEncryptedAesKey = forge.util.bytesToHex(encryptedAesKey);
  const eName = encryptSafeName(name, aesKey);

  return { // name,  
    eName, aes_key: hexEncryptedAesKey, version:3 };
};
*/

function encodeItemGCM(cleartextItem, aesKey, options) {
  const cleartextData = cleartextItem.join('\0');
  const cipher = forge.cipher.createCipher('AES-GCM', aesKey);
  const iv = forge.random.getBytesSync(16);
  cipher.start({ iv });
  cipher.update(forge.util.createBuffer(cleartextData, 'utf8')); // already joined by encode_item (
  const result = cipher.finish(); // check 'result' for true/false

  const obj = {
    iv: btoa(iv),
    data: btoa(cipher.output.data),
    tag: btoa(cipher.mode.tag.data),
    version: 3,
  };

  if(options.version) {
    obj.version = options.version;
  } else if (cleartextItem.length === 6) {
    obj.version = 4;
  }

  if (typeof options !== 'undefined') {
    // Object.assign "polifill"
    for (let prop1 in options) {
      obj[prop1] = options[prop1];
    }
  }
  return JSON.stringify(obj);
}

function encryptFolderName(cleartextName, aesKey) {
  const iv = forge.random.getBytesSync(16);
  const cipher = forge.cipher.createCipher('AES-GCM', aesKey);
  cipher.start({ iv });
  cipher.update(forge.util.createBuffer(cleartextName, 'utf8')); // already joined by encode_item (
  const result = cipher.finish(); // check 'result' for true/false
  return JSON.stringify({
    iv: btoa(iv),
    data: btoa(cipher.output.data),
    tag: btoa(cipher.mode.tag.data),
    version: 3,
  });
}

function encryptItem(item, aesKey, options) {
  return encodeItemGCM(item, aesKey, options);
}

function decodeFolder(item, aesKey) {
  const decipher = forge.cipher.createDecipher('AES-GCM', aesKey);
  decipher.start({ iv: atob(item.iv), tag: atob(item.tag) });
  decipher.update(forge.util.createBuffer(atob(item.data)));
  const pass = decipher.finish();
  return decipher.output.toString('utf8').split('\0');
}

function decodeItemGCM(item, aesKey) {
  const decipher = forge.cipher.createDecipher('AES-GCM', aesKey);
  decipher.start({ iv: atob(item.iv), tag: atob(item.tag) });
  decipher.update(forge.util.createBuffer(atob(item.data)));
  const pass = decipher.finish();
  return decipher.output.toString('utf8').split('\0');
}

function decodeItem(item, aesKey) {
  const decipher = forge.cipher.createDecipher('AES-ECB', aesKey);
  decipher.start({ iv: forge.random.getBytesSync(16) });

  if (item.version === 1) {
    const encryptedData = forge.util.hexToBytes(item.creds);
    decipher.update(forge.util.createBuffer(encryptedData));
    const result = decipher.finish(); // check 'result' for true/false
    const creds = decipher.output.toString('utf8').split('\0');
    return [item.title, creds[0], creds[1], item.url, item.notes];
  }
  if (item.version === 2) {
    const encryptedData = forge.util.hexToBytes(item.data);
    decipher.update(forge.util.createBuffer(encryptedData));
    const result = decipher.finish(); // check 'result' for true/false
    return decipher.output.toString('utf8').split('\0');
  }
  if ( (item.version === 3) || (item.version === 4)) {
    return decodeItemGCM(item, aesKey);
  }
  alert(`Error 450: cannot decode data version ${item.version}`); //  ??
  return null;
}

let WebCryptoPrivateKey = null;
let ForgePrivateKey = null;
// let clientKey = null;

const getSubtle = () => {
  const crypto = window.crypto || window.msCrypto;
  return crypto ? (crypto.webkitSubtle || crypto.subtle) : null;
};

const isIOS10 = () => navigator.userAgent.match('Version/10') && navigator.userAgent.match(/iPhone|iPod|iPad/i);

const str2uint8 = (str) => {
  const bytes = new Uint8Array(str.length);
  for (let i = 0, strLen = str.length; i < strLen; i += 1) {
    bytes[i] = str.charCodeAt(i);
  }
  return bytes.buffer;
};

const str2ab = (str) => {
  const buf = new ArrayBuffer(str.length * 2); // 2 bytes for each char
  const bufView = new Uint16Array(buf);
  for (let i = 0, strLen = str.length; i < strLen; i += 1) {
    bufView[i] = str.charCodeAt(i);
  }
  return buf;
};

const b64ToAb = (base64) => {
  const s = atob(base64);
  const bytes = new Uint8Array(s.length);
  for (let i = 0; i < s.length; i += 1) {
    bytes[i] = s.charCodeAt(i);
  }
  return bytes.buffer;
};

const abToB64 = data => btoa(String.fromCharCode.apply(null, new Uint8Array(data)));

const ab2str = buf => String.fromCharCode.apply(null, new Uint16Array(buf));

const uint82str = buf => String.fromCharCode.apply(null, new Uint8Array(buf));

const pem2CryptoKey = (pem) => {
  const eol = pem.indexOf('\n');
  const pem1 = pem.slice(eol + 1, -2);
  const eof = pem1.lastIndexOf('\n');
  const pem2 = pem1.slice(0, eof);
  const pemBinary = atob(pem2);
  const pemAb = str2uint8(pemBinary);
  return getSubtle().importKey(
    'pkcs8',
    pemAb,
    {
      name: 'RSA-OAEP',
      hash: { name: 'SHA-1' },
    },
    false,
    ['decrypt'],
  )
    .then((key) => {
      WebCryptoPrivateKey = key;
      return key;
    });
};

const getPrivateKey = (ePrivateKey, ticket) => WWPass.cryptoPromise.getWWPassCrypto(ticket, 'AES-CBC')
  .then((thePromise) => {
    const iv = new Uint8Array([176, 178, 97, 142, 156, 31, 45, 30, 81, 210, 85, 14, 202, 203, 86, 240]);
    return getSubtle().decrypt(
      {
        name: 'AES-CBC',
        iv,
      },
      thePromise.clientKey,
      b64ToAb(ePrivateKey)
    ).then(ab2str)
      .then((pem) => {
        if (isIOS10() /* || navigator.userAgent.match(/edge/i) */) {
          ForgePrivateKey = forge.pki.privateKeyFromPem(pem);
          return ForgePrivateKey;
        }
        // return pem2CryptoKey(pem);
        return pem2CryptoKey(pem).catch(() => { // try old keys, generated in forge
          serverLog('forge privateKey');
          ForgePrivateKey = forge.pki.privateKeyFromPem(pem);
          return ForgePrivateKey;
        });
      });
  });

const encryptPrivateKey = (cPrivateKey, ticket) => WWPass.cryptoPromise.getWWPassCrypto(ticket, 'AES-CBC')
  .then((thePromise) => {
    const iv = new Uint8Array([176, 178, 97, 142, 156, 31, 45, 30, 81, 210, 85, 14, 202, 203, 86, 240]);
    return getSubtle().encrypt(
      {
        name: 'AES-CBC',
        iv,
      },
      thePromise.clientKey,
      str2ab(cPrivateKey)
    ).then(abToB64);
  });

const forgeDecryptAesKey = async (eKeyH) => {
  const eKey = forge.util.hexToBytes(eKeyH);
  return ForgePrivateKey.decrypt(eKey, 'RSA-OAEP');
};

const decryptAesKey = (eKey) => {
  if (ForgePrivateKey) {
    return forgeDecryptAesKey(eKey);
  }
  const u8Key = str2uint8(forge.util.hexToBytes(eKey));
  return getSubtle().decrypt(
    {
      name: 'RSA-OAEP',
      hash: { name: 'SHA-1' }, // Edge!
    },
    WebCryptoPrivateKey,
    u8Key
  ).then(abKey => uint82str(new Uint8Array(abKey)));
};

const decryptFile = (fileObj, aesKey) => {
  const filename = decodeItemGCM(fileObj.filename, aesKey)[0];
  const keyDecipher = forge.cipher.createDecipher('AES-ECB', aesKey);
  keyDecipher.start({ iv: atob(fileObj.file.iv) });
  keyDecipher.update(forge.util.createBuffer(atob(fileObj.file.key)));
  keyDecipher.finish();
  const fileAesKey = keyDecipher.output.data;
  const decipher = forge.cipher.createDecipher('AES-GCM', fileAesKey);
  decipher.start({ iv: atob(fileObj.file.iv), tag: atob(fileObj.file.tag) });
  decipher.update(forge.util.createBuffer(atob(fileObj.file.data)));
  const success = decipher.finish(); // got false actully, still decrypted ok????
  const { length } = decipher.output.data;
  const buf = new ArrayBuffer(length);
  const arr = new Uint8Array(buf);
  let i = -1;
  while (++i < length) {
    arr[i] = decipher.output.data.charCodeAt(i);
  }
  return { filename, buf };
};

function moveFile(item, srcSafe, dstSafe) {

  const keyDecipher = forge.cipher.createDecipher('AES-ECB', srcSafe.bstringKey);
  keyDecipher.start({ iv: atob(item.file.iv) }); // any iv goes: AES-ECB
  keyDecipher.update(forge.util.createBuffer(atob(item.file.key)));
  keyDecipher.finish();
  const fileAesKey = keyDecipher.output.data;

  const keyCipher = forge.cipher.createCipher('AES-ECB', dstSafe.bstringKey);
  const keyIV = forge.random.getBytesSync(16);
  keyCipher.start({ iv: keyIV });
  keyCipher.update(forge.util.createBuffer(fileAesKey));
  keyCipher.finish();

  const pItem = decodeItemGCM(item, srcSafe.bstringKey);
  const eItem = JSON.parse(encodeItemGCM(pItem, dstSafe.bstringKey));
  
  eItem.file = Object.assign({}, {...item.file});
  eItem.file.key = btoa(keyCipher.output.data);
  return eItem;
}

export default {
  createSafe,
  getPrivateKey,
  decryptAesKey,
  decodeItem,
  decodeFolder,
  decryptFile,
  encryptFolderName,
  encryptItem,
  encryptPrivateKey,
  moveFile
};
