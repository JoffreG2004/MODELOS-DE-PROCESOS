#!/usr/bin/env python3
"""
UNIT TEST: Validador de Nombres y Apellidos
Prueba el ValidadorNombres.php con 50 casos de prueba
- Nombres válidos con caracteres españoles
- Nombres inválidos (números, símbolos, vacíos)
- Casos edge: longitud, espacios, caracteres especiales
"""

import requests
import json
import os
from common import safe_request, result

BASE = "http://localhost/PRY_PROYECTO"
SESSION = requests.Session()
REPORT_DIR = os.path.join(os.path.dirname(__file__), "reportes")
OUTPUT_FILE = os.path.join(REPORT_DIR, "ultimo-resultado-validador-nombres.json")
os.makedirs(REPORT_DIR, exist_ok=True)

# Endpoint para validar nombres (usa el validador)
VALIDAR_URL = f"{BASE}/app/registro_cliente.php"


def validar_nombre_via_registro(nombre, apellido, nombre_test):
    """Probar validador de nombres mediante endpoint de registro"""
    payload = {
        "nombre": nombre,
        "apellido": apellido,
        "cedula": "1234567890",
        "telefono": "0999999999",
        "ciudad": "Quito",
        "usuario": f"test_{nombre_test}",
        "password": "Test1234!"
    }
    
    res = safe_request("POST", VALIDAR_URL, SESSION, data=payload)
    data = res.get("data", {})
    
    return data


def main():
    """Ejecuta 50 tests del validador de nombres"""
    print("\n🔤 TESTS DE VALIDADOR DE NOMBRES Y APELLIDOS")
    print("=" * 60)
    
    resultados = []
    
    # =============================================
    # GRUPO 1: NOMBRES VÁLIDOS (15 tests)
    # =============================================
    print("\n✅ GRUPO 1: Nombres Válidos")
    
    nombres_validos = [
        ("Juan", "Pérez", "nombre con tilde"),
        ("María", "José", "nombre doble"),
        ("José Luis", "García", "nombre compuesto con espacio"),
        ("Ana María", "Rodríguez", "ambos con espacios"),
        ("Carlos", "O'Brien", "apellido con apóstrofe"),
        ("Jean-Pierre", "Martínez", "nombre con guion"),
        ("Sofía", "López", "acento en í"),
        ("Andrés", "Sánchez", "acento en é"),
        ("Raúl", "Fernández", "acento en ú"),
        ("Mónica", "González", "acento en ó"),
        ("Ángel", "Ramírez", "acento en á"),
        ("Antonio", "Nuñez", "ñ minúscula"),
        ("Pedro", "Muñoz", "ñ en apellido"),
        ("Luis", "Ibáñez", "acento y ñ"),
        ("Carolina", "Pérez-López", "apellido compuesto con guion")
    ]
    
    for nombre, apellido, desc in nombres_validos:
        data = validar_nombre_via_registro(nombre, apellido, desc.replace(" ", "_"))
        # Si no hay error de validación de nombre, es válido
        error_nombre = "nombre" in data.get("message", "").lower() and "caracteres" in data.get("message", "").lower()
        paso = not error_nombre
        
        resultados.append(result(
            nombre=f"Nombre válido: {nombre} {apellido} ({desc})",
            panel="Validador de Nombres",
            accion=f"Validar nombre='{nombre}' apellido='{apellido}'",
            esperado="Debe aceptar nombres con tildes, ñ, espacios, guiones, apóstrofes",
            paso=paso,
            respuesta=data
        ))
    
    # =============================================
    # GRUPO 2: NOMBRES CON CARACTERES ESPECIALES (10 tests)
    # =============================================
    print("\n🔣 GRUPO 2: Nombres con Caracteres Especiales")
    
    nombres_especiales = [
        ("Müller", "Schmidt", "ü alemán", False),  # Debe fallar (no soportado)
        ("François", "Dubois", "ç francés", False),
        ("Björk", "Guðmundsdóttir", "ö islandés", False),
        ("José", "Nuñez", "ñ válido", True),
        ("María José", "de la Cruz", "espacios válidos", True),
        ("Jean-Paul", "Saint-Pierre", "guiones válidos", True),
        ("O'Connor", "McCarthy", "apóstrofe válido", True),
        ("Ñoño", "Peña", "ñ al inicio", True),
        ("Inés", "Úrsula", "acentos válidos", True),
        ("José Ángel", "Ramón", "todo válido español", True)
    ]
    
    for nombre, apellido, desc, debe_pasar in nombres_especiales:
        data = validar_nombre_via_registro(nombre, apellido, desc.replace(" ", "_"))
        error_nombre = "caracteres no válidos" in data.get("message", "").lower() or "contiene caracteres" in data.get("message", "").lower()
        
        if debe_pasar:
            paso = not error_nombre  # No debe tener error
        else:
            paso = error_nombre  # Debe tener error (rechazar caracteres no soportados)
        
        resultados.append(result(
            nombre=f"Caracteres especiales: {nombre} {apellido} ({desc})",
            panel="Validador de Nombres",
            accion=f"Validar nombre='{nombre}' apellido='{apellido}'",
            esperado=f"{'Aceptar' if debe_pasar else 'Rechazar'} nombre con {desc}",
            paso=paso,
            respuesta=data
        ))
    
    # =============================================
    # GRUPO 3: NOMBRES INVÁLIDOS (15 tests)
    # =============================================
    print("\n❌ GRUPO 3: Nombres Inválidos")
    
    nombres_invalidos = [
        ("", "Pérez", "nombre vacío"),
        ("Juan", "", "apellido vacío"),
        ("123", "Pérez", "nombre con números"),
        ("Juan", "456", "apellido con números"),
        ("Juan123", "Pérez", "nombre con números al final"),
        ("Juan@", "Pérez", "nombre con @"),
        ("Juan#Test", "Pérez", "nombre con #"),
        ("Juan!", "Pérez", "nombre con !"),
        ("Juan$", "Pérez", "nombre con $"),
        ("A", "B", "nombre muy corto (1 char)"),
        ("Juan" * 20, "Pérez", "nombre muy largo (>50 chars)"),
        ("Juan  Pedro", "Pérez", "espacios dobles"),
        ("Juan   ", "Pérez", "espacios al final"),
        ("   Juan", "Pérez", "espacios al inicio"),
        ("<script>", "alert", "intento XSS")
    ]
    
    for nombre, apellido, desc in nombres_invalidos:
        data = validar_nombre_via_registro(nombre, apellido, desc.replace(" ", "_"))
        # Debe rechazar estos nombres
        tiene_error = data.get("success") is False
        paso = tiene_error
        
        resultados.append(result(
            nombre=f"Nombre inválido: {desc}",
            panel="Validador de Nombres",
            accion=f"Validar nombre='{nombre[:20]}...' apellido='{apellido[:20]}...'",
            esperado="Debe rechazar nombres inválidos",
            paso=paso,
            respuesta=data
        ))
    
    # =============================================
    # GRUPO 4: CASOS EDGE (10 tests)
    # =============================================
    print("\n⚠️ GRUPO 4: Casos Edge")
    
    casos_edge = [
        ("Jo", "Li", "longitud mínima (2 chars)", True),
        ("A" * 50, "B" * 50, "longitud máxima exacta (50 chars)", True),
        ("A" * 51, "Pérez", "excede máximo (51 chars)", False),
        ("María-José", "López", "nombre con guion", True),
        ("Mary Ann", "Smith Jones", "ambos con espacios", True),
        ("José'", "O'Brien", "apóstrofe al final", True),
        ("'Juan", "Pérez", "apóstrofe al inicio", True),
        ("-Juan", "Pérez", "guion al inicio", True),
        ("Juan-", "Pérez", "guion al final", True),
        ("Ñ", "Ñ", "solo ñ (1 char)", False)
    ]
    
    for nombre, apellido, desc, debe_pasar in casos_edge:
        data = validar_nombre_via_registro(nombre, apellido, desc.replace(" ", "_"))
        tiene_error = data.get("success") is False
        
        if debe_pasar:
            paso = not tiene_error
        else:
            paso = tiene_error
        
        resultados.append(result(
            nombre=f"Edge case: {desc}",
            panel="Validador de Nombres",
            accion=f"Validar nombre='{nombre[:20]}...' apellido='{apellido[:20]}...'",
            esperado=f"{'Aceptar' if debe_pasar else 'Rechazar'} caso edge: {desc}",
            paso=paso,
            respuesta=data
        ))
    
    # ========================================
    # RESUMEN
    # ========================================
    print("\n" + "=" * 60)
    
    total = len(resultados)
    pasados = sum(1 for r in resultados if r["paso"])
    fallados = total - pasados
    porcentaje = (pasados / total * 100) if total > 0 else 0
    
    print(f"✅ Pasados: {pasados}/{total} ({porcentaje:.1f}%)")
    print(f"❌ Fallados: {fallados}")
    
    # Guardar resultados
    with open(OUTPUT_FILE, 'w', encoding='utf-8') as f:
        json.dump(resultados, f, indent=2, ensure_ascii=False)
    
    print(f"📄 Guardado en: {OUTPUT_FILE}")
    print("=" * 60)
    
    # Mostrar tests fallados
    if fallados > 0:
        print("\n❌ TESTS FALLADOS:")
        for r in resultados:
            if not r["paso"]:
                print(f"  - {r['nombre']}")
    
    return 0 if fallados == 0 else 1


if __name__ == "__main__":
    exit(main())
