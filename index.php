<?php
session_start();

// 1. Konfigurasi Database SQLite
$db_file = __DIR__ . '/jadwal.sqlite';
$pdo = new PDO('sqlite:' . $db_file);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Buat tabel jika belum ada
$pdo->exec("CREATE TABLE IF NOT EXISTS jadwal (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    judul TEXT NOT NULL,
    deskripsi TEXT,
    tenggat_waktu DATE,
    tingkat_kesulitan TEXT,
    dibuat_pada DATETIME DEFAULT CURRENT_TIMESTAMP
)");

// 2. Muat Kredensial Admin dari .env
function loadEnvFile($path) {
    if (!is_file($path)) {
        return [];
    }
    $vars = [];
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, '#') === 0 || strpos($line, '=') === false) {
            continue;
        }
        list($key, $value) = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        $len = strlen($value);
        if ($len >= 2 && (($value[0] === '"' && $value[$len - 1] === '"') || ($value[0] === "'" && $value[$len - 1] === "'"))) {
            $value = substr($value, 1, -1);
        }
        $vars[$key] = $value;
    }
    return $vars;
}

$env = loadEnvFile(__DIR__ . '/.env');
$admin_user = $env['ADMIN_USER'] ?? (getenv('ADMIN_USER') ?: '');
$admin_pass = $env['ADMIN_PASS'] ?? (getenv('ADMIN_PASS') ?: '');

// 3. Handle Login & Logout
if (isset($_POST['login'])) {
    $input_user = (string)($_POST['username'] ?? '');
    $input_pass = (string)($_POST['password'] ?? '');

    if ($admin_user !== '' && $admin_pass !== '' && hash_equals($admin_user, $input_user) && hash_equals($admin_pass, $input_pass)) {
        $_SESSION['logged_in'] = true;
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    } else {
        $error = ($admin_user === '' || $admin_pass === '')
            ? "Konfigurasi kredensial (.env) belum diatur!"
            : "Username atau password salah!";
    }
}

if (isset($_GET['action']) && $_GET['action'] == 'logout') {
    session_destroy();
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// 4. Handle CRUD Jadwal
if (isset($_SESSION['logged_in'])) {
    if (isset($_POST['tambah_jadwal'])) {
        $stmt = $pdo->prepare("INSERT INTO jadwal (judul, deskripsi, tenggat_waktu, tingkat_kesulitan) VALUES (?, ?, ?, ?)");
        $stmt->execute([
            $_POST['judul'], 
            $_POST['deskripsi'], 
            $_POST['tenggat_waktu'], 
            $_POST['tingkat_kesulitan']
        ]);
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }

    if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
        $stmt = $pdo->prepare("DELETE FROM jadwal WHERE id = ?");
        $stmt->execute([$_GET['id']]);
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }
}

// Fungsi Helper Format Tanggal Indonesia dengan Nama Hari
function formatTanggalIndonesia($tanggal, $singkat = false) {
    if (!$tanggal) return '-';
    $hari = [
        'Sunday' => 'Minggu',
        'Monday' => 'Senin',
        'Tuesday' => 'Selasa',
        'Wednesday' => 'Rabu',
        'Thursday' => 'Kamis',
        'Friday' => 'Jumat',
        'Saturday' => 'Sabtu'
    ];
    $bulanPanjang = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni',
        7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
    ];
    $bulanSingkat = [
        1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'Mei', 6 => 'Jun',
        7 => 'Jul', 8 => 'Agu', 9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Des'
    ];
    $timestamp = strtotime($tanggal);
    $namaHari = $hari[date('l', $timestamp)] ?? date('l', $timestamp);
    $d = date('d', $timestamp);
    $m = $singkat ? ($bulanSingkat[(int)date('n', $timestamp)] ?? date('M', $timestamp)) : ($bulanPanjang[(int)date('n', $timestamp)] ?? date('F', $timestamp));
    $y = date('Y', $timestamp);
    return "$namaHari, $d $m $y";
}

// Ambil Data Jadwal (Urutan: Paling Baru Dibuat Berada Paling Atas)
$jadwals = [];
if (isset($_SESSION['logged_in'])) {
    $stmt = $pdo->query("SELECT * FROM jadwal ORDER BY dibuat_pada DESC, id DESC");
    $jadwals = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kartala - Manajemen Jadwal</title>
    <!-- Script Deteksi Tema Cepat (Mencegah Flash of Wrong Theme) -->
    <script>
        (function() {
            const saved = localStorage.getItem('kartala_theme') || 'system';
            const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            if (saved === 'dark' || (saved === 'system' && prefersDark)) {
                document.documentElement.classList.add('dark');
            } else {
                document.documentElement.classList.remove('dark');
            }
        })();
    </script>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        ios: {
                            blue: '#007aff',
                            red: '#ff3b30'
                        }
                    }
                }
            }
        }
    </script>
    <style>
        /* Tipografi ala Apple */
        body {
            font-family: -apple-system, BlinkMacSystemFont, "SF Pro Text", "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            -webkit-font-smoothing: antialiased;
        }

        #print-area { display: none; }

        .ios-input {
            transition: all 0.2s;
        }
        .ios-input:focus {
            box-shadow: 0 0 0 2px #007aff;
            outline: none;
        }

        .ios-checkbox {
            appearance: none;
            background-color: #ffffff;
            border: 1px solid #d1d1d6;
            margin: 0;
            font: inherit;
            color: currentColor;
            width: 1.5em;
            height: 1.5em;
            border-radius: 50%;
            display: grid;
            place-content: center;
            transition: 0.2s all ease-in-out;
            cursor: pointer;
        }
        .dark .ios-checkbox {
            background-color: #2c2c2e;
            border-color: #48484a;
        }
        .ios-checkbox::before {
            content: "";
            width: 0.8em;
            height: 0.8em;
            transform: scale(0);
            transition: 120ms transform ease-in-out;
            box-shadow: inset 1em 1em white;
            background-color: white;
            transform-origin: center;
            clip-path: polygon(14% 44%, 0 65%, 50% 100%, 100% 16%, 80% 0%, 43% 62%);
        }
        .ios-checkbox:checked {
            background-color: #007aff;
            border-color: #007aff;
        }
        .ios-checkbox:checked::before {
            transform: scale(1);
        }

        /* Konfigurasi Print (HASIL CETAK TETAP LIGHT MODE) */
        @media print {
            html, body {
                background-color: #ffffff !important;
                color: #111827 !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            body * { visibility: hidden; }
            #print-area, #print-area * { visibility: visible; }
            #print-area {
                display: flex !important;
                flex-wrap: wrap;
                gap: 12px;
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
                background-color: #ffffff !important;
                color-scheme: light !important;
            }
            .print-card {
                box-shadow: none !important;
                border-radius: 12px;
                page-break-inside: avoid;
                padding: 12px;
                box-sizing: border-box;
                overflow: hidden;
                color: #111827 !important;
                background-color: #ffffff !important;
                border-width: 1.5px !important;
                display: flex !important;
                flex-direction: column !important;
                justify-content: space-between !important;
            }
            .print-card.card-mudah {
                background-color: #f0fdf4 !important;
                border-color: #86efac !important;
            }
            .print-card.card-sedang {
                background-color: #fff7ed !important;
                border-color: #fdba74 !important;
            }
            .print-card.card-sulit {
                background-color: #fef2f2 !important;
                border-color: #fca5a5 !important;
            }
            .print-card .card-title {
                color: #111827 !important;
            }
            .print-card .card-desc {
                color: #374151 !important;
                display: block !important;
                -webkit-line-clamp: unset !important;
                line-clamp: unset !important;
                height: auto !important;
                overflow: visible !important;
            }
            .print-card .card-footer {
                border-top: 1px solid rgba(0, 0, 0, 0.08) !important;
            }
            .print-card .card-date {
                color: #374151 !important;
                background-color: rgba(0, 0, 0, 0.06) !important;
            }
            .print-card .card-badge-mudah {
                background-color: #bbf7d0 !important;
                color: #166534 !important;
            }
            .print-card .card-badge-sedang {
                background-color: #fed7aa !important;
                color: #9a3412 !important;
            }
            .print-card .card-badge-sulit {
                background-color: #fecaca !important;
                color: #991b1b !important;
            }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body class="bg-[#f2f2f7] dark:bg-[#000000] text-gray-900 dark:text-gray-100 min-h-screen pb-10 transition-colors duration-200">

<?php if (!isset($_SESSION['logged_in'])): ?>
    <!-- HALAMAN LOGIN -->
    <div class="min-h-screen flex items-center justify-center p-4">
        <div class="bg-white dark:bg-[#1c1c1e] p-8 rounded-[24px] shadow-[0_8px_30px_rgb(0,0,0,0.04)] border border-gray-100 dark:border-[#2c2c2e] w-full max-w-sm transition-colors duration-200">
            <div class="flex justify-center mb-6">
                <div class="w-16 h-16 bg-[#007aff] rounded-2xl flex items-center justify-center shadow-md">
                    <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                </div>
            </div>
            <h2 class="text-2xl font-bold mb-6 text-center text-gray-900 dark:text-white tracking-tight">Login ke Kartala</h2>
            
            <?php if(isset($error)): ?>
                <div class="bg-[#ff3b30]/10 text-[#ff3b30] p-3 rounded-xl mb-4 text-sm font-medium text-center"><?= $error ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="mb-4">
                    <input type="text" name="username" placeholder="Username" class="ios-input bg-[#f2f2f7] dark:bg-[#2c2c2e] text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 border-none w-full px-4 py-3 rounded-xl text-[17px]" required>
                </div>
                <div class="mb-6">
                    <input type="password" name="password" placeholder="Password" class="ios-input bg-[#f2f2f7] dark:bg-[#2c2c2e] text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 border-none w-full px-4 py-3 rounded-xl text-[17px]" required>
                </div>
                <button type="submit" name="login" class="w-full bg-[#007aff] hover:bg-[#005bb5] text-white font-semibold py-3 px-4 rounded-xl transition text-[17px]">
                    Masuk
                </button>
            </form>
        </div>
    </div>

<?php else: ?>
    <!-- NAVBAR -->
    <nav class="sticky top-0 z-50 bg-white/80 dark:bg-[#1c1c1e]/80 backdrop-blur-md border-b border-gray-200/50 dark:border-white/10 no-print transition-colors duration-200">
        <div class="max-w-6xl mx-auto px-4 py-3 flex flex-wrap justify-between items-center gap-3">
            <div class="flex items-center gap-3 sm:gap-6">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 bg-[#007aff] rounded-lg flex items-center justify-center shadow-sm">
                        <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                    </div>
                    <h1 class="text-xl font-bold tracking-tight text-gray-900 dark:text-white">Kartala</h1>
                </div>

                <!-- Navigasi Halaman -->
                <div class="flex items-center bg-gray-100/90 dark:bg-[#2c2c2e] p-1 rounded-xl text-xs sm:text-sm font-medium border border-gray-200/40 dark:border-white/5">
                    <a href="index.php" class="px-3 py-1.5 rounded-lg bg-white dark:bg-[#3a3a3c] shadow-sm text-gray-900 dark:text-white font-semibold transition flex items-center gap-1.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"></path></svg>
                        <span>Daftar Rencana</span>
                    </a>
                    <a href="kalender.php" class="px-3 py-1.5 rounded-lg text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white transition flex items-center gap-1.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                        <span>Kalender</span>
                    </a>
                </div>
            </div>

            <div class="flex items-center gap-2 sm:gap-3">
                <!-- Theme Switcher (Sistem / Terang / Gelap) -->
                <div class="flex items-center bg-gray-100 dark:bg-[#2c2c2e] p-1 rounded-xl text-xs font-medium border border-gray-200/50 dark:border-white/10">
                    <button type="button" onclick="setTheme('system')" id="theme-btn-system" class="theme-btn px-2 sm:px-2.5 py-1 rounded-lg transition-all flex items-center gap-1" title="Mengikuti Pengaturan Browser">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                        <span class="hidden sm:inline">Auto</span>
                    </button>
                    <button type="button" onclick="setTheme('light')" id="theme-btn-light" class="theme-btn px-2 sm:px-2.5 py-1 rounded-lg transition-all flex items-center gap-1" title="Mode Terang">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                        <span class="hidden sm:inline">Terang</span>
                    </button>
                    <button type="button" onclick="setTheme('dark')" id="theme-btn-dark" class="theme-btn px-2 sm:px-2.5 py-1 rounded-lg transition-all flex items-center gap-1" title="Mode Gelap">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"></path></svg>
                        <span class="hidden sm:inline">Gelap</span>
                    </button>
                </div>

                <a href="?action=logout" class="text-[#ff3b30] hover:bg-[#ff3b30]/10 px-3 py-1.5 rounded-full text-xs sm:text-sm font-semibold transition">Keluar</a>
            </div>
        </div>
    </nav>

    <div class="max-w-6xl mx-auto mt-6 px-4 no-print flex flex-col md:flex-row gap-6">
        
        <!-- KOLOM KIRI -->
        <div class="md:w-[35%] flex flex-col gap-6">
            <!-- Form Tugas Baru -->
            <div class="bg-white dark:bg-[#1c1c1e] p-5 rounded-[20px] shadow-[0_2px_10px_rgb(0,0,0,0.03)] border border-gray-100/70 dark:border-[#2c2c2e] transition-colors duration-200">
                <h2 class="text-lg font-bold mb-4 tracking-tight text-gray-900 dark:text-white">Tugas Baru</h2>
                <form method="POST">
                    <div class="mb-3">
                        <input type="text" name="judul" placeholder="Judul Tugas" class="ios-input bg-[#f2f2f7] dark:bg-[#2c2c2e] text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 border-none w-full px-4 py-3 rounded-xl text-[15px]" required>
                    </div>
                    <div class="mb-3">
                        <textarea name="deskripsi" placeholder="Catatan atau deskripsi..." class="ios-input bg-[#f2f2f7] dark:bg-[#2c2c2e] text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 border-none w-full px-4 py-3 rounded-xl text-[15px]" rows="3" required></textarea>
                    </div>
                    <div class="mb-3">
                        <input type="date" name="tenggat_waktu" class="ios-input bg-[#f2f2f7] dark:bg-[#2c2c2e] text-gray-700 dark:text-gray-200 border-none w-full px-4 py-3 rounded-xl text-[15px]" required>
                    </div>
                    <div class="mb-4">
                        <select name="tingkat_kesulitan" class="ios-input bg-[#f2f2f7] dark:bg-[#2c2c2e] text-gray-700 dark:text-gray-200 border-none w-full px-4 py-3 rounded-xl text-[15px]">
                            <option value="Mudah">🟢 Mudah</option>
                            <option value="Sedang">🟠 Sedang</option>
                            <option value="Sulit">🔴 Sulit</option>
                        </select>
                    </div>
                    <button type="submit" name="tambah_jadwal" class="w-full bg-[#007aff] hover:bg-[#005bb5] text-white font-semibold py-3 px-4 rounded-xl transition">
                        Tambahkan
                    </button>
                </form>
            </div>

            <!-- Print Control -->
            <div class="bg-white dark:bg-[#1c1c1e] p-5 rounded-[20px] shadow-[0_2px_10px_rgb(0,0,0,0.03)] border border-gray-100/70 dark:border-[#2c2c2e] relative overflow-hidden transition-colors duration-200">
                <div class="absolute top-0 right-0 p-4 opacity-5 dark:opacity-10 pointer-events-none text-gray-900 dark:text-white">
                    <svg class="w-16 h-16" fill="currentColor" viewBox="0 0 24 24"><path d="M19 8H5V3H19V8ZM16 5H8V6H16V5ZM21 10C21.5523 10 22 10.4477 22 11V18H18V21H6V18H2V11C2 10.4477 2.44772 10 3 10H21ZM16 16H8V19H16V16ZM18 12C18.5523 12 19 12.4477 19 13C19 13.5523 18.5523 14 18 14C17.4477 14 17 13.5523 17 13C17 12.4477 17.4477 12 18 12Z"></path></svg>
                </div>
                <h2 class="text-lg font-bold mb-1 tracking-tight text-gray-900 dark:text-white">Print Layout</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">Centang kartu tugas, atur ukuran & font.</p>
                
                <!-- Dimensi Kartu: Default Lebar 10cm, Tinggi 8cm -->
                <div class="flex gap-3 mb-4">
                    <div class="w-1/2">
                        <label class="block text-[13px] font-semibold text-gray-400 dark:text-gray-400 mb-1">Lebar (cm)</label>
                        <input type="number" id="print_w" value="10" step="0.5" class="ios-input bg-[#f2f2f7] dark:bg-[#2c2c2e] text-gray-900 dark:text-white border-none w-full px-3 py-2 rounded-lg font-medium text-center">
                    </div>
                    <div class="w-1/2">
                        <label class="block text-[13px] font-semibold text-gray-400 dark:text-gray-400 mb-1">Tinggi (cm)</label>
                        <input type="number" id="print_h" value="8" step="0.5" class="ios-input bg-[#f2f2f7] dark:bg-[#2c2c2e] text-gray-900 dark:text-white border-none w-full px-3 py-2 rounded-lg font-medium text-center">
                    </div>
                </div>

                <!-- Pengatur Ukuran Font Print -->
                <div class="mb-5 bg-gray-50 dark:bg-[#252527] p-3 rounded-xl border border-gray-100 dark:border-white/5">
                    <div class="flex justify-between items-center mb-1.5">
                        <label class="block text-[13px] font-semibold text-gray-500 dark:text-gray-400">Ukuran Font Cetak</label>
                        <span id="font_val" class="text-xs font-bold text-[#007aff]">14px (Normal)</span>
                    </div>
                    <input type="range" id="print_font_size" min="10" max="22" value="14" step="1" 
                           oninput="updatePrintFontSize(this.value)" 
                           class="w-full accent-[#007aff] cursor-pointer">
                    <div class="flex justify-between items-center gap-1.5 mt-2">
                        <button type="button" onclick="updatePrintFontSize(11)" class="flex-1 py-1 text-[11px] font-medium rounded-md bg-white dark:bg-[#343438] hover:bg-gray-100 dark:hover:bg-[#404044] text-gray-700 dark:text-gray-200 border border-gray-200/60 dark:border-white/5 transition text-center shadow-xs">Kecil</button>
                        <button type="button" onclick="updatePrintFontSize(14)" class="flex-1 py-1 text-[11px] font-medium rounded-md bg-white dark:bg-[#343438] hover:bg-gray-100 dark:hover:bg-[#404044] text-gray-700 dark:text-gray-200 border border-gray-200/60 dark:border-white/5 transition text-center shadow-xs">Normal</button>
                        <button type="button" onclick="updatePrintFontSize(16)" class="flex-1 py-1 text-[11px] font-medium rounded-md bg-white dark:bg-[#343438] hover:bg-gray-100 dark:hover:bg-[#404044] text-gray-700 dark:text-gray-200 border border-gray-200/60 dark:border-white/5 transition text-center shadow-xs">Besar</button>
                        <button type="button" onclick="updatePrintFontSize(18)" class="flex-1 py-1 text-[11px] font-medium rounded-md bg-white dark:bg-[#343438] hover:bg-gray-100 dark:hover:bg-[#404044] text-gray-700 dark:text-gray-200 border border-gray-200/60 dark:border-white/5 transition text-center shadow-xs">Ekstra</button>
                    </div>
                </div>

                <button onclick="printSelected()" class="w-full bg-gray-900 hover:bg-black dark:bg-white dark:hover:bg-gray-100 dark:text-gray-900 text-white font-semibold py-3 px-4 rounded-xl transition flex items-center justify-center gap-2 shadow-sm">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                    <span>Cetak Kartu</span>
                </button>
            </div>
        </div>

        <!-- KOLOM KANAN: Daftar Card (Terbaru di Paling Atas) -->
        <div class="md:w-[65%]">
            <div class="flex justify-between items-center mb-4 ml-1">
                <h2 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">Daftar Rencana</h2>
                <span class="text-xs font-semibold text-gray-500 dark:text-gray-400 bg-gray-200/70 dark:bg-white/10 px-2.5 py-1 rounded-full">
                    <?= count($jadwals) ?> Tugas
                </span>
            </div>
            
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                
                <?php if (empty($jadwals)): ?>
                    <div class="col-span-full bg-white dark:bg-[#1c1c1e] p-8 rounded-[20px] text-center border border-dashed border-gray-300 dark:border-gray-700 text-gray-400 dark:text-gray-500">
                        Belum ada jadwal rencana.
                    </div>
                <?php endif; ?>

                <?php foreach ($jadwals as $row): 
                    // Set Default Warna Card (Light & Dark)
                    $badge_style = 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300';
                    $card_style = 'bg-white border-gray-100/60 dark:bg-[#1c1c1e] dark:border-[#2c2c2e]';
                    $difficulty_class = 'card-default';
                    $badge_class = 'card-badge-default';

                    // Kondisi Pewarnaan Pastel Berdasarkan Kesulitan
                    if ($row['tingkat_kesulitan'] == 'Mudah') {
                        $badge_style = 'bg-green-100 text-green-800 dark:bg-green-950/80 dark:text-green-300 border border-green-200/50 dark:border-green-800/40 card-badge-mudah';
                        $card_style = 'bg-green-50/70 border-green-200 dark:bg-[#122416] dark:border-green-900/40';
                        $difficulty_class = 'card-mudah';
                        $badge_class = 'card-badge-mudah';
                    }
                    if ($row['tingkat_kesulitan'] == 'Sedang') {
                        $badge_style = 'bg-orange-100 text-orange-800 dark:bg-orange-950/80 dark:text-orange-300 border border-orange-200/50 dark:border-orange-800/40 card-badge-sedang';
                        $card_style = 'bg-orange-50/70 border-orange-200 dark:bg-[#281c12] dark:border-orange-900/40';
                        $difficulty_class = 'card-sedang';
                        $badge_class = 'card-badge-sedang';
                    }
                    if ($row['tingkat_kesulitan'] == 'Sulit') {
                        $badge_style = 'bg-red-100 text-red-800 dark:bg-red-950/80 dark:text-red-300 border border-red-200/50 dark:border-red-800/40 card-badge-sulit';
                        $card_style = 'bg-red-50/70 border-red-200 dark:bg-[#281315] dark:border-red-900/40';
                        $difficulty_class = 'card-sulit';
                        $badge_class = 'card-badge-sulit';
                    }
                ?>
                    <!-- Card Dengan Latar Warna Pastel -->
                    <div class="<?= $card_style ?> <?= $difficulty_class ?> rounded-[20px] shadow-[0_2px_8px_rgb(0,0,0,0.04)] p-5 relative flex flex-col justify-between hover:shadow-[0_8px_20px_rgb(0,0,0,0.08)] transition-all duration-300 task-card border">
                        
                        <!-- Checkbox (Pojok Kanan Atas) -->
                        <div class="absolute top-4 right-4 card-actions">
                            <input type="checkbox" class="ios-checkbox task-checkbox" title="Pilih untuk di-print">
                        </div>

                        <div>
                            <div class="mb-3">
                                <span class="card-badge <?= $badge_class ?> text-[12px] font-bold px-2.5 py-1 rounded-md inline-block <?= $badge_style ?>">
                                    <?= htmlspecialchars($row['tingkat_kesulitan']) ?>
                                </span>
                            </div>
                            <h3 class="text-[18px] font-bold leading-snug mb-1.5 text-gray-900 dark:text-white card-title pr-8"><?= htmlspecialchars($row['judul']) ?></h3>
                            <p onclick="this.classList.toggle('line-clamp-3')" class="text-[14px] text-gray-600 dark:text-gray-300 mb-4 line-clamp-3 leading-relaxed card-desc cursor-pointer hover:text-gray-900 dark:hover:text-white transition-all duration-300" title="Klik untuk membaca selengkapnya">
                                <?= nl2br(htmlspecialchars($row['deskripsi'])) ?>
                            </p>
                        </div>
                        
                        <!-- Footer dengan Hari di Depan Tanggal -->
                        <div class="mt-auto pt-4 border-t border-black/5 dark:border-white/10 flex justify-between items-center card-footer">
                            <div class="card-date text-[13px] font-semibold text-gray-600 dark:text-gray-300 flex items-center bg-black/5 dark:bg-white/10 px-2.5 py-1 rounded-md">
                                <svg class="w-4 h-4 mr-1.5 opacity-70" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                <span><?= formatTanggalIndonesia($row['tenggat_waktu'], true) ?></span>
                            </div>
                            <a href="?action=delete&id=<?= $row['id'] ?>" onclick="return confirm('Hapus tugas ini?')" class="text-[#ff3b30] hover:bg-[#ff3b30]/10 p-1.5 rounded-lg transition card-actions" title="Hapus tugas">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>

            </div>
        </div>
    </div>

    <!-- AREA TERSEMBUNYI UNTUK DIPRINT -->
    <div id="print-area"></div>

    <script>
        // Manajemen Ukuran Font Print
        function updatePrintFontSize(size) {
            size = parseInt(size, 10);
            document.getElementById('print_font_size').value = size;
            let label = size + 'px';
            if (size <= 11) label += ' (Kecil)';
            else if (size <= 14) label += ' (Normal)';
            else if (size <= 17) label += ' (Besar)';
            else label += ' (Ekstra)';
            document.getElementById('font_val').textContent = label;
        }

        // Manajemen Tema Dark / Light / Sistem
        function applyTheme(theme) {
            const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            if (theme === 'dark' || (theme === 'system' && prefersDark)) {
                document.documentElement.classList.add('dark');
            } else {
                document.documentElement.classList.remove('dark');
            }
            
            // Perbarui tombol aktif
            document.querySelectorAll('.theme-btn').forEach(btn => {
                btn.classList.remove('bg-white', 'dark:bg-[#3a3a3c]', 'shadow-sm', 'text-gray-900', 'dark:text-white', 'font-semibold');
                btn.classList.add('text-gray-500', 'dark:text-gray-400');
            });
            const activeBtn = document.getElementById('theme-btn-' + theme);
            if (activeBtn) {
                activeBtn.classList.remove('text-gray-500', 'dark:text-gray-400');
                activeBtn.classList.add('bg-white', 'dark:bg-[#3a3a3c]', 'shadow-sm', 'text-gray-900', 'dark:text-white', 'font-semibold');
            }
        }

        function setTheme(theme) {
            localStorage.setItem('kartala_theme', theme);
            applyTheme(theme);
        }

        // Jalankan sinkronisasi tema saat load
        window.addEventListener('DOMContentLoaded', () => {
            const currentTheme = localStorage.getItem('kartala_theme') || 'system';
            applyTheme(currentTheme);
        });

        // Pantau perubahan preferensi browser jika mode sistem
        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
            const currentTheme = localStorage.getItem('kartala_theme') || 'system';
            if (currentTheme === 'system') {
                applyTheme('system');
            }
        });

        // Cetak Kartu Terpilih (Tetap Light Mode dengan Font Sesuai Slider)
        function printSelected() {
            const widthCm = document.getElementById('print_w').value || 10;
            const heightCm = document.getElementById('print_h').value || 8;
            const baseFontSize = parseInt(document.getElementById('print_font_size').value, 10) || 14;
            const checkboxes = document.querySelectorAll('.task-checkbox:checked');
            
            if (checkboxes.length === 0) {
                alert("Silakan centang minimal satu kartu yang ingin dicetak.");
                return;
            }

            const printArea = document.getElementById('print-area');
            printArea.innerHTML = ''; 

            checkboxes.forEach(cb => {
                const originalCard = cb.closest('.task-card');
                const clonedCard = originalCard.cloneNode(true);
                
                // Hapus tombol aksi (checkbox & tombol hapus)
                const actions = clonedCard.querySelectorAll('.card-actions');
                actions.forEach(el => el.remove());

                // Tambahkan class penanda print
                clonedCard.classList.add('print-card');
                
                // Set ukuran dinamis
                clonedCard.style.width = widthCm + 'cm';
                clonedCard.style.height = heightCm + 'cm';

                // Terapkan ukuran font dinamis secara proporsional
                const titleEl = clonedCard.querySelector('.card-title');
                const descEl = clonedCard.querySelector('.card-desc');
                const badgeEl = clonedCard.querySelector('.card-badge');
                const dateEl = clonedCard.querySelector('.card-date');

                if (descEl) descEl.style.fontSize = baseFontSize + 'px';
                if (titleEl) titleEl.style.fontSize = Math.round(baseFontSize * 1.28) + 'px';
                if (badgeEl) badgeEl.style.fontSize = Math.max(9, Math.round(baseFontSize * 0.85)) + 'px';
                if (dateEl) dateEl.style.fontSize = Math.max(9, Math.round(baseFontSize * 0.85)) + 'px';

                printArea.appendChild(clonedCard);
            });

            window.print();
        }
    </script>
<?php endif; ?>

</body>
</html>