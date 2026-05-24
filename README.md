# Cloudify

Cloudify adalah workspace visual berbasis PHP untuk mengelola upload tim dengan pengalaman gallery-first dan kontrol akses yang jelas:
- Upload file (validasi ekstensi, MIME, dan size)
- List metadata file pada dashboard
- View library dalam mode list dan grid
- Preview file di mode grid (thumbnail untuk file gambar)
- Download file
- Rename file
- Delete file via POST + CSRF token
- Audit trail aktivitas upload/download/delete
- Homepage publik bergaya galeri visual
- Login system dengan role `admin`, `user`, `viewer`, dan `guest`
- Admin dapat mengelola semua pengguna dan semua file
- User dapat upload, download, dan delete file miliknya sendiri
- Viewer hanya dapat melihat dan download file tertentu
- Guest hanya mendapat akses terbatas ke homepage publik

## Struktur Utama
- `index.php` - homepage publik
- `dashboard.php` - dashboard utama setelah login
- `login.php` - halaman login
- `logout.php` - endpoint logout
- `upload.php` - endpoint upload
- `download.php` - endpoint download
- `delete.php` - endpoint delete
- `rename.php` - endpoint rename
- `preview.php` - preview gambar publik untuk homepage
- `src/Services/CloudStorageService.php` - service storage
- `src/Services/FileOwnershipStore.php` - metadata pemilik file dan policy akses file
- `src/Security/AuthManager.php` - session login, verifikasi password, dan guard halaman
- `src/Services/AuditLogger.php` - audit event logger
- `src/Security/CsrfManager.php` - CSRF guard
- `config/users.php` - daftar akun lokal dengan password hash
- `docs/LAPORAN_PRAKTEK_CLOUD.md` - template laporan pengumpulan

## Menjalankan di XAMPP
1. Pastikan folder proyek berada di `htdocs`.
2. Jalankan Apache dari XAMPP.
3. Buka:
   - `http://localhost/simple_cloud/`
4. Untuk uji browser lain, buka URL yang sama di browser berbeda.

## Akun Demo
- Admin: `admin` / `admin123`
- User: `user` / `user123`
- Viewer: `viewer` / `viewer123`
- Guest: `guest` / `guest123`

## Catatan Praktikum
- File tersimpan di folder `uploads/`.
- Log aktivitas tersimpan di `logs/audit.log`.
- Metadata pemilik file tersimpan di `logs/file_owners.json`.
- Batas upload default: 20 MB per file dan 1 GB total storage.
- Ekstensi yang diizinkan: `pdf`, `doc`, `docx`, `xls`, `xlsx`, `csv`, `txt`, `png`, `jpg`, `jpeg`, `zip`, `rar`, `ppt`, `pptx`.
- Laporan siap isi ada di `docs/LAPORAN_PRAKTEK_CLOUD.md`.
# simple_cloud
