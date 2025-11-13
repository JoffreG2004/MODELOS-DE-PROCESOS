<?php
session_start();
require_once 'conexion/db.php';

// Verificar que el administrador esté autenticado
if (!isset($_SESSION['admin_authenticated']) || $_SESSION['admin_authenticated'] !== true) {
    // Si no está autenticado, redirigir al index
    header('Location: index.html');
    exit;
}

// Prevenir caché del navegador para evitar acceso con botón atrás
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Obtener datos del administrador desde la sesión
$admin_nombre = $_SESSION['admin_nombre'] ?? 'Administrador';
$admin_apellido = $_SESSION['admin_apellido'] ?? '';
$admin_usuario = $_SESSION['admin_usuario'] ?? '';
$admin_email = $_SESSION['admin_email'] ?? '';

try {
        // Estadísticas generales
        $stats = [];
        
        // Total de mesas
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM mesas");
        $stats['mesas_total'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Mesas disponibles
        $stmt = $pdo->query("SELECT COUNT(*) as disponibles FROM mesas WHERE estado = 'disponible'");
        $stats['mesas_disponibles'] = $stmt->fetch(PDO::FETCH_ASSOC)['disponibles'];
        
        // Reservas de hoy
        $stmt = $pdo->query("SELECT COUNT(*) as hoy FROM reservas WHERE DATE(fecha_reserva) = CURDATE()");
        $stats['reservas_hoy'] = $stmt->fetch(PDO::FETCH_ASSOC)['hoy'];
        
        // Reservas pendientes
        $stmt = $pdo->query("SELECT COUNT(*) as pendientes FROM reservas WHERE estado = 'pendiente'");
        $stats['reservas_pendientes'] = $stmt->fetch(PDO::FETCH_ASSOC)['pendientes'];
        
        // Total de clientes
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM clientes");
        $stats['clientes_total'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Total de platos
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM platos WHERE activo = 1");
        $stats['platos_total'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Reservas recientes (últimas 5)
        $stmt = $pdo->query("
            SELECT r.*, c.nombre, c.apellido, c.telefono, m.numero_mesa, m.ubicacion
            FROM reservas r
            JOIN clientes c ON r.cliente_id = c.id
            JOIN mesas m ON r.mesa_id = m.id
            ORDER BY r.fecha_creacion DESC
            LIMIT 5
        ");
        $reservas_recientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Estado de mesas
        $stmt = $pdo->query("
            SELECT estado, COUNT(*) as cantidad
            FROM mesas
            GROUP BY estado
        ");
        $mesas_por_estado = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        $error = "Error al obtener estadísticas: " . $e->getMessage();
    }
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Restaurante Elegante</title>

    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="public/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="public/css/style.css">

    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;500;600;700&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">

    <!-- SweetAlert2 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    
    <!-- Restaurant Layout CSS -->
    <link rel="stylesheet" href="public/css/restaurant-layout-new.css?v=<?php echo time(); ?>">

    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: var(--gradient-warm);
            min-height: 100vh;
        }

        .login-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .login-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: var(--shadow-xl);
            border: 1px solid rgba(212, 175, 55, 0.2);
            overflow: hidden;
            max-width: 400px;
            width: 90%;
        }

        .login-header {
            background: var(--gradient-primary);
            color: var(--text-light);
            padding: 2rem;
            text-align: center;
        }

        .login-title {
            font-family: 'Playfair Display', serif;
            font-size: 1.8rem;
            margin-bottom: 0.5rem;
        }

        .dashboard-sidebar {
            background: var(--gradient-primary);
            min-height: 100vh;
            color: var(--text-light);
        }

        .sidebar-header {
            padding: 2rem;
            text-align: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .sidebar-nav .nav-link {
            color: rgba(255, 255, 255, 0.9);
            padding: 1rem 2rem;
            border-radius: 0;
            transition: all 0.3s ease;
        }

        .sidebar-nav .nav-link:hover,
        .sidebar-nav .nav-link.active {
            color: var(--accent-light);
            background: rgba(255, 255, 255, 0.1);
        }

        .dashboard-content {
            background: var(--light-bg);
            min-height: 100vh;
        }

        .stats-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: var(--shadow);
            border-left: 4px solid;
            transition: transform 0.3s ease;
        }

        .stats-card:hover {
            transform: translateY(-5px);
        }

        .stats-card.primary { border-left-color: var(--primary-color); }
        .stats-card.success { border-left-color: var(--success-color); }
        .stats-card.warning { border-left-color: var(--warning-color); }
        .stats-card.info { border-left-color: var(--info-color); }

        .stats-number {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .stats-label {
            color: var(--text-secondary);
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .dashboard-header {
            background: white;
            padding: 1rem 2rem;
            box-shadow: var(--shadow-sm);
            border-bottom: 1px solid rgba(212, 175, 55, 0.1);
        }
    </style>
</head>

<body>
    <!-- DASHBOARD PRINCIPAL -->
    <div class="container-fluid">
        <div class="row">
            <!-- SIDEBAR -->
            <div class="col-md-3 col-lg-2 p-0">
                <div class="dashboard-sidebar">
                    <div class="sidebar-header">
                        <i class="bi bi-cup-hot-fill fs-2 mb-2 text-warning"></i>
                        <h4 class="mb-1" style="font-family: 'Playfair Display', serif;">Restaurante</h4>
                        <small class="text-light opacity-75">Panel de Control</small>
                    </div>
                    
                    <nav class="sidebar-nav">
                        <ul class="nav flex-column">
                            <li class="nav-item">
                                <a class="nav-link active" href="#dashboard">
                                    <i class="bi bi-speedometer2 me-2"></i> Dashboard
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="#reservas">
                                    <i class="bi bi-calendar-check me-2"></i> Reservas
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="#mesas">
                                    <i class="bi bi-table me-2"></i> Gestión de Mesas
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="#menu">
                                    <i class="bi bi-book me-2"></i> Gestión de Menú
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="#clientes">
                                    <i class="bi bi-people me-2"></i> Clientes
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="#reportes">
                                    <i class="bi bi-graph-up me-2"></i> Reportes
                                </a>
                            </li>
                            <li class="nav-item mt-3">
                                <a class="nav-link" href="#" onclick="logout()">
                                    <i class="bi bi-box-arrow-right me-2"></i> Cerrar Sesión
                                </a>
                            </li>
                        </ul>
                    </nav>
                </div>
            </div>
            
            <!-- CONTENIDO PRINCIPAL -->
            <div class="col-md-9 col-lg-10 p-0">
                <!-- HEADER -->
                <div class="dashboard-header d-flex justify-content-between align-items-center">
                    <div>
                        <h4 class="mb-1">
                            <i class="bi bi-person-circle me-2 text-warning"></i>
                            Bienvenido, <?php echo htmlspecialchars($admin_nombre . ' ' . $admin_apellido); ?>
                        </h4>
                        <small class="text-muted">
                            <i class="bi bi-calendar me-1"></i>
                            <?php echo date('d/m/Y H:i:s'); ?>
                            <span class="mx-2">|</span>
                            <i class="bi bi-envelope me-1"></i>
                            <?php echo htmlspecialchars($admin_email); ?>
                        </small>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge bg-success">
                            <i class="bi bi-person-badge me-1"></i>
                            Administrador
                        </span>
                        <button onclick="logout()" class="btn btn-sm btn-outline-danger" title="Cerrar Sesión">
                            <i class="bi bi-box-arrow-right me-1"></i>
                            Cerrar Sesión
                        </button>
                    </div>
                </div>
                
                <!-- CONTENIDO DEL DASHBOARD -->
                <div class="dashboard-content p-4">
                    <!-- INDICADOR DE ESTADO Y CONTROLES -->
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <div class="d-flex align-items-center">
                                <div id="indicador-conexion" class="me-3">🟢 Conectado</div>
                                <button id="btn-actualizar-manual" class="btn btn-sm btn-outline-primary me-2">
                                    <i class="bi bi-arrow-clockwise"></i> Actualizar
                                </button>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="toggle-auto-update" checked>
                                    <label class="form-check-label" for="toggle-auto-update">
                                        Auto-actualizar
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 text-end">
                            <small id="ultima-actualizacion" class="text-muted">
                                Última actualización: --:--:--
                            </small>
                        </div>
                    </div>

                    <!-- ESTADÍSTICAS PRINCIPALES CON APIS DINÁMICAS -->
                    <div class="row mb-4">
                        <div class="col-md-3 mb-3">
                            <div class="stats-card primary">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <div id="total-mesas" class="stats-number text-primary">--</div>
                                        <div class="stats-label">Total Mesas</div>
                                        <div class="stats-trend">
                                            <small class="text-muted">Sistema de reservas</small>
                                        </div>
                                    </div>
                                    <i class="bi bi-table fs-2 text-primary opacity-75"></i>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-3 mb-3">
                            <div class="stats-card success">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <div id="mesas-disponibles" class="stats-number text-success">--</div>
                                        <div class="stats-label">Mesas Disponibles</div>
                                        <div class="stats-trend">
                                            <small id="mesas-disponibilidad" class="text-muted">Cargando...</small>
                                        </div>
                                    </div>
                                    <i class="bi bi-check-circle fs-2 text-success opacity-75"></i>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-3 mb-3">
                            <div class="stats-card warning">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <div id="reservas-hoy" class="stats-number text-warning">--</div>
                                        <div class="stats-label">Reservas Hoy</div>
                                        <div class="stats-trend">
                                            <small class="text-muted">Actualizándose automáticamente</small>
                                        </div>
                                    </div>
                                    <i class="bi bi-calendar-event fs-2 text-warning opacity-75"></i>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-3 mb-3">
                            <div class="stats-card info">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <div id="clientes-registrados" class="stats-number text-info">--</div>
                                        <div class="stats-label">Clientes Registrados</div>
                                        <div class="stats-trend">
                                            <small class="text-muted">Base de datos completa</small>
                                        </div>
                                    </div>
                                    <i class="bi bi-people fs-2 text-info opacity-75"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- BARRA DE OCUPACIÓN EN TIEMPO REAL -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <div class="card border-0 shadow-sm">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <h6 class="mb-0">
                                            <i class="bi bi-speedometer2 me-2"></i>
                                            Ocupación del Restaurante
                                        </h6>
                                        <span id="porcentaje-ocupacion" class="badge bg-gradient text-dark" style="background: var(--gradient-gold) !important;">--%</span>
                                    </div>
                                    <div class="progress" style="height: 20px; background: rgba(212, 175, 55, 0.1);">
                                        <div id="barra-ocupacion" class="progress-bar" role="progressbar" style="width: 0%; background: linear-gradient(135deg, #d4af37 0%, #ffd700 100%);" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                                    </div>
                                    <div class="d-flex justify-content-between mt-2">
                                        <small class="text-muted">Disponibles</small>
                                        <small class="text-muted">Ocupadas</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- LAYOUT VISUAL DEL RESTAURANTE -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <div class="card border-0 shadow-sm">
                                <div class="card-header d-flex justify-content-between align-items-center" style="background: linear-gradient(135deg, rgba(45, 27, 18, 0.1) 0%, rgba(212, 175, 55, 0.1) 100%); border-bottom: 2px solid rgba(212, 175, 55, 0.2);">
                                    <h5 class="mb-0" style="color: var(--primary-color);">
                                        <i class="bi bi-diagram-3 me-2" style="color: var(--gold-color);"></i>
                                        Distribución Visual del Restaurante
                                    </h5>
                                    <button class="btn btn-sm" style="background: var(--gradient-gold); color: var(--primary-color); border: none;" onclick="restaurantLayout.refresh()">
                                        <i class="bi bi-arrow-clockwise me-1"></i> Actualizar
                                    </button>
                                </div>
                                <div class="card-body p-2">
                                    <div id="restaurant-layout-container"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- SECCIONES DINÁMICAS CON APIs -->
                    <div class="row">
                        <!-- ESTADO DE MESAS EN TIEMPO REAL -->
                        <div class="col-md-8 mb-4">
                            <div class="card border-0 shadow-sm">
                                <div class="card-header" style="background: linear-gradient(135deg, rgba(45, 27, 18, 0.1) 0%, rgba(212, 175, 55, 0.1) 100%); border-bottom: 2px solid rgba(212, 175, 55, 0.2);">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <h5 class="mb-0" style="color: var(--primary-color);">
                                            <i class="bi bi-grid-3x3-gap me-2" style="color: var(--gold-color);"></i>
                                            Estado de Mesas en Tiempo Real
                                        </h5>
                                        <div class="d-flex align-items-center">
                                            <div class="me-3">
                                                <span class="badge" style="background: #28a745; color: white;">●</span>
                                                <small>Disponible</small>
                                            </div>
                                            <div>
                                                <span class="badge" style="background: #dc3545; color: white;">●</span>
                                                <small>Ocupada</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-body p-3">
                                    <div id="mesas-grid" class="row">
                                        <!-- Las mesas se cargan dinámicamente aquí -->
                                        <div class="col-12 text-center py-4">
                                            <div class="spinner-border" style="color: var(--gold-color);" role="status">
                                                <span class="visually-hidden">Cargando mesas...</span>
                                            </div>
                                            <div class="mt-2">
                                                <small class="text-muted">Cargando estado de mesas...</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- RESERVAS PRÓXIMAS EN TIEMPO REAL -->
                        <div class="col-md-4 mb-4">
                            <div class="card border-0 shadow-sm">
                                <div class="card-header" style="background: linear-gradient(135deg, rgba(45, 27, 18, 0.1) 0%, rgba(212, 175, 55, 0.1) 100%); border-bottom: 2px solid rgba(212, 175, 55, 0.2);">
                                    <h5 class="mb-0" style="color: var(--primary-color);">
                                        <i class="bi bi-clock-history me-2" style="color: var(--gold-color);"></i>
                                        Reservas Próximas
                                    </h5>
                                </div>
                                <div class="card-body p-3">
                                    <div id="reservas-recientes">
                                        <!-- Las reservas se cargan dinámicamente aquí -->
                                        <div class="text-center py-4">
                                            <div class="spinner-border" style="color: var(--gold-color);" role="status">
                                                <span class="visually-hidden">Cargando reservas...</span>
                                            </div>
                                            <div class="mt-2">
                                                <small class="text-muted">Cargando reservas próximas...</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- NOTIFICACIONES DINÁMICAS -->
                    <div id="notificaciones" class="position-fixed" style="top: 20px; right: 20px; z-index: 1050;">
                        <!-- Las notificaciones aparecen aquí -->
                                                                                            <div class="text-center py-2">
                                                <small class="text-muted">Reservas cargadas via API</small>
                                            </div>
                                        </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- ACCIONES RÁPIDAS -->
                    <div class="row">
                        <div class="col-12">
                            <div class="card border-0 shadow-sm">
                                <div class="card-header bg-light">
                                    <h5 class="mb-0">
                                        <i class="bi bi-lightning me-2"></i>
                                        Acciones Rápidas
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-3 mb-3">
                                            <button class="btn btn-outline-primary w-100 py-3" onclick="mostrarGestionReservas()">
                                                <i class="bi bi-calendar-check fs-2 d-block mb-2"></i>
                                                Gestionar Reservas
                                            </button>
                                        </div>
                                        <div class="col-md-3 mb-3">
                                            <button class="btn btn-outline-success w-100 py-3" onclick="mostrarGestionMesas()">
                                                <i class="bi bi-table fs-2 d-block mb-2"></i>
                                                Gestionar Mesas
                                            </button>
                                        </div>
                                        <div class="col-md-3 mb-3">
                                            <button class="btn btn-outline-warning w-100 py-3" onclick="mostrarSubidaExcel()">
                                                <i class="bi bi-file-earmark-excel fs-2 d-block mb-2"></i>
                                                Cargar Menú Excel
                                            </button>
                                        </div>
                                        <div class="col-md-3 mb-3">
                                            <button class="btn btn-outline-info w-100 py-3">
                                                <i class="bi bi-graph-up fs-2 d-block mb-2"></i>
                                                Ver Reportes
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- MODAL: GESTIÓN DE MESAS -->
                    <div class="modal fade" id="modalGestionMesas" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog modal-xl">
                            <div class="modal-content">
                                <div class="modal-header" style="background: var(--gradient-primary);">
                                    <h5 class="modal-title text-white">
                                        <i class="bi bi-table me-2"></i>
                                        Gestión de Mesas
                                    </h5>
                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="mb-3 d-flex gap-2">
                                        <button class="btn btn-success" onclick="gestionMesas.mostrarModal('agregar')">
                                            <i class="fas fa-plus me-2"></i>
                                            Crear Nueva Mesa
                                        </button>
                                        <button class="btn btn-info" onclick="gestionMesas.cargarMesas()">
                                            <i class="fas fa-sync-alt me-2"></i>
                                            Actualizar Lista
                                        </button>
                                    </div>
                                    
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead class="table-dark">
                                                <tr>
                                                    <th>Número</th>
                                                    <th>Ubicación</th>
                                                    <th>Capacidad</th>
                                                    <th>Estado</th>
                                                    <th>Descripción</th>
                                                    <th>Acciones</th>
                                                </tr>
                                            </thead>
                                            <tbody id="tablaMesas">
                                                <tr>
                                                    <td colspan="6" class="text-center">
                                                        <div class="spinner-border" role="status">
                                                            <span class="visually-hidden">Cargando...</span>
                                                        </div>
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- MODAL: GESTIÓN DE RESERVAS -->
                    <div class="modal fade" id="modalGestionReservas" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog modal-xl">
                            <div class="modal-content">
                                <div class="modal-header" style="background: var(--gradient-primary);">
                                    <h5 class="modal-title text-white">
                                        <i class="bi bi-calendar-check me-2"></i>
                                        Gestión de Reservas
                                    </h5>
                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="mb-3 d-flex gap-2">
                                        <button class="btn btn-primary" onclick="gestionReservas.mostrarModalReserva('crear')">
                                            <i class="fas fa-plus me-2"></i>
                                            Crear Nueva Reserva
                                        </button>
                                        <button class="btn btn-info" onclick="gestionReservas.cargarReservas()">
                                            <i class="fas fa-sync-alt me-2"></i>
                                            Actualizar Lista
                                        </button>
                                        <select class="form-select w-auto" id="filtroEstadoReserva" onchange="gestionReservas.cargarReservas()">
                                            <option value="">Todos los estados</option>
                                            <option value="pendiente">Pendientes</option>
                                            <option value="confirmada">Confirmadas</option>
                                            <option value="en_curso">En Curso</option>
                                            <option value="finalizada">Finalizadas</option>
                                            <option value="cancelada">Canceladas</option>
                                        </select>
                                    </div>
                                    
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead class="table-dark">
                                                <tr>
                                                    <th>ID</th>
                                                    <th>Cliente</th>
                                                    <th>Mesa</th>
                                                    <th>Fecha</th>
                                                    <th>Hora</th>
                                                    <th>Personas</th>
                                                    <th>Estado</th>
                                                    <th>Acciones</th>
                                                </tr>
                                            </thead>
                                            <tbody id="tablaReservas">
                                                <tr>
                                                    <td colspan="8" class="text-center">
                                                        <div class="spinner-border" role="status">
                                                            <span class="visually-hidden">Cargando...</span>
                                                        </div>
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- MODAL: SUBIR EXCEL MENÚ -->
                    <div class="modal fade" id="modalSubirExcel" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <div class="modal-header" style="background: var(--gradient-gold);">
                                    <h5 class="modal-title text-white">
                                        <i class="bi bi-file-earmark-excel me-2"></i>
                                        Cargar Menú desde Excel
                                    </h5>
                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="alert alert-info">
                                        <i class="bi bi-info-circle me-2"></i>
                                        <strong>Formato del Excel:</strong>
                                        <ul class="mb-0 mt-2">
                                            <li>Debe tener dos hojas: <code>categorias</code> y <code>platos</code></li>
                                            <li><strong>Hoja categorias:</strong> nombre, descripcion, orden_menu, activo</li>
                                            <li><strong>Hoja platos:</strong> categoria, nombre, descripcion, precio, stock_disponible, tiempo_preparacion, imagen_url, ingredientes, es_especial, activo</li>
                                        </ul>
                                    </div>
                                    
                                    <form id="formSubirExcel" enctype="multipart/form-data">
                                        <div class="mb-3">
                                            <label class="form-label">Seleccionar archivo Excel *</label>
                                            <input type="file" class="form-control" id="archivoExcel" 
                                                   accept=".xlsx,.xls" required>
                                            <small class="text-muted">Formatos permitidos: .xlsx, .xls (máx. 10MB)</small>
                                        </div>
                                        
                                        <div id="progresoSubida" class="d-none">
                                            <div class="progress mb-2">
                                                <div class="progress-bar progress-bar-striped progress-bar-animated" 
                                                     role="progressbar" style="width: 100%"></div>
                                            </div>
                                            <small class="text-muted">
                                                <i class="bi bi-hourglass-split"></i> Procesando archivo...
                                            </small>
                                        </div>
                                    </form>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                    <button type="button" class="btn btn-warning" onclick="subirExcelMenu()">
                                        <i class="bi bi-upload me-2"></i>
                                        Subir y Procesar
                                    </button>
                                </div>
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
        // Función para alternar visibilidad de contraseña
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleIcon = document.getElementById('toggleIcon');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.classList.remove('bi-eye');
                toggleIcon.classList.add('bi-eye-slash');
            } else {
                passwordInput.type = 'password';
                toggleIcon.classList.remove('bi-eye-slash');
                toggleIcon.classList.add('bi-eye');
            }
        }

        // Dashboard directo

        // Función de logout mejorada con prevención de caché
        function logout() {
            Swal.fire({
                title: '¿Cerrar sesión?',
                text: '¿Está seguro que desea salir del panel de administración?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Sí, cerrar sesión',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Mostrar loading
                    Swal.fire({
                        title: 'Cerrando sesión...',
                        html: '<div class="spinner-border text-warning" role="status"><span class="visually-hidden">Cargando...</span></div>',
                        showConfirmButton: false,
                        allowOutsideClick: false
                    });
                    
                    // Limpiar cualquier dato local
                    sessionStorage.clear();
                    localStorage.removeItem('adminSession');
                    
                    // Redirigir al logout
                    setTimeout(() => {
                        window.location.replace('app/logout.php');
                    }, 500);
                }
            });
        }
        
        // Prevenir navegación: si el usuario presiona atrás, cerrar sesión automáticamente
        let sessionActive = true;
        
        // Detectar cuando el usuario intenta salir de la página
        window.addEventListener('beforeunload', function(e) {
            // Marcar que está intentando salir
            sessionActive = false;
        });
        
        // Detectar navegación con botones del navegador
        window.addEventListener('popstate', function(event) {
            // Si el usuario presiona atrás, cerrar sesión inmediatamente
            Swal.fire({
                title: 'Cerrando sesión...',
                html: '<div class="spinner-border text-warning" role="status"><span class="visually-hidden">Cargando...</span></div>',
                showConfirmButton: false,
                allowOutsideClick: false,
                timer: 1000
            });
            
            // Cerrar sesión
            sessionStorage.clear();
            localStorage.clear();
            
            setTimeout(() => {
                window.location.replace('app/logout.php');
            }, 1000);
        });
        
        // Prevenir navegación con botones atrás/adelante después de logout
        window.addEventListener('pageshow', function(event) {
            // Si la página se carga desde caché (usuario presionó atrás)
            if (event.persisted) {
                // Verificar si hay sesión activa
                fetch('app/verificar_sesion_admin.php')
                    .then(response => response.json())
                    .then(data => {
                        if (!data.activa) {
                            // No hay sesión activa, redirigir
                            window.location.replace('index.html');
                        }
                    })
                    .catch(() => {
                        // En caso de error, redirigir por seguridad
                        window.location.replace('index.html');
                    });
            }
        });

        // Deshabilitar botón atrás del navegador
        history.pushState(null, null, location.href);
        window.onpopstate = function () {
            // Si el usuario presiona atrás, cerrar sesión
            Swal.fire({
                title: 'Saliendo del panel...',
                html: '<div class="spinner-border text-warning" role="status"><span class="visually-hidden">Cargando...</span></div>',
                showConfirmButton: false,
                allowOutsideClick: false,
                timer: 800
            });
            
            sessionStorage.clear();
            localStorage.clear();
            
            setTimeout(() => {
                window.location.replace('app/logout.php');
            }, 800);
        };


        // Actualizar reloj en tiempo real
        function updateClock() {
            const now = new Date();
            const timeString = now.toLocaleString('es-ES');
            const clockElement = document.querySelector('.dashboard-header small');
            if (clockElement) {
                clockElement.innerHTML = '<i class="bi bi-calendar me-1"></i>' + timeString;
            }
        }

        // Actualizar cada segundo si está en dashboard
        setInterval(updateClock, 1000);
    </script>

    <!-- DASHBOARD DINÁMICO CON APIs -->
    <script src="public/js/dashboard-api.js"></script>
    
    <script>
        // Configuración adicional del dashboard
        document.addEventListener('DOMContentLoaded', function() {
            console.log('🏆 Dashboard Dinámico del Restaurante Elegante iniciado');
            
            // Personalizar configuración si es necesario
            if (window.dashboard) {
                dashboard.configuracion.debug = false; // Cambiar a true para debug
                
                // Configurar intervalos personalizados (opcional)
                // dashboard.configuracion.intervalos.estadisticas = 20000; // 20 segundos
                // dashboard.configuracion.intervalos.mesas = 15000;        // 15 segundos
                // dashboard.configuracion.intervalos.reservas = 25000;     // 25 segundos
                
                console.log('⚙️ Dashboard configurado correctamente');
            }
        });
        
        // Función global para refrescar todo manualmente desde botones
        function actualizarDashboardCompleto() {
            if (window.dashboard) {
                dashboard.cargarDatosIniciales();
                dashboard.mostrarNotificacion('Dashboard actualizado manualmente', 'info');
            }
        }
        
        // Integración con SweetAlert para notificaciones importantes
        function mostrarAlertaReservaUrgente(reserva) {
            Swal.fire({
                title: '⚠️ Reserva Urgente!',
                html: `
                    <div style="text-align: left;">
                        <p><strong>Cliente:</strong> ${reserva.cliente}</p>
                        <p><strong>Mesa:</strong> ${reserva.mesa}</p>
                        <p><strong>Hora:</strong> ${reserva.hora}</p>
                        <p><strong>Personas:</strong> ${reserva.personas}</p>
                    </div>
                `,
                icon: 'warning',
                confirmButtonText: 'Entendido',
                confirmButtonColor: '#d4af37',
                timer: 10000,
                timerProgressBar: true
            });
        }
        
        // Sistema de notificaciones push (para futuras mejoras)
        function configurarNotificacionesPush() {
            if ('Notification' in window && 'serviceWorker' in navigator) {
                // Pedir permisos para notificaciones del navegador
                Notification.requestPermission().then(function(permission) {
                    if (permission === 'granted') {
                        console.log('✅ Notificaciones push habilitadas');
                    }
                });
            }
        }
        
        // Configurar notificaciones push al cargar
        configurarNotificacionesPush();
        
        // Manejo de errores de conexión
        window.addEventListener('online', function() {
            if (window.dashboard) {
                dashboard.mostrarEstadoConexion('conectado');
                dashboard.mostrarNotificacion('Conexión restaurada', 'success');
                dashboard.cargarDatosIniciales();
            }
        });
        
        window.addEventListener('offline', function() {
            if (window.dashboard) {
                dashboard.mostrarEstadoConexion('error');
                dashboard.mostrarNotificacion('Sin conexión a internet', 'error');
            }
        });

        // ============================================
        // SEGURIDAD: Prevenir navegación con botones Atrás/Adelante
        // ============================================
        
        (function() {
            // Verificar sesión periódicamente
            function verificarSesion() {
                fetch('app/verificar_sesion_admin.php', {
                    method: 'GET',
                    cache: 'no-cache'
                })
                .then(response => response.json())
                .then(data => {
                    if (!data.activa) {
                        // Si la sesión no está activa, redirigir inmediatamente
                        window.location.replace('index.html');
                    }
                })
                .catch(() => {
                    // Si hay error, asumir sesión inválida
                    window.location.replace('index.html');
                });
            }

            // Verificar sesión cada 30 segundos
            setInterval(verificarSesion, 30000);

            // Prevenir navegación con botón Atrás
            history.pushState(null, null, location.href);
            
            window.addEventListener('popstate', function() {
                // Al detectar navegación hacia atrás, cerrar sesión
                fetch('app/logout.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' }
                }).finally(() => {
                    window.location.replace('index.html');
                });
            });

            // Detectar cuando la página se carga desde caché
            window.addEventListener('pageshow', function(event) {
                if (event.persisted) {
                    // Página cargada desde caché, verificar sesión
                    verificarSesion();
                }
            });

            // Prevenir caché al salir de la página
            window.addEventListener('beforeunload', function() {
                // Marcar que se está saliendo
                sessionStorage.setItem('admin_exiting', 'true');
            });

            // Al cargar, verificar si se estaba saliendo
            if (sessionStorage.getItem('admin_exiting') === 'true') {
                sessionStorage.removeItem('admin_exiting');
                verificarSesion();
            }
        })();
    </script>
    
    <!-- Restaurant Layout JavaScript -->
    <script src="public/js/restaurant-layout-new.js?v=<?php echo time(); ?>"></script>
    
    <!-- Gestión de Mesas JavaScript -->
    <script src="public/js/gestion-mesas.js?v=<?php echo time(); ?>"></script>
    
    <!-- Gestión de Reservas JavaScript -->
    <script src="public/js/gestion-reservas.js?v=<?php echo time(); ?>"></script>
    
    <script>
        // Esperar a que todos los scripts estén cargados
        document.addEventListener('DOMContentLoaded', function() {
            console.log('🍽️ Archivos de restaurant layout cargados');
        });
        
        // FUNCIONES GLOBALES - Definidas en window
        window.mostrarGestionMesas = function() {
            if (typeof gestionMesas === 'undefined') {
                console.error('gestionMesas no está definido');
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Error al cargar el módulo de gestión de mesas'
                });
                return;
            }
            const modal = new bootstrap.Modal(document.getElementById('modalGestionMesas'));
            modal.show();
            gestionMesas.abrir();
        };
        
        window.mostrarGestionReservas = function() {
            if (typeof gestionReservas === 'undefined') {
                console.error('gestionReservas no está definido');
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Error al cargar el módulo de gestión de reservas'
                });
                return;
            }
            const modal = new bootstrap.Modal(document.getElementById('modalGestionReservas'));
            modal.show();
            gestionReservas.abrir();
        };
        
        window.mostrarSubidaExcel = function() {
            const modal = new bootstrap.Modal(document.getElementById('modalSubirExcel'));
            modal.show();
        };
        
        // Función para subir y procesar Excel
        window.subirExcelMenu = async function() {
            const fileInput = document.getElementById('archivoExcel');
            const file = fileInput.files[0];
            
            if (!file) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Archivo requerido',
                    text: 'Por favor selecciona un archivo Excel'
                });
                return;
            }
            
            // Validar extensión .xlsx
            const fileExt = file.name.split('.').pop().toLowerCase();
            if (fileExt !== 'xlsx' && fileExt !== 'xls') {
                Swal.fire({
                    icon: 'error',
                    title: 'Formato inválido',
                    text: 'Solo se permiten archivos .xlsx o .xls'
                });
                return;
            }
            
            // Validar tamaño (max 10MB)
            if (file.size > 10 * 1024 * 1024) {
                Swal.fire({
                    icon: 'error',
                    title: 'Archivo muy grande',
                    text: 'El archivo no debe superar los 10MB'
                });
                return;
            }
            
            // Mostrar progreso
            document.getElementById('progresoSubida').classList.remove('d-none');
            
            try {
                const formData = new FormData();
                formData.append('excel_file', file);
                
                const response = await fetch('app/api/subir_excel.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                // Ocultar progreso
                document.getElementById('progresoSubida').classList.add('d-none');
                
                if (result.ok) {
                    Swal.fire({
                        icon: 'success',
                        title: '¡Éxito!',
                        html: `
                            <p>${result.message}</p>
                            ${result.stdout ? `<div class="text-start mt-3">
                                <strong>Detalles:</strong>
                                <pre class="bg-light p-2 rounded" style="max-height: 300px; overflow-y: auto; font-size: 11px;">${result.stdout}</pre>
                            </div>` : ''}
                        `,
                        confirmButtonText: 'Entendido',
                        width: '600px'
                    }).then(() => {
                        // Cerrar modal y limpiar
                        const modal = bootstrap.Modal.getInstance(document.getElementById('modalSubirExcel'));
                        modal.hide();
                        fileInput.value = '';
                    });
                } else {
                    // Mostrar error con stderr
                    Swal.fire({
                        icon: 'error',
                        title: 'Error al procesar Excel',
                        html: `
                            <p>${result.message}</p>
                            ${result.stderr ? `<div class="text-start mt-3">
                                <strong>Detalles del error:</strong>
                                <pre class="bg-danger text-white p-2 rounded" style="max-height: 300px; overflow-y: auto; font-size: 11px;">${result.stderr}</pre>
                            </div>` : ''}
                        `,
                        confirmButtonText: 'Entendido',
                        width: '600px'
                    });
                }
            } catch (error) {
                document.getElementById('progresoSubida').classList.add('d-none');
                console.error('Error subiendo Excel:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: error.message || 'No se pudo procesar el archivo Excel'
                });
            }
        };
    </script>
</body>
</html>