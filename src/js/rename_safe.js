import $ from 'jquery';
import { modalAjaxError } from './utils';
import state from './state';
import passhub from './passhub';

$('#id_SafeRename_button').click(() => {
  const safename = $('#SafeName_rename').val().trim();
  if (safename == '') {
    $('#rename_vault_alert').text(' * Safe name cannot be empty').show();
    return;
  }
  if (safename === state.currentSafe.name) {
    $('#renameVault').modal('hide');
    return;
  }
  $.ajax({
    url: 'update_vault.php',
    type: 'POST',
    data: {
      vault: state.currentSafe.id,
      verifier: state.csrf,
      newSafeName: safename,
    },
    error: (hdr, status, err) => {
      modalAjaxError($('#rename_vault_alert'), hdr, status, err);
    },
    success: (result) => {
      if (result.status === 'Ok') {
        $('#renameVault').modal('hide');
        passhub.refreshUserData();
        return;
      }
      if (result.status === 'login') {
        window.location.href = 'expired.php';
        return;
      }
      $('#rename_vault_alert').html(result.status).show();
    },
  });
});

$('#renameVault').on('shown.bs.modal', () => {
  $('#rename_vault_alert').text('').hide();
  $('#SafeName_rename').val(state.currentSafe.name).focus();
});
