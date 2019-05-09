import $ from 'jquery';
import passhub from './passhub';

if (window.navigator.userAgent.match(/iPhone|iPod|iPad/i)) {
  $('#SharingCodeValue').click(() => {
    document.getElementById('SharingCodeValue').selectionStart = 0;
    document.getElementById('SharingCodeValue').selectionEnd = 999;
  });
}

function safeShareTimer() {
  $('#share_safe_info').hide();
}

$('#send_sharing_code_as_message').click(() => {
  if (navigator.share) {
    navigator.share({
      title: 'PassHub invitation',
      text: $('#send_sharing_code_by_email').attr('data-msg'),
    });
  }
});

$('#id_copy_sharing_code').click(() => {
  $('#SharingCodeValue').focus();
  $('#SharingCodeValue').select();
  const success = document.execCommand('copy', false, null);
  document.getElementById('SharingCodeValue').selectionStart = 0;
  document.getElementById('SharingCodeValue').selectionEnd = 0;
  $('#share_safe_info').show();
  window.setTimeout(safeShareTimer, 1500);

  /*
  const msg = $('#send_sharing_code_by_email').attr('data-msg');
  const sc = $('#SharingCodeValue').val();
  $('#SharingCodeValue').val(msg);

  $('#SharingCodeValue').select();
  const success = document.execCommand('copy', false, null);
  document.getElementById('SharingCodeValue').selectionStart = 0;
  document.getElementById('SharingCodeValue').selectionEnd = 0;
  $('#SharingCodeValue').val(sc);
  $('#share_safe_info').show();
  window.setTimeout(safeShareTimer, 1500);
  */
});

let generateSharingCodeBtnUpdatePage = false;

function shareSafeBtnClickName() {
  const ajaxData = {
    verifier: passhub.csrf,
    vault: passhub.currentSafe.id,
  };
  if (!passhub.currentSafe.user_name) {
    const username = $('#sharingName').val().trim();
    if (!username) {
      $('#gen_share_with_name_alert').html('* Please fill in your name').show();
      return;
    }
    ajaxData.name = username;
  }
  $.ajax({
    url: 'get_sharing_code.php',
    type: 'POST',
    data: ajaxData,
    error: (hdr, status, err) => {
      passhub.modalAjaxError($('#gen_share_with_name_alert'), hdr, status, err);
    },
    success: (result) => {
      if (result.status !== 'Ok') {
        $('#gen_share_with_name_alert').text(result.status).show();
      } else {
        let urlBase = window.location.href;
        urlBase = urlBase.substring(0, urlBase.lastIndexOf('/')) + '/';
        generateSharingCodeBtnUpdatePage = true;
        const mailLink = 'mailto:?subject=PassHub invitation&body='
        + `${result.ownerName} shared a safe with you.`
        + ` Please login to %0D%0A%0D%0A${urlBase}%0D%0A%0D%0A and press "Accept Invitation" link.`
        + ` Use this safe sharing code: %0D%0A%0D%0A${result.code}%0D%0A%0D%0A`
        + ` NOTE: The code is set to expire in ${result.sharingCodeTTL / 60 / 60} hours`;
        /*
        + `%0D%0AThis is PassHub safe sharing code: ${result.code} %0D%0A%0D%0A`
        + `Please login to %0D%0A%0D%0A ${urlBase} %0D%0A%0D%0A and press %22Accept Invitation%22 link.`
        + `%0D%0A%0D%0A NOTE: The code is set to expire in ${result.sharingCodeTTL / 60 / 60} hours`;
        */
        const msg = `${result.ownerName} shared a safe with you.`
        + ` Please login to ${urlBase} and press "Accept Invitation" link.`
        + ` Use this safe sharing code: ${result.code}`
        + ` NOTE: The code is set to expire in ${result.sharingCodeTTL / 60 / 60} hours`;

        $('#gen_share_with_name_alert').hide();
        $('#SharingCodeValue').val(result.code);
        $('#SharingCodeLabel').show();
        $('#SharingCodeValue').show();
        if (!window.navigator.userAgent.match(/iPhone|iPod|iPad/i)) {
          $('#id_copy_sharing_code').show();
        }
        $('#send_sharing_code_by_email').attr('href', mailLink);
        $('#send_sharing_code_by_email').attr('data-msg', msg);

        if (navigator.share) {
          $('#send_sharing_code_as_message').show();
          $('#send_sharing_code_by_email').hide();
        } else {
          $('#send_sharing_code_as_message').hide();
          $('#send_sharing_code_by_email').show();
        }
        $('#generate_sharing_code_btn').hide();
      }
    },
  });
}

$('#generate_sharing_code_btn').click(shareSafeBtnClickName);

$('#safeShareModal').on('show.bs.modal', () => {
  $('#safeShareLabel').find('span').text(passhub.currentSafe.name);
  $('#sharingName').val('');
  $('#SharingCodeLabel').hide();
  $('#SharingCodeValue').val('').hide();
  $('#generate_sharing_code_btn').show();
  $('#gen_share_with_name_alert').text('').hide();

  $('#id_copy_sharing_code').hide();
  $('#send_sharing_code_by_email').hide();
  $('#send_sharing_code_by_email').attr('href', '#');
  $('#send_sharing_as_message').hide();

  generateSharingCodeBtnUpdatePage = false;

  if (passhub.currentSafe.user_name) {
    $('#sharingNameLabel').hide();
    $('#sharingName').hide();
    $('#generate_sharing_code_btn').trigger('click');
  } else {
    $('#sharingNameLabel').show();
    $('#sharingName').show();
  }
});

$('#safeShareModal').on('shown.bs.modal', () => {
  if ($('#sharingName').is(':visible')) {
    $('#sharingName').focus();
  }
});

$('#safeShareModal').on('hidden.bs.modal', () => {
  if (generateSharingCodeBtnUpdatePage) {
    window.location.href = `index.php?vault=${passhub.currentSafe.id}`;
  }
});
