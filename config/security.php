<?php

return [
    // Password default tidak lagi memblokir akses; dapat diaktifkan eksplisit
    // pada instalasi yang membutuhkan kebijakan tersebut.
    'require_student_phone' => env('REQUIRE_STUDENT_PHONE', false),
    'force_password_change' => env('FORCE_PASSWORD_CHANGE', false),
    'rate_limit_per_minute' => (int) env('SECURITY_RATE_LIMIT_PER_MINUTE', 180),
    // Blokir otomatis IP yang gagal login berulang kali (lintas username).
    // Nilai ambang tinggi karena banyak siswa dapat berbagi satu IP sekolah (NAT).
    // Set SECURITY_AUTO_BLOCK_FAILED_LOGINS=0 untuk menonaktifkan.
    'auto_block_failed_logins' => (int) env('SECURITY_AUTO_BLOCK_FAILED_LOGINS', 50),
    'auto_block_window_minutes' => (int) env('SECURITY_AUTO_BLOCK_WINDOW_MINUTES', 10),
    'auto_block_duration_minutes' => (int) env('SECURITY_AUTO_BLOCK_DURATION_MINUTES', 15),
    'max_upload_mb' => (int) env('SECURITY_MAX_UPLOAD_MB', 5),
];
