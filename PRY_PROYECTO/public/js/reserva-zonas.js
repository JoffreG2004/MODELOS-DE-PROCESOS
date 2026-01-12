/**
 * Gestión de Reservas de Zonas Completas
 * Permite a los clientes reservar salas/zonas enteras del restaurante
 */

class ReservaZonas {
    constructor() {
        this.zonasSeleccionadas = new Set();
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
    }

    mostrarModalReservaZona() {
        const zonasHTML = Object.entries(this.nombresZonas).map(([key, nombre]) => `
            <div class="form-check zona-check-item mb-3 p-3 border rounded" style="cursor: pointer;" 
                 onclick="reservaZonas.toggleZona('${key}')">
                <input class="form-check-input zona-checkbox" type="checkbox" 
                       value="${key}" id="zona-${key}">
                <label class="form-check-label w-100" for="zona-${key}" style="cursor: pointer;">
                    <h5 class="mb-1">${nombre}</h5>
                    <small class="text-muted">Incluye todas las mesas de esta área</small>
                </label>
            </div>
        `).join('');

        Swal.fire({
            title: '🎉 Reserva de Zona Completa',
            html: `
                <div class="text-start">
                    <div class="alert alert-info mb-4">
                        <strong>💡 Reserva áreas completas:</strong><br>
                        <small>
                        • 1 zona: $60<br>
                        • 2 zonas: $100<br>
                        • 3 zonas: $120<br>
                        • Todo el establecimiento: $140
                        </small>
                    </div>

                    <h6 class="mb-3">Selecciona las zonas que deseas reservar:</h6>
                    ${zonasHTML}

                    <div id="precioSeleccion" class="alert alert-success mt-3" style="display: none;">
                        <strong>Precio total: $<span id="precioTotal">0</span></strong><br>
                        <small><span id="zonasSeleccionadasText"></span></small>
                    </div>

                    <hr class="my-4">

                    <div class="mb-3">
                        <label class="form-label"><i class="fas fa-calendar"></i> Fecha de Reserva</label>
                        <input type="date" class="form-control" id="fechaReservaZona" 
                               min="${new Date().toISOString().split('T')[0]}" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label"><i class="fas fa-clock"></i> Hora de Reserva</label>
                        <input type="time" class="form-control" id="horaReservaZona" 
                               value="19:00" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label"><i class="fas fa-users"></i> Número de Personas</label>
                        <input type="number" class="form-control" id="personasReservaZona" 
                               min="1" max="200" value="20" required>
                    </div>
                </div>
            `,
            width: '600px',
            showCancelButton: true,
            confirmButtonText: 'Confirmar Reserva',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#d4af37',
            preConfirm: () => {
                if (this.zonasSeleccionadas.size === 0) {
                    Swal.showValidationMessage('Debes seleccionar al menos una zona');
                    return false;
                }

                const fecha = document.getElementById('fechaReservaZona').value;
                const hora = document.getElementById('horaReservaZona').value;
                const personas = document.getElementById('personasReservaZona').value;

                if (!fecha || !hora || !personas) {
                    Swal.showValidationMessage('Completa todos los campos requeridos');
                    return false;
                }

                return {
                    zonas: Array.from(this.zonasSeleccionadas),
                    fecha_reserva: fecha,
                    hora_reserva: hora,
                    numero_personas: parseInt(personas)
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
        checkbox.checked = !checkbox.checked;

        if (checkbox.checked) {
            this.zonasSeleccionadas.add(zonaKey);
        } else {
            this.zonasSeleccionadas.delete(zonaKey);
        }

        this.actualizarPrecio();
    }

    actualizarPrecio() {
        const cantidad = this.zonasSeleccionadas.size;
        const precio = this.precios[cantidad] || 0;

        const precioDiv = document.getElementById('precioSeleccion');
        const precioSpan = document.getElementById('precioTotal');
        const zonasText = document.getElementById('zonasSeleccionadasText');

        if (cantidad > 0) {
            precioDiv.style.display = 'block';
            precioSpan.textContent = precio;

            const nombresSeleccionados = Array.from(this.zonasSeleccionadas)
                .map(z => this.nombresZonas[z])
                .join(', ');
            zonasText.textContent = `${cantidad} zona(s) seleccionada(s): ${nombresSeleccionados}`;
        } else {
            precioDiv.style.display = 'none';
        }
    }

    async crearReservaZona(data) {
        try {
            Swal.fire({
                title: 'Procesando...',
                text: 'Creando tu reserva de zona',
                allowOutsideClick: false,
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
                    title: '✅ ¡Solicitud de Reserva Enviada!',
                    html: `
                        <div class="text-start">
                            <div class="alert alert-warning mb-3">
                                <strong>⏳ Pendiente de Confirmación</strong><br>
                                <small>El administrador debe confirmar tu reserva de zona</small>
                            </div>
                            
                            <p><strong>Zonas solicitadas:</strong></p>
                            <ul>
                                ${result.data.zonas.map(z => `<li>${z}</li>`).join('')}
                            </ul>
                            <p><strong>📅 Fecha:</strong> ${result.data.fecha}</p>
                            <p><strong>🕐 Hora:</strong> ${result.data.hora}</p>
                            <p><strong>👥 Personas:</strong> ${result.data.personas}</p>
                            <p><strong>🪑 Mesas incluidas:</strong> ${result.data.cantidad_mesas}</p>
                            <p><strong>💰 Total a pagar:</strong> $${result.data.precio_total}</p>
                            <hr>
                            <p class="text-muted"><small>💡 Recibirás una notificación cuando el administrador confirme tu reserva</small></p>
                        </div>
                    `,
                    confirmButtonColor: '#d4af37',
                    confirmButtonText: 'Ver Mis Reservas'
                }).then(() => {
                    if (typeof location !== 'undefined') {
                        location.reload();
                    }
                });
            } else {
                throw new Error(result.message || 'Error al crear reserva');
            }
        } catch (error) {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: error.message || 'No se pudo crear la reserva de zona',
                confirmButtonColor: '#d33'
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

console.log('reserva-zonas.js cargado correctamente');
