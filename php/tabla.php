<?php
$profesiones = [
    'soldadura' => 'Soldadura',
    'informatica' => 'Informática',
    'socio' => 'Asistencia Sociosanitaria',
];
$error = null;
$tipoCodigo = $_GET['tipo'] ?? 'soldadura';
if (!is_string($tipoCodigo) || !array_key_exists($tipoCodigo, $profesiones)) {
    $tipoCodigo = 'soldadura';
}
$_REQUEST['tipo'] = $tipoCodigo;

try {
    $conexion = new PDO('mysql:host=localhost;dbname=CAE;charset=utf8mb4', 'root', '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $nombre = trim($_POST['nombre'] ?? '');
        $apellidos = trim($_POST['apellidos'] ?? '');
        $dni = trim($_POST['dni'] ?? '');
        $fechaNacimiento = trim($_POST['f_nac'] ?? '');
        $telefono = trim($_POST['tlf'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $profesion = $_POST['profesion'] ?? '';
        $jornadaParcial = $_POST['jornadaParcial'] ?? null;
        $idiomas = $_POST['idiomas'] ?? [];
        $fechaValida = DateTime::createFromFormat('!Y-m-d', $fechaNacimiento);

        if (
            $nombre === '' || $apellidos === '' || $dni === '' || $telefono === '' ||
            !filter_var($email, FILTER_VALIDATE_EMAIL) || !$fechaValida ||
            $fechaValida->format('Y-m-d') !== $fechaNacimiento ||
            !is_string($profesion) || !array_key_exists($profesion, $profesiones) ||
            !in_array((string) $jornadaParcial, ['0', '1'], true) || !is_array($idiomas)
        ) {
            throw new InvalidArgumentException('Revisa los datos del formulario.');
        }

        $idiomasValidos = ['Euskera', 'Inglés'];
        foreach ($idiomas as $idioma) {
            if (!is_string($idioma) || !in_array($idioma, $idiomasValidos, true)) {
                throw new InvalidArgumentException('El idioma seleccionado no es válido.');
            }
        }

        $consulta = $conexion->prepare(
            'INSERT INTO SOLICITUD (nombre, apellidos, dni, f_nac, tlf, email, profesion, jornadaParcial, idiomas)
            VALUES (:nombre, :apellidos, :dni, :f_nac, :tlf, :email, :profesion, :jornadaParcial, :idiomas)'
        );
        $consulta->execute([
            'nombre' => $nombre,
            'apellidos' => $apellidos,
            'dni' => $dni,
            'f_nac' => $fechaNacimiento,
            'tlf' => $telefono,
            'email' => $email,
            'profesion' => $profesion,
            'jornadaParcial' => (int) $jornadaParcial,
            'idiomas' => implode(', ', array_unique($idiomas)),
        ]);

        header('Location: tabla.php?tipo=' . urlencode($profesion) . '&guardada=1');
        exit;
    }
} catch (InvalidArgumentException $exception) {
    $error = $exception->getMessage();
} catch (PDOException $exception) {
    $error = 'No se ha podido completar la operación. Comprueba que MySQL está iniciado y que existe la base de datos CAE.';
}
?>
<html>
    <head>
        <title>Examen de Desarrollo web en entorno servidor</title>
        <link rel="icon" type="image/png" sizes="32x32" href="../imagenes/favicon.jpeg">
        <link rel="stylesheet" type="text/css" href="../estilos/estilos.css">

    </head>
    <body>
        <h1>Centro de Ayuda al Empleo</h1>
        <h2>    
            <?php
                $tipo = "Soldadura";
                if ($_REQUEST['tipo'] == 'informatica'){
                    $tipo = 'Informática';
                } elseif ($_REQUEST['tipo'] == 'socio'){
                    $tipo = "Asistencia Sociosanitaria";
                }
                echo "Solicitudes de $tipo";
            ?>
        </h2>

        <?php if ($error !== null): ?>
            <p role="alert"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p>
        <?php elseif (isset($_GET['guardada'])): ?>
            <p role="status">La solicitud se ha guardado correctamente.</p>
        <?php endif; ?>

        <?php 
        // Aquí tenéis que crear la tabla de solicitantes de ese tipo
        if ($error === null) {
            try {
                $consulta = $conexion->prepare(
                    'SELECT id, nombre, apellidos, dni, f_nac, tlf, email, profesion, jornadaParcial, idiomas
                    FROM SOLICITUD WHERE profesion = :profesion ORDER BY apellidos, nombre'
                );
                $consulta->execute(['profesion' => $tipoCodigo]);
                $solicitudes = $consulta->fetchAll();

                if (count($solicitudes) > 0) {
                    echo '<table><tr><th>ID</th><th>Nombre</th><th>Apellidos</th><th>DNI</th><th>Fecha de nacimiento</th><th>Teléfono</th><th>Email</th><th>Profesión</th><th>Jornada</th><th>Idiomas</th></tr>';
                    foreach ($solicitudes as $solicitud) {
                        echo '<tr>';
                        echo '<td>' . htmlspecialchars($solicitud['id'], ENT_QUOTES, 'UTF-8') . '</td>';
                        echo '<td>' . htmlspecialchars($solicitud['nombre'], ENT_QUOTES, 'UTF-8') . '</td>';
                        echo '<td>' . htmlspecialchars($solicitud['apellidos'], ENT_QUOTES, 'UTF-8') . '</td>';
                        echo '<td>' . htmlspecialchars($solicitud['dni'], ENT_QUOTES, 'UTF-8') . '</td>';
                        echo '<td>' . htmlspecialchars($solicitud['f_nac'], ENT_QUOTES, 'UTF-8') . '</td>';
                        echo '<td>' . htmlspecialchars($solicitud['tlf'], ENT_QUOTES, 'UTF-8') . '</td>';
                        echo '<td>' . htmlspecialchars($solicitud['email'], ENT_QUOTES, 'UTF-8') . '</td>';
                        echo '<td>' . htmlspecialchars($profesiones[$solicitud['profesion']], ENT_QUOTES, 'UTF-8') . '</td>';
                        echo '<td>' . ((int) $solicitud['jornadaParcial'] === 1 ? 'Parcial' : 'Completa') . '</td>';
                        echo '<td>' . htmlspecialchars($solicitud['idiomas'], ENT_QUOTES, 'UTF-8') . '</td>';
                        echo '</tr>';
                    }
                    echo '</table>';
                } else {
                    echo '<p>No hay solicitudes para mostrar.</p>';
                }
            } catch (PDOException $exception) {
                echo '<p role="alert">No se ha podido consultar la base de datos CAE.</p>';
            }
        }
        ?>
        <button onclick="location.href='../html/index.html'">Volver al formulario</button>
    </body>
</html>