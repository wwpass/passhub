import $ from 'jquery';
import passhubCrypto from './crypto';
import passhub from './passhub';


/*
  from search: no search updated
*/


let init;

function showAlert(message) {
  $('#item_form_alert').text(message).show();
  window.scrollTo(0, 0);
}

function submitItemForm() {
  if ($('#item_form_title').val().trim().length === 0) {
    showAlert('Please fill in "Title" field');
    $('#item_form_title').val('');
    return;
  }
  /*
  if (!init.note) {
    if ($('#item_form_password').val() !== $('#item_form_confirm_password').val()) {
      showAlert("Passwords don't match");
      return;
    }
  }
  */
  const pData = [
    $('#item_form_title').val().trim(),
    $('#item_form_username').val(),
    $('#item_form_password').val(),
    $('#item_form_url').val(),
    $('#item_form_notes').val().trim(),
  ];
  const otpSecretIn = $('#item_form_otp_secret').val().trim();
  const otpSecret = otpSecretIn.replace(/-/g, '').replace(/ /g, '');
  if (otpSecret.length > 0) {
    if (!/^[A-Za-z2-7=]+$/.test(otpSecret)) {
      showAlert('Invalid characters in OTP secret, only A-Z a-z and 2-7 are allowed, spaces optional');
      return;
    }
    pData.push(otpSecretIn);
  }

  const options = init.note ? { note: 1 } : {};

  const eData = passhubCrypto.encryptItem(pData, init.safe.bstringKey, options);
  const data = {
    verifier: init.csrf,
    vault: init.safe.id,
    folder: init.folder,
    encrypted_data: eData,
  };
  if (!init.create) {
    data.entryID = init.itemID;
  }

  $.ajax({
    url: 'items.php',
    method: 'POST',
    data,
    success: (result) => {
      if (result.status === 'Ok') {
        $('#item_form_page').hide();
        $('#index_page_row').show();
        passhub.indexPageResize();
        passhub.refreshUserData();
        return;
      }
      if (result.status === 'login') {
        window.location.href = 'expired.php';
        return;
      }
      if (result.status === 'expired') {
        window.location.href = 'expired.php';
        return;
      }
      if (result.status === 'no rights') {
        showAlert('Sorry you do not have editor rights for this safe');
        return;
      }
      showAlert(result.status);
    },
    error: (hdr, status, err) => {
      passhub.modalAjaxError($('#safe_users_alert'), hdr, status, err);
    },
  });
}

$('#item_form_otp_link').click(function () {
  $('#item_form_otp_link').hide();
  $('#item_form_otp_group').show();
});


function showItemForm(initObject) {
  init = { ...initObject };

  $('#item_form_otp_link').show();
  $('#item_form_otp_group').hide();
  if (init.create) {
    $('#item_form_header').text(init.note ? 'Create Note' : 'Create Item');

    $('#item_form_title').val('');
    $('#item_form_username').val('');
    $('#item_form_password').val('');
    $('#item_form_confirm_password').val('');
    $('#item_form_url').val('');
    $('#item_form_notes').val('');
    $('#item_form_otp_secret').val('');
    $('#item_form_title').focus();
  } else {
    for (let i = 0; i < init.safe.items.length; i++) {
      if (init.safe.items[i]._id === init.itemID) {
        const item = passhub.currentSafe.items[i];
        init.note = item.note;
        $('#item_form_header').text(item.note ? 'Edit Note' : 'Edit Item');
        $('#item_form_title').val(item.cleartext[0]);
        $('#item_form_username').val(item.cleartext[1]);
        $('#item_form_password').val(item.cleartext[2]);
        $('#item_form_confirm_password').val(item.cleartext[2]);
        $('#item_form_url').val(item.cleartext[3]);
        $('#item_form_notes').val(item.cleartext[4]);
        if (item.cleartext.length === 6) {
          $('#item_form_otp_link').hide();
          $('#item_form_otp_group').show();
          $('#item_form_otp_secret').val(item.cleartext[5]);
        }
      }
    }
  }

  if (init.note) {
    $('.note_hidden').hide();
  } else {
    $('.note_hidden').show();
  }
  $('#item_form_alert').hide();

  $('#index_page_row').hide();
  $('#item_form_page').show();
  passhub.indexPageResize();

  const data = {
    check: true,
    vault: init.safe.id,
    verifier: init.csrf,
  };
  if (!init.create) {
    data.entryID = init.itemID;
  }

  $.ajax({
    url: 'items.php',
    type: 'POST',
    data,
    error: () => {
      // passhub.modalAjaxError($('#add_from_invite_name_alert'), hdr, status, err);
    },
    success: (result) => {
      if (result.status === 'Ok') {
        return;
      }
      if (result.status === 'login') {
        window.location.href = 'expired.php';
        return;
      }
      if (result.status === 'expired') {
        window.location.href = 'expired.php';
        return;
      }
      showAlert(result.status);
    },
  });
}

let itemFormPwShown = false;
function togglePw() {
  if (itemFormPwShown === false) {
    $('#item_form_password').attr('type', 'text');
    $('#item_form_confirm_password').attr('type', 'text');
    itemFormPwShown = true;
    $('#show_password').html("<svg width='24' height='24' style='stroke:black;opacity:0.5'><use href='#i-hide'></use></svg>");
  } else {
    $('#item_form_password').attr('type', 'password');
    $('#item_form_confirm_password').attr('type', 'password');
    itemFormPwShown = false;
    $('#show_password').html("<svg width='24' height='24' style='stroke:black;opacity:0.5'><use href='#i-show'></use></svg>");
  }
}

let itemFormOtpShown = false;

function toggleOtp() {
  if (itemFormOtpShown === false) {
    $('#item_form_otp_secret').attr('type', 'text');
    itemFormOtpShown = true;
    $('#item_form_otp_secret').html("<svg width='24' height='24' style='stroke:black;opacity:0.5'><use href='#i-hide'></use></svg>");
  } else {
    $('#item_form_otp_secret').attr('type', 'password');
    itemFormOtpShown = false;
    $('#item_form_otp_secret').html("<svg width='24' height='24' style='stroke:black;opacity:0.5'><use href='#i-show'></use></svg>");
  }
}

function initItemForm() {
  $('#generate_password_button').click(() => {
    $('#generatePassword').modal('show');
  });
  $('#item_form_title').focus(() => {
    $('#item_form_alert').hide();
  });
  $('#item_form_password').focus(() => {
    $('#item_form_alert').hide();
  });
  $('#item_form_confirm_password').focus(() => {
    $('#item_form_alert').hide();
  });
  $('#item_form_otp_secret').focus(() => {
    $('#item_form_alert').hide();
  });

  $('#show_password').click(togglePw);
  $('#show_otp_secret').click(toggleOtp);
  itemFormOtpShown = true;
  toggleOtp();

  $('.item_form_submit').click(() => submitItemForm());

  $('.item_form_close').click(() => {
    $('#item_form_page').hide();
    $('#index_page_row').show();
    passhub.indexPageResize();
  });
}

export {
  initItemForm,
  showItemForm,
};
