# FNC Financial Management System

Sistem manajemen keuangan pribadi yang dikembangkan dengan PHP untuk membantu pengguna mengelola pendapatan, pengeluaran, dan laporan keuangan dengan mudah.

## 🚀 Fitur Utama

### 📊 Dashboard Komprehensif
- Ringkasan keuangan bulanan dan tahunan
- Grafik pendapatan vs pengeluaran
- Transaksi terbaru
- Statistik kategori

### 💰 Manajemen Transaksi
- **Pendapatan**: Catat dan kelola sumber pendapatan
- **Pengeluaran**: Track dan kategorikan pengeluaran
- **Kategori**: Kelola kategori transaksi kustom
- **Multi-mata uang**: Support berbagai mata uang

### 👥 Manajemen Anggota
- Tambah anggota keluarga atau tim
- Kelola peran dan biaya bulanan
- Notifikasi email otomatis

### 📈 Laporan & Statistik
- Laporan keuangan detail
- Statistik per kategori
- Export data (PDF/Excel)
- Analisis tren keuangan

### 🔐 Keamanan
- Enkripsi data AES-256
- CSRF protection
- Session security
- Input sanitization
- Activity logging

### 📧 Notifikasi Email
- Notifikasi login
- Konfirmasi transaksi
- Laporan bulanan otomatis
- Peringatan keamanan

## 🛠️ Teknologi

- **Backend**: PHP 7.4+
- **Frontend**: HTML5, CSS3, JavaScript
- **UI Framework**: AdminLTE
- **Database**: File-based JSON storage
- **Email**: PHPMailer dengan SMTP
- **Security**: Custom security functions

## 📦 Instalasi

### Persyaratan
- PHP 7.4 atau lebih tinggi
- Web server (Apache/Nginx)
- XAMPP (untuk development lokal)
- Akun email SMTP (Gmail, Outlook, dll)

### Langkah Instalasi

1. **Clone atau download repository**
   ```bash
   cd /xampp/htdocs/
   git clone [repository-url] fnc
   ```

2. **Konfigurasi environment**
   ```bash
   cp .env.example .env
   ```

3. **Edit file .env dengan konfigurasi SMTP**
   ```env
   SMTP_HOST=smtp.gmail.com
   SMTP_PORT=587
   SMTP_LOGIN=email@gmail.com
   SMTP_KEY=app-password-gmail
   SENDER_EMAIL=email@gmail.com
   ```

4. **Setup permissions**
   ```bash
   chmod 755 uploads/
   chmod 755 logs/
   chmod 755 user/
   ```

5. **Akses aplikasi**
   - Buka browser: `http://localhost/fnc/`
   - Register akun baru atau login

## 📁 Struktur Direktori

```
fnc/
├── ajax/                # AJAX handlers
├── assets/              # CSS, JS, images
├── auth/                # Authentication logic
├── config/              # Configuration files
├── includes/            # Helper functions & libraries
├── logs/                # Application logs
├── reports/             # Report generation
├── uploads/             # File uploads
├── user/                # User data storage
├── categories.php       # Category management
├── dashboard.php        # Main dashboard
├── expense.php          # Expense management
├── income.php           # Income management
├── members.php          # Member management
├── register.php         # User registration
├── settings.php         # User settings
├── statistics.php       # Financial statistics
└── index.php            # Login page
```

## ⚙️ Konfigurasi

### Email SMTP Setup
1. Enable 2FA pada akun Gmail
2. Generate App Password
3. Update konfigurasi di `.env`
4. Test email di halaman settings

### Security Configuration
- Update `ENCRYPTION_KEY` di `config/config.php`
- Set `session.cookie_secure = 1` untuk HTTPS
- Configure CSP headers sesuai kebutuhan

## 🎯 Kelebihan

### ✅ **Keunggulan Utama**
- **Zero Database**: Tidak memerlukan database MySQL, menggunakan file JSON
- **Lightweight**: Cepat dan ringan, cocok untuk shared hosting
- **Security First**: Multiple layers of security protection
- **User-Friendly**: Interface intuitif dengan AdminLTE
- **Mobile Responsive**: Bekerja baik di desktop dan mobile
- **Offline Capable**: Basic functions work without internet
- **Easy Deployment**: Copy-paste installation

### 🚀 **Fitur Unggulan**
- Real-time dashboard dengan grafik interaktif
- Multi-currency support
- Advanced filtering dan search
- Email notifications otomatis
- Activity logging untuk audit trail
- Export functionality untuk reports

## ⚠️ Kekurangan

### 🔧 **Limitations Teknis**
- **Scalability**: File-based storage tidak ideal untuk high traffic
- **Concurrent Users**: Limited support untuk simultaneous users
- **Data Integrity**: Risk of data corruption pada concurrent writes
- **Backup Complexity**: Manual backup process required
- **Performance**: Slower untuk large datasets

### 📱 **Fitur yang Belum Ada**
- Mobile app (hanya web responsive)
- Real-time synchronization
- API endpoints untuk third-party integration
- Advanced analytics dengan machine learning
- Cloud backup integration
- Multi-language full support

## 🔄 Rencana Update Selanjutnya

### 🚨 **Priority 1: Perbaikan Email Sender**
- **Problem**: Email configuration tidak robust
- **Solutions**:
  - Queue system untuk email delivery
  - Retry mechanism untuk failed emails
  - Email template system yang lebih baik
  - Multi-SMTP provider support
  - Email delivery tracking
  - Bounce handling dan unsubscribe management

### 📱 **Priority 2: Mobile App Development**
- React Native mobile application
- Offline sync capability
- Push notifications
- Biometric authentication

### 🗄️ **Priority 3: Database Migration**
- MySQL/PostgreSQL support option
- Migration tool dari JSON ke SQL
- Hybrid storage system
- Data backup automation

### 🔧 **Priority 4: API Development**
- RESTful API endpoints
- OAuth 2.0 authentication
- API documentation dengan Swagger
- Rate limiting dan throttling

### 📊 **Priority 5: Enhanced Analytics**
- Advanced financial insights
- Budget planning tools
- Investment tracking
- Financial goal setting
- Predictive analytics

## 🐛 Troubleshooting

### Email Tidak Terkirim
```php
// Check SMTP configuration
var_dump(SMTP_HOST, SMTP_PORT, SMTP_USERNAME);

// Test connection
$telnet smtp.gmail.com 587
```

### Permission Issues
```bash
# Fix folder permissions
chmod -R 755 uploads/ logs/ user/
chown -R www-data:www-data uploads/ logs/ user/
```

### Session Problems
- Clear browser cookies
- Check session.save_path in php.ini
- Verify session configuration

## 📝 License

Project ini dilisensi under MIT License. Feel free to use, modify, dan distribute sesuai kebutuhan.

## 🤝 Kontribusi

Contributions are welcome! Please:
1. Fork the repository
2. Create feature branch
3. Submit pull request
4. Follow coding standards

## 📞 Support

Untuk bantuan atau pertanyaan:
- Email: aratakun@arata.my.id
- Issue Tracker: [GitHub Issues]

---

**Note**: Ini adalah versi 1.0.0 dan masih dalam pengembangan aktif. Harap backup data secara rutin dan report bugs jika menemukan masalah.
