// Konfirmasi sebelum mengirim form yang punya atribut data-confirm
document.addEventListener('submit', function (e) {
  var msg = e.target.getAttribute('data-confirm');
  if (msg && !window.confirm(msg)) {
    e.preventDefault();
  }
});

// Tabel baris dinamis: tombol [data-add-row="#idTabel"] menyalin baris template
document.addEventListener('click', function (e) {
  var add = e.target.closest('[data-add-row]');
  if (add) {
    var table = document.querySelector(add.getAttribute('data-add-row'));
    var tpl = table.querySelector('template');
    table.querySelector('tbody').appendChild(tpl.content.cloneNode(true));
    return;
  }
  var del = e.target.closest('[data-remove-row]');
  if (del) {
    var tbody = del.closest('tbody');
    if (tbody.rows.length > 1) {
      del.closest('tr').remove();
    }
  }
});
