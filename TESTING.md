# Checklist Pengujian ASIKSSD

Gunakan checklist ini setelah Apache dan MySQL XAMPP aktif. Jika database belum siap, buka `http://localhost/asikssd/public/setup.php` lalu jalankan setup.

## Lingkungan dan Setup
- Pastikan folder proyek berada di `C:\xampp\htdocs\asikssd`.
- Pastikan konfigurasi database di `config/database.php` sesuai dengan MySQL lokal XAMPP.
- Jalankan setup melalui `public/setup.php` atau CLI `php database/setup_phase1.php`.
- Pastikan setup membuat database, tabel inti, role, sekolah demo, tahun pelajaran aktif `2025/2026`, mata pelajaran, siswa demo, nilai demo, dan hasil kelulusan demo.
- Buka `http://localhost/asikssd/` dan pastikan diarahkan ke login siswa, bukan daftar folder.
- Buka `http://localhost/asikssd/public/check_graduation.php` dan pastikan redirect ke `index.php`.

## Akun Demo
- Admin: NPSN `12345678`, password `Admin@12345`.
- Siswa Alya: NISN `0031234567`, password `12042013*`.
- Siswa Bagas: NISN `0031234568`, password `21082013*`.
- Siswa Citra: NISN `0031234569`, password `30012013*`.
- Siswa Dimas: NISN `0031234570`, password `05062013*`.
- Siswa Eka: NISN `0031234571`, password `14092013*`.

## Login Siswa
- Buka `http://localhost/asikssd/` atau `http://localhost/asikssd/public/index.php`.
- Pastikan halaman yang muncul adalah login siswa dengan logo sekolah, jadwal pengumuman, dan countdown timer.
- Pastikan halaman login siswa tidak memiliki link, sidebar, atau menu admin.
- Login memakai NISN dan password siswa demo.
- Pastikan siswa masuk ke halaman hasil siswa tanpa sidebar atau menu admin.
- Pastikan hasil final siswa demo tampil dengan status `LULUS`.
- Klik cetak SKL dari halaman siswa jika tersedia, lalu pastikan halaman SKL hanya tampil untuk siswa berstatus final `LULUS`.
- Coba salah password 5 kali dari IP/session yang sama, sistem harus menahan login sementara sekitar 5 menit.
- Pastikan password default siswa mengikuti format tanggal lahir `ddmmyyyy*`.

## Login Admin dan Hak Akses
- Buka `http://localhost/asikssd/public/admin_login.php`.
- Login admin memakai NPSN `12345678` dan password `Admin@12345`.
- Coba akses `public/dashboard.php` tanpa session admin, sistem harus mengarah ke login admin.
- Buat user role `OPERATOR`, `GURU`, dan `KEPALA SEKOLAH` dari Pengaturan.
- Pastikan akses role sesuai implementasi:
  - `ADMIN`: semua modul.
  - `OPERATOR`: dashboard, data sekolah, siswa, nilai, rekap nilai, kelulusan, laporan.
  - `GURU`: dashboard, siswa, nilai, rekap nilai.
  - `KEPALA SEKOLAH`: dashboard, rekap nilai, kelulusan, laporan.
- Pastikan modul tanpa izin menampilkan halaman `403`.
- Dari Pengaturan, nonaktifkan/aktifkan user dan reset password user.
- Pastikan admin tidak bisa menonaktifkan akun yang sedang dipakai sendiri.
- Logout admin dan pastikan halaman admin tidak bisa dibuka tanpa login ulang.

## Tampilan dan Interaksi Umum
- Cek tampilan desktop: sidebar, topbar, kartu statistik/panel, tabel, dan tombol aksi tampil rapi.
- Cek tablet/mobile: halaman siswa dan halaman admin tetap dapat dibaca, tabel dapat discroll, dan kontrol tidak bertumpuk.
- Uji tombol sidebar, mode gelap, dan fullscreen pada halaman admin.
- Cek pesan sukses/error setelah simpan, import, proses, backup, restore, dan aksi user.
- Pastikan token CSRF bekerja dengan memuat ulang halaman saat form lama gagal dipakai.

## Data Sekolah dan Pengaturan
- Edit Data Sekolah, simpan, lalu muat ulang halaman untuk memastikan data tersimpan.
- Ubah tahun pelajaran aktif pada Data Sekolah dan pastikan pilihan tahun muncul pada Data Nilai.
- Upload logo login siswa dari Pengaturan, lalu pastikan logo muncul pada login siswa.
- Upload kop SKL, lalu pastikan cetak SKL memakai gambar kop tersebut.
- Atur Jadwal Pengumuman dari Pengaturan dan pastikan login siswa menampilkan tanggal/jam yang baru.
- Jika tanggal pengumuman sudah lewat, timer harus berubah menjadi status telah dimulai.
- Ubah Kriteria Kelulusan: nilai minimum, bobot rapor semester 7-11, bobot ASAJ, syarat kelengkapan nilai, dan syarat administrasi.

## Data Siswa
- Tambah siswa baru dengan tanggal lahir; kosongkan password agar sistem membuat password `ddmmyyyy*`.
- Edit siswa; kosongkan password agar password lama tidak berubah.
- Edit siswa dan isi password baru; pastikan password siswa berubah.
- Uji validasi NIS, NISN, nama, jenis kelamin, kelas, tanggal lahir, dan status wajib/valid.
- Detail dan hapus siswa dari tombol aksi.
- Uji pencarian, filter jenis kelamin, filter status, sort kolom, dan pagination 10 data per halaman.
- Export Data Siswa ke CSV dan Excel.
- Download template import siswa, isi data valid, import, dan pastikan preview validasi muncul sebelum simpan.
- Uji import siswa dengan tanggal lahir kosong atau format selain `yyyy-mm-dd`; sistem harus menolak karena password default membutuhkan tanggal lahir.
- Uji import siswa dengan kelas/status/jenis kelamin tidak valid dan pastikan preview menandai data bermasalah.

## Data Nilai dan Rekap
- Pastikan 5 siswa demo memiliki nilai lengkap untuk semua mata pelajaran aktif pada periode `S7`, `S8`, `S9`, `S10`, `S11`, dan `ASAJ`.
- Input nilai manual pada Data Nilai untuk setiap kelas, mata pelajaran, tahun pelajaran, dan periode.
- Pastikan nilai kosong ditolak dan nilai di luar rentang 0-100 ditolak.
- Download template import nilai, isi kolom `NISN`, `NAMA`, dan `NILAI`, lalu pastikan preview validasi muncul sebelum simpan.
- Uji import nilai dengan NISN salah, nama tidak sesuai, nilai kosong, nilai di luar 0-100, dan NISN duplikat.
- Buka Rekap Nilai dan cek jumlah nilai, rata-rata, serta status kelengkapan.

## Kelulusan dan SKL
- Pastikan 5 siswa demo sudah memiliki hasil kelulusan final dengan status `LULUS`.
- Proses kelulusan untuk siswa tambahan atau data uji baru.
- Pastikan nilai rapor semester 7-11 dan ASAJ pada modal kelulusan terisi otomatis dari Data Nilai dan bersifat readonly.
- Pastikan prestasi dan ekstrakurikuler dapat diisi sebagai komponen tambahan opsional.
- Pastikan proses kelulusan ditolak jika salah satu mata pelajaran belum memiliki nilai lengkap `S7` sampai `S11` dan `ASAJ`.
- Pastikan rumus nilai akhir per mata pelajaran memakai konfigurasi aktif, defaultnya 70% rata-rata rapor semester 7-11 + 30% ASAJ.
- Jalankan verifikasi operator, verifikasi kepala sekolah, dan finalisasi.
- Pastikan data yang sudah final tidak bisa diverifikasi/finalisasi ulang sembarangan.
- Setelah finalisasi, login sebagai siswa dan pastikan hasil muncul di halaman siswa.
- Cetak SKL dari modul Kelulusan dan dari halaman siswa; simpan PDF melalui dialog print browser jika diperlukan.

## Laporan, Audit, Backup, dan Restore
- Buka Laporan, ganti jenis laporan, lalu cek preview untuk daftar siswa, rekap nilai, kelengkapan nilai, kelulusan, siswa lulus, siswa tidak lulus, dan berita acara/rekap administrasi.
- Cetak laporan dan export CSV/Excel.
- Gunakan tombol Export PDF pada laporan dan pastikan dialog print browser terbuka.
- Buat backup dari Pengaturan dan pastikan file `backup-asikssd-YYYYMMDD-HHMMSS.sql` muncul/terunduh dari daftar backup.
- Restore hanya menggunakan file `.sql` yang dibuat oleh ASIKSSD.
- Pastikan restore menolak file SQL lain yang tidak diawali header backup ASIKSSD.
- Cek Audit Log setelah aksi create/update/delete/import/proses/verifikasi/backup/restore.
- Uji tombol hapus audit log lebih dari 30 hari dan bersihkan semua sesuai kebutuhan data uji.

## Catatan Batasan Pengujian
- Aplikasi memakai CDN Bootstrap, Font Awesome, Chart.js, dan SheetJS; koneksi internet dibutuhkan kecuali aset vendor sudah dilokalkan.
- Export PDF laporan dan SKL menggunakan `window.print()`, bukan generator PDF server-side.
- Backup tersimpan di `storage/backups`; pastikan folder dapat dibuat dan ditulis oleh PHP/XAMPP.
