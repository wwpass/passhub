import $ from 'jquery';
import { modalAjaxError } from './utils';
import state from './state';
import passhub from './passhub';

$('#deleteSafeBtn').click(() => {
  $.ajax({
    url: 'delete_safe.php',
    type: 'POST',
    data: {
      operation: $('#not_empty_safe_warning').is(':visible') ? 'delete_not_empty' : 'delete',
      verifier: state.csrf,
      SafeID: state.currentSafe.id,
    },
    error: (hdr, status, err) => {
      modalAjaxError($('#delete_safe_alert'), hdr, status, err);
    },
    success: (result) => {
      if (result.status === 'Ok') {
        if (result.hasOwnProperty('items')) {
          $('#not_empty_safe_warning').hide();
          $('#deleteSafeBtn').hide();
          $('#delete_safe_warning').hide();
          $('#deleteSafeCancelBtn').hide();
          $('#deleteSafeCloseBtn').show();
          $('#deleteSafeLabel').text('Safe deleted');
          $('#not_empty_safe_stats').text(`Deleted folders: ${result.folders}  items: ${result.items}`).show();
          return;
        }
        // window.location.href = 'index.php';
        $('#deleteSafeModal').modal('hide');
        passhub.refreshUserData();
        return;
      }
      if (result.status === 'not empty') {
        $('#delete_safe_warning').hide();
        $('#not_empty_safe_warning').show();
        return;
      }
      if (result.status === 'unsubscribe') {
        $('#delete_safe_warning').hide();
        $('#unsubscribe_safe_warning').show();
        $('#deleteSafeBtn').hide();
        $('#unsubscribeSafeBtn').show();
        return;
      }
      if (result.status === 'login') {
        window.location.href = 'expired.php';
        return;
      }
      $('#delete_safe_alert').html(result.status).show();
    },
  });
});

$('#deleteSafeModal').on('show.bs.modal', () => {
  $('#delete_safe_alert').text('').hide();
  $('.safe_to_delete').text(state.currentSafe.name);
  $('#not_empty_safe_stats').hide();
  $('#not_empty_safe_warning').hide();
  $('#delete_safe_warning').show();
  $('#deleteSafeBtn').show();
  $('#deleteSafeCancelBtn').show();
  $('#deleteSafeCloseBtn').hide();
  $('#unsubscribe_safe_warning').hide();
  $('#unsubscribeSafeBtn').hide();
});

$('#deleteSafeModal').on('hide.bs.modal', () => {
  if ($('#not_empty_safe_stats').is(':visible')) {
    // window.location.href = 'index.php';
    passhub.refreshUserData();
  }
});

$('#unsubscribeSafeBtn').click(function () {
  $.ajax({
    url: 'safe_acl.php',
    method: 'POST',
    data: {
      verifier: state.csrf,
      vault: state.currentSafe.id,
      operation: 'unsubscribe',
    },
    success: (result) => {
      $('#deleteSafeModal').modal('hide'); 
      passhub.refreshUserData();
    },
    error: (hdr, status, err) => {
      modalAjaxError($('#safe_users_alert'), hdr, status, err);
    },
  });
});
