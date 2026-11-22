USE tecnostock;

create table usuario (
  ID_usuario INT PRIMARY KEY AUTO_INCREMENT,
  nombre VARCHAR(100) NOT NULL,
  correo VARCHAR(150) NOT NULL UNIQUE,
  clave VARCHAR(255) NOT NULL,
  estado TINYINT NOT NULL DEFAULT 1
);

create table categoria (
  ID_categoria INT PRIMARY KEY AUTO_INCREMENT,
  nombre VARCHAR(100) NOT NULL,
  descripcion VARCHAR(255),
  estado TINYINT NOT NULL DEFAULT 1
);

create table producto (
  ID_producto INT PRIMARY KEY AUTO_INCREMENT,
  codigo VARCHAR(30) NOT NULL UNIQUE,
  nombre VARCHAR(100) NOT NULL,
  descripcion VARCHAR(255),
  precio DECIMAL(10,2) NOT NULL CHECK (precio >= 0),
  stock_actual INT NOT NULL CHECK (stock_actual >= 0),
  stock_minimo INT NOT NULL CHECK (stock_minimo >= 0),
  estado TINYINT NOT NULL DEFAULT 1,
  ID_categoria INT NOT NULL,
  FOREIGN KEY (ID_categoria) REFERENCES categoria(ID_categoria)
);

create table movimiento (
  ID_movimiento INT PRIMARY KEY AUTO_INCREMENT,
  fecha_hora DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  tipo ENUM('entrada','salida') NOT NULL,
  cantidad INT NOT NULL CHECK (cantidad > 0),
  observacion VARCHAR(255),
  ID_producto INT NOT NULL,
  ID_usuario INT NOT NULL,
  FOREIGN KEY (ID_producto) REFERENCES producto(ID_producto),
  FOREIGN KEY (ID_usuario) REFERENCES usuario(ID_usuario)
);
