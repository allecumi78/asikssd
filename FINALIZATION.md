# Status Finalisasi ASIKSSD

ASIKSSD saat ini layak digunakan sebagai aplikasi internal sekolah pada lingkungan XAMPP/intranet setelah seluruh checklist `TESTING.md` selesai dijalankan dan data sekolah sudah disesuaikan dengan satuan pendidikan.

## Akses Aplikasi
- Login siswa utama: `http://localhost/asikssd/` atau `http://localhost/asikssd/public/index.php`.
- Login admin/operator/guru/kepala sekolah: `http://localhost/asikssd/public/admin_login.php`.
- Setup awal: `http://localhost/asikssd/public/setup.php`.
- Halaman admin tanpa session akan diarahkan ke login admin.
- Login siswa tidak menampilkan link, sidebar, atau menu admin.
- `public/check_graduation.php` hanya menjadi redirect kompatibilitas ke login siswa.

## Akun Awal
- Admin: NPSN `12345678`, password `Admin@12345`.
- Siswa demo: gunakan NISN siswa dan password default dari tanggal lahir dengan format `ddmmyyyy*`.
- Contoh: Alya Putri Pratama, NISN `0031234567`, tanggal lahir `2013-04-12`, password `12042013*`.

## Sistem yang Sudah Dibangun
- Autentikasi admin dan siswa dipisah melalui service berbeda.
- Password admin dan siswa disimpan dengan `password_hash`.
- Password default siswa dibuat dari tanggal lahir + tanda `*`.
- Session cookie memakai `HttpOnly`, `SameSite=Lax`, dan `Secure` saat HTTPS aktif.
- CSRF token aktif pada form penting, header keamanan dasar dipasang, dan login siswa memiliki rate limit 5 percobaan dengan jeda sekitar 5 menit.
- Role permission dasar tersedia untuk `ADMIN`, `OPERATOR`, `GURU`, dan `KEPALA SEKOLAH`.
- Root `.htaccess` memakai `Options -Indexes` agar folder tidak tampil sebagai daftar file.
- Dashboard admin menampilkan statistik, distribusi kelas, grafik status kelulusan, dan aktivitas terbaru.
- Tampilan login siswa responsif dengan logo sekolah, jadwal pengumuman, dan countdown timer.
- Data Sekolah dapat diedit termasuk identitas sekolah dan tahun pelajaran.
- Pengaturan mendukung upload logo login siswa, upload kop SKL, jadwal pengumuman, kriteria kelulusan, manajemen user, audit log, backup, dan restore.
- Data Siswa mendukung CRUD, detail, pencarian, filter, sort, pagination, import Excel/CSV dengan preview-validasi, dan export CSV/Excel.
- Data Nilai memakai periode `S7`, `S8`, `S9`, `S10`, `S11`, dan `ASAJ`.
- Data Nilai mendukung input manual dan import Excel/CSV dengan validasi NISN, nama, duplikasi, dan rentang nilai 0-100.
- Rekap Nilai menampilkan kelengkapan dan ringkasan nilai siswa.
- Kelulusan dihitung per mata pelajaran dari rata-rata rapor semester 7-11 dan ASAJ, dengan prestasi/ekstrakurikuler sebagai komponen tambahan opsional.
- Formula default nilai ijazah: 70% nilai rapor semester 7-11 + 30% Nilai Sumatif Akhir Jenjang (ASAJ).
- Alur kelulusan mendukung proses, verifikasi operator, verifikasi kepala sekolah, finalisasi, dan tampilan hasil siswa.
- Cetak SKL tersedia untuk siswa yang sudah final `LULUS`, baik dari admin maupun dari halaman siswa.
- Laporan menyediakan preview, cetak, export CSV/Excel, dan export PDF melalui dialog print browser.
- Backup SQL dibuat dengan header ASIKSSD dan restore dibatasi untuk file backup ASIKSSD.
- Seed demo menyediakan 5 siswa kelas VI dengan data, nilai lengkap, skor per mata pelajaran, dan hasil kelulusan final `LULUS`.

## Hak Akses Role
- `ADMIN`: akses penuh semua modul.
- `OPERATOR`: dashboard, data sekolah, data siswa, data nilai, rekap nilai, kelulusan, dan laporan.
- `GURU`: dashboard, data siswa, data nilai, dan rekap nilai.
- `KEPALA SEKOLAH`: dashboard, rekap nilai, kelulusan, dan laporan.

## Catatan Operasional
- Ganti password admin default setelah setup.
- Sesuaikan data sekolah, kepala sekolah, tahun pelajaran, jadwal pengumuman, kriteria kelulusan, logo, dan kop SKL sebelum digunakan resmi.
- Jalankan backup sebelum restore atau sebelum perubahan data besar.
- Gunakan HTTPS jika aplikasi dipasang pada hosting atau jaringan yang bisa diakses banyak perangkat.
- Untuk pemakaian offline penuh, unduh Bootstrap, Font Awesome, Chart.js, dan SheetJS ke `public/assets/vendor`, lalu ganti link CDN di file PHP.
- Pastikan folder `storage/backups` dan `public/assets/img/uploads` dapat ditulis oleh PHP/XAMPP.
- Jalankan ulang `public/setup.php` atau `php database/setup_phase1.php` setelah perubahan schema/seed demo.

## Batasan yang Perlu Diketahui
- Export PDF laporan dan SKL mengandalkan fitur print browser (`window.print()`), bukan pembuatan PDF server-side.
- Import Excel/CSV divalidasi di sisi browser dengan SheetJS dan divalidasi ulang sebagian di server saat simpan.
- Aplikasi ditujukan untuk XAMPP/intranet; hardening tambahan tetap disarankan untuk hosting publik.
