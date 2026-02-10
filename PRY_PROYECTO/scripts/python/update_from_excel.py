#!/usr/bin/env python3
"""
update_from_excel.py
Lee un Excel con dos hojas ("categorias" y "platos") y actualiza MySQL:
 - Tabla categorias: id, nombre (UNICO), descripcion, orden_menu, activo
 - Tabla platos: id, categoria_id (FK), nombre, descripcion, precio, stock_disponible,
                 tiempo_preparacion, imagen_url, ingredientes, es_especial, activo
Requisitos:
  pip install pandas openpyxl pymysql python-slugify
Uso:
  python update_from_excel.py --input menu.xlsx \
      --mysql-host localhost --mysql-db crud_proyecto \
      --mysql-user crud_proyecto --mysql-pass 12345
"""
import argparse
import re
import sys
import pymysql
import pandas as pd

CAT_SHEET = "categorias"
PLA_SHEET = "platos"
MAX_ISSUES_TO_PRINT = 30


def clean_text(value, default=""):
    if value is None or pd.isna(value):
        return default
    text = str(value).strip()
    if text.lower() in {"nan", "none", "null"}:
        return default
    return text


def parse_number(value):
    """Acepta números como 12.5, 12,50, 1.234,56 o 1,234.56."""
    if value is None or pd.isna(value):
        return None
    if isinstance(value, (int, float)):
        return float(value)

    text = clean_text(value)
    if not text:
        return None

    # Quitar símbolos comunes de moneda/espacios
    text = re.sub(r"[^\d,.\-]", "", text)
    if not text:
        return None

    if "," in text and "." in text:
        # Si la última coma aparece después del último punto, asumimos formato 1.234,56
        if text.rfind(",") > text.rfind("."):
            text = text.replace(".", "").replace(",", ".")
        else:
            # Formato 1,234.56
            text = text.replace(",", "")
    elif "," in text:
        text = text.replace(",", ".")

    return float(text)


def to_binary(value, default, warnings, ctx, field):
    if value is None or pd.isna(value):
        return default

    if isinstance(value, str):
        raw = value.strip().lower()
    else:
        raw = str(value).strip().lower()

    if raw in {"", "nan", "none", "null"}:
        return default
    if raw in {"1", "true", "t", "si", "sí", "yes", "y", "activo"}:
        return 1
    if raw in {"0", "false", "f", "no", "n", "inactivo"}:
        return 0

    try:
        num = parse_number(value)
        if num is None:
            raise ValueError("vacío")
        if num in (0.0, 1.0):
            return int(num)
        converted = 1 if num > 0 else 0
        warnings.append(
            f"{ctx}: {field}={value!r} no es binario, se convirtió a {converted}."
        )
        return converted
    except Exception:
        warnings.append(
            f"{ctx}: {field}={value!r} inválido, se usó valor por defecto {default}."
        )
        return default


def to_non_negative_int(value, default, warnings, ctx, field):
    try:
        num = parse_number(value)
    except Exception:
        warnings.append(
            f"{ctx}: {field}={value!r} inválido, se usó valor por defecto {default}."
        )
        return default
    if num is None:
        return default

    intval = int(round(num))
    if abs(num - intval) > 1e-9:
        warnings.append(f"{ctx}: {field}={value!r} se redondeó a {intval}.")
    if intval < 0:
        warnings.append(f"{ctx}: {field}={value!r} era negativo, se usó 0.")
        return 0
    return intval


def to_non_negative_float(value, default, warnings, ctx, field):
    try:
        num = parse_number(value)
    except Exception:
        warnings.append(
            f"{ctx}: {field}={value!r} inválido, se usó valor por defecto {default}."
        )
        return float(default)
    if num is None:
        return default
    if num < 0:
        warnings.append(f"{ctx}: {field}={value!r} era negativo, se usó 0.")
        return 0.0
    return float(num)


def normalize_columns(df):
    df.columns = [clean_text(c).lower() for c in df.columns]
    return df

def connect_mysql(host, db, user, pwd, charset="utf8mb4"):
    return pymysql.connect(
        host=host, user=user, password=pwd, database=db,
        charset=charset, cursorclass=pymysql.cursors.DictCursor, autocommit=False
    )

def table_exists(conn, table_name):
    with conn.cursor() as c:
        c.execute("SELECT COUNT(1) AS cnt FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s", (table_name,))
        return c.fetchone()["cnt"] > 0

def get_columns(conn, table_name):
    with conn.cursor() as c:
        c.execute("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s", (table_name,))
        return [r['COLUMN_NAME'] for r in c.fetchall()]

def ensure_unique_indexes(conn, categorias_table='categorias', platos_table='platos'):
    with conn.cursor() as c:
        # Índice único en categorias.nombre
        c.execute("""
            SELECT COUNT(1) AS cnt FROM INFORMATION_SCHEMA.STATISTICS
            WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s AND INDEX_NAME='uq_categorias_nombre';
        """, (categorias_table,))
        if c.fetchone()["cnt"] == 0:
            c.execute(f"CREATE UNIQUE INDEX uq_categorias_nombre ON {categorias_table}(nombre);")
        # Índice compuesto en platos(nombre, categoria_id)
        c.execute("""
            SELECT COUNT(1) AS cnt FROM INFORMATION_SCHEMA.STATISTICS
            WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s AND INDEX_NAME='uq_platos_nombre_categoria';
        """, (platos_table,))
        if c.fetchone()["cnt"] == 0:
            c.execute(f"CREATE UNIQUE INDEX uq_platos_nombre_categoria ON {platos_table}(nombre, categoria_id);")

def upsert_categoria(conn, row, categorias_table='categorias'):
    with conn.cursor() as c:
        # Buscar por nombre
        c.execute(f"SELECT id FROM {categorias_table} WHERE nombre=%s", (row["nombre"],))
        r = c.fetchone()
        if r:
            # Update
            c.execute(f"""
                UPDATE {categorias_table} SET descripcion=%s, orden_menu=%s, activo=%s
                WHERE id=%s
            """, (row["descripcion"], row["orden_menu"], row["activo"], r["id"]))
            return r["id"], "updated"
        else:
            # Insert
            c.execute(f"""
                INSERT INTO {categorias_table} (nombre, descripcion, orden_menu, activo)
                VALUES (%s,%s,%s,%s)
            """, (row["nombre"], row["descripcion"], row["orden_menu"], row["activo"]))
            return c.lastrowid, "inserted"

def upsert_plato(conn, row, categoria_id, platos_table='platos', available_columns=None):
    # available_columns: list of column names present in platos_table
    if available_columns is None:
        available_columns = []
    with conn.cursor() as c:
        c.execute(f"SELECT id FROM {platos_table} WHERE nombre=%s AND categoria_id=%s", (row["nombre"], categoria_id))
        r = c.fetchone()

        expected_fields = [
            "descripcion","precio","stock_disponible","tiempo_preparacion",
            "imagen_url","ingredientes","es_especial","activo"
        ]
        # Use only the fields that exist in the table
        fields = [f for f in expected_fields if f in available_columns]

        # Build values aligned with fields
        val_map = {
            "descripcion": row.get("descripcion", ""),
            "precio": row.get("precio", 0.0),
            "stock_disponible": row.get("stock_disponible", 0),
            "tiempo_preparacion": row.get("tiempo_preparacion", 0),
            "imagen_url": row.get("imagen_url", ""),
            "ingredientes": row.get("ingredientes", ""),
            "es_especial": row.get("es_especial", 0),
            "activo": row.get("activo", 1),
        }

        values = tuple(val_map[f] for f in fields)

        if r:
            if fields:
                c.execute(f"""
                    UPDATE {platos_table} SET {', '.join(f + '=%s' for f in fields)}
                    WHERE id=%s
                """, (*values, r["id"]))
            return r["id"], "updated"
        else:
            if fields:
                placeholders = ','.join(['%s']*len(fields))
                c.execute(f"""
                    INSERT INTO {platos_table} (categoria_id, nombre, {', '.join(fields)})
                    VALUES (%s,%s,{placeholders})
                """, (categoria_id, row["nombre"], *values))
            else:
                # No extra fields to insert, just categoria_id and nombre
                c.execute(f"INSERT INTO {platos_table} (categoria_id, nombre) VALUES (%s,%s)", (categoria_id, row["nombre"]))
            return c.lastrowid, "inserted"

def load_frames(path):
    xls = pd.ExcelFile(path)
    sheet_names = [s.strip() for s in xls.sheet_names]

    def choose_sheet(wanted, candidates):
        w = wanted.lower()
        # Exact match
        for s in candidates:
            if s.lower() == w:
                return s
        # Match by contains
        for s in candidates:
            if w in s.lower() or s.lower() in w:
                return s
        # Fallback: look for keywords
        keywords = wanted.lower().split('_')
        for s in candidates:
            low = s.lower()
            if any(kw for kw in keywords if kw and kw in low):
                return s
        # last resort: first sheet
        return candidates[0] if candidates else wanted

    cat_sheet_name = choose_sheet(CAT_SHEET, sheet_names)
    pla_sheet_name = choose_sheet(PLA_SHEET, sheet_names)

    cats = normalize_columns(pd.read_excel(xls, cat_sheet_name))
    platos = normalize_columns(pd.read_excel(xls, pla_sheet_name))

    # Columnas mínimas requeridas
    if "nombre" not in cats.columns:
        raise ValueError("La hoja de categorías debe incluir la columna 'nombre'.")
    if "nombre" not in platos.columns or "categoria" not in platos.columns:
        raise ValueError("La hoja de platos debe incluir columnas 'categoria' y 'nombre'.")

    # Completar opcionales para evitar KeyError
    for col, default in {
        "descripcion": "",
        "orden_menu": 0,
        "activo": 1
    }.items():
        if col not in cats.columns:
            cats[col] = default

    for col, default in {
        "descripcion": "",
        "precio": 0,
        "stock_disponible": 0,
        "tiempo_preparacion": 0,
        "imagen_url": "",
        "ingredientes": "",
        "es_especial": 0,
        "activo": 1
    }.items():
        if col not in platos.columns:
            platos[col] = default

    # Normalizar textos clave
    cats["nombre"] = cats["nombre"].apply(lambda v: clean_text(v))
    platos["nombre"] = platos["nombre"].apply(lambda v: clean_text(v))
    platos["categoria"] = platos["categoria"].apply(lambda v: clean_text(v))

    return cats, platos


def sanitize_categoria_row(row, row_number, warnings, errors):
    ctx = f"[categorias fila {row_number}]"
    nombre = clean_text(row.get("nombre"))
    if not nombre:
        errors.append(f"{ctx} sin nombre, se omitió.")
        return None

    return {
        "nombre": nombre,
        "descripcion": clean_text(row.get("descripcion"), ""),
        "orden_menu": to_non_negative_int(row.get("orden_menu"), 0, warnings, ctx, "orden_menu"),
        "activo": to_binary(row.get("activo"), 1, warnings, ctx, "activo"),
    }


def sanitize_plato_row(row, row_number, warnings, errors):
    ctx = f"[platos fila {row_number}]"
    nombre = clean_text(row.get("nombre"))
    categoria = clean_text(row.get("categoria"))

    if not nombre:
        errors.append(f"{ctx} sin nombre, se omitió.")
        return None
    if not categoria:
        errors.append(f"{ctx} sin categoría, se omitió.")
        return None

    return {
        "categoria": categoria,
        "nombre": nombre,
        "descripcion": clean_text(row.get("descripcion"), ""),
        "precio": to_non_negative_float(row.get("precio"), 0.0, warnings, ctx, "precio"),
        "stock_disponible": to_non_negative_int(row.get("stock_disponible"), 0, warnings, ctx, "stock_disponible"),
        "tiempo_preparacion": to_non_negative_int(row.get("tiempo_preparacion"), 0, warnings, ctx, "tiempo_preparacion"),
        "imagen_url": clean_text(row.get("imagen_url"), ""),
        "ingredientes": clean_text(row.get("ingredientes"), ""),
        "es_especial": to_binary(row.get("es_especial"), 0, warnings, ctx, "es_especial"),
        "activo": to_binary(row.get("activo"), 1, warnings, ctx, "activo"),
    }

def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--input", required=True)
    ap.add_argument("--mysql-host", default="localhost")
    ap.add_argument("--mysql-db", required=True)
    ap.add_argument("--mysql-user", required=True)
    ap.add_argument("--mysql-pass", required=True)
    ap.add_argument("--clear-before", action="store_true", help="Eliminar todos los platos y categorías antes de cargar")
    args = ap.parse_args()

    cats_df, platos_df = load_frames(args.input)
    conn = connect_mysql(args.mysql_host, args.mysql_db, args.mysql_user, args.mysql_pass)
    warnings = []
    errors = []
    counters = {
        "categorias_inserted": 0,
        "categorias_updated": 0,
        "categorias_skipped": 0,
        "platos_inserted": 0,
        "platos_updated": 0,
        "platos_skipped": 0,
    }
    try:
        # Detectar nombres de tablas y columnas reales para ser tolerante a cambios de esquema
        # Priorizar `categorias_platos` (usado en este proyecto) si existe
        categorias_table = 'categorias_platos' if table_exists(conn, 'categorias_platos') else ('categorias' if table_exists(conn, 'categorias') else 'categorias')
        platos_table = 'platos' if table_exists(conn, 'platos') else 'platos'

        platos_columns = get_columns(conn, platos_table)

        # NUEVO: Limpiar tablas si se solicitó
        if args.clear_before:
            with conn.cursor() as c:
                print("🗑️  Limpiando tablas existentes...")
                c.execute("SET FOREIGN_KEY_CHECKS=0")
                if table_exists(conn, 'pre_pedidos'):
                    c.execute("DELETE FROM pre_pedidos")
                c.execute(f"DELETE FROM {platos_table}")
                c.execute(f"DELETE FROM {categorias_table}")
                c.execute("SET FOREIGN_KEY_CHECKS=1")
                print(f"✅ Tablas limpiadas: pre_pedidos, {platos_table}, {categorias_table}")

        try:
            ensure_unique_indexes(conn, categorias_table=categorias_table, platos_table=platos_table)
        except Exception as idx_err:
            warnings.append(f"No se pudieron crear/validar índices únicos: {idx_err}")

        # Upsert categorías
        cat_map = {}
        for idx, row in cats_df.iterrows():
            safe_row = sanitize_categoria_row(row, idx + 2, warnings, errors)
            if not safe_row:
                counters["categorias_skipped"] += 1
                continue
            try:
                cat_id, action = upsert_categoria(conn, safe_row, categorias_table=categorias_table)
                cat_map[safe_row["nombre"]] = cat_id
                counters[f"categorias_{action}"] += 1
            except Exception as row_err:
                counters["categorias_skipped"] += 1
                errors.append(f"[categorias fila {idx + 2}] error DB: {row_err}")

        # Upsert platos
        for idx, row in platos_df.iterrows():
            safe_row = sanitize_plato_row(row, idx + 2, warnings, errors)
            if not safe_row:
                counters["platos_skipped"] += 1
                continue
            categoria_name = safe_row["categoria"]
            if categoria_name not in cat_map:
                # Crear categoría al vuelo si no está
                cat_id, action = upsert_categoria(conn, {
                    "nombre": categoria_name,
                    "descripcion": "",
                    "orden_menu": 0,
                    "activo": 1
                }, categorias_table=categorias_table)
                cat_map[categoria_name] = cat_id
                counters[f"categorias_{action}"] += 1
            try:
                _, action = upsert_plato(conn, safe_row, cat_map[categoria_name], platos_table=platos_table, available_columns=platos_columns)
                counters[f"platos_{action}"] += 1
            except Exception as row_err:
                counters["platos_skipped"] += 1
                errors.append(f"[platos fila {idx + 2}] error DB: {row_err}")

        conn.commit()
        print("Actualización completada ✅")
        print(
            f"Categorías -> insertadas: {counters['categorias_inserted']}, "
            f"actualizadas: {counters['categorias_updated']}, "
            f"omitidas: {counters['categorias_skipped']}"
        )
        print(
            f"Platos -> insertados: {counters['platos_inserted']}, "
            f"actualizados: {counters['platos_updated']}, "
            f"omitidos: {counters['platos_skipped']}"
        )

        if warnings:
            print(f"Advertencias ({len(warnings)}):")
            for item in warnings[:MAX_ISSUES_TO_PRINT]:
                print(f" - {item}")
            if len(warnings) > MAX_ISSUES_TO_PRINT:
                print(f" - ... y {len(warnings) - MAX_ISSUES_TO_PRINT} más")

        if errors:
            print(f"Filas con error/omitidas ({len(errors)}):")
            for item in errors[:MAX_ISSUES_TO_PRINT]:
                print(f" - {item}")
            if len(errors) > MAX_ISSUES_TO_PRINT:
                print(f" - ... y {len(errors) - MAX_ISSUES_TO_PRINT} más")
    except Exception as e:
        conn.rollback()
        print("Error ❌:", e, file=sys.stderr)
        sys.exit(1)
    finally:
        conn.close()

if __name__ == "__main__":
    main()
