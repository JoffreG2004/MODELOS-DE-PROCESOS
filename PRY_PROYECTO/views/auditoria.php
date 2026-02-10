<?php
session_start();

// Verificar que el usuario es administrador autenticado
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_authenticated']) || $_SESSION['admin_authenticated'] !== true) {
    header('Location: ../index.html');
    exit();
}

$admin_nombre = $_SESSION['admin_nombre'] ?? 'Administrador';
$admin_id = $_SESSION['admin_id'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Auditoría del Sistema</title>
    <link rel="stylesheet" href="../public/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <style>
        :root {
            --dark-bg: #0a0e27;
            --dark-card: #151932;
            --dark-soft: #1f2646;
            --accent-gold: #ffd700;
            --accent-cyan: #00d4ff;
            --text-primary: #f8fafc;
            --text-secondary: #cbd5e1;
            --text-strong: #e2e8f0;
        }

        body {
            background: linear-gradient(135deg, #0a0e27 0%, #1a1f3a 100%);
            color: var(--text-primary);
            font-family: "Inter", "Segoe UI", sans-serif;
            letter-spacing: 0.01em;
        }

        .panel-shell {
            max-width: 1280px;
            margin: 0 auto;
        }

        .card {
            background: var(--dark-card);
            border: 1px solid rgba(255, 215, 0, 0.18);
            border-radius: 16px;
            color: var(--text-primary);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.25);
        }

        .card .text-muted,
        .text-muted {
            color: var(--text-secondary) !important;
        }

        .audit-card {
            border-left: 4px solid #0d6efd;
            margin-bottom: 1rem;
        }

        .audit-card.horarios { border-left-color: #0dcaf0; }
        .audit-card.reservas { border-left-color: #198754; }
        .audit-card.cancelacion { border-left-color: #dc3545; }
        .badge-admin {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #fff;
        }

        .json-viewer {
            background: rgba(0, 0, 0, 0.35);
            padding: 1rem;
            border-radius: 8px;
            font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
            font-size: 0.84rem;
            color: #dbeafe;
            overflow-x: auto;
            border: 1px solid rgba(148, 163, 184, 0.22);
        }

        .diff-removed { background: rgba(239, 68, 68, 0.15); color: #fecaca; }
        .diff-added { background: rgba(34, 197, 94, 0.15); color: #bbf7d0; }

        .form-label {
            color: var(--text-strong);
            font-weight: 600;
        }

        .form-select,
        .form-control {
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 215, 0, 0.28);
            color: #ffffff;
        }

        .form-select:focus,
        .form-control:focus {
            background: rgba(255, 255, 255, 0.12);
            color: #fff;
            border-color: var(--accent-gold);
            box-shadow: 0 0 0 0.2rem rgba(255, 215, 0, 0.2);
        }

        .form-select option {
            background: #111827;
            color: #f8fafc;
        }

        .toolbar-card {
            background: linear-gradient(135deg, rgba(255, 215, 0, 0.06), rgba(0, 212, 255, 0.05));
            border: 1px solid rgba(255, 215, 0, 0.25);
        }

        .search-hint {
            color: #e2e8f0;
            font-size: 0.92rem;
        }

        .result-meta {
            color: #93c5fd;
            font-weight: 600;
        }

        .btn-primary {
            border: none;
            background: linear-gradient(135deg, #3b82f6, #2563eb);
        }

        .btn-success {
            border: none;
            background: linear-gradient(135deg, #16a34a, #15803d);
        }

        .btn-outline-light {
            border-color: rgba(248, 250, 252, 0.35);
            color: #f8fafc;
        }

        .btn-outline-light:hover {
            background: rgba(248, 250, 252, 0.16);
            color: #fff;
        }

        .card .border.rounded {
            border-color: rgba(148, 163, 184, 0.35) !important;
            background: rgba(148, 163, 184, 0.08);
            color: #e2e8f0;
        }

        .swal2-popup {
            color: #111827 !important;
        }

        .swal2-title,
        .swal2-html-container {
            color: #111827 !important;
        }

        .alert-info {
            background: rgba(14, 165, 233, 0.12);
            border: 1px solid rgba(14, 165, 233, 0.35);
            color: #bae6fd;
        }

        .stats-title {
            font-size: 1.85rem;
            font-weight: 700;
        }

        @media (max-width: 768px) {
            .stats-title {
                font-size: 1.45rem;
            }
        }
    </style>
</head>
<body>
    <div class="container-fluid py-4 panel-shell">
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <h2 class="stats-title"><i class="bi bi-clipboard-data"></i> Auditoría del Sistema</h2>
                    <div>
                        <span class="badge bg-primary me-2"><?php echo htmlspecialchars($admin_nombre); ?></span>
                        <a href="../admin.php" class="btn btn-secondary">
                            <i class="bi bi-arrow-left"></i> Volver al Dashboard
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Resumen -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h3 id="total-cambios-horarios" class="text-primary">--</h3>
                        <p class="mb-0">Cambios de Horarios</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h3 id="total-acciones-reservas" class="text-success">--</h3>
                        <p class="mb-0">Acciones en Reservas</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h3 id="total-canceladas" class="text-danger">--</h3>
                        <p class="mb-0">Reservas Canceladas</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h3 id="total-notificaciones" class="text-info">--</h3>
                        <p class="mb-0">Notificaciones Enviadas</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filtros -->
        <div class="card toolbar-card mb-4">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Tipo de Auditoría</label>
                        <select id="tipo-auditoria" class="form-select">
                            <option value="horarios">Cambios de Horarios</option>
                            <option value="reservas">Acciones en Reservas</option>
                            <option value="admin">Mis Acciones</option>
                            <option value="resumen">Resumen General</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Límite de Registros</label>
                        <select id="limite" class="form-select">
                            <option value="25">25</option>
                            <option value="50" selected>50</option>
                            <option value="100">100</option>
                            <option value="200">200</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Texto</label>
                        <input id="filtro-texto" type="text" class="form-control" placeholder="Admin, acción, IP, motivo...">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Desde</label>
                        <input id="fecha-inicio" type="date" class="form-control">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Hasta</label>
                        <input id="fecha-fin" type="date" class="form-control">
                    </div>
                </div>

                <div class="row g-3 mt-1 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label">ID Reserva (opcional)</label>
                        <input id="filtro-reserva-id" type="number" min="1" class="form-control" placeholder="Solo para tipo Reservas">
                    </div>
                    <div class="col-md-3 d-grid">
                        <button class="btn btn-primary" onclick="cargarAuditoria()">
                            <i class="bi bi-search me-1"></i> Buscar
                        </button>
                    </div>
                    <div class="col-md-2 d-grid">
                        <button class="btn btn-outline-light" onclick="limpiarFiltrosAuditoria()">
                            <i class="bi bi-eraser me-1"></i> Limpiar
                        </button>
                    </div>
                    <div class="col-md-2 d-grid">
                        <button class="btn btn-success" onclick="exportarCSV()">
                            <i class="bi bi-file-earmark-excel me-1"></i> Exportar
                        </button>
                    </div>
                    <div class="col-md-2">
                        <div id="info-resultados" class="result-meta">Sin búsqueda</div>
                    </div>
                </div>

                <div class="mt-3 search-hint">
                    Usa texto y fechas para filtrar. Si eliges "Reservas", también puedes buscar por ID de reserva.
                </div>
            </div>
        </div>

        <!-- Resultados -->
        <div id="resultados-auditoria">
            <div class="text-center py-5">
                <i class="bi bi-search text-muted" style="font-size: 3rem;"></i>
                <p class="text-muted">Selecciona un tipo de auditoría y haz clic en Buscar</p>
            </div>
        </div>
    </div>

    <script src="../public/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        const AUDITORIA_API = '../app/api/auditoria.php';
        let ultimoResultadoAuditoria = null;
        let ultimoTipoAuditoria = null;

        function escaparHtml(texto) {
            return String(texto ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function parsearJSONSeguro(valor) {
            if (!valor) return {};
            if (typeof valor === 'object') return valor;
            try {
                return JSON.parse(valor);
            } catch (e) {
                return {};
            }
        }

        async function fetchJsonSeguro(url) {
            const response = await fetch(url, { credentials: 'same-origin', cache: 'no-cache' });
            const raw = await response.text();
            let data;
            try {
                data = JSON.parse(raw);
            } catch (e) {
                throw new Error(raw || `Respuesta inválida (${response.status})`);
            }
            if (!response.ok) {
                throw new Error(data.message || `Error HTTP ${response.status}`);
            }
            return data;
        }

        function obtenerParametrosBusqueda() {
            const tipo = document.getElementById('tipo-auditoria').value;
            const limite = document.getElementById('limite').value;
            const texto = (document.getElementById('filtro-texto').value || '').trim();
            const fechaInicio = document.getElementById('fecha-inicio').value || '';
            const fechaFin = document.getElementById('fecha-fin').value || '';
            const reservaIdRaw = document.getElementById('filtro-reserva-id').value || '';
            const reservaId = reservaIdRaw ? parseInt(reservaIdRaw, 10) : null;

            return {
                tipo,
                limite,
                texto,
                fechaInicio,
                fechaFin,
                reservaId: Number.isInteger(reservaId) && reservaId > 0 ? reservaId : null
            };
        }

        function actualizarInfoResultados(texto) {
            const el = document.getElementById('info-resultados');
            if (el) {
                el.textContent = texto;
            }
        }

        function limpiarFiltrosAuditoria() {
            document.getElementById('filtro-texto').value = '';
            document.getElementById('fecha-inicio').value = '';
            document.getElementById('fecha-fin').value = '';
            document.getElementById('filtro-reserva-id').value = '';
            actualizarInfoResultados('Filtros limpiados');
            cargarAuditoria();
        }
        window.limpiarFiltrosAuditoria = limpiarFiltrosAuditoria;

        function activarEventosFiltros() {
            const camposEnter = ['filtro-texto', 'filtro-reserva-id', 'fecha-inicio', 'fecha-fin'];
            camposEnter.forEach((id) => {
                const el = document.getElementById(id);
                if (!el) return;
                el.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        cargarAuditoria();
                    }
                });
            });
            ['tipo-auditoria', 'limite'].forEach((id) => {
                const el = document.getElementById(id);
                if (!el) return;
                el.addEventListener('change', () => {
                    if (id === 'tipo-auditoria') {
                        actualizarEstadoFiltroReserva();
                    }
                    cargarAuditoria();
                });
            });
        }

        function actualizarEstadoFiltroReserva() {
            const tipo = document.getElementById('tipo-auditoria').value;
            const input = document.getElementById('filtro-reserva-id');
            if (!input) return;
            const habilitado = tipo === 'reservas';
            input.disabled = !habilitado;
            input.placeholder = habilitado ? 'Solo para tipo Reservas' : 'Disponible al elegir "Reservas"';
            if (!habilitado) {
                input.value = '';
            }
        }

        // Cargar resumen al inicio
        cargarResumen();

        async function cargarResumen() {
            try {
                const data = await fetchJsonSeguro(`${AUDITORIA_API}?tipo=resumen`);
                
                if (data.success) {
                    document.getElementById('total-cambios-horarios').textContent = data.resumen.total_cambios_horarios || 0;
                    document.getElementById('total-acciones-reservas').textContent = data.resumen.total_acciones_reservas || 0;
                    document.getElementById('total-canceladas').textContent = data.resumen.total_reservas_canceladas || 0;
                    document.getElementById('total-notificaciones').textContent = data.resumen.total_notificaciones || 0;
                }
            } catch (error) {
                console.error('Error cargando resumen:', error);
            }
        }

        async function cargarAuditoria() {
            const filtros = obtenerParametrosBusqueda();

            if (filtros.fechaInicio && filtros.fechaFin && filtros.fechaInicio > filtros.fechaFin) {
                Swal.fire('Fechas inválidas', 'La fecha "Desde" no puede ser mayor que "Hasta".', 'warning');
                return;
            }
            
            Swal.fire({
                title: 'Cargando...',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            try {
                const params = new URLSearchParams();
                params.set('tipo', filtros.tipo);
                params.set('limite', filtros.limite);
                if (filtros.texto) params.set('q', filtros.texto);
                if (filtros.fechaInicio) params.set('fecha_inicio', filtros.fechaInicio);
                if (filtros.fechaFin) params.set('fecha_fin', filtros.fechaFin);
                if (filtros.reservaId && filtros.tipo === 'reservas') {
                    params.set('reserva_id', String(filtros.reservaId));
                }

                const data = await fetchJsonSeguro(`${AUDITORIA_API}?${params.toString()}`);
                
                Swal.close();
                
                if (data.success) {
                    ultimoResultadoAuditoria = data;
                    ultimoTipoAuditoria = filtros.tipo;
                    mostrarResultados(data, filtros.tipo);
                    const total = Number(data.total ?? (Array.isArray(data.datos) ? data.datos.length : 0));
                    actualizarInfoResultados(`${total} registro(s) encontrado(s)`);
                    if (data.warning) {
                        Swal.fire({
                            icon: 'warning',
                            title: 'Advertencia',
                            text: data.warning,
                            timer: 3500,
                            showConfirmButton: false
                        });
                    }
                } else {
                    Swal.fire('Error', data.message, 'error');
                }
            } catch (error) {
                actualizarInfoResultados('Error en búsqueda');
                Swal.fire('Error', error.message || 'No se pudo cargar la auditoría', 'error');
                console.error(error);
            }
        }

        function mostrarResultados(data, tipo) {
            const container = document.getElementById('resultados-auditoria');

            if (tipo === 'resumen') {
                const r = data.resumen || {};
                container.innerHTML = `
                    <div class="card">
                        <div class="card-body">
                            <h5 class="mb-3"><i class="bi bi-bar-chart-line"></i> Resumen General</h5>
                            <div class="row g-3">
                                <div class="col-md-3"><div class="border rounded p-3 text-center"><h4>${r.total_cambios_horarios || 0}</h4><small>Cambios horarios</small></div></div>
                                <div class="col-md-3"><div class="border rounded p-3 text-center"><h4>${r.total_acciones_reservas || 0}</h4><small>Acciones reservas</small></div></div>
                                <div class="col-md-3"><div class="border rounded p-3 text-center"><h4>${r.total_reservas_canceladas || 0}</h4><small>Reservas canceladas</small></div></div>
                                <div class="col-md-3"><div class="border rounded p-3 text-center"><h4>${r.total_notificaciones || 0}</h4><small>Notificaciones</small></div></div>
                            </div>
                        </div>
                    </div>
                `;
                return;
            }

            if (!data.datos || data.datos.length === 0) {
                container.innerHTML = `
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> No se encontraron registros
                    </div>
                `;
                return;
            }
            
            let html = '';
            
            if (tipo === 'horarios') {
                html = data.datos.map(item => `
                    <div class="card audit-card horarios">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h5 class="card-title">
                                        <i class="bi bi-clock-history"></i> Cambio de Horarios
                                    </h5>
                                    <p class="text-muted mb-2">
                                        <span class="badge badge-admin">${escaparHtml(item.admin)}</span>
                                        <span class="ms-2"><i class="bi bi-calendar"></i> ${escaparHtml(item.fecha || '-')}</span>
                                        <span class="ms-2"><i class="bi bi-geo-alt"></i> ${escaparHtml(item.ip || '-')}</span>
                                    </p>
                                </div>
                                <div class="text-end">
                                    ${item.reservas_afectadas > 0 ? `
                                        <span class="badge bg-danger">${item.reservas_canceladas} Canceladas</span>
                                        <span class="badge bg-info">${item.notificaciones_enviadas} WhatsApp</span>
                                    ` : '<span class="badge bg-success">Sin afectaciones</span>'}
                                </div>
                            </div>
                            
                            ${item.observaciones ? `
                                <div class="alert alert-warning mt-3 mb-0">
                                    <i class="bi bi-exclamation-triangle"></i> ${escaparHtml(item.observaciones)}
                                </div>
                            ` : ''}
                            
                            <div class="mt-3">
                                <button class="btn btn-sm btn-outline-primary" onclick="verDetalles(${item.id}, 'horarios')">
                                    <i class="bi bi-eye"></i> Ver Detalles
                                </button>
                            </div>
                        </div>
                    </div>
                `).join('');
            } else if (tipo === 'reservas') {
                html = data.datos.map(item => `
                    <div class="card audit-card reservas">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h5 class="card-title">
                                        <i class="bi bi-journal-check"></i> Reserva #${item.reserva_id}
                                    </h5>
                                    <p class="text-muted mb-2">
                                        <span class="badge badge-admin">${escaparHtml(item.admin || 'Sistema')}</span>
                                        <span class="ms-2"><i class="bi bi-activity"></i> ${escaparHtml(item.accion || 'sin acción')}</span>
                                        <span class="ms-2"><i class="bi bi-calendar"></i> ${escaparHtml(item.fecha || '')}</span>
                                        <span class="ms-2"><i class="bi bi-geo-alt"></i> ${escaparHtml(item.ip || '-')}</span>
                                    </p>
                                    <p class="mb-1">
                                        <strong>Estado:</strong> 
                                        ${escaparHtml(item.estado_anterior || 'N/A')} 
                                        <i class="bi bi-arrow-right"></i> 
                                        ${escaparHtml(item.estado_nuevo || 'N/A')}
                                    </p>
                                    ${item.motivo ? `<p class="mb-0"><strong>Motivo:</strong> ${escaparHtml(item.motivo)}</p>` : ''}
                                </div>
                                <div class="text-end">
                                    <button class="btn btn-sm btn-outline-primary" onclick="verDetalles(${item.id}, 'reservas')">
                                        <i class="bi bi-eye"></i> Ver Detalles
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                `).join('');
            } else if (tipo === 'admin') {
                html = data.datos.map(item => `
                    <div class="card audit-card ${item.tipo === 'horarios' ? 'horarios' : 'reservas'}">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <span class="badge ${item.tipo === 'horarios' ? 'bg-info' : 'bg-success'}">
                                        ${escaparHtml((item.tipo || '').toUpperCase())}
                                    </span>
                                    <strong class="ms-2">${escaparHtml(item.accion || '-')}</strong>
                                </div>
                                <small class="text-muted">${escaparHtml(item.fecha || '-')}</small>
                            </div>
                            ${item.observaciones ? `<p class="mb-0 mt-2">${escaparHtml(item.observaciones)}</p>` : ''}
                            ${item.reservas_afectadas ? `
                                <span class="badge bg-warning text-dark mt-2">${item.reservas_afectadas} reservas afectadas</span>
                            ` : ''}
                        </div>
                    </div>
                `).join('');
            }
            
            container.innerHTML = html;
        }

        function verDetalles(id, tipo) {
            fetchJsonSeguro(`${AUDITORIA_API}?tipo=${encodeURIComponent(tipo)}&id=${encodeURIComponent(id)}`)
                .then(data => {
                    if (!data.success || !data.datos || data.datos.length === 0) {
                        Swal.fire('Error', 'No se encontraron detalles', 'error');
                        return;
                    }
                    
                    const item = data.datos[0];
                    let html = '';
                    
                    if (tipo === 'horarios') {
                        const antes = parsearJSONSeguro(item.configuracion_anterior);
                        const despues = parsearJSONSeguro(item.configuracion_nueva);
                        
                        html = `
                            <div class="text-start">
                                <h6><i class="bi bi-person-badge"></i> Administrador: <strong>${item.admin}</strong></h6>
                                <p><i class="bi bi-calendar"></i> Fecha: ${item.fecha}</p>
                                <p><i class="bi bi-geo-alt"></i> IP: ${item.ip}</p>
                                <hr>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <h6 class="text-danger"><i class="bi bi-dash-circle"></i> Configuración Anterior</h6>
                                        <div class="json-viewer diff-removed">
                                            <pre>${JSON.stringify(antes, null, 2)}</pre>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <h6 class="text-success"><i class="bi bi-plus-circle"></i> Configuración Nueva</h6>
                                        <div class="json-viewer diff-added">
                                            <pre>${JSON.stringify(despues, null, 2)}</pre>
                                        </div>
                                    </div>
                                </div>
                                
                                ${item.reservas_afectadas > 0 ? `
                                    <hr>
                                    <div class="alert alert-warning">
                                        <strong><i class="bi bi-exclamation-triangle"></i> Impacto:</strong><br>
                                        • ${item.reservas_canceladas} reservas canceladas<br>
                                        • ${item.notificaciones_enviadas} notificaciones WhatsApp enviadas
                                    </div>
                                ` : ''}
                                
                                ${item.observaciones ? `
                                    <div class="alert alert-info">
                                        <strong>Observaciones:</strong><br>
                                        ${escaparHtml(item.observaciones)}
                                    </div>
                                ` : ''}
                            </div>
                        `;
                    } else if (tipo === 'reservas') {
                        const antes = parsearJSONSeguro(item.datos_anteriores);
                        const despues = parsearJSONSeguro(item.datos_nuevos);
                        html = `
                            <div class="text-start">
                                <h6><i class="bi bi-receipt"></i> Reserva: <strong>#${item.reserva_id}</strong></h6>
                                <p><i class="bi bi-person-badge"></i> Admin: ${escaparHtml(item.admin || 'Sistema')}</p>
                                <p><i class="bi bi-activity"></i> Acción: ${escaparHtml(item.accion || '')}</p>
                                <p><i class="bi bi-calendar"></i> Fecha: ${escaparHtml(item.fecha || '')}</p>
                                <p><i class="bi bi-arrow-left-right"></i> Estado: ${escaparHtml(item.estado_anterior || 'N/A')} → ${escaparHtml(item.estado_nuevo || 'N/A')}</p>
                                ${item.motivo ? `<p><i class="bi bi-chat-left-text"></i> Motivo: ${escaparHtml(item.motivo)}</p>` : ''}
                                <hr>
                                <div class="row">
                                    <div class="col-md-6">
                                        <h6 class="text-danger"><i class="bi bi-dash-circle"></i> Datos Anteriores</h6>
                                        <div class="json-viewer diff-removed">
                                            <pre>${escaparHtml(JSON.stringify(antes, null, 2))}</pre>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <h6 class="text-success"><i class="bi bi-plus-circle"></i> Datos Nuevos</h6>
                                        <div class="json-viewer diff-added">
                                            <pre>${escaparHtml(JSON.stringify(despues, null, 2))}</pre>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        `;
                    }
                    
                    Swal.fire({
                        title: 'Detalles de Auditoría',
                        html: html,
                        width: '800px',
                        showCloseButton: true,
                        showConfirmButton: false
                    });
                })
                .catch(err => {
                    Swal.fire('Error', err.message || 'No se pudieron cargar los detalles', 'error');
                    console.error(err);
                });
        }

        function exportarCSV() {
            if (!ultimoResultadoAuditoria || !Array.isArray(ultimoResultadoAuditoria.datos) || ultimoResultadoAuditoria.datos.length === 0) {
                Swal.fire('Sin datos', 'Primero carga una auditoría con resultados', 'warning');
                return;
            }

            const tipo = ultimoTipoAuditoria || 'auditoria';
            const datos = ultimoResultadoAuditoria.datos;
            const headers = Object.keys(datos[0]);
            const escapeCsv = (v) => {
                const text = String(v ?? '').replace(/"/g, '""');
                return `"${text}"`;
            };

            const rows = [headers.map(escapeCsv).join(',')];
            datos.forEach(item => {
                const line = headers.map(h => {
                    const value = typeof item[h] === 'object' ? JSON.stringify(item[h]) : item[h];
                    return escapeCsv(value);
                }).join(',');
                rows.push(line);
            });

            const blob = new Blob([rows.join('\n')], { type: 'text/csv;charset=utf-8;' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `auditoria_${tipo}_${new Date().toISOString().slice(0, 10)}.csv`;
            document.body.appendChild(a);
            a.click();
            a.remove();
            URL.revokeObjectURL(url);
        }

        document.addEventListener('DOMContentLoaded', () => {
            activarEventosFiltros();
            actualizarEstadoFiltroReserva();
            cargarAuditoria();
        });
    </script>
</body>
</html>
