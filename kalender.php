<?php
session_start();

if (!isset($_SESSION['logged_in'])) {
    header("Location: index.php");
    exit;
}

// 1. Konfigurasi Database SQLite
$db_file = __DIR__ . '/jadwal.sqlite';
$pdo = new PDO('sqlite:' . $db_file);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Handle Logout
if (isset($_GET['action']) && $_GET['action'] == 'logout') {
    session_destroy();
    header("Location: index.php");
    exit;
}

// Handle Tambah Jadwal langsung dari kalender
if (isset($_POST['tambah_jadwal'])) {
    $stmt = $pdo->prepare("INSERT INTO jadwal (judul, deskripsi, tenggat_waktu, tingkat_kesulitan) VALUES (?, ?, ?, ?)");
    $stmt->execute([
        $_POST['judul'], 
        $_POST['deskripsi'], 
        $_POST['tenggat_waktu'], 
        $_POST['tingkat_kesulitan']
    ]);
    header("Location: kalender.php");
    exit;
}

// Handle Delete Jadwal langsung dari kalender
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $stmt = $pdo->prepare("DELETE FROM jadwal WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    header("Location: kalender.php");
    exit;
}

// Helper Format Tanggal Indonesia
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

// Ambil Seluruh Data Jadwal
$stmt = $pdo->query("SELECT * FROM jadwal ORDER BY tenggat_waktu ASC, id ASC");
$jadwals = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kartala - Kalender Jadwal Rencana</title>
    <!-- Script Deteksi Tema Cepat -->
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
        body {
            font-family: -apple-system, BlinkMacSystemFont, "SF Pro Text", "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            -webkit-font-smoothing: antialiased;
        }

        .cal-day-cell {
            min-height: 105px;
            transition: all 0.15s ease-in-out;
        }
        @media (max-width: 640px) {
            .cal-day-cell {
                min-height: 65px;
            }
        }
        .cal-day-cell:hover {
            transform: translateY(-1px);
        }
        .custom-scrollbar::-webkit-scrollbar {
            width: 4px;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb {
            background-color: rgba(156, 163, 175, 0.4);
            border-radius: 9999px;
        }
    </style>
</head>
<body class="bg-[#f2f2f7] dark:bg-[#000000] text-gray-900 dark:text-gray-100 min-h-screen pb-12 transition-colors duration-200">

    <!-- NAVBAR -->
    <nav class="sticky top-0 z-50 bg-white/80 dark:bg-[#1c1c1e]/80 backdrop-blur-md border-b border-gray-200/50 dark:border-white/10 transition-colors duration-200">
        <div class="max-w-6xl mx-auto px-4 py-3 flex flex-wrap justify-between items-center gap-3">
            <div class="flex items-center gap-3 sm:gap-6">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 bg-[#007aff] rounded-lg flex items-center justify-center shadow-sm">
                        <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                    </div>
                    <h1 class="text-xl font-bold tracking-tight text-gray-900 dark:text-white">Kartala</h1>
                </div>

                <!-- Navigasi Halaman -->
                <div class="flex items-center bg-gray-100/90 dark:bg-[#2c2c2e] p-1 rounded-xl text-xs sm:text-sm font-medium border border-gray-200/40 dark:border-white/5">
                    <a href="index.php" class="px-3 py-1.5 rounded-lg text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white transition flex items-center gap-1.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"></path></svg>
                        <span>Daftar Rencana</span>
                    </a>
                    <a href="kalender.php" class="px-3 py-1.5 rounded-lg bg-white dark:bg-[#3a3a3c] shadow-sm text-gray-900 dark:text-white font-semibold transition flex items-center gap-1.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                        <span>Kalender</span>
                    </a>
                </div>
            </div>

            <div class="flex items-center gap-2 sm:gap-3">
                <!-- Theme Switcher -->
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

    <!-- KONTEN UTAMA: LAYOUT 2 KOLOM PERSIS DAFTAR RENCANA -->
    <div class="max-w-6xl mx-auto mt-6 px-4 flex flex-col md:flex-row gap-6">
        
        <!-- KOLOM KIRI (md:w-[35%]): Form Tugas Baru & Statistik -->
        <div class="md:w-[35%] flex flex-col gap-6">
            
            <!-- Form Tugas Baru (Persis seperti index.php) -->
            <div id="box-tugas-baru" class="bg-white dark:bg-[#1c1c1e] p-5 rounded-[20px] shadow-[0_2px_10px_rgb(0,0,0,0.03)] border border-gray-100/70 dark:border-[#2c2c2e] transition-all duration-300">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-lg font-bold tracking-tight text-gray-900 dark:text-white">Tugas Baru</h2>
                    <span id="selected-date-badge" class="text-[11px] font-semibold text-[#007aff] bg-[#007aff]/10 dark:bg-[#007aff]/20 px-2.5 py-1 rounded-full hidden">
                        Dari Kalender
                    </span>
                </div>
                <form method="POST" id="form-tugas-baru">
                    <div class="mb-3">
                        <input type="text" name="judul" id="input_judul" placeholder="Judul Tugas" class="ios-input bg-[#f2f2f7] dark:bg-[#2c2c2e] text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 border-none w-full px-4 py-3 rounded-xl text-[15px]" required>
                    </div>
                    <div class="mb-3">
                        <textarea name="deskripsi" id="input_deskripsi" placeholder="Catatan atau deskripsi..." class="ios-input bg-[#f2f2f7] dark:bg-[#2c2c2e] text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 border-none w-full px-4 py-3 rounded-xl text-[15px]" rows="3" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="block text-[13px] font-semibold text-gray-400 dark:text-gray-400 mb-1">Tenggat Waktu</label>
                        <input type="date" name="tenggat_waktu" id="input_tenggat" class="ios-input bg-[#f2f2f7] dark:bg-[#2c2c2e] text-gray-700 dark:text-gray-200 border-none w-full px-4 py-3 rounded-xl text-[15px]" required>
                    </div>
                    <div class="mb-4">
                        <label class="block text-[13px] font-semibold text-gray-400 dark:text-gray-400 mb-1">Tingkat Kesulitan</label>
                        <select name="tingkat_kesulitan" id="input_kesulitan" class="ios-input bg-[#f2f2f7] dark:bg-[#2c2c2e] text-gray-700 dark:text-gray-200 border-none w-full px-4 py-3 rounded-xl text-[15px]">
                            <option value="Mudah">🟢 Mudah</option>
                            <option value="Sedang">🟠 Sedang</option>
                            <option value="Sulit">🔴 Sulit</option>
                        </select>
                    </div>
                    <button type="submit" name="tambah_jadwal" class="w-full bg-[#007aff] hover:bg-[#005bb5] text-white font-semibold py-3 px-4 rounded-xl transition shadow-xs flex items-center justify-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                        <span>Tambahkan ke Kalender</span>
                    </button>
                </form>
            </div>

            <!-- Ringkasan Statistik Bulan Ini -->
            <div class="bg-white dark:bg-[#1c1c1e] p-5 rounded-[20px] shadow-[0_2px_10px_rgb(0,0,0,0.03)] border border-gray-100/70 dark:border-[#2c2c2e] transition-colors duration-200">
                <div class="flex justify-between items-center mb-3">
                    <h3 class="text-sm font-bold tracking-tight text-gray-900 dark:text-white">Statistik Bulan Ini</h3>
                    <span id="stat-month-label" class="text-xs font-semibold text-gray-400">September 2026</span>
                </div>
                <div class="grid grid-cols-2 gap-2.5">
                    <div class="bg-[#f2f2f7]/80 dark:bg-[#252527] p-3 rounded-xl">
                        <div class="text-[11px] font-medium text-gray-500 dark:text-gray-400">Total Tugas</div>
                        <div id="stat-total" class="text-lg font-bold text-gray-900 dark:text-white mt-0.5">0</div>
                    </div>
                    <div class="bg-green-50/70 dark:bg-green-950/30 border border-green-200/50 dark:border-green-900/40 p-3 rounded-xl">
                        <div class="text-[11px] font-medium text-green-700 dark:text-green-400">🟢 Mudah</div>
                        <div id="stat-mudah" class="text-lg font-bold text-green-800 dark:text-green-300 mt-0.5">0</div>
                    </div>
                    <div class="bg-orange-50/70 dark:bg-orange-950/30 border border-orange-200/50 dark:border-orange-900/40 p-3 rounded-xl">
                        <div class="text-[11px] font-medium text-orange-700 dark:text-orange-400">🟠 Sedang</div>
                        <div id="stat-sedang" class="text-lg font-bold text-orange-800 dark:text-orange-300 mt-0.5">0</div>
                    </div>
                    <div class="bg-red-50/70 dark:bg-red-950/30 border border-red-200/50 dark:border-red-900/40 p-3 rounded-xl">
                        <div class="text-[11px] font-medium text-red-700 dark:text-red-400">🔴 Sulit</div>
                        <div id="stat-sulit" class="text-lg font-bold text-red-800 dark:text-red-300 mt-0.5">0</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- KOLOM KANAN (md:w-[65%]): Kalender & Agenda -->
        <div class="md:w-[65%] flex flex-col gap-6">
            
            <!-- HEADER KONTROL KALENDER -->
            <div class="bg-white dark:bg-[#1c1c1e] p-5 rounded-[24px] shadow-[0_2px_12px_rgb(0,0,0,0.03)] border border-gray-100 dark:border-[#2c2c2e] transition-colors duration-200">
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                    
                    <!-- Judul Bulan & Navigasi -->
                    <div class="flex items-center gap-3">
                        <div class="flex items-center bg-gray-100 dark:bg-[#2c2c2e] p-1 rounded-xl border border-gray-200/50 dark:border-white/10">
                            <button onclick="prevMonth()" class="p-2 rounded-lg hover:bg-white dark:hover:bg-[#3a3a3c] text-gray-700 dark:text-gray-200 transition shadow-xs" title="Bulan Sebelumnya">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"></path></svg>
                            </button>
                            <button onclick="goToToday()" class="px-3 py-1.5 text-xs font-bold rounded-lg hover:bg-white dark:hover:bg-[#3a3a3c] text-gray-700 dark:text-gray-200 transition shadow-xs">
                                Hari Ini
                            </button>
                            <button onclick="nextMonth()" class="p-2 rounded-lg hover:bg-white dark:hover:bg-[#3a3a3c] text-gray-700 dark:text-gray-200 transition shadow-xs" title="Bulan Berikutnya">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                            </button>
                        </div>

                        <div>
                            <h2 id="calendar-month-year" class="text-xl sm:text-2xl font-extrabold tracking-tight text-gray-900 dark:text-white capitalize">
                                September 2026
                            </h2>
                        </div>
                    </div>

                    <!-- Filter Tingkat Kesulitan -->
                    <div class="flex items-center gap-1.5 flex-wrap">
                        <span class="text-xs font-semibold text-gray-400 dark:text-gray-500 mr-1">Filter:</span>
                        <button onclick="setFilter('all')" id="filter-all" class="filter-btn px-3 py-1 rounded-lg text-xs font-semibold bg-gray-900 dark:bg-white text-white dark:text-gray-900 transition shadow-xs">Semua</button>
                        <button onclick="setFilter('Mudah')" id="filter-Mudah" class="filter-btn px-3 py-1 rounded-lg text-xs font-semibold bg-green-100 dark:bg-[#152e19] text-green-800 dark:text-green-300 hover:bg-green-200 dark:hover:bg-green-900 transition">Mudah</button>
                        <button onclick="setFilter('Sedang')" id="filter-Sedang" class="filter-btn px-3 py-1 rounded-lg text-xs font-semibold bg-orange-100 dark:bg-[#332213] text-orange-800 dark:text-orange-300 hover:bg-orange-200 dark:hover:bg-orange-900 transition">Sedang</button>
                        <button onclick="setFilter('Sulit')" id="filter-Sulit" class="filter-btn px-3 py-1 rounded-lg text-xs font-semibold bg-red-100 dark:bg-[#33171b] text-red-800 dark:text-red-300 hover:bg-red-200 dark:hover:bg-red-900 transition">Sulit</button>
                    </div>
                </div>
            </div>

            <!-- GRID KALENDER -->
            <div class="bg-white dark:bg-[#1c1c1e] p-4 sm:p-5 rounded-[24px] shadow-[0_2px_12px_rgb(0,0,0,0.03)] border border-gray-100 dark:border-[#2c2c2e] transition-colors duration-200">
                
                <!-- Nama Hari (Senin s/d Minggu) -->
                <div class="grid grid-cols-7 gap-1.5 sm:gap-2 mb-2 text-center">
                    <div class="py-2 text-xs sm:text-sm font-bold text-gray-500 dark:text-gray-400">Sen</div>
                    <div class="py-2 text-xs sm:text-sm font-bold text-gray-500 dark:text-gray-400">Sel</div>
                    <div class="py-2 text-xs sm:text-sm font-bold text-gray-500 dark:text-gray-400">Rab</div>
                    <div class="py-2 text-xs sm:text-sm font-bold text-gray-500 dark:text-gray-400">Kam</div>
                    <div class="py-2 text-xs sm:text-sm font-bold text-gray-500 dark:text-gray-400">Jum</div>
                    <div class="py-2 text-xs sm:text-sm font-bold text-blue-600 dark:text-blue-400">Sab</div>
                    <div class="py-2 text-xs sm:text-sm font-bold text-red-500 dark:text-red-400">Min</div>
                </div>

                <!-- Tanggal Grid Container -->
                <div id="calendar-grid" class="grid grid-cols-7 gap-1.5 sm:gap-2">
                    <!-- Diisi secara dinamis via JavaScript -->
                </div>
            </div>

            <!-- DAFTAR RINCIAN TUGAS BULAN INI -->
            <div class="bg-white dark:bg-[#1c1c1e] p-5 sm:p-6 rounded-[24px] shadow-[0_2px_12px_rgb(0,0,0,0.03)] border border-gray-100 dark:border-[#2c2c2e] transition-colors duration-200">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-base sm:text-lg font-bold text-gray-900 dark:text-white tracking-tight flex items-center gap-2">
                        <svg class="w-5 h-5 text-[#007aff]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                        <span>Agenda Rencana Bulan Ini</span>
                    </h3>
                    <span id="agenda-count-badge" class="text-xs font-semibold text-gray-500 dark:text-gray-400 bg-gray-100 dark:bg-white/10 px-2.5 py-1 rounded-full">
                        0 Tugas
                    </span>
                </div>
                
                <div id="month-task-list" class="space-y-3">
                    <!-- Diisi secara dinamis via JavaScript -->
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL DETAIL TUGAS PADA TANGGAL TERTENTU -->
    <div id="day-modal" class="fixed inset-0 z-50 hidden bg-black/50 backdrop-blur-xs flex items-center justify-center p-4">
        <div class="bg-white dark:bg-[#1c1c1e] w-full max-w-lg rounded-[24px] shadow-2xl border border-gray-100 dark:border-[#2c2c2e] overflow-hidden transition-all duration-200 transform">
            <div class="p-5 border-b border-gray-100 dark:border-white/10 flex justify-between items-center bg-gray-50/50 dark:bg-[#252527]">
                <div>
                    <h3 id="modal-date-title" class="text-lg font-bold text-gray-900 dark:text-white">
                        Senin, 14 September 2026
                    </h3>
                    <p id="modal-task-count" class="text-xs font-semibold text-gray-500 dark:text-gray-400 mt-0.5">
                        2 Tugas Terjadwal
                    </p>
                </div>
                <button onclick="closeModal()" class="w-8 h-8 rounded-full bg-gray-200/80 dark:bg-white/10 hover:bg-gray-300 dark:hover:bg-white/20 text-gray-600 dark:text-gray-300 flex items-center justify-center transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>

            <!-- Isi Tugas pada Tanggal Terpilih -->
            <div id="modal-tasks-container" class="p-5 max-h-[55vh] overflow-y-auto space-y-3 custom-scrollbar">
                <!-- Diisi via JS -->
            </div>

            <!-- Footer Modal: Tombol Tambah di Tanggal Ini & Tutup -->
            <div class="p-4 bg-gray-50/80 dark:bg-[#252527] border-t border-gray-100 dark:border-white/10 flex flex-wrap justify-between items-center gap-2">
                <button type="button" onclick="quickAddForDate(currentModalDateKey)" class="px-3.5 py-2 bg-[#007aff] hover:bg-[#005bb5] text-white text-xs font-semibold rounded-xl transition flex items-center gap-1.5 shadow-sm">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                    <span>Tambah Tugas di Tanggal Ini</span>
                </button>
                <button onclick="closeModal()" class="px-4 py-2 bg-gray-200 dark:bg-[#3a3a3c] hover:bg-gray-300 dark:hover:bg-[#48484a] text-gray-800 dark:text-white text-xs font-semibold rounded-xl transition">
                    Tutup
                </button>
            </div>
        </div>
    </div>

    <!-- SCRIPT LOGIKA KALENDER & INTERAKSI -->
    <script>
        // Data Jadwal dari PHP SQLite
        const allTasks = <?= json_encode($jadwals, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;

        const bulanNama = [
            "Januari", "Februari", "Maret", "April", "Mei", "Juni",
            "Juli", "Agustus", "September", "Oktober", "November", "Desember"
        ];

        const hariNama = [
            "Minggu", "Senin", "Selasa", "Rabu", "Kamis", "Jumat", "Sabtu"
        ];

        const today = new Date();
        let currentYear = today.getFullYear();
        let currentMonth = today.getMonth(); // 0-indexed
        let currentFilter = 'all';
        let currentModalDateKey = '';

        function initCalendar() {
            // Set default tanggal form ke hari ini
            const todayFormatted = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-${String(today.getDate()).padStart(2, '0')}`;
            const tenggatInput = document.getElementById('input_tenggat');
            if (tenggatInput && !tenggatInput.value) {
                tenggatInput.value = todayFormatted;
            }
            renderCalendar();
        }

        // Fitur Tambah Cepat Berdasarkan Tanggal Kalender
        function quickAddForDate(dateStr) {
            closeModal();
            const tenggatInput = document.getElementById('input_tenggat');
            const judulInput = document.getElementById('input_judul');
            const boxTugas = document.getElementById('box-tugas-baru');
            const dateBadge = document.getElementById('selected-date-badge');

            if (tenggatInput) {
                tenggatInput.value = dateStr;
            }

            if (dateBadge) {
                dateBadge.textContent = 'Tgl: ' + formatIndoDate(dateStr);
                dateBadge.classList.remove('hidden');
            }

            // Scroll halus ke form tugas baru di kolom kiri
            if (boxTugas) {
                boxTugas.scrollIntoView({ behavior: 'smooth', block: 'center' });
                // Beri efek highlight border biru sesaat
                boxTugas.classList.add('ring-2', 'ring-[#007aff]');
                setTimeout(() => {
                    boxTugas.classList.remove('ring-2', 'ring-[#007aff]');
                }, 1800);
            }

            if (judulInput) {
                setTimeout(() => judulInput.focus(), 300);
            }
        }

        function prevMonth() {
            currentMonth--;
            if (currentMonth < 0) {
                currentMonth = 11;
                currentYear--;
            }
            renderCalendar();
        }

        function nextMonth() {
            currentMonth++;
            if (currentMonth > 11) {
                currentMonth = 0;
                currentYear++;
            }
            renderCalendar();
        }

        function goToToday() {
            const now = new Date();
            currentYear = now.getFullYear();
            currentMonth = now.getMonth();
            renderCalendar();
        }

        function setFilter(diff) {
            currentFilter = diff;
            document.querySelectorAll('.filter-btn').forEach(btn => {
                btn.classList.remove('bg-gray-900', 'dark:bg-white', 'text-white', 'dark:text-gray-900');
                btn.classList.add('bg-gray-100', 'dark:bg-[#2c2c2e]', 'text-gray-700', 'dark:text-gray-300');
            });
            const activeBtn = document.getElementById('filter-' + diff);
            if (activeBtn) {
                activeBtn.classList.remove('bg-gray-100', 'dark:bg-[#2c2c2e]', 'text-gray-700', 'dark:text-gray-300');
                activeBtn.classList.add('bg-gray-900', 'dark:bg-white', 'text-white', 'dark:text-gray-900');
            }
            renderCalendar();
        }

        function formatIndoDate(dateStr) {
            const d = new Date(dateStr + 'T00:00:00');
            const dayName = hariNama[d.getDay()];
            const dateNum = d.getDate();
            const monthName = bulanNama[d.getMonth()];
            const yearNum = d.getFullYear();
            return `${dayName}, ${dateNum} ${monthName} ${yearNum}`;
        }

        function renderCalendar() {
            // Update Header Bulan & Tahun
            const monthYearStr = `${bulanNama[currentMonth]} ${currentYear}`;
            document.getElementById('calendar-month-year').textContent = monthYearStr;
            const statMonthLabel = document.getElementById('stat-month-label');
            if (statMonthLabel) statMonthLabel.textContent = monthYearStr;

            const grid = document.getElementById('calendar-grid');
            grid.innerHTML = '';

            // Hari pertama bulan (0 = Minggu, 1 = Senin, ... 6 = Sabtu)
            const firstDayOfMonth = new Date(currentYear, currentMonth, 1);
            let startingDay = firstDayOfMonth.getDay(); 
            // Ubah agar Senin = 0 ... Minggu = 6
            startingDay = (startingDay === 0) ? 6 : startingDay - 1;

            const daysInMonth = new Date(currentYear, currentMonth + 1, 0).getDate();
            const daysInPrevMonth = new Date(currentYear, currentMonth, 0).getDate();

            // Kumpulkan tugas-tugas pada bulan ini untuk kalkulasi statistik
            let countTotal = 0;
            let countMudah = 0;
            let countSedang = 0;
            let countSulit = 0;
            const monthTasks = [];

            const ymPrefix = `${currentYear}-${String(currentMonth + 1).padStart(2, '0')}`;

            allTasks.forEach(task => {
                if (task.tenggat_waktu && task.tenggat_waktu.startsWith(ymPrefix)) {
                    countTotal++;
                    if (task.tingkat_kesulitan === 'Mudah') countMudah++;
                    if (task.tingkat_kesulitan === 'Sedang') countSedang++;
                    if (task.tingkat_kesulitan === 'Sulit') countSulit++;
                    monthTasks.push(task);
                }
            });

            document.getElementById('stat-total').textContent = countTotal;
            document.getElementById('stat-mudah').textContent = countMudah;
            document.getElementById('stat-sedang').textContent = countSedang;
            document.getElementById('stat-sulit').textContent = countSulit;
            const agendaCountBadge = document.getElementById('agenda-count-badge');
            if (agendaCountBadge) agendaCountBadge.textContent = `${countTotal} Tugas`;

            // Render Hari dari Bulan Sebelumnya (Padded / Muted)
            for (let i = 0; i < startingDay; i++) {
                const prevDateNum = daysInPrevMonth - startingDay + i + 1;
                const cell = document.createElement('div');
                cell.className = 'cal-day-cell p-2 rounded-2xl bg-gray-50/50 dark:bg-[#171719]/40 border border-gray-100/50 dark:border-white/5 opacity-30 select-none';
                cell.innerHTML = `<span class="text-xs font-semibold text-gray-400 dark:text-gray-600">${prevDateNum}</span>`;
                grid.appendChild(cell);
            }

            // Render Hari Bulan Ini
            const todayStr = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-${String(today.getDate()).padStart(2, '0')}`;

            for (let day = 1; day <= daysInMonth; day++) {
                const dateKey = `${currentYear}-${String(currentMonth + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
                const isToday = (dateKey === todayStr);

                // Ambil tugas pada tanggal ini yang sesuai filter
                const tasksOnDay = allTasks.filter(t => {
                    const matchDate = (t.tenggat_waktu === dateKey);
                    if (!matchDate) return false;
                    if (currentFilter === 'all') return true;
                    return t.tingkat_kesulitan === currentFilter;
                });

                const allTasksOnDayCount = allTasks.filter(t => t.tenggat_waktu === dateKey).length;

                const cell = document.createElement('div');
                cell.className = `cal-day-cell p-1.5 sm:p-2.5 rounded-2xl border cursor-pointer flex flex-col justify-between transition-all group ${
                    isToday 
                        ? 'bg-blue-50/70 dark:bg-blue-950/20 border-[#007aff] shadow-sm' 
                        : 'bg-white dark:bg-[#252527] border-gray-100 dark:border-white/5 hover:border-gray-300 dark:hover:border-white/20'
                }`;

                cell.onclick = () => openDayModal(dateKey, allTasks.filter(t => t.tenggat_waktu === dateKey));

                // Header cell: Angka tanggal + Tombol Quick Add '+'
                let headerHtml = `
                    <div class="flex justify-between items-center mb-1">
                        <div class="flex items-center gap-1">
                            <span class="w-6 h-6 flex items-center justify-center text-xs font-bold rounded-full ${
                                isToday 
                                    ? 'bg-[#007aff] text-white' 
                                    : 'text-gray-800 dark:text-gray-200'
                            }">
                                ${day}
                            </span>
                            ${allTasksOnDayCount > 0 ? `
                                <span class="text-[10px] font-bold px-1.5 py-0.2 rounded-full bg-gray-100 dark:bg-white/10 text-gray-600 dark:text-gray-300">
                                    ${allTasksOnDayCount}
                                </span>
                            ` : ''}
                        </div>
                        <button type="button" onclick="event.stopPropagation(); quickAddForDate('${dateKey}')" 
                                class="w-5 h-5 rounded-full hover:bg-gray-200 dark:hover:bg-white/20 flex items-center justify-center text-gray-400 hover:text-[#007aff] dark:hover:text-blue-400 text-xs font-bold transition opacity-0 group-hover:opacity-100" 
                                title="Pilih tanggal ini di formulir tugas baru">
                            +
                        </button>
                    </div>
                `;

                // Task items (max 2 di mobile, max 3 di desktop)
                let tasksHtml = `<div class="space-y-1 overflow-hidden">`;
                const maxDisplay = 2;
                tasksOnDay.slice(0, maxDisplay).forEach(task => {
                    let badgeColor = 'bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 border-gray-200';
                    if (task.tingkat_kesulitan === 'Mudah') {
                        badgeColor = 'bg-green-100/90 dark:bg-[#152e19] text-green-800 dark:text-green-300 border-green-200 dark:border-green-800/40';
                    } else if (task.tingkat_kesulitan === 'Sedang') {
                        badgeColor = 'bg-orange-100/90 dark:bg-[#332213] text-orange-800 dark:text-orange-300 border-orange-200 dark:border-orange-800/40';
                    } else if (task.tingkat_kesulitan === 'Sulit') {
                        badgeColor = 'bg-red-100/90 dark:bg-[#33171b] text-red-800 dark:text-red-300 border-red-200 dark:border-red-800/40';
                    }

                    tasksHtml += `
                        <div class="text-[11px] font-semibold px-1.5 py-0.5 rounded-md truncate border ${badgeColor}" title="${task.judul}">
                            ${task.judul}
                        </div>
                    `;
                });

                if (tasksOnDay.length > maxDisplay) {
                    tasksHtml += `
                        <div class="text-[10px] font-bold text-[#007aff] px-1 truncate">
                            +${tasksOnDay.length - maxDisplay} lainnya
                        </div>
                    `;
                }

                tasksHtml += `</div>`;

                cell.innerHTML = headerHtml + tasksHtml;
                grid.appendChild(cell);
            }

            // Sisa Grid Bulan Berikutnya agar genap kelipatan 7
            const totalCells = startingDay + daysInMonth;
            const remainingCells = (totalCells % 7 === 0) ? 0 : 7 - (totalCells % 7);
            for (let i = 1; i <= remainingCells; i++) {
                const cell = document.createElement('div');
                cell.className = 'cal-day-cell p-2 rounded-2xl bg-gray-50/50 dark:bg-[#171719]/40 border border-gray-100/50 dark:border-white/5 opacity-30 select-none';
                cell.innerHTML = `<span class="text-xs font-semibold text-gray-400 dark:text-gray-600">${i}</span>`;
                grid.appendChild(cell);
            }

            // Render Rincian Tugas Bulan Ini
            renderMonthTaskList(monthTasks);
        }

        function renderMonthTaskList(tasks) {
            const container = document.getElementById('month-task-list');
            if (tasks.length === 0) {
                container.innerHTML = `
                    <div class="p-6 text-center text-gray-400 dark:text-gray-500 border border-dashed border-gray-200 dark:border-[#2c2c2e] rounded-2xl">
                        Tidak ada rencana jadwal pada ${bulanNama[currentMonth]} ${currentYear}.
                    </div>
                `;
                return;
            }

            // Urutkan berdasarkan tenggat waktu
            tasks.sort((a, b) => new Date(a.tenggat_waktu) - new Date(b.tenggat_waktu));

            let html = '';
            tasks.forEach(task => {
                let badgeStyle = 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300';
                if (task.tingkat_kesulitan === 'Mudah') badgeStyle = 'bg-green-100 text-green-800 dark:bg-[#152e19] dark:text-green-300';
                if (task.tingkat_kesulitan === 'Sedang') badgeStyle = 'bg-orange-100 text-orange-800 dark:bg-[#332213] dark:text-orange-300';
                if (task.tingkat_kesulitan === 'Sulit') badgeStyle = 'bg-red-100 text-red-800 dark:bg-[#33171b] dark:text-red-300';

                html += `
                    <div class="p-4 rounded-2xl bg-gray-50/80 dark:bg-[#252527] border border-gray-100 dark:border-white/5 flex flex-col sm:flex-row sm:items-center justify-between gap-3 hover:border-gray-200 dark:hover:border-white/10 transition">
                        <div class="flex-1">
                            <div class="flex items-center gap-2 mb-1">
                                <span class="text-[11px] font-bold px-2 py-0.5 rounded-md ${badgeStyle}">
                                    ${task.tingkat_kesulitan}
                                </span>
                                <span class="text-xs font-semibold text-gray-500 dark:text-gray-400 flex items-center gap-1">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                    ${formatIndoDate(task.tenggat_waktu)}
                                </span>
                            </div>
                            <h4 class="text-sm font-bold text-gray-900 dark:text-white">${task.judul}</h4>
                            <p class="text-xs text-gray-600 dark:text-gray-300 mt-1 line-clamp-2">${task.deskripsi || '-'}</p>
                        </div>
                        <div class="flex items-center gap-2 self-end sm:self-center">
                            <button type="button" onclick="quickAddForDate('${task.tenggat_waktu}')" class="text-xs text-[#007aff] hover:underline px-2 py-1 rounded-lg transition" title="Jadikan tenggat tugas baru">
                                Pakai Tanggal
                            </button>
                            <a href="kalender.php?action=delete&id=${task.id}" onclick="return confirm('Hapus tugas ini?')" class="text-[#ff3b30] hover:bg-[#ff3b30]/10 p-2 rounded-xl transition" title="Hapus tugas">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                            </a>
                        </div>
                    </div>
                `;
            });

            container.innerHTML = html;
        }

        // Modal Handler
        function openDayModal(dateStr, tasks) {
            currentModalDateKey = dateStr;
            document.getElementById('modal-date-title').textContent = formatIndoDate(dateStr);
            document.getElementById('modal-task-count').textContent = `${tasks.length} Tugas Terjadwal`;

            const container = document.getElementById('modal-tasks-container');
            if (tasks.length === 0) {
                container.innerHTML = `
                    <div class="p-8 text-center text-gray-400 dark:text-gray-500 border border-dashed border-gray-200 dark:border-gray-700 rounded-2xl">
                        Tidak ada rencana jadwal pada tanggal ini. Klik tombol di bawah untuk menambahkan tugas baru pada tanggal ini.
                    </div>
                `;
            } else {
                let html = '';
                tasks.forEach(task => {
                    let badgeStyle = 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300';
                    let cardBg = 'bg-gray-50 dark:bg-[#2c2c2e] border-gray-100 dark:border-white/10';
                    if (task.tingkat_kesulitan === 'Mudah') {
                        badgeStyle = 'bg-green-100 text-green-800 dark:bg-[#152e19] dark:text-green-300';
                        cardBg = 'bg-green-50/70 border-green-200/60 dark:bg-[#142817] dark:border-green-900/40';
                    }
                    if (task.tingkat_kesulitan === 'Sedang') {
                        badgeStyle = 'bg-orange-100 text-orange-800 dark:bg-[#332213] dark:text-orange-300';
                        cardBg = 'bg-orange-50/70 border-orange-200/60 dark:bg-[#2b1f15] dark:border-orange-900/40';
                    }
                    if (task.tingkat_kesulitan === 'Sulit') {
                        badgeStyle = 'bg-red-100 text-red-800 dark:bg-[#33171b] dark:text-red-300';
                        cardBg = 'bg-red-50/70 border-red-200/60 dark:bg-[#2c1619] dark:border-red-900/40';
                    }

                    html += `
                        <div class="p-4 rounded-2xl border ${cardBg}">
                            <div class="flex justify-between items-start gap-2 mb-2">
                                <span class="text-xs font-bold px-2.5 py-1 rounded-md ${badgeStyle}">
                                    ${task.tingkat_kesulitan}
                                </span>
                                <a href="kalender.php?action=delete&id=${task.id}" onclick="return confirm('Hapus tugas ini?')" class="text-[#ff3b30] hover:bg-[#ff3b30]/10 p-1.5 rounded-lg transition" title="Hapus tugas">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                </a>
                            </div>
                            <h4 class="text-base font-bold text-gray-900 dark:text-white mb-1">${task.judul}</h4>
                            <p class="text-xs sm:text-sm text-gray-600 dark:text-gray-300 leading-relaxed whitespace-pre-line">${task.deskripsi || 'Tidak ada deskripsi.'}</p>
                        </div>
                    `;
                });
                container.innerHTML = html;
            }

            document.getElementById('day-modal').classList.remove('hidden');
        }

        function closeModal() {
            document.getElementById('day-modal').classList.add('hidden');
        }

        // Close on escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') closeModal();
        });

        // Close on backdrop click
        document.getElementById('day-modal').addEventListener('click', (e) => {
            if (e.target.id === 'day-modal') closeModal();
        });

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

        // Jalankan saat load
        window.addEventListener('DOMContentLoaded', () => {
            const currentTheme = localStorage.getItem('kartala_theme') || 'system';
            applyTheme(currentTheme);
            initCalendar();
        });

        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
            const currentTheme = localStorage.getItem('kartala_theme') || 'system';
            if (currentTheme === 'system') {
                applyTheme('system');
            }
        });
    </script>
</body>
</html>