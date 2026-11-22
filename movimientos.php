<?php
require 'auth.php';
require 'conexion.php';

/* ---------- Mensaje flash ---------- */
$mensaje = $_SESSION['mensaje'] ?? null;
unset($_SESSION['mensaje']);

$errores = [];
$datos = ['id_producto' => '', 'tipo' => '', 'cantidad' => '', 'observacion' => ''];

/* ---------- Registrar movimiento ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $datos['id_producto'] = $_POST['id_producto'] ?? '';
    $datos['tipo']        = $_POST['tipo'] ?? '';
    $datos['cantidad']    = trim($_POST['cantidad'] ?? '');
    $datos['observacion'] = trim($_POST['observacion'] ?? '');

    $idProducto = filter_var($datos['id_producto'], FILTER_VALIDATE_INT);
    $cantidad   = filter_var(
        $datos['cantidad'],
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1, 'max_range' => 1000000]]
    );

    if (!$idProducto) {
        $errores[] = 'Debes seleccionar un producto.';
    }
    if (!in_array($datos['tipo'], ['entrada', 'salida'], true)) {
        $errores[] = 'Debes seleccionar el tipo de movimiento.';
    }
    if ($cantidad === false) {
        $errores[] = 'La cantidad debe ser un número entero mayor a 0.';
    }
    if (mb_strlen($datos['observacion']) > 255) {
        $errores[] = 'La observación no puede superar los 255 caracteres.';
    }

    if (!$errores) {
        try {
            $pdo->beginTransaction();

            // Se bloquea la fila del producto para que el stock leído sea el vigente
            $stmt = $pdo->prepare(
                "SELECT nombre, stock_actual, stock_minimo
                 FROM producto WHERE ID_producto = ? AND estado = 1 FOR UPDATE"
            );
            $stmt->execute([$idProducto]);
            $producto = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$producto) {
                throw new RuntimeException('El producto no existe o está desactivado.');
            }

            if ($datos['tipo'] === 'salida' && $cantidad > $producto['stock_actual']) {
                throw new RuntimeException(
                    'Stock insuficiente. Disponible: ' . (int)$producto['stock_actual'] .
                    ', solicitado: ' . $cantidad . '.'
                );
            }

            $observacion = $datos['observacion'] !== '' ? $datos['observacion'] : null;

            // 1) Registrar el movimiento
            $stmt = $pdo->prepare(
                "INSERT INTO movimiento (tipo, cantidad, observacion, ID_producto, ID_usuario)
                 VALUES (?, ?, ?, ?, ?)"
            );
            $stmt->execute([$datos['tipo'], $cantidad, $observacion, $idProducto, $_SESSION['id_usuario']]);

            // 2) Actualizar el stock
            if ($datos['tipo'] === 'entrada') {
                $stmt = $pdo->prepare("UPDATE producto SET stock_actual = stock_actual + ? WHERE ID_producto = ?");
            } else {
                $stmt = $pdo->prepare("UPDATE producto SET stock_actual = stock_actual - ? WHERE ID_producto = ?");
            }
            $stmt->execute([$cantidad, $idProducto]);

            $pdo->commit();

            $nuevoStock = $datos['tipo'] === 'entrada'
                ? $producto['stock_actual'] + $cantidad
                : $producto['stock_actual'] - $cantidad;

            $texto = ucfirst($datos['tipo']) . ' registrada correctamente. Stock actual de "' .
                     $producto['nombre'] . '": ' . $nuevoStock . '.';
            $tipoAlerta = 'success';

            if ($nuevoStock < $producto['stock_minimo']) {
                $texto .= ' Atención: el stock quedó por debajo del mínimo (' . (int)$producto['stock_minimo'] . ').';
                $tipoAlerta = 'warning';
            }

            $_SESSION['mensaje'] = ['tipo' => $tipoAlerta, 'texto' => $texto];
            header('Location: movimientos.php');
            exit;

        } catch (RuntimeException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errores[] = $e->getMessage();
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errores[] = 'No se pudo registrar el movimiento. Intenta nuevamente.';
        }
    }
}

/* ---------- Productos activos para el selector ---------- */
$productos = $pdo->query(
    "SELECT ID_producto, codigo, nombre, stock_actual
     FROM producto WHERE estado = 1 ORDER BY nombre"
)->fetchAll(PDO::FETCH_ASSOC);

/* ---------- Historial (últimos 50) ---------- */
$historial = $pdo->query(
    "SELECT m.fecha_hora, m.tipo, m.cantidad, m.observacion,
            p.codigo, p.nombre AS producto, u.nombre AS usuario
     FROM movimiento m
     INNER JOIN producto p ON p.ID_producto = m.ID_producto
     INNER JOIN usuario u ON u.ID_usuario = m.ID_usuario
     ORDER BY m.fecha_hora DESC, m.ID_movimiento DESC
     LIMIT 50"
)->fetchAll(PDO::FETCH_ASSOC);

require 'header.php';
?>

<h2 class="mb-3">Movimientos de inventario</h2>

<?php if ($mensaje): ?>
  <div class="alert alert-<?= htmlspecialchars($mensaje['tipo']) ?> alert-dismissible fade show">
    <?= htmlspecialchars($mensaje['texto']) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
<?php endif; ?>

<?php if ($errores): ?>
  <div class="alert alert-danger">
    <ul class="mb-0">
      <?php foreach ($errores as $err): ?>
        <li><?= htmlspecialchars($err) ?></li>
      <?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<div class="card mb-4">
  <div class="card-header">Registrar movimiento</div>
  <div class="card-body">
    <form method="POST" id="formMovimiento" novalidate>
      <div class="row">
        <div class="col-md-5 mb-3">
          <label for="id_producto" class="form-label">Producto</label>
          <select id="id_producto" name="id_producto" class="form-select">
            <option value="">Selecciona...</option>
            <?php foreach ($productos as $p): ?>
              <option value="<?= (int)$p['ID_producto'] ?>"
                      data-stock="<?= (int)$p['stock_actual'] ?>"
                      <?= (string)$datos['id_producto'] === (string)$p['ID_producto'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($p['codigo'] . ' - ' . $p['nombre']) ?>
                (stock: <?= (int)$p['stock_actual'] ?>)
              </option>
            <?php endforeach; ?>
          </select>
          <div class="invalid-feedback"></div>
        </div>

        <div class="col-md-3 mb-3">
          <label for="tipo" class="form-label">Tipo</label>
          <select id="tipo" name="tipo" class="form-select">
            <option value="">Selecciona...</option>
            <option value="entrada" <?= $datos['tipo'] === 'entrada' ? 'selected' : '' ?>>Entrada</option>
            <option value="salida"  <?= $datos['tipo'] === 'salida'  ? 'selected' : '' ?>>Salida</option>
          </select>
          <div class="invalid-feedback"></div>
        </div>

        <div class="col-md-4 mb-3">
          <label for="cantidad" class="form-label">Cantidad</label>
          <input type="number" id="cantidad" name="cantidad" class="form-control"
                 step="1" min="1" value="<?= htmlspecialchars($datos['cantidad']) ?>">
          <div class="invalid-feedback"></div>
          <div class="form-text" id="ayudaStock"></div>
        </div>
      </div>

      <div class="mb-3">
        <label for="observacion" class="form-label">Observación (opcional)</label>
        <input type="text" id="observacion" name="observacion" class="form-control" maxlength="255"
               value="<?= htmlspecialchars($datos['observacion']) ?>">
        <div class="invalid-feedback"></div>
      </div>

      <button type="submit" class="btn btn-primary">Registrar movimiento</button>
    </form>
  </div>
</div>

<h4 class="mb-3">Historial (últimos 50 movimientos)</h4>

<div class="table-responsive">
  <table class="table table-hover align-middle">
    <thead class="table-dark">
      <tr>
        <th>Fecha</th>
        <th>Producto</th>
        <th>Tipo</th>
        <th class="text-end">Cantidad</th>
        <th>Observación</th>
        <th>Usuario</th>
      </tr>
    </thead>
    <tbody>
      <?php if (count($historial) === 0): ?>
        <tr>
          <td colspan="6" class="text-center text-muted py-4">Aún no hay movimientos registrados.</td>
        </tr>
      <?php endif; ?>

      <?php foreach ($historial as $m): ?>
        <tr>
          <td><?= htmlspecialchars(date('d-m-Y H:i', strtotime($m['fecha_hora']))) ?></td>
          <td><?= htmlspecialchars($m['codigo'] . ' - ' . $m['producto']) ?></td>
          <td>
            <?php if ($m['tipo'] === 'entrada'): ?>
              <span class="badge bg-success">Entrada</span>
            <?php else: ?>
              <span class="badge bg-danger">Salida</span>
            <?php endif; ?>
          </td>
          <td class="text-end"><?= (int)$m['cantidad'] ?></td>
          <td><?= htmlspecialchars($m['observacion'] ?? '') ?></td>
          <td><?= htmlspecialchars($m['usuario']) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<script>
// Validaciones del lado cliente (se repiten en PHP, que es la validación definitiva)
const form      = document.getElementById('formMovimiento');
const selProd   = document.getElementById('id_producto');
const selTipo   = document.getElementById('tipo');
const inpCant   = document.getElementById('cantidad');
const ayudaStock = document.getElementById('ayudaStock');

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

function stockSeleccionado() {
  const opcion = selProd.options[selProd.selectedIndex];
  return opcion && opcion.dataset.stock !== undefined ? parseInt(opcion.dataset.stock, 10) : null;
}

function actualizarAyuda() {
  const stock = stockSeleccionado();
  ayudaStock.textContent = (stock !== null && selTipo.value === 'salida')
    ? 'Máximo disponible para salida: ' + stock
    : '';
}

selProd.addEventListener('change', actualizarAyuda);
selTipo.addEventListener('change', actualizarAyuda);
actualizarAyuda();

form.addEventListener('submit', function (e) {
  let valido = true;

  if (selProd.value === '') valido = marcar(selProd, 'Selecciona un producto.') && valido;
  else marcar(selProd, '');

  if (selTipo.value === '') valido = marcar(selTipo, 'Selecciona el tipo.') && valido;
  else marcar(selTipo, '');

  const cantTxt = inpCant.value.trim();
  if (!/^\d+$/.test(cantTxt) || parseInt(cantTxt, 10) < 1) {
    valido = marcar(inpCant, 'Ingresa un entero mayor a 0.') && valido;
  } else {
    const cantidad = parseInt(cantTxt, 10);
    const stock = stockSeleccionado();
    if (selTipo.value === 'salida' && stock !== null && cantidad > stock) {
      valido = marcar(inpCant, 'La salida supera el stock disponible (' + stock + ').') && valido;
    } else {
      marcar(inpCant, '');
    }
  }

  if (!valido) e.preventDefault();
});
</script>

<?php require 'footer.php'; ?>