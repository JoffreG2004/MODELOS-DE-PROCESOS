<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restablecer Contraseña</title>
    <link rel="stylesheet" href="public/bootstrap/css/bootstrap.min.css">
    <style>
        body {
            background: #0b0f16;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .card-reset {
            width: 100%;
            max-width: 460px;
            border: 1px solid rgba(212, 175, 55, 0.25);
            box-shadow: 0 18px 35px rgba(0, 0, 0, 0.35);
        }
        .btn-primary {
            background: #d4af37;
            border-color: #d4af37;
            color: #111;
            font-weight: 700;
        }
        .btn-primary:hover {
            background: #c59f2e;
            border-color: #c59f2e;
            color: #111;
        }
    </style>
</head>
<body>
    <div class="card card-reset">
        <div class="card-body p-4">
            <h4 class="mb-3">Restablecer contraseña</h4>
            <p class="text-muted mb-4">Ingresa tu nueva contraseña para continuar.</p>

            <div id="alertBox" class="alert d-none" role="alert"></div>

            <form id="resetPasswordForm" novalidate>
                <input type="hidden" id="token" name="token">

                <div class="mb-3">
                    <label for="password" class="form-label">Nueva contraseña</label>
                    <div class="input-group">
                        <input type="password" class="form-control" id="password" name="password" required minlength="8"
                            pattern="(?=.*[A-Z])(?=.*\d).{8,}">
                        <button class="btn btn-outline-secondary" type="button" onclick="toggleResetPassword('password', 'toggleIconPassword')">
                            <span id="toggleIconPassword">👁</span>
                        </button>
                    </div>
                    <div class="form-text">Mínimo 8 caracteres, al menos 1 mayúscula y 1 número.</div>
                </div>

                <div class="mb-3">
                    <label for="passwordConfirm" class="form-label">Confirmar contraseña</label>
                    <div class="input-group">
                        <input type="password" class="form-control" id="passwordConfirm" name="password_confirm" required minlength="8"
                            pattern="(?=.*[A-Z])(?=.*\d).{8,}">
                        <button class="btn btn-outline-secondary" type="button" onclick="toggleResetPassword('passwordConfirm', 'toggleIconPasswordConfirm')">
                            <span id="toggleIconPasswordConfirm">👁</span>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary w-100">Guardar contraseña</button>
            </form>

            <div class="text-center mt-3">
                <a href="index.html">Volver al inicio</a>
            </div>
        </div>
    </div>

    <script>
        function showAlert(type, message) {
            const alertBox = document.getElementById('alertBox');
            alertBox.className = `alert alert-${type}`;
            alertBox.textContent = message;
        }

        function toggleResetPassword(inputId, iconId) {
            const input = document.getElementById(inputId);
            const icon = document.getElementById(iconId);
            if (!input || !icon) return;
            if (input.type === 'password') {
                input.type = 'text';
                icon.textContent = '🙈';
            } else {
                input.type = 'password';
                icon.textContent = '👁';
            }
        }

        const params = new URLSearchParams(window.location.search);
        const token = (params.get('token') || '').trim();
        document.getElementById('token').value = token;

        if (!token) {
            showAlert('danger', 'Token inválido. Solicita un nuevo enlace de recuperación.');
            document.getElementById('resetPasswordForm').classList.add('d-none');
        }

        document.getElementById('resetPasswordForm').addEventListener('submit', async function (e) {
            e.preventDefault();

            const password = document.getElementById('password').value.trim();
            const passwordConfirm = document.getElementById('passwordConfirm').value.trim();
            const passwordPolicy = /^(?=.*[A-Z])(?=.*\d).{8,}$/;

            if (!passwordPolicy.test(password)) {
                showAlert('warning', 'La contraseña debe tener mínimo 8 caracteres, 1 mayúscula y 1 número.');
                return;
            }

            if (password !== passwordConfirm) {
                showAlert('warning', 'Las contraseñas no coinciden.');
                return;
            }

            const body = new FormData();
            body.append('token', token);
            body.append('password', password);
            body.append('password_confirm', passwordConfirm);

            try {
                const response = await fetch('app/restablecer_password.php', {
                    method: 'POST',
                    body
                });

                const data = await response.json();
                if (data.success) {
                    showAlert('success', 'Contraseña actualizada correctamente. Ahora puedes iniciar sesión.');
                    this.reset();
                    setTimeout(() => {
                        window.location.href = 'index.html';
                    }, 1800);
                } else {
                    showAlert('danger', data.message || 'No se pudo restablecer la contraseña.');
                }
            } catch (err) {
                showAlert('danger', 'Error de conexión. Intenta nuevamente.');
            }
        });
    </script>
</body>
</html>
