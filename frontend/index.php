<?php
declare(strict_types=1);
session_start();

$apiBase = rtrim(getenv('API_URL') ?: 'http://localhost:8080/api/productos', '/');
if (!filter_var($apiBase, FILTER_VALIDATE_URL) || !in_array((string) parse_url($apiBase, PHP_URL_SCHEME), ['http', 'https'], true)) {
    http_response_code(500);
    exit('La variable API_URL no es una URL HTTP válida.');
}
if (!extension_loaded('curl')) {
    http_response_code(500);
    exit('Activa la extensión curl en php.ini y reinicia PHP.');
}
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(24));
}
function e(mixed $value): string {
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function api(string $method, string $endpoint = '', ?array $data = null): array {
    global $apiBase;
    $ch = curl_init($apiBase . $endpoint);
    $options = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_CONNECTTIMEOUT => 4,
        CURLOPT_TIMEOUT => 12,
        CURLOPT_HTTPHEADER => ['Accept: application/json', 'Content-Type: application/json'],
    ];
    if ($data !== null) {
        $options[CURLOPT_POSTFIELDS] = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
    curl_setopt_array($ch, $options);
    $body = curl_exec($ch);
    $error = curl_error($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($body === false) {
        return ['ok' => false, 'status' => 0, 'data' => null, 'error' => 'No se pudo contactar con Quarkus: ' . $error];
    }
    $decoded = $body !== '' ? json_decode($body, true) : null;
    return ['ok' => $code >= 200 && $code < 300, 'status' => $code, 'data' => $decoded,
            'error' => (is_array($decoded) ? ($decoded['error'] ?? null) : null) ?: 'Error HTTP ' . $code];
}
function redirectWith(string $message, string $type = 'success'): never {
    $_SESSION['flash'] = ['text' => $message, 'type' => $type];
    header('Location: /', true, 303);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf'], (string) ($_POST['csrf'] ?? ''))) {
        http_response_code(403);
        exit('Token de formulario incorrecto.');
    }
    $action = (string) ($_POST['action'] ?? '');
    $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($action === 'delete') {
        if (!$id) redirectWith('ID inválido.', 'error');
        $result = api('DELETE', '/' . $id);
        redirectWith($result['ok'] ? 'Producto eliminado lógicamente.' : $result['error'], $result['ok'] ? 'success' : 'error');
    }
    if ($action === 'create' || $action === 'update') {
        if ($action === 'update' && !$id) redirectWith('ID inválido.', 'error');
        $nombre = trim((string) ($_POST['nombre'] ?? ''));
        $proveedor = trim((string) ($_POST['proveedor'] ?? ''));
        $fecha = (string) ($_POST['fecha_vencimiento'] ?? '');
        $precio = (string) ($_POST['precio'] ?? '');
        $stock = (string) ($_POST['stock'] ?? '');
        $dt = DateTimeImmutable::createFromFormat('!Y-m-d', $fecha);
        if ($nombre === '' || strlen($nombre) > 400 || $proveedor === '' || strlen($proveedor) > 400 ||
            !$dt || $dt->format('Y-m-d') !== $fecha || !preg_match('/^\d{1,8}(\.\d{1,2})?$/D', $precio) ||
            !preg_match('/^(0|[1-9]\d*)$/D', $stock) || (float) $stock > 2147483647) {
            redirectWith('Revisa los datos: nombre/proveedor (máx. 100), fecha, precio y stock.', 'error');
        }
        $payload = [
            'nombre' => $nombre, 'proveedor' => $proveedor, 'fecha_vencimiento' => $fecha,
            'precio' => (float) $precio, 'stock' => (int) $stock,
        ];
        $result = $action === 'create' ? api('POST', '', $payload) : api('PUT', '/' . $id, $payload);
        redirectWith($result['ok'] ? ($action === 'create' ? 'Producto registrado.' : 'Producto actualizado.') : $result['error'], $result['ok'] ? 'success' : 'error');
    }
    redirectWith('Acción no válida.', 'error');
}

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
$listResult = api('GET');
$productos = $listResult['ok'] && is_array($listResult['data']) ? $listResult['data'] : [];
$editId = filter_var($_GET['editar'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$edit = null;
if ($editId) {
    $r = api('GET', '/' . $editId);
    if ($r['ok'] && is_array($r['data'])) $edit = $r['data'];
    else $flash = ['text' => $r['error'], 'type' => 'error'];
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Minimarket | Gestión de productos</title>
<style>
:root{font-family:system-ui,-apple-system,Segoe UI,sans-serif;color:#1b2b3b;background:#eef3f8}
*{box-sizing:border-box}body{margin:0}.container{max-width:1150px;margin:36px auto;padding:0 20px}
header{background:linear-gradient(110deg,#173a66,#127c8e);color:#fff;padding:26px 32px;border-radius:16px}
h1{margin:0;font-size:29px}header p{margin:8px 0 0;color:#d5f2fb}
.panel{background:#fff;box-shadow:0 5px 24px #1d3a5c12;border-radius:14px;margin-top:22px;padding:24px}
h2{margin:0 0 18px;font-size:20px}.fields{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:16px}
label{display:flex;flex-direction:column;gap:6px;font-weight:600;font-size:14px}input{border:1px solid #c8d5e0;border-radius:8px;padding:11px;font:inherit;width:100%}
.actions{display:flex;align-items:center;gap:10px;margin-top:18px}.btn,button{background:#126a84;color:white;border:0;border-radius:8px;padding:10px 14px;font:inherit;font-size:14px;font-weight:600;cursor:pointer;text-decoration:none;display:inline-block}
.btn.gray{background:#64748b}.btn.edit{background:#235ca8}.btn.danger{background:#ba3343}button:hover,.btn:hover{filter:brightness(.93)}
.table-wrap{overflow-x:auto}table{width:100%;border-collapse:collapse;text-align:left}th{background:#ecf2f8;color:#28425c}th,td{padding:12px;border-bottom:1px solid #e2e9ef;font-size:14px;white-space:nowrap}td.buttons{display:flex;gap:8px}
.alert{padding:13px 16px;border-radius:9px;margin-top:18px}.success{background:#daf5e7;color:#136844}.error{background:#fce3e7;color:#9a2638}
.small{color:#64748b;font-size:13px;margin-top:14px}@media(max-width:760px){.fields{grid-template-columns:1fr}header{padding:24px}td.buttons{display:table-cell}td.buttons>*{margin:3px}}
</style>
</head>
<body><main class="container">
<header><h1>Minimarket • Hito 3</h1><p>Gestión de inventario — PHP → API REST Quarkus → MySQL</p></header>
<?php if ($flash): ?><div class="alert <?= e($flash['type']) ?>"><?= e($flash['text']) ?></div><?php endif; ?>
<?php if (!$listResult['ok']): ?><div class="alert error">No se pudo cargar el listado: <?= e($listResult['error']) ?>. Inicia MySQL y el backend.</div><?php endif; ?>
<section class="panel">
<h2><?= $edit ? 'Editar producto #' . e($edit['id']) : 'Registrar producto' ?></h2>
<form method="post" action="/">
<input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
<input type="hidden" name="action" value="<?= $edit ? 'update' : 'create' ?>">
<?php if ($edit): ?><input type="hidden" name="id" value="<?= e($edit['id']) ?>"><?php endif; ?>
<div class="fields">
<label>Nombre<input name="nombre" maxlength="100" required value="<?= e($edit['nombre'] ?? '') ?>" placeholder="Ej. Arroz 1 kg"></label>
<label>Proveedor<input name="proveedor" maxlength="100" required value="<?= e($edit['proveedor'] ?? '') ?>" placeholder="Ej. Distribuidora Andina"></label>
<label>Fecha de vencimiento<input type="date" name="fecha_vencimiento" required value="<?= e($edit['fecha_vencimiento'] ?? '') ?>"></label>
<label>Precio (Bs)<input type="number" name="precio" min="0" max="99999999.99" step="0.01" required value="<?= e($edit['precio'] ?? '') ?>"></label>
<label>Stock<input type="number" name="stock" min="0" max="2147483647" step="1" required value="<?= e($edit['stock'] ?? '') ?>"></label>
</div>
<div class="actions"><button type="submit"><?= $edit ? 'Guardar cambios' : 'Crear producto' ?></button><?php if ($edit): ?><a href="/" class="btn gray">Cancelar</a><?php endif; ?></div>
</form></section>
<section class="panel"><h2>Productos activos (<?= count($productos) ?>)</h2><div class="table-wrap"><table>
<thead><tr><th>ID</th><th>Nombre</th><th>Proveedor</th><th>Fecha vencimiento</th><th>Precio (Bs)</th><th>Stock</th><th>Acciones</th></tr></thead>
<tbody><?php foreach ($productos as $p): ?><tr>
<td><?= e($p['id'] ?? '') ?></td><td><?= e($p['nombre'] ?? '') ?></td><td><?= e($p['proveedor'] ?? '') ?></td>
<td><?= e($p['fecha_vencimiento'] ?? '') ?></td><td><?= e(number_format((float) ($p['precio'] ?? 0), 2, '.', '')) ?></td><td><?= e($p['stock'] ?? '') ?></td>
<td class="buttons"><a class="btn edit" href="/?editar=<?= (int) ($p['id'] ?? 0) ?>">Editar</a>
<form method="post" action="/" onsubmit="return confirm('¿Eliminar lógicamente este producto? Seguirá guardado en MySQL.');">
<input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) ($p['id'] ?? 0) ?>">
<button class="btn danger" type="submit">Eliminar</button></form></td></tr><?php endforeach; ?>
<?php if (!$productos): ?><tr><td colspan="7">No hay productos activos para mostrar.</td></tr><?php endif; ?></tbody></table></div>
<p class="small">El botón Eliminar solo marca eliminado=1 en MySQL; no borra el registro.</p></section>
</main></body></html>
