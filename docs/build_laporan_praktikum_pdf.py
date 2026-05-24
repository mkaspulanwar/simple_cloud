from __future__ import annotations

from pathlib import Path

from reportlab.lib import colors
from reportlab.lib.enums import TA_CENTER
from reportlab.lib.pagesizes import LETTER
from reportlab.lib.styles import ParagraphStyle, getSampleStyleSheet
from reportlab.lib.units import inch
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
OUT = ROOT / "docs" / "Laporan_Praktikum_Cloudify.pdf"


class FooterCanvas:
    def __init__(self, canvas, doc):
        self.canvas = canvas
        self.doc = doc

    def __call__(self, canvas, doc):
        canvas.saveState()
        canvas.setFont("Helvetica", 8)
        canvas.setFillColor(colors.HexColor("#667085"))
        canvas.drawString(inch, 0.55 * inch, "Laporan Praktikum Cloudify")
        canvas.drawRightString(LETTER[0] - inch, 0.55 * inch, f"Halaman {doc.page}")
        canvas.restoreState()


def styles():
    base = getSampleStyleSheet()
    base.add(
        ParagraphStyle(
            name="CoverTitle",
            parent=base["Title"],
            fontName="Helvetica-Bold",
            fontSize=18,
            leading=22,
            alignment=TA_CENTER,
            textColor=colors.HexColor("#0B2545"),
            spaceAfter=4,
        )
    )
    base.add(
        ParagraphStyle(
            name="CoverSubtitle",
            parent=base["Title"],
            fontName="Helvetica-Bold",
            fontSize=15,
            leading=19,
            alignment=TA_CENTER,
            textColor=colors.HexColor("#2E74B5"),
            spaceAfter=18,
        )
    )
    base["Heading1"].fontName = "Helvetica-Bold"
    base["Heading1"].fontSize = 15
    base["Heading1"].leading = 19
    base["Heading1"].textColor = colors.HexColor("#2E74B5")
    base["Heading1"].spaceBefore = 14
    base["Heading1"].spaceAfter = 7
    base["Heading2"].fontName = "Helvetica-Bold"
    base["Heading2"].fontSize = 12
    base["Heading2"].leading = 15
    base["Heading2"].textColor = colors.HexColor("#2E74B5")
    base["Heading2"].spaceBefore = 10
    base["Heading2"].spaceAfter = 5
    base["BodyText"].fontName = "Helvetica"
    base["BodyText"].fontSize = 10
    base["BodyText"].leading = 14
    base["BodyText"].spaceAfter = 6
    base.add(
        ParagraphStyle(
            name="Small",
            parent=base["BodyText"],
            fontSize=8.5,
            leading=11,
        )
    )
    base.add(
        ParagraphStyle(
            name="TableText",
            parent=base["BodyText"],
            fontSize=8.2,
            leading=10.5,
            spaceAfter=0,
        )
    )
    base.add(
        ParagraphStyle(
            name="TableHeader",
            parent=base["TableText"],
            fontName="Helvetica-Bold",
            textColor=colors.HexColor("#0B2545"),
        )
    )
    return base


def p(text: str, style):
    return Paragraph(text, style)


def h1(text: str, style):
    return Paragraph(text, style["Heading1"])


def h2(text: str, style):
    return Paragraph(text, style["Heading2"])


def body(text: str, style):
    return Paragraph(text, style["BodyText"])


def bullet_list(items: list[str], style):
    return ListFlowable(
        [ListItem(body(item, style), leftIndent=12) for item in items],
        bulletType="bullet",
        leftIndent=18,
        bulletFontName="Helvetica",
        bulletFontSize=8,
        spaceAfter=6,
    )


def numbered_list(items: list[str], style):
    return ListFlowable(
        [ListItem(body(item, style), leftIndent=12) for item in items],
        bulletType="1",
        leftIndent=18,
        bulletFontName="Helvetica",
        bulletFontSize=9,
        spaceAfter=6,
    )


def table(data: list[list[str]], widths: list[float], style):
    rows = []
    for row_index, row in enumerate(data):
        paragraph_style = style["TableHeader"] if row_index == 0 else style["TableText"]
        rows.append([p(str(cell), paragraph_style) for cell in row])
    result = Table(rows, colWidths=[w * inch for w in widths], repeatRows=1, hAlign="LEFT")
    result.setStyle(
        TableStyle(
            [
                ("GRID", (0, 0), (-1, -1), 0.45, colors.HexColor("#D9E2EC")),
                ("BACKGROUND", (0, 0), (-1, 0), colors.HexColor("#F2F4F7")),
                ("VALIGN", (0, 0), (-1, -1), "MIDDLE"),
                ("LEFTPADDING", (0, 0), (-1, -1), 6),
                ("RIGHTPADDING", (0, 0), (-1, -1), 6),
                ("TOPPADDING", (0, 0), (-1, -1), 5),
                ("BOTTOMPADDING", (0, 0), (-1, -1), 5),
            ]
        )
    )
    return result


def kv_table(rows: list[tuple[str, str]], style):
    return table([["Item", "Keterangan"]] + [[a, b] for a, b in rows], [1.65, 4.85], style)


def build() -> None:
    style = styles()
    doc = SimpleDocTemplate(
        str(OUT),
        pagesize=LETTER,
        rightMargin=inch,
        leftMargin=inch,
        topMargin=inch,
        bottomMargin=0.85 * inch,
        title="Laporan Praktikum Cloudify",
        author="Cloudify",
    )
    story: list[Flowable] = []

    story.append(p("LAPORAN PRAKTIKUM", style["CoverTitle"]))
    story.append(p("Mini Project Website Cloud Storage - Cloudify", style["CoverSubtitle"]))
    story.append(
        kv_table(
            [
                ("Nama", "........................................................"),
                ("NIM", "........................................................"),
                ("Kelas", "........................................................"),
                ("Mata Praktikum", "Mini Project Website"),
                ("Tanggal", "24 Mei 2026"),
            ],
            style,
        )
    )
    story.append(Spacer(1, 12))

    story.append(h1("1. Pendahuluan", style))
    story.append(
        body(
            "Cloudify adalah website cloud storage lokal berbasis PHP yang digunakan untuk "
            "mengelola file gambar melalui antarmuka web. Website ini dibuat sebagai mini "
            "project praktikum dengan fokus pada halaman utama, manajemen pengguna berbasis "
            "role, pembatasan akses file, serta validasi upload berdasarkan ukuran, kapasitas, "
            "ekstensi, dan MIME type.",
            style,
        )
    )
    story.append(
        body(
            "Tujuan praktikum ini adalah menerapkan konsep aplikasi web dinamis yang mampu "
            "menerima unggahan file, menampilkan file dalam bentuk galeri, mengatur hak akses "
            "pengguna, dan menjaga keamanan dasar melalui autentikasi, CSRF token, sanitasi "
            "nama file, serta pencatatan audit.",
            style,
        )
    )

    story.append(h1("2. Identitas Sistem", style))
    story.append(
        kv_table(
            [
                ("Nama aplikasi", "Cloudify"),
                ("Jenis aplikasi", "Website cloud storage / galeri file lokal"),
                ("Teknologi", "PHP, MySQL/MariaDB, HTML, CSS, JavaScript, XAMPP"),
                ("Folder upload", "uploads/"),
                ("Database", "simple_cloud"),
                ("Zona waktu", "Asia/Makassar"),
            ],
            style,
        )
    )

    story.append(h1("3. Pemetaan Kebutuhan Tugas", style))
    story.append(
        table(
            [
                ["No", "Kebutuhan Tugas", "Implementasi Pada Website", "Status"],
                ["1", "Membuat home page website", "Tersedia pada index.php berupa landing/gallery page publik dengan preview gambar dan akses menuju login atau dashboard.", "Terpenuhi"],
                ["2", "Admin mengelola semua pengguna dan semua file", "Role superadmin/admin dapat mengakses seluruh file. Superadmin memiliki modul manage_users melalui manage_user.php.", "Terpenuhi, dengan catatan admin biasa fokus pada semua file sedangkan superadmin mengelola user."],
                ["3", "User upload, download, dan hapus file miliknya sendiri", "Role user memiliki permission upload, download, delete, rename. FileOwnershipStore membatasi akses berdasarkan owner_id.", "Terpenuhi"],
                ["4", "Viewer hanya melihat dan mengunduh file tertentu", "Role viewer belum dibuat eksplisit. Fungsi serupa dapat dipenuhi oleh public gallery/download public atau perlu penambahan role viewer pada AuthManager, UserStore, dan database enum.", "Perlu penyelarasan"],
                ["5", "Guest akses terbatas atau hanya melihat halaman tertentu", "Role guest hanya memiliki permission home dan dashboard. Guest tidak dapat upload, download internal, rename, atau delete file.", "Terpenuhi"],
                ["6", "Batas kapasitas dan jenis file upload", "config/app.php membatasi ukuran file 10 MB, kapasitas storage 1 GB, dan whitelist png, jpg, jpeg, gif, webp disertai validasi MIME.", "Terpenuhi"],
            ],
            [0.45, 1.55, 3.25, 1.25],
            style,
        )
    )

    story.append(h1("4. Analisis Fitur", style))
    story.append(h2("4.1 Home Page", style))
    story.append(body("Halaman utama pada index.php berfungsi sebagai etalase publik Cloudify. Konten file ditampilkan dalam bentuk galeri gambar sehingga pengguna dapat melihat isi storage tanpa langsung masuk ke dashboard. Halaman ini juga menyediakan preview modal dan tombol download publik untuk file gambar yang ditampilkan.", style))
    story.append(bullet_list(["Menjadi halaman pertama yang memperkenalkan aplikasi dan isi storage.", "Menampilkan file gambar dari folder uploads dengan metadata ukuran dan waktu modifikasi.", "Mengarahkan pengguna login ke dashboard dan pengguna baru ke halaman autentikasi."], style))

    story.append(h2("4.2 Autentikasi dan Level Pengguna", style))
    story.append(body("Pengaturan hak akses utama berada pada AuthManager::roleAllows(). Sistem menggunakan session untuk menyimpan id, nama, dan role pengguna setelah login. Password diverifikasi dengan password_verify sehingga password tidak dibandingkan dalam bentuk teks biasa.", style))
    story.append(
        table(
            [
                ["Role", "Hak Akses Utama", "Penjelasan"],
                ["Superadmin", "Semua modul termasuk manage_users", "Mengelola struktur pengguna, file, audit, upload, download, rename, dan delete."],
                ["Admin", "Semua file dan audit", "Mengelola semua file di storage, termasuk rename, download, dan delete."],
                ["User", "File milik sendiri", "Dapat upload dan mengelola file dengan owner_id yang sama dengan akunnya."],
                ["Guest", "Home dan dashboard terbatas", "Tidak memiliki akses upload, download internal, rename, atau delete."],
                ["Viewer", "Belum eksplisit", "Sebaiknya ditambahkan untuk memenuhi instruksi tugas secara penuh."],
            ],
            [1.05, 2.0, 3.45],
            style,
        )
    )

    story.append(h2("4.3 Upload dan Validasi File", style))
    story.append(body("Proses upload dilakukan melalui upload.php dan diproses oleh CloudStorageService. Validasi dilakukan berlapis agar file yang masuk sesuai kebijakan sistem.", style))
    story.append(numbered_list(["Memastikan request upload menggunakan metode POST.", "Memvalidasi CSRF token agar aksi upload berasal dari form yang sah.", "Memeriksa error upload dari PHP, ukuran file, kapasitas storage, dan sumber temporary file.", "Membersihkan nama file agar tidak mengandung karakter berbahaya atau path traversal.", "Memeriksa ekstensi file terhadap whitelist png, jpg, jpeg, gif, dan webp.", "Memeriksa MIME type agar isi file sesuai dengan ekstensi yang diklaim.", "Mencatat pemilik file ke tabel files dan menulis event upload ke audit log."], style))
    story.append(kv_table([("Maksimum per file", "10 MB"), ("Maksimum storage", "1 GB"), ("Ekstensi diizinkan", "png, jpg, jpeg, gif, webp"), ("Validasi tambahan", "MIME type, ukuran, kapasitas, nama file aman, dan file upload valid")], style))

    story.append(h2("4.4 Manajemen File", style))
    story.append(body("Dashboard menyediakan daftar file dalam mode list dan grid. Pengguna dapat melakukan download, rename, dan delete sesuai hak akses. Admin/superadmin dapat melihat semua file, sedangkan user hanya melihat file yang tercatat sebagai miliknya.", style))
    story.append(bullet_list(["Download melalui download.php dengan pengecekan permission dan kepemilikan file.", "Delete melalui delete.php hanya menerima POST dan wajib CSRF token.", "Rename menjaga ekstensi file agar tidak berubah dan nama tujuan tidak bentrok.", "Preview gambar disediakan melalui preview.php untuk MIME gambar yang valid."], style))

    story.append(h2("4.5 Audit dan Keamanan", style))
    story.append(body("Website mencatat aktivitas penting seperti upload, download, delete, dan manajemen pengguna. AuditLogger menyimpan timestamp, aksi, status, user, IP, user agent, dan konteks tambahan ke logs/audit.log. Dari sisi keamanan, sistem memakai CSRF token, session login, sanitasi nama file, validasi MIME type, dan pembatasan akses berbasis role.", style))

    story.append(h1("5. Alur Kerja Sistem", style))
    story.append(numbered_list(["Pengunjung membuka home page Cloudify dan melihat galeri publik.", "Pengguna login melalui login.php menggunakan akun yang tersimpan di database.", "AuthManager menyimpan session dan mengecek permission role.", "Pengguna yang memiliki izin upload memilih file gambar dari dashboard.", "CloudStorageService memvalidasi ukuran, kapasitas, ekstensi, MIME, dan nama file.", "File yang valid dipindahkan ke uploads/ dan metadata owner dicatat ke tabel files.", "Aksi pengguna seperti upload, download, rename, dan delete dicatat ke audit log."], style))

    story.append(h1("6. Pengujian Fitur", style))
    story.append(
        table(
            [
                ["Skenario", "Langkah Uji", "Hasil yang Diharapkan"],
                ["Home page", "Buka http://localhost/simple_cloud/", "Galeri publik tampil dan tombol login/dashboard tersedia."],
                ["Login admin", "Masuk sebagai superadmin/admin.", "Dashboard menampilkan seluruh file dan kontrol manajemen sesuai role."],
                ["Login user", "Masuk sebagai user lalu upload gambar valid.", "File berhasil masuk dan owner_id user tercatat."],
                ["Guest", "Masuk sebagai guest.", "Panel upload tidak tersedia dan akses file dibatasi."],
                ["Upload melebihi batas", "Upload file lebih dari 10 MB.", "Sistem menolak dengan pesan ukuran melebihi batas."],
                ["Upload ekstensi salah", "Upload file selain png, jpg, jpeg, gif, webp.", "Sistem menolak karena ekstensi/MIME tidak diizinkan."],
                ["Delete file", "User mencoba hapus file miliknya.", "File terhapus, metadata dihapus, dan audit log tercatat."],
            ],
            [1.3, 2.65, 2.55],
            style,
        )
    )

    story.append(h1("7. Evaluasi dan Rekomendasi", style))
    story.append(body("Secara umum Cloudify sudah memenuhi sebagian besar batasan mini project. Fitur home page, upload file, pembatasan ukuran dan tipe file, dashboard, audit, serta pembatasan akses admin, user, dan guest telah tersedia. Catatan utama berada pada role viewer karena instruksi tugas menyebut viewer sebagai level pengguna tersendiri, sedangkan kode saat ini belum mendefinisikan role tersebut.", style))
    story.append(bullet_list(["Tambahkan role viewer ke enum database users, UserStore::ROLES, dan AuthManager::roleAllows().", "Berikan viewer permission dashboard dan download tanpa upload, rename, atau delete.", "Tambahkan mekanisme daftar file tertentu untuk viewer, misalnya tabel file_shares berisi viewer_id dan file_name.", "Sesuaikan halaman manajemen user agar superadmin dapat memilih role viewer.", "Update README agar batas upload mengikuti konfigurasi terbaru, yaitu 10 MB dan hanya file gambar."], style))

    story.append(h1("8. Kesimpulan", style))
    story.append(body("Mini project Cloudify menunjukkan penerapan website cloud storage sederhana dengan antarmuka home page, dashboard manajemen file, autentikasi, role-based access control, validasi upload, dan audit aktivitas. Sistem sudah layak sebagai praktikum dasar manajemen file berbasis web. Agar sepenuhnya selaras dengan instruksi tugas, pengembangan berikutnya perlu menambahkan role viewer eksplisit yang hanya dapat melihat dan mengunduh file tertentu.", style))

    story.append(h1("Lampiran: File Penting", style))
    story.append(
        table(
            [
                ["File", "Fungsi"],
                ["index.php", "Home page publik dan galeri visual."],
                ["dashboard.php", "Dashboard utama setelah login."],
                ["upload.php", "Endpoint upload file."],
                ["download.php", "Endpoint download dan inline preview."],
                ["delete.php", "Endpoint hapus file dengan POST dan CSRF."],
                ["src/Security/AuthManager.php", "Autentikasi dan permission role."],
                ["src/Services/CloudStorageService.php", "Storage, validasi upload, list, rename, delete."],
                ["src/Services/FileOwnershipStore.php", "Pengecekan kepemilikan dan metadata file."],
                ["src/Services/UserStore.php", "Manajemen akun pengguna dari database."],
                ["config/app.php", "Konfigurasi batas file, kapasitas, dan MIME whitelist."],
            ],
            [2.35, 4.15],
            style,
        )
    )

    doc.build(story, onFirstPage=FooterCanvas(None, None), onLaterPages=FooterCanvas(None, None))
    print(OUT)


if __name__ == "__main__":
    build()
