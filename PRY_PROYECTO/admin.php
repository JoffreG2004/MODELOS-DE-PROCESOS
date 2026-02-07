<?php
session_start();
require_once 'conexion/db.php';

// Verificar que el administrador esté autenticado
if (!isset($_SESSION['admin_authenticated']) || $_SESSION['admin_authenticated'] !== true) {
    // Si no está autenticado, redirigir al index
    header('Location: index.html');
    exit;
}

// Actualizar automáticamente los estados de las reservas
try {
    $pdo->exec("CALL activar_reservas_programadas()");
} catch (PDOException $e) {
    // Log error silenciosamente
    error_log("Error actualizando estados de reservas: " . $e->getMessage());
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
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>Dashboard - Restaurante Elegante</title>

    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="public/bootstrap/css/bootstrap.min.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="public/css/style.css?v=<?php echo time(); ?>">

    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;500;600;700&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">

    <!-- SweetAlert2 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.js"></script>
    
    <!-- Restaurant Layout CSS -->
    <link rel="stylesheet" href="public/css/restaurant-layout-new.css?v=<?php echo time(); ?>">

    <style>
        :root {
            --dark-bg: #0a0e27;
            --dark-card: #151932;
            --dark-hover: #1e2341;
            --accent-gold: #ffd700;
            --accent-cyan: #00d4ff;
            --accent-purple: #8b5cf6;
            --text-primary: #ffffff;
            --text-secondary: #a0aec0;
            --success-glow: #10b981;
            --danger-glow: #ef4444;
            --warning-glow: #f59e0b;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #0a0e27 0%, #1a1f3a 100%);
            min-height: 100vh;
            color: var(--text-primary);
        }

        .dashboard-sidebar {
            background: linear-gradient(180deg, #0f1629 0%, #1a1f3a 100%);
            min-height: 100vh;
            color: var(--text-primary);
            border-right: 1px solid rgba(255, 255, 255, 0.05);
            box-shadow: 4px 0 20px rgba(0, 0, 0, 0.3);
        }

        .sidebar-header {
            padding: 2rem;
            text-align: center;
            border-bottom: 1px solid rgba(255, 215, 0, 0.2);
            background: rgba(255, 215, 0, 0.05);
        }

        .sidebar-header i {
            font-size: 3rem;
            color: var(--accent-gold);
            filter: drop-shadow(0 0 10px rgba(255, 215, 0, 0.5));
        }

        .sidebar-nav .nav-link {
            color: var(--text-secondary);
            padding: 1rem 2rem;
            border-radius: 0;
            transition: all 0.3s ease;
            border-left: 3px solid transparent;
            margin: 0.2rem 0;
        }

        .sidebar-nav .nav-link:hover {
            color: var(--accent-gold);
            background: rgba(255, 215, 0, 0.1);
            border-left-color: var(--accent-gold);
            transform: translateX(5px);
        }

        .sidebar-nav .nav-link.active {
            color: var(--accent-gold);
            background: rgba(255, 215, 0, 0.15);
            border-left-color: var(--accent-gold);
        }

        .dashboard-content {
            background: var(--dark-bg);
            min-height: 100vh;
            padding: 2rem !important;
        }

        .dashboard-header {
            background: var(--dark-card);
            padding: 1.5rem 2rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.4);
            border-bottom: 1px solid rgba(255, 215, 0, 0.2);
            border-radius: 15px;
            margin-bottom: 2rem;
        }

        .stats-card {
            background: var(--dark-card);
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
            border: 1px solid rgba(255, 255, 255, 0.05);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .stats-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 3px;
            background: linear-gradient(90deg, var(--accent-gold), var(--accent-cyan));
        }

        .stats-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 12px 48px rgba(255, 215, 0, 0.3);
            border-color: var(--accent-gold);
        }

        .stats-card.primary::before { background: linear-gradient(90deg, #3b82f6, #60a5fa); }
        .stats-card.success::before { background: linear-gradient(90deg, #10b981, #34d399); }
        .stats-card.warning::before { background: linear-gradient(90deg, #f59e0b, #fbbf24); }
        .stats-card.info::before { background: linear-gradient(90deg, #06b6d4, #22d3ee); }
        .stats-card.gold::before { background: linear-gradient(90deg, #ffd700, #ffed4e); }
        .stats-card.purple::before { background: linear-gradient(90deg, #8b5cf6, #a78bfa); }

        .stats-icon {
            font-size: 3rem;
            opacity: 0.2;
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
        }

        .stats-number {
            font-size: 3rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            background: linear-gradient(135deg, var(--accent-gold), var(--accent-cyan));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .stats-label {
            color: var(--text-secondary);
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 500;
        }

        .card {
            background: var(--dark-card) !important;
            border: 1px solid rgba(255, 255, 255, 0.05) !important;
            border-radius: 20px !important;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4) !important;
        }

        /* Animación para el ícono de campana */
        @keyframes swing {
            0%, 100% { transform: rotate(0deg); }
            10%, 30%, 50%, 70%, 90% { transform: rotate(15deg); }
            20%, 40%, 60%, 80% { transform: rotate(-15deg); }
        }

        .animate__animated {
            animation-duration: 1s;
            animation-fill-mode: both;
        }

        .animate__swing {
            animation-name: swing;
            transform-origin: top center;
        }

        .animate__infinite {
            animation-iteration-count: infinite;
            animation-duration: 2s;
        }

        .card-header {
            background: rgba(255, 215, 0, 0.05) !important;
            border-bottom: 1px solid rgba(255, 215, 0, 0.2) !important;
            border-radius: 20px 20px 0 0 !important;
            padding: 1.5rem !important;
        }

        .card-header h5 {
            color: var(--text-primary) !important;
            font-weight: 600;
        }

        /* Estilos para Reservas Activas */
        .reserva-card {
            border-radius: 15px;
            transition: all 0.3s ease;
            border-left: 5px solid transparent;
        }

        .reserva-card.preparando {
            border-left-color: #f59e0b;
            background: linear-gradient(135deg, rgba(245, 158, 11, 0.05), transparent);
        }

        .reserva-card.en_curso {
            border-left-color: #10b981;
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.05), transparent);
        }

        .reserva-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.3);
        }

        .estado-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-weight: 600;
            font-size: 0.85rem;
        }

        .estado-badge.preparando {
            background: linear-gradient(135deg, #f59e0b, #fbbf24);
            color: #000;
        }

        .estado-badge.en_curso {
            background: linear-gradient(135deg, #10b981, #34d399);
            color: #000;
        }

        .tiempo-transcurrido {
            font-size: 0.9rem;
            font-weight: 500;
        }

        .tiempo-transcurrido.critico {
            color: #ef4444;
            font-weight: 700;
            animation: pulso 2s infinite;
        }

        @keyframes pulso {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }

        .indicador-llegada {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 0.5rem;
        }

        .indicador-llegada.llegado {
            background: #10b981;
            box-shadow: 0 0 10px #10b981;
        }

        .indicador-llegada.esperando {
            background: #f59e0b;
            box-shadow: 0 0 10px #f59e0b;
        }

        .indicador-llegada.no-llego {
            background: #ef4444;
            box-shadow: 0 0 10px #ef4444;
        }

        .btn-accion {
            border-radius: 10px;
            font-weight: 600;
            padding: 0.5rem 1.5rem;
            transition: all 0.3s ease;
        }

        .btn-accion:hover {
            transform: scale(1.05);
        }

        .filter-section {
            background: rgba(255, 255, 255, 0.03);
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1.5rem;
        }

        .bg-gradient {
            background: linear-gradient(135deg, #8b5cf6, #a78bfa) !important;
            color: white !important;
        }

        .btn-outline-primary,
        .btn-outline-success,
        .btn-outline-warning,
        .btn-outline-secondary {
            border: 2px solid !important;
            transition: all 0.3s ease !important;
            position: relative;
            overflow: hidden;
            font-weight: 600;
        }

        .btn-outline-primary {
            border-color: #3b82f6 !important;
            color: #60a5fa !important;
        }

        .btn-outline-primary:hover {
            background: linear-gradient(135deg, #3b82f6, #60a5fa) !important;
            color: white !important;
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(59, 130, 246, 0.4) !important;
        }

        .btn-outline-success {
            border-color: #10b981 !important;
            color: #34d399 !important;
        }

        .btn-outline-success:hover {
            background: linear-gradient(135deg, #10b981, #34d399) !important;
            color: white !important;
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(16, 185, 129, 0.4) !important;
        }

        .btn-outline-warning {
            border-color: #f59e0b !important;
            color: #fbbf24 !important;
        }

        .btn-outline-warning:hover {
            background: linear-gradient(135deg, #f59e0b, #fbbf24) !important;
            color: white !important;
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(245, 158, 11, 0.4) !important;
        }

        .btn-outline-secondary {
            border-color: #8b5cf6 !important;
            color: #a78bfa !important;
        }

        .btn-outline-secondary:hover {
            background: linear-gradient(135deg, #8b5cf6, #a78bfa) !important;
            color: white !important;
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(139, 92, 246, 0.4) !important;
        }

        .badge {
            font-weight: 600;
            padding: 0.5rem 1rem;
            border-radius: 10px;
        }

        .badge.bg-success {
            background: linear-gradient(135deg, #10b981, #34d399) !important;
            box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3);
        }

        #indicador-conexion {
            background: var(--dark-card);
            padding: 0.5rem 1rem;
            border-radius: 10px;
            border: 1px solid var(--success-glow);
            color: var(--success-glow);
            font-weight: 600;
        }

        .modal-content {
            background: var(--dark-card) !important;
            border: 1px solid rgba(255, 215, 0, 0.2) !important;
            color: var(--text-primary) !important;
        }

        .modal-header {
            border-bottom: 1px solid rgba(255, 215, 0, 0.2) !important;
            background: rgba(0, 0, 0, 0.3) !important;
        }
        
        .modal-header .modal-title {
            color: var(--accent-gold) !important;
        }
        
        .modal-header .btn-close {
            filter: invert(1) !important;
        }

        .modal-body {
            color: var(--text-primary) !important;
            background: var(--dark-card) !important;
        }
        
        .modal-body * {
            color: #ffffff !important;
        }
        
        .modal-body label {
            color: #ffffff !important;
            font-weight: 500;
        }
        
        .modal-body p,
        .modal-body span,
        .modal-body div,
        .modal-body small {
            color: #ffffff !important;
        }
        
        .modal-body .text-muted {
            color: rgba(255, 255, 255, 0.6) !important;
        }
        
        .modal-body .alert {
            background: rgba(255, 215, 0, 0.1) !important;
            border-color: rgba(255, 215, 0, 0.3) !important;
            color: #ffffff !important;
        }
        
        .modal-body .alert-info {
            background: rgba(0, 212, 255, 0.1) !important;
            border-color: rgba(0, 212, 255, 0.3) !important;
        }

        .modal-footer {
            border-top: 1px solid rgba(255, 215, 0, 0.2) !important;
            background: rgba(0, 0, 0, 0.2) !important;
        }

        .form-control,
        .form-select,
        input.form-control,
        select.form-select,
        textarea.form-control,
        .modal input[type="text"],
        .modal input[type="number"],
        .modal input[type="time"],
        .modal input[type="date"],
        .modal input[type="email"],
        .modal select,
        .modal textarea {
            background: #1a1d35 !important;
            border: 1px solid rgba(255, 215, 0, 0.3) !important;
            color: #00d4ff !important;
            font-weight: 500 !important;
            -webkit-text-fill-color: #00d4ff !important;
        }
        
        .form-control::placeholder {
            color: rgba(255, 255, 255, 0.5) !important;
            -webkit-text-fill-color: rgba(255, 255, 255, 0.5) !important;
        }
        
        .form-control:focus,
        .form-select:focus,
        input.form-control:focus,
        select.form-select:focus,
        textarea.form-control:focus {
            background: #1a1d35 !important;
            border-color: var(--accent-gold) !important;
            color: #00d4ff !important;
            -webkit-text-fill-color: #00d4ff !important;
            box-shadow: 0 0 0 0.25rem rgba(255, 215, 0, 0.2) !important;
        }
        
        .form-control option {
            background: #1a1d35 !important;
            color: #ffffff !important;
        }
        
        /* Estilos para tablas dentro de modales */
        .modal-body table {
            color: var(--text-primary) !important;
        }
        
        .modal-body table thead th {
            background: rgba(0, 0, 0, 0.5) !important;
            color: var(--accent-gold) !important;
            border-color: rgba(255, 215, 0, 0.2) !important;
        }
        
        .modal-body table tbody td {
            background: rgba(0, 0, 0, 0.2) !important;
            color: #ffffff !important;
            border-color: rgba(255, 255, 255, 0.1) !important;
        }
        
        .modal-body table tbody tr:hover td {
            background: rgba(255, 215, 0, 0.1) !important;
        }
        
        /* Asegurar que los badges se vean bien */
        .modal-body .badge {
            color: #ffffff !important;
        }

        .form-control:focus,
        .form-select:focus {
            background: rgba(255, 255, 255, 0.08) !important;
            border-color: var(--accent-gold) !important;
            box-shadow: 0 0 0 0.2rem rgba(255, 215, 0, 0.25) !important;
            color: var(--text-primary) !important;
        }

        .table {
            color: var(--text-primary) !important;
        }

        .table-dark {
            background: rgba(0, 0, 0, 0.3) !important;
        }

        .table-hover tbody tr:hover {
            background: rgba(255, 215, 0, 0.1) !important;
        }

        .alert-info {
            background: rgba(6, 182, 212, 0.1) !important;
            border-color: rgba(6, 182, 212, 0.3) !important;
            color: #22d3ee !important;
        }

        /* Animaciones */
        @keyframes pulse-glow {
            0%, 100% { box-shadow: 0 0 20px rgba(255, 215, 0, 0.4); }
            50% { box-shadow: 0 0 40px rgba(255, 215, 0, 0.6); }
        }

        .stats-card:hover {
            animation: pulse-glow 2s infinite;
        
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
                                <a class="nav-link active" href="javascript:void(0);" onclick="scrollToTop()">
                                    <i class="bi bi-speedometer2 me-2"></i> Dashboard
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="javascript:void(0);" onclick="scrollToSection('reservas-activas-section')">
                                    <i class="bi bi-clock-history me-2"></i> 🔴 Reservas Activas
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="javascript:void(0);" onclick="scrollToSection('reservas-section')">
                                    <i class="bi bi-calendar-check me-2"></i> Reservas
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="javascript:void(0);" onclick="scrollToSection('reservas-zonas-section')">
                                    <i class="bi bi-grid-3x3 me-2"></i> Reservas de Zonas
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="javascript:void(0);" onclick="scrollToSection('gestion-mesas-section')">
                                    <i class="bi bi-table me-2"></i> Gestión de Mesas
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="javascript:void(0);" onclick="scrollToSection('menu-section')">
                                    <i class="bi bi-book me-2"></i> Gestión de Menú
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="javascript:void(0);" onclick="scrollToSection('clientes-section')">
                                    <i class="bi bi-people me-2"></i> Clientes
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="javascript:void(0);" onclick="scrollToSection('configuracion-section')">
                                    <i class="bi bi-gear me-2"></i> Configuración
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="views/auditoria.php" target="_blank">
                                    <i class="bi bi-clipboard-data me-2"></i> Auditoría
                                </a>
                            </li>
                            <li class="nav-item mt-3">
                                <a class="nav-link" href="javascript:void(0);" onclick="logout()">
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
                    <div class="row mb-4 g-4">
                        <div class="col-md-6 col-lg-3">
                            <div class="stats-card primary">
                                <i class="bi bi-grid-3x3-gap stats-icon"></i>
                                <div class="stats-content">
                                    <div id="total-mesas" class="stats-number">--</div>
                                    <div class="stats-label">Total Mesas</div>
                                    <div class="mt-2">
                                        <small class="text-muted">
                                            <i class="bi bi-info-circle me-1"></i>Sistema de reservas
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6 col-lg-3">
                            <div class="stats-card success">
                                <i class="bi bi-check-circle-fill stats-icon"></i>
                                <div class="stats-content">
                                    <div id="mesas-disponibles" class="stats-number">--</div>
                                    <div class="stats-label">Disponibles</div>
                                    <div class="mt-2">
                                        <small class="text-muted">
                                            <i class="bi bi-clock me-1"></i><span id="info-disponibles">-- disponibles / -- ocupadas</span>
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6 col-lg-3">
                            <div class="stats-card warning">
                                <i class="bi bi-calendar-event stats-icon"></i>
                                <div class="stats-content">
                                    <div id="reservas-hoy" class="stats-number">--</div>
                                    <div class="stats-label">Reservas Hoy</div>
                                    <div class="mt-2">
                                        <small class="text-muted">
                                            <i class="bi bi-calendar-check me-1"></i>Actualizándose automáticamente
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6 col-lg-3">
                            <div class="stats-card info">
                                <i class="bi bi-hourglass-split stats-icon"></i>
                                <div class="stats-content">
                                    <div id="reservas-pendientes" class="stats-number">--</div>
                                    <div class="stats-label">Pendientes</div>
                                    <div class="mt-2">
                                        <small class="text-muted">
                                            <i class="bi bi-exclamation-circle me-1"></i>Requieren atención
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ALERTA DE RESERVAS NUEVAS PENDIENTES -->
                    <div class="row mb-5" id="alerta-reservas-nuevas" style="display: none;">
                        <div class="col-12">
                            <div class="card border-0 shadow-lg" style="background: linear-gradient(135deg, rgba(251, 191, 36, 0.1), rgba(245, 158, 11, 0.1)); border-left: 5px solid #f59e0b !important;">
                                <div class="card-body p-4">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <h4 class="mb-0" style="color: #f59e0b;">
                                            <i class="bi bi-bell-fill me-2 animate__animated animate__swing animate__infinite"></i>
                                            <span id="titulo-reservas-nuevas">¡Tienes Reservas Nuevas Pendientes!</span>
                                        </h4>
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="badge bg-warning fs-5 px-3 py-2" id="contador-reservas-nuevas">0</span>
                                            <button class="btn btn-success btn-sm" onclick="confirmarTodasLasReservas()" id="btn-confirmar-todas">
                                                <i class="bi bi-check-circle-fill me-1"></i> Confirmar Todas
                                            </button>
                                        </div>
                                    </div>
                                    <p class="text-muted mb-3">Las siguientes reservas requieren tu confirmación inmediata:</p>
                                    <div id="lista-reservas-pendientes" class="row g-3">
                                        <!-- Se llenará dinámicamente -->
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ========================================== -->
                    <!-- SECCIÓN: RESERVAS ACTIVAS (EN CURSO)      -->
                    <!-- ========================================== -->
                    <div class="row mb-5" id="reservas-activas-section">
                        <div class="col-12">
                            <div class="card border-0 shadow-sm">
                                <div class="card-header bg-gradient d-flex justify-content-between align-items-center" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                                    <h4 class="mb-0 text-white">
                                        <i class="bi bi-clock-history me-2"></i>
                                        Reservas Activas - Gestión Rápida
                                    </h4>
                                    <div class="d-flex gap-2">
                                        <button class="btn btn-light btn-sm" onclick="cargarReservasActivas()" id="btn-refresh-activas">
                                            <i class="bi bi-arrow-clockwise"></i> Actualizar
                                        </button>
                                        <span class="badge bg-white text-dark fs-6 px-3" id="contador-activas">0</span>
                                    </div>
                                </div>
                                <div class="card-body p-0">
                                    <!-- Filtros -->
                                    <div class="p-3 border-bottom bg-light">
                                        <div class="row g-2">
                                            <div class="col-md-4">
                                                <select class="form-select form-select-sm" id="filtro-zona-activas">
                                                    <option value="">🌐 Todas las zonas</option>
                                                    <option value="interior">🏛️ Salón Principal</option>
                                                    <option value="terraza">🌳 Terraza</option>
                                                    <option value="vip">👑 VIP</option>
                                                    <option value="bar">🍸 Bar & Lounge</option>
                                                </select>
                                            </div>
                                            <div class="col-md-4">
                                                <select class="form-select form-select-sm" id="filtro-estado-llegada">
                                                    <option value="">Todos los estados</option>
                                                    <option value="llegado">🟢 Cliente llegó</option>
                                                    <option value="esperando">🟡 Esperando</option>
                                                    <option value="no_llego">🔴 No llegó (+15min)</option>
                                                </select>
                                            </div>
                                            <div class="col-md-4">
                                                <button class="btn btn-primary btn-sm w-100" onclick="filtrarReservasActivas()">
                                                    <i class="bi bi-funnel"></i> Filtrar
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Lista de Reservas Activas -->
                                    <div id="lista-reservas-activas" class="p-3">
                                        <div class="text-center py-5">
                                            <div class="spinner-border text-primary" role="status">
                                                <span class="visually-hidden">Cargando...</span>
                                            </div>
                                            <p class="mt-3 text-muted">Cargando reservas activas...</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ESTADÍSTICAS SECUNDARIAS -->
                    <div class="row mb-5 g-4">
                        <div class="col-md-6 col-lg-4">
                            <div class="stats-card gold">
                                <i class="bi bi-people-fill stats-icon"></i>
                                <div class="stats-content">
                                    <div id="clientes-total" class="stats-number">--</div>
                                    <div class="stats-label">Clientes Registrados</div>
                                    <div class="mt-2">
                                        <small class="text-muted">
                                            <i class="bi bi-person-check me-1"></i>Base de datos completa
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6 col-lg-4">
                            <div class="stats-card purple">
                                <i class="bi bi-egg-fried stats-icon"></i>
                                <div class="stats-content">
                                    <div id="platos-total" class="stats-number">--</div>
                                    <div class="stats-label">Platos Activos</div>
                                    <div class="mt-2">
                                        <small class="text-muted">
                                            <i class="bi bi-book me-1"></i>Menú disponible
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6 col-lg-4">
                            <div class="stats-card" style="--accent-color: #ec4899;">
                                <i class="bi bi-graph-up-arrow stats-icon"></i>
                                <div class="stats-content">
                                    <div id="ocupacion-hoy" class="stats-number">10%</div>
                                    <div class="stats-label">Ocupación Hoy</div>
                                    <div class="mt-2">
                                        <small class="text-muted">
                                            <i class="bi bi-bar-chart me-1"></i>Rendimiento actual
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ESTADÍSTICAS DE MESAS EN TIEMPO REAL -->
                    <div class="row mb-5">
                        <div class="col-12">
                            <div class="card border-0 shadow-sm">
                                <div class="card-body p-4">
                                    <div class="d-flex justify-content-between align-items-center mb-4">
                                        <h5 class="mb-0">
                                            <i class="bi bi-table me-2" style="color: var(--accent-gold);"></i>
                                            Estado de Mesas
                                        </h5>
                                        <div class="d-flex gap-3">
                                            <span class="badge bg-success">
                                                <i class="bi bi-check-circle-fill me-1"></i>
                                                <span id="count-disponibles">0</span> Disponibles
                                            </span>
                                            <span class="badge bg-danger">
                                                <i class="bi bi-x-circle-fill me-1"></i>
                                                <span id="count-ocupadas">0</span> Ocupadas
                                            </span>
                                            <span class="badge bg-info">
                                                <i class="bi bi-clock-fill me-1"></i>
                                                <span id="count-reservadas">0</span> Reservadas
                                            </span>
                                        </div>
                                    </div>
                                    <div class="progress" style="height: 40px; background: rgba(255, 255, 255, 0.05); border-radius: 20px; overflow: hidden;">
                                        <div id="barra-disponibles" class="progress-bar bg-success" role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                                        <div id="barra-ocupadas" class="progress-bar bg-danger" role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                                        <div id="barra-reservadas" class="progress-bar bg-info" role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- GRÁFICOS ESTADÍSTICOS -->
                    <div class="row mb-5 g-4">
                        <!-- GRÁFICO DE RESERVAS DEL MES -->
                        <div class="col-lg-8">
                            <div class="card border-0 shadow-sm">
                                <div class="card-header">
                                    <h5 class="mb-0">
                                        <i class="bi bi-bar-chart-fill me-2" style="color: var(--accent-gold);"></i>
                                        Reservas (Últimos 30 Días)
                                    </h5>
                                </div>
                                <div class="card-body p-4">
                                    <canvas id="chartReservasMes" style="max-height: 350px;"></canvas>
                                </div>
                            </div>
                        </div>

                        <!-- GRÁFICO DE HORARIOS POPULARES -->
                        <div class="col-lg-4">
                            <div class="card border-0 shadow-sm">
                                <div class="card-header">
                                    <h5 class="mb-0">
                                        <i class="bi bi-clock-fill me-2" style="color: var(--accent-cyan);"></i>
                                        Horarios Más Populares
                                    </h5>
                                </div>
                                <div class="card-body p-4">
                                    <canvas id="chartHorariosPopulares" style="max-height: 350px;"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ESTADÍSTICAS ADICIONALES -->
                    <div class="row mb-4 g-4">
                        <!-- TOP MESAS MÁS RESERVADAS -->
                        <div class="col-lg-6">
                            <div class="card border-0 shadow-sm">
                                <div class="card-header">
                                    <h5 class="mb-0">
                                        <i class="bi bi-trophy-fill me-2" style="color: var(--accent-gold);"></i>
                                        Mesas Más Reservadas
                                    </h5>
                                </div>
                                <div class="card-body p-4">
                                    <canvas id="chartMesasPopulares" style="max-height: 300px;"></canvas>
                                </div>
                            </div>
                        </div>

                        <!-- ESTADOS DE RESERVAS -->
                        <div class="col-lg-6">
                            <div class="card border-0 shadow-sm">
                                <div class="card-header">
                                    <h5 class="mb-0">
                                        <i class="bi bi-pie-chart-fill me-2" style="color: var(--accent-purple);"></i>
                                        Estado de Reservas
                                    </h5>
                                </div>
                                <div class="card-body p-4">
                                    <canvas id="chartEstadoReservas" style="max-height: 300px;"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- FILTRO DE RESERVAS POR MESA Y FECHA -->
                    <div class="row mb-5">
                        <div class="col-12">
                            <div class="card border-0 shadow-lg" style="background: linear-gradient(135deg, var(--dark-card) 0%, rgba(30, 35, 65, 0.95) 100%); border: 2px solid rgba(255, 215, 0, 0.2) !important; border-radius: 25px; overflow: hidden;">
                                <div class="card-header" style="background: linear-gradient(135deg, rgba(255, 215, 0, 0.15), rgba(0, 212, 255, 0.1)); border-bottom: 2px solid rgba(255, 215, 0, 0.3) !important; padding: 1.5rem 2rem;">
                                    <div class="d-flex align-items-center justify-content-between">
                                        <h4 class="mb-0" style="font-weight: 700; background: linear-gradient(135deg, var(--accent-gold), var(--accent-cyan)); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">
                                            <i class="bi bi-funnel-fill me-3" style="color: var(--accent-gold); -webkit-text-fill-color: var(--accent-gold); font-size: 1.8rem; filter: drop-shadow(0 0 10px rgba(255, 215, 0, 0.5));"></i>
                                            Consultar Reservas por Mesa y Fecha
                                        </h4>
                                        <span class="badge bg-gradient" style="background: linear-gradient(135deg, #3b82f6, #8b5cf6); padding: 0.6rem 1.2rem; font-size: 0.9rem;">
                                            <i class="bi bi-search me-1"></i>Filtro Avanzado
                                        </span>
                                    </div>
                                </div>
                                <div class="card-body p-4" style="background: rgba(0, 0, 0, 0.2);">
                                    <!-- Formulario de búsqueda mejorado -->
                                    <div class="row g-4 mb-4">
                                        <div class="col-md-3">
                                            <div class="filter-input-group">
                                                <label for="filtro-fecha-inicio" class="form-label" style="color: var(--accent-gold); font-weight: 600; font-size: 0.95rem; text-transform: uppercase; letter-spacing: 0.5px;">
                                                    <i class="bi bi-calendar-event-fill me-2" style="color: var(--accent-cyan);"></i>Fecha Inicio
                                                </label>
                                                <div class="input-group" style="position: relative;">
                                                    <span class="input-group-text" style="background: linear-gradient(135deg, rgba(59, 130, 246, 0.2), rgba(139, 92, 246, 0.2)); border: 1px solid var(--accent-cyan); color: var(--accent-cyan);">
                                                        <i class="bi bi-calendar3"></i>
                                                    </span>
                                                    <input type="date" class="form-control filter-input" id="filtro-fecha-inicio" style="font-weight: 500;">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="filter-input-group">
                                                <label for="filtro-fecha-fin" class="form-label" style="color: var(--accent-gold); font-weight: 600; font-size: 0.95rem; text-transform: uppercase; letter-spacing: 0.5px;">
                                                    <i class="bi bi-calendar-check-fill me-2" style="color: var(--accent-cyan);"></i>Fecha Fin
                                                </label>
                                                <div class="input-group">
                                                    <span class="input-group-text" style="background: linear-gradient(135deg, rgba(59, 130, 246, 0.2), rgba(139, 92, 246, 0.2)); border: 1px solid var(--accent-cyan); color: var(--accent-cyan);">
                                                        <i class="bi bi-calendar3"></i>
                                                    </span>
                                                    <input type="date" class="form-control filter-input" id="filtro-fecha-fin" style="font-weight: 500;">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="filter-input-group">
                                                <label for="filtro-mesa" class="form-label" style="color: var(--accent-gold); font-weight: 600; font-size: 0.95rem; text-transform: uppercase; letter-spacing: 0.5px;">
                                                    <i class="bi bi-table me-2" style="color: var(--accent-cyan);"></i>Seleccionar Mesa
                                                </label>
                                                <div class="input-group">
                                                    <span class="input-group-text" style="background: linear-gradient(135deg, rgba(59, 130, 246, 0.2), rgba(139, 92, 246, 0.2)); border: 1px solid var(--accent-cyan); color: var(--accent-cyan);">
                                                        <i class="bi bi-grid-3x3-gap-fill"></i>
                                                    </span>
                                                    <select class="form-select filter-input" id="filtro-mesa" style="font-weight: 500;">
                                                        <option value="">🍽️ Todas las mesas</option>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-2 d-flex align-items-end">
                                            <button type="button" class="btn w-100" id="btn-filtrar-reservas" style="background: linear-gradient(135deg, #3b82f6, #8b5cf6); border: none; color: white; font-weight: 700; padding: 0.75rem; border-radius: 15px; box-shadow: 0 8px 25px rgba(59, 130, 246, 0.4); transition: all 0.3s ease; text-transform: uppercase; letter-spacing: 0.5px;">
                                                <i class="bi bi-search me-2"></i>Buscar
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Resultados con diseño mejorado -->
                                    <div id="resultados-filtro" class="mt-5" style="display: none;">
                                        <div class="alert mb-4" style="background: linear-gradient(135deg, rgba(59, 130, 246, 0.15), rgba(139, 92, 246, 0.15)); border: 2px solid var(--accent-cyan); border-radius: 20px; padding: 1.5rem; box-shadow: 0 5px 20px rgba(59, 130, 246, 0.3);">
                                            <div class="d-flex align-items-center justify-content-between">
                                                <div class="d-flex align-items-center">
                                                    <i class="bi bi-check-circle-fill me-3" style="color: var(--accent-cyan); font-size: 2rem;"></i>
                                                    <div>
                                                        <h5 class="mb-1" style="color: var(--accent-gold); font-weight: 700;">Resultados de la Búsqueda</h5>
                                                        <p class="mb-0" style="color: #fff;">Se encontraron <strong style="color: var(--accent-cyan); font-size: 1.3rem;"><span id="total-reservas-filtradas">0</span></strong> reservas</p>
                                                    </div>
                                                </div>
                                                <span class="badge" style="background: linear-gradient(135deg, var(--accent-gold), #fbbf24); color: #000; padding: 0.7rem 1.5rem; font-size: 1.1rem; border-radius: 15px;">
                                                    <i class="bi bi-calendar-check me-1"></i><span id="total-reservas-filtradas-2">0</span>
                                                </span>
                                            </div>
                                        </div>
                                        <div class="table-responsive" style="border-radius: 20px; overflow: hidden; box-shadow: 0 10px 40px rgba(0, 0, 0, 0.5);">
                                            <table class="table table-hover mb-0" style="background: rgba(0, 0, 0, 0.3); border: 1px solid rgba(255, 215, 0, 0.2);">
                                                <thead style="background: linear-gradient(135deg, rgba(255, 215, 0, 0.2), rgba(0, 212, 255, 0.1)); border-bottom: 2px solid var(--accent-gold);">
                                                    <tr>
                                                        <th style="color: var(--accent-gold); font-weight: 700; padding: 1.2rem; border: none;">#</th>
                                                        <th style="color: var(--accent-gold); font-weight: 700; padding: 1.2rem; border: none;"><i class="bi bi-table me-2"></i>Mesa</th>
                                                        <th style="color: var(--accent-gold); font-weight: 700; padding: 1.2rem; border: none;"><i class="bi bi-person-fill me-2"></i>Cliente</th>
                                                        <th style="color: var(--accent-gold); font-weight: 700; padding: 1.2rem; border: none;"><i class="bi bi-calendar-event me-2"></i>Fecha</th>
                                                        <th style="color: var(--accent-gold); font-weight: 700; padding: 1.2rem; border: none;"><i class="bi bi-clock-fill me-2"></i>Hora</th>
                                                        <th style="color: var(--accent-gold); font-weight: 700; padding: 1.2rem; border: none;"><i class="bi bi-people-fill me-2"></i>Personas</th>
                                                        <th style="color: var(--accent-gold); font-weight: 700; padding: 1.2rem; border: none;"><i class="bi bi-flag-fill me-2"></i>Estado</th>
                                                    </tr>
                                                </thead>
                                                <tbody id="tabla-reservas-filtradas" style="color: #fff;">
                                                    <!-- Se llenará con JavaScript -->
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>

                                    <!-- Mensaje cuando no hay resultados -->
                                    <div id="sin-resultados" class="alert mt-5" style="display: none; background: linear-gradient(135deg, rgba(245, 158, 11, 0.15), rgba(239, 68, 68, 0.15)); border: 2px solid var(--warning-glow); border-radius: 20px; padding: 2rem; box-shadow: 0 8px 30px rgba(245, 158, 11, 0.3);">
                                        <div class="text-center">
                                            <i class="bi bi-exclamation-triangle-fill mb-3" style="color: var(--warning-glow); font-size: 4rem; display: block;"></i>
                                            <h5 style="color: var(--warning-glow); font-weight: 700; margin-bottom: 1rem;">No se encontraron reservas</h5>
                                            <p style="color: #fff; font-size: 1.1rem;">No hay reservas que coincidan con los criterios seleccionados. Intenta con otro rango de fechas o mesa.</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <style>
                                .filter-input-group {
                                    animation: fadeInUp 0.5s ease;
                                }
                                
                                @keyframes fadeInUp {
                                    from {
                                        opacity: 0;
                                        transform: translateY(20px);
                                    }
                                    to {
                                        opacity: 1;
                                        transform: translateY(0);
                                    }
                                }
                                
                                .filter-input {
                                    background: rgba(26, 29, 53, 0.8) !important;
                                    border: 1px solid rgba(0, 212, 255, 0.3) !important;
                                    color: var(--accent-cyan) !important;
                                    padding: 0.75rem 1rem;
                                    border-radius: 0 12px 12px 0 !important;
                                    transition: all 0.3s ease;
                                }
                                
                                .filter-input:focus {
                                    background: rgba(26, 29, 53, 1) !important;
                                    border-color: var(--accent-gold) !important;
                                    box-shadow: 0 0 0 0.3rem rgba(255, 215, 0, 0.25) !important;
                                    transform: translateY(-2px);
                                }
                                
                                .filter-input option {
                                    background: #1a1d35 !important;
                                    color: #ffffff !important;
                                    padding: 10px;
                                }
                                
                                #btn-filtrar-reservas:hover {
                                    transform: translateY(-5px);
                                    box-shadow: 0 15px 40px rgba(59, 130, 246, 0.6) !important;
                                    background: linear-gradient(135deg, #2563eb, #7c3aed) !important;
                                }
                                
                                #btn-filtrar-reservas:active {
                                    transform: translateY(-2px);
                                }
                                
                                .table-hover tbody tr:hover {
                                    background: rgba(255, 215, 0, 0.1) !important;
                                    transform: scale(1.01);
                                    transition: all 0.2s ease;
                                    cursor: pointer;
                                }
                                
                                .table tbody td {
                                    padding: 1rem !important;
                                    border-color: rgba(255, 215, 0, 0.1) !important;
                                    vertical-align: middle;
                                }
                                
                                .input-group-text {
                                    border-radius: 12px 0 0 12px !important;
                                }
                            </style>
                        </div>
                    </div>
                    
                    <!-- LAYOUT VISUAL DEL RESTAURANTE -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <div class="card border-0 shadow-sm">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0">
                                        <i class="bi bi-diagram-3-fill me-2" style="color: var(--accent-gold);"></i>
                                        Distribución Visual del Restaurante
                                    </h5>
                                    <button class="btn btn-sm btn-outline-warning" onclick="restaurantLayout.refresh()">
                                        <i class="bi bi-arrow-clockwise me-1"></i> Actualizar Vista
                                    </button>
                                </div>
                                <div class="card-body p-3" style="background: rgba(0, 0, 0, 0.2);">
                                    <div id="restaurant-layout-container"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- ACCIONES RÁPIDAS -->
                    <div class="row mb-4" id="reservas-section">
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
                                            <button class="btn btn-outline-secondary w-100 py-3" onclick="mostrarGestionHorarios()">
                                                <i class="bi bi-clock-history fs-2 d-block mb-2"></i>
                                                Configurar Horarios
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
                            <div class="modal-content" id="gestion-mesas-section">
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
                                        <button class="btn btn-warning" onclick="gestionMesas.mostrarAccionesMasivas()">
                                            <i class="fas fa-tasks me-2"></i>
                                            Acciones Masivas
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
                    
                    <!-- MODAL: GESTIÓN DE HORARIOS -->
                    <div class="modal fade" id="modalGestionHorarios" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog modal-lg">
                            <div class="modal-content">
                                <div class="modal-header" style="background: var(--gradient-primary);">
                                    <h5 class="modal-title text-white">
                                        <i class="bi bi-clock-history me-2"></i>
                                        Configuración de Horarios de Reserva
                                    </h5>
                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="alert alert-info">
                                        <i class="bi bi-info-circle me-2"></i>
                                        Configura el horario en que los clientes pueden hacer reservas y los días que el restaurante está cerrado.
                                    </div>
                                    
                                    <form id="formHorarios">
                                        <div class="row mb-4">
                                            <div class="col-md-6">
                                                <label class="form-label fw-bold">
                                                    <i class="bi bi-clock me-1"></i>
                                                    Hora de Inicio de Reservas
                                                </label>
                                                <input type="time" class="form-control" id="horaApertura" step="60" required>
                                                <small class="text-muted">Primera hora disponible para reservar</small>
                                            </div>
                                            
                                            <div class="col-md-6">
                                                <label class="form-label fw-bold">
                                                    <i class="bi bi-clock-fill me-1"></i>
                                                    Hora Final de Reservas
                                                </label>
                                                <input type="time" class="form-control" id="horaCierre" step="60" required>
                                                <small class="text-muted">Última hora disponible para reservar</small>
                                            </div>
                                        </div>
                                        
                                        <div class="mb-4">
                                            <label class="form-label fw-bold">
                                                <i class="bi bi-calendar-x me-1"></i>
                                                Días Cerrados
                                            </label>
                                            <div class="row g-2">
                                                <div class="col-6 col-md-3">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" id="cerradoLunes" value="1">
                                                        <label class="form-check-label" for="cerradoLunes">Lunes</label>
                                                    </div>
                                                </div>
                                                <div class="col-6 col-md-3">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" id="cerradoMartes" value="2">
                                                        <label class="form-check-label" for="cerradoMartes">Martes</label>
                                                    </div>
                                                </div>
                                                <div class="col-6 col-md-3">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" id="cerradoMiercoles" value="3">
                                                        <label class="form-check-label" for="cerradoMiercoles">Miércoles</label>
                                                    </div>
                                                </div>
                                                <div class="col-6 col-md-3">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" id="cerradoJueves" value="4">
                                                        <label class="form-check-label" for="cerradoJueves">Jueves</label>
                                                    </div>
                                                </div>
                                                <div class="col-6 col-md-3">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" id="cerradoViernes" value="5">
                                                        <label class="form-check-label" for="cerradoViernes">Viernes</label>
                                                    </div>
                                                </div>
                                                <div class="col-6 col-md-3">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" id="cerradoSabado" value="6">
                                                        <label class="form-check-label" for="cerradoSabado">Sábado</label>
                                                    </div>
                                                </div>
                                                <div class="col-6 col-md-3">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" id="cerradoDomingo" value="0">
                                                        <label class="form-check-label" for="cerradoDomingo">Domingo</label>
                                                    </div>
                                                </div>
                                            </div>
                                            <small class="text-muted">Marca los días que el restaurante NO acepta reservas</small>
                                        </div>
                                    </form>
                                    
                                    <div id="estadoActualHorarios" class="mt-4 p-3" style="background: rgba(0,0,0,0.2); border-radius: 8px;">
                                        <h6 class="mb-3"><i class="bi bi-info-circle me-2"></i>Configuración Actual:</h6>
                                        <div class="spinner-border spinner-border-sm" role="status">
                                            <span class="visually-hidden">Cargando...</span>
                                        </div>
                                    </div>
                                    
                                    <div class="alert alert-warning mt-3">
                                        <i class="bi bi-exclamation-triangle me-2"></i>
                                        <strong>Nota:</strong> Los cambios se aplicarán inmediatamente y el sistema validará automáticamente que las nuevas reservas cumplan con estos horarios.
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                    <button type="button" class="btn btn-primary" onclick="window.guardarHorarios()">
                                        <i class="bi bi-save me-2"></i>
                                        Guardar Configuración
                                    </button>
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
                                        
                                        <div class="mb-3">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="clearBeforeLoad" checked>
                                                <label class="form-check-label" for="clearBeforeLoad">
                                                    <strong>Reemplazar menú completo</strong>
                                                    <br>
                                                    <small class="text-muted">
                                                        ✅ Recomendado: Elimina todos los platos y categorías actuales antes de cargar el nuevo Excel.
                                                        Si desmarcas esta opción, solo actualizará los platos existentes y agregará nuevos.
                                                    </small>
                                                </label>
                                            </div>
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
        // Variables globales para almacenar instancias de gráficos
        let chartReservasMes = null;
        let chartHorarios = null;
        let chartMesas = null;
        let chartEstado = null;
        
        // Configuración global de Chart.js para tema oscuro
        Chart.defaults.color = '#a0aec0';
        Chart.defaults.borderColor = 'rgba(255, 255, 255, 0.1)';
        Chart.defaults.plugins.legend.labels.color = '#ffffff';
        Chart.defaults.plugins.tooltip.backgroundColor = 'rgba(21, 25, 50, 0.95)';
        Chart.defaults.plugins.tooltip.titleColor = '#ffd700';
        Chart.defaults.plugins.tooltip.bodyColor = '#ffffff';
        Chart.defaults.plugins.tooltip.borderColor = '#ffd700';
        Chart.defaults.plugins.tooltip.borderWidth = 1;

        // Inicializar gráficos al cargar el DOM
        document.addEventListener('DOMContentLoaded', function() {
            actualizarEstadosAutomaticamente();
            cargarDatosYGraficos();
            
            // Actualizar estados cada 2 minutos si está habilitado
            let intervaloActualizacion = setInterval(actualizarEstadosAutomaticamente, 120000);
            
            // Control del toggle auto-actualización
            document.getElementById('toggle-auto-update').addEventListener('change', function(e) {
                if (e.target.checked) {
                    intervaloActualizacion = setInterval(actualizarEstadosAutomaticamente, 120000);
                } else {
                    clearInterval(intervaloActualizacion);
                }
            });
            
            // Botón actualizar manual
            document.getElementById('btn-actualizar-manual').addEventListener('click', async function() {
                const btn = this;
                btn.disabled = true;
                btn.innerHTML = '<i class="bi bi-arrow-clockwise spinner-border spinner-border-sm"></i> Actualizando...';
                
                await actualizarEstadosAutomaticamente();
                await cargarDatosYGraficos();
                
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-arrow-clockwise"></i> Actualizar';
            });
        });

        // Función para actualizar estados de reservas automáticamente
        async function actualizarEstadosAutomaticamente() {
            try {
                const response = await fetch('app/api/actualizar_estados_reservas.php', {
                    credentials: 'same-origin'
                });
                const data = await response.json();
                
                // Actualizar indicador de última actualización
                const ahora = new Date();
                const horaFormato = ahora.toLocaleTimeString('es-ES');
                document.getElementById('ultima-actualizacion').textContent = `Última actualización: ${horaFormato}`;
                
                return data;
            } catch (error) {
                console.error('Error actualizando estados:', error);
                return null;
            }
        }

        function cargarDatosYGraficos() {
            const indicador = document.getElementById('indicador-conexion');
            
            // Forzar actualización del badge de ocupación al iniciar
            const porcentajeOcupacionInicial = document.getElementById('porcentaje-ocupacion');
            if (porcentajeOcupacionInicial) {
                porcentajeOcupacionInicial.textContent = 'Cargando...';
            }
            
            fetch('app/api/dashboard_stats.php?v=' + Date.now(), {
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json'
                }
            })
                .then(response => response.json())
                .then(result => {
                    if (result.success && result.data) {
                        indicador.innerHTML = '🟢 Conectado';
                        indicador.className = 'me-3';
                        
                        // Actualizar estadísticas de las cards
                        actualizarEstadisticas(result.data);
                        
                        // Inicializar gráficos
                        inicializarGraficos(result.data);
                    } else {
                        indicador.innerHTML = '🔴 Error de conexión';
                        indicador.className = 'me-3 text-danger';
                        console.error('Error al obtener datos:', result.error);
                        // Mostrar gráficos con datos de ejemplo si hay error
                        inicializarGraficos(null);
                    }
                })
                .catch(error => {
                    indicador.innerHTML = '🔴 Error de conexión';
                    indicador.className = 'me-3 text-danger';
                    console.error('Error de conexión:', error);
                    // Mostrar gráficos con datos de ejemplo si hay error
                    inicializarGraficos(null);
                });
        }

        function actualizarEstadisticas(datos) {
            // Si no se pasan datos, recargar desde el servidor
            if (!datos) {
                fetch('app/api/dashboard_stats.php?v=' + Date.now(), {
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json'
                    }
                })
                .then(response => response.json())
                .then(result => {
                    if (result.success && result.data) {
                        actualizarEstadisticas(result.data);
                    }
                })
                .catch(error => {
                    console.error('Error recargando estadísticas:', error);
                });
                return;
            }
            
            // Actualizar cards principales
            document.getElementById('total-mesas').textContent = datos.totalMesas || '--';
            document.getElementById('mesas-disponibles').textContent = datos.mesasDisponibles || '--';
            document.getElementById('reservas-hoy').textContent = datos.reservasHoy || '--';
            document.getElementById('reservas-pendientes').textContent = datos.reservasPendientes || '--';
            
            // Actualizar cards secundarias
            document.getElementById('clientes-total').textContent = datos.clientesTotal || '--';
            
            const platosTotal = document.getElementById('platos-total');
            if (platosTotal) {
                platosTotal.textContent = datos.platosActivos || '--';
            }
            
            const ocupacionHoy = document.getElementById('ocupacion-hoy');
            if (ocupacionHoy) {
                const ocupacionValor = datos.porcentajeOcupacion ?? 0;
                ocupacionHoy.textContent = ocupacionValor + '%';
            }
            
            // Actualizar barras de estado de mesas
            const totalMesas = datos.totalMesas || 1;
            const disponibles = datos.mesasDisponibles || 0;
            const ocupadas = datos.mesasOcupadas || 0;
            const reservadas = (totalMesas - disponibles - ocupadas) || 0;
            
            document.getElementById('count-disponibles').textContent = disponibles;
            document.getElementById('count-ocupadas').textContent = ocupadas;
            document.getElementById('count-reservadas').textContent = reservadas;
            
            const porcDisponibles = Math.round((disponibles / totalMesas) * 100);
            const porcOcupadas = Math.round((ocupadas / totalMesas) * 100);
            const porcReservadas = Math.round((reservadas / totalMesas) * 100);
            
            document.getElementById('barra-disponibles').style.width = porcDisponibles + '%';
            document.getElementById('barra-ocupadas').style.width = porcOcupadas + '%';
            document.getElementById('barra-reservadas').style.width = porcReservadas + '%';
            
            document.getElementById('barra-disponibles').setAttribute('aria-valuenow', porcDisponibles);
            document.getElementById('barra-ocupadas').setAttribute('aria-valuenow', porcOcupadas);
            document.getElementById('barra-reservadas').setAttribute('aria-valuenow', porcReservadas);
            
            // Actualizar info adicional
            const infoDisponibles = document.getElementById('info-disponibles');
            if (infoDisponibles) {
                infoDisponibles.textContent = `${datos.mesasDisponibles || 0} disponibles / ${datos.mesasOcupadas || 0} ocupadas`;
            }
            
            // Actualizar última actualización
            const ahora = new Date();
            const horaActual = ahora.toLocaleTimeString('es-ES');
            const ultimaActualizacion = document.getElementById('ultima-actualizacion');
            if (ultimaActualizacion) {
                ultimaActualizacion.textContent = `Última actualización: ${horaActual}`;
            }
            
            // Cargar reservas pendientes
            cargarReservasPendientes();
        }

        // Función para cargar y mostrar reservas pendientes
        async function cargarReservasPendientes() {
            console.log('🔄 Cargando reservas pendientes...');
            try {
                // Cargar tanto reservas normales como de zona
                const [responseNormales, responseZonas] = await Promise.all([
                    fetch('app/obtener_reservas.php?estado=pendiente', {
                        credentials: 'same-origin',
                        headers: { 'Accept': 'application/json' },
                        cache: 'no-cache'
                    }),
                    fetch('app/api/obtener_reservas_zonas.php?estado=pendiente', {
                        credentials: 'same-origin',
                        headers: { 'Accept': 'application/json' },
                        cache: 'no-cache'
                    })
                ]);
                
                const dataNormales = await responseNormales.json();
                const dataZonas = await responseZonas.json();
                
                const reservasNormales = dataNormales.success ? dataNormales.reservas : [];
                const reservasZonas = dataZonas.success ? dataZonas.reservas : [];
                
                const alertaContainer = document.getElementById('alerta-reservas-nuevas');
                const listaContainer = document.getElementById('lista-reservas-pendientes');
                const contador = document.getElementById('contador-reservas-nuevas');
                const titulo = document.getElementById('titulo-reservas-nuevas');
                
                const totalReservas = reservasNormales.length + reservasZonas.length;
                
                if (totalReservas > 0) {
                    // Mostrar alerta
                    alertaContainer.style.display = 'block';
                    contador.textContent = totalReservas;
                    titulo.textContent = totalReservas === 1 
                        ? '¡Tienes 1 Reserva Nueva Pendiente!' 
                        : `¡Tienes ${totalReservas} Reservas Nuevas Pendientes!`;
                    
                    // Generar tarjetas de reservas normales
                    const cardsNormales = reservasNormales.map(reserva => `
                        <div class="col-md-6 col-lg-4">
                            <div class="card border-warning shadow-sm h-100" style="border-width: 2px;">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-start mb-3">
                                        <h5 class="card-title mb-0">
                                            <i class="bi bi-person-fill text-warning"></i>
                                            ${reserva.cliente_nombre || 'Cliente'}
                                        </h5>
                                        <span class="badge bg-warning">MESA</span>
                                    </div>
                                    <div class="mb-2">
                                        <i class="bi bi-calendar3 text-muted"></i>
                                        <strong>Fecha:</strong> ${(() => {
                                            const fecha = reserva.fecha_reserva.split('T')[0];
                                            const [year, month, day] = fecha.split('-');
                                            return `${day}/${month}/${year}`;
                                        })()}
                                    </div>
                                    <div class="mb-2">
                                        <i class="bi bi-clock text-muted"></i>
                                        <strong>Hora:</strong> ${reserva.hora_reserva}
                                    </div>
                                    <div class="mb-2">
                                        <i class="bi bi-table text-muted"></i>
                                        <strong>Mesa:</strong> ${reserva.mesa_numero || reserva.mesa_id}
                                    </div>
                                    <div class="mb-3">
                                        <i class="bi bi-people text-muted"></i>
                                        <strong>Personas:</strong> ${reserva.numero_personas}
                                    </div>
                                    <div class="d-grid gap-2">
                                        <button class="btn btn-success" onclick="confirmarReservaNueva(${reserva.id})">
                                            <i class="bi bi-check-circle me-1"></i> Confirmar
                                        </button>
                                        <button class="btn btn-outline-danger btn-sm" onclick="cancelarReservaNueva(${reserva.id})">
                                            <i class="bi bi-x-circle me-1"></i> Rechazar
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `);
                    
                    // Generar tarjetas de reservas de zona
                    const cardsZonas = reservasZonas.map(reserva => `
                        <div class="col-md-6 col-lg-4">
                            <div class="card border-success shadow-sm h-100" style="border-width: 3px;">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-start mb-3">
                                        <h5 class="card-title mb-0">
                                            <i class="bi bi-person-fill text-success"></i>
                                            ${reserva.cliente_nombre || 'Cliente'}
                                        </h5>
                                        <span class="badge bg-success">ZONA COMPLETA</span>
                                    </div>
                                    <div class="mb-2">
                                        <i class="bi bi-building text-muted"></i>
                                        <strong>Zonas:</strong> ${reserva.zonas_nombres.join(', ')}
                                    </div>
                                    <div class="mb-2">
                                        <i class="bi bi-calendar3 text-muted"></i>
                                        <strong>Fecha:</strong> ${(() => {
                                            const fecha = reserva.fecha_reserva.split('T')[0];
                                            const [year, month, day] = fecha.split('-');
                                            return `${day}/${month}/${year}`;
                                        })()}
                                    </div>
                                    <div class="mb-2">
                                        <i class="bi bi-clock text-muted"></i>
                                        <strong>Hora:</strong> ${reserva.hora_reserva}
                                    </div>
                                    <div class="mb-2">
                                        <i class="bi bi-table text-muted"></i>
                                        <strong>Mesas:</strong> ${reserva.cantidad_mesas} incluidas
                                    </div>
                                    <div class="mb-2">
                                        <i class="bi bi-people text-muted"></i>
                                        <strong>Personas:</strong> ${reserva.numero_personas}
                                    </div>
                                    <div class="mb-3">
                                        <i class="bi bi-cash text-muted"></i>
                                        <strong>Total:</strong> $${reserva.precio_total}
                                    </div>
                                    <div class="d-grid gap-2">
                                        <button class="btn btn-success" onclick="confirmarReservaZona(${reserva.id})">
                                            <i class="bi bi-check-circle me-1"></i> Confirmar Zona
                                        </button>
                                        <button class="btn btn-outline-danger btn-sm" onclick="rechazarReservaZona(${reserva.id})">
                                            <i class="bi bi-x-circle me-1"></i> Rechazar
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `);
                    
                    listaContainer.innerHTML = [...cardsNormales, ...cardsZonas].join('');
                } else {
                    // Ocultar alerta si no hay pendientes
                    alertaContainer.style.display = 'none';
                }
            } catch (error) {
                console.error('Error cargando reservas pendientes:', error);
                const alertaContainer = document.getElementById('alerta-reservas-nuevas');
                if (alertaContainer) {
                    alertaContainer.style.display = 'none';
                }
            }
        }

        // Función para confirmar reserva y enviar WhatsApp
        async function confirmarReservaNueva(reservaId) {
            try {
                if (!reservaId) {
                    throw new Error('ID de reserva requerido');
                }

                const resultado = await Swal.fire({
                    title: '¿Confirmar Reserva?',
                    html: `
                        <p>Se confirmará la reserva y se enviará una notificación por WhatsApp al cliente.</p>
                        <div class="alert alert-info mt-3">
                            <i class="bi bi-whatsapp"></i> El cliente recibirá un mensaje de confirmación automáticamente
                        </div>
                    `,
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#10b981',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: '<i class="bi bi-check-circle"></i> Sí, Confirmar',
                    cancelButtonText: 'Cancelar'
                });

                if (resultado.isConfirmed) {
                    // Mostrar loading
                    Swal.fire({
                        title: 'Confirmando...',
                        html: 'Enviando notificación por WhatsApp...',
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });

                    const response = await fetch('app/api/confirmar_reserva_admin.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({ reserva_id: reservaId, id: reservaId })
                    });

                    const raw = await response.text();
                    let data;
                    try {
                        data = JSON.parse(raw);
                    } catch (e) {
                        throw new Error(raw || `Error HTTP ${response.status}`);
                    }

                    if (data.success) {
                        const zonasCanceladas = data.reservas_zona_canceladas && data.reservas_zona_canceladas.total > 0
                            ? `
                                <div class="alert alert-warning mt-3">
                                    <strong>Reservas de zona canceladas automáticamente:</strong>
                                    <ul style="margin: 8px 0 0 18px;">
                                        ${data.reservas_zona_canceladas.detalles.map(z =>
                                            `<li>#${z.id} - ${z.cliente} (${z.hora_reserva})</li>`
                                        ).join('')}
                                    </ul>
                                </div>
                              `
                            : '';
                        await Swal.fire({
                            title: '¡Reserva Confirmada!',
                            html: `
                                <p>${data.message}</p>
                                ${data.whatsapp && data.whatsapp.enviado 
                                    ? '<div class="alert alert-success mt-2"><i class="bi bi-whatsapp"></i> WhatsApp enviado correctamente</div>' 
                                    : `<div class="alert alert-warning mt-2"><i class="bi bi-exclamation-triangle"></i> No se pudo enviar WhatsApp${data.whatsapp && data.whatsapp.error ? `<br><small>${data.whatsapp.error}</small>` : ''}</div>`
                                }
                                ${zonasCanceladas}
                            `,
                            icon: 'success',
                            confirmButtonColor: '#10b981'
                        });

                        // Recargar reservas pendientes y estadísticas
                        await cargarReservasPendientes();
                        await actualizarEstadosAutomaticamente();
                        await cargarDatosYGraficos();
                    } else {
                        throw new Error(data.message || 'Error al confirmar reserva');
                    }
                }
            } catch (error) {
                Swal.fire({
                    title: 'Error',
                    text: error.message || 'No se pudo confirmar la reserva',
                    icon: 'error',
                    confirmButtonColor: '#ef4444'
                });
            }
        }

        // Función para cancelar/rechazar reserva
        async function cancelarReservaNueva(reservaId) {
            try {
                const resultado = await Swal.fire({
                    title: '¿Rechazar Reserva?',
                    text: 'Esta acción no se puede deshacer',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#ef4444',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Sí, Rechazar',
                    cancelButtonText: 'Cancelar'
                });

                if (resultado.isConfirmed) {
                    const response = await fetch('app/eliminar_reserva.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({ id: reservaId })
                    });

                    const data = await response.json();

                    if (data.success) {
                        await Swal.fire({
                            title: 'Reserva Rechazada',
                            text: data.message,
                            icon: 'success',
                            confirmButtonColor: '#10b981'
                        });

                        // Recargar reservas pendientes y estadísticas
                        await cargarReservasPendientes();
                        await actualizarEstadosAutomaticamente();
                        await cargarDatosYGraficos();
                    } else {
                        throw new Error(data.message || 'Error al rechazar reserva');
                    }
                }
            } catch (error) {
                Swal.fire({
                    title: 'Error',
                    text: error.message || 'No se pudo rechazar la reserva',
                    icon: 'error',
                    confirmButtonColor: '#ef4444'
                });
            }
        }

        // Función para confirmar TODAS las reservas pendientes de una vez
        async function confirmarTodasLasReservas() {
            try {
                // Obtener todas las reservas pendientes (normales y de zona)
                const [responseNormales, responseZonas] = await Promise.all([
                    fetch('app/obtener_reservas.php?estado=pendiente'),
                    fetch('app/api/obtener_reservas_zonas.php?estado=pendiente')
                ]);
                
                const dataNormales = await responseNormales.json();
                const dataZonas = await responseZonas.json();
                
                const reservasNormales = dataNormales.success ? dataNormales.reservas : [];
                const reservasZonas = dataZonas.success ? dataZonas.reservas : [];
                const totalReservas = reservasNormales.length + reservasZonas.length;
                
                if (totalReservas === 0) {
                    Swal.fire({
                        icon: 'info',
                        title: 'Sin Reservas',
                        text: 'No hay reservas pendientes para confirmar',
                        confirmButtonColor: '#d4af37'
                    });
                    return;
                }
                
                const resultado = await Swal.fire({
                    title: '¿Confirmar Todas las Reservas?',
                    html: `
                        <p>Se confirmarán:</p>
                        <ul class="text-start">
                            <li><strong>${reservasNormales.length}</strong> reserva(s) de mesa</li>
                            <li><strong>${reservasZonas.length}</strong> reserva(s) de zona completa</li>
                        </ul>
                        <div class="alert alert-warning mt-3">
                            <i class="bi bi-exclamation-triangle"></i> Esta acción confirmará todas las reservas a la vez
                        </div>
                    `,
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#10b981',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: '<i class="bi bi-check-all"></i> Sí, Confirmar Todas',
                    cancelButtonText: 'Cancelar'
                });
                
                if (!resultado.isConfirmed) return;
                
                Swal.fire({
                    title: 'Confirmando Reservas...',
                    html: `<div>Procesando <span id="progreso-reservas">0</span>/${totalReservas}</div>`,
                    allowOutsideClick: false,
                    didOpen: () => { Swal.showLoading(); }
                });
                
                let confirmadas = 0;
                let errores = 0;
                let progreso = 0;
                
                // Confirmar reservas normales
                for (const reserva of reservasNormales) {
                    progreso++;
                    document.getElementById('progreso-reservas').textContent = progreso;
                    try {
                        if (!reserva.id) {
                            errores++;
                            continue;
                        }
                        const respuesta = await fetch('app/api/confirmar_reserva_admin.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ reserva_id: reserva.id, id: reserva.id })
                        });
                        const raw = await respuesta.text();
                        let resultado = { success: false };
                        try {
                            resultado = JSON.parse(raw);
                        } catch (e) {
                            resultado = { success: false, message: raw || `Error HTTP ${respuesta.status}` };
                        }
                        if (resultado.success) confirmadas++;
                        else errores++;
                    } catch (error) {
                        errores++;
                    }
                }
                
                // Confirmar reservas de zona
                for (const reserva of reservasZonas) {
                    progreso++;
                    document.getElementById('progreso-reservas').textContent = progreso;
                    try {
                        const respuesta = await fetch('app/api/gestionar_reserva_zona.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ reserva_id: reserva.id, accion: 'confirmar' })
                        });
                        const resultado = await respuesta.json();
                        if (resultado.success) confirmadas++;
                        else errores++;
                    } catch (error) {
                        errores++;
                    }
                }
                
                await Swal.fire({
                    title: '¡Proceso Completado!',
                    html: `
                        <div class="text-start">
                            <p><strong>✅ Confirmadas:</strong> ${confirmadas}</p>
                            ${errores > 0 ? `<p><strong>❌ Errores:</strong> ${errores}</p>` : ''}
                        </div>
                    `,
                    icon: errores > 0 ? 'warning' : 'success',
                    confirmButtonColor: '#10b981'
                });
                
                await cargarReservasPendientes();
                await actualizarEstadosAutomaticamente();
                await cargarDatosYGraficos();
                
            } catch (error) {
                console.error('Error:', error);
                Swal.fire({
                    title: 'Error',
                    text: 'No se pudieron confirmar las reservas',
                    icon: 'error',
                    confirmButtonColor: '#ef4444'
                });
            }
        }

        // Funciones para gestionar reservas de zona
        async function confirmarReservaZona(reservaId) {
            try {
                const resultado = await Swal.fire({
                    title: '¿Confirmar Reserva de Zona?',
                    html: `
                        <p>Se confirmará la reserva de zona completa.</p>
                        <div class="alert alert-info mt-3">
                            <i class="bi bi-building"></i> Todas las mesas de las zonas seleccionadas quedarán reservadas
                        </div>
                    `,
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#10b981',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: '<i class="bi bi-check-circle"></i> Sí, Confirmar',
                    cancelButtonText: 'Cancelar'
                });

                if (resultado.isConfirmed) {
                    Swal.fire({
                        title: 'Confirmando...',
                        allowOutsideClick: false,
                        didOpen: () => { Swal.showLoading(); }
                    });

                    const response = await fetch('app/api/gestionar_reserva_zona.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ reserva_id: reservaId, accion: 'confirmar' })
                    });

                    const data = await response.json();

                    if (data.success) {
                        await Swal.fire({
                            title: '¡Reserva de Zona Confirmada!',
                            text: data.message,
                            icon: 'success',
                            confirmButtonColor: '#10b981'
                        });

                        await cargarReservasPendientes();
                        await actualizarEstadosAutomaticamente();
                        await cargarDatosYGraficos();
                    } else {
                        throw new Error(data.message || 'Error al confirmar reserva');
                    }
                }
            } catch (error) {
                Swal.fire({
                    title: 'Error',
                    text: error.message || 'No se pudo confirmar la reserva de zona',
                    icon: 'error',
                    confirmButtonColor: '#ef4444'
                });
            }
        }

        async function rechazarReservaZona(reservaId) {
            try {
                const resultado = await Swal.fire({
                    title: '¿Rechazar Reserva de Zona?',
                    input: 'textarea',
                    inputLabel: 'Motivo del rechazo (opcional)',
                    inputPlaceholder: 'Ej: No hay disponibilidad, horario no permitido, etc.',
                    showCancelButton: true,
                    confirmButtonColor: '#ef4444',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Rechazar',
                    cancelButtonText: 'Cancelar'
                });

                if (resultado.isConfirmed) {
                    const response = await fetch('app/api/gestionar_reserva_zona.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ 
                            reserva_id: reservaId, 
                            accion: 'rechazar',
                            motivo: resultado.value || 'Sin motivo especificado'
                        })
                    });

                    const data = await response.json();

                    if (data.success) {
                        await Swal.fire({
                            title: 'Reserva Rechazada',
                            text: data.message,
                            icon: 'info',
                            confirmButtonColor: '#d4af37'
                        });

                        await cargarReservasPendientes();
                        await cargarDatosYGraficos();
                    } else {
                        throw new Error(data.message || 'Error al rechazar reserva');
                    }
                }
            } catch (error) {
                Swal.fire({
                    title: 'Error',
                    text: error.message || 'No se pudo rechazar la reserva',
                    icon: 'error',
                    confirmButtonColor: '#ef4444'
                });
            }
        }

        function inicializarGraficos(datos) {
            // Destruir gráficos existentes antes de crear nuevos
            if (chartReservasMes) {
                chartReservasMes.destroy();
                chartReservasMes = null;
            }
            if (chartHorarios) {
                chartHorarios.destroy();
                chartHorarios = null;
            }
            if (chartMesas) {
                chartMesas.destroy();
                chartMesas = null;
            }
            if (chartEstado) {
                chartEstado.destroy();
                chartEstado = null;
            }
            
            // GRÁFICO 1: Reservas del Mes (Barras)
            const ctxReservasMes = document.getElementById('chartReservasMes');
            if (ctxReservasMes) {
                let labels, values;
                
                if (datos && datos.reservasMes && datos.reservasMes.length > 0) {
                    labels = datos.reservasMes.map(item => item.semana);
                    values = datos.reservasMes.map(item => item.total);
                } else {
                    labels = ['Semana 1', 'Semana 2', 'Semana 3', 'Semana 4'];
                    values = [0, 0, 0, 0];
                }

                chartReservasMes = new Chart(ctxReservasMes, {
                    type: 'bar',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: 'Reservas',
                            data: values,
                            backgroundColor: [
                                'rgba(59, 130, 246, 0.8)',
                                'rgba(16, 185, 129, 0.8)',
                                'rgba(245, 158, 11, 0.8)',
                                'rgba(139, 92, 246, 0.8)'
                            ],
                            borderColor: [
                                'rgba(59, 130, 246, 1)',
                                'rgba(16, 185, 129, 1)',
                                'rgba(245, 158, 11, 1)',
                                'rgba(139, 92, 246, 1)'
                            ],
                            borderWidth: 2,
                            borderRadius: 10,
                            borderSkipped: false,
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: false
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        return ' Reservas: ' + context.parsed.y;
                                    }
                                }
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                grid: {
                                    color: 'rgba(255, 255, 255, 0.05)'
                                },
                                ticks: {
                                    color: '#a0aec0'
                                }
                            },
                            x: {
                                grid: {
                                    display: false
                                },
                                ticks: {
                                    color: '#a0aec0'
                                }
                            }
                        }
                    }
                });
            }

            // GRÁFICO 2: Horarios Populares (Dona)
            const ctxHorarios = document.getElementById('chartHorariosPopulares');
            if (ctxHorarios) {
                let labelsHorarios, valuesHorarios, colores;
                
                if (datos && datos.horariosPopulares && datos.horariosPopulares.length > 0) {
                    labelsHorarios = datos.horariosPopulares.map(item => item.horario);
                    valuesHorarios = datos.horariosPopulares.map(item => item.total);
                    colores = [
                        'rgba(255, 215, 0, 0.8)',
                        'rgba(59, 130, 246, 0.8)',
                        'rgba(239, 68, 68, 0.8)',
                        'rgba(139, 92, 246, 0.8)',
                        'rgba(16, 185, 129, 0.8)'
                    ];
                } else {
                    labelsHorarios = ['Sin datos'];
                    valuesHorarios = [1];
                    colores = ['rgba(107, 114, 128, 0.5)'];
                }

                chartHorarios = new Chart(ctxHorarios, {
                    type: 'doughnut',
                    data: {
                        labels: labelsHorarios,
                        datasets: [{
                            data: valuesHorarios,
                            backgroundColor: colores,
                            borderColor: colores.map(c => c.replace('0.8', '1')),
                            borderWidth: 2
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: {
                                    padding: 15,
                                    color: '#ffffff',
                                    font: {
                                        size: 12
                                    }
                                }
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                        const percentage = ((context.parsed / total) * 100).toFixed(1);
                                        return ' ' + context.label + ': ' + percentage + '%';
                                    }
                                }
                            }
                        }
                    }
                });
            }

            // GRÁFICO 3: Mesas Más Reservadas (Barras horizontales)
            const ctxMesas = document.getElementById('chartMesasPopulares');
            if (ctxMesas) {
                let labelsMesas, valuesMesas;
                
                if (datos && datos.mesasPopulares && datos.mesasPopulares.length > 0) {
                    labelsMesas = datos.mesasPopulares.map(item => item.mesa);
                    valuesMesas = datos.mesasPopulares.map(item => item.total);
                } else {
                    labelsMesas = ['Sin datos'];
                    valuesMesas = [0];
                }

                chartMesas = new Chart(ctxMesas, {
                    type: 'bar',
                    data: {
                        labels: labelsMesas,
                        datasets: [{
                            label: 'Reservas',
                            data: valuesMesas,
                            backgroundColor: 'rgba(255, 215, 0, 0.8)',
                            borderColor: 'rgba(255, 215, 0, 1)',
                            borderWidth: 2,
                            borderRadius: 10
                        }]
                    },
                    options: {
                        indexAxis: 'y',
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: false
                            }
                        },
                        scales: {
                            x: {
                                beginAtZero: true,
                                grid: {
                                    color: 'rgba(255, 255, 255, 0.05)'
                                },
                                ticks: {
                                    color: '#a0aec0'
                                }
                            },
                            y: {
                                grid: {
                                    display: false
                                },
                                ticks: {
                                    color: '#a0aec0'
                                }
                            }
                        }
                    }
                });
            }

            // GRÁFICO 4: Estado de Reservas (Pie)
            const ctxEstado = document.getElementById('chartEstadoReservas');
            if (ctxEstado) {
                let labelsEstado, valuesEstado, coloresEstado;
                
                if (datos && datos.estadosReservas && datos.estadosReservas.length > 0) {
                    labelsEstado = datos.estadosReservas.map(item => item.estado);
                    valuesEstado = datos.estadosReservas.map(item => item.total);
                    coloresEstado = [
                        'rgba(16, 185, 129, 0.8)',
                        'rgba(245, 158, 11, 0.8)',
                        'rgba(59, 130, 246, 0.8)',
                        'rgba(139, 92, 246, 0.8)',
                        'rgba(239, 68, 68, 0.8)'
                    ];
                } else {
                    labelsEstado = ['Sin datos'];
                    valuesEstado = [1];
                    coloresEstado = ['rgba(107, 114, 128, 0.5)'];
                }

                chartEstado = new Chart(ctxEstado, {
                    type: 'pie',
                    data: {
                        labels: labelsEstado,
                        datasets: [{
                            data: valuesEstado,
                            backgroundColor: coloresEstado,
                            borderColor: coloresEstado.map(c => c.replace('0.8', '1')),
                            borderWidth: 2
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: {
                                    padding: 15,
                                    color: '#ffffff',
                                    font: {
                                        size: 12
                                    }
                                }
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                        const percentage = ((context.parsed / total) * 100).toFixed(1);
                                        return ' ' + context.label + ': ' + context.parsed + ' (' + percentage + '%)';
                                    }
                                }
                            }
                        }
                    }
                });
            }
        }
    </script>

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

        // Función para hacer scroll suave a una sección
        function scrollToSection(sectionId) {
            const element = document.getElementById(sectionId);
            if (element) {
                element.scrollIntoView({ 
                    behavior: 'smooth', 
                    block: 'start' 
                });
            }
        }

        // Función para volver al inicio
        function scrollToTop() {
            window.scrollTo({ 
                top: 0, 
                behavior: 'smooth' 
            });
        }

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
                    // Notificaciones habilitadas silenciosamente
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
    
    <?php
        $tzAdmin = new DateTimeZone('America/Guayaquil');
        $fechaHoyAdmin = (new DateTime('now', $tzAdmin))->format('Y-m-d');
    ?>
    <script>
        window.FECHA_HOY_SERVIDOR = '<?php echo $fechaHoyAdmin; ?>';
    </script>

    <!-- Gestión de Reservas JavaScript -->
    <script src="public/js/gestion-reservas.js?v=<?php echo time(); ?>"></script>
    
    <script>
        // Esperar a que todos los scripts estén cargados
        document.addEventListener('DOMContentLoaded', function() {
            console.log('🍽️ Archivos de restaurant layout cargados');
        });

        // Compatibilidad: algunos flujos heredados llaman cargarReservas() global
        window.cargarReservas = async function() {
            if (window.gestionReservas && typeof window.gestionReservas.cargarReservas === 'function') {
                return window.gestionReservas.cargarReservas();
            }
            throw new Error('El módulo de gestión de reservas no está disponible');
        };
        
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
        
        // Función para mostrar modal de gestión de horarios
        window.mostrarGestionHorarios = function() {
            const modal = new bootstrap.Modal(document.getElementById('modalGestionHorarios'));
            modal.show();
            window.cargarHorariosActuales();
        };
        
        // Función para cargar horarios actuales
        window.cargarHorariosActuales = async function() {
            const estadoDiv = document.getElementById('estadoActualHorarios');
            estadoDiv.innerHTML = '<h6>Configuración Actual:</h6><div class="spinner-border spinner-border-sm" role="status"><span class="visually-hidden">Cargando...</span></div>';
            
            try {
                const response = await fetch('app/api/gestionar_horarios.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({ action: 'obtener' })
                });
                
                const data = await response.json();
                
                if (data.success && data.configuracion) {
                    const config = data.configuracion;
                    
                    // Llenar el formulario
                    if (config.hora_apertura) {
                        document.getElementById('horaApertura').value = config.hora_apertura.valor || '11:00';
                    }
                    if (config.hora_cierre) {
                        document.getElementById('horaCierre').value = config.hora_cierre.valor || '20:00';
                    }
                    
                    // Cargar días cerrados y marcar checkboxes
                    const dayNames = ['Domingo', 'Lunes', 'Martes', 'Miercoles', 'Jueves', 'Viernes', 'Sabado'];
                    const diasCerrados = config.dias_cerrados?.valor || '';
                    const diasArray = diasCerrados ? diasCerrados.split(',').map(d => d.trim()) : [];
                    
                    // Desmarcar todos primero
                    dayNames.forEach((day, index) => {
                        const checkbox = document.getElementById(`cerrado${day}`);
                        if (checkbox) checkbox.checked = false;
                    });
                    
                    // Marcar los días cerrados
                    diasArray.forEach(dia => {
                        const dayIndex = parseInt(dia);
                        if (!isNaN(dayIndex) && dayIndex >= 0 && dayIndex <= 6) {
                            const checkbox = document.getElementById(`cerrado${dayNames[dayIndex]}`);
                            if (checkbox) checkbox.checked = true;
                        }
                    });
                    
                    // Crear texto legible de días cerrados
                    let diasCerradosTexto = 'Ninguno';
                    if (diasArray.length > 0) {
                        const nombresEspañol = {
                            0: 'Domingo', 1: 'Lunes', 2: 'Martes', 3: 'Miércoles',
                            4: 'Jueves', 5: 'Viernes', 6: 'Sábado'
                        };
                        diasCerradosTexto = diasArray.map(d => nombresEspañol[parseInt(d)]).filter(Boolean).join(', ');
                    }
                    
                    // Mostrar configuración actual
                    estadoDiv.innerHTML = `
                        <h6>Configuración Actual:</h6>
                        <ul class="list-unstyled mb-0">
                            <li><strong>Apertura:</strong> ${config.hora_apertura?.valor || 'No configurado'}</li>
                            <li><strong>Cierre:</strong> ${config.hora_cierre?.valor || 'No configurado'}</li>
                            <li><strong>L-V:</strong> ${(config.horario_lunes_viernes_inicio?.valor || config.hora_apertura?.valor || 'No configurado')} - ${(config.horario_lunes_viernes_fin?.valor || config.hora_cierre?.valor || 'No configurado')}</li>
                            <li><strong>Sábado:</strong> ${(config.horario_sabado_inicio?.valor || config.hora_apertura?.valor || 'No configurado')} - ${(config.horario_sabado_fin?.valor || config.hora_cierre?.valor || 'No configurado')}</li>
                            <li><strong>Domingo:</strong> ${(config.horario_domingo_inicio?.valor || config.hora_apertura?.valor || 'No configurado')} - ${(config.horario_domingo_fin?.valor || config.hora_cierre?.valor || 'No configurado')}</li>
                            <li><strong>Días cerrados:</strong> ${diasCerradosTexto}</li>
                        </ul>
                    `;
                } else {
                    estadoDiv.innerHTML = '<div class="alert alert-warning">No se pudo cargar la configuración actual</div>';
                }
            } catch (error) {
                console.error('Error cargando horarios:', error);
                estadoDiv.innerHTML = '<div class="alert alert-danger">Error de conexión</div>';
            }
        };
        
        // Función para guardar horarios
        window.guardarHorarios = async function() {
            const form = document.getElementById('formHorarios');
            
            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }
            
            const horaApertura = document.getElementById('horaApertura').value;
            const horaCierre = document.getElementById('horaCierre').value;
            
            // Obtener días cerrados
            const diasCerrados = [];
            ['Lunes', 'Martes', 'Miercoles', 'Jueves', 'Viernes', 'Sabado', 'Domingo'].forEach(dia => {
                const checkbox = document.getElementById('cerrado' + dia);
                if (checkbox && checkbox.checked) {
                    diasCerrados.push(checkbox.value);
                }
            });
            
            // Validar que hora de cierre sea después de apertura
            if (horaCierre <= horaApertura) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'La hora final debe ser posterior a la hora de inicio'
                });
                return;
            }
            
            try {
                const response = await fetch('app/api/gestionar_horarios.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({
                        action: 'actualizar',
                        configuraciones: {
                            hora_apertura: horaApertura,
                            hora_cierre: horaCierre,
                            horario_lunes_viernes_inicio: horaApertura,
                            horario_lunes_viernes_fin: horaCierre,
                            horario_sabado_inicio: horaApertura,
                            horario_sabado_fin: horaCierre,
                            horario_domingo_inicio: horaApertura,
                            horario_domingo_fin: horaCierre,
                            dias_cerrados: diasCerrados.join(',')
                        }
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: '¡Guardado!',
                        text: 'Configuración de horarios actualizada correctamente',
                        timer: 2000,
                        showConfirmButton: false
                    });
                    window.cargarHorariosActuales();
                } else if (data.advertencia && data.requiere_confirmacion) {
                    // Hay reservas afectadas, mostrar advertencia y pedir confirmación
                    const reservasHtml = data.reservas_afectadas.map(r => {
                        let problemaBadge = '';
                        switch(r.problema) {
                            case 'dia_cerrado':
                                problemaBadge = '<span class="badge bg-dark"><i class="bi bi-x-circle"></i> Día Cerrado</span>';
                                break;
                            case 'antes_apertura':
                                problemaBadge = '<span class="badge bg-warning text-dark"><i class="bi bi-sunrise"></i> Antes de apertura</span>';
                                break;
                            case 'despues_cierre':
                                problemaBadge = '<span class="badge bg-danger"><i class="bi bi-sunset"></i> Después de cierre</span>';
                                break;
                            default:
                                problemaBadge = '<span class="badge bg-secondary">Fuera de horario</span>';
                        }
                        
                        return `
                        <tr style="background: white; color: black;">
                            <td style="color: black;"><strong>${r.cliente}</strong></td>
                            <td style="color: black;">${r.fecha}</td>
                            <td style="color: black;">${r.hora}</td>
                            <td style="color: black;">Mesa ${r.mesa}</td>
                            <td style="color: black;">${r.nuevo_horario}</td>
                            <td>${problemaBadge}</td>
                        </tr>
                        `;
                    }).join('');
                    
                    Swal.fire({
                        icon: 'warning',
                        title: '⚠️ Reservas Afectadas',
                        html: `
                            <div class="text-start">
                                <p class="mb-3 fs-5"><strong>${data.message}</strong></p>
                                <p class="text-danger mb-3">Las siguientes reservas serán <strong>CANCELADAS</strong> y se enviará notificación por WhatsApp:</p>
                                <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                                    <table class="table table-bordered table-hover" style="font-size: 0.95rem;">
                                        <thead class="table-dark" style="position: sticky; top: 0; z-index: 10;">
                                            <tr>
                                                <th style="min-width: 150px;">Cliente</th>
                                                <th style="min-width: 100px;">Fecha</th>
                                                <th style="min-width: 80px;">Hora</th>
                                                <th style="min-width: 80px;">Mesa</th>
                                                <th style="min-width: 150px;">Nuevo Horario</th>
                                                <th style="min-width: 130px;">Problema</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            ${reservasHtml}
                                        </tbody>
                                    </table>
                                </div>
                                <p class="mt-3 text-center text-muted">
                                    <i class="bi bi-whatsapp text-success fs-5"></i> 
                                    Se enviará mensaje WhatsApp a todos los clientes afectados.
                                </p>
                            </div>
                        `,
                        showCancelButton: true,
                        confirmButtonText: '<i class="bi bi-check-circle"></i> Confirmar y Cancelar Reservas',
                        cancelButtonText: '<i class="bi bi-x-circle"></i> Cancelar Cambio',
                        confirmButtonColor: '#dc3545',
                        cancelButtonColor: '#6c757d',
                        width: '900px',
                        customClass: {
                            confirmButton: 'btn btn-danger',
                            cancelButton: 'btn btn-secondary'
                        }
                    }).then(async (result) => {
                        if (result.isConfirmed) {
                            // Usuario confirmó, enviar de nuevo con forzar=true
                            try {
                                Swal.fire({
                                    title: 'Procesando...',
                                    html: 'Cancelando reservas y enviando notificaciones...',
                                    allowOutsideClick: false,
                                    didOpen: () => {
                                        Swal.showLoading();
                                    }
                                });
                                
                                const response2 = await fetch('app/api/gestionar_horarios.php', {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/json'
                                    },
                                    credentials: 'same-origin',
                                    body: JSON.stringify({
                                        action: 'actualizar',
                                        forzar: true,
                                        configuraciones: {
                                            hora_apertura: horaApertura,
                                            hora_cierre: horaCierre,
                                            horario_lunes_viernes_inicio: horaApertura,
                                            horario_lunes_viernes_fin: horaCierre,
                                            horario_sabado_inicio: horaApertura,
                                            horario_sabado_fin: horaCierre,
                                            horario_domingo_inicio: horaApertura,
                                            horario_domingo_fin: horaCierre,
                                            dias_cerrados: diasCerrados.join(',')
                                        },
                                        reservas_afectadas: data.reservas_afectadas
                                    })
                                });
                                
                                const data2 = await response2.json();
                                
                                if (data2.success) {
                                    Swal.fire({
                                        icon: 'success',
                                        title: '✅ Horarios Actualizados',
                                        html: `
                                            <p>Configuración guardada correctamente</p>
                                            <p class="text-success"><i class="bi bi-whatsapp"></i> Mensajes enviados</p>
                                            <p class="text-danger"><i class="bi bi-x-circle"></i> Reservas canceladas</p>
                                        `,
                                        confirmButtonText: 'Entendido'
                                    });
                                    window.cargarHorariosActuales();
                                } else {
                                    Swal.fire({
                                        icon: 'error',
                                        title: 'Error',
                                        text: data2.message || 'Error al actualizar horarios'
                                    });
                                }
                            } catch (error) {
                                console.error('Error:', error);
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Error',
                                    text: 'Error al procesar la actualización'
                                });
                            }
                        }
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: data.message || 'No se pudo guardar la configuración'
                    });
                }
            } catch (error) {
                console.error('Error guardando horarios:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Error de conexión al guardar los horarios'
                });
            }
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
                
                // Agregar parámetro clear_before
                const clearBefore = document.getElementById('clearBeforeLoad').checked;
                formData.append('clear_before', clearBefore ? 'true' : 'false');
                
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
        
        // =================================================================
        // FILTRO DE RESERVAS POR MESA Y FECHA
        // =================================================================
        
        // Cargar mesas en el select de filtro
        const cargarMesasEnFiltro = async () => {
            try {
                const response = await fetch('app/obtener_mesas.php');
                const data = await response.json();
                
                const selectMesa = document.getElementById('filtro-mesa');
                selectMesa.innerHTML = '<option value="">Todas las mesas</option>';
                
                if (data.success && data.mesas) {
                   data.mesas.forEach(mesa => {
                       const option = document.createElement('option');
                        option.value = mesa.id;
                        option.textContent = `Mesa ${mesa.numero_mesa} - ${mesa.ubicacion} (${mesa.capacidad} personas)`;
                        selectMesa.appendChild(option);
                    });
                }
            } catch (error) {
                console.error('Error cargando mesas:', error);
            }
        };
        
        // Función lambda para filtrar reservas
        const filtrarReservas = async () => {
            const fechaInicio = document.getElementById('filtro-fecha-inicio').value;
            const fechaFin = document.getElementById('filtro-fecha-fin').value;
            const mesaId = document.getElementById('filtro-mesa').value;
            
            // Validaciones
            if (!fechaInicio || !fechaFin) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Datos incompletos',
                    text: 'Por favor selecciona las fechas de inicio y fin'
                });
                return;
            }
            
            if (new Date(fechaInicio) > new Date(fechaFin)) {
                Swal.fire({
                    icon: 'error',
                    title: 'Fechas inválidas',
                    text: 'La fecha de inicio no puede ser mayor a la fecha fin'
                });
                return;
            }
            
            try {
                // Mostrar loading
                Swal.fire({
                    title: 'Buscando reservas...',
                    text: 'Por favor espera',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });
                
                // Construir URL con parámetros
                const params = new URLSearchParams({
                    fecha_inicio: fechaInicio,
                    fecha_fin: fechaFin
                });
                
                if (mesaId) {
                    params.append('mesa_id', mesaId);
                }
                
                const response = await fetch(`app/api/obtener_reservas_filtradas.php?${params}`);
                const data = await response.json();
                
                Swal.close();
                
                if (data.success) {
                    const reservas = data.reservas;
                    const total = data.total;
                    
                    // Mostrar/ocultar elementos según resultados
                    if (total > 0) {
                        document.getElementById('resultados-filtro').style.display = 'block';
                        document.getElementById('sin-resultados').style.display = 'none';
                        document.getElementById('total-reservas-filtradas').textContent = total;
                        document.getElementById('total-reservas-filtradas-2').textContent = total;
                        
                        // Llenar tabla con resultados
                        const tbody = document.getElementById('tabla-reservas-filtradas');
                        tbody.innerHTML = '';
                        
                        reservas.forEach((reserva, index) => {
                            const tr = document.createElement('tr');
                            
                            // Determinar clase del badge según estado
                            let badgeClass = 'bg-secondary';
                            let badgeIcon = 'bi-question-circle';
                            switch(reserva.estado) {
                                case 'confirmada':
                                    badgeClass = 'bg-success';
                                    badgeIcon = 'bi-check-circle-fill';
                                    break;
                                case 'pendiente':
                                    badgeClass = 'bg-warning text-dark';
                                    badgeIcon = 'bi-clock-fill';
                                    break;
                                case 'cancelada':
                                    badgeClass = 'bg-danger';
                                    badgeIcon = 'bi-x-circle-fill';
                                    break;
                                case 'finalizada':
                                    badgeClass = 'bg-info';
                                    badgeIcon = 'bi-flag-fill';
                                    break;
                                case 'en_curso':
                                    badgeClass = 'bg-primary';
                                    badgeIcon = 'bi-play-circle-fill';
                                    break;
                            }
                            
                            const esZona = reserva.tipo_reserva === 'zona';
                            const zonaTexto = esZona
                                ? (reserva.zonas_nombres ? reserva.zonas_nombres.join(', ') : 'Zona completa')
                                : reserva.ubicacion;
                            const tituloMesa = esZona
                                ? 'Reserva de Zona'
                                : `Mesa ${reserva.numero_mesa}`;

                            tr.innerHTML = `
                                <td style="font-weight: 700; color: var(--accent-gold);">${index + 1}</td>
                                <td>
                                    <div style="display: flex; align-items: center;">
                                        <div style="background: linear-gradient(135deg, rgba(255, 215, 0, 0.2), rgba(0, 212, 255, 0.1)); padding: 0.5rem; border-radius: 10px; margin-right: 0.8rem;">
                                            <i class="bi ${esZona ? 'bi-grid-3x3-gap-fill' : 'bi-table'}" style="color: var(--accent-gold); font-size: 1.2rem;"></i>
                                        </div>
                                        <div>
                                            <strong style="color: var(--accent-cyan); font-size: 1.1rem;">${tituloMesa}</strong>
                                            <br><small class="text-muted"><i class="bi bi-geo-alt-fill me-1"></i>${zonaTexto}</small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div>
                                        <strong style="color: #fff;">${reserva.cliente_nombre} ${reserva.cliente_apellido}</strong>
                                        <br><small class="text-muted"><i class="bi bi-telephone-fill me-1"></i>${reserva.cliente_telefono}</small>
                                    </div>
                                </td>
                                <td>
                                    <span style="background: rgba(59, 130, 246, 0.2); padding: 0.4rem 0.8rem; border-radius: 10px; color: var(--accent-cyan); font-weight: 600;">
                                        <i class="bi bi-calendar3 me-1"></i>${(() => {
                                            const fecha = reserva.fecha_reserva.split('T')[0];
                                            const [year, month, day] = fecha.split('-');
                                            const meses = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
                                            return `${day} ${meses[parseInt(month)-1]} ${year}`;
                                        })()}
                                    </span>
                                </td>
                                <td>
                                    <span style="background: linear-gradient(135deg, rgba(255, 215, 0, 0.2), rgba(0, 212, 255, 0.1)); padding: 0.5rem 1rem; border-radius: 12px; color: var(--accent-gold); font-weight: 700; font-size: 1.1rem;">
                                        <i class="bi bi-clock-fill me-2"></i>${reserva.hora_reserva}
                                    </span>
                                </td>
                                <td>
                                    <span style="background: rgba(139, 92, 246, 0.2); padding: 0.4rem 0.8rem; border-radius: 10px; color: #a78bfa; font-weight: 600;">
                                        <i class="bi bi-people-fill me-1"></i>${reserva.numero_personas}
                                    </span>
                                </td>
                                <td>
                                    <span class="badge ${badgeClass}" style="padding: 0.6rem 1.2rem; font-size: 0.9rem; border-radius: 12px; font-weight: 600;">
                                        <i class="bi ${badgeIcon} me-1"></i>${reserva.estado.toUpperCase()}
                                    </span>
                                </td>
                            `;
                            
                            tbody.appendChild(tr);
                        });
                        
                        // Scroll suave hacia los resultados
                        document.getElementById('resultados-filtro').scrollIntoView({ 
                            behavior: 'smooth', 
                            block: 'nearest' 
                        });
                        
                    } else {
                        document.getElementById('resultados-filtro').style.display = 'none';
                        document.getElementById('sin-resultados').style.display = 'block';
                        document.getElementById('sin-resultados').scrollIntoView({ 
                            behavior: 'smooth', 
                            block: 'nearest' 
                        });
                    }
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: data.error || 'No se pudieron obtener las reservas'
                    });
                }
                
            } catch (error) {
                Swal.close();
                console.error('Error filtrando reservas:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Error al realizar la búsqueda: ' + error.message
                });
            }
        };
        
        // Event listeners
        document.getElementById('btn-filtrar-reservas').addEventListener('click', filtrarReservas);
        
        // Permitir filtrar con Enter
        document.getElementById('filtro-fecha-inicio').addEventListener('keypress', (e) => {
            if (e.key === 'Enter') filtrarReservas();
        });
        document.getElementById('filtro-fecha-fin').addEventListener('keypress', (e) => {
            if (e.key === 'Enter') filtrarReservas();
        });
        
        // Cargar mesas al cargar la página
        cargarMesasEnFiltro();
        
        // =================================================================
        // FIN FILTRO DE RESERVAS
        // =================================================================

        // =================================================================
        // GESTIÓN DE RESERVAS ACTIVAS
        // =================================================================
        
        let reservasActivasData = [];
        
        /**
         * Cargar reservas activas desde el servidor
         */
        async function cargarReservasActivas() {
            const btnRefresh = document.getElementById('btn-refresh-activas');
            const listaContainer = document.getElementById('lista-reservas-activas');
            
            try {
                btnRefresh.disabled = true;
                btnRefresh.innerHTML = '<i class="bi bi-arrow-clockwise spinner-border spinner-border-sm"></i> Cargando...';
                
                const response = await fetch('app/obtener_reservas_activas.php');
                const data = await response.json();
                
                if (data.success) {
                    reservasActivasData = data.data;
                    mostrarReservasActivas(reservasActivasData);
                    document.getElementById('contador-activas').textContent = data.total;
                } else {
                    throw new Error(data.error || 'Error al cargar reservas');
                }
            } catch (error) {
                console.error('Error:', error);
                listaContainer.innerHTML = `
                    <div class="alert alert-danger m-3">
                        <i class="bi bi-exclamation-triangle"></i> 
                        Error al cargar reservas activas: ${error.message}
                    </div>
                `;
            } finally {
                btnRefresh.disabled = false;
                btnRefresh.innerHTML = '<i class="bi bi-arrow-clockwise"></i> Actualizar';
            }
        }
        
        /**
         * Mostrar reservas activas en la interfaz
         */
        function mostrarReservasActivas(reservas) {
            const container = document.getElementById('lista-reservas-activas');
            
            if (!reservas || reservas.length === 0) {
                container.innerHTML = `
                    <div class="text-center py-5">
                        <i class="bi bi-inbox" style="font-size: 4rem; color: #ccc;"></i>
                        <p class="mt-3 text-muted">No hay reservas activas en este momento</p>
                    </div>
                `;
                return;
            }
            
            let html = '<div class="row g-3">';
            
            reservas.forEach(reserva => {
                const estiloEstado = obtenerEstiloEstadoLlegada(reserva.estado_llegada);
                const colorBorde = {
                    'preparando': '#fbbf24',
                    'en_curso': '#10b981'
                }[reserva.estado] || '#6b7280';
                
                html += `
                    <div class="col-12 col-lg-6">
                        <div class="card border-0 shadow-sm" style="border-left: 4px solid ${colorBorde} !important;">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <div>
                                        <h5 class="mb-1">
                                            <i class="bi ${reserva.tipo_reserva === 'zona' ? 'bi-grid-3x3-gap-fill' : 'bi-table'}"></i>
                                            ${reserva.tipo_reserva === 'zona' ? 'Zona: ' + reserva.zona : 'Mesa #' + reserva.numero_mesa}
                                        </h5>
                                        <small class="text-muted">
                                            <i class="bi bi-geo-alt"></i> ${reserva.zona}
                                        </small>
                                    </div>
                                    <span class="badge ${reserva.estado === 'preparando' ? 'bg-warning' : 'bg-success'} px-3 py-2">
                                        ${reserva.estado === 'preparando' ? '⏳ PREPARANDO' : '🔵 EN CURSO'}
                                    </span>
                                </div>
                                
                                <div class="row g-2 mb-3">
                                    <div class="col-6">
                                        <small class="text-muted d-block">Cliente</small>
                                        <strong>${reserva.cliente_nombre} ${reserva.cliente_apellido}</strong>
                                    </div>
                                    <div class="col-6">
                                        <small class="text-muted d-block">Teléfono</small>
                                        <a href="tel:${reserva.cliente_telefono}">${reserva.cliente_telefono}</a>
                                    </div>
                                    <div class="col-4">
                                        <small class="text-muted d-block">Hora</small>
                                        <strong>${reserva.hora_reserva}</strong>
                                    </div>
                                    <div class="col-4">
                                        <small class="text-muted d-block">Personas</small>
                                        <strong>${reserva.num_personas}</strong>
                                    </div>
                                    <div class="col-4">
                                        <small class="text-muted d-block">Tiempo</small>
                                        <strong>${reserva.tiempo_transcurrido}</strong>
                                    </div>
                                </div>
                                
                                <!-- Indicador de Llegada -->
                                <div class="mb-3">
                                    <div class="d-flex align-items-center gap-2 p-2 rounded" style="background: ${estiloEstado.bg};">
                                        <span style="font-size: 1.5rem;">${estiloEstado.icono}</span>
                                        <div class="flex-fill">
                                            <small class="text-muted d-block">Estado de Llegada</small>
                                            <strong style="color: ${estiloEstado.color};">${estiloEstado.texto}</strong>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Botones de Acción -->
                                <div class="btn-group w-100" role="group">
                                    ${!reserva.cliente_llego ? `
                                        <button class="btn btn-outline-success btn-sm" 
                                                onclick="marcarComoLlegado(${reserva.id}, '${reserva.tipo_reserva}')">
                                            <i class="bi bi-check-circle"></i> Llegó
                                        </button>
                                    ` : ''}
                                    <button class="btn btn-primary btn-sm" 
                                            onclick="finalizarReservaModal(${reserva.id}, '${reserva.tipo_reserva}', '${reserva.cliente_nombre} ${reserva.cliente_apellido}', 'Mesa ${reserva.numero_mesa}')">
                                        <i class="bi bi-check-square"></i> Finalizar
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            });
            
            html += '</div>';
            container.innerHTML = html;
        }
        
        /**
         * Obtener estilos según estado de llegada
         */
        function obtenerEstiloEstadoLlegada(estado) {
            const estilos = {
                'llegado': {
                    icono: '🟢',
                    texto: 'Cliente llegó',
                    color: '#10b981',
                    bg: 'rgba(16, 185, 129, 0.1)'
                },
                'esperando': {
                    icono: '🟡',
                    texto: 'Esperando llegada',
                    color: '#f59e0b',
                    bg: 'rgba(245, 158, 11, 0.1)'
                },
                'no_llego': {
                    icono: '🔴',
                    texto: 'No ha llegado (+15min)',
                    color: '#ef4444',
                    bg: 'rgba(239, 68, 68, 0.1)'
                }
            };
            return estilos[estado] || estilos.esperando;
        }
        
        /**
         * Filtrar reservas activas
         */
        function filtrarReservasActivas() {
            const zona = document.getElementById('filtro-zona-activas').value;
            const estadoLlegada = document.getElementById('filtro-estado-llegada').value;
            
            let reservasFiltradas = reservasActivasData;
            
            if (zona) {
                reservasFiltradas = reservasFiltradas.filter(r => r.zona.includes(zona));
            }
            
            if (estadoLlegada) {
                reservasFiltradas = reservasFiltradas.filter(r => r.estado_llegada === estadoLlegada);
            }
            
            mostrarReservasActivas(reservasFiltradas);
        }
        
        /**
         * Marcar cliente como llegado
         */
        async function marcarComoLlegado(reservaId, tipoReserva) {
            try {
                const response = await fetch('app/marcar_cliente_llego.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ reserva_id: reservaId, tipo_reserva: tipoReserva })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: '¡Cliente Llegó!',
                        text: 'Se ha marcado la llegada del cliente',
                        timer: 2000,
                        showConfirmButton: false
                    });
                    cargarReservasActivas();
                } else {
                    throw new Error(data.error);
                }
            } catch (error) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: error.message
                });
            }
        }
        
        /**
         * Modal para finalizar reserva
         */
        async function finalizarReservaModal(reservaId, tipoReserva, cliente, mesa) {
            const { value: formValues } = await Swal.fire({
                title: '¿Finalizar Reserva?',
                html: `
                    <div class="text-start">
                        <p><strong>Cliente:</strong> ${cliente}</p>
                        <p><strong>${tipoReserva === 'zona' ? 'Zona' : 'Mesa'}:</strong> ${mesa}</p>
                        <hr>
                        <label for="observaciones" class="form-label">Observaciones (opcional):</label>
                        <textarea id="observaciones" class="form-control" rows="3" 
                                  placeholder="Ej: Cliente satisfecho, pidió factura..."></textarea>
                    </div>
                `,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: '<i class="bi bi-check-circle"></i> Finalizar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#10b981',
                preConfirm: () => {
                    return {
                        observaciones: document.getElementById('observaciones').value
                    };
                }
            });
            
            if (formValues) {
                await finalizarReserva(reservaId, tipoReserva, formValues.observaciones);
            }
        }
        
        /**
         * Finalizar reserva
         */
        async function finalizarReserva(reservaId, tipoReserva, observaciones) {
            try {
                const response = await fetch('app/finalizar_reserva_manual.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        reserva_id: reservaId,
                        tipo_reserva: tipoReserva,
                        observaciones: observaciones
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: '¡Reserva Finalizada!',
                        html: `
                            <p>La reserva se finalizó correctamente.</p>
                            <p><strong>Tiempo total:</strong> ${data.data.tiempo_total}</p>
                        `,
                        timer: 3000,
                        showConfirmButton: false
                    });
                    
                    // Recargar listas (solo funciones disponibles en este archivo)
                    await cargarReservasActivas();
                    await cargarReservasPendientes();
                    await cargarDatosYGraficos();

                    // Recargar modal de gestión si está inicializado
                    if (window.gestionReservas && typeof window.gestionReservas.cargarReservas === 'function') {
                        await window.gestionReservas.cargarReservas();
                    }
                } else {
                    throw new Error(data.error);
                }
            } catch (error) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: error.message
                });
            }
        }
        
        // Cargar reservas activas al inicio
        document.addEventListener('DOMContentLoaded', function() {
            cargarReservasActivas();
            // Auto-actualizar cada 2 minutos
            setInterval(cargarReservasActivas, 120000);
        });
        
        // =================================================================
        // FIN GESTIÓN DE RESERVAS ACTIVAS
        // =================================================================
    </script>

    <!-- Script de Seguridad: Deshabilitar click derecho en formularios -->
    <script src="public/js/security.js"></script>
</body>
</html>
