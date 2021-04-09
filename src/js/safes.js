// import jQuery from 'jquery';
import $ from 'jquery';
import 'jquery-contextmenu';

import state from './state';
import * as items from './items';
import * as utils from './utils';
import passhub from './passhub';

import treeView from './treeView';

const sharedFolderIcon = "<svg width='24' height='24' style='stroke:white; opacity:0.7; vertical-align:middle; margin-right:10px'>"
+ `<use href='#i-folder_shared'></use></svg>`;
const folderIcon =  "<svg width='24' height='24' style='stroke:white; opacity:0.7; vertical-align:middle; margin-right:10px'>"
+ `<use href='#i-folder'></use></svg>`;

/*
const sharedFolderIcon = "<svg width='24' height='24' class='folder-icon'>"
+ `<use href='#i-folder_shared'></use></svg>`;

const folderIcon =  "<svg width='24' height='24' class='folder-icon'>"
+ `<use href='#i-folder'></use></svg>`;
*/

const showSafesMobile = () => {
  $('#safe_list_ul_mobile').empty();
  for (let i = 0; i < state.safes.length; i++) {
    const safe = state.safes[i];
    const icon = safe.users > 1 ? sharedFolderIcon : folderIcon;

    let safeText = `${icon}${utils.escapeHtml(safe.name)}`;
    if (safe.confirm_req > 0) {
      safeText += " <span class='badge badge-pill badge-light' style='float:none' >!</span>";
    } else {
      safeText += " <span class='badge badge-pill badge-light' style='float:none;display:none'>!</span>";
    }
    const arrowForward = "<svg width='24' height='24' style='stroke:white; vertical-align:middle; float:right;'>"
    + "<use href='#ar-forward'></use></svg>";

    let row;
    if (safe.id === state.currentSafe.id) {
      if (safe.key) { // safe confirmed
        row = `<div class='d-md-none list-item-vault-active safe' data-safe-id='${safe.id}'>`;
        row += `${safeText} ${arrowForward}</div>`;

      } else {
        row = `<div class=' d-md-none list-item-vault-active safe' data-safe-id='${safe.id}'>${safeText}`;
        row += "<span class=' glyphicon glyphicon-menu-right' aria-hidden='true' style='float:right;'></span></div>";
      }
      $('#safe_list_ul_mobile').append(row);
    } else {
      row = `<div class='list-item-vault safe' data-safe-id='${safe.id}'>${safeText}</div>`;
      $('#safe_list_ul_mobile').append(row);
    }
  }
};

function getChildren(folders, parent) {
  const result = [];
  for(let f = 0; f < folders.length; f++) {
    if(folders[f].parent == parent) {
      const child = {};
      child.text = utils.escapeHtml(folders[f].cleartext[0]);
      child.id = folders[f]._id;
      child.icon = folderIcon; 
      const children = getChildren(folders, folders[f]._id);
      if(children.length > 0) {
        child.children = children;
      }
      result.push(child);
    }
  }
  return result;
}

let activeElementID;
let backupActiveElementID;
let newFolderID;

function setNewFolderID(id) {
  newFolderID = id;
}

const showSafes = () => {

  const data = [];
  for (let i = 0; i < state.safes.length; i++) {
    const safe = state.safes[i];
    const safe_entry = {};
    safe_entry.text = `${utils.escapeHtml(safe.name)}`;
    safe_entry.id=safe.id;
    safe_entry.isSafe = true;
    safe_entry.icon = safe.users > 1 ? sharedFolderIcon : folderIcon;
    
    // safe_entry.icon=`public/img/SVG2/${safeIcon}.svg`; 
    safe_entry.li_attr = {'data-id': safe.id};
    const children = getChildren(safe.folders, 0);
    if(children.length >0) {
      safe_entry.children = children; 
    }
    data.push(safe_entry); 
  }
  // $('#safe_list_ul').css({'color' : 'white'});
  const container = document.querySelector('#safe_list_ul');
  while (container.firstChild) {
    container.removeChild(container.firstChild);
  }
  treeView(container, data);
  showSafesMobile();

  if(newFolderID) {
    activeElementID = newFolderID;
    newFolderID = null;
  }
  if(!activeElementID) {
    activeElementID = state.safes[0].id;
    backupActiveElementID = null; 
  }
  setActiveElement(activeElementID);
}

const menuIcon =  "<svg width='24' height='24' style='stroke:white; opacity:0.7; vertical-align:middle; margin:0 10px'>"
+ `<use href='#el-dots'></use></svg>`;

const menuDots = document.createElement('div');
menuDots.classList.add('menu-dots');
menuDots.classList.add('safe-menu');
menuDots.innerHTML = `${menuIcon}`;

function setActiveElement (id) {
  let el = document.getElementById(id);
  if(!el) {
    el = document.getElementById(backupActiveElementID);
    if(el) {
      activeElementID = backupActiveElementID;
      backupActiveElementID = null;
    } else {
      activeElementID = state.safes[0].id;
      backupActiveElementID = null; 
      el = document.getElementById(activeElementID);
    }
  }

  $('.active-folder').removeClass('active-folder');
  el.classList.add('active-folder');
  el.appendChild(menuDots);

  if(el.classList.contains('safe')) {
    // sync mobile view

    menuDots.classList.remove('folder-menu');
    menuDots.classList.add('safe-menu');
    $('#item_list_folder_menu').removeClass('folder-menu');
    $('#item_list_folder_menu').addClass('safe-menu');
    passhub.showTable();
  } else {
    menuDots.classList.remove('safe-menu');
    menuDots.classList.add('folder-menu');
    $('#item_list_folder_menu').removeClass('safe-menu');
    $('#item_list_folder_menu').addClass('folder-menu');
  }

  activeElementID = el.id;
  if(el.classList.contains('leaf')) { // may be the first ( n-th safe) 
    if(el.parentElement.firstChild.tagName.toLowerCase() == 'summary') {
      backupActiveElementID = el.parentElement.firstChild.id;
      if(!el.parentElement.open) {
        el.parentElement.open = true;
      }
    }
  } else if (el.tagName.toLowerCase() == 'summary'){
    if(el.parentElement.parentElement.firstChild.tagName.toLowerCase() == 'summary') {
      backupActiveElementID = el.parentElement.parentElement.firstChild.id;
    } else {
      backupActiveElementID = null;
    } 
  } 
  else  {
    backupActiveElementID = null;
  }
  setActiveFolder(el.id);
}

$('#safe_list_ul').on('click', 'summary', function(e) {

  if(e.offsetX < 14) {
    return;
  }

  const searchMode = state.searchMode;

  if (state.searchMode) {
    state.searchMode = false;
    $('#search_string').val('');
    $('#search_string_xs').val('');
  }

  if(e.target.tagName.toLowerCase() == 'svg') {
    if(e.target.parentElement.tagName.toLowerCase() == 'summary') {
      if( !e.target.parentElement.classList.contains('active-folder') || searchMode) {
        setActiveElement(e.target.parentElement.id);
      }
    }
  }

  if(e.target.tagName.toLowerCase() == 'summary') {
    if(!e.target.classList.contains('active-folder') || searchMode) {
      setActiveElement(e.target.id);
    }
  }
});

$('#safe_list_ul').on('click', '.leaf', function(e) {

  if (state.searchMode) {
    state.searchMode = false;
    $('#search_string').val('');
    $('#search_string_xs').val('');
    setActiveElement(e.currentTarget.id);
    return;
  }

  if(!e.currentTarget.classList.contains('active-folder')) {
    setActiveElement(e.currentTarget.id);
  }
});

/*
const dots = "<svg width='24' height='24' style='stroke:white; vertical-align:middle;float:right; '>"
+ "<use href='#el-dots'></use></svg>";


const showFolders = (folders, indent, folderPath) => {
  const parent = folderPath[0];
  for (let f = 0; f < folders.length; f++) {
    if (folders[f].parent == parent) {
      const folderText = "<svg width='24' height='24' style='stroke:white; opacity:0.5; vertical-align:middle; margin-right:10px'><use href='#i-folder'></use></svg>" + utils.escapeHtml(folders[f].cleartext[0]);
      let row;
      if (state.activeFolder == folders[f]._id) { // active
        row = `<div class=' d-none d-md-block list-item-vault-active folder-menu'  data-folder-id='${folders[f]._id}' style = 'padding-left:${5 + indent}px'>`;
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
*/
/*

function getParent(folderID) {
  for (let f = 0; f < state.currentSafe.folders.length; f++) {
    if (state.currentSafe.folders[f]._id == folderID) {
      return state.currentSafe.folders[f].parent;
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
*/

function searchOff() {
  $('#search_string').val('');
  $('#search_string_xs').val('');
  if (state.searchMode) {
    state.searchMode = false;
    if(state.activeFolder) {
      setActiveElement(state.activeFolder);
    } else {
      setActiveElement(state.currentSafe.id);
    }
  }
}

function searchStringChange(searchString) {
  if(searchString.length > 0 ) {
    passhub.searchString = searchString;
    state.searchMode = true;
    items.show();
  } else {
    searchOff();
  }
}

$('#search_string').on('input', () => {
  const searchString = $('#search_string').val().trim();
  $('#search_string_xs').val(searchString)
  searchStringChange(searchString);
});

$('#search_string_xs').on('input', () => {
  const searchString = $('#search_string_xs').val().trim();
  $('#search_string').val(searchString)
  searchStringChange(searchString);
});

$('.search_clear').on('click', () => {
  searchOff();
});

function folderOnClick() {
  searchOff();
  const id = $(this).attr('data-folder-id');
  setActiveElement(id);
}

$('body').on('click', '.folder-click', folderOnClick);


function safeOnClickMobile() {
  // searchOff();

  if (state.searchMode) {
    state.searchMode = false;
    $('#search_string').val('');
    $('#search_string_xs').val('');
  }

  const id = $(this).attr('data-safe-id');
  setActiveElement(id);

 /* 
  if (id != state.currentSafe.id) {
    setActiveElement(id);

    // passhub.setActiveSafe(id);
    // setActiveFolder(state.activeFolder);
    // passhub.makeCurrentVaultVisible();


  } else if (state.activeFolder != 0) {
    setActiveElement(id);
    // state.activeFolder = 0;
    // setActiveFolder(state.activeFolder);
    // passhub.makeCurrentVaultVisible();
  }
  // passhub.showTable();
  */
}

$('#safe_list_ul_mobile').on('click', '.safe', safeOnClickMobile);

const setItemPaneHeader = () => {
  if (state.currentSafe.key) { // safe confirmed
    $('#item_list_folder_menu').show();
  } else {
    $('#item_list_folder_menu').hide();
  }

  if (!state.activeFolder || (state.activeFolder === '0')) {
    let safeText = utils.escapeHtml(state.currentSafe.name);
    if (state.currentSafe.confirm_req > 0) {
      safeText += " <span class='badge badge-pill badge-light' >!</span>";
    }
    $('#safe_and_folder_name').html(safeText);
  } else {
    for (let f = 0; f < state.currentSafe.folders.length; f++) {
      if (state.currentSafe.folders[f]._id == state.activeFolder) {
        $('#safe_and_folder_name').text(utils.escapeHtml(state.currentSafe.folders[f].cleartext[0]));
      }
    }
  }
};


function reportCurrentSafe() {
  $.ajax({
    url: 'index.php',
    type: 'POST',
    data: {
      verifier: state.csrf,
      current_safe: state.currentSafe.id,
    },
    error: () => {},
    success: () => {},
  });
}

const setActiveFolder = (id, search) => {
  
  const folder = passhub.getFolderById(id);
  if(passhub.getFolderById(id)) {
    state.activeFolder = id;
    state.currentSafe = passhub.getSafeById(folder.SafeID);
  } else {
    const safe = passhub.getSafeById(id);
    if(safe) {
      state.activeFolder = 0;
      state.currentSafe = safe;
      reportCurrentSafe();
    } else {
      return;
    }
  }
  if (state.currentSafe.key) { // safe confirmed
    if (state.activeFolder == 0) {
      $('#item_list_folder_menu').removeClass('folder-menu');
      $('#item_list_folder_menu').addClass('safe-menu');
    } else {
      $('#item_list_folder_menu').removeClass('safe-menu');
      $('#item_list_folder_menu').addClass('folder-menu');
    }
  }
  showSafesMobile();
  
  setItemPaneHeader();
  if (typeof search === 'undefined') {
    items.show(state.activeFolder);
  }
};


const cutCopyTimeout = 40000;

const safeMenu = {
  selector: '.safe-menu',
  className: 'safeMenu',  // Selenium test locator
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
        $(state.shareModal).modal('show');
      },
    },
    users: {
      name: 'Users',
      isHtmlName: true,
      visible: () => !state.currentSafe.confirm_req,
      callback: () => {
        $('#safeUsers').modal('show');
      },
    },
    users_red: {
      name: "Users <span class='badge badge-pill badge-danger' >!</span>",
      isHtmlName: true,
      visible: () => state.currentSafe.confirm_req,
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
    folder: {
      name: 'Add folder',
      callback: () => {
        $('#newFolderModal').modal('show');
      },
    },
    paste: {
      name: 'Paste',
      callback: () => {
        $('.context-menu-list').trigger('contextmenu:hide');
        $('.toast_copy').toast('hide');
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
          state.currentSafe.id,
          state.activeFolder,
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
        if ((now.getTime() - clip.timestamp) > cutCopyTimeout) {
          sessionStorage.removeItem('clip');
          return true;
        }
        if (clip.safeID == state.currentSafe.id) {
          if ('item' in clip) {
            for (let i = 0; i < state.currentSafe.items.length; i++) {
              if (state.currentSafe.items[i]._id == clip.item) {
                if ('folder' in state.currentSafe.items[i]) {
                  if (state.currentSafe.items[i].folder == 0) {
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
        passhub.exportSafe = state.currentSafe;
        $('#backupModal').modal('show');
      },
    },
    delete: {
      name: 'Delete',
      // icon: 'delete',
      callback: () => {
        $('#deleteSafeModal').modal('show');
      },
    },
  },
};

$.contextMenu(safeMenu);

const folderMenu = {
  selector: '.folder-menu',
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
    folder: {
      name: 'Add folder',
      callback: () => {
        $('#newFolderModal').modal('show');
      },
    },
    paste: {
      name: 'Paste',
      callback: () => {
        $('.context-menu-list').trigger('contextmenu:hide');
        $('.toast_copy').toast('hide');
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
          state.currentSafe.id,
          state.activeFolder,
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
        if ((now.getTime() - clip.timestamp) > cutCopyTimeout) {
          sessionStorage.removeItem('clip');
          return true;
        }
        if (clip.safeID == state.currentSafe.id) {
          if ('item' in clip) {
            for (let i = 0; i < state.currentSafe.items.length; i++) {
              if (state.currentSafe.items[i]._id == clip.item) {
                if ('folder' in state.currentSafe.items[i]) {
                  if (state.currentSafe.items[i].folder == state.activeFolder) {
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
      // icon: 'delete',
      callback: () => {
        $('#deleteFolderModal').modal('show');
      },
    },
  },
};

$.contextMenu(folderMenu);

$('.export_all').on('click', () => {
  passhub.exportSafe = null;
  $('#backupModal').modal('show');
});

export default { setActiveFolder, setItemPaneHeader, showSafes, setNewFolderID};
