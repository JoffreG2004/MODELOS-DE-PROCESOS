# 📋 DOCUMENTACIÓN: test_reservas_mesas.py

**Archivo de test:** `test-configuration/unit/test_reservas_mesas.py`  
**Panel evaluado:** `Reservas y Mesas`  
**Fecha:** 2026-01-11

---

## 📊 Resumen

- **Total tests:** 50
- **Pasados:** 24 ✅
- **Fallados:** 26 ❌
- **Porcentaje éxito:** 48.0%

---

## ⚠️ ESTADO: NECESITA CORRECCIONES

**26 tests fallan** - Requiere atención

---

## ✅ Tests que pasan (24):

### Reservar Zona (24 tests)
- ✅ Fecha vacía
- ✅ Fecha año 3000 (muy lejana)
- ✅ Fecha formato DD/MM/YYYY
- ✅ SQL injection en fecha
- ✅ XSS en fecha
- ✅ Fecha None/null
- ✅ Hora vacía
- ✅ XSS en hora
- ✅ Array zonas vacío []
- ✅ Zonas inexistentes
- ✅ ... y 14 tests más

---

## ❌ Tests que fallan (26):

### Reservar Zona (26 tests fallando)

- ❌ **Fecha pasada (ayer: 2026-01-10)**
  - Esperado: Debe rechazar: pasada

- ❌ **Fecha hace 1 semana (2026-01-04)**
  - Esperado: Debe rechazar: pasada

- ❌ **Fecha hace 1 mes (2025-12-12)**
  - Esperado: Debe rechazar: pasada

- ❌ **Fecha año 2100**
  - Esperado: Debe rechazar con mensaje conteniendo 'mes'

- ❌ **Fecha 7 meses (2026-08-09) >6 meses**
  - Esperado: Debe rechazar: mes

- ❌ **Fecha texto 'mañana'**
  - Esperado: Debe rechazar con mensaje conteniendo 'fecha'

- ❌ **Hora 06:00 (antes apertura)**
  - Esperado: Debe rechazar: hora

- ❌ **Hora 02:00 (después cierre)**
  - Esperado: Debe rechazar: hora

- ❌ **Hora formato '7pm'**
  - Esperado: Debe rechazar con mensaje conteniendo 'hora'

- ❌ **Hora 25:00 (inválida)**
  - Esperado: Debe rechazar: hora

- ❌ **Sin mesas en BD**
  - Esperado: Debe rechazar: mesa

- ❌ **Zona solo mesas ocupadas**
  - Esperado: Debe rechazar con mensaje conteniendo 'mesa'

- ❌ **0 personas**
  - Esperado: Debe rechazar con mensaje conteniendo 'persona'

- ❌ **Personas negativas (-5)**
  - Esperado: Debe rechazar con mensaje conteniendo 'persona'

- ❌ **1000 personas (excesivo)**
  - Esperado: Debe rechazar con mensaje conteniendo 'persona'

- ❌ **Personas None/null**
  - Esperado: Debe rechazar con mensaje conteniendo 'persona'

- ❌ **XSS en número personas**
  - Esperado: Debe rechazar con mensaje conteniendo 'persona'

- ❌ **Múltiples zonas válidas**
  - Esperado: Debe aceptar datos válidos

- ❌ **Todas las zonas simultáneas**
  - Esperado: Debe aceptar datos válidos

- ❌ **Zona válida + zona inexistente**
  - Esperado: Debe rechazar con mensaje conteniendo 'mesa'

- ❌ **XSS + zona válida en array**
  - Esperado: Debe rechazar con mensaje conteniendo 'mesa'

- ❌ **Hoy medianoche (hora límite)**
  - Esperado: Debe rechazar: hora

- ❌ **SQL injection en personas (texto)**
  - Esperado: Debe rechazar con mensaje conteniendo 'persona'

- ❌ **Personas número muy negativo**
  - Esperado: Debe rechazar con mensaje conteniendo 'persona'

- ❌ **Personas MAX_INT (overflow)**
  - Esperado: Debe rechazar con mensaje conteniendo 'persona'

- ❌ **Personas decimal (0.5)**
  - Esperado: Debe rechazar con mensaje conteniendo 'persona'

---

## 🎯 Conclusión

**Reservas y Mesas - Estado General:**

🚨 **CRÍTICO** - 48.0% de tests pasando
- Sistema requiere trabajo significativo
- Bugs graves pendientes

**Próximos pasos:**
1. Revisar tests fallados
2. Corregir bugs críticos
3. Validar seguridad
4. Ejecutar auditoría: `python3 auditoria_tests.py`

---

*Generado automáticamente por: `generar_reportes.py`*  
*Fecha: 2026-01-11 18:05:19*
