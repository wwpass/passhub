import $ from 'jquery';
import forge from 'node-forge';
import passhubCrypto from './crypto';
import passhub from './passhub';
import openmailclient from './openmailclient';

let role = 'readonly';

function shareByMailFinal(username, eAesKey) {
  $.ajax({
    url: 'safe_acl.php',
    method: 'POST',
    data: {
      verifier: passhub.csrf,
      vault: passhub.currentSafe.id,
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
      const body = `${passhub.userMail} shared a Passhub safe with you.\n\n Please visit ${url}`;
      openmailclient.openMailClient(username, subj, body);
      window.location.href = 'index.php';
      // return;
    },
    error: (hdr, status, err) => {
      passhub.modalAjaxError($('#shareByMailAlert'), hdr, status, err);
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
      verifier: passhub.csrf,
      vault: passhub.currentSafe.id,
      operation: 'email',
      name: recipientMail,
    },
    error: (hdr, status, err) => {
      passhub.modalAjaxError($('#shareByMailAlert'), hdr, status, err);
    },
    success: (result) => {
      if (result.status !== 'Ok') {
        $('#shareByMailAlert').text(result.status).show();
        return;
      }
      // const encryptedSrcAesKey = forge.util.hexToBytes(passhub.currentSafe.key);
      passhubCrypto.decryptAesKey(passhub.currentSafe.key)
        .then((aesKey) => {
          const peerPublicKey = forge.pki.publicKeyFromPem(result.public_key);
          const peerEncryptedAesKey = peerPublicKey.encrypt(aesKey, 'RSA-OAEP');
          const hexPeerEncryptedAesKey = forge.util.bytesToHex(peerEncryptedAesKey);
          shareByMailFinal(recipientMail, hexPeerEncryptedAesKey);
        });
    },
  });
});

function setShareRole(newRole) {
  role = newRole;
  $('.share_role_selector_text').text((newRole === 'administrator') ? 'admin' : newRole);
}

$('#shareByMailModal').on('show.bs.modal', () => {
  $('#recipientMail').val('');
  let recipientSafeName = passhub.currentSafe.name;
  if (passhub.userMail) {
    let { userMail } = passhub;
    const atIdx = userMail.indexOf('@');
    if (atIdx > 0) {
      userMail = userMail.substring(0, atIdx);
    }
    recipientSafeName += ' /' + userMail;
  }
  setShareRole('readonly');
  $('#recipientSafeName').val(recipientSafeName);
  $('#shareByMailAlert').text('').hide();
  $('#shareByMailLabel').find('span').text(passhub.currentSafe.name);
});

$('#shareByMailModal').on('shown.bs.modal', () => {
  $('#recipientMail').focus();
});

const shareRoleMenu = {
  selector: '.share_role_selector',
  trigger: 'left',
  delay: 100,
  autoHide: true,
  items: {
    administrator: {
      name: 'admin',
      callback: () => {
        setShareRole('administrator');
      },
    },
    readwrite: {
      name: 'editor',
      callback: () => {
        setShareRole('editor');
      },
    },
    readonly: {
      name: 'readonly',
      callback: () => {
        setShareRole('readonly');
      },
    },
  },
};

$.contextMenu(shareRoleMenu);
