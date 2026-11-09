<?php
require __DIR__ . '/config.php';
session_start();
header('Content-Type: application/json; charset=utf-8');

function out($d) { echo json_encode($d); exit; }
function err($m) { out(['error' => $m]); }
function q($sql, $p = []) { global $db; $s = $db->prepare($sql); $s->execute($p); return $s; }
function need($role = null) {
  if (empty($_SESSION['u'])) err('Belum login');
  if ($role && $_SESSION['u']['role'] !== $role) err('Akses ditolak');
  return $_SESSION['u'];
}

try {
  $db = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
} catch (Exception $e) {
  err('Koneksi database gagal. Periksa config.php dan pastikan MySQL aktif.');
}

$in = json_decode(file_get_contents('php://input'), true) ?? [];
$a  = $_GET['a'] ?? '';

try {
  if ($a === 'register') {                                   // UC-001
    $n = trim($in['nama'] ?? ''); $e = strtolower(trim($in['email'] ?? ''));
    $p = $in['password'] ?? ''; $h = trim($in['hp'] ?? ''); $r = $in['role'] ?? 'pembeli';
    if (!$n || !$e || !$p || !$h) err('Lengkapi semua data.');
    if (!filter_var($e, FILTER_VALIDATE_EMAIL)) err('Format email tidak valid');
    if (strlen($p) < 6) err('Password minimal 6 karakter');
    if (!preg_match('/^(08|\+?62)\d{8,12}$/', $h)) err('Nomor HP tidak valid');
    if (!in_array($r, ['pembeli', 'penjual'], true)) err('Role tidak valid');
    if (q('SELECT 1 FROM users WHERE email_user=?', [$e])->fetch()) err('Email Sudah Terdaftar');
    q('INSERT INTO users(nama_user,email_user,password,no_hp_user,role) VALUES(?,?,?,?,?)',
      [$n, $e, password_hash($p, PASSWORD_DEFAULT), $h, $r]);
    if ($r === 'pembeli')
      q('INSERT INTO pembeli(id_user,nama_pembeli,no_hp_pembeli) VALUES(?,?,?)', [$db->lastInsertId(), $n, $h]);
    out(['ok' => 1]);
  }

  if ($a === 'login') {                                      // UC-002
    $e = strtolower(trim($in['email'] ?? ''));
    $u = q('SELECT * FROM users WHERE email_user=?', [$e])->fetch();
    if (!$u) err('Akun belum terdaftar');
    if (!password_verify($in['password'] ?? '', $u['password'])) err('Email atau password salah');
    session_regenerate_id(true);
    $_SESSION['u'] = ['id' => (int)$u['id_user'], 'role' => $u['role']];
    out(['ok' => 1]);
  }

  if ($a === 'logout') { session_destroy(); out(['ok' => 1]); }

  if ($a === 'load') {                                       // data awal setelah login
    $s = need();
    $u = q('SELECT * FROM users WHERE id_user=?', [$s['id']])->fetch();
    $pb = q('SELECT * FROM pembeli WHERE id_user=?', [$s['id']])->fetch();
    $me = ['e' => $u['email_user'], 'n' => $u['nama_user'], 'h' => $u['no_hp_user'], 'r' => $u['role'],
           'pembeli' => $pb ? ['n' => $pb['nama_pembeli'], 'h' => $pb['no_hp_pembeli'], 'a' => $pb['alamat_pengiriman']] : null];
    $pr = q('SELECT id_produk id, nama_produk n, jenis_kopi t, deskripsi_produk d, harga_produk p, stok s FROM produk ORDER BY id_produk')->fetchAll();
    $w = $s['role'] === 'penjual' ? '1=1' : 'u.id_user=' . (int)$s['id'];
    $or = q("SELECT o.id_pesanan id, o.nomor_pesanan no, u.email_user u, b.nama_pembeli `by`, b.no_hp_pembeli hp,
                    DATE_FORMAT(o.tanggal,'%e %b %Y') tgl, o.total_harga tot, o.status_pesanan st,
                    o.alamat_pengiriman adr, e.nama_ekspedisi eks, COALESCE(o.nomor_resi,'') resi
             FROM pesanan o JOIN pembeli b ON b.id_pembeli=o.id_pembeli JOIN users u ON u.id_user=b.id_user
             JOIN ekspedisi e ON e.id_ekspedisi=o.id_ekspedisi WHERE $w ORDER BY o.tanggal DESC, o.id_pesanan DESC")->fetchAll();
    $it = [];
    foreach (q('SELECT d.id_pesanan, CONCAT(p.nama_produk, " ×", d.jumlah_pesanan) x FROM detail_pesanan d JOIN produk p ON p.id_produk=d.id_produk ORDER BY d.id_detail') as $r)
      $it[$r['id_pesanan']][] = $r['x'];
    foreach ($or as &$o) $o['it'] = implode(', ', $it[$o['id']] ?? []);
    $cart = [];
    foreach (q('SELECT id_produk, jumlah FROM item_keranjang WHERE id_user=?', [$s['id']]) as $c) $cart[$c['id_produk']] = (int)$c['jumlah'];
    out(['me' => $me, 'products' => $pr, 'orders' => $or, 'cart' => (object)$cart]);
  }

  if ($a === 'cart_set') {                                   // UC-004
    $s = need('pembeli');
    $db->beginTransaction();
    q('DELETE FROM item_keranjang WHERE id_user=?', [$s['id']]);
    foreach (($in['items'] ?? []) as $id => $qt)
      if ((int)$qt > 0) q('INSERT INTO item_keranjang(id_user,id_produk,jumlah) VALUES(?,?,?)', [$s['id'], (int)$id, (int)$qt]);
    $db->commit();
    out(['ok' => 1]);
  }

  if ($a === 'checkout') {                                   // UC-005
    $s = need('pembeli');
    $n = trim($in['nama'] ?? ''); $h = trim($in['hp'] ?? ''); $al = trim($in['alamat'] ?? '');
    $items = $in['items'] ?? [];
    if (!$n) err('Nama pembeli wajib diisi');
    if (!preg_match('/^(08|\+?62)\d{8,12}$/', $h)) err('Nomor HP tidak valid');
    if (!$al) err('Alamat pengiriman wajib diisi');
    if (!$items) err('Keranjang masih kosong');
    $ek = q('SELECT * FROM ekspedisi WHERE nama_ekspedisi=?', [$in['ekspedisi'] ?? ''])->fetch();
    if (!$ek) err('Pilih ekspedisi');
    $db->beginTransaction();
    $tot = (float)$ek['tarif_ekspedisi']; $rows = [];
    foreach ($items as $id => $qt) {
      $p = q('SELECT * FROM produk WHERE id_produk=? FOR UPDATE', [(int)$id])->fetch(); $qt = (int)$qt;
      if (!$p || $qt < 1 || $p['stok'] < $qt) throw new Exception('Stok tidak mencukupi');
      $tot += $p['harga_produk'] * $qt; $rows[] = [$p, $qt];
    }
    q('INSERT INTO pembeli(id_user,nama_pembeli,no_hp_pembeli,alamat_pengiriman) VALUES(?,?,?,?)
       ON DUPLICATE KEY UPDATE nama_pembeli=VALUES(nama_pembeli), no_hp_pembeli=VALUES(no_hp_pembeli), alamat_pengiriman=VALUES(alamat_pengiriman)',
      [$s['id'], $n, $h, $al]);
    $pb = q('SELECT id_pembeli FROM pembeli WHERE id_user=?', [$s['id']])->fetch()['id_pembeli'];
    $no = 'KK-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(2)));
    q('INSERT INTO pesanan(nomor_pesanan,id_pembeli,id_ekspedisi,tanggal,total_harga,status_pesanan,alamat_pengiriman) VALUES(?,?,?,NOW(),?,?,?)',
      [$no, $pb, $ek['id_ekspedisi'], $tot, 'Dibayar', $al]);
    $idp = $db->lastInsertId();
    foreach ($rows as [$p, $qt]) {
      q('INSERT INTO detail_pesanan(id_pesanan,id_produk,jumlah_pesanan,harga_satuan) VALUES(?,?,?,?)', [$idp, $p['id_produk'], $qt, $p['harga_produk']]);
      q('UPDATE produk SET stok=stok-? WHERE id_produk=?', [$qt, $p['id_produk']]);
    }
    q('INSERT INTO pembayaran(id_pesanan,metode_pembayaran,status_pembayaran,tanggal_pembayaran) VALUES(?,?,?,NOW())', [$idp, $in['metode'] ?? 'Transfer bank', 'lunas']);
    q('DELETE FROM item_keranjang WHERE id_user=?', [$s['id']]);
    $db->commit();
    out(['no' => $no]);
  }

  if ($a === 'product_save') {                               // UC-007
    need('penjual');
    $id = (int)($in['id'] ?? 0); $n = trim($in['n'] ?? ''); $d = trim($in['d'] ?? ''); $t = $in['t'] ?? 'Arabika';
    if (!$n || !$d) err('Data produk wajib diisi');
    if (!is_numeric($in['p'] ?? null) || !is_numeric($in['s'] ?? null) || $in['p'] < 0 || $in['s'] < 0) err('Harga atau stok tidak valid');
    if ($id) q('UPDATE produk SET nama_produk=?,jenis_kopi=?,deskripsi_produk=?,harga_produk=?,stok=? WHERE id_produk=?', [$n, $t, $d, $in['p'], (int)$in['s'], $id]);
    else { q('INSERT INTO produk(nama_produk,jenis_kopi,deskripsi_produk,harga_produk,stok) VALUES(?,?,?,?,?)', [$n, $t, $d, $in['p'], (int)$in['s']]); $id = $db->lastInsertId(); }
    out(['id' => (int)$id]);
  }

  if ($a === 'product_delete') {                             // UC-007
    need('penjual'); $id = (int)($in['id'] ?? 0);
    if (q("SELECT 1 FROM detail_pesanan d JOIN pesanan o ON o.id_pesanan=d.id_pesanan WHERE d.id_produk=? AND o.status_pesanan<>'Selesai' LIMIT 1", [$id])->fetch())
      err('Produk tidak dapat dihapus karena masih dalam pesanan');
    try { q('DELETE FROM produk WHERE id_produk=?', [$id]); }
    catch (PDOException $e) { err('Produk tidak dapat dihapus karena sudah punya riwayat pesanan'); }
    out(['ok' => 1]);
  }

  if ($a === 'order_status') {                               // UC-008
    need('penjual'); $st = $in['status'] ?? ''; $resi = trim($in['resi'] ?? '');
    if (!in_array($st, ['Dikemas', 'Dikirim', 'Selesai'], true)) err('Status tidak valid');
    if ($st === 'Dikirim' && !$resi) err('Nomor resi wajib diisi');
    q('UPDATE pesanan SET status_pesanan=?, nomor_resi=IF(?<>"",?,nomor_resi) WHERE id_pesanan=?', [$st, $resi, $resi, (int)($in['id'] ?? 0)]);
    out(['ok' => 1]);
  }

  err('Aksi tidak dikenal');
} catch (Exception $e) {
  if (isset($db) && $db->inTransaction()) $db->rollBack();
  err($e instanceof PDOException ? 'Terjadi kesalahan database.' : $e->getMessage());
}
