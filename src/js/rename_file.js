import $ from 'jquery';
import { modalAjaxError } from './utils';
import state from './state';
import passhubCrypto from './crypto';
import passhub from './passhub';

$('#id_FileRename_button').click(function () {
// $('#id_FileRename_button').click(() => {
  const name = $('#FileName_rename').val().trim();
  if (name == '') {
    $('#rename_file_alert').text(' * File name cannot be empty').show();
    return;
  // TODO, see rename folder
  }
  if (name === $('#id_FileRename_button').attr('data-record_name')) {
    $('#renameFile').modal('hide');
    return;
  }
  passhubCrypto.decryptAesKey(state.currentSafe.key)
    .then((aesKey) => {
      const newName = passhubCrypto.encryptItem([name, '', '', '', ''], aesKey);
      $.ajax({
        url: 'file_ops.php',
        type: 'POST',
        data: {
          verifier: state.csrf,
          operation: 'rename',
          SafeID: state.currentSafe.id,
          itemId: $(this).attr('data-record_nb'),
          newName,
        },
        error: (hdr, status, err) => {
          modalAjaxError($('#rename_file_alert'), hdr, status, err);
        },
        success: (result) => {
          if (result.status === 'Ok') {
            $('#renameFile').modal('hide');
            passhub.refreshUserData();
            return;
          }
          if (result.status === 'login') {
            window.location.href = 'expired.php';
            return;
          }
          $('#rename_file_alert').html(result.status).show();
        },
      });
    });
});

$('#renameFile').on('shown.bs.modal', () => {
  const elm = document.getElementById('FileName_rename');
  elm.focus();
  const name = elm.value;
  const lastDot = name.lastIndexOf('.');

  if ((lastDot >= 1) && (lastDot < name.length - 1)) {
    elm.setSelectionRange(0, lastDot);
  }
});

function renameFile(fileItem) {
  $('#id_FileRename_button').attr('data-record_nb', fileItem.attr('data-record_nb'));
  $('#id_FileRename_button').attr('data-record_name', fileItem.attr('data-record_name'));
  $('#FileName_rename').val(fileItem.attr('data-record_name'));
  $('#rename_file_alert').text('').hide();
  $('#renameFile').modal('show');
}

export default { renameFile };
