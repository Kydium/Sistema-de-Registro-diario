<?php
// ============================================================
// API Backend – Sistema de Registro de Servicios Técnicos
// ============================================================

require_once 'config.php';

// Sesión y CORS
session_set_cookie_params(SESSION_LIFETIME);
session_start();

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

// Helper: respuesta JSON
function resp(bool $ok, $data = null, string $msg = ''): void {
    echo json_encode(['ok' => $ok, 'data' => $data, 'msg' => $msg]);
    exit;
}

// Helper: usuario logueado
function requireAuth(): array {
    if (empty($_SESSION['usuario'])) {
        http_response_code(401);
        resp(false, null, 'No autenticado');
    }
    return $_SESSION['usuario'];
}

// Helper: solo admin
function requireAdmin(): array {
    $u = requireAuth();
    if ($u['rol'] !== 'administrador') {
        http_response_code(403);
        resp(false, null, 'Sin permisos');
    }
    return $u;
}

// Leer body JSON
$body = [];
$raw  = file_get_contents('php://input');
if ($raw) $body = json_decode($raw, true) ?? [];

$action = $_GET['action'] ?? $body['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

// ── RUTAS ─────────────────────────────────────────────────────────────────

switch ($action) {

    // ── AUTH ────────────────────────────────────────────────────────────────
    case 'login':
        $usuario  = trim($body['usuario'] ?? '');
        $password = $body['password'] ?? '';
        if (!$usuario || !$password) resp(false, null, 'Datos incompletos');

        $db   = getDB();
        $stmt = $db->prepare('SELECT * FROM usuarios WHERE usuario = ?');
        $stmt->execute([$usuario]);
        $row  = $stmt->fetch();

        if (!$row || $row['password'] !== hash('sha256', $password)) {
            resp(false, null, 'Usuario o contraseña incorrectos');
        }

        $_SESSION['usuario'] = [
            'id'     => $row['id'],
            'usuario'=> $row['usuario'],
            'nombre' => $row['nombre'],
            'rol'    => $row['rol'],
        ];
        resp(true, $_SESSION['usuario']);

    case 'logout':
        session_destroy();
        resp(true);

    case 'me':
        if (empty($_SESSION['usuario'])) resp(false, null, 'no_auth');
        resp(true, $_SESSION['usuario']);

    // ── REGISTROS ───────────────────────────────────────────────────────────
    case 'get_registros':
        requireAuth();
        $db     = getDB();
        $fecha  = $_GET['fecha']    ?? '';
        $busq   = $_GET['busqueda'] ?? '';

        $where  = [];
        $params = [];

        if ($fecha) {
            $where[]  = 'r.fecha = ?';
            $params[] = $fecha;
        }
        if ($busq) {
            $where[]  = '(r.nombre LIKE ? OR r.documento LIKE ? OR r.telefono LIKE ? OR r.telefono2 LIKE ? OR r.contexto LIKE ?)';
            $like     = '%' . $busq . '%';
            array_push($params, $like, $like, $like, $like, $like);
        }

        $sql  = 'SELECT r.* FROM registros r';
        if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
        $sql .= ' ORDER BY r.creado_at DESC';

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        // Adjuntar extras
        foreach ($rows as &$row) {
            $row['completado'] = (bool)$row['completado'];
            $e = $db->prepare('SELECT campo_id, valor FROM registro_extras WHERE registro_id = ?');
            $e->execute([$row['id']]);
            $row['extras'] = [];
            foreach ($e->fetchAll() as $ex) {
                $row['extras'][$ex['campo_id']] = $ex['valor'];
            }
        }
        resp(true, $rows);

    case 'save_registro':
        $u    = requireAuth();
        $body = $body; // ya cargado arriba
        $db   = getDB();

        $nombre    = trim($body['nombre']    ?? '');
        $fecha     = $body['fecha']          ?? '';
        $documento = trim($body['documento'] ?? '');
        $telefono  = trim($body['telefono']  ?? '');
        $tipo      = $body['tipo']           ?? 'otro';

        if (!$nombre || !$fecha || !$documento || !$telefono) {
            resp(false, null, 'Campos obligatorios incompletos');
        }

        $id        = $body['id']        ?? null;
        $telefono2 = $body['telefono2'] ?? '';
        $contexto  = $body['contexto']  ?? '';
        $extras    = $body['extras']    ?? [];

        if ($id) {
            // Actualizar
            requireAdmin();
            $stmt = $db->prepare(
                'UPDATE registros SET nombre=?,fecha=?,documento=?,telefono=?,telefono2=?,tipo=?,contexto=? WHERE id=?'
            );
            $stmt->execute([$nombre, $fecha, $documento, $telefono, $telefono2, $tipo, $contexto, $id]);
        } else {
            // Insertar
            $id = (int)(microtime(true) * 1000);
            $stmt = $db->prepare(
                'INSERT INTO registros (id,nombre,fecha,documento,telefono,telefono2,tipo,contexto,completado,creado_por)
                 VALUES (?,?,?,?,?,?,?,?,0,?)'
            );
            $stmt->execute([$id, $nombre, $fecha, $documento, $telefono, $telefono2, $tipo, $contexto, $u['nombre']]);
        }

        // Extras
        $db->prepare('DELETE FROM registro_extras WHERE registro_id = ?')->execute([$id]);
        if ($extras) {
            $ins = $db->prepare('INSERT INTO registro_extras (registro_id, campo_id, valor) VALUES (?,?,?)');
            foreach ($extras as $k => $v) {
                if ($v !== '' && $v !== null) $ins->execute([$id, $k, $v]);
            }
        }

        resp(true, ['id' => $id]);

    case 'toggle_completado':
        requireAdmin();
        $id = $body['id'] ?? null;
        if (!$id) resp(false, null, 'ID requerido');

        $db   = getDB();
        $stmt = $db->prepare('UPDATE registros SET completado = 1 - completado WHERE id = ?');
        $stmt->execute([$id]);
        resp(true);

    case 'delete_registro':
        requireAdmin();
        $id = $body['id'] ?? null;
        if (!$id) resp(false, null, 'ID requerido');

        $db = getDB();
        $db->prepare('DELETE FROM registros WHERE id = ?')->execute([$id]);
        resp(true);

    // ── CATEGORÍAS ──────────────────────────────────────────────────────────
    case 'get_categorias':
        requireAuth();
        $db   = getDB();
        $rows = $db->query('SELECT * FROM categorias ORDER BY orden, label')->fetchAll();
        resp(true, $rows);

    case 'save_categoria':
        requireAdmin();
        $id    = $body['id']    ?? null;
        $label = trim($body['label'] ?? '');
        $icon  = $body['icon']  ?? '🔧';
        $color = $body['color'] ?? '#475569';
        $bg    = $body['bg']    ?? '#eef2f6';

        if (!$label) resp(false, null, 'Nombre requerido');

        $db = getDB();
        if ($id) {
            $db->prepare('UPDATE categorias SET label=?,icon=?,color=?,bg=? WHERE id=?')
               ->execute([$label, $icon, $color, $bg, $id]);
        } else {
            $newId = strtolower(preg_replace('/[^a-z0-9]/i','_',$label)) . '_' . time();
            $orden = (int)$db->query('SELECT COUNT(*)+1 FROM categorias')->fetchColumn();
            $db->prepare('INSERT INTO categorias (id,label,icon,color,bg,orden) VALUES (?,?,?,?,?,?)')
               ->execute([$newId, $label, $icon, $color, $bg, $orden]);
            $id = $newId;
        }
        resp(true, ['id' => $id]);

    case 'delete_categoria':
        requireAdmin();
        $id = $body['id'] ?? null;
        if (!$id) resp(false, null, 'ID requerido');

        $db    = getDB();
        $enUso = (int)$db->prepare('SELECT COUNT(*) FROM registros WHERE tipo = ?')
                         ->execute([$id]) ? $db->query("SELECT COUNT(*) FROM registros WHERE tipo = '$id'")->fetchColumn() : 0;

        // Fix para contar correctamente
        $stmt = $db->prepare('SELECT COUNT(*) FROM registros WHERE tipo = ?');
        $stmt->execute([$id]);
        $enUso = (int)$stmt->fetchColumn();

        if ($enUso > 0) resp(false, null, 'No se puede eliminar: hay registros usando esta categoría');

        $total = (int)$db->query('SELECT COUNT(*) FROM categorias')->fetchColumn();
        if ($total <= 1) resp(false, null, 'Debe quedar al menos una categoría');

        $db->prepare('DELETE FROM categorias WHERE id = ?')->execute([$id]);
        resp(true);

    // ── CAMPOS EXTRA ────────────────────────────────────────────────────────
    case 'get_campos':
        requireAuth();
        $rows = getDB()->query('SELECT * FROM campos_extra ORDER BY orden, label')->fetchAll();
        resp(true, $rows);

    case 'save_campo':
        requireAdmin();
        $label = trim($body['label'] ?? '');
        $tipo  = $body['tipo']        ?? 'text';
        $ph    = $body['placeholder'] ?? '';
        if (!$label) resp(false, null, 'Nombre requerido');

        $db    = getDB();
        $newId = strtolower(preg_replace('/[^a-z0-9]/i','_',$label)) . '_' . time();
        $orden = (int)$db->query('SELECT COUNT(*)+1 FROM campos_extra')->fetchColumn();
        $db->prepare('INSERT INTO campos_extra (id,label,tipo,placeholder,orden) VALUES (?,?,?,?,?)')
           ->execute([$newId, $label, $tipo, $ph, $orden]);
        resp(true, ['id' => $newId]);

    case 'delete_campo':
        requireAdmin();
        $id = $body['id'] ?? null;
        if (!$id) resp(false, null, 'ID requerido');
        getDB()->prepare('DELETE FROM campos_extra WHERE id = ?')->execute([$id]);
        resp(true);

    default:
        http_response_code(400);
        resp(false, null, 'Acción no reconocida: ' . $action);
}
