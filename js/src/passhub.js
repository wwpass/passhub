import $ from 'jquery';
import * as WWPass from 'wwpass-frontend';
import * as utils from './utils';
import progress from './progress';
import passhubCrypto from './crypto';
import safes from './safes';

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
  
/*
  init(params) {
    //  { csrf, publicKeyPem, safes, shareModal } = params;
    this.csrf = params.csrf;
    this.publicKeyPem = params.publicKeyPem;
    this.safes = params.safes;
    this.invitationAcceptPending = params.invitation_accept_pending;
    this.currentSafe = this.getSafeById(params.current_safe);
    this.userMail = params.user_mail;
    this.activeFolder = params.active_folder;
    this.shareModal = params.shareModal;
    this.showTableReq = params.show_table;
  },
*/

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
        if ((safe.items.length > 0) || (safe.folders.length > 0)) {
          promises.push(passhubCrypto.decryptAesKey(safe.key)
            .then(bstringKey => this.decryptSafeData(bstringKey, safe)));
        }
      }
    }
    return Promise.all(promises);
  },


  indexPageResize() {
    const e = document.querySelector('#probe');
    document.getElementsByTagName('body')[0].appendChild(e);

    const { bottom } = e.getBoundingClientRect();
    const index_page_row_top = document.querySelector('#index_page_row').getBoundingClientRect().top;
    const records_table_top = document.querySelector('.records_table').getBoundingClientRect().top;

    const t0 = utils.isXs() ? 2 : 30;
    const index_page_row_height = bottom - index_page_row_top - t0; 
    const records_table_height = bottom - records_table_top - t0;
    const confirmed_safe_buttons_height = $('.confirmed_safe_buttons').outerHeight();

    $('#index_page_row').height(index_page_row_height);

    const vaultsMaxHeight = index_page_row_height - $('#vlist_controls').outerHeight();

    $('.vaults_scroll_control').css('max-height', vaultsMaxHeight);

    let itemsMaxHeight = records_table_height - confirmed_safe_buttons_height;
    if (this.searchMode) {
      itemsMaxHeight = records_table_height;
    }

    $('.table_scroll_control').css('max-height', itemsMaxHeight);
  },

  /*
  // not used anymore, keep for reference
  indexPageResize1() {
    const { bottom } = document.querySelector('#probe').getBoundingClientRect();

    if (utils.isXs()) {
      if (screen.height < 400) { // iPhone 5
      } else if (
        navigator.userAgent.match(/Android/) 
        && (screen.width == 360) 
        && (screen.height == 640)) 
      { // HTC
      } else {
        try {
          document.body.style.fontSize = '18px';
        } catch (err) {
          // for some reason get document.body  null;
        }
        $('.btn').css('font-size', '18px');
        $('.list-item-vault').addClass('list-item-xs');
        $('.list-item-vault-active').addClass('list-item-xs');
      }
      $('#table_pane_body').removeClass('right_radius');
      $('#vault_list').removeClass('left_radius');
      if ($('#vault_list').is(':visible')) {
        $('body').css('background-color', vaultPaneColor);
      } else if ($('#table_pane_body').is(':visible')) {
        $('body').css('background-color', tablePaneColor);
      } else {
        $('body').css('background-color', 'white');
      }
    } else {
      document.body.style.fontSize = '16px';
      $('.list-item-vault').removeClass('list-item-xs');
      $('.list-item-vault-active').removeClass('list-item-xs');
      // $('.btn').css('font-size', '14px');
      $('#table_pane_body').addClass('right_radius');
      $('#vault_list').addClass('left_radius');
      $('#index_page_row').addClass('is-table-row');
      $('body').css('background-color', 'white');
    }
    try {
      let t0 = $('.is-table-row').position().top;
      t0 += utils.isXs() ? 2 : 30;
      $('.is-table-row').height('calc(100vh - ' + t0 + 'px)').height();
    } catch (err) {
      alert(err);
    }
  
    const itrHeight = $('#index_page_row').height();
    $('#table_pane_body').height(itrHeight - $('#table_pane_nav').outerHeight());
    let vaultsMaxHeight = itrHeight - $('#vlist_controls').height() - 25 - 20;
    let itemsMaxHeight = $('#table_pane_body').height() - $('.confirmed_safe_buttons').outerHeight();

    if (navigator.userAgent.match(/iPhone/) && (screen.width === 375) && (screen.height === 812)) { // iPhone X
      vaultsMaxHeight -= 140;
      itemsMaxHeight -= 140;
    } else if (navigator.userAgent.match(/iPhone/) && (screen.width === 375) && (screen.height === 667)) { // iPhone 6
      vaultsMaxHeight -= 100;
      itemsMaxHeight -= 100;
    } else if (navigator.userAgent.match(/iPhone/) && ((screen.height === 320) || (screen.width === 320))) { // iPhone 5
      vaultsMaxHeight -= 80;
      itemsMaxHeight -= 80;
    } else if (navigator.userAgent.match(/iPhone|iPod|iPad|Android/i)) {
      vaultsMaxHeight -= 80;
      itemsMaxHeight -= 80;
    }

    $('.vaults_scroll_control').css('max-height', vaultsMaxHeight);
    $('.table_scroll_control').css('max-height', itemsMaxHeight);
  },
  */

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
/*    
    if (utils.isXs()) {
      $('body').css('background-color', tablePaneColor);
    }
*/
    this.indexPageResize();
  },

  showSafes() {
    $('.table_pane').addClass('d-none');
    $('.vaults_pane').removeClass('d-none');
    $('.item_pane').addClass('d-none');
/*
    if (utils.isXs()) {
      $('body').css('background-color', vaultPaneColor);
    }
*/
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
    $('#item_view_notes').height('calc(100vh - ' + parseInt(showCredsNotesTop + 150) + 'px)').height();
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
        safes.setActiveFolder(this.activeFolder);
        this.indexPageResize();
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
        csrf: this.csrf,
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
              progress.unlock();
              return true;
            });
        }
      },
    });
  },

  getUserData() {
    this.csrf = document.getElementById('csrf').getAttribute('data-csrf');
    const t = document.querySelectorAll('[data-folder]');
    if (t.length === 1) {
      this.activeFolder = t[0].getAttribute('data-folder');
    }

    $.ajax({
      url: 'get_user_data.php',
      type: 'POST',
      data: {
        csrf: this.csrf,
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

    /*
    + this.csrf = params.csrf;
    + this.publicKeyPem = params.publicKeyPem;
    + this.safes = params.safes;
    this.invitationAcceptPending = params.invitation_accept_pending;
    + this.currentSafe = this.getSafeById(params.current_safe);
    + this.userMail = params.user_mail;
    this.activeFolder = params.active_folder;
    + this.shareModal = params.shareModal;
    this.showTableReq = params.show_table;
    */

    /*
    fetch('get_user_data.php', {
      credentials: 'same-origin',
    })
      .then(response => response.json())
      .then((myJson) => {
        // $('#log_msg').text(JSON.stringify(myJson));
        this.csrf = myJson.data.csrfToken;
        this.safes = myJson.data.safes;
        // passhub.safes.sort(cmpSafeNames);
        this.currentSafe = this.getSafeById(myJson.data.currentSafe);
        this.publicKeyPem = myJson.data.publicKeyPem;
        this.decodeKeys(myJson.data.ticket, myJson.data.ePrivateKey);
        $(window).resize(this.indexPageResize);
      });
    */
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
