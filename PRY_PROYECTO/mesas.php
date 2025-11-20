<?php
session_start();

// Verificar si el cliente está autenticado
if (!isset($_SESSION['cliente_id']) || !isset($_SESSION['cliente_authenticated']) || $_SESSION['cliente_authenticated'] !== true) {
    header('Location: index.html');
    exit;
}

require_once 'conexion/db.php';

$cliente_id = $_SESSION['cliente_id'];
$cliente_nombre = $_SESSION['cliente_nombre'] ?? '';
$cliente_apellido = $_SESSION['cliente_apellido'] ?? '';
$cliente_email = $_SESSION['cliente_email'] ?? '';

// Verificar si ya hay una mesa seleccionada en la sesión
$mesa_seleccionada_id = $_SESSION['mesa_seleccionada_id'] ?? null;
$mesa_seleccionada = null;

if ($mesa_seleccionada_id) {
    // Obtener información de la mesa seleccionada
    $stmt = $pdo->prepare("SELECT * FROM mesas WHERE id = ?");
    $stmt->execute([$mesa_seleccionada_id]);
    $mesa_seleccionada = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Le Salon de Lumière - Reservar Mesa</title>
    
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

        .hero-section {
            background: var(--gradient-dark);
            color: white;
            padding: 60px 0;
            margin-bottom: 50px;
            border-bottom: 3px solid var(--gold-color);
        }

        .hero-title {
            font-family: 'Playfair Display', serif;
            font-size: 3rem;
            font-weight: 700;
            color: var(--gold-color);
            margin-bottom: 15px;
        }

        .mesa-seleccionada-card {
            background: linear-gradient(135deg, var(--secondary-color) 0%, var(--primary-color) 100%);
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 10px 40px rgba(212, 175, 55, 0.5);
            border: 3px solid var(--gold-color);
            margin-bottom: 30px;
            animation: pulseGold 2s infinite;
            color: white;
        }

        @keyframes pulseGold {
            0%, 100% { box-shadow: 0 10px 40px rgba(212, 175, 55, 0.5); }
            50% { box-shadow: 0 15px 50px rgba(212, 175, 55, 0.7); }
        }

        .mesa-card-mini {
            background: linear-gradient(135deg, var(--secondary-color) 0%, var(--primary-color) 100%);
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 8px 25px rgba(212, 175, 55, 0.3);
            transition: all 0.3s ease;
            cursor: pointer;
            border: 2px solid rgba(212, 175, 55, 0.3);
            color: white;
        }

        .mesa-card-mini:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 35px rgba(212, 175, 55, 0.6);
            border-color: var(--gold-color);
        }

        .mesa-card-mini.disponible {
            border-left: 5px solid #28a745;
        }

        .mesa-card-mini.ocupada {
            border-left: 5px solid #dc3545;
            opacity: 0.6;
            cursor: not-allowed;
        }

        .mesa-card-mini.reservada {
            border-left: 5px solid #ffc107;
            opacity: 0.7;
            cursor: not-allowed;
        }

        .btn-gold {
            background: var(--gradient-gold);
            color: var(--primary-color);
            border: none;
            padding: 12px 30px;
            font-weight: 600;
            border-radius: 50px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(212, 175, 55, 0.4);
        }

        .btn-gold:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(212, 175, 55, 0.6);
            color: var(--primary-color);
        }

        .form-control {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(212, 175, 55, 0.3);
            color: white;
            border-radius: 10px;
        }

        .form-control::placeholder {
            color: rgba(255, 255, 255, 0.5);
        }

        .form-control:focus {
            background: rgba(255, 255, 255, 0.15);
            border-color: var(--gold-color);
            box-shadow: 0 0 0 0.2rem rgba(212, 175, 55, 0.25);
            color: white;
        }

        .form-label {
            color: var(--gold-color);
            font-weight: 600;
        }

        .badge-precio {
            background: var(--gradient-gold);
            color: var(--primary-color);
            padding: 8px 20px;
            border-radius: 20px;
            font-size: 1.2rem;
            font-weight: 700;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark navbar-custom">
        <div class="container">
            <a class="navbar-brand" href="index.html">
                <i class="bi bi-cup-hot-fill me-2"></i>
                Le Salon de Lumière
            </a>
            
            <div class="navbar-nav ms-auto">
                <div class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle text-white" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="bi bi-person-circle"></i>
                        <?php echo htmlspecialchars($cliente_nombre . ' ' . $cliente_apellido); ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="#" onclick="cerrarSesion()">
                            <i class="bi bi-box-arrow-right"></i> Cerrar Sesión
                        </a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section text-center">
        <div class="container">
            <h1 class="hero-title">
                <i class="bi bi-calendar-heart me-3"></i>
                Reserva tu Mesa
            </h1>
            <p class="lead" style="color: rgba(255,255,255,0.8);">
                Selecciona tu espacio perfecto en nuestro elegante restaurante
            </p>
        </div>
    </section>

    <div class="container pb-5">
        <?php if ($mesa_seleccionada): ?>
        <!-- Mesa Ya Seleccionada -->
        <div class="mesa-seleccionada-card">
            <div class="text-center mb-4">
                <h2 style="color: var(--gold-color);">
                    <i class="bi bi-check-circle-fill"></i> Mesa Seleccionada
                </h2>
                <p style="color: rgba(255,255,255,0.7);">Esta es tu mesa reservada. Puedes cambiarla si lo deseas.</p>
            </div>
            
            <div class="row align-items-center">
                <div class="col-md-4 text-center mb-3 mb-md-0">
                    <div style="font-size: 5rem; color: var(--gold-color);">
                        <?php 
                        $iconos = ['interior' => '🏛️', 'terraza' => '🌿', 'vip' => '👑', 'bar' => '🍸'];
                        echo $iconos[$mesa_seleccionada['ubicacion']] ?? '🍽️';
                        ?>
                    </div>
                    <h3 style="color: white; font-weight: 700;">
                        Mesa <?php echo htmlspecialchars($mesa_seleccionada['numero_mesa']); ?>
                    </h3>
                </div>
                
                <div class="col-md-8">
                    <div class="row g-3">
                        <div class="col-6">
                            <div class="p-3 rounded" style="background: rgba(255, 255, 255, 0.1); border: 1px solid rgba(212, 175, 55, 0.3);">
                                <i class="bi bi-geo-alt-fill" style="color: var(--gold-color);"></i>
                                <strong style="color: white;">Ubicación:</strong><br>
                                <span style="color: rgba(255,255,255,0.8);"><?php echo ucfirst($mesa_seleccionada['ubicacion']); ?></span>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="p-3 rounded" style="background: rgba(255, 255, 255, 0.1); border: 1px solid rgba(212, 175, 55, 0.3);">
                                <i class="bi bi-people-fill" style="color: var(--gold-color);"></i>
                                <strong style="color: white;">Capacidad:</strong><br>
                                <span style="color: rgba(255,255,255,0.8);"><?php echo $mesa_seleccionada['capacidad_minima']; ?>-<?php echo $mesa_seleccionada['capacidad_maxima']; ?> personas</span>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="p-3 text-center" style="background: linear-gradient(135deg, rgba(212, 175, 55, 0.2) 0%, rgba(212, 175, 55, 0.1) 100%); border-radius: 15px; border: 2px solid var(--gold-color);">
                                <div style="font-size: 0.9rem; color: var(--gold-color); margin-bottom: 5px; font-weight: 600;">Precio de Reserva</div>
                                <span class="badge-precio">$<?php echo number_format($mesa_seleccionada['precio_reserva'], 2); ?></span>
                                <div style="font-size: 0.8rem; color: rgba(255,255,255,0.6); margin-top: 5px;">+ Consumo de platos</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-3 text-center">
                        <button class="btn me-2" onclick="cambiarMesa()" style="background: rgba(255,255,255,0.1); color: white; border: 1px solid rgba(255,255,255,0.3);">
                            <i class="bi bi-arrow-repeat"></i> Cambiar Mesa
                        </button>
                        <button class="btn btn-gold" onclick="confirmarReserva()">
                            <i class="bi bi-calendar-check"></i> Confirmar Reserva
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <?php else: ?>
        <!-- Seleccionar Mesa -->
        <div class="row">
            <div class="col-12">
                <div class="card border-0 shadow-lg" style="background: linear-gradient(135deg, var(--secondary-color) 0%, var(--primary-color) 100%); border: 2px solid rgba(212, 175, 55, 0.3) !important;">
                    <div class="card-header text-white text-center" style="background: var(--gradient-gold); padding: 20px; border: none;">
                        <h4 class="mb-0" style="color: var(--primary-color);">
                            <i class="bi bi-diagram-3-fill me-2"></i>
                            Selecciona tu Mesa Disponible
                        </h4>
                    </div>
                    
                    <div class="card-body p-4">
                        <!-- Leyenda -->
                        <div class="text-center mb-4">
                            <span class="badge bg-success me-2">
                                <i class="bi bi-check-circle"></i> Disponible
                            </span>
                            <span class="badge bg-danger me-2">
                                <i class="bi bi-x-circle"></i> Ocupada
                            </span>
                            <span class="badge bg-warning">
                                <i class="bi bi-calendar-check"></i> Reservada
                            </span>
                        </div>
                        
                        <!-- Grid de Mesas -->
                        <div class="row g-3" id="mesasContainer">
                            <div class="col-12 text-center py-5">
                                <div class="spinner-border" style="color: var(--gold-color);" role="status"></div>
                                <p class="mt-3" style="color: rgba(255,255,255,0.7);">Cargando mesas disponibles...</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <script src="public/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script>
        // Cargar mesas disponibles
        async function cargarMesas() {
            try {
                const response = await fetch('app/api/mesas_estado.php');
                const data = await response.json();
                
                if (data.success) {
                    mostrarMesas(data.mesas);
                } else {
                    console.error('Error:', data.message);
                }
            } catch (error) {
                console.error('Error cargando mesas:', error);
            }
        }

        function mostrarMesas(mesas) {
            const container = document.getElementById('mesasContainer');
            if (!container) return;
            
            container.innerHTML = '';
            
            mesas.forEach(mesa => {
                const disponible = mesa.estado === 'disponible';
                const iconos = {
                    'interior': '🏛️',
                    'terraza': '🌿',
                    'vip': '👑',
                    'bar': '🍸'
                };
                
                const col = document.createElement('div');
                col.className = 'col-md-4 col-lg-3';
                col.innerHTML = `
                    <div class="mesa-card-mini ${mesa.estado}" onclick="${disponible ? `seleccionarMesa(${mesa.id})` : ''}">
                        <div class="text-center mb-2">
                            <div style="font-size: 2.5rem;">${iconos[mesa.ubicacion] || '🍽️'}</div>
                            <h5 style="color: white; font-weight: 700; margin: 10px 0;">
                                Mesa ${mesa.numero}
                            </h5>
                        </div>
                        
                        <div class="mb-2">
                            <small style="color: rgba(255,255,255,0.7);"><i class="bi bi-geo-alt"></i> ${mesa.ubicacion}</small><br>
                            <small style="color: rgba(255,255,255,0.7);"><i class="bi bi-people"></i> ${mesa.capacidad_minima}-${mesa.capacidad_maxima} personas</small>
                        </div>
                        
                        <div class="text-center mb-2">
                            <span class="badge ${disponible ? 'bg-success' : 'bg-danger'}">
                                ${disponible ? 'Disponible' : mesa.estado.charAt(0).toUpperCase() + mesa.estado.slice(1)}
                            </span>
                        </div>
                        
                        ${disponible ? `
                        <div class="text-center p-2" style="background: rgba(212, 175, 55, 0.2); border-radius: 10px; border: 1px solid var(--gold-color);">
                            <strong style="color: var(--gold-color); font-size: 1.2rem;">$${parseFloat(mesa.precio_reserva || 5).toFixed(2)}</strong>
                        </div>
                        ` : ''}
                    </div>
                `;
                
                container.appendChild(col);
            });
        }

        async function seleccionarMesa(mesaId) {
            const result = await Swal.fire({
                title: '¿Confirmar selección?',
                text: 'Esta mesa quedará guardada para tu reserva',
                icon: 'question',
                background: '#1a1a1a',
                color: '#ffffff',
                showCancelButton: true,
                confirmButtonText: 'Sí, seleccionar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#d4af37',
                cancelButtonColor: '#666'
            });
            
            if (result.isConfirmed) {
                try {
                    const response = await fetch('app/api/seleccionar_mesa.php', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json'},
                        body: JSON.stringify({ mesa_id: mesaId })
                    });
                    
                    const data = await response.json();
                    
                    if (data.success) {
                        Swal.fire({
                            icon: 'success',
                            title: '¡Mesa Seleccionada!',
                            text: 'Tu mesa ha sido guardada exitosamente',
                            background: '#1a1a1a',
                            color: '#ffffff',
                            confirmButtonColor: '#d4af37'
                        }).then(() => {
                            window.location.reload();
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: data.message,
                            background: '#1a1a1a',
                            color: '#ffffff',
                            confirmButtonColor: '#d4af37'
                        });
                    }
                } catch (error) {
                    console.error('Error:', error);
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'No se pudo seleccionar la mesa',
                        background: '#1a1a1a',
                        color: '#ffffff',
                        confirmButtonColor: '#d4af37'
                    });
                }
            }
        }

        function cambiarMesa() {
            Swal.fire({
                title: '¿Cambiar mesa?',
                text: 'Se deseleccionará tu mesa actual',
                icon: 'question',
                background: '#1a1a1a',
                color: '#ffffff',
                showCancelButton: true,
                confirmButtonText: 'Sí, cambiar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#d4af37',
                cancelButtonColor: '#666'
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch('app/api/deseleccionar_mesa.php', {
                        method: 'POST'
                    }).then(() => {
                        window.location.reload();
                    });
                }
            });
        }

        function confirmarReserva() {
            Swal.fire({
                title: 'Confirmar Reserva',
                background: '#1a1a1a',
                color: '#ffffff',
                html: `
                    <style>
                        .swal2-popup {
                            background: #1a1a1a !important;
                        }
                        .reserva-form-label {
                            color: #d4af37 !important;
                            font-weight: 600;
                            display: block;
                            text-align: left;
                            margin-bottom: 8px;
                            margin-top: 15px;
                        }
                        .reserva-form-control {
                            background: rgba(255, 255, 255, 0.95) !important;
                            border: 2px solid #d4af37 !important;
                            color: #1a1a1a !important;
                            border-radius: 10px;
                            padding: 12px;
                            width: 100%;
                            font-size: 1rem;
                            font-weight: 500;
                        }
                        .reserva-form-control:focus {
                            background: white !important;
                            border-color: #d4af37 !important;
                            box-shadow: 0 0 0 3px rgba(212, 175, 55, 0.3);
                            outline: none;
                        }
                    </style>
                    <div style="margin-top: 20px;">
                        <label class="reserva-form-label">Fecha de Reserva</label>
                        <input type="date" id="fecha_reserva" class="reserva-form-control" 
                            min="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div style="margin-top: 20px;">
                        <label class="reserva-form-label">Hora de Reserva</label>
                        <input type="time" id="hora_reserva" class="reserva-form-control" 
                            min="10:00" max="22:00" required>
                    </div>
                    <div style="margin-top: 20px;">
                        <label class="reserva-form-label">Número de Personas</label>
                        <input type="number" id="num_personas" class="reserva-form-control" 
                            min="<?php echo $mesa_seleccionada['capacidad_minima'] ?? 1; ?>" 
                            max="<?php echo $mesa_seleccionada['capacidad_maxima'] ?? 10; ?>" 
                            placeholder="Ingrese cantidad de personas" required>
                    </div>
                `,
                showCancelButton: true,
                confirmButtonText: 'Reservar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#d4af37',
                cancelButtonColor: '#666',
                width: '500px',
                didOpen: () => {
                    // Enfocar el primer campo al abrir
                    document.getElementById('fecha_reserva').focus();
                },
                preConfirm: () => {
                    const fecha = document.getElementById('fecha_reserva').value.trim();
                    const hora = document.getElementById('hora_reserva').value.trim();
                    const personas = document.getElementById('num_personas').value.trim();
                    
                    console.log('Validando:', {fecha, hora, personas}); // Debug
                    
                    if (!fecha) {
                        Swal.showValidationMessage('Por favor selecciona una fecha');
                        return false;
                    }
                    
                    if (!hora) {
                        Swal.showValidationMessage('Por favor selecciona una hora');
                        return false;
                    }
                    
                    if (!personas) {
                        Swal.showValidationMessage('Por favor ingresa el número de personas');
                        return false;
                    }
                    
                    const min = <?php echo $mesa_seleccionada['capacidad_minima'] ?? 1; ?>;
                    const max = <?php echo $mesa_seleccionada['capacidad_maxima'] ?? 10; ?>;
                    const numPersonas = parseInt(personas);
                    
                    if (isNaN(numPersonas) || numPersonas < min || numPersonas > max) {
                        Swal.showValidationMessage(`El número de personas debe estar entre ${min} y ${max}`);
                        return false;
                    }
                    
                    return { fecha, hora, personas: numPersonas };
                }
            }).then(async (result) => {
                if (result.isConfirmed) {
                    const { fecha, hora, personas } = result.value;
                    
                    try {
                        const response = await fetch('app/crear_reserva_admin.php', {
                            method: 'POST',
                            headers: {'Content-Type': 'application/json'},
                            body: JSON.stringify({
                                cliente_id: <?php echo $cliente_id; ?>,
                                mesa_id: <?php echo $mesa_seleccionada['id'] ?? 0; ?>,
                                fecha_reserva: fecha,
                                hora_reserva: hora,
                                numero_personas: personas
                            })
                        });
                        
                        const data = await response.json();
                        
                        if (data.success) {
                            // Mostrar confirmación con opciones post-reserva
                            Swal.fire({
                                icon: 'success',
                                title: '¡Reserva Confirmada!',
                                html: `
                                    <p style="color: #ffffff; margin-bottom: 30px;">Tu reserva ha sido creada exitosamente</p>
                                    <div style="display: flex; gap: 15px; justify-content: center;">
                                        <button onclick="terminarReserva()" class="btn btn-gold" style="flex: 1;">
                                            <i class="bi bi-check-circle-fill me-2"></i>Terminar Reserva
                                        </button>
                                        <button onclick="reservarPlatos()" class="btn btn-gold" style="flex: 1;">
                                            <i class="bi bi-basket-fill me-2"></i>Reservar Platos
                                        </button>
                                    </div>
                                `,
                                background: '#1a1a1a',
                                color: '#ffffff',
                                showConfirmButton: false,
                                showCloseButton: true
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: data.message || 'No se pudo crear la reserva',
                                background: '#1a1a1a',
                                color: '#ffffff',
                                confirmButtonColor: '#d4af37'
                            });
                        }
                    } catch (error) {
                        console.error('Error:', error);
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'No se pudo procesar la reserva',
                            background: '#1a1a1a',
                            color: '#ffffff',
                            confirmButtonColor: '#d4af37'
                        });
                    }
                }
            });
        }

        function terminarReserva() {
            Swal.fire({
                icon: 'success',
                title: '¡Gracias por tu reserva!',
                text: 'Te esperamos en Le Salon de Lumière',
                background: '#1a1a1a',
                color: '#ffffff',
                confirmButtonColor: '#d4af37',
                confirmButtonText: 'Volver al inicio'
            }).then(() => {
                window.location.href = 'index.html';
            });
        }

        function reservarPlatos() {
            // Redirigir a página de menú/platos (pendiente de crear)
            Swal.fire({
                icon: 'info',
                title: 'Menú de Platos',
                text: 'Próximamente podrás reservar tus platos favoritos',
                background: '#1a1a1a',
                color: '#ffffff',
                confirmButtonColor: '#d4af37'
            });
            // TODO: window.location.href = 'menu.php';
        }

        function cerrarSesion() {
            window.location.href = 'app/logout.php';
        }

        // Cargar mesas al iniciar
        <?php if (!$mesa_seleccionada): ?>
        document.addEventListener('DOMContentLoaded', cargarMesas);
        <?php endif; ?>
    </script>
</body>
</html>
