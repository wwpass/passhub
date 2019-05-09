import $ from 'jquery';
// import progress from './progress';
// import passhubCrypto from './crypto';
import safes from './safes';
import passhub from './passhub';

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

passhub.getUserData();


/*
if (passhub.invitation_accept_pending || (passhub.current_safe.key ==null)) {
  setTimeout(getSharingStatus, 30 * 1000);
}

if (passhub.show_table_req) {
  passhub.show_table();
}

$(document).ready(function() {
  $(window).resize(passhub.index_page_resize);
});
*/
