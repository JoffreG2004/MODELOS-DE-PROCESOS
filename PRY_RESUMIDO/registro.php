<?php
require __DIR__ . '/db.php';

// Si ya está logueado, redirigir
if (!empty($_SESSION['user'])) {
    header('Location: reservas.php');
    exit;
}

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $password_confirm = trim($_POST['password_confirm'] ?? '');

    // Validaciones
    if (empty($nombre)) {
        $errors[] = 'El nombre es obligatorio';
    } elseif (strlen($nombre) < 3) {
        $errors[] = 'El nombre debe tener al menos 3 caracteres';
    }

    if (empty($email)) {
        $errors[] = 'El email es obligatorio';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'El email no es válido';
    }

    if (empty($password)) {
        $errors[] = 'La contraseña es obligatoria';
    } elseif (strlen($password) < 6) {
        $errors[] = 'La contraseña debe tener al menos 6 caracteres';
    }

    if ($password !== $password_confirm) {
        $errors[] = 'Las contraseñas no coinciden';
    }

    // Verificar si el email ya existe
    if (empty($errors)) {
        $stmt = $pdo->prepare('SELECT id FROM usuarios WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $errors[] = 'Este email ya está registrado';
        }
    }

    // Insertar usuario si no hay errores
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare('INSERT INTO usuarios (nombre, email, password) VALUES (?, ?, ?)');
            $stmt->execute([$nombre, $email, $password]);
            $success = true;
        } catch (PDOException $e) {
            $errors[] = 'Error al crear la cuenta. Intenta nuevamente.';
        }
    }
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>📝 Registro - Restaurante Mini</title>
    <link rel="stylesheet" href="public/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="public/css/mini.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg shadow-sm">
        <div class="container">
            <a class="navbar-brand" href="index.html"><span>🍽️ Restaurante Mini</span></a>
            <div class="ms-auto d-flex gap-2">
                <a class="btn btn-outline-primary" href="login.php">🔐 Iniciar sesión</a>
                <a class="btn btn-outline-secondary" href="index.html">← Volver</a>
            </div>
        </div>
    </nav>

    <main class="container py-5">
        <div class="row g-4 align-items-center">
            <div class="col-lg-6">
                <div class="hero-section">
                    <div class="hero-badge mb-3">✨ Únete ahora</div>
                    <h2 class="fw-bold display-6">¡Crea tu cuenta gratis!</h2>
                    <p class="lead">Comienza a gestionar tu restaurante de forma profesional y sin complicaciones.</p>
                    <div class="mt-4">
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <div class="fs-2">🎯</div>
                            <div>
                                <h6 class="mb-0 fw-bold">100% Gratis</h6>
                                <small class="opacity-75">Sin costos ocultos ni suscripciones</small>
                            </div>
                        </div>
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <div class="fs-2">⚡</div>
                            <div>
                                <h6 class="mb-0 fw-bold">Configuración rápida</h6>
                                <small class="opacity-75">Comienza en menos de 2 minutos</small>
                            </div>
                        </div>
                        <div class="d-flex align-items-center gap-3">
                            <div class="fs-2">🔒</div>
                            <div>
                                <h6 class="mb-0 fw-bold">Seguro y privado</h6>
                                <small class="opacity-75">Tus datos están protegidos</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card auth-card">
                    <h4 class="card-title mb-2">📝 Crear cuenta nueva</h4>
                    <p class="text-muted mb-4">Completa el formulario para registrarte</p>
                    
                    <?php if ($success): ?>
                        <div class="alert alert-success">
                            <strong>✅ ¡Cuenta creada exitosamente!</strong>
                            <p class="mb-2">Tu cuenta ha sido registrada correctamente.</p>
                            <a href="login.php" class="btn btn-success btn-sm mt-2">Ir a iniciar sesión →</a>
                        </div>
                    <?php else: ?>
                        <?php if (!empty($errors)): ?>
                            <div class="alert alert-danger">
                                <strong>❌ Errores encontrados:</strong>
                                <ul class="mb-0 mt-2">
                                    <?php foreach ($errors as $error): ?>
                                        <li><?= htmlspecialchars($error) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <form method="post" action="registro.php">
                            <div class="mb-3">
                                <label class="form-label">👤 Nombre completo</label>
                                <input type="text" name="nombre" class="form-control" placeholder="Ej: Juan Pérez" required minlength="3" value="<?= htmlspecialchars($_POST['nombre'] ?? '') ?>">
                                <small class="text-muted">Mínimo 3 caracteres</small>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">📧 Email</label>
                                <input type="email" name="email" class="form-control" placeholder="tu@email.com" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                                <small class="text-muted">Usa un email válido</small>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">🔑 Contraseña</label>
                                <input type="password" name="password" class="form-control" placeholder="••••••••" required minlength="6">
                                <small class="text-muted">Mínimo 6 caracteres</small>
                            </div>
                            <div class="mb-4">
                                <label class="form-label">🔒 Confirmar contraseña</label>
                                <input type="password" name="password_confirm" class="form-control" placeholder="••••••••" required minlength="6">
                                <small class="text-muted">Debe coincidir con la contraseña</small>
                            </div>
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary btn-lg">✨ Crear mi cuenta gratis</button>
                                <button type="reset" class="btn btn-outline-secondary">🔄 Limpiar campos</button>
                            </div>
                        </form>
                        
                        <div class="mt-4 text-center p-3 bg-light rounded">
                            <p class="text-muted mb-0">
                                ¿Ya tienes una cuenta? 
                                <a href="login.php" class="fw-bold text-decoration-none">Iniciar sesión aquí →</a>
                            </p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Características adicionales -->
        <div class="row mt-5 pt-5">
            <div class="col-12 text-center mb-4">
                <div class="hero-badge d-inline-flex mb-3">🎁 Beneficios de registrarse</div>
                <h3 class="fw-bold mb-4">Todo lo que obtienes gratis</h3>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body text-center">
                        <div class="fs-1 mb-3">📅</div>
                        <h5 class="fw-bold mb-2">Gestión de Reservas</h5>
                        <p class="text-muted mb-0">Administra todas tus reservas en un solo lugar de forma simple y eficiente.</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body text-center">
                        <div class="fs-1 mb-3">🍽️</div>
                        <h5 class="fw-bold mb-2">Menú Digital</h5>
                        <p class="text-muted mb-0">Gestiona tu catálogo de platos y precios con actualizaciones instantáneas.</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body text-center">
                        <div class="fs-1 mb-3">📊</div>
                        <h5 class="fw-bold mb-2">Panel de Control</h5>
                        <p class="text-muted mb-0">Visualiza toda la información importante desde un dashboard centralizado.</p>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <footer class="py-4 bg-white border-top mt-5">
        <div class="container text-center">
            <p class="text-muted small mb-0">© 2026 Restaurante Mini · Sistema de Gestión Simplificado</p>
        </div>
    </footer>

    <script src="public/bootstrap/js/bootstrap.bundle.min.js"></script>
</body>
</html>
