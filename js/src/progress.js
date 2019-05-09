import $ from 'jquery';

let progressTimeout;

function lock(seconds, message) {
  let timeout = 10; // defaults to 10 seconds
  if (undefined !== seconds) {
    timeout = seconds;
  }
  if (undefined === message) {
    message = '';
  }
  $('.progress-lock__message > span').text(`${message} Please wait…`);
  $('#progress-lock').show(0);
  if (timeout) {
    progressTimeout = window.setTimeout(() => {
      window.location.href = 'error_page.php?js=timeout';
    }, timeout * 1000);
  }
}

function unlock() {
  clearTimeout(progressTimeout);
  $('#progress-lock').hide();
}

export default { lock, unlock };
