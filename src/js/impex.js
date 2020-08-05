import $ from 'jquery';
import forge from 'node-forge';
import { saveAs } from 'file-saver';
import passhub from './passhub';
import * as utils from './utils';
import {
  doRestoreXML,
  doRestoreCSV,
  createSafeFromFolder,
  importMerge,
} from './import';

$("input[name='export_format']").change(() => {
  const exportFormat = document.querySelector('input[name="export_format"]:checked').value;
  if (exportFormat === 'CSV') {
    $('#export_filename').text('passhub.csv');
  } else {
    $('#export_filename').text('passhub.xml');
  }
});

/*
function cvs_stuffing(x) {
    if( (x.indexOf(' ') != -1) || (x.indexOf('\t') != -1)
     || (x.indexOf(',') != -1) || (x.indexOf('\'') != -1)
     || (x.indexOf('\n') != -1) || (x.indexOf('\r') != -1)) {
        let dq = x.indexOf('\'');
        while(dq != -1) {
           x= x.substr(0, dq) + '\'' + x.substr(dq);
           dq = x.indexOf('\'', dq+2);
        }
        x = '\'' + x + '\'';
    }
    return x;
}
*/
function doBackupCSV3() {
  let csv = 'path,title,username,password,url,notes\r\n';

  function backupFolder(csv, path, safe, folder) {
    for (let i = 0; i < safe.items.length; i++) {
      if (safe.items[i].hasOwnProperty('file')) {
        continue;
      }
      if (safe.items[i].hasOwnProperty('folder') && (safe.items[i].folder == folder._id)) {
        csv += $.csv.fromArrays([[path + '/' + folder.cleartext[0],
          safe.items[i].cleartext[0],
          safe.items[i].cleartext[1],
          safe.items[i].cleartext[2],
          safe.items[i].cleartext[3],
          safe.items[i].cleartext[4]]]);
      }
    }
    for (let f = 0; f < safe.folders.length; f++) {
      if (safe.folders[f].parent == folder._id) {
        csv = backupFolder(csv, path + '/' + folder.cleartext[0], safe, safe.folders[f]);
      }
    }
    return csv;
  }

  function exportSafe(safe) {
    for (let i = 0; i < safe.items.length; i++) {
      if (safe.items[i].hasOwnProperty('file')) {
        continue;
      }
      if (!safe.items[i].hasOwnProperty('folder') || (safe.items[i].folder == 0)) {
        csv += $.csv.fromArrays([[safe.name,
          safe.items[i].cleartext[0],
          safe.items[i].cleartext[1],
          safe.items[i].cleartext[2],
          safe.items[i].cleartext[3],
          safe.items[i].cleartext[4]]]);
      }
    }
    for (let f = 0; f < safe.folders.length; f++) {
      const folder = safe.folders[f];
      if (folder.parent == 0) {
        csv = backupFolder(csv, safe.name, safe, folder);
      }
    }
  }

  if (passhub.exportSafe) {
    exportSafe(passhub.exportSafe);
  } else {
    for (let s = 0; s < passhub.safes.length; s++) {
      exportSafe(passhub.safes[s]);
    }
  }
  const blob = new Blob([csv], { type: 'text/csv;charset=utf-8' });
  saveAs(blob, 'passhub.csv'); // FileExport
}

function doBackupXML2() {
  let xml='';

  function dump_item(item, indent) {
    if (item.hasOwnProperty('file')) {
      return;
    }

    xml += indent + '<Entry>\r\n';
    const id1 = indent + '    ';
    const id2 = id1 + '    ';

    if (item.hasOwnProperty('lastModified')) {
      xml += id1 + '<Times>\r\n';
      xml += id2 + '<LastModificationTime>' + utils.escapeHtml(item.lastModified) + '</LastModificationTime>\r\n';
      xml += id1 + '</Times>\r\n';
    }

    if (item.cleartext[4] != '') {
      xml += id1 + '<String>\r\n';
      xml += id2 + '<Key>Notes</Key>\r\n';
      xml += id2 + '<Value>' + utils.escapeHtml(item.cleartext[4]) + '</Value>\r\n';
      xml += id1 + '</String>\r\n';
    }
    if (item.cleartext[2] != '') {
      xml += id1 + '<String>\r\n';
      xml += id2 + '<Key>Password</Key>\r\n';
      xml += id2 + '<Value ProtectInMemory="True">' + utils.escapeHtml(item.cleartext[2]) + '</Value>\r\n';
      xml += id1 + '</String>\r\n';
    }
    if (item.cleartext[0] != '') {
      xml += id1 + '<String>\r\n';
      xml += id2 + '<Key>Title</Key>\r\n';
      xml += id2 + '<Value>' + utils.escapeHtml(item.cleartext[0]) + '</Value>\r\n';
      xml += id1 + '</String>\r\n';
    }
    if (item.cleartext[3] != '') {
      xml += id1 + '<String>\r\n';
      xml += id2 + '<Key>URL</Key>\r\n';
      xml += id2 + '<Value>' + utils.escapeHtml(item.cleartext[3]) + '</Value>\r\n';
      xml += id1 + '</String>\r\n';
    }
    if (item.cleartext[1] != '') {
      xml += id1 + '<String>\r\n';
      xml += id2 + '<Key>UserName</Key>\r\n';
      xml += id2 + '<Value>' + utils.escapeHtml(item.cleartext[1]) + '</Value>\r\n';
      xml += id1 + '</String>\r\n';
    }
    if (item.hasOwnProperty('note')) {
      xml += id1 + '<String>\r\n';
      xml += id2 + '<Key>Note</Key>\r\n';
      xml += id2 + '<Value>' + 1 + '</Value>\r\n';
      xml += id1 + '</String>\r\n';
    }
    if (item.cleartext.length == 6) {
      xml += id1 + '<String>\r\n';
      xml += id2 + '<Key>TOTP</Key>\r\n';
      xml += id2 + '<Value>' + utils.escapeHtml(item.cleartext[5]) + '</Value>\r\n';
      xml += id1 + '</String>\r\n';
    }
    xml += indent + '</Entry>\r\n';
  }

  function export_folder(safe, folder_id, indent) {
    for (let i = 0; i < safe.items.length; i++) {
      if (safe.items[i].hasOwnProperty('folder')) {
        if (safe.items[i].folder == folder_id) {
          dump_item(safe.items[i], indent);
        }
      } else if (folder_id == 0) {
        dump_item(safe.items[i], indent);
      }
    }
    for (let f = 0; f < safe.folders.length; f++) {
      if (safe.folders[f].hasOwnProperty('parent')) {
        if (safe.folders[f].parent == folder_id) {
          xml += indent + '<Group>\r\n';
          xml += indent + '    <Name>' + utils.escapeHtml(safe.folders[f].cleartext[0]) + '</Name>\r\n';
          export_folder(safe, safe.folders[f]._id, indent + '    ');
          xml += indent + '</Group>\r\n';
        }
      }
    }
  }

  function exportSafe(safe) {
    xml += '            <Group>\r\n';
    xml += '                <Name>' + utils.escapeHtml(safe.name) + '</Name>\r\n';
    export_folder(safe, 0, '                ');
    xml += '            </Group>\r\n';
  }

  xml = '<?xml version="1.0" encoding="utf-8" standalone="yes"?>\r\n<KeePassFile>\r\n    <Root>\r\n';
  xml += '        <Group>\r\n';
  xml += '            <Name>Passhub</Name>\r\n';

  if (!passhub.exportSafe) {
    for (let s = 0; s < passhub.safes.length; s++) {
      exportSafe(passhub.safes[s]);
    }
  } else {
    exportSafe(passhub.exportSafe);
  }
  xml += '        </Group>\r\n';  
  xml += '     </Root>\r\n</KeePassFile>\r\n';

  const blob = new Blob([xml], { type: 'text/xml' });
  saveAs(blob, 'passhub.xml'); // FileExport
}

$('#backupModal').on('show.bs.modal', () => {
  $('#backup_alert').text('').hide();
  $('#backup_button').show();
  if (!passhub.exportSafe) {
    $('#ExportLabel').text('Export/Backup all');
  } else {
    $('#ExportLabel').text(`Export safe ${passhub.exportSafe.name}`);
  }
});

$('#backup_button').click(() => {
  if (passhub.exportSafe) {
    const exportFormat = document.querySelector('input[name="export_format"]:checked').value;
    if (exportFormat === 'CSV') {
      doBackupCSV3(passhub.exportSafe);
      // do_backup_csv2(result.data);
    } else {
      doBackupXML2(passhub.exportSafe);
      // do_backup_xml(result.data);
    }
    $('#backupModal').modal('hide');
    return;
  }

  $.ajax({
    url: 'impex.php',
    type: 'POST',
    data: {
      export: 1,
      verifier: passhub.csrf,
    },
    error: (hdr, status, err) => {
      $('#backup_button').hide();
      passhub.modalAjaxError($('#backup_alert'), hdr, status, err);
    },
    success: (result) => {
      if (result.status === 'Ok') {
        const exportFormat = document.querySelector('input[name="export_format"]:checked').value;
        if (exportFormat === 'CSV') {
          doBackupCSV3(result.data);
          // do_backup_csv2(result.data);
        } else {
          doBackupXML2(result.data);
          // do_backup_xml(result.data);
        }
        $('#backupModal').modal('hide');
        return;
      }
      if (result.status === 'login') {
        window.location.href = 'expired.php';
        return;
      }
      $('#backup_button').hide();
      $('#backup_alert').html(result.status).show();
    },
  });
});

// ********************************************************************
// Import/restore
// ********************************************************************

function uploadImportedData(safeArray) {
  if (safeArray.length === 0) {
    $('#import_button').hide();
    $('#restoreModal .modal-body').hide();
    $('#restore_alert').text('no records to update').show();
    return;
  }
  $.ajax({
    url: 'impex.php',
    type: 'POST',
    data: {
      import: safeArray,
      verifier: passhub.csrf,
    },
    error: (hdr, status, err) => {
      $('#import_button').hide();
      $('#restoreModal .modal-body').hide();
      passhub.modalAjaxError($('#restore_alert'), hdr, status, err);
    },
    success: (result) => {
      if (result.status === 'Ok') {
        $('#restoreModal').modal('hide');
        passhub.refreshUserData();
        return;
      }
      $('#import_button').hide();
      $('#restoreModal .modal-body').hide();
      $('#restore_alert').html(result.status).show();
    },
  });
}

$('#restoreModal').on('show.bs.modal', () => {
  $('#restore_alert').text('').hide();
  $('#import_button').show();
  $('#restoreModal .modal-body').show();

  document.querySelector('#import_form_fileinput_id').value = '';
  $('#import_form_fileinput_id').next('.custom-file-label').html('Choose File');
});

$('#import_button').click(() => {
  const input = document.querySelector('#import_form_fileinput_id');
  const curFiles = input.files;
  if (curFiles.length === 0) {
    $('#restore_alert').html('Please select backup file').show();
    return;
  }
  const theFile = curFiles[0];
  theFile.extension = theFile.name.split('.').pop().toLowerCase();
  if ((theFile.extension !== 'csv') && (theFile.extension !== 'xml')) {
    $('#restore_alert').html(`Unsupported file type <b>${utils.escapeHtml(theFile.extension.toUpperCase())}</b>`).show();
    return;
  }
  if (theFile.size > 3000000) {
    $('#restore_alert').html('File too long').show();
    return;
  }
  // read the file first
  const reader = new FileReader();
  reader.onerror = (err) => {
    console.log(err, err.loaded, err.loaded === 0, theFile.name);
    $('#restore_alert').html('Error loading file').show();
  };

  reader.onload = () => {
    const text = reader.result;
    let imported = {};
    try {
      if (theFile.extension === 'xml') {
        imported = doRestoreXML(text);
      } else {
        imported.name = theFile.name;
        imported.entries = [];
        imported.folders = doRestoreCSV(text);
      }
    } catch (err) {
      $('#restore_alert').text(err).show();
      return;
    }
    const { publicKeyPem } = passhub;
    const publicKey = forge.pki.publicKeyFromPem(publicKeyPem);

    const restoreMode = document.querySelector('input[name="restore_mode"]:checked').value;
    if (restoreMode === 'Restore') {
      $.ajax({
        url: 'impex.php',
        type: 'POST',
        data: {
          export: 1,
          verifier: passhub.csrf,
        },
        error: (hdr, status, err) => {
          $('#import_button').hide();
          $('#restoreModal .modal-body').hide();
          passhub.modalAjaxError($('#restore_alert'), hdr, status, err);
        },
        success: (result) => {
          if (result.status === 'login') {
            window.location.href = 'expired.php';
            return false;
          }
          if (result.status === 'Ok') {
            return importMerge(imported.folders, result.data, publicKey)
              .then(safeArray => uploadImportedData(safeArray));
          }
          $('#import_button').hide();
          $('#restoreModal .modal-body').hide();
          $('#restore_alert').html(result.status).show();
          return false;
        },
      });
      return;
    }

    const importedSafe = createSafeFromFolder(imported, publicKey);
    uploadImportedData([importedSafe]);
  };
  reader.readAsText(theFile);
});
