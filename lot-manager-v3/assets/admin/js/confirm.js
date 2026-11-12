/* Shared confirm box. Add data-mccm-confirm="message" to a link to ask first;
   scripts call mccmConfirm({title, message, button, danger}, onOk). */
(function () {
  var dlg;
  function build() {
    dlg = document.createElement('dialog');
    dlg.innerHTML = '<form method="dialog"><h2 class="mccm-dlg-t"></h2><p class="mccm-dlg-m"></p>' +
      '<div class="mccm-dlg-b"><button value="no" class="mccm-dlg-no">Cancel</button>' +
      '<button value="ok" class="mccm-dlg-yes"></button></div></form>';
    dlg.addEventListener('click', function (e) { if (e.target === dlg) dlg.close(); });
    document.body.appendChild(dlg);
  }
  window.mccmConfirm = function (o, ok) {
    if (!dlg) build();
    dlg.className = 'mccm-dlg' + (o.danger ? ' is-danger' : '');
    dlg.querySelector('.mccm-dlg-t').textContent = o.title || 'Are you sure?';
    dlg.querySelector('.mccm-dlg-m').textContent = o.message || '';
    dlg.querySelector('.mccm-dlg-yes').textContent = o.button || 'Continue';
    dlg.returnValue = '';
    dlg.onclose = function () { if (dlg.returnValue === 'ok') ok(); };
    dlg.showModal();
    dlg.querySelector('.mccm-dlg-no').focus();
  };
  document.addEventListener('click', function (e) {
    var a = e.target.closest && e.target.closest('a[data-mccm-confirm]');
    if (!a) return;
    e.preventDefault();
    window.mccmConfirm({
      title: a.dataset.mccmTitle, message: a.dataset.mccmConfirm,
      button: a.dataset.mccmBtn, danger: a.dataset.mccmDanger
    }, function () { window.location.href = a.href; });
  });
})();
