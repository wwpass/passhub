import $ from 'jquery';
import 'jquery-contextmenu';
import { modalAjaxError, humanReadableFileSize } from './utils';
import state from './state';
//

$('#account_modal').on('show.bs.modal', () => {
  $('#account_alert').text('').hide();
  $.ajax({
    url: 'account.php',
    method: 'POST',
    data: {
      verifier: state.csrf,
    },
    success: (result) => {
      if (result.status === 'Ok') {
        let data = '';
        if ('email' in result) {
          data += `<div style="margin:1em 0">email: <b>${result.email}</b>
          <a href="change_mail.php" style="float: right;">
            Change email
          </a>
          </div>`;
        } else {
          data += `<div style="margin:1em 0">
          <a href="change_mail.php?add">
            Add email address
          </a>
          </div>`;

        }
        if ('plan' in result) {
          data += `<p>Account type: <b>${result.plan}</b></p>`;
        }
        if ('expires' in result) {
          let { expires } = result;
          if (expires !== 'never') {
            expires = new Date(expires);
            expires = expires.toLocaleDateString();
          }
          data += `<p>Expires: <b>${expires}</b></p>`;
        }
        let recordsLine = `records: ${result.records}`;
        if ('maxRecords' in result) {
          recordsLine += ` out of ${result.maxRecords}`;
        }

        let storageLine = `storage: ${humanReadableFileSize(result.used)}`;
        if ('maxStorage' in result) {
          storageLine += ` out of ${humanReadableFileSize(result.maxStorage)}`;
        }
        data += `<p>${recordsLine}</p>`;
        data += `<p>${storageLine}</p>`;
        document.querySelector('#account_data').innerHTML = data;

        if ('upgrade_button' in result) {
          document.querySelector('#upgrade').style.display = 'block';
        } else {
          document.querySelector('#upgrade').style.display = 'none';
        }

        return;
      }
      if (result.status === 'login') {
        window.location.href = 'expired.php';
        return;
      }
      $('#account_alert').text(result.status).show();
    },
    error: (hdr, status, err) => {
      modalAjaxError($('#account_alert'), hdr, status, err);
    },
  });
  $('#account_alert').text('').hide();
});


$('#delete_my_account').click(() => {
  window.location = 'close_account.php';
});

const accountMenu = {
  selector: '#account_button',
  trigger: 'left',
  delay: 100,
  autoHide: true,
  zIndex: 10,
  className: 'contextmenu-customheight',
  items: {
    entry: {
      name: 'My Account',
      callback: () => {
        $('#account_modal').modal('show');
      }
    },
    note: {
      name: 'Logout',
      callback: () => {
        window.location.href = 'logout.php';
      }
    },
  },
};

$.contextMenu(accountMenu);
