import forge from 'node-forge';
import $ from 'jquery';
import progress from './progress';
import passhubCrypto from './crypto';
import './account';
import passhub from './passhub';


let init;
function showAlert(message) {
  $('#file_form_alert').text(message).show();
  window.scrollTo(0, 0);
}

function cancelFileForm() {
  progress.unlock();
  $('#file_form_page').hide();
  $('#index_page_row').show();
  passhub.indexPageResize();
}

function humanReadableFileSize(size) {
  if (size < 1024) return `${size} B`;
  const i = Math.floor(Math.log(size) / Math.log(1024));
  // let num = (size / Math.pow(1024, i));
  let num = (size / (1024 ** i));
  const round = Math.round(num);
  num = round < 10 ? num.toFixed(2) : round < 100 ? num.toFixed(1) : round
  return `${num} ${'KMGTPEZY'[i - 1]}B`;
}

function submitFileForm() {
  $('#file_form_alert').hide();
  const curFiles = document.querySelector('#file_form_fileinput_id').files;
  if (curFiles.length === 0) {
    $('#file_form_alert').html('Please select file to upload').show();
    return;
  }
  const theFile = curFiles[0];


  if ((init.maxFileSize) && (theFile.size > init.maxFileSize)) {
    $('#file_form_alert').html(`File too large. Max allowed file size is ${humanReadableFileSize(init.maxFileSize)}`).show();
    return;
  }

  if (init.maxStorage && init.storageUsed) {
    let spareStorage = init.maxStorage - init.storageUsed;
    if (spareStorage < 0) {
      spareStorage = 0;
    }
    if (spareStorage < theFile.size) {
      $('#file_form_alert').html(`File too large. Spare storage size is ${humanReadableFileSize(spareStorage)}`).show();
      return;
    }
  }

  // $('.file_form_save').hide();
  $('.file_form_close').hide();
  const reader = new FileReader();
  reader.onerror = (err) => {
    console.log(err, err.loaded, err.loaded === 0, file);
    alert('fail');
  };

  reader.onload = () => {
    const data = new FormData();
    const fileArray = new Uint8Array(reader.result);
    const fileAesKey = forge.random.getBytesSync(32);
    const fileCipher = forge.cipher.createCipher('AES-GCM', fileAesKey);
    const fileIV = forge.random.getBytesSync(16);
    fileCipher.start({ iv: fileIV });
    fileCipher.update(forge.util.createBuffer(fileArray));
    fileCipher.finish();
    const keyCipher = forge.cipher.createCipher('AES-ECB', init.safe.bstringKey);
    const keyIV = forge.random.getBytesSync(16);
    keyCipher.start({ iv: keyIV });
    keyCipher.update(forge.util.createBuffer(fileAesKey));
    keyCipher.finish();
    progress.unlock();
    progress.lock(0, 'Uploading. ');

    data.append('vault', init.safe.id);
    data.append('folder', init.folder);
    data.append('verifier', init.csrf);
    data.append('meta', passhubCrypto.encryptItem([theFile.name, '', '', '', ''], init.safe.bstringKey));
    data.append('file', JSON.stringify({
      version: 3,
      key: btoa(keyCipher.output.data),
      iv: btoa(fileIV),
      data: btoa(fileCipher.output.data),
      tag: btoa(fileCipher.mode.tag.data),
    }));

    $.ajax({
      type: 'POST',
      enctype: 'multipart/form-data',
      url: 'create_file.php',
      data,
      processData: false,
      contentType: false,
      cache: false,
      timeout: 600000,
      success: (result) => {
        progress.unlock();
        if (result.status === 'Ok') {
          $('#file_form_page').hide();
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
        showAlert(result.status);
        $('.file_form_close').show();
      },
      error: (e) => {
        progress.unlock();
        $('.file_form_close').show();
        if (e.status == 413) {
          showAlert('File too large');
        } else {
          showAlert(e.responseText);
        }
      },
    });
  };
  progress.lock(0, 'Encrypting.');
  reader.readAsArrayBuffer(theFile);
}

function showFileForm(initParams) {
  init = { ...initParams };

  document.querySelector('#file_form_fileinput_id').value = '';
  $('#file_form_fileinput_id').next('.custom-file-label').html('');

  $('#file_form_alert').text('').hide();
  $('.file_form_save').show();
  $('.file_form_close').show();

  $('#index_page_row').hide();
  $('#file_form_page').show();
  $.ajax({
    url: 'create_file.php',
    type: 'POST',
    data: {
      check: true,
      vault: init.safe.id,
      verifier: init.csrf,
    },
    error: (hdr, status, err) => {
      // passhub.modalAjaxError($('#add_from_invite_name_alert'), hdr, status, err);
    },
    success: (result) => {
      if (result.status === 'Ok') {
        let text = '';
        if (result.storageUsed) {
          text += `Storage used: ${humanReadableFileSize(result.storageUsed)}`;
          init.storageUsed = result.storageUsed;
        }
        if ('maxStorage' in result) {
          text += ` out of  ${humanReadableFileSize(result.maxStorage)}`;
          init.maxStorage = result.maxStorage;
        }
        text += '<br>';
        if (result.maxFileSize) {
          text += `Max file size: ${humanReadableFileSize(result.maxFileSize)}`;
          init.maxFileSize = result.maxFileSize;
        }
        document.querySelector('#file_form_info').innerHTML = text;
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
      $('.file_form_close').show();
    },
  });
}

function initFileForm() {
  $('.file_form_submit').click(submitFileForm);
  $('.file_form_close').click(cancelFileForm);
  $('#file_form_fileinput_id').on('change', () => {
    $('#file_form_alert').text('').hide();
  });
}

export {
  initFileForm,
  showFileForm,
};
