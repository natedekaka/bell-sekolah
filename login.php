<?php
require_once __DIR__ . '/core/init.php';

// Sudah login? Langsung ke dashboard
if (is_logged_in()) {
    redirect('index.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf('login.php');

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Username dan password wajib diisi.';
    } else {
        $stmt = conn()->prepare("SELECT * FROM users WHERE username = ? AND aktif = 1 LIMIT 1");
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

        if ($user && password_verify($password, $user['password_hash'])) {
            do_login($user);
            redirect('index.php');
        } else {
            $error = 'Username atau password salah.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — Bell Sekolah</title>
    <link rel="stylesheet" href="assets/css/app.css">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%230f172a' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><path d='M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9'/><path d='M10.3 21a1.94 1.94 0 0 0 3.4 0'/></svg>">
</head>
<body class="bg-slate-900 min-h-screen flex items-center justify-center p-4">

<div class="w-full max-w-md">
    <div class="bg-white rounded-2xl shadow-2xl p-8">
        <div class="text-center mb-6">
            <div class="text-5xl mb-2"><svg style="display:block;width:3rem;height:3rem;margin:0 auto" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg></div>
            <h1 class="text-2xl font-bold text-slate-800">Bell Sekolah</h1>
            <p class="text-sm text-slate-500">Sistem Bel Otomatis Berbasis Web</p>
        </div>

        <?php if ($error): ?>
            <div class="mb-4 rounded-lg border border-red-300 bg-red-100 px-4 py-3 text-red-800 text-sm"><?= e($error) ?></div>
        <?php endif; ?>
        <?= flash_html() ?>

        <form method="post" action="login.php" class="space-y-4">
            <?= csrf_field() ?>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Username</label>
                <input type="text" name="username" required autofocus
                       class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:ring-2 focus:ring-blue-400 focus:border-blue-400 outline-none">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Password</label>
                <input type="password" name="password" required
                       class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:ring-2 focus:ring-blue-400 focus:border-blue-400 outline-none">
            </div>
            <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2.5 rounded-lg transition">
                Masuk
            </button>
        </form>

        <p class="text-center text-xs text-slate-400 mt-6">
            Akun bawaan: <code class="bg-gray-100 px-1 py-0.5 rounded">admin / admin123</code>
        </p>
    </div>
</div>
</body>
</html>