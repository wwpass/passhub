import $ from 'jquery';
import * as utils from './utils';
import state from './state';
import safes from './safes';
import passhubCrypto from './crypto';
import passhub from './passhub';

$('#create_new_vault_btn').click(() => {
  const safename = $('#SafeName_create').val().trim();
  if (safename == '') {
    $('#create_vault_alert').text(' * Please fill in Safe name');
    $('#create_vault_alert').show();
  } else {
    const safe = passhubCrypto.createSafe(state.publicKeyPem, safename);
    $.ajax({
      url: 'create_vault.php',
      type: 'POST',
      data: {
        verifier: state.csrf,
        safe,
      },
      error: (hdr, status, err) => {
        utils.modalAjaxError($('#create_vault_alert'), hdr, status, err);
      },
      success: (result) => {
        if (result.status === 'Ok') {
          // window.location.href = `index.php?vault=${result.id}`;
          $('#newSafe').modal('hide');
          safes.setNewFolderID(result.id);
          passhub.refreshUserData();
          return;
        }
        if (result.status === 'login') {
          window.location.href = 'expired.php';
          return;
        }
        $('#create_vault_alert').text(result.status);
        $('#create_vault_alert').show();
      },
    });
  }
});

$('#newSafe').on('shown.bs.modal', () => {
  $('#create_vault_alert').text('').hide();
  $('#SafeName_create').val('').focus();
});

$('#SafeName_create').focus(function() {
  $('#create_vault_alert').hide();
});
