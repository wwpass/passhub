import $ from 'jquery';
// import progress from './progress';
// import passhubCrypto from './crypto';

import bsCustomFileInput from 'bs-custom-file-input';
import safes from './safes';
import passhub from './passhub';
import { initFileForm } from './new_file';
import { initItemForm } from './item_form';

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


$('body').on('click', '.folder_back', () => {
  for (let i = 0; i < passhub.currentSafe.folders.length; i++) {
    if (passhub.currentSafe.folders[i]._id == passhub.activeFolder) {
      safes.setActiveFolder(passhub.currentSafe.folders[i].parent);
      return;
    }
  }
  passhub.activeFolder = 0;
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
