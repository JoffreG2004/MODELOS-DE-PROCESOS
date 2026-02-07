<?php
require __DIR__ . '/db.php';
require_login();

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mesa_id = (int) ($_POST['mesa_id'] ?? 0);
    $fecha = $_POST['fecha'] ?? '';
    $hora = $_POST['hora'] ?? '';
    $num_personas = (int) ($_POST['num_personas'] ?? 0);

    if ($mesa_id && $fecha && $hora && $num_personas > 0) {
        $stmt = $pdo->prepare('INSERT INTO reservas (usuario_id, mesa_id, fecha, hora, num_personas) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([
            $_SESSION['user']['id'],
            $mesa_id,
            $fecha,
            $hora,
            $num_personas,
        ]);
        $message = 'Reserva creada correctamente.';
    } else {
        $message = 'Completa todos los campos.';
    }
}

$mesas = $pdo->query('SELECT id, numero, capacidad FROM mesas ORDER BY numero')->fetchAll();
$platos = $pdo->query('SELECT id, nombre, precio FROM platos ORDER BY nombre')->fetchAll();
$reservas = $pdo->query('SELECT r.id, r.fecha, r.hora, r.num_personas, m.numero AS mesa, u.nombre AS cliente FROM reservas r JOIN mesas m ON r.mesa_id = m.id JOIN usuarios u ON r.usuario_id = u.id ORDER BY r.fecha DESC, r.hora DESC')->fetchAll();

$today = date('Y-m-d');
$now = date('H:i');
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>📅 Reservas - Restaurante Mini</title>
    <link rel="stylesheet" href="public/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="public/css/mini.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg shadow-sm sticky-top">
        <div class="container">
            <a class="navbar-brand" href="index.html"><span>🍽️ Restaurante Mini</span></a>
            <div class="ms-auto d-flex align-items-center gap-3">
                <span class="text-muted">👤 Hola, <strong><?php echo htmlspecialchars($_SESSION['user']['nombre']); ?></strong></span>
                <a class="btn btn-outline-danger" href="logout.php">🚪 Salir</a>
            </div>
        </div>
    </nav>
            </div>
        </div>
    </nav>

    <main class="container py-4">
        <?php if ($message): ?>
            <div class="alert alert-success">✅ <strong>¡Genial!</strong> <?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <div class="row g-4">
            <div class="col-lg-5">
                <div class="card p-4 h-100">
                    <h4 class="card-title">🆕 Nueva reserva</h4>
                    <p class="text-muted">Registra una nueva mesa en segundos</p>
                    <form method="post">
                        <div class="mb-3">
                            <label class="form-label">🪑 Mesa</label>
                            <select name="mesa_id" class="form-select" required>
                                <option value="">Selecciona una mesa...</option>
                                <?php foreach ($mesas as $mesa): ?>
                                    <option value="<?php echo $mesa['id']; ?>">🪑 Mesa <?php echo $mesa['numero']; ?> (👥 <?php echo $mesa['capacidad']; ?> personas)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">📅 Fecha</label>
                            <input type="date" name="fecha" class="form-control" required value="<?php echo $today; ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">⏰ Hora</label>
                            <input type="time" name="hora" class="form-control" required value="<?php echo $now; ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">👥 Número de personas</label>
                            <input type="number" name="num_personas" class="form-control" min="1" max="20" placeholder="Ej: 4" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100 btn-lg">✅ Guardar reserva</button>
                    </form>
                </div>
            </div>
            <div class="col-lg-7">
                <div class="card p-4 h-100">
                    <h4 class="card-title">🍴 Menú disponible</h4>
                    <p class="text-muted">Platos ya definidos en el sistema</p>
                    <div class="row g-3">
                        <?php foreach ($platos as $plato): ?>
                            <div class="col-md-6">
                                <div class="feature-card">
                                    <h6 class="mb-2">🍽️ <?php echo htmlspecialchars($plato['nombre']); ?></h6>
                                    <span class="badge badge-soft">💵 S/ <?php echo number_format((float) $plato['precio'], 2); ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="card p-4 mt-4">
            <h4 class="card-title">📋 Historial de reservas</h4>
            <p class="text-muted">Vista de todas las reservas registradas</p>
            <?php if (!$reservas): ?>
                <div class="alert alert-warning">⚠️ No hay reservas todavía. ¡Crea la primera!</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>📅 Fecha</th>
                                <th>⏰ Hora</th>
                                <th>🪑 Mesa</th>
                                <th>👥 Personas</th>
                                <th>👤 Cliente</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reservas as $reserva): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($reserva['fecha']); ?></td>
                                    <td><?php echo htmlspecialchars(substr($reserva['hora'], 0, 5)); ?></td>
                                    <td>Mesa <?php echo htmlspecialchars($reserva['mesa']); ?></td>
                                    <td><?php echo htmlspecialchars($reserva['num_personas']); ?></td>
                                    <td><?php echo htmlspecialchars($reserva['cliente']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <script src="public/bootstrap/js/bootstrap.bundle.min.js"></script>
</body>
</html>
