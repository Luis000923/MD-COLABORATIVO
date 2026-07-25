\---

grupo: G0X
tarea: Consultas SQL sobre Guía 1 y Guía 2
fecha\_entrega: 2026-07-24
integrante\_1\_carnet: 2026010179
integrante\_1\_nombre: Tatiana Vanessa Mendoza Ruano
integrante\_2\_carnet: 2026010235
integrante\_2\_nombre: Luis Ernesto Galdamez Vides 
integrante\_3\_carnet: 2026011679
integrante\_3\_nombre: Herbert Geovanny Solano Chávez 
integrante\_4\_carnet: 2026010976
integrante\_4\_nombre: Jorge Antonio Velásquez Vasquez 
integrante\_5\_carnet: 2026011059
integrante\_5\_nombre: Medina Rodas Roberto Alejandro
---

integrante\_6\_carnet: 2026010071
integrante\_6\_nombre: Gonzalez Benitez Irvin Alexander
---



## Datos de referencia

Ejecuta primero este script en tu esquema de Oracle Database para crear las tablas y cargar los datos sobre los que trabajarán las 4 consultas.

```sql
CREATE TABLE clientes (
  id\\\_cliente  NUMBER(5)    PRIMARY KEY,
  nombre      VARCHAR2(60) NOT NULL,
  email       VARCHAR2(60),
  telefono    VARCHAR2(20)
);

CREATE TABLE productos (
  id\\\_producto NUMBER(5)     PRIMARY KEY,
  nombre      VARCHAR2(60)  NOT NULL,
  precio      NUMBER(6,2),
  stock       NUMBER(5)
);

CREATE TABLE pedidos (
  id\\\_pedido   NUMBER(6)    PRIMARY KEY,
  id\\\_cliente  NUMBER(5)    REFERENCES clientes(id\\\_cliente),
  fecha       DATE,
  total       NUMBER(8,2)
);

INSERT INTO clientes VALUES (1, 'Carlos Gómez',   'carlos@mail.com', '7011-2233');
INSERT INTO clientes VALUES (2, 'Beatriz Lima',   'bea@mail.com',    '7022-3344');
INSERT INTO clientes VALUES (3, 'Jorge Alas',     'jorge@mail.com',  '7033-4455');
INSERT INTO clientes VALUES (4, 'Rosa Martínez',  'rosa@mail.com',   '7044-5566');

INSERT INTO productos VALUES (1, 'Mouse inalámbrico', 12.50, 40);
INSERT INTO productos VALUES (2, 'Teclado mecánico',  35.00, 15);
INSERT INTO productos VALUES (3, 'Monitor 24"',       145.00, 8);
INSERT INTO productos VALUES (4, 'Cámara web HD',     28.00, 10);

INSERT INTO pedidos VALUES (501, 1, DATE '2026-07-10', 47.50);
INSERT INTO pedidos VALUES (502, 2, DATE '2026-07-11', 145.00);
INSERT INTO pedidos VALUES (503, 1, DATE '2026-07-12', 35.00);
INSERT INTO pedidos VALUES (504, 3, DATE '2026-07-13', 12.50);
INSERT INTO pedidos VALUES (505, 1, DATE '2026-07-14', 28.00);

COMMIT;
```

\---

## Consulta 1 — Guía 1: Elementos de BD (Peso: 25%)

Enunciado: Listar el nombre y el email de todos los clientes, ordenados alfabéticamente por nombre.

```sql
-- SELECT nombre, email 

FROM clientes 

ORDER BY nombre ASC;

```

\---

## Consulta 2 — Guía 1: Elementos de BD (Peso: 25%)

Enunciado: Mostrar todos los pedidos del cliente "Carlos Gómez" (busca primero su id\_cliente), incluyendo id\_pedido, fecha y total.

```sql
-- SELECT id\_pedido, fecha, total 

FROM pedidos 

WHERE id\_cliente = 1;

```

\---

## Consulta 3 — Guía 2: Comandos SQL (Peso: 25%)

Enunciado: Insertar un nuevo producto ("Audífonos Bluetooth", precio 22.00, stock 25) y luego actualizar el stock de "Mouse inalámbrico" restando 3 unidades. Confirma los cambios.

```sql
-- INSERT INTO productos (id\_producto, nombre, precio, stock) 

VALUES (5, 'Audífonos Bluetooth', 22.00, 25);



UPDATE productos 

SET stock = stock - 3 

WHERE id\_producto = 1;



COMMIT;

```

\---

## Consulta 4 — Guía 2: Funciones de agregación (Peso: 25%)

Enunciado: Mostrar, para cada cliente (id\_cliente), cuántos pedidos ha realizado y cuánto ha gastado en total, ordenado de mayor a menor gasto total.

```sql
-- SELECT id\_cliente, 

&#x20;      COUNT(id\_pedido) AS total\_pedidos, 

&#x20;      SUM(total) AS gasto\_total 

FROM pedidos 

GROUP BY id\_cliente 

ORDER BY gasto\_total DESC;
```

