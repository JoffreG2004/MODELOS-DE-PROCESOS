/**
 * Gestión de Reservas de Zonas Completas
 * Permite a los clientes reservar salas/zonas enteras del restaurante
 */

class ReservaZonas {
    constructor() {
        this.zonasSeleccionadas = new Set();
        this.horarioZona = { inicio: '11:00', fin: '22:00' };
        this.precios = {
            1: 60,
            2: 100,
            3: 120,
            4: 140
        };
        this.nombresZonas = {
            'interior': '🍽️ Salón Principal',
            'terraza': '🌿 Terraza',
            'vip': '👑 Área VIP',
            'bar': '🍸 Bar & Lounge'
        };
        this.aforoPorZona = {};
        this.aforoCargado = false;
    }

    obtenerFechaLocalISO(fecha = new Date()) {
        const year = fecha.getFullYear();
        const month = String(fecha.getMonth() + 1).padStart(2, '0');
        const day = String(fecha.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    }

    obtenerFechaMinima() {
        const fechaLocal = this.obtenerFechaLocalISO();
        const fechaServidor = typeof window !== 'undefined' ? window.FECHA_HOY_SERVIDOR : null;
        if (fechaServidor && /^\d{4}-\d{2}-\d{2}$/.test(fechaServidor)) {
            return fechaLocal < fechaServidor ? fechaLocal : fechaServidor;
        }
        return fechaLocal;
    }

    obtenerFechaMaxima() {
        const fechaServidorMax = typeof window !== 'undefined' ? window.FECHA_MAX_RESERVA : null;
        if (fechaServidorMax && /^\d{4}-\d{2}-\d{2}$/.test(fechaServidorMax)) {
            return fechaServidorMax;
        }
        const fecha = new Date();
        fecha.setDate(fecha.getDate() + 14);
        return this.obtenerFechaLocalISO(fecha);
    }

    async actualizarHorarioZona(fecha) {
        try {
            const response = await fetch('app/api/validar_horario_reserva.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ fecha, hora: '12:00' })
            });
            const data = await response.json();
            if (data.horario_disponible) {
                this.horarioZona = {
                    inicio: data.horario_disponible.inicio,
                    fin: data.horario_disponible.fin
                };
            }
        } catch (e) {
            // Mantener horario por defecto si falla
        }

        const timeInput = document.getElementById('horaReservaZona');
        if (timeInput) {
            timeInput.min = this.horarioZona.inicio;
            timeInput.max = this.horarioZona.fin;
            if (timeInput.value < this.horarioZona.inicio || timeInput.value > this.horarioZona.fin) {
                timeInput.value = this.horarioZona.inicio;
            }
        }
    }

    mostrarModalReservaZona() {
        const zonasHTML = Object.entries(this.nombresZonas).map(([key, nombre]) => `
            <div class="form-check zona-check-item mb-3 p-3 border rounded" style="cursor: pointer;" 
                 onclick="reservaZonas.toggleZona('${key}')" id="zona-item-${key}">
                <input class="form-check-input zona-checkbox" type="checkbox" 
                       value="${key}" id="zona-${key}">
                <label class="form-check-label w-100" for="zona-${key}" style="cursor: pointer;">
                    <h5 class="mb-1">${nombre}</h5>
                    <small class="text-muted">Incluye todas las mesas de esta área</small>
                </label>
            </div>
        `).join('');

        // Fecha mínima local: hoy (evita desfase por UTC de toISOString)
        const minDate = this.obtenerFechaMinima();
        const maxDate = this.obtenerFechaMaxima();

        Swal.fire({
            title: '🎉 Reserva de Zona Completa',
            html: `
                <style>
                    .swal2-popup.reserva-zona-modal {
                        width: min(700px, calc(100vw - 24px)) !important;
                        max-width: 700px !important;
                        margin: 0 auto !important;
                        padding: clamp(14px, 2vw, 22px) !important;
                    }

                    .swal2-popup.reserva-zona-modal .swal2-html-container {
                        margin: 0 !important;
                        max-height: min(72vh, 650px) !important;
                        overflow-y: auto !important;
                        overflow-x: hidden !important;
                        padding-right: 6px !important;
                    }

                    .swal2-popup.reserva-zona-modal .zona-check-item {
                        display: flex !important;
                        align-items: flex-start !important;
                        gap: 10px !important;
                        overflow: visible !important;
                    }

                    .swal2-popup.reserva-zona-modal .zona-checkbox {
                        position: static !important;
                        opacity: 1 !important;
                        width: 18px !important;
                        height: 18px !important;
                        margin: 5px 0 0 0 !important;
                        flex: 0 0 auto !important;
                        accent-color: #d4af37 !important;
                        pointer-events: none !important;
                    }

                    .swal2-popup.reserva-zona-modal .zona-check-item .form-check-label {
                        padding-left: 0 !important;
                        display: block !important;
                    }

                    .swal2-popup.reserva-zona-modal .zona-check-item .form-check-label::before,
                    .swal2-popup.reserva-zona-modal .zona-check-item .form-check-label::after {
                        content: none !important;
                    }

                    @media (max-width: 768px) {
                        .swal2-popup.reserva-zona-modal .swal2-title {
                            font-size: 1.85rem !important;
                            line-height: 1.2 !important;
                        }
                    }
                </style>

                <div class="text-start reserva-zona-contenido">
                    <div class="alert alert-info mb-4">
                        <strong>💡 Precios de reserva por zonas:</strong><br>
                        <small style="line-height: 1.6;">
                        • <strong>1 zona:</strong> $60<br>
                        • <strong>2 zonas:</strong> $100<br>
                        • <strong>3 zonas:</strong> $120<br>
                        • <strong>Todo el establecimiento:</strong> $140
                        </small>
                    </div>

                    <h6 class="mb-3" style="color: var(--text-primary); font-weight: 600;">
                        Selecciona las zonas que deseas reservar:
                    </h6>
                    <div class="zonas-container">
                        ${zonasHTML}
                    </div>

                    <div id="precioSeleccion" class="alert alert-success mt-3" style="display: none;">
                        <strong>💰 Precio total: $<span id="precioTotal">0</span></strong><br>
                        <small><span id="zonasSeleccionadasText"></span></small>
                    </div>

                    <div id="aforoSeleccion" class="alert alert-secondary mt-3">
                        <strong id="aforoTitulo">👥 Aforo máximo</strong>: <span id="aforoTotalSeleccion">-</span> personas<br>
                        <small id="aforoDetalleSeleccion">Selecciona una o más zonas para calcular el aforo.</small>
                    </div>

                    <hr class="my-4">

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">
                                <i class="fas fa-calendar" style="color: var(--accent-color);"></i> 
                                Fecha de Reserva
                            </label>
                            <input type="date" class="form-control" id="fechaReservaZona" 
                                   min="${minDate}" max="${maxDate}" required 
                                   style="color: var(--text-primary) !important;">
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">
                                <i class="fas fa-clock" style="color: var(--accent-color);"></i> 
                                Hora de Reserva
                            </label>
                            <input type="time" class="form-control" id="horaReservaZona" 
                                   value="19:00" min="${this.horarioZona.inicio}" max="${this.horarioZona.fin}" step="60" required
                                   style="color: var(--text-primary) !important;">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">
                            <i class="fas fa-users" style="color: var(--accent-color);"></i> 
                            Número de Personas
                        </label>
                        <input type="number" class="form-control" id="personasReservaZona" 
                               min="1" value="1" required placeholder="Selecciona zonas para calcular aforo"
                               step="1" inputmode="numeric" pattern="\\d*"
                               style="color: var(--text-primary) !important;">
                        <small class="text-muted" id="aforoHint">El aforo se calcula por zonas: sillas disponibles + 10 personas de pie por zona.</small>
                    </div>

                    <div class="alert alert-warning mt-3">
                        <small>
                            <i class="fas fa-info-circle"></i>
                            <strong>Importante:</strong> Las reservas de zona completa requieren confirmación del administrador.
                            Recibirás una notificación una vez aprobada tu solicitud.
                        </small>
                    </div>
                </div>
            `,
            width: '95vw',
            heightAuto: false,
            showCancelButton: true,
            confirmButtonText: '<i class="fas fa-paper-plane"></i> Solicitar Reserva',
            cancelButtonText: '<i class="fas fa-times"></i> Cancelar',
            confirmButtonColor: '#d4af37',
            cancelButtonColor: '#6c757d',
            buttonsStyling: true,
            customClass: {
                popup: 'reserva-zona-modal',
                confirmButton: 'swal2-confirm',
                cancelButton: 'swal2-cancel',
                actions: 'swal2-actions'
            },
            didOpen: () => {
                const dateInput = document.getElementById('fechaReservaZona');
                if (dateInput) {
                    const min = this.obtenerFechaMinima();
                    const max = this.obtenerFechaMaxima();
                    dateInput.min = min;
                    dateInput.max = max;
                    if (!dateInput.value || dateInput.value < min) {
                        dateInput.value = min;
                    } else if (dateInput.value > max) {
                        dateInput.value = max;
                    }
                    this.actualizarHorarioZona(dateInput.value);
                    dateInput.addEventListener('change', () => {
                        this.actualizarHorarioZona(dateInput.value);
                    });
                }
                this.cargarAforoBasePorZona();
            },
            preConfirm: () => {
                if (this.zonasSeleccionadas.size === 0) {
                    Swal.showValidationMessage('❌ Debes seleccionar al menos una zona');
                    return false;
                }

                const fecha = document.getElementById('fechaReservaZona').value;
                const hora = document.getElementById('horaReservaZona').value;
                const personasRaw = document.getElementById('personasReservaZona').value.trim();
                const personas = parseInt(personasRaw, 10);

                if (!fecha) {
                    Swal.showValidationMessage('📅 Por favor selecciona una fecha');
                    return false;
                }

                if (!hora) {
                    Swal.showValidationMessage('⏰ Por favor selecciona una hora');
                    return false;
                }

                if (!personasRaw) {
                    Swal.showValidationMessage('👥 Por favor ingresa el número de personas');
                    return false;
                }

                if (!/^\d+$/.test(personasRaw)) {
                    Swal.showValidationMessage('👥 El número de personas debe ser un entero sin puntos ni comas');
                    return false;
                }

                if (!personas || personas < 1) {
                    Swal.showValidationMessage('👥 Debes ingresar al menos 1 persona');
                    return false;
                }

                // Validar que la fecha no sea pasada (se permite hoy o futuro)
                const today = this.obtenerFechaMinima();
                if (fecha < today) {
                    Swal.showValidationMessage('📅 No puedes seleccionar una fecha pasada');
                    return false;
                }

                const maxDate = this.obtenerFechaMaxima();
                if (fecha > maxDate) {
                    Swal.showValidationMessage(`📅 Solo puedes reservar hasta ${maxDate}`);
                    return false;
                }

                // Validar horario de servicio (dinamico)
                const inicio = this.horarioZona.inicio;
                const fin = this.horarioZona.fin;
                if (hora < inicio || hora > fin) {
                    Swal.showValidationMessage(`⏰ Horario disponible: ${inicio} - ${fin}`);
                    return false;
                }

                if (this.aforoCargado) {
                    const aforoSeleccion = this.calcularAforoSeleccionado();
                    if (aforoSeleccion.aforoTotal <= 0) {
                        Swal.showValidationMessage('👥 No hay aforo disponible en las zonas seleccionadas');
                        return false;
                    }
                    if (personas > aforoSeleccion.aforoTotal) {
                        Swal.showValidationMessage(
                            `👥 Capacidad insuficiente: máximo ${aforoSeleccion.aforoTotal} personas (${aforoSeleccion.totalSillas} sillas + ${aforoSeleccion.totalParados} de pie)`
                        );
                        return false;
                    }
                }

                return {
                    zonas: Array.from(this.zonasSeleccionadas),
                    fecha_reserva: fecha,
                    hora_reserva: hora,
                    numero_personas: personas
                };
            }
        }).then((result) => {
            if (result.isConfirmed && result.value) {
                this.crearReservaZona(result.value);
            }
        });

        // Inicializar
        this.zonasSeleccionadas.clear();
        this.actualizarPrecio();
    }

    toggleZona(zonaKey) {
        const checkbox = document.getElementById(`zona-${zonaKey}`);
        const zonaItem = document.getElementById(`zona-item-${zonaKey}`);
        
        if (!checkbox) return;

        checkbox.checked = !checkbox.checked;

        if (checkbox.checked) {
            this.zonasSeleccionadas.add(zonaKey);
            if (zonaItem) {
                zonaItem.classList.add('selected');
                zonaItem.style.borderColor = 'var(--accent-color)';
                zonaItem.style.backgroundColor = 'rgba(212, 175, 55, 0.1)';
                zonaItem.style.transform = 'translateY(-1px)';
                zonaItem.style.boxShadow = '0 4px 12px rgba(212, 175, 55, 0.3)';
            }
        } else {
            this.zonasSeleccionadas.delete(zonaKey);
            if (zonaItem) {
                zonaItem.classList.remove('selected');
                zonaItem.style.borderColor = '#e0e0e0';
                zonaItem.style.backgroundColor = 'var(--surface-elevated)';
                zonaItem.style.transform = 'none';
                zonaItem.style.boxShadow = 'none';
            }
        }

        this.actualizarPrecio();
    }

    async cargarAforoBasePorZona() {
        const base = {};
        Object.keys(this.nombresZonas).forEach((zona) => {
            base[zona] = { mesas: 0, sillas: 0, parados: 0, aforoTotal: 0 };
        });

        this.aforoPorZona = base;
        this.aforoCargado = false;
        this.actualizarAforoSeleccionado();

        try {
            const response = await fetch('app/api/mesas_estado.php', { cache: 'no-store' });
            const data = await response.json();
            if (!data.success || !Array.isArray(data.mesas)) {
                return;
            }

            data.mesas.forEach((mesa) => {
                const zona = mesa.ubicacion || mesa.tipo;
                if (!base[zona]) {
                    return;
                }

                const estadoOriginal = String(mesa.estado_original || mesa.estado || '').toLowerCase();
                if (estadoOriginal !== 'disponible') {
                    return;
                }

                const capacidadMesa = parseInt(mesa.capacidad_maxima ?? mesa.capacidad ?? 0, 10) || 0;
                base[zona].mesas += 1;
                base[zona].sillas += capacidadMesa;
            });

            Object.keys(base).forEach((zona) => {
                base[zona].parados = base[zona].mesas > 0 ? 10 : 0;
                base[zona].aforoTotal = base[zona].sillas + base[zona].parados;
            });

            this.aforoPorZona = base;
            this.aforoCargado = true;
            this.actualizarAforoSeleccionado();
        } catch (error) {
            this.aforoCargado = false;
            this.actualizarAforoSeleccionado();
        }
    }

    calcularAforoSeleccionado() {
        const seleccion = Array.from(this.zonasSeleccionadas);
        const detalle = seleccion.map((zona) => {
            const aforoZona = this.aforoPorZona[zona] || { mesas: 0, sillas: 0, parados: 0, aforoTotal: 0 };
            return {
                zona,
                nombre: this.nombresZonas[zona] || zona,
                mesas: aforoZona.mesas,
                sillas: aforoZona.sillas,
                parados: aforoZona.parados,
                aforoTotal: aforoZona.aforoTotal
            };
        });

        const totalSillas = detalle.reduce((sum, zona) => sum + zona.sillas, 0);
        const totalParados = detalle.reduce((sum, zona) => sum + zona.parados, 0);
        const aforoTotal = detalle.reduce((sum, zona) => sum + zona.aforoTotal, 0);

        return { detalle, totalSillas, totalParados, aforoTotal };
    }

    actualizarAforoSeleccionado() {
        const aforoDiv = document.getElementById('aforoSeleccion');
        const aforoTitulo = document.getElementById('aforoTitulo');
        const aforoTotalSpan = document.getElementById('aforoTotalSeleccion');
        const aforoDetalle = document.getElementById('aforoDetalleSeleccion');
        const aforoHint = document.getElementById('aforoHint');
        const personasInput = document.getElementById('personasReservaZona');
        const cantidad = this.zonasSeleccionadas.size;

        if (cantidad === 0) {
            if (aforoDiv) {
                aforoDiv.style.display = 'block';
            }
            if (aforoTitulo) {
                aforoTitulo.textContent = '👥 Aforo máximo';
            }
            if (aforoTotalSpan) {
                aforoTotalSpan.textContent = '-';
            }
            if (aforoDetalle) {
                aforoDetalle.textContent = 'Selecciona una o más zonas para calcular el aforo.';
            }
            if (personasInput) {
                personasInput.removeAttribute('max');
                personasInput.placeholder = 'Selecciona zonas para calcular aforo';
            }
            if (aforoHint) {
                aforoHint.textContent = 'El aforo se calcula por zonas: sillas disponibles + 10 personas de pie por zona.';
            }
            return;
        }

        if (aforoDiv) {
            aforoDiv.style.display = 'block';
        }

        if (!this.aforoCargado) {
            if (aforoTitulo) {
                aforoTitulo.textContent = '👥 Aforo máximo';
            }
            if (aforoTotalSpan) {
                aforoTotalSpan.textContent = '...';
            }
            if (aforoDetalle) {
                aforoDetalle.textContent = 'Calculando aforo dinámico...';
            }
            if (aforoHint) {
                aforoHint.textContent = 'Calculando aforo dinámico de las zonas seleccionadas...';
            }
            if (personasInput) {
                personasInput.removeAttribute('max');
            }
            return;
        }

        const aforoSeleccion = this.calcularAforoSeleccionado();
        const detalleTexto = aforoSeleccion.detalle
            .map((zona) => `${zona.nombre}: ${zona.aforoTotal} (${zona.sillas} sillas + ${zona.parados} de pie)`)
            .join(' | ');

        if (aforoTitulo) {
            if (cantidad === 1 && aforoSeleccion.detalle[0]) {
                aforoTitulo.textContent = `👥 Aforo máximo de ${aforoSeleccion.detalle[0].nombre}`;
            } else if (cantidad === 4) {
                aforoTitulo.textContent = '👥 Aforo máximo de todo el establecimiento';
            } else {
                aforoTitulo.textContent = `👥 Aforo máximo entre ${cantidad} zonas seleccionadas`;
            }
        }
        if (aforoTotalSpan) {
            aforoTotalSpan.textContent = String(aforoSeleccion.aforoTotal);
        }
        if (aforoDetalle) {
            aforoDetalle.textContent = detalleTexto || 'Sin datos de aforo';
        }
        if (personasInput) {
            personasInput.max = String(aforoSeleccion.aforoTotal);
            personasInput.placeholder = `Máximo ${aforoSeleccion.aforoTotal} personas`;
        }
        if (aforoHint) {
            aforoHint.textContent = `Aforo calculado: ${aforoSeleccion.totalSillas} sillas + ${aforoSeleccion.totalParados} de pie = ${aforoSeleccion.aforoTotal} personas.`;
        }
    }

    actualizarPrecio() {
        const cantidad = this.zonasSeleccionadas.size;
        const precio = this.precios[cantidad] || 0;

        const precioDiv = document.getElementById('precioSeleccion');
        const precioSpan = document.getElementById('precioTotal');
        const zonasText = document.getElementById('zonasSeleccionadasText');

        if (cantidad > 0 && precioDiv && precioSpan && zonasText) {
            precioDiv.style.display = 'block';
            precioSpan.textContent = precio;

            const nombresSeleccionados = Array.from(this.zonasSeleccionadas)
                .map(z => this.nombresZonas[z])
                .join(', ');
            
            let descripcion;
            if (cantidad === 1) {
                descripcion = `1 zona seleccionada: ${nombresSeleccionados}`;
            } else if (cantidad === 4) {
                descripcion = `¡Todo el establecimiento reservado! (${cantidad} zonas)`;
            } else {
                descripcion = `${cantidad} zonas seleccionadas: ${nombresSeleccionados}`;
            }
            
            zonasText.innerHTML = `<i class="fas fa-check-circle" style="color: #28a745;"></i> ${descripcion}`;
        } else if (precioDiv) {
            precioDiv.style.display = 'none';
        }

        this.actualizarAforoSeleccionado();
    }

    async crearReservaZona(data) {
        try {
            Swal.fire({
                title: '⏳ Procesando...',
                text: 'Enviando tu solicitud de reserva de zona',
                allowOutsideClick: false,
                showConfirmButton: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            const response = await fetch('app/api/crear_reserva_zona.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(data)
            });

            const result = await response.json();

            if (result.success) {
                Swal.fire({
                    icon: 'success',
                    title: '🎉 ¡Solicitud de Reserva Enviada!',
                    html: `
                        <div class="text-start">
                            <div class="alert alert-warning mb-4" style="background-color: rgba(255, 140, 0, 0.1); border-color: rgba(255, 140, 0, 0.3);">
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-clock fa-2x me-3" style="color: #ff8c00;"></i>
                                    <div>
                                        <strong>⏳ Pendiente de Confirmación</strong><br>
                                        <small>El administrador revisará y confirmará tu reserva de zona</small>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <h6 class="text-primary mb-3">📋 Detalles de la Reserva</h6>
                                    <p class="mb-2"><strong>📅 Fecha:</strong> ${result.data.fecha}</p>
                                    <p class="mb-2"><strong>🕐 Hora:</strong> ${result.data.hora}</p>
                                    <p class="mb-3"><strong>👥 Personas:</strong> ${result.data.personas}</p>
                                </div>
                                <div class="col-md-6">
                                    <h6 class="text-success mb-3">💰 Información de Pago</h6>
                                    <p class="mb-2"><strong>🪑 Mesas incluidas:</strong> ${result.data.cantidad_mesas}</p>
                                    <p class="mb-2"><strong>👥 Aforo máximo:</strong> ${(result.data.capacidad && typeof result.data.capacidad.aforo_total !== 'undefined') ? result.data.capacidad.aforo_total : 'N/D'} personas</p>
                                    <p class="mb-3"><strong>� Total a pagar:</strong> 
                                        <span class="badge bg-success fs-6">$${result.data.precio_total}</span>
                                    </p>
                                </div>
                            </div>

                            <div class="mb-3">
                                <h6 class="text-warning mb-2">🏢 Zonas Solicitadas:</h6>
                                <div class="d-flex flex-wrap gap-2">
                                    ${result.data.zonas.map(z => 
                                        `<span class="badge bg-primary px-3 py-2">${z}</span>`
                                    ).join('')}
                                </div>
                            </div>

                            <hr style="border-color: var(--accent-color); opacity: 0.3;">

                            <div class="alert alert-info mt-3" style="background-color: rgba(205, 133, 63, 0.1); border-color: rgba(205, 133, 63, 0.3);">
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-bell fa-lg me-3" style="color: #cd853f;"></i>
                                    <small>
                                        <strong>� Notificación automática:</strong><br>
                                        Recibirás una notificación por WhatsApp cuando el administrador confirme tu reserva
                                    </small>
                                </div>
                            </div>
                        </div>
                    `,
                    width: '700px',
                    confirmButtonText: '<i class="fas fa-list-check"></i> Ver Mis Reservas',
                    showCancelButton: true,
                    cancelButtonText: '<i class="fas fa-home"></i> Ir al Inicio',
                    confirmButtonColor: '#d4af37',
                    cancelButtonColor: '#6c757d',
                    buttonsStyling: true,
                    customClass: {
                        confirmButton: 'swal2-confirm',
                        cancelButton: 'swal2-cancel'
                    }
                }).then((result) => {
                    if (result.isConfirmed) {
                        // Ir a la página de perfil para ver reservas
                        if (typeof location !== 'undefined') {
                            location.href = 'perfil_cliente.php';
                        }
                    } else {
                        // Recargar la página actual
                        if (typeof location !== 'undefined') {
                            location.reload();
                        }
                    }
                });
            } else {
                throw new Error(result.message || 'Error al crear reserva');
            }
        } catch (error) {
            console.error('Error al crear reserva:', error);
            Swal.fire({
                icon: 'error',
                title: '❌ Error al Procesar',
                html: `
                    <div class="text-start">
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>No se pudo procesar tu solicitud</strong>
                        </div>
                        <p class="text-muted mb-3">${error.message || 'Error desconocido'}</p>
                        <small class="text-muted">
                            <i class="fas fa-info-circle me-1"></i>
                            Por favor, intenta nuevamente o contacta al restaurante directamente.
                        </small>
                    </div>
                `,
                confirmButtonText: '<i class="fas fa-rotate-right"></i> Intentar Nuevamente',
                confirmButtonColor: '#d4af37',
                buttonsStyling: true,
                customClass: {
                    confirmButton: 'swal2-confirm'
                }
            });
        }
    }
}

// Inicializar globalmente
window.reservaZonas = new ReservaZonas();

// Función global para abrir el modal
window.mostrarReservaZona = function () {
    console.log('mostrarReservaZona llamada');
    if (window.reservaZonas) {
        window.reservaZonas.mostrarModalReservaZona();
    } else {
        console.error('reservaZonas no está inicializado');
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'El sistema de reservas no está disponible',
                confirmButtonColor: '#d4af37'
            });
        }
    }
};

// Función para aplicar estilos forzados a inputs de fecha/hora
window.aplicarEstilosInputs = function() {
    // Esperar un poco para que los elementos se rendericen
    setTimeout(() => {
        const dateInput = document.getElementById('fechaReservaZona');
        const timeInput = document.getElementById('horaReservaZona');
        const numberInput = document.getElementById('personasReservaZona');
        
        [dateInput, timeInput, numberInput].forEach(input => {
            if (input) {
                input.style.cssText = `
                    color: var(--text-primary) !important;
                    background-color: #ffffff !important;
                    border: 2px solid #e0e0e0 !important;
                    border-radius: 8px !important;
                    padding: 12px 15px !important;
                    font-size: 14px !important;
                `;
                
                // Si está en modo oscuro
                if (document.body.classList.contains('dark-mode')) {
                    input.style.cssText = `
                        color: #ffffff !important;
                        background-color: #3c3c3c !important;
                        border: 2px solid #555555 !important;
                        border-radius: 8px !important;
                        padding: 12px 15px !important;
                        font-size: 14px !important;
                    `;
                }
            }
        });

        if (numberInput) {
            numberInput.addEventListener('input', () => {
                numberInput.value = numberInput.value.replace(/[^0-9]/g, '');
            });
        }
    }, 100);
};

// Llamar al aplicar estilos cuando se abra el modal
const originalMostrarModal = window.reservaZonas.mostrarModalReservaZona.bind(window.reservaZonas);
window.reservaZonas.mostrarModalReservaZona = function() {
    originalMostrarModal();
    window.aplicarEstilosInputs();
};

console.log('reserva-zonas.js cargado correctamente');
