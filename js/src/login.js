// import $ from 'jquery';
import * as WWPass from 'wwpass-frontend';

let urlBase = window.location.href;
urlBase = `${urlBase.substring(0, urlBase.lastIndexOf('/'))}/`;

// $('.passhub_url').text(urlBase);

function supportsHtml5Storage() {
  try {
    return 'localStorage' in window && window.localStorage !== null;
  } catch (e) {
    return false;
  }
}

function compatibleBrowser() {
  if (!supportsHtml5Storage()) {
    return false;
  }
  if (window.msCrypto) {
    return false;
  }
  if (!window.crypto) {
    return false;
  }
  if (window.crypto.subtle || window.crypto.webkitSubtle) {
    return true;
  }
  return false;
}

const isIOS = navigator.userAgent.match(/iPhone|iPod|iPad/i)
|| (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1); // crazy ios13 on iPad..

const mobileDevice = isIOS || navigator.userAgent.match(/Android/i);


function isSafariPrivateMode() {
  const isSafari = navigator.userAgent.match(/Version\/([0-9\._]+).*Safari/);

  if (!isSafari || !isIOS) {
    return false;
  }
  const version = parseInt(isSafari[1], 10);
  if (version >= 11) {
    try {
      window.openDatabase(null, null, null, null);
      return false;
    } catch (_) {
      return true;
    }
  } else if (version === 10) {
    if (localStorage.length) {
      return false;
    }
    try {
      localStorage.test = 1;
      localStorage.removeItem('test');
      return false;
    } catch (_) {
      return true;
    }
  }
  return false;
}

if (isSafariPrivateMode()) {
  window.location.href = 'error_page.php?js=SafariPrivateMode';
}

if (!navigator.userAgent.match(/electron/)) {
  if ((window.location.protocol !== 'https:') && (window.location.hostname !== 'localhost') && !window.location.hostname.endsWith('.localhost')) {
    window.location.href = 'notsupported.php?js=2';
  } else if (!compatibleBrowser()) {
    window.location.href = 'notsupported.php?js=1';
  }
}

WWPass.authInit({
//  qrcode: document.querySelector('#qrcode'),
  qrcode: '#qrcode',
  passkey: document.querySelector('#button--login'),
  ticketURL: `${urlBase}getticket.php`,
  callbackURL: `${urlBase}login.php`,
});


if (mobileDevice) {
  // $('#qrcode').addClass('qrtap');
  // $('.qr_code_instruction').html('Touch the QR code or scan it with <b>WWPass&nbsp;PassKey&nbsp;app</b>');
  document.querySelector('#qrcode').classList.add('qrtap');

  if (document.querySelector('.qr_code_instruction')) { // pre-2019
    document.querySelector('.qr_code_instruction').innerHTML = 'Tap the QR code or scan it with <b>WWPass&nbsp;Key&nbsp;app</b> to open your PAssHub vault';
  }
} else {
  $(document).ready(() => {
    function checkPlugin() {
      if (WWPass.pluginPresent()) {
        // pre-2019 login (legacy)
        let hardwarePassKeySet = document.querySelectorAll('.hardware');
        if (hardwarePassKeySet.length) {
          [].forEach.call(hardwarePassKeySet, (it) => {
            it.classList.remove('hardware');
          });
          const infoShare = document.querySelector('.landingContent__infoShare');
          infoShare.classList.add('landingContent__infoShare--hardToken');
          return;
        }
        // biz login
        hardwarePassKeySet = document.querySelectorAll('.landingContent__hardToken');
        if (hardwarePassKeySet.length) {
          [].forEach.call(hardwarePassKeySet, (it) => {
            it.classList.remove('landingContent__hardToken');
          });
          return;
        }
        // login 2019
        const loginBtn = document.querySelector('#button--login');
        loginBtn.classList.remove('embedded--hide');
        $('#button--login > button').hide();
        return;
      }
      setTimeout(checkPlugin, 100);
    }
    setTimeout(checkPlugin, 100);
  });
}


// login 2019

document.addEventListener('DOMContentLoaded', () => {
  const qrtext = document.querySelector('#qrtext');
  if (qrtext) {
    if (mobileDevice) {
      // qrtext.innerText = 'Tap the QR code or ';
      qrtext.innerHTML = 'Download <b>WWPass&nbsp;Key&nbsp;App</b> and tap the QR code';
    } else {
      qrtext.innerText = 'Scan the QR code with WWPass™ Key App';
      // qrtext.classList.add('text--qrcode');
    }
    qrtext.style.display = 'block';
  }
});
