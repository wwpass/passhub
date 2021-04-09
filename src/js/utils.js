import $ from 'jquery';

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

function serverLog(msg) {
  $.ajax({
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

function bsAlert(msg) {
  $('#bsAlert').text(msg);
  $('#bsAlertModal').modal('show');
}

const isXs = () => {
  if ($('#xs_indicator').is(':visible')) {
    return true;
  }
  return false;
};

function modalAjaxError(alertElement, hdr, status, err) {
  if (hdr.status === 0) {
    alertElement.text('You are offline. Please check your network.').show();
    return;
  }
  alertElement.text(`${status} ${err}`).show();
};

const humanReadableFileSize = (size) => {
  if (size < 1024) return `${size} B`;
  const i = Math.floor(Math.log(size) / Math.log(1024));
  let num = (size / Math.pow(1024, i));
  const round = Math.round(num);
  num = round < 10 ? num.toFixed(2) : round < 100 ? num.toFixed(1) : round
  return `${num} ${'KMGTPEZY'[i-1]}B`;
};

const isMobile = () => {
  const isIOS = navigator.userAgent.match(/iPhone|iPod|iPad/i)
  || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1); // crazy ios13 on iPad..
  
  const mobileDevice = isIOS || navigator.userAgent.match(/Android/i);
  return mobileDevice;
}

export {
  bsAlert,
  escapeHtml,
  isXs,
  serverLog,
  modalAjaxError,
  humanReadableFileSize,
  isMobile
};
