import forge from 'node-forge';
import $ from 'jquery';

const createSafe = (publicKeyTxt, name, items, folders) => {
  const aesKey = forge.random.getBytesSync(32);
  const publicKey = forge.pki.publicKeyFromPem(publicKeyTxt);
  const encryptedAesKey = publicKey.encrypt(aesKey, 'RSA-OAEP');
  const hexEncryptedAesKey = forge.util.bytesToHex(encryptedAesKey);
  return { name, aes_key: hexEncryptedAesKey };
};

const escapeHtml = (unsafe) => {
  return unsafe
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');
};

function passhub_url_base() {
  let urlBase = window.location.href;
  urlBase = urlBase.substring(0, urlBase.lastIndexOf('/')) + '/';
  return urlBase;
}

const isXs = () => {
  if ($('#xs_indicator').is(':visible')) {
    return true;
  }
  return false;
};

function serverLog(msg) {
  $.ajax({
    // url: `index.php?current_safe=${passhub.currentSafe.id}`,
    url: 'serverlog.php',
    type: 'POST',
    data: {
    //  verifier: csrf,
      msg,
    },
    error: () => {},
    success: () => {},
  });
}

export {
  createSafe,
  escapeHtml,
  isXs,
  serverLog,
};
