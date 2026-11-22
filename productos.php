<?php
require 'auth.php';
require 'conexion.php';

/* ---------- Desactivar producto (POST) ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'desactivar') {
    $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);

    if ($id) {
        try {
            $stmt = $pdo->prepare("UPDATE producto SET estado = 0 WHERE ID_producto = ?");
            $stmt->execute([$id]);
            $_SESSION['mensaje'] = ['tipo' => 'success', 'texto' => 'Producto desactivado correctamente.'];
        } catch (PDOException $e) {
            $_SESSION['mensaje'] = ['tipo' => 'danger', 'texto' => 'No se pudo desactivar el producto.'];
        }
    } else {
        $_SESSION['mensaje'] = ['tipo' => 'danger', 'texto' => 'Producto inválido.'];
    }

    header('Location: productos.php');
    exit;
}

/* ---------- Mensaje flash (viene de esta u otras páginas) ---------- */
$mensaje = $_SESSION['mensaje'] ?? null;
unset($_SESSION['mensaje']);

/* ---------- Búsqueda ---------- */
$buscar = trim($_GET['q'] ?? '');

$sql = "SELECT p.ID_producto, p.codigo, p.nombre, p.precio, p.stock_actual,
               p.stock_minimo, p.estado, c.nombre AS categoria
        FROM producto p
        INNER JOIN categoria c ON c.ID_categoria = p.ID_categoria";
$params = [];

if ($buscar !== '') {
    $sql .= " WHERE p.nombre LIKE ? OR p.codigo LIKE ? OR c.nombre LIKE ?";
    $like = '%' . $buscar . '%';
    $params = [$like, $like, $like];
}

$sql .= " ORDER BY p.nombre ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

require 'header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h2 class="mb-0">Productos</h2>
  <a href="producto_form.php" class="btn btn-primary">+ Nuevo producto</a>
</div>

<?php if ($mensaje): ?>
  <div class="alert alert-<?= htmlspecialchars($mensaje['tipo']) ?> alert-dismissible fade show">
    <?= htmlspecialchars($mensaje['texto']) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
<?php endif; ?>

<form method="GET" class="row g-2 mb-3">
  <div class="col-md-6">
    <input type="text" name="q" class="form-control"
           placeholder="Buscar por nombre, código o categoría"
           value="<?= htmlspecialchars($buscar) ?>">
  </div>
  <div class="col-auto">
    <button type="submit" class="btn btn-outline-primary">Buscar</button>
    <?php if ($buscar !== ''): ?>
      <a href="productos.php" class="btn btn-outline-secondary">Limpiar</a>
    <?php endif; ?>
  </div>
</form>

<div class="table-responsive">
  <table class="table table-hover align-middle">
    <thead class="table-dark">
      <tr>
        <th>Código</th>
        <th>Nombre</th>
        <th>Categoría</th>
        <th class="text-end">Precio</th>
        <th class="text-end">Stock actual</th>
        <th class="text-end">Stock mínimo</th>
        <th>Estado</th>
        <th>Acciones</th>
      </tr>
    </thead>
    <tbody>
      <?php if (count($productos) === 0): ?>
        <tr>
          <td colspan="8" class="text-center text-muted py-4">
            No se encontraron productos.
          </td>
        </tr>
      <?php endif; ?>

      <?php foreach ($productos as $p): ?>
        <?php
          $activo    = (int)$p['estado'] === 1;
          $stockBajo = $activo && $p['stock_actual'] < $p['stock_minimo'];
        ?>
        <tr class="<?= $stockBajo ? 'table-danger' : '' ?> <?= !$activo ? 'text-muted' : '' ?>">
          <td><?= htmlspecialchars($p['codigo']) ?></td>
          <td>
            <?= htmlspecialchars($p['nombre']) ?>
            <?php if ($stockBajo): ?>
              <span class="badge bg-danger ms-1">Stock bajo</span>
            <?php endif; ?>
          </td>
          <td><?= htmlspecialchars($p['categoria']) ?></td>
          <td class="text-end">$<?= number_format($p['precio'], 0, ',', '.') ?></td>
          <td class="text-end"><?= (int)$p['stock_actual'] ?></td>
          <td class="text-end"><?= (int)$p['stock_minimo'] ?></td>
          <td>
            <?php if ($activo): ?>
              <span class="badge bg-success">Activo</span>
            <?php else: ?>
              <span class="badge bg-secondary">Desactivado</span>
            <?php endif; ?>
          </td>
          <td>
            <?php if ($activo): ?>
              <a href="producto_form.php?id=<?= (int)$p['ID_producto'] ?>"
                 class="btn btn-sm btn-outline-primary">Editar</a>

              <form method="POST" class="d-inline"
                    onsubmit="return confirm('¿Desactivar este producto? No se eliminará, solo dejará de estar disponible.');">
                <input type="hidden" name="accion" value="desactivar">
                <input type="hidden" name="id" value="<?= (int)$p['ID_producto'] ?>">
                <button type="submit" class="btn btn-sm btn-outline-danger">Desactivar</button>
              </form>
            <?php else: ?>
              <span class="small">—</span>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php require 'footer.php'; ?>