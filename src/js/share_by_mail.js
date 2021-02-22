import $ from 'jquery';
import forge from 'node-forge';
import { modalAjaxError } from './utils';
import state from './state';
import passhubCrypto from './crypto';
import passhub from './passhub';
import openmailclient from './openmailclient';

function shareByMailFinal(username, eAesKey) {
  let role = $('.role_selector.add-user').text();
  if (role === 'admin') {
    role = 'administrator';
  }
  $.ajax({
    url: 'safe_acl.php',
    method: 'POST',
    data: {
      verifier: state.csrf,
      vault: state.currentSafe.id,
      operation: 'email_final',
      name: username,
      key: eAesKey,
      safeName: $('#recipientSafeName').val().trim(),
      role,

    },
    success: (result) => {
      if (result.status !== 'Ok') {
        $('#shareByMailAlert').text(result.status).show();
        return;
      }
      const url = window.location.href.substring(0, window.location.href.lastIndexOf('/')) + '/';
      const subj = 'Passhub safe shared with you';
      const body = `${state.userMail} shared a Passhub safe with you.\n\n Please visit ${url}`;
      openmailclient.openMailClient(username, subj, body);

      $('#shareByMailModal').modal('hide');
      passhub.refreshUserData();
    },
    error: (hdr, status, err) => {
      modalAjaxError($('#shareByMailAlert'), hdr, status, err);
    },
  });
}

$('#shareByMailBtn').click(() => {
  const recipientMail = $('#recipientMail').val().trim();
  if (!recipientMail) {
    $('#shareByMailAlert').html('* Please fill in recipient email address').show();
    return;
  }

  $.ajax({
    url: 'safe_acl.php',
    type: 'POST',
    data: {
      verifier: state.csrf,
      vault: state.currentSafe.id,
      operation: 'email',
      origin: window.location.origin,
      name: recipientMail,
    },
    error: (hdr, status, err) => {
      modalAjaxError($('#shareByMailAlert'), hdr, status, err);
    },
    success: (result) => {
      if (result.status !== 'Ok') {
        $('#shareByMailAlert').html(result.status).show();
        return;
      }
      passhubCrypto.decryptAesKey(state.currentSafe.key)
        .then((aesKey) => {
          const peerPublicKey = forge.pki.publicKeyFromPem(result.public_key);
          const peerEncryptedAesKey = peerPublicKey.encrypt(aesKey, 'RSA-OAEP');
          const hexPeerEncryptedAesKey = forge.util.bytesToHex(peerEncryptedAesKey);
          shareByMailFinal(recipientMail, hexPeerEncryptedAesKey);
        });
    },
  });
});

$('#shareByMailModal').on('show.bs.modal', () => {
  $('#recipientMail').val('');
  let recipientSafeName = state.currentSafe.name;
  if (state.userMail) {
    let userMail = state.userMail;
    const atIdx = userMail.indexOf('@');
    if (atIdx > 0) {
      userMail = userMail.substring(0, atIdx);
    }
    recipientSafeName += ' /' + userMail;
  }
  $('.role_selector.add-user').text('readonly');
  $('#recipientSafeName').val(recipientSafeName);
  $('#shareByMailAlert').text('').hide();
  $('#shareByMailLabel').find('span').text(state.currentSafe.name);
});

$('#shareByMailModal').on('shown.bs.modal', () => {
  $('#recipientMail').focus();
});
