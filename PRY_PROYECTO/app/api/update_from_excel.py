#!/usr/bin/env python3
"""
update_from_excel.py
Lee un Excel con dos hojas ("categorias" y "platos") y actualiza MySQL:
 - Tabla categorias_platos: id, nombre (UNICO), descripcion, orden_menu, activo
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
import sys
import pymysql
import pandas as pd
from slugify import slugify

CAT_SHEET = "categorias"
PLA_SHEET = "platos"
CAT_TABLE = "categorias_platos"  # Nombre real de la tabla en la BD
PLA_TABLE = "platos"  # Nombre real de la tabla en la BD

def connect_mysql(host, db, user, pwd, charset="utf8mb4"):
    return pymysql.connect(
        host=host, user=user, password=pwd, database=db,
        charset=charset, cursorclass=pymysql.cursors.DictCursor, autocommit=False
    )

def ensure_unique_indexes(conn):
    with conn.cursor() as c:
        # Crea índice único en categorias_platos.nombre si no existe
        c.execute("""
            SELECT COUNT(1) AS cnt FROM INFORMATION_SCHEMA.STATISTICS
            WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='categorias_platos' AND INDEX_NAME='uq_categorias_nombre';
        """)
        if c.fetchone()["cnt"] == 0:
            c.execute("CREATE UNIQUE INDEX uq_categorias_nombre ON categorias_platos(nombre);")
        # Crea índice compuesto en platos(nombre, categoria_id) si no existe
        c.execute("""
            SELECT COUNT(1) AS cnt FROM INFORMATION_SCHEMA.STATISTICS
            WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='platos' AND INDEX_NAME='uq_platos_nombre_categoria';
        """)
        if c.fetchone()["cnt"] == 0:
            c.execute("CREATE UNIQUE INDEX uq_platos_nombre_categoria ON platos(nombre, categoria_id);")

def upsert_categoria(conn, row):
    with conn.cursor() as c:
        # Buscar por nombre
        c.execute(f"SELECT id FROM {CAT_TABLE} WHERE nombre=%s", (row["nombre"],))
        r = c.fetchone()
        if r:
            # Update
            c.execute(f"""
                UPDATE {CAT_TABLE} SET descripcion=%s, orden_menu=%s, activo=%s
                WHERE id=%s
            """, (row["descripcion"], int(row["orden_menu"]) if pd.notna(row["orden_menu"]) else None,
                  int(row["activo"]) if pd.notna(row["activo"]) else 1, r["id"]))
            return r["id"], "updated"
        else:
            # Insert
            c.execute(f"""
                INSERT INTO {CAT_TABLE} (nombre, descripcion, orden_menu, activo)
                VALUES (%s,%s,%s,%s)
            """, (row["nombre"], row["descripcion"],
                  int(row["orden_menu"]) if pd.notna(row["orden_menu"]) else None,
                  int(row["activo"]) if pd.notna(row["activo"]) else 1))
            return c.lastrowid, "inserted"

def upsert_plato(conn, row, categoria_id):
    with conn.cursor() as c:
        c.execute(f"SELECT id FROM {PLA_TABLE} WHERE nombre=%s AND categoria_id=%s", (row["nombre"], categoria_id))
        r = c.fetchone()
        
        # Solo usar campos que existen en la tabla real
        fields = ["descripcion", "precio", "stock_disponible", "imagen_url", "activo"]
        values = (
            row.get("descripcion", ""),
            float(row["precio"]) if pd.notna(row.get("precio")) else 0,
            int(row["stock_disponible"]) if pd.notna(row.get("stock_disponible")) else 0,
            row.get("imagen_url", ""),
            int(row.get("activo", 1)) if pd.notna(row.get("activo")) else 1,
        )
        if r:
            c.execute(f"""
                UPDATE {PLA_TABLE} SET {', '.join(f + '=%s' for f in fields)}
                WHERE id=%s
            """, (*values, r["id"]))
            return r["id"], "updated"
        else:
            c.execute(f"""
                INSERT INTO {PLA_TABLE} (categoria_id, nombre, {', '.join(fields)})
                VALUES (%s,%s,{','.join(['%s']*len(values))})
            """, (categoria_id, row["nombre"], *values))
            return c.lastrowid, "inserted"

def load_frames(path):
    xls = pd.ExcelFile(path)
    cats = pd.read_excel(xls, CAT_SHEET)
    platos = pd.read_excel(xls, PLA_SHEET)
    
    # Agregar columnas faltantes con valores por defecto en categorias
    if 'descripcion' not in cats.columns:
        cats['descripcion'] = ""
    if 'orden_menu' not in cats.columns:
        cats['orden_menu'] = 0
    if 'activo' not in cats.columns:
        cats['activo'] = 1
    
    # Agregar columnas faltantes con valores por defecto en platos
    if 'descripcion' not in platos.columns:
        platos['descripcion'] = ""
    if 'precio' not in platos.columns:
        platos['precio'] = 0
    if 'stock_disponible' not in platos.columns:
        platos['stock_disponible'] = 0
    if 'tiempo_preparacion' not in platos.columns:
        platos['tiempo_preparacion'] = 0
    if 'imagen_url' not in platos.columns:
        platos['imagen_url'] = ""
    if 'ingredientes' not in platos.columns:
        platos['ingredientes'] = ""
    if 'es_especial' not in platos.columns:
        platos['es_especial'] = 0
    if 'activo' not in platos.columns:
        platos['activo'] = 1
    
    # Llenar valores nulos - Compatible con pandas 2.x
    cats = cats.fillna(value={"descripcion":"", "activo":1, "orden_menu":0})
    platos = platos.fillna(value={
        "descripcion":"", "precio":0, "stock_disponible":0, 
        "tiempo_preparacion":0, "imagen_url":"", "ingredientes":"", 
        "es_especial":0, "activo":1
    })
    
    # Normalizaciones
    cats["nombre"] = cats["nombre"].astype(str).str.strip()
    platos["nombre"] = platos["nombre"].astype(str).str.strip()
    platos["categoria"] = platos["categoria"].astype(str).str.strip()
    return cats, platos

def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--input", required=True)
    ap.add_argument("--mysql-host", default="localhost")
    ap.add_argument("--mysql-db", required=True)
    ap.add_argument("--mysql-user", required=True)
    ap.add_argument("--mysql-pass", required=True)
    args = ap.parse_args()

    cats_df, platos_df = load_frames(args.input)
    conn = connect_mysql(args.mysql_host, args.mysql_db, args.mysql_user, args.mysql_pass)
    try:
        ensure_unique_indexes(conn)

        # Upsert categorías
        cat_map = {}
        for _, row in cats_df.iterrows():
            if not row["nombre"]:
                continue
            cat_id, action = upsert_categoria(conn, row)
            cat_map[row["nombre"]] = cat_id

        # Upsert platos
        for _, row in platos_df.iterrows():
            if not row["nombre"] or not row["categoria"]:
                continue
            categoria_name = row["categoria"]
            if categoria_name not in cat_map:
                # Crear categoría al vuelo si no está
                cat_id, _ = upsert_categoria(conn, {
                    "nombre": categoria_name,
                    "descripcion": "",
                    "orden_menu": None,
                    "activo": 1
                })
                cat_map[categoria_name] = cat_id
            upsert_plato(conn, row, cat_map[categoria_name])

        conn.commit()
        print("Actualizacion exitosa")
    except Exception as e:
        conn.rollback()
        print(f"Error: {str(e)}", file=sys.stderr)
        sys.exit(1)
    finally:
        conn.close()

if __name__ == "__main__":
    main()
