USE tecnostock;

USE tecnostock;

INSERT INTO usuario (nombre, correo, clave, estado) VALUES
('Encargado de Inventario', 'admin@tecnostock.cl', '$2y$10$/79BaejAnOe0PLKL8pqnlen6YGqD9Hty/yfO8LVW6Z9XjGaF7fXFW', 1);

INSERT INTO categoria (nombre, descripcion, estado) VALUES
('Periféricos', 'Mouse, teclados y cámaras', 1),
('Audio', 'Audífonos y parlantes', 1),
('Cables y cargadores', 'Cables USB y cargadores', 1),
('Accesorios', 'Soportes y otros accesorios', 1);