USE db_minimarket_pro413;

CREATE TABLE IF NOT EXISTS Producto (
  id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(100) NOT NULL,
  proveedor VARCHAR(100) NOT NULL,
  fecha_vencimiento DATE NOT NULL,
  precio DECIMAL(10,2) NOT NULL,
  stock INT NOT NULL,
  eliminado TINYINT(1) NOT NULL DEFAULT 0,
  CONSTRAINT chk_precio CHECK (precio >= 0),
  CONSTRAINT chk_stock CHECK (stock >= 0)
);

INSERT INTO Producto (nombre, proveedor, fecha_vencimiento, precio, stock) VALUES
  ('Arroz 1 kg', 'Distribuidora Andina', '2027-12-31', 12.50, 40),
  ('Leche 1 L', 'Lacteos Bolivia', '2027-05-20', 8.90, 25),
  ('Aceite 900 ml', 'Proveedor Central', '2027-11-30', 17.00, 18);
