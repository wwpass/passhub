function openMailClient(to, subj, body) {
  const encodedBody = encodeURIComponent(body); 
  const link = `mailto:${to}?subject=${subj}&body=${encodedBody}`;
  $('#mailhref').attr('href', link);
  if (navigator.userAgent.match(/iPhone|iPod|iPad/i)) {
    $('#mailhref').attr('target', '_blank');
    window.open(link);
    return;
  }
  document.getElementById('mailhref').click();
}
export default { openMailClient };
