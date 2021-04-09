import $ from 'jquery';
import 'jquery-contextmenu';
import axios from 'axios';

// import openmailclient from './openmailclient';
// import './account';
import './timers';

let verifier = document.getElementById('csrf').getAttribute('data-csrf');

function cmp(o1, o2) {
  const u1 = o1.email.toUpperCase();
  const u2 = o2.email.toUpperCase();
  if (u1 < u2) {
    return -1;
  }
  if (u1 > u2) {
    return 1;
  }
  return 0;
}

function userTable(data) {
  const { timeZone } = Intl.DateTimeFormat().resolvedOptions();
  $('#th_seen').html(`Seen<br> (${timeZone})`);

  $('#stats').text(data.stats);

  let {users} = data;

  users.sort(cmp);

  const userTableBody = document.querySelector('#userTableBody');
  while (userTableBody.firstChild) {
    userTableBody.removeChild(userTableBody.firstChild);
  }

  for (let u = 0; u < users.length; u++) {
    if (!users[u].email) {
      users[u].email = '-';
    }

    let role = 'active';
    if(users[u].disabled) {
      role = 'disabled';
    } else if(users[u].site_admin) {
      role = 'admin';
    } else if(!users[u]._id) {
      role = 'invited';
    }

    let row = `<tr data-email = ${users[u].email} data-id = ${users[u]._id}>`;
    if (users[u]._id == data.me) {
      row = '<tr style="font-weight:bold">'
      row += '<td></td>';
      row += `<td style="text-align:center;">${role}</td>`; 
      row += `<td><b>${users[u].email}</b></td>`;
      //row += `<td><b>${users[u]._id}</b></td>`;
      row += `<td><b>${users[u].safe_cnt}</b></td>`;
      row += `<td><b>${users[u].shared_safe_cnt}</b></td><td><b>That's You</b></td></tr>`;
    } else {
      row += `<td class="delete_user" style="cursor:pointer">
      <svg style='stroke-width:0; fill: red' width='24' height='24'><use xlink:href='public/img/SVG2/sprite.svg#cross'></use></svg>
      </td>`;

      if(role == 'invited') {
        row += `<td style="text-align:center;">authorized</td>`; 
        row += `<td>${users[u].email}</td>`;
        row += '<td></td><td></td><td></td>';
      } else {
        row += `<td style="text-align:center;"><span class='user_status_selector dropdown-toggle'>${role}</span></td>`; 
        row += `<td>${users[u].email}</td>`;
        // row += `<td>${users[u]._id}</td>`;
        row += `<td>${users[u].safe_cnt}</td>`;
        const seen = new Date(users[u].lastSeen).toLocaleString();
        row += `<td>${users[u].shared_safe_cnt}</td><td>${seen}</td></tr>`;
        }
    }
    $('#userTableBody').append(row);
  }
  $('#newUserMail').val('');
}

function getPageData() {

  axios.post('iam.php', {
      verifier,
      operation: 'users'
  }).then((result) => {
    console.log(result);
    if (result.data.status === 'Ok') {
      userTable(result.data);
      return;
    }
    if (result.data.status === 'login') {
      window.location.href = 'expired.php';
      return;
    }
  })
  .catch((error) =>{console.log(error)})
}  

setTimeout(function() {
  $('#newUserMail').val('');
}, 1000)
getPageData();

$('#userTableBody').on('click', '.delete_user', function () {
  const tr = $(this)[0].closest('tr');
  let {email, id} = tr.dataset;
  $('#user_mail').text(email);
  if( !id ||  (id == "undefined")) {
    id ='';
  }
  $('#user_id').text(id);
  $('#deleteUserModal').modal('show');
});

$('#deleteUserModal').on('show.bs.modal', () => {
  $('#delete_user_alert').text('').hide();
});

$('#deleteUserBtn').on('click', function() {
  axios.post('iam.php', {
      verifier,
      operation: 'delete',
      id: $('#user_id').text(),
      email: $('#user_mail').text(),
  })
  .then( (result) => {
    if (result.data.status === 'Ok') {
      $('#deleteUserModal').modal('hide');
      getPageData();
      return;
    }
    if (result.data.status === 'login') {
      window.location.href = 'expired.php';
      return;
    }
    $('#delete_user_alert').html(result.data.status).show();
  })
  .catch((error) => {
    alert(error);
  });
});

function submitNewUser() {  
  const email = $('#newUserMail').val().trim();
  const re = /\S+@\S+\.\S+/;

  if (!re.test(email)) {
    $('#inviteByMailAlert').text('Please provide a valid email address').show();
    return;
  }
  axios.post('iam.php',
    {
      verifier,
      operation: "newuser",
      email,
  })
  .then((result) =>{
    if (result.data.status === 'Ok') {
      getPageData();
      return;
    }
    if (result.data.status === 'login') {
      window.location.href = 'expired.php';
      return;
    }
    $('#inviteByMailAlert').html(result.data.status).show();

  })
  .catch((error) => {
    alert(error);
  })
};

$('#SubmitNewUserMail').on('click', submitNewUser);

$('#newUserMail').on('keydown', function(e) {
  $('#inviteByMailAlert').text('').hide();
  if(e.key == "Enter") {
    submitNewUser();
  }
});  

$('#newUserMail').on('focusin', () => {
  $('#inviteByMailAlert').text('').hide();
});

function setStatus(jQitem, operation) {
  const tr = jQitem[0].closest('tr');
  const { email, id} = tr.dataset;
  axios.post('iam.php',
    {
      verifier,
      operation,
      id,
      email,
  })
  .then((result) => {
    if (result.data.status === 'Ok') {
      getPageData();
      return;
    }
    if (result.data.status === 'login') {
      window.location.href = 'expired.php';
      return;
    }
    alert(result.data.status);
  })
  .catch((error) => {
    alert(error);
  })
}

const roleMenu = {
  selector: '.user_status_selector',
  trigger: 'left',
  delay: 100,
  autoHide: true,
  items: {
    active: {
      name: 'active',
      callback: function () {
        setStatus($(this), 'active');
      },
    },
    disabled: {
      name: 'disabled',
      callback: function () {
        setStatus($(this), 'disabled');
      },
    },
    admin: {
      name: 'admin',
      callback: function () {
        setStatus($(this), 'admin');
      },
    },
  },
};

$.contextMenu(roleMenu);
