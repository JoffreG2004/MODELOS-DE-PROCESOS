<?php
/**
 * TEST: Verificar sistema de bloqueo de 3 horas
 */

require_once 'conexion/db.php';
require_once 'models/Reserva.php';

echo "========================================\n";
echo "🧪 PRUEBA: Bloqueo de 3 Horas\n";
echo "========================================\n\n";

$reserva = new Reserva();
$mesa_id = 6; // Mesa de la reserva existente
$fecha = date('Y-m-d'); // Hoy

echo "Mesa a probar: #$mesa_id\n";
echo "Fecha: $fecha\n\n";

// Obtener hora de la reserva existente
$stmt = $pdo->prepare("SELECT TIME_FORMAT(hora_reserva, '%H:%i') as hora FROM reservas WHERE id = 173");
$stmt->execute();
$reserva_existente = $stmt->fetch(PDO::FETCH_ASSOC);
echo "Reserva existente: {$reserva_existente['hora']}\n\n";

// TEST 1: Intentar reservar 1 hora después (21:50) - DEBE RECHAZAR
echo "TEST 1: Intentar reservar 1 hora después\n";
$hora_test1 = date('H:i:s', strtotime($reserva_existente['hora']) + 3600);
$disponible1 = $reserva->verificarDisponibilidad($mesa_id, $fecha, $hora_test1);
echo "  Hora: $hora_test1\n";
echo "  Resultado: " . ($disponible1 ? "✅ DISPONIBLE" : "❌ NO DISPONIBLE") . "\n";
echo "  Esperado: ❌ NO DISPONIBLE (menos de 3 horas)\n";
echo "  " . ($disponible1 ? "🔴 FALLO" : "🟢 CORRECTO") . "\n\n";

// TEST 2: Intentar reservar 2 horas después (22:50) - DEBE RECHAZAR
echo "TEST 2: Intentar reservar 2 horas después\n";
$hora_test2 = date('H:i:s', strtotime($reserva_existente['hora']) + 7200);
$disponible2 = $reserva->verificarDisponibilidad($mesa_id, $fecha, $hora_test2);
echo "  Hora: $hora_test2\n";
echo "  Resultado: " . ($disponible2 ? "✅ DISPONIBLE" : "❌ NO DISPONIBLE") . "\n";
echo "  Esperado: ❌ NO DISPONIBLE (menos de 3 horas)\n";
echo "  " . ($disponible2 ? "🔴 FALLO" : "🟢 CORRECTO") . "\n\n";

// TEST 3: Intentar reservar 4 horas después (00:50) - DEBE PERMITIR
echo "TEST 3: Intentar reservar 4 horas después\n";
$hora_test3 = date('H:i:s', strtotime($reserva_existente['hora']) + 14400);
$disponible3 = $reserva->verificarDisponibilidad($mesa_id, $fecha, $hora_test3);
echo "  Hora: $hora_test3\n";
echo "  Resultado: " . ($disponible3 ? "✅ DISPONIBLE" : "❌ NO DISPONIBLE") . "\n";
echo "  Esperado: ✅ DISPONIBLE (más de 3 horas)\n";
echo "  " . ($disponible3 ? "🟢 CORRECTO" : "🔴 FALLO") . "\n\n";

// TEST 4: Intentar reservar 1 hora ANTES (19:50) - DEBE RECHAZAR
echo "TEST 4: Intentar reservar 1 hora ANTES\n";
$hora_test4 = date('H:i:s', strtotime($reserva_existente['hora']) - 3600);
$disponible4 = $reserva->verificarDisponibilidad($mesa_id, $fecha, $hora_test4);
echo "  Hora: $hora_test4\n";
echo "  Resultado: " . ($disponible4 ? "✅ DISPONIBLE" : "❌ NO DISPONIBLE") . "\n";
echo "  Esperado: ❌ NO DISPONIBLE (preparación de 1h)\n";
echo "  " . ($disponible4 ? "🔴 FALLO" : "🟢 CORRECTO") . "\n\n";

// TEST 5: Mesa diferente - DEBE PERMITIR
echo "TEST 5: Mesa diferente (Mesa #1) a la misma hora\n";
$disponible5 = $reserva->verificarDisponibilidad(1, $fecha, $reserva_existente['hora']);
echo "  Mesa: #1\n";
echo "  Hora: {$reserva_existente['hora']}\n";
echo "  Resultado: " . ($disponible5 ? "✅ DISPONIBLE" : "❌ NO DISPONIBLE") . "\n";
echo "  Esperado: ✅ DISPONIBLE (mesa diferente)\n";
echo "  " . ($disponible5 ? "🟢 CORRECTO" : "🔴 FALLO") . "\n\n";

echo "========================================\n";
echo "📊 RESUMEN DE PRUEBAS\n";
echo "========================================\n";
$total = 5;
$correctos = 0;
$correctos += !$disponible1 ? 1 : 0;
$correctos += !$disponible2 ? 1 : 0;
$correctos += $disponible3 ? 1 : 0;
$correctos += !$disponible4 ? 1 : 0;
$correctos += $disponible5 ? 1 : 0;

echo "Total pruebas: $total\n";
echo "Correctas: $correctos\n";
echo "Fallidas: " . ($total - $correctos) . "\n\n";

if ($correctos === $total) {
    echo "🎉 TODAS LAS PRUEBAS PASARON!\n";
} else {
    echo "⚠️  ALGUNAS PRUEBAS FALLARON\n";
}

echo "========================================\n";
?>
