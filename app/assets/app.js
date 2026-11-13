// Modal konfirmasi modern menggantikan alert window.confirm() bawaan browser
(function () {
  var modalEl = null;
  var backdropEl = null;
  var pendingForm = null;
  var pendingSubmitter = null;

  function closeModal() {
    if (modalEl) {
      modalEl.classList.remove('show');
      modalEl.style.display = 'none';
    }
    if (backdropEl) {
      backdropEl.classList.remove('show');
      backdropEl.remove();
      backdropEl = null;
    }
    document.body.classList.remove('modal-open');
    pendingForm = null;
    pendingSubmitter = null;
  }

  function buatModal() {
    if (document.getElementById('customConfirmModal')) return;
    var wrapper = document.createElement('div');
    wrapper.innerHTML = [
      '<div class="modal fade" id="customConfirmModal" tabindex="-1" style="display:none; z-index: 1055;" aria-hidden="true">',
      '  <div class="modal-dialog modal-dialog-centered" style="max-width: 380px; margin: auto;">',
      '    <div class="modal-content border-0 shadow-lg text-center p-4" style="border-radius: 20px; background: #fff;">',
      '      <div id="ccmIcon" class="mb-3"></div>',
      '      <h5 class="fw-bold mb-2 text-dark" id="ccmTitle">Konfirmasi</h5>',
      '      <p class="text-secondary mb-4 small" id="ccmMsg" style="font-size: 0.95rem; line-height: 1.5;"></p>',
      '      <div class="d-flex justify-content-center gap-2">',
      '        <button type="button" class="btn btn-light border px-4 py-2 text-muted fw-semibold flex-fill" id="ccmBtnBatal" style="border-radius: 10px;">Batal</button>',
      '        <button type="button" class="btn px-4 py-2 fw-semibold text-white flex-fill" id="ccmBtnYa" style="border-radius: 10px;">Ya, Lanjutkan</button>',
      '      </div>',
      '    </div>',
      '  </div>',
      '</div>'
    ].join('\n');
    document.body.appendChild(wrapper.firstElementChild);
    modalEl = document.getElementById('customConfirmModal');

    document.getElementById('ccmBtnBatal').addEventListener('click', closeModal);
    modalEl.addEventListener('click', function (e) {
      if (e.target === modalEl) closeModal();
    });

    document.getElementById('ccmBtnYa').addEventListener('click', function () {
      if (!pendingForm) {
        closeModal();
        return;
      }
      var f = pendingForm;
      var sub = pendingSubmitter;
      closeModal();

      f._modalConfirmed = true;
      if (sub && sub.name && sub.value) {
        var hidden = document.createElement('input');
        hidden.type = 'hidden';
        hidden.name = sub.name;
        hidden.value = sub.value;
        f.appendChild(hidden);
      }
      f.submit();
    });
  }

  function showConfirm(form, submitter, msg) {
    buatModal();
    pendingForm = form;
    pendingSubmitter = submitter;

    var iconEl = document.getElementById('ccmIcon');
    var titleEl = document.getElementById('ccmTitle');
    var msgEl = document.getElementById('ccmMsg');
    var btnYa = document.getElementById('ccmBtnYa');

    msgEl.textContent = msg;

    var isDanger = /batal|hapus|tolak|delete|cancel/i.test(msg) ||
                   (submitter && /btn-danger|btn-outline-danger/.test(submitter.className));

    if (isDanger) {
      iconEl.innerHTML = '<div style="width: 64px; height: 64px; margin: 0 auto; background: #fee4e2; border-radius: 50%; display: flex; align-items: center; justify-content: center;"><i class="bi bi-exclamation-triangle-fill text-danger" style="font-size: 1.85rem;"></i></div>';
      titleEl.textContent = /hapus|delete/i.test(msg) ? 'Hapus Data?' : (/tolak/i.test(msg) ? 'Tolak Pengajuan?' : 'Batalkan Pesanan?');
      btnYa.className = 'btn btn-danger px-4 py-2 fw-semibold text-white flex-fill';
      btnYa.textContent = /hapus/i.test(msg) ? 'Ya, Hapus' : (/tolak/i.test(msg) ? 'Ya, Tolak' : 'Ya, Batalkan');
    } else {
      iconEl.innerHTML = '<div style="width: 64px; height: 64px; margin: 0 auto; background: #e0f2fe; border-radius: 50%; display: flex; align-items: center; justify-content: center;"><i class="bi bi-question-circle-fill text-primary" style="font-size: 1.85rem;"></i></div>';
      titleEl.textContent = 'Konfirmasi';
      btnYa.className = 'btn btn-dark px-4 py-2 fw-semibold text-white flex-fill';
      btnYa.textContent = 'Ya, Lanjutkan';
    }

    // Tampilkan backdrop
    backdropEl = document.createElement('div');
    backdropEl.className = 'modal-backdrop fade show';
    backdropEl.style.backdropFilter = 'blur(3px)';
    backdropEl.style.zIndex = '1050';
    document.body.appendChild(backdropEl);
    document.body.classList.add('modal-open');

    // Tampilkan modal
    modalEl.style.display = 'block';
    setTimeout(function () {
      modalEl.classList.add('show');
    }, 10);
  }

  // Intercept form submit
  document.addEventListener('submit', function (e) {
    var form = e.target;
    if (form._modalConfirmed) {
      delete form._modalConfirmed;
      return;
    }
    var submitter = e.submitter || null;
    var msg = (submitter && submitter.getAttribute('data-confirm')) || form.getAttribute('data-confirm');
    if (!msg) return;

    e.preventDefault();
    e.stopPropagation();
    showConfirm(form, submitter, msg);
  }, true);

  // Fallback: intercept click pada elemen ber-data-confirm
  document.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-confirm]');
    if (!btn) return;
    var form = btn.closest('form');
    if (!form || form._modalConfirmed) return;

    var msg = btn.getAttribute('data-confirm') || form.getAttribute('data-confirm');
    if (!msg) return;

    e.preventDefault();
    e.stopPropagation();
    showConfirm(form, btn, msg);
  }, true);

  // Matikan window.confirm agar tidak pernah muncul popup browser native
  window.confirm = function () {
    return false;
  };
})();

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
