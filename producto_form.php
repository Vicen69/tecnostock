<?php
require 'auth.php';
require 'conexion.php';

/* ---------- ¿Crear o editar? ---------- */
$idParam  = $_POST['id'] ?? $_GET['id'] ?? null;
$id       = null;
$producto = null;

if ($idParam !== null && $idParam !== '') {
    $id = filter_var($idParam, FILTER_VALIDATE_INT);
    if (!$id) {
        $_SESSION['mensaje'] = ['tipo' => 'danger', 'texto' => 'Producto inválido.'];
        header('Location: productos.php');
        exit;
    }

    $stmt = $pdo->prepare("SELECT * FROM producto WHERE ID_producto = ?");
    $stmt->execute([$id]);
    $producto = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$producto) {
        $_SESSION['mensaje'] = ['tipo' => 'danger', 'texto' => 'El producto no existe.'];
        header('Location: productos.php');
        exit;
    }
    if ((int)$producto['estado'] !== 1) {
        $_SESSION['mensaje'] = ['tipo' => 'warning', 'texto' => 'No se puede editar un producto desactivado.'];
        header('Location: productos.php');
        exit;
    }
}
$editando = $producto !== null;

/* ---------- Categorías disponibles ---------- */
$stmt = $pdo->prepare(
    "SELECT ID_categoria, nombre FROM categoria
     WHERE estado = 1 OR ID_categoria = ? ORDER BY nombre"
);
$stmt->execute([$editando ? $producto['ID_categoria'] : 0]);
$categorias = $stmt->fetchAll(PDO::FETCH_ASSOC);
$idsCategoria = array_column($categorias, 'ID_categoria');

/* ---------- Valores iniciales del formulario ---------- */
$datos = [
    'codigo'       => $editando ? $producto['codigo']       : '',
    'nombre'       => $editando ? $producto['nombre']       : '',
    'descripcion'  => $editando ? $producto['descripcion']  : '',
    'precio'       => $editando ? $producto['precio']       : '',
    'stock_actual' => $editando ? $producto['stock_actual'] : '0',
    'stock_minimo' => $editando ? $producto['stock_minimo'] : '0',
    'ID_categoria' => $editando ? $producto['ID_categoria'] : '',
];
$errores = [];

/* ---------- Procesar formulario ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $datos['nombre']       = trim($_POST['nombre'] ?? '');
    $datos['descripcion']  = trim($_POST['descripcion'] ?? '');
    $datos['precio']       = trim($_POST['precio'] ?? '');
    $datos['stock_minimo'] = trim($_POST['stock_minimo'] ?? '');
    $datos['ID_categoria'] = $_POST['ID_categoria'] ?? '';

    if (!$editando) {
        $datos['codigo']       = trim($_POST['codigo'] ?? '');
        $datos['stock_actual'] = trim($_POST['stock_actual'] ?? '');
    }

    // Código (solo al crear)
    if (!$editando) {
        if ($datos['codigo'] === '') {
            $errores[] = 'El código es obligatorio.';
        } elseif (mb_strlen($datos['codigo']) > 30) {
            $errores[] = 'El código no puede superar los 30 caracteres.';
        }
    }

    // Nombre
    if ($datos['nombre'] === '') {
        $errores[] = 'El nombre es obligatorio.';
    } elseif (mb_strlen($datos['nombre']) > 100) {
        $errores[] = 'El nombre no puede superar los 100 caracteres.';
    }

    // Descripción
    if (mb_strlen($datos['descripcion']) > 255) {
        $errores[] = 'La descripción no puede superar los 255 caracteres.';
    }

    // Precio
    $precioTxt = str_replace(',', '.', $datos['precio']);
    if ($precioTxt === '' || !is_numeric($precioTxt)) {
        $errores[] = 'El precio es obligatorio y debe ser un número.';
    } elseif ((float)$precioTxt < 0) {
        $errores[] = 'El precio no puede ser negativo.';
    } elseif ((float)$precioTxt > 99999999.99) {
        $errores[] = 'El precio es demasiado alto.';
    }

    // Stock mínimo
    $opcionesStock = ['options' => ['min_range' => 0, 'max_range' => 1000000]];
    $stockMinimo = filter_var($datos['stock_minimo'], FILTER_VALIDATE_INT, $opcionesStock);
    if ($stockMinimo === false) {
        $errores[] = 'El stock mínimo debe ser un número entero mayor o igual a 0.';
    }

    // Stock inicial (solo al crear)
    $stockInicial = 0;
    if (!$editando) {
        $stockInicial = filter_var($datos['stock_actual'], FILTER_VALIDATE_INT, $opcionesStock);
        if ($stockInicial === false) {
            $errores[] = 'El stock inicial debe ser un número entero mayor o igual a 0.';
        }
    }

    // Categoría
    $idCategoria = filter_var($datos['ID_categoria'], FILTER_VALIDATE_INT);
    if (!$idCategoria || !in_array($idCategoria, $idsCategoria)) {
        $errores[] = 'Debes seleccionar una categoría válida.';
    }

    // Código duplicado (solo al crear)
    if (!$editando && !$errores) {
        $stmt = $pdo->prepare("SELECT 1 FROM producto WHERE codigo = ?");
        $stmt->execute([$datos['codigo']]);
        if ($stmt->fetch()) {
            $errores[] = 'Ya existe un producto con ese código.';
        }
    }

    /* ---------- Guardar ---------- */
    if (!$errores) {
        $precio      = round((float)$precioTxt, 2);
        $descripcion = $datos['descripcion'] !== '' ? $datos['descripcion'] : null;

        try {
            if ($editando) {
                $stmt = $pdo->prepare(
                    "UPDATE producto
                     SET nombre = ?, descripcion = ?, precio = ?, stock_minimo = ?, ID_categoria = ?
                     WHERE ID_producto = ?"
                );
                $stmt->execute([$datos['nombre'], $descripcion, $precio, $stockMinimo, $idCategoria, $id]);
                $_SESSION['mensaje'] = ['tipo' => 'success', 'texto' => 'Producto actualizado correctamente.'];
            } else {
                $pdo->beginTransaction();

                $stmt = $pdo->prepare(
                    "INSERT INTO producto
                     (codigo, nombre, descripcion, precio, stock_actual, stock_minimo, estado, ID_categoria)
                     VALUES (?, ?, ?, ?, ?, ?, 1, ?)"
                );
                $stmt->execute([
                    $datos['codigo'], $datos['nombre'], $descripcion,
                    $precio, $stockInicial, $stockMinimo, $idCategoria
                ]);
                $nuevoId = $pdo->lastInsertId();

                // El stock inicial queda registrado como una entrada, para no perder historial
                if ($stockInicial > 0) {
                    $stmt = $pdo->prepare(
                        "INSERT INTO movimiento (tipo, cantidad, observacion, ID_producto, ID_usuario)
                         VALUES ('entrada', ?, 'Stock inicial', ?, ?)"
                    );
                    $stmt->execute([$stockInicial, $nuevoId, $_SESSION['id_usuario']]);
                }

                $pdo->commit();
                $_SESSION['mensaje'] = ['tipo' => 'success', 'texto' => 'Producto registrado correctamente.'];
            }

            header('Location: productos.php');
            exit;

        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($e->getCode() === '23000') {
                $errores[] = 'Ya existe un producto con ese código.';
            } else {
                $errores[] = 'No se pudo guardar el producto. Intenta nuevamente.';
            }
        }
    }
}

require 'header.php';
?>

<div class="row justify-content-center">
  <div class="col-lg-7">
    <h2 class="mb-3"><?= $editando ? 'Editar producto' : 'Nuevo producto' ?></h2>

    <?php if ($errores): ?>
      <div class="alert alert-danger">
        <ul class="mb-0">
          <?php foreach ($errores as $err): ?>
            <li><?= htmlspecialchars($err) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <form method="POST" id="formProducto" novalidate>
      <?php if ($editando): ?>
        <input type="hidden" name="id" value="<?= (int)$id ?>">
      <?php endif; ?>

      <div class="mb-3">
        <label for="codigo" class="form-label">Código</label>
        <input type="text" id="codigo" name="codigo" class="form-control" maxlength="30"
               value="<?= htmlspecialchars($datos['codigo']) ?>"
               <?= $editando ? 'readonly' : '' ?>>
        <div class="invalid-feedback"></div>
        <?php if ($editando): ?>
          <div class="form-text">El código no se puede modificar.</div>
        <?php endif; ?>
      </div>

      <div class="mb-3">
        <label for="nombre" class="form-label">Nombre</label>
        <input type="text" id="nombre" name="nombre" class="form-control" maxlength="100"
               value="<?= htmlspecialchars($datos['nombre']) ?>">
        <div class="invalid-feedback"></div>
      </div>

      <div class="mb-3">
        <label for="descripcion" class="form-label">Descripción</label>
        <textarea id="descripcion" name="descripcion" class="form-control" rows="2"
                  maxlength="255"><?= htmlspecialchars($datos['descripcion'] ?? '') ?></textarea>
        <div class="invalid-feedback"></div>
      </div>

      <div class="row">
        <div class="col-md-6 mb-3">
          <label for="precio" class="form-label">Precio</label>
          <input type="number" id="precio" name="precio" class="form-control"
                 step="0.01" min="0" value="<?= htmlspecialchars($datos['precio']) ?>">
          <div class="invalid-feedback"></div>
        </div>

        <div class="col-md-6 mb-3">
          <label for="ID_categoria" class="form-label">Categoría</label>
          <select id="ID_categoria" name="ID_categoria" class="form-select">
            <option value="">Selecciona...</option>
            <?php foreach ($categorias as $c): ?>
              <option value="<?= (int)$c['ID_categoria'] ?>"
                <?= (string)$datos['ID_categoria'] === (string)$c['ID_categoria'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($c['nombre']) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <div class="invalid-feedback"></div>
        </div>
      </div>

      <div class="row">
        <div class="col-md-6 mb-3">
          <label for="stock_actual" class="form-label">
            <?= $editando ? 'Stock actual' : 'Stock inicial' ?>
          </label>
          <input type="number" id="stock_actual" name="stock_actual" class="form-control"
                 step="1" min="0" value="<?= htmlspecialchars($datos['stock_actual']) ?>"
                 <?= $editando ? 'readonly' : '' ?>>
          <div class="invalid-feedback"></div>
          <?php if ($editando): ?>
            <div class="form-text">El stock solo cambia registrando movimientos.</div>
          <?php endif; ?>
        </div>

        <div class="col-md-6 mb-3">
          <label for="stock_minimo" class="form-label">Stock mínimo</label>
          <input type="number" id="stock_minimo" name="stock_minimo" class="form-control"
                 step="1" min="0" value="<?= htmlspecialchars($datos['stock_minimo']) ?>">
          <div class="invalid-feedback"></div>
        </div>
      </div>

      <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary">
          <?= $editando ? 'Guardar cambios' : 'Registrar producto' ?>
        </button>
        <a href="productos.php" class="btn btn-outline-secondary">Cancelar</a>
      </div>
    </form>
  </div>
</div>

<script>
// Validaciones del lado cliente (se repiten en PHP, que es la validación definitiva)
const form = document.getElementById('formProducto');
const editando = <?= $editando ? 'true' : 'false' ?>;

function marcar(campo, mensaje) {
  const feedback = campo.parentElement.querySelector('.invalid-feedback');
  if (mensaje) {
    campo.classList.add('is-invalid');
    feedback.textContent = mensaje;
    return false;
  }
  campo.classList.remove('is-invalid');
  feedback.textContent = '';
  return true;
}

function esEntero(valor) {
  return /^\d+$/.test(valor.trim());
}

form.addEventListener('submit', function (e) {
  let valido = true;

  if (!editando) {
    const codigo = form.codigo;
    if (codigo.value.trim() === '') valido = marcar(codigo, 'El código es obligatorio.') && valido;
    else if (codigo.value.trim().length > 30) valido = marcar(codigo, 'Máximo 30 caracteres.') && valido;
    else marcar(codigo, '');
  }

  const nombre = form.nombre;
  if (nombre.value.trim() === '') valido = marcar(nombre, 'El nombre es obligatorio.') && valido;
  else if (nombre.value.trim().length > 100) valido = marcar(nombre, 'Máximo 100 caracteres.') && valido;
  else marcar(nombre, '');

  const descripcion = form.descripcion;
  if (descripcion.value.trim().length > 255) valido = marcar(descripcion, 'Máximo 255 caracteres.') && valido;
  else marcar(descripcion, '');

  const precio = form.precio;
  if (precio.value.trim() === '' || isNaN(precio.value)) valido = marcar(precio, 'Ingresa un precio válido.') && valido;
  else if (parseFloat(precio.value) < 0) valido = marcar(precio, 'El precio no puede ser negativo.') && valido;
  else marcar(precio, '');

  const categoria = form.ID_categoria;
  if (categoria.value === '') valido = marcar(categoria, 'Selecciona una categoría.') && valido;
  else marcar(categoria, '');

  if (!editando) {
    const stockActual = form.stock_actual;
    if (!esEntero(stockActual.value)) valido = marcar(stockActual, 'Ingresa un entero mayor o igual a 0.') && valido;
    else marcar(stockActual, '');
  }

  const stockMinimo = form.stock_minimo;
  if (!esEntero(stockMinimo.value)) valido = marcar(stockMinimo, 'Ingresa un entero mayor o igual a 0.') && valido;
  else marcar(stockMinimo, '');

  if (!valido) e.preventDefault();
});
</script>

<?php require 'footer.php'; ?>