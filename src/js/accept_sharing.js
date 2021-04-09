import $ from 'jquery';
import state from './state';
import { modalAjaxError } from './utils';

$('#add_by_invite_btn').click(() => {
  const safename = $('#SafeName_invite').val().trim();
  const invitecode = $('#inviteCode').val().trim();
  const username = $('#newUserName').val().trim();
  if (safename == '') {
    $('#add_from_invite_name_alert').text(' * Safe name cannot be empty').show();
  } else if (invitecode == '') {
    $('#add_from_invite_name_alert').text('* Please enter an invitation code').show();
  } else if (username == '') {
    $('#add_from_invite_name_alert').text(' * Please define your name').show();
  } else {
    $.ajax({
      url: 'add_by_invite.php',
      type: 'POST',
      data: {
        newSafeName: safename,
        inviteCode: invitecode,
        newUserName: username,
        verifier: state.csrf,
      },
      error: (hdr, status, err) => {
        modalAjaxError($('#add_from_invite_name_alert'), hdr, status, err);
      },
      success: (result) => {
        if (result.status === 'Ok') {
          window.location.href = `index.php?vault=${result.vault}`;
          return;
        }
        if (result.status === 'login') {
          window.location.href = 'expired.php';
          return;
        }
        $('#add_from_invite_name_alert').text(result.status).show();
      },
    });
  }
});

$('#fromInviteSafe').on('show.bs.modal', () => {
  $('#add_from_invite_name_alert').text('').hide();
  $('#SafeName_invite').val('');
  $('#inviteCode').val('');
  $('#newUserName').val('');
});

$('#fromInviteSafe').on('shown.bs.modal', () => {
  $('#inviteCode').focus();
});
