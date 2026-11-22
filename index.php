<?php
session_start();
require 'conexion.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $correo = trim($_POST['correo'] ?? '');
    $clave  = $_POST['clave'] ?? '';

    if ($correo === '' || $clave === '') {
        $error = 'Debes ingresar correo y clave.';
    } else {
        $stmt = $pdo->prepare("SELECT ID_usuario, nombre, clave FROM usuario WHERE correo = ? AND estado = 1");
        $stmt->execute([$correo]);
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($usuario && password_verify($clave, $usuario['clave'])) {
            $_SESSION['id_usuario'] = $usuario['ID_usuario'];
            $_SESSION['nombre'] = $usuario['nombre'];
            header('Location: inicio.php');
            exit;
        } else {
            $error = 'Correo o clave incorrectos.';
        }
    }
}

require 'header.php';
?>

<div class="row justify-content-center">
  <div class="col-md-4">
    <h2 class="mb-3">Iniciar sesión</h2>

    <?php if ($error): ?>
      <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">
      <div class="mb-3">
        <label class="form-label">Correo</label>
        <input type="email" name="correo" class="form-control" required>
      </div>
      <div class="mb-3">
        <label class="form-label">Clave</label>
        <input type="password" name="clave" class="form-control" required>
      </div>
      <button type="submit" class="btn btn-primary w-100">Entrar</button>
    </form>
  </div>
</div>

<?php require 'footer.php'; ?>