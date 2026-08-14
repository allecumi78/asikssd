# ASIKSSD

ASIKSSD adalah aplikasi PHP/MySQL untuk pengelolaan informasi kelulusan siswa sekolah dasar pada lingkungan XAMPP atau intranet sekolah.

## Fungsi Dokumen
- `README.md`: ringkasan proyek, struktur folder, dan pintu masuk dokumentasi.
- `TESTING.md`: checklist pengujian manual dari setup sampai backup/restore.
- `FINALIZATION.md`: status final aplikasi, fitur yang sudah dibangun, hak akses role, dan catatan operasional.

## Struktur Singkat
- `app/`: core aplikasi, middleware, service autentikasi, audit, dan utility.
- `config/`: konfigurasi aplikasi dan database.
- `database/`: migrasi schema, seed demo, dan script setup.
- `public/`: halaman yang diakses browser, aset CSS/JS, gambar, dan upload.

## Mulai Cepat
1. Aktifkan Apache dan MySQL di XAMPP.
2. Pastikan proyek berada di `C:\xampp\htdocs\asikssd`.
3. Sesuaikan `config/database.php` bila konfigurasi MySQL berbeda.
4. Jalankan setup dari `http://localhost/asikssd/public/setup.php`.

Setelah setup selesai:
- Siswa membuka `http://localhost/asikssd/`.
- Admin membuka `http://localhost/asikssd/public/admin_login.php`.

## Alur Setelah Instalasi
1. Baca dan jalankan checklist di `TESTING.md`.
2. Jika seluruh pengujian lolos, gunakan `FINALIZATION.md` sebagai catatan kesiapan penggunaan.
3. Ganti data demo dengan data sekolah sebenarnya sebelum pemakaian resmi.
