import $ from 'jquery';
import progress from './progress';
import passhubCrypto from './crypto';

(() => {
  item_form.aes_key = '';
  item_form.pw_shown = 0;

  function get_aes_key() {
    return item_form.aes_key;
  }

  function decryptFailed(err) {
    alert(`Decrytpion failed ${err}`);
    window.location.href = `error_page.php?js=387&error=${err}`;
  }

  function fillTheForm() {  
    if (!item_form.create) {
      const { item } = item_form;
      if (item.hasOwnProperty('note') && (item.note == 1)) {
        item_form.note = 1;
      }
      const data = passhubCrypto.decodeItem(item, item_form.aes_key);

      $('#title').val(data[0]);
      $('#username').val(data[1]);
      $('#password').val(data[2]);
      $('#confirm_password').val(data[2]);
      $('#url').val(data[3]);
      $('#notes').text(data[4]);
    }
    $('.phub-dialog').show();
    if (item_form.create) {
      $('#title').focus();
    }
    page_resize();
    progress.unlock();
  }

  function cancelForm() { // iOS
    if (navigator.userAgent.match(/iPhone|iPod|iPad|Macintosh/i)) {
      $('#ios_cancel').val('1');
      $('#password').attr('type', 'hidden');
      $('#confirm_password').attr('type', 'hidden');
      $('h3').hide();
      progress.lock();
      $('#entry_form').hide().submit();
      return 0;
    }
    $('h3').hide();
    progress.lock();
    $('#entry_form').hide();
    window.location.href = 'index.php?show_table';
    return 0;
  }

  function show_alert(message) {
    $('#alert_message').text(message).show();
    window.scrollTo(0, 0);
  }

  function submitForm() {
    const x = $('#notes').val().length;
    if (x >= item_form.maxNoteSize) {
      show_alert(`Warning: Notes text too long, truncated. Max size is ${item_form.maxNoteSize} bytes.`);
      return false;
    }
    if ($('#title').val().trim().length === 0) {
      show_alert('Please fill in "Title" field');
      $('#title').val('');
      return false;
    }
    if (!item_form.note) {
      if ($('#password').val() !== $('#confirm_password').val()) {
        show_alert("Passwords don't match");
        return false;
      }
    }
    $('#password').attr('type', 'hidden');
    $('#confirm_password').attr('type', 'hidden');

    // const pData = [$('#title').val().trim(), $('#username').val().trim(), $('#password').val().trim(), $('#url').val().trim(), $('#notes').val().trim()];
    // const pData = [$('#title').val().trim(), $('#username').val().trim(), $('#password').val(), $('#url').val(), $('#notes').val().trim()];
    const pData = [$('#title').val().trim(), $('#username').val(), $('#password').val(), $('#url').val(), $('#notes').val().trim()];
    const options = item_form.note? { note: 1 } : {};
    const eData = passhubCrypto.encryptItem(pData, get_aes_key(), options);
    $('#encrypted_data').val(eData);
    $('h3').hide();
    progress.lock();
    $('#entry_form').hide().submit();
  }

  function decodeKeys(ePrivateKey, ticket) {
    passhubCrypto.getPrivateKey(ePrivateKey, ticket)
      .then(() => passhubCrypto.decryptAesKey(item_form.encrypted_key_CSE))
      .then((pKey) => {
        // aesKey = pKey;
        item_form.aes_key = pKey;
        return fillTheForm();
      })
      .catch((err) => {
        alert(err);
      });
  }

  $(window).on('load', () => {
    // progress.lock();
    decodeKeys(item_form.privateKey_CSE, item_form.ticket);
  });

  function toggle_pw() {
    if (item_form.pw_shown == 0) {
      $('#password').attr('type', 'text');
      $('#confirm_password').attr('type', 'text');
      item_form.pw_shown = 1;
      $('#show_password').html("<svg width='24' height='24' style='stroke:black;opacity:0.5'><use xlink:href='img/SVG2/sprite.svg#i-hide'></use></svg>");
    } else {
      $('#password').attr('type', 'password');
      $('#confirm_password').attr('type', 'password');
      item_form.pw_shown = 0;
      $('#show_password').html("<svg width='24' height='24' style='stroke:black;opacity:0.5'><use xlink:href='img/SVG2/sprite.svg#i-show'></use></svg>");
    }
  }

  $(document).ready(() => {
    if (item_form.create) {
      $('#entry_form').attr('action', 'create.php');
    }
    if (item_form.note) {
      $('.note_hidden').hide();
      if (item_form.create) {
        $('h3').text('Create Note');
      } else {
        $('h3').text('Edit Note');
      }
    } else if (item_form.create) {
      $('h3').text('Create Entry');
    } else {
      $('h3').text('Edit Entry'); // default
    }
    $('#show_password').click(toggle_pw);
    $('#save_button').click(submitForm);
    $('#cancel_button').click(cancelForm); // iOS
    if (navigator.userAgent.match(/iPhone|iPod|iPad/i)) {
      $('input').css('font-size', '16px');
      $('textarea').css('font-size', '16px');
    }
    if (navigator.userAgent.match(/iPhone|iPod|iPad|Android/i)) {
      $('#url').attr('type', 'url');
    }
  });

  $('#generate_password_button').click(() => {
    $('#generatePassword').modal('show');
  });

  function page_resize() {
    if (item_form.note) {
      if (document.querySelector('#notes')) { // can write
        const dialogTop = $('.phub-dialog').offset().top;
        const t = parseInt(20 + dialogTop);
        let dialogHeight = $('.phub-dialog').height(`calc(100vh - ${t}px)`).height();
        dialogHeight = parseInt(dialogHeight);

        if (navigator.userAgent.match(/iPhone/) && (screen.width === 375) && (screen.height === 812)) { // iPhone X
          dialogHeight -= 140;
        } else if (navigator.userAgent.match(/iPhone/) && (screen.width === 375) && (screen.height === 667)) { // iPhone 6
          dialogHeight -= 100;
        } else if (navigator.userAgent.match(/iPhone/) && (screen.width === 320) && (screen.height === 568)) { // iPhone 6s, Anf, display siz
          dialogHeight -= 60;
        } else if (navigator.userAgent.match(/iPhone/) && ((screen.height === 320) || (screen.width === 320))) { // iPhone 5
          dialogHeight -= 80;
        } else if (navigator.userAgent.match(/iPhone|iPod|iPad|Android/i)) {
          dialogHeight -= 60;
        }
        // 412 732: Galaxy edge 7

        const notesTop = $('#notes').offset().top;
        const notesInDialogOffset = notesTop - dialogTop;
        $('#notes').height(dialogHeight - notesInDialogOffset - 100);
      }  
    }
  }

  $(window).resize(page_resize);
})();
