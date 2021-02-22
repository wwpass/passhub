import $ from 'jquery';
import 'jquery-contextmenu';
import { saveAs } from 'file-saver';
import * as base32 from 'hi-base32';

import state from './state';
import * as utils from './utils';
import safes from './safes';
import passhub from './passhub';
import rename_file from './rename_file';
import delete_item from './delete_item';
import progress from './progress';
import passhubCrypto from './crypto';
import { showFileForm } from './new_file';
import { showItemForm } from './item_form';
import * as extensionInterface from './extensionInterface';
import getTOTP from './totp';


function getItemById(id) {
  for (let s = 0; s < state.safes.length; s += 1) {
    for (let i = 0; i < state.safes[s].items.length; i += 1) {
      if (state.safes[s].items[i]._id === id) {
        return state.safes[s].items[i];
      }
    }
  }
  return null;
}

class Item {
  constructor(id) {
    this.item = getItemById(id);
    if (this.item === null) {
      throw 'No such itemID';
    }
  }
  path() {
    let result = [];
    let parent = this.item.folder;

    while(parent != 0) {
      const folder = passhub.getFolderById(parent); 
      result.unshift(folder.cleartext[0]);
      parent = folder.parent;
    }
    const safe = passhub.getSafeById(this.item.SafeID);
    result.unshift(safe.name);  
    return result.join(' / ');
  }
}

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
//  if (navigator.userAgent.match(/iPhone|iPod|iPad/i)) {
    const i = filename.lastIndexOf('.');
    if (i !== -1) {
      const ext = filename.substr(i + 1).toLowerCase();
      if (ext in mimeType) {
        return mimeType[ext];
      }
    }
  // }
  return 'application/octet-binary';
}

function downloadFileItem(fileItem, callBack) {
  progress.lock(0);
  $.ajax({
    url: 'file_ops.php',
    type: 'POST',
    data: {
      operation: 'download',
      SafeID: state.currentSafe.id,
      verifier: state.csrf,
      itemId: fileItem.attr('data-record_nb')
    },
    error: (hdr, status, err) => {
      progress.unlock();
      alert(`72 ${status} ${err}`);
//      passhub.modalAjaxError($('#rename_vault_alert'), hdr, status, err);
    },
    success: (result) => {
      if (result.status === 'Ok') {
        passhubCrypto.decryptAesKey(state.currentSafe.key)
          .then((aesKey) => {
            const { filename, buf } = passhubCrypto.decryptFile(result, aesKey);
            const mime = getMimeByExt(filename);
            const blob = new Blob([buf], { type: mime });
            callBack(blob, filename);
            progress.unlock();
          });
        return;
      }
      if (result.status === 'File not found') {
        progress.unlock();
        utils.bsAlert('File not found. Could be erased by another user');
        passhub.refreshUserData();
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

function saveFileItem(fileItem) {
  downloadFileItem(fileItem, saveAs);
}

function set_size() {
  //const h = $('#image_view_page').height() - $('#image_view_page .card-header').height();

  const h = $('#image_view_page').height() 
   + $('#image_view_page').offset().top - $('#image_view_page .card-body').offset().top;

 $('#image_view_page .card-body').outerHeight(h);
}

$('#image_view_page .card').on('resize', set_size);

function viewFileItem(fileItem) {
  downloadFileItem(fileItem,function(blob, filename)  {
    const dot = filename.lastIndexOf('.');
    if(dot > 0) {
      $('#image_view_page_title').text(filename);
      progress.unlock();
      $('.main-page').hide();
      $('#image_view_page').show();
      const  ext = filename.substring(dot+1).toLowerCase();

      if( ext == 'pdf') {
        const imageView = document.querySelector('#image_view_page img');
        imageView.style.display = 'none'; 
        const iframe = document.getElementById('viewer');
        iframe.style.display='';
        const obj_url = URL.createObjectURL(blob);
        iframe.setAttribute('src', obj_url);
        URL.revokeObjectURL(obj_url);
        return;
      }

      const imageView = document.querySelector('#image_view_page img');
      imageView.style.display = ''; 
      const iframe = document.getElementById('viewer');
      iframe.style.display='none';

      /*      
      const obj_url = URL.createObjectURL(blob);
      imageView.setAttribute('src', obj_url);
      URL.revokeObjectURL(obj_url);
      */
      
      const reader = new FileReader();

      reader.addEventListener("load", function () {
        imageView.src = reader.result;
        console.log(imageView.naturalHeight);
        set_size();
      }, false);
      reader.readAsDataURL(blob);
 
    }
  });
}

$('.image_view_page_close').on('click', function() {
  $('#image_view_page').hide();
  $('.main-page').show();
});


const itemRow = (item, searchMode) => {
  if (item.cleartext.length < 5) {
    return `<td>Error ${item._id}</td>`;
  }
  const name = utils.escapeHtml(item.cleartext[0]);
  const url = prepareUrl(utils.escapeHtml(item.cleartext[3]));
  let icon = "<svg width='24' height='24' class='item_icon'><use href='#i-key'></use></svg>";
  let row = "<td colspan = '2' class='col-xl-6 col-lg-7 col-md-12' style='border-right: none; padding-left:15px'>";
  if ('file' in item) {
    icon = "<svg width='24' height='24' class='item_icon'><use href='#i-file'></use></svg>";
  } else if ('note' in item) {
    icon = "<svg width='24' height='24' class='item_icon'><use href='#i-note'></use></svg>";
  } else {
    row = "<td class='col-xl-5 col-lg-6 col-md-12' style='border-right: 1px solid #b5d0f0; padding-left:15px'>";
  }
  if (searchMode === undefined) {
    row += `<div class='d-md-none item-click text-truncate' data-record_nb ='${item._id}' style='cursor: pointer; padding-top:5px;padding-bottom:5px;'>`;
  } else {
    row += `<div class='d-md-none sync_search_safe text-truncate' data-record_nb ='${item._id}'style='cursor: pointer; padding-top:5px;padding-bottom:5px;'>`;
  }
  row += `${icon}${name}</div>`;
  if ('file' in item) {
    let sizeString = '';
    if (item.file.hasOwnProperty('size')) {
      sizeString = utils.humanReadableFileSize(item.file.size);
    }
    if (searchMode === undefined) {
      row += "<div class='d-none d-md-block file_record_title text-truncate' style='cursor: pointer;' data-record_nb =";
    } else {
      row += "<div class='d-none d-md-block sync_search_safe file_record_title text-truncate' style='cursor: pointer;' data-record_nb =";
    }
    row += "'" + item._id + "'";
    row += " data-record_name ='" + name + "'>" + icon + name + '</div>'
      + '</td>'
      // + "<td class = 'tdmain hidden-xs' style='cursor: default; border-right: none'>" + size + '</td>';
      + "<td class = 'tdmain col-xl-3 col-lg-5  d-none d-lg-table-cell' align='right' style='cursor: default; border-right: none'>" + sizeString + "</td>";
      // + "<td class = 'tdmain hidden-xs hidden-sm '>" + date + '</td>';
  } else {
    if (searchMode === undefined) {
      row += "<div class='d-none d-md-block record_title text-truncate' style='cursor: pointer;' data-record_nb =";
    } else {
      row += "<div class='d-none d-md-block sync_search_safe record_title text-truncate' style='cursor: pointer;' data-record_nb =";
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

         + "<img src='public/img/outline-https-24px.svg' alt= 'login/password' height='24'>"
//        + "<span class='glyphicon glyphicon-asterisk' aria-hidden='true' style='cursor: pointer; '></span>"
        + '</td>'
        + "<td class='tdmain col-xl-3 col-lg-5 d-none d-lg-table-cell item_url' data-record_nb="
        + item._id + " style='cursor: default;'>" + url + '</td>';
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

const show = (folder) => {

  if(!state.searchMode  && !state.currentSafe.key) {
    $('.not_confirmed_safe').show();
    $('.confirmed_safe').hide();
    $('.confirmed_safe_buttons').hide();
    return;
  }

  $('.not_confirmed_safe').hide();
  $('.confirmed_safe').show();
  $('#item_list_tbody').empty();

  if(state.searchMode) {
    $('.confirmed_safe_buttons').hide();
    safes.setItemPaneHeader();
    const found = passhub.search(passhub.searchString);
    if(found.length == 0) {
      $('.empty_safe').html('Search mode: nothing found');
      $('.empty_safe').show();
      $('#records_table').hide();
      return;
    }
    for (let i = 0; i < found.length; i++) {
      $('#item_list_tbody').append('<tr class="d-flex">' + itemRow(found[i], true) + '</tr>');
    }
    $('.empty_safe').hide();
    $('#records_table').show();
    if (found.length > 0) {
      syncSearchSafe(found[0]._id);
    }
    passhub.showTable();
    return;  
  }
  $('.confirmed_safe_buttons').show();

  for (let i = 0; i < state.currentSafe.folders.length; i++) {
    if ('parent' in state.currentSafe.folders[i]) {
      if (state.currentSafe.folders[i].parent != folder) {
        continue;
      }
    } else if (folder != 0) {
      continue;
    }
    const row = '<tr>'
      + `<td class='col-xs-12 d-md-none list-item-title folder-click' data-folder-id='${state.currentSafe.folders[i]._id}' style='padding-left:15px'>`
      + "<div style='padding-top:5px;padding-bottom:5px; cursor:pointer'>"
      + "<svg width='24' height='24' class='item_icon'><use href='#i-folder'></use></svg>"
      + utils.escapeHtml(state.currentSafe.folders[i].cleartext[0])
      + "<svg width='24' height='24' style='stroke:#2277e6; opacity:0.5; float:right; vertical-align:middle; margin-right:10px'><use href='#ar-forward'></use></svg>"
      + '</div></td></tr>';
    $('#item_list_tbody').append(row);
  }
  for (let i = 0; i < state.currentSafe.items.length; i++) {
    // use local 'let item', same in
    if ('folder' in state.currentSafe.items[i]) {
      if (state.currentSafe.items[i].folder != folder) {
        continue;
      }
    } else if (folder != 0) {
      continue;
    }
    $('#item_list_tbody').append(`<tr class="d-flex"> ${itemRow(state.currentSafe.items[i])} </tr>`);
  }
  if ($('#item_list_tbody > tr').length == 0) {
    if (folder == 0) {
      $('.empty_safe').html('The Safe is empty');
    } else {
      $('.empty_safe').html('The Folder is empty');
    }
    $('.empty_safe').show();
    $('#records_table').hide();
  } else {
    $('.empty_safe').hide();
    $('#records_table').show();
  }
};

$('body').on('click', '.lp_show', function () {
  if (state.searchMode) {
    syncSearchSafe($(this).attr('data-record_nb'));
  }
  showItemModal($(this).attr('data-record_nb'), true);
});

// extension find callback
function findCallback() {

}

function openInExtension(id) {

  const item = getItemById(id);
  if(item) {
    const s = {
      id: 'loginRequest',
      username: item.cleartext[1],
      password: item.cleartext[2],
      url: item.cleartext[3],
    }
    extensionInterface.sendCredentials(s);
  }
}

$('body').on('click', '.item-click', function () {
  showItem($(this).attr('data-record_nb'));
});

$('body').on('click', '.item_url', function() {
  openInExtension($(this).attr('data-record_nb'));
  return false;
}); 

let editItemId;

$('.item_view_edit_btn').click(() => {
  $('#showCreds').modal('hide');
  showItemForm({
    create: false,
    safe: state.currentSafe,
    folder: state.activeFolder,
    itemID: editItemId,
    csrf: state.csrf,
  });
  //  window.location.href = `edit.php?vault=${state.currentSafe.id}&id=${editItemId}`;
});

let intervalTimerID; 

function update6(item) {

  function doUpdate6() {

    if ('totpKey' in item) {
      getTOTP(item.totpKey).then((six) => {
        if ($('.item_view_value').first().text() !== six) {
          $('.item_view_value').text(six);
        }
      });
    }
  }
  if (typeof intervalTimerID !== 'undefined') {
    clearInterval(intervalTimerID);
  }
  intervalTimerID = window.setInterval(doUpdate6, 1000);
}

function showOTP(item) {
  const secret = item.cleartext[5];
  if (secret.length > 0) {
    // const encoder = new TextEncoder('utf-8');
    // const secretBytes1 = encoder.encode(item.secret);

    const s = secret.replace(/\s/g, '').toUpperCase();
    const secretBytes = new Uint8Array(base32.decode.asBytes(s));

    window.crypto.subtle.importKey(
      'raw',
      secretBytes,
      { name: 'HMAC', hash: { name: 'SHA-1' } },
      false,
      ['sign'],
    ).then((key) => {
      item.totpKey = key;
      update6(item)
    });
  }
}

function showItemModal(id, credsOnly = false) {

  try {
    const item = new Item(id);
    const path = item.path();
    $('.item_path').text(`${path}`);
    
  } catch(e) {
    console.log(e);
  }

  for (let i = 0; i < state.currentSafe.items.length; i++) {
    if (state.currentSafe.items[i]._id == id) {
      editItemId = id;
      const item = state.currentSafe.items[i];
      if (item.lastModified) {
        const modified = new Date(item.lastModified).toLocaleString();
        $('.modified').text(`Modified: ${modified}`);
      }

      const data = item.cleartext;
      $('#show-creds-title').text(data[0]);
      $('#creds0ID').val(data[1]);
      $('#creds1ID').val(data[2]);
      const url = prepareUrl(utils.escapeHtml(data[3]));
      $('#item_view_url').html(url);
      $('#item_view_url').attr('data-record_nb', id);

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
      if (item.cleartext.length === 6) {
        showOTP(item);
        $('.item_view_otp').show();
        $('.item_view_value').text('------');
      } else {
        $('.item_view_otp').hide();
      }

      $('#showCreds').modal('show');
      passhub.itemViewNoteResize();
      $('#item_view_note').resize(passhub.itemViewNoteResize);
      break;
    }
  }
}

function showItem(id) {

  try {
    const item = new Item(id);
    const path = item.path();
    $('.item_pane_path').text(`${path}`);
    
  } catch(e) {
    console.log(e);
  }

  let item = null;
  if (state.searchMode) {
    item = getItemById(id);
  } else {
    for (let i = 0; i < state.currentSafe.items.length; i++) {
      if (state.currentSafe.items[i]._id == id) {
        item = state.currentSafe.items[i];
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
      
      if (isFileViewable(data[0])) {
        document.querySelector('#item_pane_view_button').style.display = '';
      } else {
        document.querySelector('#item_pane_view_button').style.display = 'none';
      }
      

      // $('#item_pane_delete_button').click(() => { delete_item.deleteItem($('#item_pane_menu')); });
      $('#item_pane_download_button').on('click', () => { saveFileItem($('#item_pane_menu')); });
      $('#item_pane_view_button').on('click', () => { viewFileItem($('#item_pane_menu')); });

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
        $('#item_pane_url').attr('data-record_nb', id);
        if (item.cleartext.length === 6) {
          showOTP(item);
          $('.item_view_otp').show();
          $('.item_view_value').text('------');
        } else {
          $('.item_view_otp').hide();
        }
  
      }
      $('#item_pane_notes').text(data[4]);
    }
  }

  $('.table_pane').addClass('d-none');
  $('.vaults_pane').addClass('d-none');
  $('.item_pane').removeClass('d-none');
}

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

        showItemForm({
          create: true,
          note: false,
          safe: state.currentSafe,
          folder: state.activeFolder,
          csrf: state.csrf,
        });
      }
    },
    note: {
      name: 'Note',
      callback: () => {
        showItemForm({
          create: true,
          note: true,
          safe: state.currentSafe,
          folder: state.activeFolder,
          csrf: state.csrf,
        });
      }
    },
    file: {
      name: 'File',
      callback: () => {
        showFileForm({
          safe: state.currentSafe,
          folder: state.activeFolder,
          csrf: state.csrf,
        });
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


function isFileViewable(filename)  {
  const dot = filename.lastIndexOf('.');
  if(dot > 0) {
    const  ext = filename.substring(dot+1).toLowerCase();
    if( ext == 'pdf') {
      
      if(utils.isMobile()) {
        return false;
      } 
           
      if ((navigator.userAgent.indexOf("Chrome") == -1)
        && (navigator.userAgent.indexOf("Safari") > 0)
        && (navigator.userAgent.indexOf("Macintosh") > 0)) {
          return false;
      }
      return true;
    }
    if( (ext == 'jpeg') 
      || (ext == 'jpg') 
      || (ext == 'png')
      || (ext == 'gif')
      || (ext == 'bmp')

      /* || (ext == 'tif')
       || (ext == 'svg')  
      */
    ) {
      return true;
    }
  }
  return false;
}

const fileMenuEvents = {
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
};

const fileMenuItems = {

  download: {
    name: 'Download',
    callback: function () {
      saveFileItem($(this));
    },
  },
  
  view: {
    name: 'In-memory View',
    
    visible: function(key) {
      const filename = $(this).text();
      return isFileViewable(filename);
    },
    
    callback: function () {
      viewFileItem($(this));
    },
  },

  cut: {
    name: 'Cut',
    callback: function () {
      $('.toast_header_text').text('Move file to another safe');
      $('.toast_copy').toast('show');
      const now = new Date();
      sessionStorage.setItem('clip', JSON.stringify({
        operation: 'move',
        timestamp: now.getTime(),
        safeID: state.currentSafe.id,
        item: $(this).attr('data-record_nb')
      }));
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
    // icon: 'delete',
    callback: function () {
      delete_item.deleteItem($(this));
    },
  }

};

const fileItemMenu = {
  selector: '.file_record_title',
  trigger: 'left',
  delay: 100,
  autoHide: true,
  zIndex: 10,
  events: fileMenuEvents,
  items: fileMenuItems,
};

$.contextMenu(fileItemMenu);

const fileItemMenuRight = {
  selector: '.file_record_title',
  trigger: 'right',
  delay: 100,
  autoHide: true,
  zIndex: 10,
  events: fileMenuEvents,
  items: fileMenuItems,
};

$.contextMenu(fileItemMenuRight);

const itemMenuEvents = {
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
};

const itemMenuItems = {
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
      showItemForm({
        create: false,
        safe: state.currentSafe,
        folder: state.activeFolder,
        itemID: $(this).attr('data-record_nb'),
        csrf: state.csrf,
      });
    },
  },
  cut: {
    name: 'Cut',
    callback: function () {
      $('.toast_header_text').text('Move item to another safe');
      $('.toast_copy').toast('show');
      const now = new Date();
      sessionStorage.setItem('clip', JSON.stringify({
        operation: 'move',
        timestamp: now.getTime(),
        safeID: state.currentSafe.id,
        item: $(this).attr('data-record_nb')
      }));
    },
  },
  copy: {
    name: 'Copy',
    callback: function () {
      $('.toast_header_text').text('Copy item to another safe');
      $('.toast_copy').toast('show');
      const now = new Date();
      sessionStorage.setItem('clip', JSON.stringify({
        operation: 'copy',
        timestamp: now.getTime(),
        safeID: state.currentSafe.id,
        item: $(this).attr('data-record_nb')
      }));
    },
  },
  delete: {
    name: 'Delete',
    // icon: 'delete',
    callback: function () {
      delete_item.deleteItem($(this));
    },
  },
};

const itemMenu = {
  selector: '.record_title',
  trigger: 'left',
  // delay: 100,
  autoHide: true,
  zIndex: 10,
  events: itemMenuEvents,
  items: itemMenuItems,
};
$.contextMenu(itemMenu);

const itemMenuRight = {
  selector: '.record_title',
  trigger: 'right',
  autoHide: true,
  zIndex: 10,
  events: itemMenuEvents,
  items: itemMenuItems,
};
$.contextMenu(itemMenuRight);

function syncSearchSafe(itemID) {
  const item = getItemById(itemID);
  if ((state.currentSafe.id == item.SafeID) && (state.activeFolder == item.folder)) {
    return;
  }
  passhub.setActiveSafe(item.SafeID);
  safes.setActiveFolder(item.folder, true);
  passhub.makeCurrentVaultVisible();
}


$('body').on('click', '.sync_search_safe', function () {
  syncSearchSafe($(this).attr('data-record_nb'));
  if ($(this).hasClass('d-md-none')) {
    showItem($(this).attr('data-record_nb'));
    // showItemModal($(this).attr('data-record_nb'));
  }
});

export { show, showItem, /*showSearchResult*/ };
