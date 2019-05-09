import $ from 'jquery';
import openmailclient from './openmailclient';

page_args.verifier = document.getElementById('csrf').getAttribute('data-csrf');

function userTable() {
  const { timeZone } = Intl.DateTimeFormat().resolvedOptions();
  $('#th_seen').html(`Seen<br> (${timeZone})`);
  const { users } = page_args;
  for (let u = 0; u < users.length; u++) {
    if (!users[u].email) {
      users[u].email = '-';
    }
    let row = '<tr>';
    if (users[u]._id == page_args.me) {
      row += '<td></td>';
      row += '<td>';
      if (users[u].site_admin) {
        row += '<span class = "glyphicon glyphicon-check" style="color:grey; display:block; text-align:center; margin:0 auto;"></span></td>';
      } else {
        row += '<span class = "glyphicon glyphicon-unchecked" style="color:black; display:block; text-align:center; margin:0 auto;"></span></td>';
      }
      row += `<td><b>${users[u].email}</b></td>`;
      //row += `<td><b>${users[u]._id}</b></td>`;
      row += `<td><b>${users[u].safe_cnt}</b></td>`;
      row += `<td><b>${users[u].shared_safe_cnt}</b></td><td><b>That's You</b></td></tr>`;
    } else {
      row += `<td class="delete_user" style="cursor:pointer" data-mail = ${users[u].email} data-id = ${users[u]._id}><span class = "glyphicon glyphicon-remove" style="color:red"></span></td>`;
      row += `<td class="site_admin" data-mail = ${users[u].email} data-id = ${users[u]._id}>`;
      if (users[u].site_admin) {
        row += '<span class = "glyphicon glyphicon-check" style="color:green; cursor:pointer; display:block; text-align:center; margin:0 auto;"></span></td>';
      } else {
        row += '<span class = "glyphicon glyphicon-unchecked" style="color:black; cursor:pointer; display:block; text-align:center; margin:0 auto;"></span></td>';
      }
      row += `<td>${users[u].email}</td>`;
      // row += `<td>${users[u]._id}</td>`;
      row += `<td>${users[u].safe_cnt}</td>`;
      const seen = new Date(users[u].lastSeen).toLocaleString();
      row += `<td>${users[u].shared_safe_cnt}</td><td>${seen}</td></tr>`;
    }
    $('#userTableBody').append(row);
  }
}

userTable();

$('.delete_user').click(function () {
  $('#user_mail').text($(this).attr('data-mail'));
  $('#user_id').text($(this).attr('data-id'));
  $('#deleteUserModal').modal('show');
});

$('#deleteUserModal').on('show.bs.modal', () => {
  $('#delete_user_alert').text('').hide();
});
/*
$('.invite_external_user').click(() => {
  $('#inviteByMail').modal('show');
});
*/
$('.white_list').click(() => {
  $('#mailWhiteList').modal('show');
});

function fillWhiteList(mailArray) {

  function cmp(o1, o2) {
    const u1 = o1.email.toUpperCase();
    const u2 = o2.email.toUpperCase();
    if (u1 < u2) {
      return -1;
    }
    if (u1 > u2) {
      return 1;
    }
    return 0;
  }

  if (mailArray.length === 0) {
    $('#white_list_ul').append('<div style="list-style-type: none;">&lt;List of invited users: empty.&gt;</div>');
    return;
  }

  mailArray.sort(cmp);

  for (let m = 0; m < mailArray.length; m++) {
    const rm = `<span class = "glyphicon glyphicon-remove" data-mail = ${mailArray[m].email}></span>`;
    $('#white_list_ul').append(`<div class='white_list_item'>${rm} ${mailArray[m].email}</div>`);
  }
}

$('body').on('click', '.white_list_item .glyphicon-remove', function () {
  const email = $(this).attr('data-mail');
  $.ajax({
    url: 'iam.php',
    type: 'POST',
    data: {
      verifier: page_args.verifier,
      deleteMail: email,
    },
    error: (hdr, status, err) => {
      $('#inviteByMailAlert').text(`${status} ${err}`).show();
    },
    success: (result) => {
      if (result.status === 'Ok') {
        $('#newUserMail').val('').focus();
        $('#white_list_ul').empty();
        fillWhiteList(result.mail_array);
        return;
      }
      if (result.status === 'login') {
        window.location.href = 'expired.php';
        return;
      }
      $('#inviteByMailAlert').html(result.status).show();
    },
  });
});

$('#mailWhiteList').on('shown.bs.modal', () => {
  $('#white_list_ul').empty();
  $.ajax({
    type: 'GET',
    data: {
      white_list: true,
    },
    error: (hdr, status, err) => {
      $('#delete_user_alert').text(`${status} ${err}`).show();
    },
    success: (result) => {
      if (result.status === 'Ok') {
        fillWhiteList(result.mail_array);
        return;
      }
      if (result.status === 'login') {
        window.location.href = 'expired.php';
      }
    },
  });
});

$('.site_admin').click(function () {
  $.ajax({
    url: 'edit_user.php',
    type: 'POST',
    data: {
      verifier: page_args.verifier,
      id: $(this).attr('data-id'),
      //   email:  $('#user_mail').text(),
    },
    error: (hdr, status, err) => {
      alert(`${status} ${err}`);
    },
    success: (result) => {
      if (result.status === 'Ok') {
        window.location.href = 'iam.php';
        return;
      }
      if (result.status === 'login') {
        window.location.href = 'expired.php';
        return;
      }
      alert(result.status);
    },
  });
});

$('#deleteUserBtn').click(() => {
  $.ajax({
    url: 'delete_user.php',
    type: 'POST',
    data: {
      verifier: page_args.verifier,
      id: $('#user_id').text(),
      email: $('#user_mail').text(),
    },
    error: (hdr, status, err) => {
      $('#delete_user_alert').text(`${status} ${err}`).show();
    },
    success: (result) => {
      if (result.status === 'Ok') {
        window.location.href = 'iam.php';
        return;
      }
      if (result.status === 'login') {
        window.location.href = 'expired.php';
        return;
      }
      $('#delete_user_alert').html(result.status).show();
    },
  });
});

// $('#newUserMail').on('input', function () {
$('#newUserMail').focusin(() => {
  if ($('#inviteByMailAlert').is(':visible')) {
    $('#inviteByMailAlert').text('').hide();
  }
});

$('.white_list_cancel').on('click', () => {
  $('#newUserMail').val('').focus();
});

/*
function openMailClient(to, subj, body) {
  const encodedBody = encodeURIComponent(body); 
  const link = `mailto:${to}?subject=${subj}&body=${encodedBody}`;
  $('#mailhref').attr('href', link);
  document.getElementById('mailhref').click();
}
*/

$('#id_SubmitNewUserMail').click(() => {
  const email = $('#newUserMail').val().trim();
  const re = /\S+@\S+\.\S+/;

  if (!re.test(email)) {
    $('#inviteByMailAlert').text(' * Please provide a valid email address').show();
    return;
  }

  $.ajax({
    url: 'iam.php',
    type: 'POST',
    data: {
      verifier: page_args.verifier,
      newUserMail: email,
    },
    error: (hdr, status, err) => {
      $('#inviteByMailAlert').text(`${status} ${err}`).show();
    },
    success: (result) => {
      if (result.status === 'Ok') {
        $('#newUserMail').val('').focus();
        $('#white_list_ul').empty();
        fillWhiteList(result.mail_array);
        const url = window.location.href.substring(0, window.location.href.lastIndexOf('/')) + '/';
        const subj = `You have been granted access to ${url} password manager`;
        const body = `Please follow the instructions on \n\n ${url} \n\n to create your account`;
        openmailclient.openMailClient(email, subj, body);
        return;
      }
      if (result.status === 'login') {
        window.location.href = 'expired.php';
        return;
      }
      $('#inviteByMailAlert').html(result.status).show();
    },
  });
});

$('#mailWhiteList').on('shown.bs.modal', () => {
  $('#inviteByMailAlert').text('').hide();
  $('#invitation_mail').text("You are invited to create an account at ");
  $('#newUserMail').val('').focus();
});
