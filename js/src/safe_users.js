import $ from 'jquery';
import forge from 'node-forge';
import passhub from './passhub';
import passhubCrypto from './crypto';
import 'jquery-contextmenu';

let safeUsersUpdatePageReq = false;

function showUsers(result) {
  $('#UserList').empty();
  if (result.status === 'Ok') {
    if (result.update_page_req) {
      safeUsersUpdatePageReq = true;
    }
    const ul = result.UserList;
    if (ul.length === 0) {
      $('#UserList').append($('<li>').append('Safe not shared'));
      // error actually
    } else {
      const myselfIdx = ul.findIndex(element => element.myself);
      if (myselfIdx < 0) {
        // error
      }
      let li;
      if (!ul[myselfIdx].name) {
        li = `<div style="margin:12px 0;"><span class="vault_user_name">Safe is not shared</span></div>`;
      } else {
        li = `<div style="margin:12px 0;"><span class="vault_user_name"><b>${ul[myselfIdx].name}</b> (you)</span>`;
        const role = (ul[myselfIdx].role === 'administrator') ? 'admin' : ul[myselfIdx].role;
        li += `<span style='float:right; width: 10em; text-align: right;'><b>${role}</b?</span></div>`;
      }
      $('#UserList').append(li);
      for (let i = 0; i < ul.length; i++) {
        if (i === myselfIdx) {
          continue;
        }
        const role = (ul[i].role === 'administrator') ? 'admin' : ul[i].role;
        if (ul[myselfIdx].role === 'administrator') {
          li = `<div style="margin:12px 0;"><span class="vault_user_name">${ul[i].name}</span>`;
          li += '<div style="float:right">';
          if (ul[i].status == 0) {
            li += '<button class="btn btn-default btn-sm confirm_vault_user" style="font-size:16px;">Confirm</button>';
          } else {
            li += "<span class = 'role_selector'>"
                    + "<span class='caret' style='margin: 0 5px'></span>"
                    + `${role}</span>`;
            /*
            li += "<button class='btn btn-default btn-sm role_selector' type='button' 
                   style='font-size:16px; width: 7em; text-align: right;'>"
            + role + "<span class='caret' style='margin-left:5px'></span></button>";
            */
          }
          li += "<span class = 'del_user'>"
             + "Delete</span>";
          /*
          li += '<button class="btn btn-default btn-sm delete_vault_user" style="font-size:16px;">Delete</button>';
          */
          li += '</div>';

          li += '</div>';
        } else {
          li = `<div style="margin:12px 0;"><span class="vault_user_name">${ul[i].name}</span>`;
          li += `<span style='float:right; width: 10em; text-align: right;'>${role} </span></div>`;
        }
        $('#UserList').append(li);
      }
      $.contextMenu(roleMenu);
    }
    return;
  }
  if (result.status === 'login') {
    window.location.href = 'expired.php';
    return;
  }
  $('#safe_users_alert').text(result.status).show();
}


function setRole(elm, role) {
  $.ajax({
    url: 'safe_acl.php',
    method: 'POST',
    data: {
      verifier: passhub.csrf,
      vault: passhub.currentSafe.id,
      operation: 'role',
      name: elm.parent().parent().find('.vault_user_name')[0].innerText,
      role,
    },
    success: showUsers,
    error: (hdr, status, err) => {
      passhub.modalAjaxError($('#safe_users_alert'), hdr, status, err);
    },
  });
}

const roleMenu = {
  selector: '.role_selector',
  trigger: 'left',
  delay: 100,
  autoHide: true,
  items: {
    administrator: {
      name: 'admin',
      callback: function () {
        setRole($(this), 'administrator');
      },
    },
    readwrite: {
      name: 'editor',
      callback: function () {
        setRole($(this), 'editor');
      },
    },
    readonly: {
      name: 'readonly',
      callback: function () {
        setRole($(this), 'readonly');
      },
    },
  },
};

function confirmUserFinalize(username, eAesKey) {
  $.ajax({
    url: 'safe_acl.php',
    method: 'POST',
    data: {
      verifier: passhub.csrf,
      vault: passhub.currentSafe.id,
      operation: 'confirm',
      name: username,
      key: eAesKey,
    },
    success: showUsers,
    error: (hdr, status, err) => {
      passhub.modalAjaxError($('#safe_users_alert'), hdr, status, err);
    },
  });
}

// 'confirm user' button functionality
$('#UserList').on('click', '.confirm_vault_user', function () {
  const x = $(this).parent().parent().find('span');
  const name = x[0].innerText;
  $.ajax({
    url: 'safe_acl.php',
    method: 'POST',
    data: {
      verifier: passhub.csrf,
      vault: passhub.currentSafe.id,
      operation: 'get_public_key',
      name,
    },
    error: (hdr, status, err) => {
      passhub.modalAjaxError($('#safe_users_alert'), hdr, status, err);
    },
    success: (result) => {
      if (result.status === 'Ok') {
        return passhubCrypto.decryptAesKey(result.my_encrypted_aes_key)
          .then((aesKey) => {
            const peerPublicKey = forge.pki.publicKeyFromPem(result.public_key);
            const peerEncryptedAesKey = peerPublicKey.encrypt(aesKey, 'RSA-OAEP');
            const hexPeerEncryptedAesKey = forge.util.bytesToHex(peerEncryptedAesKey);
            confirmUserFinalize(name, hexPeerEncryptedAesKey);
          });
      }
      if (result.status === 'login') {
        window.location.href = 'expired.php';
        return;
      }
      $('#safe_users_alert').text(result.status).show();
    },
  });
});

$('#safeUsers').on('show.bs.modal', () => {
  $('#safeUsersLabel').find('span').text(passhub.currentSafe.name);
  safeUsersUpdatePageReq = false;
  $.ajax({
    url: 'safe_acl.php',
    method: 'POST',
    data: {
      verifier: passhub.csrf,
      vault: passhub.currentSafe.id,
    },
    success: showUsers,
    error: (hdr, status, err) => {
      passhub.modalAjaxError($('#safe_users_alert'), hdr, status, err);
    },
  });
  $('#safe_users_alert').text('').hide();
});

$('#safeUsers').on('hidden.bs.modal', () => {
  if (safeUsersUpdatePageReq) {
    window.location.href = `index.php?vault=${passhub.currentSafe.id}`;
  }
});


// 'delete user' button functionality
$('#UserList').on('click', '.delete_vault_user', function () {
  const x = $(this).parent().parent().find('span');
  $.ajax({
    url: 'safe_acl.php',
    method: 'POST',
    data: {
      verifier: passhub.csrf,
      vault: passhub.currentSafe.id,
      operation: 'delete',
      name: x[0].innerText,
    },
    success: showUsers,
    error: (hdr, status, err) => {
      passhub.modalAjaxError($('#safe_users_alert'), hdr, status, err);
    },
  });
});
