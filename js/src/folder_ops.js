import $ from 'jquery';
import safes from './safes';
import passhub from './passhub';
import passhubCrypto from './crypto';

$('#newFolderBtn').click(() => {
  const folderName = $('#newFolderName').val().trim();
  if (folderName == '') {
    $('#new_folder_alert').text(' * Please fill in folder name').show();
  } else {
    passhubCrypto.decryptAesKey(passhub.currentSafe.key)
      .then((aesKey) => {
        const eFolderName = passhubCrypto.encryptFolderName(folderName, aesKey);
        $.ajax({
          url: 'folder_ops.php',
          type: 'POST',
          data: {
            operation: 'create',
            verifier: passhub.csrf,
            SafeID: passhub.currentSafe.id,
            folderID: passhub.activeFolder,
            name: eFolderName,
          },
          error: (hdr, status, err) => {
            passhub.modalAjaxError($('#new_folder_alert'), hdr, status, err);
          },
          success: (result) => {
            if (result.status === 'Ok') {
              // window.location.href = 'index.php';
              $('#newFolderModal').modal('hide');
              passhub.refreshUserData();
              return;
            }
            if (result.status === 'login') {
              window.location.href = 'expired.php';
              return;
            }
            $('#new_folder_alert').text(result.status).show();
          },
        });
      });
  }
});

$('#renameFolderBtn').click(() => {
  const folderName = $('#nextFolderName').val().trim();
  if (folderName == '') {
    $('#rename_folder_alert').text(' * Folder name cannot be empty').show();
  } else {
    passhubCrypto.decryptAesKey(passhub.currentSafe.key)
      .then((aesKey) => {
        const eFolderName = passhubCrypto.encryptFolderName(folderName, aesKey);
        $.ajax({
          url: 'folder_ops.php',
          type: 'POST',
          data: {
            operation: 'rename',
            verifier: passhub.csrf,
            SafeID: passhub.currentSafe.id,
            folderID: passhub.activeFolder,
            name: eFolderName,
          },
          error: (hdr, status, err) => {
            passhub.modalAjaxError($('#rename_folder_alert'), hdr, status, err);
          },
          success: (result) => {
            if (result.status === 'Ok') {
              // window.location.href = `index.php?vault=${passhub.currentSafe.id}&folder=${passhub.activeFolder}`;
              $('#renameFolderModal').modal('hide');
              passhub.refreshUserData();
              return;
            }
            if (result.status === 'login') {
              window.location.href = 'expired.php';
              return;
            }
            $('#rename_folder_alert').html(result.status).show();
          },
        });
      });
  }
});

$('#deleteFolderBtn').click(() => {
  $.ajax({
    url: 'folder_ops.php',
    type: 'POST',
    data: {
      operation: $('#not_empty_warning').is(':visible') ? 'delete_not_empty' : 'delete',
      verifier: passhub.csrf,
      SafeID: passhub.currentSafe.id,
      folderID: passhub.activeFolder,
    },
    error: (hdr, status, err) => {
      passhub.modalAjaxError($('#delete_folder_alert'), hdr, status, err);
    },
    success: (result) => {
      if (result.status === 'Ok') {
        passhub.refreshUserData();
        if (result.hasOwnProperty('items')) {
          $('#not_empty_warning').hide();
          $('#not_empty_stats').text(`Deleted folders: ${result.folders}  items: ${result.items}`).show();
          $('#deleteFolderBtn').hide();
          $('#deleteFolderCancelBtn').hide();
          $('#deleteFolderCloseBtn').show();
          return;
        }
        $('#deleteFolderModal').modal('hide');
        return;
      }
      if (result.status === 'Folder not empty') {
        $('#not_empty_warning').show();
        return;
      }
      if (result.status === 'login') {
        window.location.href = 'expired.php';
        return;
      }
      $('#delete_folder_alert').html(result.status).show();
    },
  });
});

$('#newFolderModal').on('shown.bs.modal', () => {
  $('#new_folder_alert').text('').hide();
  $('#newFolderName').val('');
  $('#newFolderName').focus();
});

$('#renameFolderModal').on('shown.bs.modal', () => {
  $('#rename_folder_alert').text('').hide();
  $('#nextFolderName').val('');
  for (let i = 0; i < passhub.currentSafe.folders.length; i++) {
    if (passhub.currentSafe.folders[i]._id == passhub.activeFolder) {
      $('#nextFolderName').val(passhub.currentSafe.folders[i].cleartext[0]);
      break;
    }
  }
  $('#nextFolderName').focus();
});

let parentFolder = 0;

$('#deleteFolderModal').on('hide.bs.modal', () => {
  if ($('#safe_and_folder_name').is(':visible')) {
    // window.location.href = `index.php?vault=${passhub.currentSafe.id}`;
    passhub.refreshUserData();
    if (parentFolder) {
      safes.setActiveFolder(parentFolder);
    } else {
      passhub.activeFolder = 0;
      passhub.showSafes();
    }
  }
});

$('#deleteFolderModal').on('show.bs.modal', () => {
  $('#delete_folder_alert').text('').hide();
  $('#folder_to_delete').text('');
  $('#not_empty_stats').hide();
  $('#not_empty_warning').hide();
  $('#deleteFolderBtn').show();
  $('#deleteFolderCancelBtn').show();
  $('#deleteFolderCloseBtn').hide();
  for (let i = 0; i < passhub.currentSafe.folders.length; i++) {
    if (passhub.currentSafe.folders[i]._id == passhub.activeFolder) {
      $('#folder_to_delete').text(passhub.currentSafe.folders[i].cleartext[0]);
      parentFolder = passhub.currentSafe.folders[i].parent;
    }
  }
});
