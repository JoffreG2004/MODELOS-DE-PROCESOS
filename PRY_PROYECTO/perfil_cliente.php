<?php
session_start();

// Verificar si el cliente está autenticado
if (!isset($_SESSION['cliente_id']) || !isset($_SESSION['cliente_authenticated']) || $_SESSION['cliente_authenticated'] !== true) {
    header('Location: index.html');
    exit;
}

require_once 'conexion/db.php';

// Actualizar automáticamente los estados de las reservas
try {
    $pdo->exec("CALL activar_reservas_programadas()");
} catch (PDOException $e) {
    // Log error silenciosamente, no interrumpir la carga de la página
    error_log("Error actualizando estados de reservas: " . $e->getMessage());
}

$cliente_id = $_SESSION['cliente_id'];
$cliente_nombre = $_SESSION['cliente_nombre'] ?? '';
$cliente_apellido = $_SESSION['cliente_apellido'] ?? '';
$cliente_email = $_SESSION['cliente_email'] ?? '';

// Obtener reservas normales del cliente
$stmt = $pdo->prepare("
    SELECT r.*, m.numero_mesa, m.capacidad_maxima,
           DATE_FORMAT(r.fecha_reserva, '%d/%m/%Y') as fecha_formateada,
           TIME_FORMAT(r.hora_reserva, '%H:%i') as hora_formateada,
           'normal' as tipo_reserva
    FROM reservas r
    INNER JOIN mesas m ON r.mesa_id = m.id
    WHERE r.cliente_id = ?
    ORDER BY r.fecha_reserva DESC, r.hora_reserva DESC
    LIMIT 50
");
$stmt->execute([$cliente_id]);
$reservas_normales = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener reservas de zona del cliente
$stmt = $pdo->prepare("
    SELECT 
        rz.id,
        rz.zonas,
        rz.fecha_reserva,
        rz.hora_reserva,
        rz.numero_personas,
        rz.precio_total,
        rz.cantidad_mesas,
        rz.estado,
        rz.motivo_cancelacion,
        DATE_FORMAT(rz.fecha_reserva, '%d/%m/%Y') as fecha_formateada,
        TIME_FORMAT(rz.hora_reserva, '%H:%i') as hora_formateada,
        'zona' as tipo_reserva
    FROM reservas_zonas rz
    WHERE rz.cliente_id = ?
    ORDER BY rz.fecha_reserva DESC, rz.hora_reserva DESC
    LIMIT 50
");
$stmt->execute([$cliente_id]);
$reservas_zonas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Decodificar zonas JSON y traducir nombres
$nombres_zonas = [
    'interior' => 'Salón Principal',
    'terraza' => 'Terraza',
    'vip' => 'Área VIP',
    'bar' => 'Bar & Lounge'
];

foreach ($reservas_zonas as &$reserva) {
    $zonas_array = json_decode($reserva['zonas'], true);
    $reserva['zonas_nombres'] = array_map(function($z) use ($nombres_zonas) {
        return $nombres_zonas[$z] ?? $z;
    }, $zonas_array);
    $reserva['zonas_texto'] = implode(', ', $reserva['zonas_nombres']);
}

// Combinar ambas listas
$reservas = array_merge($reservas_normales, $reservas_zonas);

// Ordenar por fecha
usort($reservas, function($a, $b) {
    $fecha_a = strtotime($a['fecha_reserva'] . ' ' . $a['hora_reserva']);
    $fecha_b = strtotime($b['fecha_reserva'] . ' ' . $b['hora_reserva']);
    return $fecha_b - $fecha_a;
});
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Perfil - Le Salon de Lumière</title>
    
    <link rel="stylesheet" href="public/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    
    <style>
        :root {
            --primary-color: #1a1a1a;
            --secondary-color: #0d0d0d;
            --gold-color: #d4af37;
            --gradient-gold: linear-gradient(135deg, #d4af37 0%, #f4d03f 100%);
            --gradient-dark: linear-gradient(135deg, #1a1a1a 0%, #0d0d0d 100%);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: var(--gradient-dark);
            min-height: 100vh;
            color: white;
        }

        .navbar-custom {
            background: var(--secondary-color) !important;
            box-shadow: 0 4px 15px rgba(212, 175, 55, 0.3);
            padding: 15px 0;
            border-bottom: 2px solid var(--gold-color);
        }

        .navbar-brand {
            font-family: 'Playfair Display', serif;
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--gold-color) !important;
            text-shadow: 0 2px 10px rgba(212, 175, 55, 0.5);
        }

        .nav-link {
            color: rgba(255, 255, 255, 0.9) !important;
            font-weight: 500;
            transition: all 0.3s;
        }

        .nav-link:hover {
            color: var(--gold-color) !important;
        }

        .btn-gold {
            background: var(--gradient-gold);
            color: var(--primary-color);
            font-weight: 600;
            border: none;
            padding: 10px 25px;
            border-radius: 50px;
            transition: all 0.3s;
        }

        .btn-gold:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(212, 175, 55, 0.5);
        }

        .btn-outline-gold {
            background: transparent;
            border: 2px solid var(--gold-color);
            color: var(--gold-color);
            font-weight: 600;
            padding: 12px 30px;
            border-radius: 50px;
            transition: all 0.3s;
        }

        .btn-outline-gold:hover {
            background: var(--gold-color);
            color: var(--dark-bg);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(212, 175, 55, 0.5);
        }

        .zona-check-item {
            background: rgba(212, 175, 55, 0.05);
            transition: all 0.3s;
        }

        .zona-check-item:hover {
            background: rgba(212, 175, 55, 0.15);
            border-color: var(--gold-color) !important;
        }

        .zona-checkbox:checked + label {
            color: var(--gold-color);
        }

        .hero-section {
            background: var(--gradient-dark);
            padding: 40px 0;
            margin-bottom: 40px;
            border-bottom: 3px solid var(--gold-color);
        }

        .hero-title {
            font-family: 'Playfair Display', serif;
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--gold-color);
            margin-bottom: 10px;
        }

        .profile-card {
            background: linear-gradient(135deg, var(--secondary-color) 0%, var(--primary-color) 100%);
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 10px 40px rgba(212, 175, 55, 0.3);
            border: 2px solid var(--gold-color);
            margin-bottom: 30px;
        }

        .reserva-card {
            background: rgba(26, 26, 26, 0.8);
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            border: 1px solid rgba(212, 175, 55, 0.3);
            transition: all 0.3s;
        }

        .reserva-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(212, 175, 55, 0.4);
            border-color: var(--gold-color);
        }

        .badge-estado {
            padding: 8px 15px;
            border-radius: 50px;
            font-weight: 600;
            font-size: 0.85rem;
        }

        .badge-pendiente {
            background: linear-gradient(135deg, #ffa726 0%, #fb8c00 100%);
            color: white;
        }

        .badge-confirmada {
            background: linear-gradient(135deg, #66bb6a 0%, #43a047 100%);
            color: white;
        }

        .badge-cancelada {
            background: linear-gradient(135deg, #ef5350 0%, #e53935 100%);
            color: white;
        }

        .badge-finalizada {
            background: linear-gradient(135deg, #78909c 0%, #546e7a 100%);
            color: white;
        }

        .badge-en_curso {
            background: linear-gradient(135deg, #42a5f5 0%, #1e88e5 100%);
            color: white;
        }

        @media print {
            body * {
                visibility: hidden;
            }
            .print-area, .print-area * {
                visibility: visible;
            }
            .print-area {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark navbar-custom">
        <div class="container">
            <a class="navbar-brand" href="index.html">
                <i class="bi bi-gem me-2"></i>Le Salon de Lumière
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="mesas.php">
                            <i class="bi bi-table me-1"></i>Reservar
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="perfil_cliente.php">
                            <i class="bi bi-person-circle me-1"></i>Mi Perfil
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="app/logout.php">
                            <i class="bi bi-box-arrow-right me-1"></i>Cerrar Sesión
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container text-center">
            <h1 class="hero-title">
                <i class="bi bi-person-circle me-3"></i>Mi Perfil
            </h1>
            <p style="color: rgba(255, 255, 255, 0.8); font-size: 1.1rem;">
                Bienvenido, <?php echo htmlspecialchars($cliente_nombre . ' ' . $cliente_apellido); ?>
            </p>
        </div>
    </section>

    <!-- Contenido Principal -->
    <section class="container mb-5">
        <div class="row">
            <!-- Información del Cliente -->
            <div class="col-md-4 mb-4">
                <div class="profile-card">
                    <div class="text-center mb-4">
                        <i class="bi bi-person-circle" style="font-size: 5rem; color: var(--gold-color);"></i>
                    </div>
                    <h4 style="color: var(--gold-color); text-align: center; margin-bottom: 20px;">
                        <?php echo htmlspecialchars($cliente_nombre . ' ' . $cliente_apellido); ?>
                    </h4>
                    <div style="color: rgba(255, 255, 255, 0.9);">
                        <p><i class="bi bi-envelope me-2" style="color: var(--gold-color);"></i><?php echo htmlspecialchars($cliente_email); ?></p>
                        <p><i class="bi bi-calendar-check me-2" style="color: var(--gold-color);"></i><?php echo count($reservas); ?> reserva(s) total</p>
                    </div>
                    <hr style="border-color: rgba(212, 175, 55, 0.3);">
                    <a href="mesas.php" class="btn btn-gold w-100 mb-2">
                        <i class="bi bi-plus-circle me-2"></i>Nueva Reserva
                    </a>
                    <button onclick="mostrarReservaZona()" class="btn btn-outline-gold w-100 mb-2">
                        <i class="bi bi-building me-2"></i>Reservar Zona Completa
                    </button>
                    <a href="app/logout.php" class="btn btn-outline-light w-100">
                        <i class="bi bi-box-arrow-right me-2"></i>Cerrar Sesión
                    </a>
                </div>
            </div>

            <!-- Mis Reservas -->
            <div class="col-md-8">
                <h3 style="color: var(--gold-color); margin-bottom: 25px;">
                    <i class="bi bi-calendar2-check me-2"></i>Mis Reservas
                </h3>

                <?php if (empty($reservas)): ?>
                    <div class="text-center" style="padding: 60px 20px;">
                        <i class="bi bi-inbox" style="font-size: 4rem; color: rgba(212, 175, 55, 0.5);"></i>
                        <h4 style="color: rgba(255, 255, 255, 0.7); margin-top: 20px;">No tienes reservas aún</h4>
                        <p style="color: rgba(255, 255, 255, 0.5);">¡Haz tu primera reserva ahora!</p>
                        <a href="mesas.php" class="btn btn-gold mt-3">
                            <i class="bi bi-plus-circle me-2"></i>Reservar Mesa
                        </a>
                    </div>
                <?php else: ?>
                    <?php foreach ($reservas as $reserva): ?>
                        <div class="reserva-card">
                            <div class="row align-items-center">
                                <div class="col-md-8">
                                    <div class="d-flex align-items-center mb-2">
                                        <span class="badge-estado badge-<?php echo $reserva['estado']; ?>">
                                            <?php echo strtoupper($reserva['estado']); ?>
                                        </span>
                                        <span style="color: rgba(255, 255, 255, 0.6); margin-left: 15px;">
                                            <?php if ($reserva['tipo_reserva'] === 'zona'): ?>
                                                <i class="bi bi-building me-1"></i>Reserva de Zona #<?php echo $reserva['id']; ?>
                                            <?php else: ?>
                                                Reserva #<?php echo $reserva['id']; ?>
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                    
                                    <?php if ($reserva['tipo_reserva'] === 'zona'): ?>
                                        <!-- Reserva de Zona -->
                                        <h5 style="color: var(--gold-color); margin-bottom: 10px;">
                                            <i class="bi bi-building me-2"></i><?php echo $reserva['zonas_texto']; ?>
                                        </h5>
                                        <p style="color: rgba(255, 255, 255, 0.9); margin-bottom: 5px;">
                                            <i class="bi bi-table me-2"></i><?php echo $reserva['cantidad_mesas']; ?> mesa(s) incluidas
                                        </p>
                                        <p style="color: rgba(255, 255, 255, 0.9); margin-bottom: 5px;">
                                            <i class="bi bi-calendar3 me-2"></i><?php echo $reserva['fecha_formateada']; ?>
                                            <i class="bi bi-clock ms-3 me-2"></i><?php echo $reserva['hora_formateada']; ?>
                                        </p>
                                        <p style="color: rgba(255, 255, 255, 0.9); margin-bottom: 5px;">
                                            <i class="bi bi-people me-2"></i><?php echo $reserva['numero_personas']; ?> persona(s)
                                        </p>
                                        <p style="color: var(--gold-color); font-weight: 600; margin-bottom: 0;">
                                            <i class="bi bi-cash me-2"></i>Total: $<?php echo number_format($reserva['precio_total'], 2); ?>
                                        </p>
                                    <?php else: ?>
                                        <!-- Reserva Normal -->
                                        <h5 style="color: var(--gold-color); margin-bottom: 10px;">
                                            <i class="bi bi-table me-2"></i>Mesa <?php echo $reserva['numero_mesa']; ?>
                                        </h5>
                                        <p style="color: rgba(255, 255, 255, 0.9); margin-bottom: 5px;">
                                            <i class="bi bi-calendar3 me-2"></i><?php echo $reserva['fecha_formateada']; ?>
                                            <i class="bi bi-clock ms-3 me-2"></i><?php echo $reserva['hora_formateada']; ?>
                                        </p>
                                        <p style="color: rgba(255, 255, 255, 0.9); margin-bottom: 0;">
                                            <i class="bi bi-people me-2"></i><?php echo $reserva['numero_personas']; ?> persona(s)
                                        </p>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($reserva['motivo_cancelacion'])): ?>
                                        <p style="color: #ef4444; margin-top: 10px; font-size: 0.9rem;">
                                            <i class="bi bi-exclamation-triangle me-2"></i>Motivo: <?php echo htmlspecialchars($reserva['motivo_cancelacion']); ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-4 text-end">
                                    <?php if ($reserva['estado'] === 'confirmada' || $reserva['estado'] === 'finalizada'): ?>
                                        <button class="btn btn-gold btn-sm mb-2" onclick="imprimirNota(<?php echo $reserva['id']; ?>, '<?php echo $reserva['tipo_reserva']; ?>')">
                                            <i class="bi bi-printer me-1"></i>Imprimir Nota
                                        </button>
                                    <?php endif; ?>
                                    <?php if ($reserva['estado'] === 'pendiente'): ?>
                                        <span class="badge bg-warning text-dark mb-2 d-block">
                                            <i class="bi bi-hourglass-split me-1"></i>Esperando confirmación
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Bootstrap JS -->
    <script src="public/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        async function cancelarReserva(reservaId) {
            const result = await Swal.fire({
                title: '¿Cancelar reserva?',
                text: 'Esta acción no se puede deshacer',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d4af37',
                cancelButtonColor: '#666',
                confirmButtonText: 'Sí, cancelar',
                cancelButtonText: 'No',
                background: '#1a1a1a',
                color: '#ffffff'
            });

            if (result.isConfirmed) {
                try {
                    const response = await fetch('app/editar_reserva.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            id: reservaId,
                            estado: 'cancelada'
                        })
                    });

                    const data = await response.json();

                    if (data.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Reserva cancelada',
                            text: 'Tu reserva ha sido cancelada exitosamente',
                            background: '#1a1a1a',
                            color: '#ffffff',
                            confirmButtonColor: '#d4af37'
                        }).then(() => {
                            location.reload();
                        });
                    } else {
                        throw new Error(data.message);
                    }
                } catch (error) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: error.message || 'No se pudo cancelar la reserva',
                        background: '#1a1a1a',
                        color: '#ffffff',
                        confirmButtonColor: '#d4af37'
                    });
                }
            }
        }

        function formatearMoneda(valor) {
            return Number(valor || 0).toFixed(2);
        }

        function escaparHtml(texto) {
            return String(texto ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        async function imprimirNota(reservaId, tipoReserva = 'normal') {
            try {
                const response = await fetch('app/api/obtener_nota_cliente.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        reserva_id: Number(reservaId),
                        tipo_reserva: tipoReserva
                    })
                });

                const data = await response.json();
                if (!data.success || !data.nota) {
                    throw new Error(data.message || 'No se pudo obtener la nota');
                }

                const nota = data.nota;
                const fechaHora = new Date().toLocaleString('es-EC');
                const itemsHtml = (nota.items && nota.items.length > 0)
                    ? nota.items.map(item => `
                        <tr>
                            <td>${escaparHtml(item.nombre)}</td>
                            <td style="text-align:center;">${item.cantidad}</td>
                            <td style="text-align:right;">$${formatearMoneda(item.precio_unitario)}</td>
                            <td style="text-align:right;">$${formatearMoneda(item.subtotal)}</td>
                        </tr>
                    `).join('')
                    : `<tr><td colspan="4" style="text-align:center; color:#666;">Sin platos asociados</td></tr>`;

                const ventana = window.open('', '_blank', 'width=900,height=700');
                if (!ventana) {
                    throw new Error('El navegador bloqueó la ventana de impresión. Habilita popups para este sitio.');
                }

                const html = `
                    <!DOCTYPE html>
                    <html lang="es">
                    <head>
                        <meta charset="UTF-8">
                        <title>Nota ${escaparHtml(nota.numero_nota)}</title>
                        <style>
                            body { font-family: Arial, sans-serif; margin: 24px; color: #111; }
                            .header { display:flex; justify-content:space-between; align-items:flex-start; border-bottom:2px solid #d4af37; padding-bottom:12px; margin-bottom:16px; }
                            .title { font-size: 22px; font-weight: 700; }
                            .subtitle { color: #666; font-size: 13px; }
                            .card { border:1px solid #ddd; border-radius:8px; padding:12px; margin-bottom:12px; }
                            .grid { display:grid; grid-template-columns:1fr 1fr; gap:10px; }
                            table { width:100%; border-collapse: collapse; margin-top:8px; }
                            th, td { border:1px solid #ddd; padding:8px; font-size: 13px; }
                            th { background: #f7f7f7; }
                            .totales { margin-top:10px; width:320px; margin-left:auto; }
                            .totales-row { display:flex; justify-content:space-between; padding:6px 0; border-bottom:1px dashed #ddd; }
                            .total-final { font-size:18px; font-weight:700; border-bottom:none; }
                            .muted { color:#666; font-size:12px; margin-top:20px; }
                            @media print { .no-print { display:none; } body { margin: 10mm; } }
                        </style>
                    </head>
                    <body>
                        <div class="header">
                            <div>
                                <div class="title">Le Salon de Lumiere</div>
                                <div class="subtitle">Nota de consumo / venta</div>
                            </div>
                            <div style="text-align:right;">
                                <div><strong>${escaparHtml(nota.numero_nota)}</strong></div>
                                <div class="subtitle">Emitida: ${escaparHtml(fechaHora)}</div>
                            </div>
                        </div>

                        <div class="card grid">
                            <div><strong>Cliente:</strong> ${escaparHtml(nota.cliente_nombre)}</div>
                            <div><strong>Email:</strong> ${escaparHtml(nota.cliente_email || '-')}</div>
                            <div><strong>Reserva:</strong> ${escaparHtml(nota.detalle_reserva)}</div>
                            <div><strong>Estado:</strong> ${escaparHtml(nota.estado)}</div>
                            <div><strong>Fecha:</strong> ${escaparHtml(nota.fecha_reserva)}</div>
                            <div><strong>Hora:</strong> ${escaparHtml(nota.hora_reserva)}</div>
                            <div><strong>Personas:</strong> ${escaparHtml(nota.numero_personas)}</div>
                            <div><strong>Tipo:</strong> ${escaparHtml(nota.tipo_reserva)}</div>
                        </div>

                        <div class="card">
                            <strong>Detalle de consumo</strong>
                            <table>
                                <thead>
                                    <tr>
                                        <th>Concepto</th>
                                        <th style="width:80px;">Cant.</th>
                                        <th style="width:120px;">P. Unit.</th>
                                        <th style="width:120px;">Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>Reserva (${escaparHtml(nota.detalle_reserva)})</td>
                                        <td style="text-align:center;">1</td>
                                        <td style="text-align:right;">$${formatearMoneda(nota.subtotal_mesa)}</td>
                                        <td style="text-align:right;">$${formatearMoneda(nota.subtotal_mesa)}</td>
                                    </tr>
                                    ${itemsHtml}
                                </tbody>
                            </table>

                            <div class="totales">
                                <div class="totales-row"><span>Subtotal mesa:</span><span>$${formatearMoneda(nota.subtotal_mesa)}</span></div>
                                <div class="totales-row"><span>Subtotal platos:</span><span>$${formatearMoneda(nota.subtotal_platos)}</span></div>
                                <div class="totales-row"><span>Impuesto:</span><span>$${formatearMoneda(nota.impuesto)}</span></div>
                                <div class="totales-row"><span>Descuento:</span><span>$${formatearMoneda(nota.descuento || 0)}</span></div>
                                <div class="totales-row total-final"><span>TOTAL:</span><span>$${formatearMoneda(nota.total)}</span></div>
                            </div>
                        </div>

                        <p class="muted">Documento generado desde el perfil del cliente.</p>
                        <button class="no-print" onclick="window.print()" style="padding:10px 18px; border:none; background:#d4af37; border-radius:6px; cursor:pointer;">Imprimir</button>
                    </body>
                    </html>
                `;

                ventana.document.open();
                ventana.document.write(html);
                ventana.document.close();
                ventana.focus();
                setTimeout(() => ventana.print(), 400);
            } catch (error) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: error.message || 'No se pudo generar la nota',
                    background: '#1a1a1a',
                    color: '#ffffff',
                    confirmButtonColor: '#d4af37'
                });
            }
        }
    </script>

    <!-- Script de Reserva de Zonas -->
    <script src="public/js/reserva-zonas.js?v=20260210-aforo2"></script>

    <!-- Script de Seguridad: Deshabilitar click derecho en formularios -->
    <script src="public/js/security.js"></script>
</body>
</html>
