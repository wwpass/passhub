import forge from 'node-forge';
import $ from 'jquery';
import progress from './progress';
import passhubCrypto from './crypto';
import './account';

let aesKey = '';

function get_aes_key() {
  return aesKey;
}

function decodeKeys(ePrivateKey, ticket) {
  passhubCrypto.getPrivateKey(ePrivateKey, ticket)
    .then(() => passhubCrypto.decryptAesKey(new_file.encrypted_aes_key_hex))
    .then((pKey) => {
      aesKey = pKey;
      return true;
    })
    .catch((err) => {
      alert(err);
    });
}

decodeKeys(new_file.pem_encrypted, new_file.ticket);

function showAlert(message) {
  $('#alert_message').text(message).show();
  window.scrollTo(0, 0);
}

function cancelForm() {
  progress.lock();
  window.location.href = 'index.php?show_table';
  return 0;
}

/*
function humanReadableFileSize(size) {
  let units = ' Bytes';
  if (size > 10 * 1024 * 1024) {
    size = Math.round(size / 1024 / 1024);
    units = ' MBytes';
  } else if (size > 10 * 1024) {
    size = Math.round(size / 1024);
    units = ' kBytes';
  }
  return size.toLocaleString() + units;
}
*/

function humanReadableFileSize(size) {
  if (size < 1024) return `${size} B`;
  const i = Math.floor(Math.log(size) / Math.log(1024));
  let num = (size / Math.pow(1024, i));
  const round = Math.round(num);
  num = round < 10 ? num.toFixed(2) : round < 100 ? num.toFixed(1) : round
  return `${num} ${'KMGTPEZY'[i-1]}B`;
}

function submitForm() {
  $('#alert_message').hide();
  const curFiles = document.querySelector('#fileinput_id').files;
  if (curFiles.length === 0) {
    $('#alert_message').html('Please select file to upload').show();
    return;
  }
  const theFile = curFiles[0];
  if (theFile.size > new_file.maxFileSize) {
    $('#alert_message').html(`File too large. Max file size is ${humanReadableFileSize(new_file.maxFileSize)}`).show();
    return;
  }
  
  const spareStorage = new_file.storage.maxStorage - new_file.storage.used; 
  if (theFile.size > spareStorage) {
    $('#alert_message').html(`File too large. Spare storage size is ${humanReadableFileSize(spareStorage)}`).show();
    return;
  }

  $('#save_button').hide();
  $('#cancel_button').hide();
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
    let result = fileCipher.finish();
    const keyCipher = forge.cipher.createCipher('AES-ECB', aesKey);
    const keyIV = forge.random.getBytesSync(16);
    keyCipher.start({ iv: keyIV });
    keyCipher.update(forge.util.createBuffer(fileAesKey));
    result = keyCipher.finish();
    progress.unlock();
    progress.lock(0, 'Uploading. ');

    data.append('vault', new_file.vault);
    data.append('folder', new_file.folder);
    data.append('verifier', new_file.verifier);
    data.append('meta', passhubCrypto.encryptItem([theFile.name, '', '', '', ''], get_aes_key()));
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
        if (result.status === 'Ok') {
          window.location.href = `index.php?show_table&vault=${new_file.vault}&folder=${new_file.folder}`;
          return;
        }
        progress.unlock();
        showAlert(result.status);
        $('#cancel_button').show();
      },
      error: (e) => {
        progress.unlock();
        $('#cancel_button').show();
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

$(document).ready(() => {
  $('#save_button').click(submitForm);
  $('#cancel_button').click(cancelForm); // iOS
  if (navigator.userAgent.match(/iPhone|iPod|iPad/i)) {
    $('input').css('font-size', '16px');
    $('textarea').css('font-size', '16px');
  }
  const { storage } = new_file;
  let text = `Storage used: ${humanReadableFileSize(storage.used)}`;
  if ('maxStorage' in storage) {
    text += ` out of  ${humanReadableFileSize(storage.maxStorage)}`;
  }
  document.querySelector('#storage_used').innerHTML = text;
});
