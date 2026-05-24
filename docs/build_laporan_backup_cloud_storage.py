from __future__ import annotations

from datetime import datetime
from pathlib import Path

from reportlab.lib import colors
from reportlab.lib.enums import TA_CENTER
from reportlab.lib.pagesizes import A4
from reportlab.lib.styles import ParagraphStyle, getSampleStyleSheet
from reportlab.lib.units import cm
from reportlab.platypus import (
    Flowable,
    ListFlowable,
    ListItem,
    PageBreak,
    Paragraph,
    SimpleDocTemplate,
    Spacer,
    Table,
    TableStyle,
)


ROOT = Path(__file__).resolve().parents[1]
OUT = ROOT / "docs" / "Laporan_Praktikum_Backup_Cloud_Storage.pdf"
TODAY = "24 Mei 2026"


def rupiah_bytes(size: int) -> str:
    units = ["B", "KB", "MB", "GB"]
    value = float(size)
    idx = 0
    while value >= 1024 and idx < len(units) - 1:
        value /= 1024
        idx += 1
    if idx == 0:
        return f"{int(value)} {units[idx]}"
    return f"{value:.2f} {units[idx]}"


def collect_backup_rows() -> list[list[str]]:
    backup_dir = ROOT / "backup" / "2026-05-24"
    files = [
        "source_code.zip",
        "uploads.zip",
        "trash.zip",
        "database_cloud_storage_2026-05-24.sql",
    ]
    rows: list[list[str]] = []
    for name in files:
        path = backup_dir / name
        size = rupiah_bytes(path.stat().st_size) if path.exists() else "Belum ditemukan"
        rows.append([name, str(path), size, "Ada" if path.exists() else "Tidak ada"])
    return rows


def styles():
    base = getSampleStyleSheet()
    base.add(
        ParagraphStyle(
            name="CoverTitle",
            parent=base["Title"],
            fontName="Helvetica-Bold",
            fontSize=19,
            leading=24,
            alignment=TA_CENTER,
            textColor=colors.HexColor("#0F2742"),
            spaceAfter=6,
        )
    )
    base.add(
        ParagraphStyle(
            name="CoverSubtitle",
            parent=base["BodyText"],
            fontName="Helvetica-Bold",
            fontSize=13,
            leading=17,
            alignment=TA_CENTER,
            textColor=colors.HexColor("#2B6CB0"),
            spaceAfter=16,
        )
    )
    base.add(
        ParagraphStyle(
            name="Lead",
            parent=base["BodyText"],
            fontName="Helvetica",
            fontSize=10.2,
            leading=14,
            textColor=colors.HexColor("#344054"),
            spaceAfter=8,
        )
    )
    base["Heading1"].fontName = "Helvetica-Bold"
    base["Heading1"].fontSize = 14
    base["Heading1"].leading = 18
    base["Heading1"].textColor = colors.HexColor("#1F5F99")
    base["Heading1"].spaceBefore = 12
    base["Heading1"].spaceAfter = 6
    base["Heading2"].fontName = "Helvetica-Bold"
    base["Heading2"].fontSize = 11.5
    base["Heading2"].leading = 15
    base["Heading2"].textColor = colors.HexColor("#1F5F99")
    base["Heading2"].spaceBefore = 8
    base["Heading2"].spaceAfter = 4
    base["BodyText"].fontName = "Helvetica"
    base["BodyText"].fontSize = 9.5
    base["BodyText"].leading = 13
    base["BodyText"].spaceAfter = 6
    base.add(
        ParagraphStyle(
            name="Small",
            parent=base["BodyText"],
            fontSize=8.2,
            leading=10.5,
        )
    )
    base.add(
        ParagraphStyle(
            name="TableText",
            parent=base["BodyText"],
            fontSize=7.6,
            leading=9.7,
            spaceAfter=0,
        )
    )
    base.add(
        ParagraphStyle(
            name="TableHeader",
            parent=base["TableText"],
            fontName="Helvetica-Bold",
            textColor=colors.HexColor("#0F2742"),
        )
    )
    return base


def p(text: str, style):
    return Paragraph(text, style)


def body(text: str, style):
    return p(text, style["BodyText"])


def h1(text: str, style):
    return p(text, style["Heading1"])


def h2(text: str, style):
    return p(text, style["Heading2"])


def bullet(items: list[str], style):
    return ListFlowable(
        [ListItem(body(item, style), leftIndent=10) for item in items],
        bulletType="bullet",
        leftIndent=16,
        bulletFontName="Helvetica",
        bulletFontSize=7,
        spaceAfter=6,
    )


def numbered(items: list[str], style):
    return ListFlowable(
        [ListItem(body(item, style), leftIndent=10) for item in items],
        bulletType="1",
        leftIndent=17,
        bulletFontName="Helvetica",
        bulletFontSize=8,
        spaceAfter=6,
    )


def make_table(data: list[list[str]], widths: list[float], style):
    rows = []
    for row_index, row in enumerate(data):
        para_style = style["TableHeader"] if row_index == 0 else style["TableText"]
        rows.append([p(str(cell), para_style) for cell in row])
    tbl = Table(rows, colWidths=[w * cm for w in widths], repeatRows=1, hAlign="LEFT")
    tbl.setStyle(
        TableStyle(
            [
                ("GRID", (0, 0), (-1, -1), 0.35, colors.HexColor("#D0D7DE")),
                ("BACKGROUND", (0, 0), (-1, 0), colors.HexColor("#EEF4FB")),
                ("VALIGN", (0, 0), (-1, -1), "MIDDLE"),
                ("LEFTPADDING", (0, 0), (-1, -1), 5),
                ("RIGHTPADDING", (0, 0), (-1, -1), 5),
                ("TOPPADDING", (0, 0), (-1, -1), 4),
                ("BOTTOMPADDING", (0, 0), (-1, -1), 4),
            ]
        )
    )
    return tbl


def kv(rows: list[tuple[str, str]], style):
    return make_table([["Item", "Keterangan"]] + [[a, b] for a, b in rows], [4.2, 11.8], style)


def footer(canvas, doc):
    canvas.saveState()
    canvas.setFont("Helvetica", 8)
    canvas.setFillColor(colors.HexColor("#667085"))
    canvas.drawString(2 * cm, 1.2 * cm, "Laporan Praktikum Backup Cloud Storage")
    canvas.drawRightString(A4[0] - 2 * cm, 1.2 * cm, f"Halaman {doc.page}")
    canvas.restoreState()


def build() -> None:
    style = styles()
    doc = SimpleDocTemplate(
        str(OUT),
        pagesize=A4,
        rightMargin=2 * cm,
        leftMargin=2 * cm,
        topMargin=2 * cm,
        bottomMargin=1.8 * cm,
        title="Laporan Praktikum Backup Cloud Storage",
        author="Cloudify",
    )
    story: list[Flowable] = []

    story.append(p("LAPORAN PRAKTIKUM", style["CoverTitle"]))
    story.append(p("Tugas 6 - Backup Cloud Storage pada Website Cloudify", style["CoverSubtitle"]))
    story.append(
        kv(
            [
                ("Nama", "........................................................"),
                ("NIM", "........................................................"),
                ("Kelas", "........................................................"),
                ("Mata Praktikum", "Mini Project Website Cloud Storage"),
                ("Tanggal Laporan", TODAY),
            ],
            style,
        )
    )
    story.append(Spacer(1, 10))
    story.append(
        p(
            "Laporan ini menjelaskan implementasi fitur backup pada website Cloudify, "
            "meliputi backup terjadwal, backup folder uploads, backup database, backup "
            "sebelum update, backup folder trash saat file dihapus, dan penyimpanan "
            "salinan backup pada lokasi berbeda.",
            style["Lead"],
        )
    )

    story.append(h1("1. Pendahuluan", style))
    story.append(
        body(
            "Cloudify adalah website cloud storage lokal berbasis PHP dan MySQL yang "
            "digunakan untuk mengelola file melalui dashboard. Pada tugas ke-6, sistem "
            "ditingkatkan dengan mekanisme backup agar data aplikasi lebih aman dari "
            "risiko kehilangan file, kesalahan update, maupun penghapusan tidak sengaja.",
            style,
        )
    )
    story.append(
        body(
            "Tujuan praktikum ini adalah menerapkan strategi backup yang memisahkan "
            "source code, folder uploads, folder trash, dan database. Dengan pemisahan "
            "tersebut, proses pemulihan dapat dilakukan lebih terarah sesuai sumber "
            "kerusakan atau kehilangan data.",
            style,
        )
    )

    story.append(h1("2. Identitas Sistem", style))
    story.append(
        kv(
            [
                ("Nama aplikasi", "Cloudify"),
                ("Jenis aplikasi", "Website cloud storage lokal"),
                ("Teknologi", "PHP, MySQL/MariaDB, HTML, CSS, JavaScript, XAMPP"),
                ("Folder project", str(ROOT)),
                ("Folder upload", str(ROOT / "uploads")),
                ("Folder trash", str(ROOT / "trash")),
                ("Database", "simple_cloud"),
                ("Zona waktu", "Asia/Makassar"),
            ],
            style,
        )
    )

    story.append(h1("3. Ringkasan Kebutuhan Tugas", style))
    story.append(
        make_table(
            [
                ["No", "Kebutuhan", "Implementasi pada Cloudify", "Status"],
                ["1", "Backup terjadwal", "Script backup_scheduled.php hanya berjalan melalui CLI dan dapat dipasang pada Windows Task Scheduler.", "Terpenuhi"],
                ["2", "Backup folder uploads", "BackupService membuat uploads.zip dari folder uploads/ ke backup/YYYY-MM-DD/.", "Terpenuhi"],
                ["3", "Backup database", "Database didump menjadi database_cloud_storage_YYYY-MM-DD.sql berisi struktur tabel dan data.", "Terpenuhi"],
                ["4", "Backup sebelum update", "Tombol Backup Sebelum Update mengirim tipe before_update sehingga folder backup diberi timestamp khusus.", "Terpenuhi"],
                ["5", "Backup trash saat delete", "delete.php memindahkan file ke trash/, menyimpan metadata, lalu memperbarui trash.zip.", "Terpenuhi"],
                ["6", "Lokasi backup berbeda", "Setiap backup disalin dari backup lokal ke C:\\cloud_storage_backup\\YYYY-MM-DD\\.", "Terpenuhi"],
            ],
            [1.0, 4.0, 8.0, 3.0],
            style,
        )
    )

    story.append(h1("4. Desain Struktur Backup", style))
    story.append(
        body(
            "Struktur hasil backup dibuat berdasarkan tanggal. Pada tanggal 2026-05-24, "
            "folder backup utama berada pada backup/2026-05-24/. Isi folder mengikuti "
            "kebutuhan tugas, yaitu source_code.zip, uploads.zip, trash.zip, dan file SQL database.",
            style,
        )
    )
    story.append(
        make_table(
            [["Nama File", "Lokasi", "Ukuran", "Status"]] + collect_backup_rows(),
            [4.2, 8.3, 2.0, 1.5],
            style,
        )
    )
    story.append(
        body(
            "Selain disimpan di dalam project, file yang sama juga disalin ke "
            "C:\\cloud_storage_backup\\2026-05-24\\. Pemisahan lokasi ini penting karena "
            "backup tidak hanya bergantung pada folder project utama.",
            style,
        )
    )

    story.append(h1("5. Analisis dan Penjelasan Fitur", style))
    story.append(h2("5.1 Backup Terjadwal", style))
    story.append(
        body(
            "Backup terjadwal disediakan melalui backup_scheduled.php. Script ini "
            "memeriksa PHP_SAPI dan hanya menerima eksekusi CLI, sehingga tidak dapat "
            "dipanggil langsung dari browser. Pada Windows, script dapat dijalankan "
            "melalui Task Scheduler menggunakan perintah berikut.",
            style,
        )
    )
    story.append(
        kv(
            [
                ("Command", "C:\\xampp\\php\\php.exe C:\\xampp\\htdocs\\simple_cloud\\backup_scheduled.php"),
                ("Output sukses", "Backup terjadwal berhasil: C:\\xampp\\htdocs\\simple_cloud\\backup\\2026-05-24"),
                ("Pencatatan", "Event backup_scheduled dicatat pada logs/audit.log"),
            ],
            style,
        )
    )

    story.append(h2("5.2 Backup Source Code", style))
    story.append(
        body(
            "Source code dikompres menjadi source_code.zip menggunakan BackupService. "
            "Saat membuat zip source code, sistem menerapkan exclude_dirs untuk "
            "menghindari folder yang tidak perlu ikut masuk seperti .git, backup, uploads, "
            "dan trash. Dengan begitu, source_code.zip fokus pada file aplikasi dan tidak "
            "menduplikasi data backup atau file pengguna.",
            style,
        )
    )

    story.append(h2("5.3 Backup Folder Uploads", style))
    story.append(
        body(
            "Folder uploads/ dikompres menjadi uploads.zip. File ini menyimpan data utama "
            "pengguna yang masih aktif di katalog Cloudify. Pemisahan uploads.zip dari "
            "source_code.zip membuat restore file pengguna dapat dilakukan tanpa harus "
            "mengubah source code aplikasi.",
            style,
        )
    )

    story.append(h2("5.4 Backup Database", style))
    story.append(
        body(
            "Database diekspor menjadi database_cloud_storage_2026-05-24.sql. Proses dump "
            "mengambil daftar tabel base table, menulis perintah DROP TABLE, CREATE TABLE, "
            "dan INSERT INTO untuk setiap baris data. File SQL ini menjadi cadangan struktur "
            "dan isi database simple_cloud.",
            style,
        )
    )

    story.append(h2("5.5 Backup Sebelum Update", style))
    story.append(
        body(
            "Backup sebelum update dipicu dari tombol dashboard dengan nilai backup_type "
            "before_update. BackupService memberi nama folder dengan format "
            "before_update_YYYY-MM-DD_HHMMSS. Format ini menjaga backup pra-update tidak "
            "tertumpuk dengan backup harian biasa, sehingga aman digunakan sebagai titik "
            "rollback sebelum perubahan aplikasi dilakukan.",
            style,
        )
    )

    story.append(h2("5.6 Backup Trash Ketika User Menghapus File", style))
    story.append(
        body(
            "Saat user menekan delete, file tidak langsung hilang permanen. Endpoint "
            "delete.php memindahkan file dari uploads/ ke trash/, mencatat metadata file "
            "pada logs/trash_files.json, menghapus catatan ownership aktif, lalu memanggil "
            "backupTrashAfterDelete(). Fungsi tersebut memperbarui trash.zip agar keadaan "
            "folder trash tetap tercadangkan setelah aksi delete.",
            style,
        )
    )
    story.append(
        bullet(
            [
                "Delete hanya menerima metode POST dan wajib lolos validasi CSRF token.",
                "Akses delete dibatasi oleh permission serta kepemilikan file.",
                "Setiap delete sukses dicatat dalam audit log bersama hasil backup trash.",
            ],
            style,
        )
    )

    story.append(h2("5.7 Backup ke Lokasi Berbeda", style))
    story.append(
        body(
            "Konfigurasi backup.external_dir diarahkan ke C:\\cloud_storage_backup. "
            "Setelah backup lokal selesai dibuat, BackupService menyalin file hasil backup "
            "ke folder eksternal dengan nama tanggal yang sama. Cara ini mengurangi risiko "
            "backup ikut hilang ketika folder project mengalami kerusakan.",
            style,
        )
    )

    story.append(PageBreak())
    story.append(h1("6. Alur Kerja Sistem Backup", style))
    story.append(
        numbered(
            [
                "Admin atau script terjadwal memanggil proses backup.",
                "BackupService menentukan nama folder berdasarkan tipe backup dan tanggal.",
                "Sistem memastikan folder target lokal tersedia.",
                "Source code dikompres menjadi source_code.zip dengan pengecualian folder data dan backup.",
                "Folder uploads dikompres menjadi uploads.zip.",
                "Folder trash dikompres menjadi trash.zip.",
                "Database simple_cloud diekspor menjadi file SQL.",
                "Semua file backup disalin ke C:\\cloud_storage_backup.",
                "Hasil proses dicatat pada audit log sebagai success atau failed.",
            ],
            style,
        )
    )

    story.append(h1("7. Analisis Keamanan dan Keandalan", style))
    story.append(
        make_table(
            [
                ["Aspek", "Analisis"],
                ["Keamanan endpoint", "backup.php hanya menerima POST, memakai CSRF token, dan dilindungi permission backup."],
                ["Kontrol akses", "Hanya role yang memiliki permission backup dapat menjalankan backup dari dashboard."],
                ["Keandalan zip", "Jika ekstensi ZipArchive tidak tersedia, BackupService memiliki fallback pembuat zip sederhana."],
                ["Audit trail", "Aksi backup manual, scheduled, before update, dan backup trash tercatat pada logs/audit.log."],
                ["Risiko", "External_dir masih berada pada drive C. Untuk produksi, lokasi backup sebaiknya memakai drive berbeda, NAS, atau cloud storage."],
                ["Restore", "Restore dapat dilakukan dengan mengekstrak zip sesuai kebutuhan dan mengimpor file SQL ke database simple_cloud."],
            ],
            [4.0, 12.0],
            style,
        )
    )

    story.append(h1("8. Hasil Pengujian", style))
    story.append(
        make_table(
            [
                ["Skenario", "Hasil"],
                ["Backup manual dari dashboard", "Berhasil membuat source_code.zip, uploads.zip, trash.zip, dan database SQL."],
                ["Backup terjadwal CLI", "Audit log menunjukkan backup_scheduled success pada 2026-05-24 19:34:16."],
                ["Backup ke lokasi berbeda", "File backup ditemukan juga pada C:\\cloud_storage_backup\\2026-05-24\\."],
                ["Delete file", "Audit log menunjukkan file dipindahkan ke trash dan trash.zip diperbarui."],
                ["Database backup", "File SQL berisi header backup, nama database, timestamp, struktur tabel, dan data INSERT."],
            ],
            [5.3, 10.7],
            style,
        )
    )

    story.append(h1("9. Kesimpulan", style))
    story.append(
        body(
            "Fitur Backup Cloud Storage pada Cloudify telah memenuhi seluruh poin tugas. "
            "Sistem mampu membuat backup source code, uploads, trash, dan database dalam "
            "struktur folder berbasis tanggal. Website juga menyediakan backup manual, "
            "backup sebelum update, backup terjadwal melalui CLI, serta pembaruan backup "
            "trash ketika user menghapus file.",
            style,
        )
    )
    story.append(
        body(
            "Secara teknis, implementasi sudah cukup baik untuk lingkungan praktikum karena "
            "menggunakan pemisahan data, validasi akses, CSRF token, audit log, dan salinan "
            "backup pada lokasi berbeda. Untuk pengembangan lanjutan, backup dapat ditambah "
            "fitur rotasi otomatis, notifikasi kegagalan, enkripsi file backup, dan penyimpanan "
            "ke media eksternal atau cloud sungguhan.",
            style,
        )
    )

    story.append(h1("10. Lampiran File Penting", style))
    story.append(
        kv(
            [
                ("Service backup", "src/Services/BackupService.php"),
                ("Endpoint backup manual", "backup.php"),
                ("Script backup terjadwal", "backup_scheduled.php"),
                ("Endpoint delete", "delete.php"),
                ("Konfigurasi backup", "config/app.php"),
                ("Audit log", "logs/audit.log"),
            ],
            style,
        )
    )

    doc.build(story, onFirstPage=footer, onLaterPages=footer)


if __name__ == "__main__":
    build()
    print(OUT)
