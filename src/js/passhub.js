import $ from 'jquery';
import state from './state';
import * as WWPass from 'wwpass-frontend';
import * as utils from './utils';
import progress from './progress';
import passhubCrypto from './crypto';
import safes from './safes';
import * as items from './items';

function getSharingStatus(ph) {
  $.ajax({
    url: 'get_sharing_status.php',
    type: 'GET',
    global: false,
    success: (result) => {
      if (result.status === 'Ok') {
        for (let i = 0; i < result.accepted.length; i++) {
          for (let s = 0; s < state.safes.length; s++) {
            if (state.safes[s].id == result.accepted[i]) {
              state.safes[s].confirm_req = 1;
            }
          }
          $(`div.hidden-xs[data-safe-id=${result.accepted[i]}]`).find('span').show();

          if (result.accepted[i] == ph.currentSafe.id) {
            safes.setItemPaneHeader();
          }
        }
        let stillNotConfirmed = false;
        if (ph.currentSafe.key == null) {
          for (let i = 0; i < result.not_confirmed.length; i++) {
            if (result.not_confirmed[i] == ph.currentSafe.id) {
              stillNotConfirmed = true;
            }
          }
        }
        if ((stillNotConfirmed === false) && (ph.currentSafe.key == null)) {
          window.location.href = 'index.php';
          return;
        }
        if ((result.invited.length > 0) || (stillNotConfirmed)) {
          ph.getSharingStatusTimer = setTimeout(getSharingStatus, 30 * 1000, ph);
        }
      }
    },
  });
}

export default {

  getSharingStatusTimer: null,

  getSafeById(id) {
    for (let s = 0; s < state.safes.length; s += 1) {
      if (state.safes[s].id == id) {
        return state.safes[s];
      }
    }
    return null;
  },

  getFolderById(id) {
    for (let s = 0; s < state.safes.length; s += 1) {
      for (let i = 0; i < state.safes[s].folders.length; i += 1) {
        if (state.safes[s].folders[i]._id === id) {
          return state.safes[s].folders[i];
        }
      }
    }
    return null;
  },

  decryptSafeData: (aesKey, safe) => {
    for (let i = 0; i < safe.items.length; i += 1) {
      safe.items[i].cleartext = passhubCrypto.decodeItem(safe.items[i], aesKey);
    }
    safe.items.sort((a, b) => {
      if (('file' in a) && !('file' in b)) {
        return 1;
      }
      if (!('file' in a) && ('file' in b)) {
        return -1;
      }
      const a0 = a.cleartext[0].toLowerCase();
      const b0 = b.cleartext[0].toLowerCase();
      return a0 < b0 ? -1 : (a0 > b0 ? 1 : 0);
    });

    for (let i = 0; i < safe.folders.length; i += 1) {
      safe.folders[i].cleartext = passhubCrypto.decodeFolder(safe.folders[i], aesKey);
    }
    safe.folders.sort((a, b) => {
      const a0 = a.cleartext[0].toLowerCase();
      const b0 = b.cleartext[0].toLowerCase();
      return a0 < b0 ? -1 : (a0 > b0 ? 1 : 0);
    });
  },

  decryptSafes(eSafes) {
    const promises = [];
    for (let i = 0; i < eSafes.length; i++) {
      const safe = eSafes[i];
      if (safe.key) {
        promises.push(passhubCrypto.decryptAesKey(safe.key)
          .then((bstringKey) => {
            safe.bstringKey = bstringKey;
            return this.decryptSafeData(bstringKey, safe)
          })
        );
      }
    }
    return Promise.all(promises);
  },

  indexPageResize() {
    const e = document.querySelector('#probe');
    document.getElementsByTagName('body')[0].appendChild(e);

    const { bottom } = e.getBoundingClientRect();
    const t0 = utils.isXs() ? 2 : 30;

    const t = document.querySelector('#probe_top');
    const { top } = t.getBoundingClientRect();

    const height = bottom - top - t0;

    $('#item_form_page').css('min-height', height);
    $('#index_page_row').height(height);

    // $('#image_view_page').css('max-height', height);
    $('#image_view_page').height(height);
  },

  makeCurrentVaultVisible() {
    const vsc = $('.vaults_scroll_control');
    if (vsc[0].scrollHeight > vsc.innerHeight()) {
      const e = utils.isXs() ? document.getElementsByClassName('list-item-vault-active d-md-none')[0]
        : document.getElementsByClassName('list-item-vault-active d-md-block')[0];
      if (e) {
        e.scrollIntoView(false);
      }
    }
  },

  showTable() {
    $('.vaults_pane').addClass('d-none');
    $('.item_pane').addClass('d-none');
    $('.table_pane').removeClass('d-none');
    this.indexPageResize();
  },

  showSafes() {
    $('.table_pane').addClass('d-none');
    $('.vaults_pane').removeClass('d-none');
    $('.item_pane').addClass('d-none');
    this.indexPageResize();
    this.makeCurrentVaultVisible();
  },

  reportCurrentSafe() {
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
  },

  setActiveSafe(id) {
    state.currentSafe = this.getSafeById(id);
    state.activeFolder = 0;
    this.reportCurrentSafe();

    if (this.getSharingStatusTimer && !state.invitationAcceptPending && state.currentSafe.key) { // safe confirmed
      clearTimeout(this.getSharingStatusTimer);
      this.getSharingStatusTimer = null;
    } else if (!this.getSharingStatusTimer && (state.invitationAcceptPending || !state.currentSafe.key)) {
      getSharingStatus(this);
    }
  },

  itemViewNoteResize: () => {
    const showCredsNotesTop = $('#item_view_notes').offset().top;
//    $('#item_view_notes').height('calc(100vh - ' + parseInt(showCredsNotesTop + 150) + 'px)').height();
    $('#item_view_notes').css('max-height', 'calc(100vh - ' + parseInt(showCredsNotesTop + 150) + 'px)');
  },

  moveItemFinalize(recordID, dst_safe, dst_folder, item, operation) {
    $.ajax({
      url: 'move.php',
      type: 'POST',
      data: {
        id: recordID,
        src_safe: state.currentSafe.id,
        dst_safe,
        dst_folder,
        item,
        operation,
      },
      error: (hdr, status, err) => {
        alert(`${status} ${err}`);
      },
      success: (result) => {
        if (result.status === 'Ok') {
          // window.location.href = `index.php?vault=${state.currentSafe.id}`;
          this.refreshUserData();
          return;
        }
        if (result.status === 'login') {
          window.location.href = 'expired.php';
          return;
        }
        alert(result.status);
      },
    });
  },

  moveFile(recordId, srcSafe, dstSafe, dstFolder, operation) {
    $.ajax({
      url: 'move.php',
      type: 'POST',
      data: {
        id: recordId,
        src_safe: srcSafe,
        dst_safe: dstSafe,
        operation,
        checkRights: true,
      },
      error: (hdr, status, err) => {
        alert(`${status} ${err}`);
      },
      success: (result) => {
        if (result.status === 'no src write') {
          ustils.bsAlert('Sorry, "Cut" operation is forbidden. You have only read access to the source safe.');
          return;
        }

        if (result.status === 'Ok') {
          const eItem = passhubCrypto.moveFile(recordId, getSafeById(srcSafe), getSafeById(dstSafe));
        }
        if (result.status === 'login') {
          window.location.href = 'index.php';
          return false;
        }
        alert(result.status);
        return false;
      },
    });
  },


  moveItem(recordId, srcSafe, dstSafe, dstFolder, operation) {
    $.ajax({
      url: 'move.php',
      type: 'POST',
      data: {
        id: recordId,
        src_safe: srcSafe,
        dst_safe: dstSafe,
        operation,
        checkRights: true,
      },
      error: (hdr, status, err) => {
        alert(`${status} ${err}`);
      },
      success: (result) => {
        if (result.status === 'no src write') {
          utils.bsAlert('Sorry, "Cut" operation is forbidden. You have only read access to the source safe.');
          return;
        }
        if (result.status === 'no dst write') {
          utils.bsAlert('Sorry, "Paste" is forbidden. You have only read access to the destination safe.');
          return;
        }

        if (result.status === 'Ok') {
          if ('file' in result.item) {
            const eItem = JSON.stringify(passhubCrypto.moveFile(result.item, this.getSafeById(srcSafe), this.getSafeById(dstSafe)));
            console.log(eItem);
            return this.moveItemFinalize(recordId, dstSafe, dstFolder, eItem, operation)
          } else {
            let pItem;
            return passhubCrypto.decryptAesKey(result.src_key)
              .then((srcAesKey) => {
                return passhubCrypto.decodeItem(result.item, srcAesKey);
              })
              .then((item) => {
                pItem = item;
                return passhubCrypto.decryptAesKey(result.dst_key);
              })
              .then((dstAesKey) => {
                return passhubCrypto.encryptItem(pItem, dstAesKey, { note: result.item.note });
              })
              .then(eItem => this.moveItemFinalize(recordId, dstSafe, dstFolder, eItem, operation));
          }
        }
        if (result.status === 'login') {
          window.location.href = 'index.php';
          return false;
        }
        alert(result.status);
        return false;
      },
    });
  },


  search(what) {
    const result = [];
    const lcWhat = what.toLowerCase();
    for (let s = 0; s < state.safes.length; s += 1) {
      if (state.safes[s].key) { // key!= null => confirmed, better have a class
        for (let i = 0; i < state.safes[s].items.length; i += 1) {
          let found = false;
          if (state.safes[s].items[i].cleartext[0].toLowerCase().indexOf(lcWhat) >= 0) {
            found = true;
          } else if (state.safes[s].items[i].cleartext[1].toLowerCase().indexOf(lcWhat) >= 0) {
            found = true;
          } else if (state.safes[s].items[i].cleartext[3].toLowerCase().indexOf(lcWhat) >= 0) {
            found = true;
          } else if (state.safes[s].items[i].cleartext[4].toLowerCase().indexOf(lcWhat) >= 0) {
            found = true;
          }
          if (found) {
            result.push(state.safes[s].items[i]);
          }
        }
      }
    }
    return result;
  },

  advise(url) {
    const u = new URL(url);
    let hostname = u.hostname.toLowerCase();
    if(hostname.substring(0,4) === 'www.') {
      hostname = hostname.substring(4);
    }
    const result = [];
    if(hostname){
      for (let s = 0; s < state.safes.length; s += 1) {
        if (state.safes[s].key) { // key!= null => confirmed, better have a class
          for (let i = 0; i < state.safes[s].items.length; i += 1) {
            try {
              let itemUrl = state.safes[s].items[i].cleartext[3].toLowerCase();
              if (itemUrl.substring(0,4) != 'http') {
                itemUrl = 'https://' + itemUrl;
              }

              itemUrl = new URL(itemUrl);
              let itemHost = itemUrl.hostname.toLowerCase();
              if(itemHost.substring(0,4) === 'www.') {
                itemHost = itemHost.substring(4);
              }
              if (itemHost == hostname) {
                result.push({ 
                    safe: state.safes[s].name,
                    title: state.safes[s].items[i].cleartext[0],
                    username: state.safes[s].items[i].cleartext[1],
                    password: state.safes[s].items[i].cleartext[2],
                });
              }
            } catch(err) {

            }
          }
        }
      }
    }
    // extensionInterface.sendAdvise(result);
    return result;
  },

  decodeKeys(ticket, ePrivateKey) {
    if (!window.location.href.includes('debug')) {
      progress.lock();
    }
    passhubCrypto.getPrivateKey(ePrivateKey, ticket)
      .then(() => this.decryptSafes(state.safes))
      .then(() => {
        state.safes.sort((a, b) => 
          a.name.toLowerCase().localeCompare(b.name.toLowerCase())
        );
        if(!state.currentSafe) {
          state.currentSafe = state.safes[0];
        }
        if (state.invitationAcceptPending || (state.currentSafe.key == null)) {
          setTimeout(getSharingStatus, 30 * 1000, this);
        }

//        if (typeof index_page_show_index != 'undefined') {
        if (false) {
          if (index_page_show_index) {
            let found = false;
            for (let s = 0; s < state.safes.length; s++) {
              for (let i = 0; i < state.safes[s].items.length; i++ ) {
                if (state.safes[s].items[i]._id == index_page_show_index) {
                  found = true;
                  if (typeof state.safes[s].items[i].folder === 'undefined') {
                    state.activeFolder = 0;
                  } else {
                    state.activeFolder = state.safes[s].items[i].folder;
                  }
//                  safes.setActiveFolder(state.activeFolder);
                }
              }
              if (!found) {
                for (let i = 0; i < state.safes[s].folders.length; i++ ) {
                  if (state.safes[s].folders[i]._id == index_page_show_index) {
                    found = true;
                    state.activeFolder = state.safes[s].folders[i]._id;
//                    safes.setActiveFolder(state.activeFolder);
                  }
                }
              }
            }
            index_page_show_index = false;
            safes.setActiveFolder(state.activeFolder);
            safes.showSafes();
            this.showTable();
            this.indexPageResize();
          }
        } else {
          safes.showSafes();
          this.showSafes(); // mobile: start with safes pane

          // safes.setActiveFolder(state.currentSafe.id);
          this.indexPageResize();
        }
        this.makeCurrentVaultVisible();

        document.querySelector('#search_string').value = '';
        document.querySelector('#search_string_xs').value = '';

        progress.unlock();
        return true;
      })
      .catch((err) => {
        if (window.location.href.includes('debug')) {
          alert(`387: ${err}`);
          return;
        }
        window.location.href = `error_page.php?js=387&error=${err}`;
      });
  },

  refreshUserData() {
    $.ajax({
      url: 'get_user_data.php',
      type: 'POST',
      data: {
        verifier: state.csrf,
      },

      error: () => {},
      success: (result) => {
        if (result.status === 'Ok') {
          state.safes = result.data.safes;
          state.publicKeyPem = result.data.publicKeyPem;
          this.decryptSafes(state.safes)
            .then(() => {
              state.safes.sort((a, b) => 
                a.name.toLowerCase().localeCompare(b.name.toLowerCase())
              );
              state.currentSafe = this.getSafeById(result.data.currentSafe);
              if (!state.currentSafe)  {
                state.currentSafe = state.safes[0];
              }  
              if (state.invitationAcceptPending || (state.currentSafe.key == null)) {
                setTimeout(getSharingStatus, 30 * 1000, this);
              }
    
              safes.showSafes();
              // safes.setActiveFolder(state.activeFolder);
              this.indexPageResize();
              this.makeCurrentVaultVisible();

              // check if
              if ($('#item_pane_title').is(':visible')) {
                const itemID = $('#item_pane_title+a').attr('data-record_nb');
                items.showItem(itemID);
              }
              progress.unlock();
              return true;
            });
        }
      },
    });
  },

  getUserData() {
    document.querySelector('#search_string').value = 'Search..';
    document.querySelector('#search_string_xs').value = 'Search..';

    state.csrf = document.getElementById('csrf').getAttribute('data-csrf');

    $.ajax({
      url: 'get_user_data.php',
      type: 'POST',
      data: {
        verifier: state.csrf,
      },
      error: () => {},
      success: (result) => {
        if (result.status === 'Ok') {
          state.safes = result.data.safes;
          state.invitationAcceptPending = result.data.invitation_accept_pending;
          state.currentSafe = this.getSafeById(result.data.currentSafe);
          state.publicKeyPem = result.data.publicKeyPem;
          state.shareModal = result.data.shareModal;
          this.decodeKeys(result.data.ticket, result.data.ePrivateKey);
          state.userMail = result.data.user_mail;
          if (result.data.onkeyremoval && WWPass.pluginPresent()) {
            WWPass.waitForRemoval().then(() => {
              window.location.href = 'logout.php';
            });
          }
          $(window).resize(this.indexPageResize);
          return;
        }
        window.location.href = `error_page.php?error=${result.status}`;
      },
    });
  },
};

