<?php
require 'auth.php';
require 'header.php';
?>
<h2>Bienvenido, <?= htmlspecialchars($_SESSION['nombre']) ?></h2>
<p>Usa el menú para gestionar productos y movimientos.</p>
<?php require 'footer.php'; ?>