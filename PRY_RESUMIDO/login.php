<?php
require __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    $stmt = $pdo->prepare('SELECT id, nombre, email, password FROM usuarios WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && hash_equals($user['password'], $password)) {
        $_SESSION['user'] = [
            'id' => (int) $user['id'],
            'nombre' => $user['nombre'],
            'email' => $user['email'],
        ];
        header('Location: reservas.php');
        exit;
    }

    header('Location: login.php?error=1');
    exit;
}

if (!empty($_SESSION['user'])) {
    header('Location: reservas.php');
    exit;
}

$error = $_GET['error'] ?? '';
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>🔐 Login - Restaurante Mini</title>
    <link rel="stylesheet" href="public/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="public/css/mini.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg shadow-sm">
        <div class="container">
            <a class="navbar-brand" href="index.html"><span>🍽️ Restaurante Mini</span></a>
            <div class="ms-auto">
                <a class="btn btn-outline-primary" href="index.html">← Volver</a>
            </div>
        </div>
    </nav>

    <main class="container py-5">
        <div class="row g-4 align-items-center">
            <div class="col-lg-6">
                <div class="hero-section">
                    <div class="hero-badge mb-3">🔒 Acceso seguro</div>
                    <h2 class="fw-bold display-6">¡Bienvenido de nuevo!</h2>
                    <p class="lead">Administra reservas, mesas y platos desde un panel simple e intuitivo.</p>
                    <div class="mt-4">
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <div class="fs-2">✅</div>
                            <div>
                                <h6 class="mb-0 fw-bold">Control total</h6>
                                <small class="opacity-75">Gestión completa en un solo lugar</small>
                            </div>
                        </div>
                        <div class="d-flex align-items-center gap-3">
                            <div class="fs-2">⚡</div>
                            <div>
                                <h6 class="mb-0 fw-bold">Super rápido</h6>
                                <small class="opacity-75">Interfaz optimizada y fluida</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card auth-card">
                    <h4 class="card-title mb-2">🔐 Inicia sesión</h4>
                    <p class="text-muted mb-4">Ingresa tus credenciales para continuar</p>
                    <?php if ($error === '1'): ?>
                        <div class="alert alert-danger">❌ <strong>Error:</strong> Credenciales incorrectas. Verifica e intenta nuevamente.</div>
                    <?php endif; ?>
                    <form method="post" action="login.php">
                        <div class="mb-4">
                            <label class="form-label">📧 Email</label>
                            <input type="email" name="email" class="form-control" placeholder="tu@email.com" required autocomplete="email">
                        </div>
                        <div class="mb-4">
                            <label class="form-label">🔑 Contraseña</label>
                            <input type="password" name="password" class="form-control" placeholder="••••••••" required autocomplete="current-password">
                        </div>
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary btn-lg">🚀 Ingresar al sistema</button>
                            <button type="reset" class="btn btn-outline-secondary">🔄 Limpiar campos</button>
                        </div>
                    </form>
                    <div class="mt-4 p-3 bg-light rounded">
                        <p class="text-muted small mb-2"><strong>💡 Credenciales demo:</strong></p>
                        <div class="d-flex flex-column gap-2">
                            <span class="badge badge-soft">📧 admin@demo.com</span>
                            <span class="badge badge-soft">🔑 admin123</span>
                        </div>
                    </div>
                    <div class="mt-3 text-center p-3 bg-white border rounded">
                        <p class="text-muted mb-0">
                            ¿No tienes una cuenta? 
                            <a href="registro.php" class="fw-bold text-decoration-none">Regístrate gratis aquí →</a>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="public/bootstrap/js/bootstrap.bundle.min.js"></script>
</body>
</html>
