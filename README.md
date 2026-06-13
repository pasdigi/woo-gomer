# Woo Gomer QRIS - WooCommerce Payment Gateway

Plugin ini bekerja khusus untuk panel Gopay Merchant Custom API.

## 🌟 Fitur
* Tanpa perlu nominal/kode unik di tagihan (misalkan pesanan Rp. 1000, pembeli hanya bayar Rp. 1000.)

---

## 🛠️ Panduan Instalasi

1. Pastikan folder plugin bernama `woo-gomer` dan kompres menjadi file `woo-gomer.zip`.
2. Buka dashboard WordPress Anda, navigasi ke menu **Plugins > Add New > Upload Plugin**.
3. Pilih file `woo-gomer.zip` dan klik **Install Now**.
4. Klik **Activate** untuk mengaktifkan plugin.

---

## ⚙️ Cara Pengaturan di WooCommerce

Setelah plugin aktif, Anda hanya perlu mengatur beberapa kolom dasar agar sistem langsung terhubung dengan API Anda.

1. Buka dashboard WordPress, navigasi ke **WooCommerce > Settings**.
2. Klik tab **Payments**.
3. Cari metode pembayaran **QRIS Gopay Merchant** (Woo Gomer QRIS), lalu aktifkan *toggle*-nya dan klik tombol **Manage**.
4. Isi pengaturan berikut:
   * **Enable/Disable:** Pastikan dicentang untuk mengaktifkan QRIS di halaman checkout.
   * **Title & Description:** Sesuaikan nama dan deskripsi pembayaran yang akan dilihat oleh pembeli.
   * **API Domain URL:** Masukkan domain URL API Anda. *(Catatan untuk domain formatnya seperti `https://customapi.pages.dev`, karena kita menggunakan subdomain di cloudflare pages)*.
   * **API Key / Webhook Secret:** Masukkan 1 kunci rahasia Anda ke kolom ini.
   * **Logo Website:** Klik tombol **Upload/Pilih Logo** untuk memasukkan logo bisnis Anda (rekomendasi ukuran: 150x50px) agar tampil cantik di halaman pembayaran QRIS.
5. Klik **Save changes**.

Selesai! Sistem WooCommerce Anda sekarang sudah terintegrasi penuh dan siap menerima pembayaran via QRIS secara otomatis.

---

**Dukungan & Panel API**
Untuk mendapatkan panel Gopay Merchant Custom API, silahkan hubungi pengembang di https://fb.me/kangarifar
Lisensi script panel hanya Rp. 200.000,- sekali bayar untuk 1 panel dengan 1 Merchant yang dapat digunakan untuk unlimited website.
