import $ from 'jquery';
import * as WWPass from 'wwpass-frontend';
import * as utils from './utils';
import progress from './progress';
import passhubCrypto from './crypto';
import safes from './safes';
import * as items from './items';

const vaultPaneColor = '#2277e6';


// const tablePaneColor = '#dae6f2';
const tablePaneColor = '#D9EFFF';

function getSharingStatus(ph) {
  $.ajax({
    url: 'get_sharing_status.php',
    type: 'GET',
    global: false,
    success: (result) => {
      if (result.status === 'Ok') {
        for (let i = 0; i < result.accepted.length; i++) {
          for (let s = 0; s < ph.safes.length; s++) {
            if (ph.safes[s].id == result.accepted[i]) {
              ph.safes[s].confirm_req = 1;
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
  searchMode: false,
  getSharingStatusTimer: null,
  safes: [],
  csrf: null,
  publicKeyPem: null,
  invitationAcceptPending: false,
  currentSafe: null,
  activeFolder: 0,
  userMail: null,
  shareModal: null,
  showTableReq: false,

  modalAjaxError: (alertElement, hdr, status, err) => {
    if (hdr.status === 0) {
      alertElement.text('You are offline. Please check your network.').show();
      return;
    }
    alertElement.text(`${status} ${err}`).show();
  },

  getSafeById(id) {
    for (let s = 0; s < this.safes.length; s += 1) {
      if (this.safes[s].id == id) {
        return this.safes[s];
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
      // url: `index.php?current_safe=${passhub.currentSafe.id}`,
      url: 'index.php',
      type: 'POST',
      data: {
        verifier: this.csrf,
        current_safe: this.currentSafe.id,
      },
      error: () => {},
      success: () => {},
    });
  },

  setActiveSafe(id) {
    this.currentSafe = this.getSafeById(id);
    this.activeFolder = 0;
    this.reportCurrentSafe();

    if (this.getSharingStatusTimer && !this.invitationAcceptPending && this.currentSafe.key) { // safe confirmed
      clearTimeout(this.getSharingStatusTimer);
      this.getSharingStatusTimer = null;
    } else if (!this.getSharingStatusTimer && (this.invitationAcceptPending || !this.currentSafe.key)) {
      getSharingStatus(this);
    }
  },

  itemViewNoteResize: () => {
    const showCredsNotesTop = $('#item_view_notes').offset().top;
//    $('#item_view_notes').height('calc(100vh - ' + parseInt(showCredsNotesTop + 150) + 'px)').height();
    $('#item_view_notes').css('max-height', 'calc(100vh - ' + parseInt(showCredsNotesTop + 150) + 'px)');
  },

  getItemById(id) {
    for (let s = 0; s < this.safes.length; s += 1) {
      for (let i = 0; i < this.safes[s].items.length; i += 1) {
        if (this.safes[s].items[i]._id === id) {
          return this.safes[s].items[i];
        }
      }
    }
    return null;
  },

  moveItemFinalize(recordID, dst_safe, dst_folder, item, operation) {
    $.ajax({
      url: 'move.php',
      type: 'POST',
      data: {
        id: recordID,
        src_safe: this.currentSafe.id,
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
          // window.location.href = `index.php?vault=${this.currentSafe.id}`;
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

  moveItem(recordId, srcSafe, dstSafe, dstFolder, operation) {
    $.ajax({
      url: 'move.php',
      type: 'POST',
      data: {
        id: recordId,
        src_safe: srcSafe,
        dst_safe: dstSafe,
        operation: 'get data',
      },
      error: (hdr, status, err) => {
        alert(`${status} ${err}`);
      },
      success: (result) => {
        if (result.status === 'Ok') {
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
    for (let s = 0; s < this.safes.length; s += 1) {
      if (this.safes[s].key) { // key!= null => confirmed, better have a class
        for (let i = 0; i < this.safes[s].items.length; i += 1) {
          let found = false;
          if (this.safes[s].items[i].cleartext[0].toLowerCase().indexOf(lcWhat) >= 0) {
            found = true;
          } else if (this.safes[s].items[i].cleartext[3].toLowerCase().indexOf(lcWhat) >= 0) {
            found = true;
          } else if (this.safes[s].items[i].cleartext[4].toLowerCase().indexOf(lcWhat) >= 0) {
            found = true;
          }
          if (found) {
            result.push(this.safes[s].items[i]);
          }
        }
      }
    }
    return result;
  },

  decodeKeys(ticket, ePrivateKey) {
    if (!window.location.href.includes('debug')) {
      progress.lock();
    }
    passhubCrypto.getPrivateKey(ePrivateKey, ticket)
      .then(() => this.decryptSafes(this.safes))
      .then(() => {
        if (typeof index_page_show_index != 'undefined') {
          if (index_page_show_index) {
            let found = false;
            for (let s = 0; s < this.safes.length; s++) {
              for (let i = 0; i < this.safes[s].items.length; i++ ) {
                if (this.safes[s].items[i]._id == index_page_show_index) {
                  found = true;
                  if (typeof this.safes[s].items[i].folder === 'undefined') {
                    this.activeFolder = 0;
                  } else {
                    this.activeFolder = this.safes[s].items[i].folder;
                  }
//                  safes.setActiveFolder(this.activeFolder);
                }
              }
              if (!found) {
                for (let i = 0; i < this.safes[s].folders.length; i++ ) {
                  if (this.safes[s].folders[i]._id == index_page_show_index) {
                    found = true;
                    this.activeFolder = this.safes[s].folders[i]._id;
//                    safes.setActiveFolder(this.activeFolder);
                  }
                }
              }
            }
            index_page_show_index = false;
            safes.setActiveFolder(this.activeFolder);
            this.showTable();
            this.indexPageResize();
          }
        } else {
          safes.setActiveFolder(this.activeFolder);
          this.indexPageResize();
        }
        this.makeCurrentVaultVisible();
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
        verifier: this.csrf,
      },

      error: () => {},
      success: (result) => {
        if (result.status === 'Ok') {
          this.safes = result.data.safes;
          // passhub.safes.sort(cmpSafeNames);
          this.currentSafe = this.getSafeById(result.data.currentSafe);
          this.publicKeyPem = result.data.publicKeyPem;
          this.decryptSafes(this.safes)
            .then(() => {
              safes.setActiveFolder(this.activeFolder);
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
    this.csrf = document.getElementById('csrf').getAttribute('data-csrf');

    $.ajax({
      url: 'get_user_data.php',
      type: 'POST',
      data: {
        verifier: this.csrf,
      },
      error: () => {},
      success: (result) => {
        if (result.status === 'Ok') {
          this.safes = result.data.safes;
          this.invitationAcceptPending = result.data.invitation_accept_pending;
          this.currentSafe = this.getSafeById(result.data.currentSafe);
          if (this.invitationAcceptPending || (this.currentSafe.key == null)) {
            setTimeout(getSharingStatus, 30 * 1000, this);
          }
          this.publicKeyPem = result.data.publicKeyPem;
          this.shareModal = result.data.shareModal;
          this.decodeKeys(result.data.ticket, result.data.ePrivateKey);
          this.userMail = result.data.user_mail;
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

  humanReadableFileSize(size) {
    if (size < 1024) return `${size} B`;
    const i = Math.floor(Math.log(size) / Math.log(1024));
    let num = (size / Math.pow(1024, i));
    const round = Math.round(num);
    num = round < 10 ? num.toFixed(2) : round < 100 ? num.toFixed(1) : round
    return `${num} ${'KMGTPEZY'[i-1]}B`;
  },
};
