# Anwar Group Document Hub (Mini Cloud Storage)

Aplikasi PHP sederhana dengan pendekatan enterprise-style untuk praktikum cloud computing:
- Upload file (validasi ekstensi, MIME, dan size)
- List metadata file pada dashboard
- View repository dalam mode list dan grid
- Preview file di mode grid (thumbnail untuk file gambar)
- Download file
- Rename file
- Delete file via POST + CSRF token
- Audit trail aktivitas upload/download/delete

## Struktur Utama
- `index.php` - dashboard utama
- `upload.php` - endpoint upload
- `download.php` - endpoint download
- `delete.php` - endpoint delete
- `rename.php` - endpoint rename
- `src/Services/CloudStorageService.php` - service storage
- `src/Services/AuditLogger.php` - audit event logger
- `src/Security/CsrfManager.php` - CSRF guard
- `docs/LAPORAN_PRAKTEK_CLOUD.md` - template laporan pengumpulan

## Menjalankan di XAMPP
1. Pastikan folder proyek berada di `htdocs`.
2. Jalankan Apache dari XAMPP.
3. Buka:
   - `http://localhost/simple_cloud/`
4. Untuk uji browser lain, buka URL yang sama di browser berbeda.

## Catatan Praktikum
- File tersimpan di folder `uploads/`.
- Log aktivitas tersimpan di `logs/audit.log`.
- Laporan siap isi ada di `docs/LAPORAN_PRAKTEK_CLOUD.md`.
# simple_cloud
