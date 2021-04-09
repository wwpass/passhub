import $ from 'jquery';
// import progress from './progress';
// import passhubCrypto from './crypto';

import bsCustomFileInput from 'bs-custom-file-input';
import state from './state';
import safes from './safes';
import passhub from './passhub';
import { initFileForm } from './new_file';
import { initItemForm } from './item_form';
import * as extensionInterface from './extensionInterface';

import './accept_sharing';
import './create_safe';
import './rename_safe';
import './delete_safe';
import './safe_users';
import './share_by_mail';
import './share_safe';
import './folder_ops';
import './item_pane';
import './impex';
import './account';
import './timers';
// import './bind_second';


$('body').on('click', '.folder_back', () => {
  for (let i = 0; i < state.currentSafe.folders.length; i++) {
    if (state.currentSafe.folders[i]._id == state.activeFolder) {
      if(state.currentSafe.folders[i].parent == 0) {
        break;
      }
      safes.setActiveFolder(state.currentSafe.folders[i].parent);
      return;
    }
  }
  state.activeFolder = 0;
  passhub.showSafes();
});

$('body').on('click', '.item_back', () => {
  passhub.showTable();
});


passhub.indexPageResize();
passhub.getUserData();

$(document).ready(() => {
  bsCustomFileInput.init();
  initFileForm();
  initItemForm();
});


// import Modal
$('#import_form_fileinput_id').on('change', () => {
  $('#restore_alert').text('').hide();
});

extensionInterface.connect((s) => passhub.advise(s));

document.addEventListener("passhubExtInstalled", function(data) {
  console.log('got passhubExtInstalled')
  extensionInterface.connect((s) => passhub.advise(s));
});
