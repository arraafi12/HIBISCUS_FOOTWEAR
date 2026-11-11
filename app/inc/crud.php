<?php
/**
 * Halaman CRUD generik untuk tabel master sederhana.
 *
 * Definisi:
 *  table, pk, title, active        : nama tabel, primary key, judul, menu aktif
 *  fields  => [kolom => [label, type, required, options, step, help, default]]
 *             type: text | textarea | number | email | select | checkbox | password
 *             type password disimpan sebagai hash ke kolom 'column' (default password_hash)
 *  columns => [kolom => [label, format]]   format: rupiah | angka | bool | callable
 *  list_sql, order, search (daftar kolom yang dicari), row_actions (callable)
 */

function crud_page(array $d): void
{
    $table = $d['table'];
    $pk    = $d['pk'];
    $self  = basename($_SERVER['SCRIPT_NAME']);

    // ----------------------------- POST -----------------------------
    if (is_post()) {
        require_admin();
        csrf_check();
        $action = post('action');
        $id     = post('id');

        if ($action === 'delete') {
            try {
                q("DELETE FROM $table WHERE $pk = ?", [$id]);
                flash('success', $d['title'] . ' berhasil dihapus.');
            } catch (PDOException $e) {
                flash('danger', db_error($e), 'Gagal menghapus ' . strtolower($d['title']));
            }
            redirect('admin/' . $self);
        }

        // simpan (tambah / ubah)
        $data   = [];
        $errors = [];
        foreach ($d['fields'] as $col => $f) {
            $type = $f['type'] ?? 'text';
            if ($type === 'checkbox') {
                $data[$col] = isset($_POST[$col]) ? 1 : 0;
                continue;
            }
            $v = post($col);
            if ($type === 'password') {
                if ($v === '' && $id === '' && !empty($f['required'])) {
                    $errors[] = $f['label'] . ' wajib diisi.';
                } elseif ($v !== '') {
                    if (strlen($v) < 6) {
                        $errors[] = $f['label'] . ' minimal 6 karakter.';
                    }
                    $data[$f['column'] ?? 'password_hash'] = password_hash($v, PASSWORD_DEFAULT);
                }
                continue;
            }
            if ($v === '' && !empty($f['required'])) {
                $errors[] = $f['label'] . ' wajib diisi.';
            }
            if ($type === 'email' && $v !== '' && !filter_var($v, FILTER_VALIDATE_EMAIL)) {
                $errors[] = $f['label'] . ' tidak valid.';
            }
            $data[$col] = $v === '' ? null : $v;
        }

        if ($errors) {
            flash('danger', implode(' ', $errors), 'Gagal menyimpan ' . strtolower($d['title']));
            $_SESSION['old'] = $_POST;
            redirect('admin/' . $self . ($id !== '' ? '?edit=' . urlencode($id) : '?form=1'));
        }

        try {
            if ($id === '') {
                $cols = array_keys($data);
                q("INSERT INTO $table (" . implode(',', $cols) . ') VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')',
                  array_values($data));
                flash('success', $d['title'] . ' berhasil ditambahkan.');
            } else {
                $set = implode(',', array_map(fn($c) => "$c = ?", array_keys($data)));
                q("UPDATE $table SET $set WHERE $pk = ?", [...array_values($data), $id]);
                flash('success', $d['title'] . ' berhasil diperbarui.');
            }
        } catch (PDOException $e) {
            flash('danger', db_error($e), 'Gagal menyimpan ' . strtolower($d['title']));
            $_SESSION['old'] = $_POST;
            redirect('admin/' . $self . ($id !== '' ? '?edit=' . urlencode($id) : '?form=1'));
        }
        redirect('admin/' . $self);
    }

    // ----------------------------- GET ------------------------------
    admin_header($d['title'], $d['active']);

    $editId = get('edit');
    if ($editId !== '' || get('form') !== '') {
        crud_form($d, $editId);
    } else {
        crud_list($d);
    }
    admin_footer();
}

function crud_list(array $d): void
{
    $table = $d['table'];
    $pk    = $d['pk'];
    $cari  = get('q');

    $sql    = $d['list_sql'] ?? "SELECT * FROM $table";
    $params = [];
    if ($cari !== '' && !empty($d['search'])) {
        $sql = "SELECT * FROM ($sql) t WHERE " . implode(' OR ', array_map(fn($c) => "$c LIKE ?", $d['search']));
        $params = array_fill(0, count($d['search']), "%$cari%");
    }
    $sql .= ' ORDER BY ' . ($d['order'] ?? $pk);
    $data = rows($sql, $params);

    page_title($d['title'], '<a class="btn btn-hibiscus" href="?form=1">Tambah</a>');
    ?>
    <div class="card">
      <div class="card-body">
        <?php if (!empty($d['search'])): ?>
        <form class="row g-2 mb-3" method="get">
          <div class="col-sm-6 col-md-4"><input class="form-control" name="q" value="<?= e($cari) ?>" placeholder="Cari..."></div>
          <div class="col-auto"><button class="btn btn-outline-secondary">Cari</button></div>
        </form>
        <?php endif; ?>
        <div class="table-responsive">
          <table class="table table-hover table-sm">
            <thead class="table-light"><tr>
              <th>#</th>
              <?php foreach ($d['columns'] as $c): ?>
                <th class="<?= in_array($c['format'] ?? '', ['rupiah', 'angka'], true) ? 'num' : '' ?>"><?= e($c['label']) ?></th>
              <?php endforeach; ?>
              <th class="text-end">Aksi</th>
            </tr></thead>
            <tbody>
            <?php foreach ($data as $i => $r): ?>
              <tr>
                <td><?= $i + 1 ?></td>
                <?php foreach ($d['columns'] as $col => $c): $f = $c['format'] ?? null; ?>
                  <td class="<?= in_array($f, ['rupiah', 'angka'], true) ? 'num' : '' ?>"><?php
                    if (is_callable($f))       echo $f($r);
                    elseif ($f === 'rupiah')   echo rupiah($r[$col]);
                    elseif ($f === 'angka')    echo angka($r[$col]);
                    elseif ($f === 'bool')     echo status_badge($r[$col] ? 'ya' : 'tidak');
                    else                       echo e($r[$col]);
                  ?></td>
                <?php endforeach; ?>
                <td class="text-end text-nowrap">
                  <?= isset($d['row_actions']) ? $d['row_actions']($r) : '' ?>
                  <a class="btn btn-sm btn-outline-primary" href="?edit=<?= e($r[$pk]) ?>" title="Ubah"><i class="bi bi-pencil"></i></a>
                  <?= delete_button('', ['action' => 'delete', 'id' => $r[$pk]]) ?>
                </td>
              </tr>
            <?php endforeach; ?>
            <?php if (!$data): ?>
              <tr><td colspan="<?= count($d['columns']) + 2 ?>" class="text-center text-muted py-4">Belum ada data.</td></tr>
            <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
    <?php
}

function crud_form(array $d, string $editId): void
{
    $table = $d['table'];
    $pk    = $d['pk'];
    $r     = [];
    if ($editId !== '') {
        $r = row("SELECT * FROM $table WHERE $pk = ?", [$editId]);
        if (!$r) {
            echo '<div class="alert alert-warning">Data tidak ditemukan.</div>';
            return;
        }
    }
    $old = $_SESSION['old'] ?? null;
    unset($_SESSION['old']);
    if ($old) {
        $r = array_merge($r, $old);
    }

    page_title(($editId !== '' ? 'Ubah ' : 'Tambah ') . $d['title'],
        '<a class="btn btn-outline-secondary" href="?">Kembali</a>');
    ?>
    <div class="card" style="max-width: 760px">
      <div class="card-body">
        <form method="post">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="save">
          <input type="hidden" name="id" value="<?= e($editId) ?>">
          <?php foreach ($d['fields'] as $col => $f):
            $type = $f['type'] ?? 'text';
            $v    = $r[$col] ?? ($f['default'] ?? '');
            $req  = !empty($f['required']) && !($type === 'password' && $editId !== '');
            $id   = 'f_' . $col;
          ?>
            <div class="mb-3">
              <?php if ($type === 'checkbox'): ?>
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" id="<?= $id ?>" name="<?= e($col) ?>" value="1"
                         <?= ($editId === '' && !$old ? ($f['default'] ?? 1) : $v) ? 'checked' : '' ?>>
                  <label class="form-check-label" for="<?= $id ?>"><?= e($f['label']) ?></label>
                </div>
              <?php else: ?>
                <label class="form-label" for="<?= $id ?>"><?= e($f['label']) ?><?= $req ? ' <span class="text-danger">*</span>' : '' ?></label>
                <?php if ($type === 'textarea'): ?>
                  <textarea class="form-control" id="<?= $id ?>" name="<?= e($col) ?>" rows="3" <?= $req ? 'required' : '' ?>><?= e($v) ?></textarea>
                <?php elseif ($type === 'select'): ?>
                  <select class="form-select" id="<?= $id ?>" name="<?= e($col) ?>" <?= $req ? 'required' : '' ?>>
                    <option value="">-- pilih --</option>
                    <?php foreach ($f['options'] as $ov => $ot): ?>
                      <option value="<?= e($ov) ?>" <?= (string) $ov === (string) $v ? 'selected' : '' ?>><?= e($ot) ?></option>
                    <?php endforeach; ?>
                  </select>
                <?php elseif ($type === 'password'): ?>
                  <input class="form-control" type="password" id="<?= $id ?>" name="<?= e($col) ?>" autocomplete="new-password" <?= $req ? 'required' : '' ?>>
                  <?php if ($editId !== ''): ?><div class="form-text">Kosongkan jika tidak ingin mengganti password.</div><?php endif; ?>
                <?php else: ?>
                  <input class="form-control" type="<?= e($type) ?>" id="<?= $id ?>" name="<?= e($col) ?>" value="<?= e($v) ?>"
                         <?= isset($f['step']) ? 'step="' . e($f['step']) . '" min="0"' : '' ?> <?= $req ? 'required' : '' ?>>
                <?php endif; ?>
                <?php if (!empty($f['help'])): ?><div class="form-text"><?= e($f['help']) ?></div><?php endif; ?>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
          <button class="btn btn-hibiscus">Simpan</button>
          <a class="btn btn-link" href="?">Batal</a>
        </form>
      </div>
    </div>
    <?php
}

/** Opsi <select> dari query (kolom pertama = value, kedua = teks). */
function options(string $sql): array
{
    $o = [];
    foreach (db()->query($sql)->fetchAll(PDO::FETCH_NUM) as [$v, $t]) {
        $o[$v] = $t;
    }
    return $o;
}
