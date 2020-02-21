import $ from 'jquery';
import 'jquery-contextmenu';
import { saveAs } from 'file-saver';
import * as utils from './utils';
import safes from './safes';
import passhub from './passhub';
import rename_file from './rename_file';
import delete_item from './delete_item';
import progress from './progress';
import passhubCrypto from './crypto';

const prepareUrl = (url) => {
  if (url.startsWith('www')) {
    return `<a target='_blank' href='http://${url}' rel="noreferrer noopener">${url}</a>`;
  }
  if (url.startsWith('https://') || url.startsWith('http://')) {
    return `<a target='_blank' href='${url}' rel="noreferrer noopener">${url}</a>`;
  }
  return url;
};

function getMimeByExt(filename) {
  const mimeType = {
    'doc': 'application/msword',
    'docx': 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'gzip': 'application/gzip',
    'jpg': 'image/jpeg',
    'jpeg': 'image/jpeg',
    'gif': 'image/gif',
    'pdf': 'application/pdf',
    'png': 'image/png',
    'ppt': 'application/vnd.ms-powerpoint',
    'pptx': 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    'tif': 'image/tiff',
    'tiff': 'image/tiff',
    'txt': 'text/plain',
    'xls': 'application/vnd.ms-excel',
    'xlsx': 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'zip': 'application/zip',
  };
  if (navigator.userAgent.match(/iPhone|iPod|iPad/i)) {
    const i = filename.lastIndexOf('.');
    if (i !== -1) {
      const ext = filename.substr(i + 1).toLowerCase();
      if (ext in mimeType) {
        return mimeType[ext];
      }
    }
  }
  return 'application/octet-binary';
}

function downloadFileItem(fileItem) {
  progress.lock(0);
  $.ajax({
    url: 'file_ops.php',
    type: 'POST',
    data: {
      operation: 'download',
      SafeID: passhub.currentSafe.id,
      verifier: passhub.csrf,
      itemId: fileItem.attr('data-record_nb')
    },
    error: (hdr, status, err) => {
      progress.unlock();
      alert(`${status} ${err}`);
//      passhub.modalAjaxError($('#rename_vault_alert'), hdr, status, err);
    },
    success: (result) => {
      if (result.status === 'Ok') {
        passhubCrypto.decryptAesKey(passhub.currentSafe.key)
          .then((aesKey) => {
            const { filename, buf } = passhubCrypto.decryptFile(result, aesKey);
            const mime = getMimeByExt(filename);
            const blob = new Blob([buf], { type: mime });
            saveAs(blob, filename); // FileExport
            progress.unlock();
          });
        return;
      }
      if (result.status === 'login') {
        progress.unlock();
        window.location.href = 'expired.php';
        return;
      }
      progress.unlock();
      window.location.href = 'error_page.php?js=other';
    },
  });
  return 0;
}

const itemRow = (item, searchMode) => {
  if (item.cleartext.length < 5) {
    return `<td>Error ${item._id}</td>`;
  }
  const name = utils.escapeHtml(item.cleartext[0]);
  const url = prepareUrl(utils.escapeHtml(item.cleartext[3]));
  let icon = "<svg width='24' height='24' class='item_icon'><use xlink:href='img/SVG2/sprite.svg#i-key'></use></svg>";
  let row = "<td colspan = '2' class='col-xl-6 col-lg-7 col-md-12' style='border-right: none; padding-left:15px'>";
  if ('file' in item) {
    icon = "<svg width='24' height='24' class='item_icon'><use xlink:href='img/SVG2/sprite.svg#i-file'></use></svg>";
  } else if ('note' in item) {
    icon = "<svg width='24' height='24' class='item_icon'><use xlink:href='img/SVG2/sprite.svg#i-note'></use></svg>";
  } else {
    row = "<td class='col-xl-5 col-lg-6 col-md-12' style='border-right: 1px solid #b5d0f0; padding-left:15px'>";
  }
  if (searchMode === undefined) {
    row += `<div class='d-md-none item-click' data-record_nb ='${item._id}' style='cursor: pointer; padding-top:5px;padding-bottom:5px;'>`;
  } else {
    row += `<div class='d-md-none sync_search_safe' data-record_nb ='${item._id}'style='cursor: pointer; padding-top:5px;padding-bottom:5px;'>`;
  }
  row += `${icon}${name}</div>`;
  if ('file' in item) {
    let sizeString = '';
    if (item.file.hasOwnProperty('size')) {
      sizeString = passhub.humanReadableFileSize(item.file.size);
    }
    if (searchMode === undefined) {
      row += "<div class='d-none d-md-block file_record_title' style='cursor: pointer;' data-record_nb =";
    } else {
      row += "<div class='d-none d-md-block sync_search_safe file_record_title' style='cursor: pointer;' data-record_nb =";
    }
    row += "'" + item._id + "'";
    row += " data-record_name ='" + name + "'>" + icon + name + '</div>'
      + '</td>'
      // + "<td class = 'tdmain hidden-xs' style='cursor: default; border-right: none'>" + size + '</td>';
      + "<td class = 'tdmain col-xl-3 col-lg-5  d-none d-lg-table-cell' align='right' style='cursor: default; border-right: none'>" + sizeString + "</td>";
      // + "<td class = 'tdmain hidden-xs hidden-sm '>" + date + '</td>';
  } else {
    if (searchMode === undefined) {
      row += "<div class='d-none d-md-block record_title' style='cursor: pointer;' data-record_nb =";
    } else {
      row += "<div class='d-none d-md-block sync_search_safe record_title' style='cursor: pointer;' data-record_nb =";
    }
    row += item._id;
    row += ` data-record_name = "${name}">${icon}${name}</div>`;
    row += '</td>';

    if ('note' in item) {
      row += "<td class='col-xl-3 col-lg-5  d-none d-lg-table-cell'></td>";
    } else {
      // creds
      row += "<td class='col-lg-1  d-none d-lg-table-cell tdmain lp_show'";
      row += "style='border-right: 1px solid #b5d0f0;cursor: pointer; text-align: center;'";
      row += `data-record_nb =${item._id}>`

         + "<img src='img/outline-https-24px.svg' alt= 'login/password' height='24'>"
//        + "<span class='glyphicon glyphicon-asterisk' aria-hidden='true' style='cursor: pointer; '></span>"
        + '</td>'
        + "<td class='tdmain col-xl-3 col-lg-5 d-none d-lg-table-cell' style='cursor: default;'>" + url + '</td>';
    }
    // notes
    // row += "<td class='tdmain hidden-xs hidden-sm col-md-3' style='cursor: default;'>" + escapeHtml(item.cleartext[4]) + '</td>';
  }
  let modified = '';
  if (item.lastModified) {
    modified = new Date(item.lastModified).toLocaleString();
  }
  row += "<td class='tdmain col-xl-3 d-none d-xl-table-cell' style='cursor: default; border-left: 1px solid #b5d0f0;'>" + modified + "</td>";

  return row;
};

/*
function folderOnClick() {
  const id = $(this).attr('data-folder-id');
  safes.setActiveFolder(id);
}
*/

const show = (folder) => {
  if (!passhub.currentSafe.key) {
    $('.not_confirmed_safe').show();
    $('.confirmed_safe').hide();
    $('.confirmed_safe_buttons').hide();
    return;
  }
  $('.not_confirmed_safe').hide();
  $('.confirmed_safe').show();
  $('.confirmed_safe_buttons').show();
  $('#item_list_tbody').empty();
  for (let i = 0; i < passhub.currentSafe.folders.length; i++) {
    if ('parent' in passhub.currentSafe.folders[i]) {
      if (passhub.currentSafe.folders[i].parent != folder) {
        continue;
      }
    } else if (folder != 0) {
      continue;
    }
    const row = '<tr>'
      + `<td class='col-xs-12 d-md-none list-item-title folder-click' data-folder-id='${passhub.currentSafe.folders[i]._id}' style='padding-left:15px'>`
      + "<div style='padding-top:5px;padding-bottom:5px; cursor:pointer'>"
      + "<svg width='24' height='24' class='item_icon'><use xlink:href='img/SVG2/sprite.svg#i-folder'></use></svg>"
      + utils.escapeHtml(passhub.currentSafe.folders[i].cleartext[0])
      + "<svg width='24' height='24' style='stroke:#2277e6; opacity:0.5; float:right; vertical-align:middle; margin-right:10px'><use xlink:href='img/SVG2/sprite.svg#ar-forward'></use></svg>"
      + '</div></td></tr>';
    $('#item_list_tbody').append(row);
  }
  for (let i = 0; i < passhub.currentSafe.items.length; i++) {
    // use local 'let item', same in
    if ('folder' in passhub.currentSafe.items[i]) {
      if (passhub.currentSafe.items[i].folder != folder) {
        continue;
      }
    } else if (folder != 0) {
      continue;
    }
    $('#item_list_tbody').append(`<tr class="d-flex"> ${itemRow(passhub.currentSafe.items[i])} </tr>`);
  }
};

$('body').on('click', '.lp_show', function () {
  if(passhub.searchMode) {
    syncSearchSafe($(this).attr('data-record_nb'));
  }
  showItemModal($(this).attr('data-record_nb'), true);
});

$('body').on('click', '.item-click', function () {
  showItem($(this).attr('data-record_nb'));
});


let editItemId;

$('.item_view_edit_btn').click(() => {
  window.location.href = `edit.php?vault=${passhub.currentSafe.id}&id=${editItemId}`;
});

function showItemModal(id, credsOnly = false) {
  for (let i = 0; i < passhub.currentSafe.items.length; i++) {
    if (passhub.currentSafe.items[i]._id == id) {
      editItemId = id;
      const item = passhub.currentSafe.items[i];
      if (item.lastModified) {
        const modified = new Date(item.lastModified).toLocaleString();
        $('.modified').text(`Modified: ${modified}`);
      }

      const data = item.cleartext;
      $('#showCredsLabel').text(data[0]);
      $('#creds0ID').val(data[1]);
      $('#creds1ID').val(data[2]);
      const url = prepareUrl(utils.escapeHtml(data[3]));
      $('#item_view_url').html(url);
      $('#item_view_notes').text(data[4]);
      $('.item_view').show();
      if (item.hasOwnProperty('note') && (item.note == 1)) {
        $('.note_hidden').hide();
      } else {
        $('.note_hidden').show();
        if (credsOnly) {
          $('.item_view').hide();
        }
      }
      $('#showCreds').modal('show');
      passhub.itemViewNoteResize();
      $('#item_view_note').resize(passhub.itemViewNoteResize);
      break;
    }
  }
}

const showItem = (id) => {
  let item = null;
  if (passhub.searchMode) {
    item = passhub.getItemById(id);
  } else {
    for (let i = 0; i < passhub.currentSafe.items.length; i++) {
      if (passhub.currentSafe.items[i]._id == id) {
        item = passhub.currentSafe.items[i];
        break;
      }
    }
  }
  if (item !== null) {
    const data = item.cleartext;
    $('#item_pane_menu').attr('data-record_nb', id);
    $('#item_pane_menu').attr('data-record_name', data[0]);
    $('#item_pane_title').text(data[0]);
    if (item.lastModified) {
      const modified = new Date(item.lastModified).toLocaleString();
      $('.modified').text(`Modified: ${modified}`);
    }
    if ('file' in item) {
      $('.file_item').show();
      $('.regular_item').hide();
      $('#item_pane_file').text(data[0]);
      if (item.file.hasOwnProperty('size')) {
        let { size } = item.file;
        let units = ' Bytes';
        if (size > 10 * 1024 * 1024) {
          size = Math.round(size / 1024 / 1024);
          units = ' MBytes';
        } else if (size > 10 * 1024) {
          size = Math.round(size / 1024);
          units = ' kBytes';
        }
        $('#item_pane_file_size').text(size.toLocaleString() + units);
      } else {
        $('#item_pane_file_size').text('-');
      }
      // $('#item_pane_delete_button').click(() => { delete_item.deleteItem($('#item_pane_menu')); });
      $('#item_pane_download_button').click(() => { downloadFileItem($('#item_pane_menu')); });
      $('#item_pane_menu').addClass('file_record_title');
      $('#item_pane_menu').removeClass('record_title');
    } else {
      $('.file_item').hide();
      $('.regular_item').show();
      $('#item_pane_menu').addClass('record_title');
      $('#item_pane_menu').removeClass('file_record_title');
      if ('note' in item) {
        $('.note_hidden').hide();
      } else {
        $('.note_hidden').show();
        if (data[1].length > 0) {
          $('.item_pane_username_div').show();
          $('.item_pane_username_dummy').hide();
          $('#item_pane_username').val(data[1]);
          $('#item_pane_menu').attr('data-record_name', data[0]);
        } else {
          $('.item_pane_username_dummy').show();
          $('.item_pane_username_div').hide();
        }
        if (data[2].length > 0) {
          $('.item_pane_password_div').show();
          $('.item_pane_password_dummy').hide();
          $('#item_pane_password').val(data[2]);
        } else {
          $('.item_pane_password_dummy').show();
          $('.item_pane_password_div').hide();
        }
        const url = prepareUrl(utils.escapeHtml(data[3]));
        $('#item_pane_url').html(url);
      }
      $('#item_pane_notes').text(data[4]);
    }
  }

  $('.table_pane').addClass('d-none');
  $('.vaults_pane').addClass('d-none');
  $('.item_pane').removeClass('d-none');

  //  init_item_pane();
/*  
  if (utils.isXs()) {
    $('body').css('background-color', 'white');
  }
*/  
};

const addItemButtonMenu = {
  selector: '.add_item_button',
  trigger: 'left',
  delay: 100,
  autoHide: true,
  zIndex: 10,
  className: 'addItemButtonMenu contextmenu-customheight',
  items: {
    entry: {
      name: 'Login Entry',
      callback: () => {
        window.location.href = `new.php?vault=${passhub.currentSafe.id}&folder=${passhub.activeFolder}`;
      }
    },
    note: {
      name: 'Note',
      callback: () => {
        window.location.href = `new.php?vault=${passhub.currentSafe.id}&folder=${passhub.activeFolder}&note=1`;
      }
    },
    file: {
      name: 'File',
      callback: () => {
        window.location.href = `newfile.php?vault=${passhub.currentSafe.id}&folder=${passhub.activeFolder}`;
      }
    },
    folder: {
      name: 'Folder',
      callback: () => {
        $('#newFolderModal').modal('show');
      },
    },
  },
};

$.contextMenu(addItemButtonMenu);

const fileItemMenu = {
  selector: '.file_record_title',
  trigger: 'left',
  delay: 100,
  autoHide: true,
  zIndex: 10,
  events: {
    show: function () {
      let tr_color = $(this).parent().parent().css('background-color');
      $(this).parent().parent().css('background-color', tr_color);
      return true;
    },
    hide: function () {
      if ($(this).parent().parent().prop('tagName') === 'TR') {
        $(this).parent().parent().css('background-color', '');
      }
      return true;
    },
  },
  items: {
    download: {
      name: 'Download',
      callback: function () {
        downloadFileItem($(this));
      },
    },
    rename: {
      name: 'Rename',
      callback: function () {
        rename_file.renameFile($(this));
      },
    },
    delete: {
      name: 'Delete',
      icon: 'delete',
      callback: function () {
        delete_item.deleteItem($(this));
      },
    }
  }
};

$.contextMenu(fileItemMenu);

const itemMenu = {
  selector: '.record_title',
  trigger: 'left',
  // delay: 100,
  autoHide: true,
  zIndex: 10,
  events: {
    show: function () {
      const trColor = $(this).parent().parent().css('background-color');
      $(this).parent().parent().css('background-color', trColor);
      return true;
    },
    hide: function () {
      if ($(this).parent().parent().prop('tagName') === 'TR') {
        $(this).parent().parent().css('background-color', '');
      }
      return true;
    },
  },
  items: {
    view: {
      name: 'View',
      visible: () => {
        if ($('#item_pane_menu').is(':visible')) {
          return false;
        }
        return true;
      },
      callback: function () {
        showItemModal($(this).attr('data-record_nb'));
      },
    },
    edit: {
      name: 'Edit',
      callback: function () {
        window.location.href = 'edit.php?vault=' + passhub.currentSafe.id + '&id=' + $(this).attr('data-record_nb');
      },
    },
    cut: {
      name: 'Cut',
      callback: function () {
        $('.toast_header_text').text('Move item to another safe');
        $('.toast').toast('show');
        const now = new Date();
        sessionStorage.setItem('clip', JSON.stringify({
          operation: 'move',
          timestamp: now.getTime(),
          safeID: passhub.currentSafe.id,
          item: $(this).attr('data-record_nb')
        }));
      },
    },
    copy: {
      name: 'Copy',
      callback: function () {
        $('.toast_header_text').text('Copy item to another safe');
        $('.toast').toast('show');
        const now = new Date();
        sessionStorage.setItem('clip', JSON.stringify({
          operation: 'copy',
          timestamp: now.getTime(),
          safeID: passhub.currentSafe.id,
          item: $(this).attr('data-record_nb')
        }));
      },
    },
    delete: {
      name: 'Delete',
      icon: 'delete',
      callback: function () {
        delete_item.deleteItem($(this));
      },
    },
  },
};
$.contextMenu(itemMenu);

function syncSearchSafe(itemID) {
  const item = passhub.getItemById(itemID);
  if ((passhub.currentSafe.id == item.SafeID) && (passhub.activeFolder == item.folder)) {
    return;
  }
  passhub.setActiveSafe(item.SafeID);
  safes.setActiveFolder(item.folder, true);
  passhub.makeCurrentVaultVisible();
}

function showSearchResult(found) {
  $('.not_confirmed_safe').hide();
  $('.confirmed_safe').show();
  $('.confirmed_safe_buttons').hide();

  $('#item_list_tbody').empty();
  safes.setItemPaneHeader();

  for (let i = 0; i < found.length; i++) {
    $('#item_list_tbody').append('<tr class="d-flex">' + itemRow(found[i], true) + '</tr>');
  }
  if (found.length > 0) {
    syncSearchSafe(found[0]._id);
  }
  passhub.showTable();
}

$('body').on('click', '.sync_search_safe', function () {
  syncSearchSafe($(this).attr('data-record_nb'));
  if ($(this).hasClass('d-md-none')) {
    showItemModal($(this).attr('data-record_nb'));
  }
});

export { show, showSearchResult };
