import $ from 'jquery';
// import passhub from './passhub';

let pwShown = 0;
let unShown = 1;

function ph_alert(msg) {
  $('#alert_info_text').text(msg);
  $('.alert').show();
  window.setTimeout(() => { $('.alert').hide(); }, /* AlertShowTimer, */ 1500);
}

function init_item_pane() {
  pwShown = 0;
  unShown = 1;
  $('#item_pane_password').attr('type', 'password');
}

function toggle_pw() {
  if (pwShown === 0) {
    $('#item_pane_password').attr('type', 'text');
    $('#creds1ID').attr('type', 'text');
    pwShown = 1;
    $('.toggle_pw_button').html("<svg width='24' height='24' style='stroke:black;opacity:0.5'><use xlink:href='img/SVG2/sprite.svg#i-hide'></use></svg>");
  } else {
    $('#item_pane_password').attr('type', 'password');
    $('#creds1ID').attr('type', 'password');
    pwShown = 0;
    $('.toggle_pw_button').html("<svg width='24' height='24' style='stroke:black;opacity:0.5'><use xlink:href='img/SVG2/sprite.svg#i-show'></use></svg>");
  }
}

function toggle_un() {
  if (unShown === 0) {
    $('#item_pane_username').attr('type', 'text');
    unShown = 1;
  } else {
    $('#item_pane_username').attr('type', 'password');
    unShown = 0;
  }
}

$(document).ready(() => {
  $('#show_username').click(toggle_un);
  $('.toggle_pw_button').click(toggle_pw);
  // $('.item_pane_back').click(passhub.showTable);
  if (window.navigator.userAgent.match(/iPhone|iPod|iPad/i)) {
    $('#item_pane_copy_username').hide();
    $('#item_pane_copy_password').hide();
    $('#item_pane_username').click(() => {
      if (unShown === 0) {
        toggle_un();
      }
      $('#item_pane_username').focus();
      document.getElementById('item_pane_username').selectionStart = 0;
      document.getElementById('item_pane_username').selectionEnd = 999;
    });
    $('#item_pane_password').click(() => {
      if (pwShown === 0) {
        toggle_pw();
      }
      $('#item_pane_password').focus();
      document.getElementById('item_pane_password').selectionStart = 0;
      document.getElementById('item_pane_password').selectionEnd = 999;
    });
  }
});

$('#item_pane_copy_username').click(() => {
  if (unShown === 0) {
    $('#item_pane_username').attr('type', 'text');
  }
  $('#item_pane_username').focus();
  $('#item_pane_username').select();
  const success = document.execCommand('copy', false, null);
  if (unShown === 0) {
    $('#item_pane_username').attr('type', 'password');
  }
  if (success) {
    ph_alert('Username copied to clipboard');
  } else {
    ph_alert('Copy username: fail');
  }
  $('#show_userrname').focus();
});


$('#item_pane_copy_password').click(() => {
  if (pwShown == 0) {
    $('#item_pane_password').attr('type', 'text');
  }
  $('#item_pane_password').focus();
  $('#item_pane_password').select();
  const success = document.execCommand('copy', false, null);
  if (pwShown == 0) {
    $('#item_pane_password').attr('type', 'password');
  }
  if (success) {
    ph_alert('Password copied to clipboard');
  } else {
    ph_alert('Copy password: fail');
  }
  $('#show_password').focus();
});

//----------------------------------------------------------------------------------


$('#showCreds').on('show.bs.modal', () => {
  pwShown = 0;
  $('#creds1ID').attr('type', 'password');
});

$('#id_copy_username').click(() => {
  $('#showCreds').modal('hide');

  const temp = $('<input>');
  $('body').append(temp);
  const txt = $('#creds0ID').val();
  temp.val(txt);
  temp.focus();
  temp.select();
  document.execCommand('copy', false, null);
  temp.remove();
  $('#showCreds_info').text('username copied to clipboard');
  $('#showCreds').modal('show');
});

$('#id_copy_password').click(() => {
  $('#showCreds').modal('hide');
  const temp = $('<input>');
  $('body').append(temp);
  const txt = $('#creds1ID').val();
  temp.val(txt);
  temp.focus();
  temp.select();
  document.execCommand('copy', false, null);
  temp.remove();

  $('#showCreds_info').text('password copied to clipboard');
  $('#showCreds').modal('show');
});

if (window.navigator.userAgent.match(/iPhone|iPod|iPad/i)) {
  $('#id_copy_username').hide();
  $('#id_copy_password').hide();

  $('#creds0ID').click(() => {
    if (unShown === 0) {
//      toggle_un();
    }
    document.getElementById('creds0ID').selectionStart = 0;
    document.getElementById('creds0ID').selectionEnd = 999;
  });

  $('#creds1ID').click(() => {
    if (pwShown === 0) {
      toggle_pw();
    }
    document.getElementById('creds1ID').selectionStart = 0;
    document.getElementById('creds1ID').selectionEnd = 999;
  });
}
