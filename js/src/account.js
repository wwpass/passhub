import $ from 'jquery';
import passhub from './passhub';

$('#account_modal').on('show.bs.modal', () => {
  $('#account_alert').text('').hide();
  $.ajax({
    url: 'account.php',
    method: 'POST',
    data: {
      verifier: passhub.csrf,
    },
    success: (result) => {
      if (result.status === 'Ok') {
        let data = '';
        if ('email' in result) {
          data += `<p>email: <b>${result.email}</b></p>`;
        }
        if ('plan' in result) {
          data += `<p>plan: ${result.plan}</p>`;
        }
        let recordsLine = `records: ${result.records}`;
        if ('maxRecords' in result) {
          recordsLine += ` out of ${result.maxRecords}`;
        }

        let storageLine = `storage: ${passhub.humanReadableFileSize(result.used)}`;
        if ('maxStorage' in result) {
          storageLine += ` out of ${passhub.humanReadableFileSize(result.maxStorage)}`;
        }
        data += `<p>${recordsLine}</p>`;
        data += `<p>${storageLine}</p>`;
        document.querySelector('#account_data').innerHTML = data;
        return;
      }
      if (result.status === 'login') {
        window.location.href = 'expired.php';
        return;
      }
      $('#account_alert').text(result.status).show();
    },
    error: (hdr, status, err) => {
      passhub.modalAjaxError($('#account_alert'), hdr, status, err);
    },
  });
  $('#account_alert').text('').hide();
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
