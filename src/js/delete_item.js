import $ from 'jquery';
import passhub from './passhub';

function deleteItem(item) {
  const recordNb = item.attr('data-record_nb');
  $('#id_deleteItem_button').attr('data-record_nb', recordNb);
  $('#title_span').text(item.attr('data-record_name'));
  $('#deleteItem').modal('show');
}

$('#deleteItem').on('show.bs.modal', () => {
  $('#deleteItem').find('.alert').text('').hide();
  $('#id_deleteItem_button').show();
});

$('#id_deleteItem_button').click(function () {
// $('#id_deleteItem_button').click(() => {
  $.ajax({
    url: 'delete.php',
    type: 'POST',
    data: {
      vault: passhub.currentSafe.id,
      verifier: passhub.csrf,
      id: $(this).attr('data-record_nb'),
    },
    error: (hdr, status, err) => {
      $('#id_deleteItem_button').hide();
      passhub.modalAjaxError($('#deleteItem').find('.alert'), hdr, status, err);
    },
    success: (result) => {
      if (result.status === 'Ok') {
        // window.location.href = `index.php?vault=${passhub.currentSafe.id}&folder=${passhub.activeFolder}`;
        $('#deleteItem').modal('hide');
        if ($('#item_pane_title').is(':visible')) {
          passhub.showTable();
        }
        passhub.refreshUserData();
        return;
      }
      if (result.status === 'login') {
        window.location.href = 'expired.php';
        return;
      }
      $('#id_deleteItem_button').hide();
      $('#deleteItem').find('.alert').html(result.status).show();
    },
  });
});

export default { deleteItem };
