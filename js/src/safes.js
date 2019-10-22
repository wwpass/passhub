// import jQuery from 'jquery';
import $ from 'jquery';
import 'jquery-contextmenu';
// import 'bootstrap';

import * as items from './items';
import * as utils from './utils';
//import * as passhub from './passhub';
import passhub from './passhub';

const dots = "<svg width='24' height='24' style='stroke:white; vertical-align:middle;float:right; '>"
+ "<use xlink:href='img/SVG2/sprite.svg#el-dots'></use></svg>";

function folderOnClick() {
  const id = $(this).attr('data-folder-id');
  setActiveFolder(id);
}

$('body').on('click', '.folder-click', folderOnClick);

const showFolders = (folders, indent, folderPath) => {
  const parent = folderPath[0];
  for (let f = 0; f < folders.length; f++) {
    if (folders[f].parent == parent) {
      const folderText = "<svg width='24' height='24' style='stroke:white; opacity:0.5; vertical-align:middle; margin-right:10px'><use xlink:href='img/SVG2/sprite.svg#i-folder'></use></svg>" + utils.escapeHtml(folders[f].cleartext[0]);
      let row;
      if (passhub.activeFolder == folders[f]._id) { // active
        row = `<div class=' d-none d-md-block list-item-vault-active folder_with_menu'  data-folder-id='${folders[f]._id}' style = 'padding-left:${5 + indent}px'>`;
        row += `${folderText} ${dots}</div>`;
      } else {
        row = `<div class='d-none d-md-block list-item-vault folder-click' data-folder-id='${folders[f]._id}' style ='padding-left:${5 + indent}px'>${folderText}</div>`;
      }
      $('#safe_list_ul').append(row);
      if ((folderPath.length > 1) && (folderPath[1] == folders[f]._id)) {
        showFolders(folders, indent + 25, folderPath.slice(1));
      }
    }
  }
};

function getParent(folderID) {
  for (let f = 0; f < passhub.currentSafe.folders.length; f++) {
    if (passhub.currentSafe.folders[f]._id == folderID) {
      return passhub.currentSafe.folders[f].parent;
    }
  }
  return 0; // should never happen
}

function createPath(node) {
  const path = [];
  while (true) {
    path.unshift(node);
    if (node == 0) {
      break;
    }
    node = getParent(node);
  }
  return path;
}

const showSafes = () => {
  $('#safe_list_ul').empty();
  for (let i = 0; i < passhub.safes.length; i++) {
    const safe = passhub.safes[i];
    const safeIcon = safe.users > 1 ? 'sprite.svg?v=1#i-folder_shared' : 'sprite.svg#i-folder';
    let safeText = "<svg width='24' height='24' style='stroke:white; opacity:0.5; vertical-align:middle; margin-right:10px'>"
      + `<use xlink:href='img/SVG2/${safeIcon}'></use></svg>${utils.escapeHtml(safe.name)}`;
    if (safe.confirm_req > 0) {
      safeText += " <span class='badge badge-pill badge-light' style='float:none' >!</span>";
    } else {
      safeText += " <span class='badge badge-pill badge-light' style='float:none;display:none'>!</span>";
    }
    const arrowForward = "<svg width='24' height='24' style='stroke:white; vertical-align:middle; float:right;'>"
    + "<use xlink:href='img/SVG2/sprite.svg#ar-forward'></use></svg>";

    let row;
    if (safe.id === passhub.currentSafe.id) {
      if (safe.key) { // safe confirmed
        row = `<div class='d-md-none list-item-vault-active safe' data-safe-id='${safe.id}'>`;
        row += `${safeText} ${arrowForward}</div>`;

        if (passhub.activeFolder == 0) {
          row += `<div class='d-none d-md-block list-item-vault-active vault_with_menu' data-safe-id='${safe.id}'>`;
          row += `${safeText} ${dots}</div>`;
        } else {
          row += `<div class='d-none d-md-block list-item-vault safe' data-safe-id='${safe.id}'>`;
          row += `${safeText}</div>`;
        }
      } else {
        row = `<div class=' d-md-none list-item-vault-active safe' data-safe-id='${safe.id}'>${safeText}`;
        row += "<span class=' glyphicon glyphicon-menu-right' aria-hidden='true' style='float:right;'></span></div>";
        row += `<div class=' d-none d-md-block list-item-vault-active safe' data-safe-id='${safe.id}'>${safeText}</div>`;
      }
      $('#safe_list_ul').append(row);
      if (safe.key) { // safe confirmed
        const folderPath = createPath(passhub.activeFolder);
        showFolders(passhub.currentSafe.folders, 35, folderPath);
      }
    } else {
      row = `<div class='list-item-vault safe' data-safe-id='${safe.id}'>${safeText}</div>`;
      $('#safe_list_ul').append(row);
    }
  }
};


function searchOff() {
  if (passhub.searchMode) {
    passhub.searchMode = false;
    $('.search_div').hide();
    $('.confirmed_safe_buttons').show();
    setActiveFolder(passhub.activeFolder);
    passhub.indexPageResize();
  }
}

$('.search_switch').click(() => {
  if (!passhub.searchMode) {
    passhub.searchMode = true;
    $('.confirmed_safe_buttons').hide();
    $('.search_div').show();
    $('#search_string').focus();
    passhub.indexPageResize();
  } else {
    searchOff();
  }
});

function safeOnClick() {
  const id = $(this).attr('data-safe-id');
  if (id != passhub.currentSafe.id) {
    passhub.setActiveSafe(id);
    setActiveFolder(passhub.activeFolder);
    passhub.makeCurrentVaultVisible();
    searchOff();
  } else if (passhub.activeFolder != 0) {
    passhub.activeFolder = 0;
    setActiveFolder(passhub.activeFolder);
    passhub.makeCurrentVaultVisible();
    searchOff();
  }
  passhub.showTable();
}

$('#safe_list_ul').on('click', '.safe', safeOnClick);

const setItemPaneHeader = () => {
  if (passhub.currentSafe.key) { // safe confirmed
    $('#item_list_folder_menu').show();
  } else {
    $('#item_list_folder_menu').hide();
  }

  if (!passhub.activeFolder || (passhub.activeFolder === '0')) {
    let safeText = utils.escapeHtml(passhub.currentSafe.name);
    if (passhub.currentSafe.confirm_req > 0) {
      safeText += " <span class='badge badge-pill badge-light' >!</span>";
    }
    $('#safe_and_folder_name').html(safeText);
  } else {
    for (let f = 0; f < passhub.currentSafe.folders.length; f++) {
      if (passhub.currentSafe.folders[f]._id == passhub.activeFolder) {
        $('#safe_and_folder_name').text(passhub.currentSafe.folders[f].cleartext[0]);
      }
    }
  }
};

const setActiveFolder = (id, search) => {
  passhub.activeFolder = 0;
  if (passhub.currentSafe.key) { // safe confirmed
    for (let i = 0; i < passhub.currentSafe.folders.length; i++) {
      if (passhub.currentSafe.folders[i]._id == id) {
        passhub.activeFolder = id;
        break;
      }
    }
    if (passhub.activeFolder == 0) {
      $('#item_list_folder_menu').removeClass('folder_with_menu');
      $('#item_list_folder_menu').addClass('vault_with_menu');
    } else {
      $('#item_list_folder_menu').removeClass('vault_with_menu');
      $('#item_list_folder_menu').addClass('folder_with_menu');
    }
    /*
    if (navigator.userAgent.match(/iPhone|iPod|iPad/i)) {
      safe_menu.trigger = 'left';
      folder_menu.trigger = 'left';
    }
    $.contextMenu(folder_menu);
    */
  }
  showSafes();
  setItemPaneHeader();
  if (typeof search === 'undefined') {
    items.show(passhub.activeFolder);
  }
};

const safeMenu = {
  selector: '.vault_with_menu',
  className: 'safeMenu',
  trigger: 'left',
  delay: 100,
  autoHide: true,
  items: {
    /*
    add_folder: {
      name: 'Add folder',
      callback: () => {
        $('#newFolderModal').modal('show');
      },
    },
    */
    share: {
      name: 'Share',
      callback: () => {
        $(passhub.shareModal).modal('show');
      },
    },
    users: {
      name: 'Users',
      isHtmlName: true,
      visible: () => !passhub.currentSafe.confirm_req,
      callback: () => {
        $('#safeUsers').modal('show');
      },
    },
    users_red: {
      name: "Users <span class='badge badge-pill badge-danger' >!</span>",
      isHtmlName: true,
      visible: () => passhub.currentSafe.confirm_req,
      callback: () => {
        $('#safeUsers').modal('show');
      },
    },
    rename: {
      name: 'Rename',
      callback: () => {
        $('#renameVault').modal('show');
      },
    },
    paste: {
      name: 'Paste',
      callback: () => {
        let clip = sessionStorage.getItem('clip');
        if (clip == null) {
          return true;
        }
        // TODO check parse exceptions
        clip = JSON.parse(clip);
        sessionStorage.removeItem('clip');
        passhub.moveItem(
          clip.item,
          clip.safeID,
          passhub.currentSafe.id,
          passhub.activeFolder,
          clip.operation,
        );
        return false;
      },
      disabled: () => {
        let clip = sessionStorage.getItem('clip');
        if (clip == null) {
          return true;
        }
        // TODO check parse exceptions
        clip = JSON.parse(clip);
        const now = new Date();
        if ((now.getTime() - clip.timestamp) > 30000) {
          sessionStorage.removeItem('clip');
          return true;
        }
        if (clip.safeID == passhub.currentSafe.id) {
          if ('item' in clip) {
            for (let i = 0; i < passhub.currentSafe.items.length; i++) {
              if (passhub.currentSafe.items[i]._id == clip.item) {
                if ('folder' in passhub.currentSafe.items[i]) {
                  if (passhub.currentSafe.items[i].folder == 0) {
                    return true;
                  }
                  return false;
                }
                return true;
              }
            }
            alert('copy/move error');
          }
        }
        return false;
      },
    },
    export: {
      name: 'Export',
      callback: () => {
        passhub.exportSafe = passhub.currentSafe;
        $('#backupModal').modal('show');
      },
    },
    delete: {
      name: 'Delete',
      icon: 'delete',
      callback: () => {
        $('#deleteSafeModal').modal('show');
      },
    },
  },
};

$.contextMenu(safeMenu);

const folderMenu = {
  selector: '.folder_with_menu',
  trigger: 'left',
  delay: 100,
  autoHide: true,
  items: {
    /*
    add_folder: {
      name: 'Add folder',
      callback: () => {
        $('#newFolderModal').modal('show');
      },
    },
    */
    rename: {
      name: 'Rename',
      callback: () => {
        $('#renameFolderModal').modal('show');
      },
    },
    paste: {
      name: 'Paste',
      callback: () => {
        let clip = sessionStorage.getItem('clip');
        if (clip == null) {
          return true;
        }
        // TODO check parse exceptions
        clip = JSON.parse(clip);
        sessionStorage.removeItem('clip');
        passhub.moveItem(
          clip.item,
          clip.safeID,
          passhub.currentSafe.id,
          passhub.activeFolder,
          clip.operation
        );
        return false;
      },
      disabled: () => {
        let clip = sessionStorage.getItem('clip');
        if (clip == null) {
          return true;
        }
        // TODO check parse exceptions
        clip = JSON.parse(clip);
        const now = new Date();
        if ((now.getTime() - clip.timestamp) > 30000) {
          sessionStorage.removeItem('clip');
          return true;
        }
        if (clip.safeID == passhub.currentSafe.id) {
          if ('item' in clip) {
            for (let i = 0; i < passhub.currentSafe.items.length; i++) {
              if (passhub.currentSafe.items[i]._id == clip.item) {
                if ('folder' in passhub.currentSafe.items[i]) {
                  if (passhub.currentSafe.items[i].folder == passhub.activeFolder) {
                    return true;
                  }
                  return false;
                }
                return false;
              }
            }
            alert('copy/move error');
          }
        }
        return false;
      },
    },
    delete: {
      name: 'Delete',
      icon: 'delete',
      callback: () => {
        $('#deleteFolderModal').modal('show');
      },
    },
  },
};

$.contextMenu(folderMenu);

$('#search_string').keydown((event) => {
  if (event.which === 13) {
    const searchString = $('#search_string').val().trim();
    if (searchString.length) {
      const found = passhub.search(searchString);
      items.showSearchResult(found);
    }
  }
});

$('.do_search').click(() => {
  const searchString = $('#search_string').val().trim();
  if (searchString.length) {
    const found = passhub.search(searchString);
    items.showSearchResult(found);
  }
});


$('.export_all').click(() => {
  passhub.exportSafe = null;
  $('#backupModal').modal('show');
});

export default { setActiveFolder, setItemPaneHeader };
