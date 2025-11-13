#!/usr/bin/env python3
"""
api_menu.py - FastAPI para subir Excel y actualizar MySQL.
Instalar:
  pip install fastapi uvicorn pandas openpyxl pymysql python-multipart python-slugify
Ejecutar:
  uvicorn api_menu:app --reload
"""
from fastapi import FastAPI, UploadFile, File, Form
import pandas as pd
import pymysql
from typing import Dict, Any
from slugify import slugify

app = FastAPI(title="Menu Uploader")

def connect_mysql(host, db, user, pwd, charset="utf8mb4"):
    return pymysql.connect(
        host=host, user=user, password=pwd, database=db,
        charset=charset, cursorclass=pymysql.cursors.DictCursor, autocommit=False
    )

def ensure_unique_indexes(conn):
    with conn.cursor() as c:
        c.execute("""
            SELECT COUNT(1) AS cnt FROM INFORMATION_SCHEMA.STATISTICS
            WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='categorias' AND INDEX_NAME='uq_categorias_nombre';
        """)
        if c.fetchone()["cnt"] == 0:
            c.execute("CREATE UNIQUE INDEX uq_categorias_nombre ON categorias(nombre);")
        c.execute("""
            SELECT COUNT(1) AS cnt FROM INFORMATION_SCHEMA.STATISTICS
            WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='platos' AND INDEX_NAME='uq_platos_nombre_categoria';
        """)
        if c.fetchone()["cnt"] == 0:
            c.execute("CREATE UNIQUE INDEX uq_platos_nombre_categoria ON platos(nombre, categoria_id);")

def upsert_categoria(conn, nombre, descripcion="", orden_menu=None, activo=1):
    with conn.cursor() as c:
        c.execute("SELECT id FROM categorias WHERE nombre=%s", (nombre,))
        r = c.fetchone()
        if r:
            c.execute("""UPDATE categorias SET descripcion=%s, orden_menu=%s, activo=%s WHERE id=%s""",
                      (descripcion, orden_menu, activo, r["id"]))
            return r["id"], "updated"
        else:
            c.execute("""INSERT INTO categorias (nombre, descripcion, orden_menu, activo) VALUES (%s,%s,%s,%s)""",
                      (nombre, descripcion, orden_menu, activo))
            return c.lastrowid, "inserted"

def upsert_plato(conn, data: Dict[str, Any], categoria_id: int):
    with conn.cursor() as c:
        c.execute("SELECT id FROM platos WHERE nombre=%s AND categoria_id=%s", (data["nombre"], categoria_id))
        r = c.fetchone()
        values = (
            data.get("descripcion",""), data.get("precio"),
            data.get("stock_disponible",0), data.get("tiempo_preparacion"),
            data.get("imagen_url",""), data.get("ingredientes",""),
            data.get("es_especial",0), data.get("activo",1)
        )
        if r:
            c.execute("""
                UPDATE platos SET descripcion=%s, precio=%s, stock_disponible=%s, tiempo_preparacion=%s,
                                 imagen_url=%s, ingredientes=%s, es_especial=%s, activo=%s
                WHERE id=%s
            """, (*values, r["id"]))
            return r["id"], "updated"
        else:
            c.execute("""
                INSERT INTO platos (categoria_id, nombre, descripcion, precio, stock_disponible, tiempo_preparacion,
                                    imagen_url, ingredientes, es_especial, activo)
                VALUES (%s,%s,%s,%s,%s,%s,%s,%s,%s,%s)
            """, (categoria_id, data["nombre"], *values))
            return c.lastrowid, "inserted"

@app.post("/upload")
async def upload_excel(
    file: UploadFile = File(...),
    mysql_host: str = Form("localhost"),
    mysql_db: str = Form(...),
    mysql_user: str = Form(...),
    mysql_pass: str = Form(...),
):
    content = await file.read()
    xls = pd.ExcelFile(content)
    cats = pd.read_excel(xls, "categorias").fillna({"descripcion":"", "orden_menu":None, "activo":1})
    platos = pd.read_excel(xls, "platos").fillna({
        "descripcion":"", "precio":None, "stock_disponible":0, "tiempo_preparacion":None,
        "imagen_url":"", "ingredientes":"", "es_especial":0, "activo":1
    })
    cats["nombre"] = cats["nombre"].astype(str).str.strip()
    platos["nombre"] = platos["nombre"].astype(str).str.strip()
    platos["categoria"] = platos["categoria"].astype(str).str.strip()

    conn = connect_mysql(mysql_host, mysql_db, mysql_user, mysql_pass)
    inserted = {"categorias":0, "platos":0}
    updated  = {"categorias":0, "platos":0}
    try:
        ensure_unique_indexes(conn)
        cat_map = {}
        for _, r in cats.iterrows():
            if not r["nombre"]: continue
            cid, act = upsert_categoria(conn, r["nombre"], r.get("descripcion",""), r.get("orden_menu"), int(r.get("activo",1)))
            cat_map[r["nombre"]] = cid
            inserted["categorias"] += 1 if act=="inserted" else 0
            updated["categorias"]  += 1 if act=="updated" else 0

        for _, r in platos.iterrows():
            if not r["nombre"] or not r["categoria"]: continue
            cid = cat_map.get(r["categoria"])
            if cid is None:
                cid, _ = upsert_categoria(conn, r["categoria"])
                cat_map[r["categoria"]] = cid
            pid, act = upsert_plato(conn, r.to_dict(), cid)
            inserted["platos"] += 1 if act=="inserted" else 0
            updated["platos"]  += 1 if act=="updated" else 0

        conn.commit()
        return {"status":"ok","inserted":inserted,"updated":updated}
    except Exception as e:
        conn.rollback()
        return {"status":"error","detail":str(e)}
    finally:
        conn.close()
