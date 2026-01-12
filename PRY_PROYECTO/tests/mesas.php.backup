<?php
session_start();

// Verificar si el cliente está autenticado
if (!isset($_SESSION['cliente_id']) || !isset($_SESSION['cliente_authenticated']) || $_SESSION['cliente_authenticated'] !== true) {
    // Si no está autenticado, redirigir al index
    header('Location: index.html');
    exit;
}

// Conectar a la base de datos
require_once 'conexion/db.php';

// Obtener información del cliente
$cliente_id = $_SESSION['cliente_id'];
$cliente_nombre = $_SESSION['cliente_nombre'] ?? '';
$cliente_apellido = $_SESSION['cliente_apellido'] ?? '';
$cliente_email = $_SESSION['cliente_email'] ?? '';
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restaurante Elegante - Reservar Mesa</title>

    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="public/bootstrap/css/bootstrap.min.css">

    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Custom CSS -->
    <link rel="stylesheet" href="public/css/style.css">

    <!-- SweetAlert2 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
</head>

<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="#">
                <i class="bi bi-cup-hot-fill me-2"></i>
                Restaurante Elegante
            </a>
            
            <div class="navbar-nav ms-auto">
                <div class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="bi bi-person-circle"></i>
                        <?php echo htmlspecialchars($cliente_nombre . ' ' . $cliente_apellido); ?>
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="#" onclick="cerrarSesion()">
                            <i class="bi bi-box-arrow-right"></i> Cerrar Sesión
                        </a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <div class="container mt-5">
        <div class="row">
            <div class="col-12">
                <div class="card shadow-lg border-0">
                    <div class="card-header text-white text-center" style="background: var(--gradient-gold);">
                        <h3 class="mb-0">
                            <i class="bi bi-calendar-heart me-2"></i>
                            Reservar Mesa - Restaurante Elegante
                        </h3>
                        <p class="mb-0 mt-2">
                            <small>Bienvenido/a <?php echo htmlspecialchars($cliente_nombre); ?>, selecciona tu mesa preferida</small>
                        </p>
                    </div>
                    
                    <div class="card-body">
                        <!-- Formulario de Reserva -->
                        <div class="row">
                            <div class="col-md-8">
                                <h5><i class="bi bi-calendar-event me-2"></i>Detalles de la Reserva</h5>
                                <form id="reservaForm">
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">
                                                <i class="bi bi-calendar-date"></i> Fecha
                                            </label>
                                            <input type="date" class="form-control" id="fecha_reserva" required
                                                min="<?php echo date('Y-m-d'); ?>">
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">
                                                <i class="bi bi-clock"></i> Hora
                                            </label>
                                            <select class="form-select" id="hora_reserva" required>
                                                <option value="">Seleccionar hora...</option>
                                                <option value="12:00">12:00 PM - Almuerzo</option>
                                                <option value="12:30">12:30 PM - Almuerzo</option>
                                                <option value="13:00">1:00 PM - Almuerzo</option>
                                                <option value="13:30">1:30 PM - Almuerzo</option>
                                                <option value="14:00">2:00 PM - Almuerzo</option>
                                                <option value="19:00">7:00 PM - Cena</option>
                                                <option value="19:30">7:30 PM - Cena</option>
                                                <option value="20:00">8:00 PM - Cena</option>
                                                <option value="20:30">8:30 PM - Cena</option>
                                                <option value="21:00">9:00 PM - Cena</option>
                                            </select>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">
                                                <i class="bi bi-people"></i> Número de Personas
                                            </label>
                                            <select class="form-select" id="num_personas" required>
                                                <option value="">Seleccionar...</option>
                                                <option value="1">1 persona</option>
                                                <option value="2">2 personas</option>
                                                <option value="3">3 personas</option>
                                                <option value="4">4 personas</option>
                                                <option value="5">5 personas</option>
                                                <option value="6">6 personas</option>
                                                <option value="7">7 personas</option>
                                                <option value="8">8 personas</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">
                                                <i class="bi bi-chat-text"></i> Observaciones (Opcional)
                                            </label>
                                            <textarea class="form-control" id="observaciones" rows="3" 
                                                placeholder="Alergias, preferencias especiales, etc."></textarea>
                                        </div>
                                    </div>

                                    <div class="d-grid">
                                        <button type="submit" class="btn btn-lg" 
                                            style="background: var(--gradient-gold); color: var(--primary-color); font-weight: 600;">
                                            <i class="bi bi-calendar-check"></i>
                                            Confirmar Reserva
                                        </button>
                                    </div>
                                </form>
                            </div>

                            <div class="col-md-4">
                                <div class="card bg-light">
                                    <div class="card-body">
                                        <h6><i class="bi bi-info-circle-fill text-info"></i> Información Importante</h6>
                                        <small class="text-muted">
                                            <p><i class="bi bi-check-circle text-success"></i> Las reservas se confirman automáticamente</p>
                                            <p><i class="bi bi-check-circle text-success"></i> Puedes cancelar hasta 2 horas antes</p>
                                            <p><i class="bi bi-check-circle text-success"></i> Mesa reservada por 2 horas máximo</p>
                                            <p><i class="bi bi-telephone text-primary"></i> Dudas: 0998521340</p>
                                        </small>
                                    </div>
                                </div>

                                <div class="card bg-warning bg-opacity-10 mt-3">
                                    <div class="card-body">
                                        <h6><i class="bi bi-star-fill text-warning"></i> Tu Información</h6>
                                        <small>
                                            <strong>Nombre:</strong> <?php echo htmlspecialchars($cliente_nombre . ' ' . $cliente_apellido); ?><br>
                                            <strong>Email:</strong> <?php echo htmlspecialchars($cliente_email); ?><br>
                                            <strong>Cliente ID:</strong> #<?php echo str_pad($cliente_id, 4, '0', STR_PAD_LEFT); ?>
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Mis Reservas -->
                <div class="card shadow-lg border-0 mt-4">
                    <div class="card-header bg-secondary text-white">
                        <h5 class="mb-0">
                            <i class="bi bi-list-check me-2"></i>
                            Mis Reservas Recientes
                        </h5>
                    </div>
                    <div class="card-body">
                        <div id="misReservas">
                            <div class="text-center">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">Cargando...</span>
                                </div>
                                <p class="mt-2">Cargando tus reservas...</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="public/bootstrap/js/bootstrap.bundle.min.js"></script>
    
    <!-- SweetAlert2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        // Envío del formulario de reserva
        document.getElementById('reservaForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const fecha = document.getElementById('fecha_reserva').value;
            const hora = document.getElementById('hora_reserva').value;
            const personas = document.getElementById('num_personas').value;
            const observaciones = document.getElementById('observaciones').value;
            
            if (!fecha || !hora || !personas) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Campos requeridos',
                    text: 'Por favor complete todos los campos obligatorios'
                });
                return;
            }

            // Mostrar loading
            Swal.fire({
                title: 'Procesando Reserva...',
                html: 'Verificando disponibilidad de mesa',
                timer: 2000,
                timerProgressBar: true,
                didOpen: () => {
                    Swal.showLoading();
                }
            }).then(() => {
                // Simular reserva exitosa
                Swal.fire({
                    icon: 'success',
                    title: '¡Reserva Confirmada!',
                    html: `
                        <p><strong>Fecha:</strong> ${fecha}</p>
                        <p><strong>Hora:</strong> ${hora}</p>
                        <p><strong>Personas:</strong> ${personas}</p>
                        <p class="mt-3 text-muted">Recibirás confirmación por email</p>
                    `,
                    timer: 4000,
                    timerProgressBar: true
                });

                // Limpiar formulario
                document.getElementById('reservaForm').reset();
                
                // Recargar reservas
                setTimeout(cargarMisReservas, 2000);
            });
        });

        // Cargar reservas del cliente
        function cargarMisReservas() {
            fetch('app/obtener_reservas_cliente.php')
                .then(response => response.json())
                .then(data => {
                    const container = document.getElementById('misReservas');
                    
                    if (data.success && data.reservas.length > 0) {
                        let html = '<div class="row">';
                        
                        data.reservas.forEach(reserva => {
                            const estado_color = reserva.estado === 'confirmada' ? 'success' : 
                                                reserva.estado === 'pendiente' ? 'warning' : 'danger';
                            
                            html += `
                                <div class="col-md-6 mb-3">
                                    <div class="card border-${estado_color}">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between">
                                                <h6 class="card-title">Reserva #${reserva.id}</h6>
                                                <span class="badge bg-${estado_color}">${reserva.estado}</span>
                                            </div>
                                            <p class="card-text mb-1">
                                                <i class="bi bi-calendar"></i> ${reserva.fecha_reserva}
                                                <i class="bi bi-clock ms-2"></i> ${reserva.hora_reserva}
                                            </p>
                                            <p class="card-text">
                                                <i class="bi bi-people"></i> ${reserva.num_personas} personas
                                            </p>
                                            ${reserva.observaciones ? `<p class="card-text"><small class="text-muted">${reserva.observaciones}</small></p>` : ''}
                                        </div>
                                    </div>
                                </div>
                            `;
                        });
                        
                        html += '</div>';
                        container.innerHTML = html;
                    } else {
                        container.innerHTML = `
                            <div class="text-center text-muted">
                                <i class="bi bi-calendar-x" style="font-size: 3rem;"></i>
                                <p class="mt-2">No tienes reservas aún</p>
                                <small>¡Haz tu primera reserva usando el formulario de arriba!</small>
                            </div>
                        `;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    document.getElementById('misReservas').innerHTML = `
                        <div class="alert alert-warning">
                            <i class="bi bi-exclamation-triangle"></i>
                            No se pudieron cargar las reservas
                        </div>
                    `;
                });
        }

        // Cerrar sesión
        function cerrarSesion() {
            Swal.fire({
                title: '¿Cerrar Sesión?',
                text: 'Se cerrará tu sesión actual',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Sí, cerrar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'app/logout.php';
                }
            });
        }

        // Cargar reservas al inicio
        document.addEventListener('DOMContentLoaded', function() {
            cargarMisReservas();
        });
    </script>
</body>

</html>
