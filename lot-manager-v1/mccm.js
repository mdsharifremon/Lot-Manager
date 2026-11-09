function mccm_show(i) {
  jQuery('#mccmextra_' + i).fadeIn('fast');
}

function mccm_hide(i) {
  jQuery('#mccmextra_' + i).fadeOut('fast');
}

function validateWO() {
  var wo=jQuery('#wo').val();
  var workorder=jQuery('#work').val();
  var wodate=jQuery('#wodate').val();
  if (wo=='' || work=='' || wodate=='') {
    alert("You must include a workorder number, date, and description of work!");
    return false;
}
  else return true;

}