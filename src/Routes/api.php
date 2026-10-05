<?php

require_once __DIR__ . '/../Models/BaseModel.php';
require_once __DIR__ . '/../Models/Entities.php';
require_once __DIR__ . '/../Models/Producto.php';
require_once __DIR__ . '/../Models/Venta.php';

route('GET', '/', function() {
    echo json_encode([
        'name' => 'Ventory API',
        'version' => '1.0.0',
        'status' => 'running'
    ]);
});

route('GET', '/test', function() {
    echo json_encode(['status' => 'ok']);
});

route('GET', '/db-check', function() {
    if (Database::testConnection()) {
        echo json_encode(['status' => 'ok', 'message' => 'base de datos conectada']);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'problema con la base de datos']);
    }
});

route('GET', '/productos', function() {
    $producto = new Producto();
    echo json_encode($producto->getAll());
});

route('GET', '/productos/{id}', function($id) {
    $producto = new Producto();
    echo json_encode($producto->getById($id));
});

route('POST', '/productos', function() {
    $data = json_decode(file_get_contents('php://input'), true);
    $producto = new Producto();

    if (isset($data['items']) && is_array($data['items'])) {
        echo json_encode($producto->saveWithRetenciones($data));
        return;
    }

    if (isset($data['id']) && $data['id'] !== '' && $data['id'] !== null) {
        $id = $data['id'];
        unset($data['id']);
        echo json_encode($producto->update($id, $data));
        return;
    }

    echo json_encode($producto->create($data));
});

route('PUT', '/productos/{id}', function($id) {
    $data = json_decode(file_get_contents('php://input'), true);
    $producto = new Producto();
    echo json_encode($producto->update($id, $data));
});

route('DELETE', '/productos/{id}', function($id) {
    $producto = new Producto();
    echo json_encode($producto->delete($id));
});

route('GET', '/productos/{id}/retenciones', function($id) {
    $producto = new Producto();
    echo json_encode($producto->getRetenciones($id));
});

route('POST', '/productos/{id}/retenciones', function($id) {
    $data = json_decode(file_get_contents('php://input'), true);
    $rows = isset($data['items']) ? $data['items'] : $data;
    $producto = new Producto();
    $result = $producto->saveRetenciones($id, $rows);
    if (!isset($result['success']) || $result['success'] !== true) {
        http_response_code(400);
    }
    echo json_encode($result);
});

route('GET', '/marcas', function() {
    $marca = new Marca();
    echo json_encode($marca->getAll());
});

route('POST', '/marcas', function() {
    $data = json_decode(file_get_contents('php://input'), true);
    $marca = new Marca();
    echo json_encode($marca->create($data));
});

route('GET', '/marcas/{id}', function($id) {
    $marca = new Marca();
    echo json_encode($marca->getById($id));
});

route('PUT', '/marcas/{id}', function($id) {
    $data = json_decode(file_get_contents('php://input'), true);
    $marca = new Marca();
    echo json_encode($marca->update($id, $data));
});

route('DELETE', '/marcas/{id}', function($id) {
    $marca = new Marca();
    echo json_encode($marca->delete($id));
});

route('GET', '/documentos', function() {
    $documento = new Documento();
    echo json_encode($documento->getAll());
});

route('POST', '/documentos', function() {
    $data = json_decode(file_get_contents('php://input'), true);
    $documento = new Documento();
    $result = $documento->create($data);
    if (!isset($result['success']) || $result['success'] !== true) {
        http_response_code(400);
    }
    echo json_encode($result);
});

route('GET', '/documentos/{id}', function($id) {
    $documento = new Documento();
    echo json_encode($documento->getById($id));
});

route('PUT', '/documentos/{id}', function($id) {
    $data = json_decode(file_get_contents('php://input'), true);
    $documento = new Documento();
    echo json_encode($documento->update($id, $data));
});

route('DELETE', '/documentos/{id}', function($id) {
    $documento = new Documento();
    echo json_encode($documento->delete($id));
});

route('GET', '/ventas', function() {
    $venta = new Venta();
    echo json_encode($venta->getAll());
});

route('POST', '/ventas', function() {
    $data = json_decode(file_get_contents('php://input'), true);
    if ($data === null) {
        http_response_code(400);
        echo json_encode(['error' => 'JSON invalido', 'success' => false]);
        return;
    }
    $venta = new Venta();
    $result = $venta->create($data);
    if (!isset($result['success']) || $result['success'] !== true) {
        http_response_code(400);
    }
    echo json_encode($result);
});

route('GET', '/ventas/{id}', function($id) {
    $venta = new Venta();
    echo json_encode($venta->getById($id));
});

route('PUT', '/ventas/{id}', function($id) {
    $data = json_decode(file_get_contents('php://input'), true);
    $venta = new Venta();
    echo json_encode($venta->update($id, $data));
});

route('PUT', '/ventas/lineas/{id}', function($id) {
    $data = json_decode(file_get_contents('php://input'), true);
    $venta = new Venta();
    $result = $venta->updateLinea($id, is_array($data) ? $data : []);
    if (!isset($result['success']) || $result['success'] !== true) {
        http_response_code(400);
    }
    echo json_encode($result);
});

route('POST', '/ventas/{id}/lineas', function($id) {
    $data = json_decode(file_get_contents('php://input'), true);
    $venta = new Venta();
    $result = $venta->addLinea($id, is_array($data) ? $data : []);
    if (!isset($result['success']) || $result['success'] !== true) {
        http_response_code(400);
    }
    echo json_encode($result);
});

route('DELETE', '/ventas/{id}', function($id) {
    $venta = new Venta();
    echo json_encode($venta->delete($id));
});

route('POST', '/ventas/{id}/cufe', function($id) {
    $venta = new Venta();
    $result = $venta->generateCufe($id);
    if (!isset($result['success']) || $result['success'] !== true) {
        http_response_code(400);
    }
    echo json_encode($result);
});

route('GET', '/unidades', function() {
    $unidad = new Unidad();
    echo json_encode($unidad->getAll());
});

route('GET', '/unidades/{id}', function($id) {
    $unidad = new Unidad();
    echo json_encode($unidad->getById($id));
});

route('POST', '/unidades', function() {
    $data = json_decode(file_get_contents('php://input'), true);
    $unidad = new Unidad();
    echo json_encode($unidad->create($data));
});

route('PUT', '/unidades/{id}', function($id) {
    $data = json_decode(file_get_contents('php://input'), true);
    $unidad = new Unidad();
    echo json_encode($unidad->update($id, $data));
});

route('DELETE', '/unidades/{id}', function($id) {
    $unidad = new Unidad();
    echo json_encode($unidad->delete($id));
});

route('GET', '/cotizaciones', function() {
    $cotizacion = new Cotizacion();
    echo json_encode($cotizacion->getAll());
});

route('POST', '/cotizaciones', function() {
    $data = json_decode(file_get_contents('php://input'), true);
    $cotizacion = new Cotizacion();
    $result = $cotizacion->create(is_array($data) ? $data : []);
    if (!isset($result['success']) || $result['success'] !== true) {
        http_response_code(400);
    }
    echo json_encode($result);
});

route('GET', '/cotizaciones/{id}', function($id) {
    $cotizacion = new Cotizacion();
    echo json_encode($cotizacion->getById($id));
});

route('PUT', '/cotizaciones/{id}', function($id) {
    $data = json_decode(file_get_contents('php://input'), true);
    $cotizacion = new Cotizacion();
    $result = $cotizacion->update($id, is_array($data) ? $data : []);
    if (!isset($result['success']) || $result['success'] !== true) {
        http_response_code(400);
    }
    echo json_encode($result);
});

route('GET', '/salidas', function() {
    $salida = new Salida();
    echo json_encode($salida->getAll());
});

route('POST', '/salidas', function() {
    $data = json_decode(file_get_contents('php://input'), true);
    $salida = new Salida();
    echo json_encode($salida->create($data));
});

route('GET', '/salidas/{id}', function($id) {
    $salida = new Salida();
    echo json_encode($salida->getById($id));
});

route('PUT', '/salidas/{id}/anular', function($id) {
    $salida = new Salida();
    echo json_encode($salida->anular($id));
});

route('GET', '/pedidos', function() {
    $pedido = new Pedido();
    echo json_encode($pedido->getAll());
});

route('POST', '/pedidos', function() {
    $data = json_decode(file_get_contents('php://input'), true);
    $pedido = new Pedido();
    echo json_encode($pedido->create($data));
});

route('GET', '/entradas', function() {
    $entrada = new Entrada();
    echo json_encode($entrada->getAll());
});

route('POST', '/entradas', function() {
    $data = json_decode(file_get_contents('php://input'), true);
    $entrada = new Entrada();
    echo json_encode($entrada->create($data));
});

route('GET', '/entradas/{id}', function($id) {
    $entrada = new Entrada();
    echo json_encode($entrada->getById($id));
});

route('GET', '/usuarios', function() {
    $usuario = new Usuario();
    echo json_encode($usuario->getAll());
});

route('POST', '/usuarios', function() {
    $data = json_decode(file_get_contents('php://input'), true);
    $usuario = new Usuario();
    echo json_encode($usuario->create($data));
});

route('GET', '/usuarios/{id}', function($id) {
    $usuario = new Usuario();
    echo json_encode($usuario->getById($id));
});

route('PUT', '/usuarios/{id}', function($id) {
    $data = json_decode(file_get_contents('php://input'), true);
    $usuario = new Usuario();
    echo json_encode($usuario->update($id, $data));
});

route('DELETE', '/usuarios/{id}', function($id) {
    $usuario = new Usuario();
    echo json_encode($usuario->delete($id));
});

route('GET', '/empresa', function() {
    $empresa = new Empresa();
    echo json_encode($empresa->get());
});

route('PUT', '/empresa', function() {
    $data = json_decode(file_get_contents('php://input'), true);
    $empresa = new Empresa();
    echo json_encode($empresa->updateData($data));
});

route('POST', '/empresa/logo', function() {
    $empresa = new Empresa();
    $result = $empresa->saveLogo($_FILES['logo'] ?? null);
    if (!isset($result['success']) || $result['success'] !== true) {
        http_response_code(400);
    }
    echo json_encode($result);
});

route('GET', '/configuraciones', function() {
    $configuracion = new Configuracion();
    echo json_encode($configuracion->getAllByReference());
});

route('GET', '/configuraciones/iddoc', function() {
    $configuracion = new Configuracion();
    echo json_encode($configuracion->getIdDocOptions());
});

route('GET', '/configuraciones/idtributo', function() {
    $configuracion = new Configuracion();
    echo json_encode($configuracion->getIdTributoOptions());
});

route('GET', '/configuraciones/idorganizacion', function() {
    $configuracion = new Configuracion();
    echo json_encode($configuracion->getIdOrganizacionOptions());
});

route('GET', '/configuraciones/idretencion', function() {
    $configuracion = new Configuracion();
    echo json_encode($configuracion->getIdRetencionOptions());
});

route('GET', '/configuraciones/pagoforma', function() {
    $configuracion = new Configuracion();
    echo json_encode($configuracion->getPagoFormaOptions());
});

route('GET', '/configuraciones/pagometodo', function() {
    $configuracion = new Configuracion();
    echo json_encode($configuracion->getPagoMetodoOptions());
});

route('GET', '/configuraciones/cufe', function() {
    $configuracion = new Configuracion();
    echo json_encode($configuracion->getCufe());
});

route('POST', '/configuraciones/cufe', function() {
    $data = json_decode(file_get_contents('php://input'), true);
    $items = isset($data['items']) ? $data['items'] : [];

    $configuracion = new Configuracion();
    $result = $configuracion->saveCufe($items);

    if (!isset($result['success']) || $result['success'] !== true) {
        http_response_code(400);
    }

    echo json_encode($result);
});

route('POST', '/configuraciones/cufe/token', function() {
    $configuracion = new Configuracion();
    $result = $configuracion->fetchCufeToken();

    if (!isset($result['success']) || $result['success'] !== true) {
        http_response_code(502);
    }

    echo json_encode($result);
});

route('POST', '/configuraciones', function() {
    $data = json_decode(file_get_contents('php://input'), true);
    $items = isset($data['items']) ? $data['items'] : [];

    $configuracion = new Configuracion();
    $result = $configuracion->saveByReference($items);

    if (!isset($result['success']) || $result['success'] !== true) {
        http_response_code(400);
    }

    echo json_encode($result);
});

route('GET', '/clientes', function() {
    $cliente = new Cliente();
    echo json_encode($cliente->getAll());
});

route('POST', '/clientes', function() {
    $data = json_decode(file_get_contents('php://input'), true);
    $cliente = new Cliente();
    echo json_encode($cliente->create($data));
});

route('PUT', '/clientes/{id}', function($id) {
    $data = json_decode(file_get_contents('php://input'), true);
    $cliente = new Cliente();
    echo json_encode($cliente->update($id, $data));
});

route('GET', '/proveedores', function() {
    $proveedor = new Proveedor();
    if (isset($_GET['q']) && trim($_GET['q']) !== '') {
        echo json_encode($proveedor->search(trim($_GET['q'])));
        return;
    }
    echo json_encode($proveedor->getAll());
});

route('POST', '/proveedores', function() {
    $data = json_decode(file_get_contents('php://input'), true);
    $proveedor = new Proveedor();
    echo json_encode($proveedor->create($data));
});

route('GET', '/proveedores/{id}', function($id) {
    $proveedor = new Proveedor();
    echo json_encode($proveedor->getById($id));
});

route('PUT', '/proveedores/{id}', function($id) {
    $data = json_decode(file_get_contents('php://input'), true);
    $proveedor = new Proveedor();
    echo json_encode($proveedor->update($id, $data));
});

route('DELETE', '/proveedores/{id}', function($id) {
    $proveedor = new Proveedor();
    echo json_encode($proveedor->delete($id));
});

route('GET', '/abonos', function() {
    $abono = new Abono();
    echo json_encode($abono->getAll());
});

route('POST', '/abonos', function() {
    $data = json_decode(file_get_contents('php://input'), true);
    $abono = new Abono();
    echo json_encode($abono->create($data));
});

route('GET', '/cobros', function() {
    $cobro = new Cobro();
    echo json_encode($cobro->getAll());
});

route('POST', '/cobros', function() {
    $data = json_decode(file_get_contents('php://input'), true);
    $cobro = new Cobro();
    echo json_encode($cobro->create($data));
});

route('GET', '/arqueos', function() {
    $arqueo = new Arqueo();
    echo json_encode($arqueo->getAll());
});

route('POST', '/arqueos', function() {
    $data = json_decode(file_get_contents('php://input'), true);
    $arqueo = new Arqueo();
    echo json_encode($arqueo->create($data));
});

route('POST', '/auth/login', function() {
    $data = json_decode(file_get_contents('php://input'), true);
    $usuario = new Usuario();
    echo json_encode($usuario->login($data));
});

route('GET', '/standard-codes', function() {
    $filePath = __DIR__ . '/../Files/Standard_Codes.js';

    if (!file_exists($filePath)) {
        http_response_code(404);
        echo json_encode(['error' => 'Standard codes file not found']);
        return;
    }

    $content = file_get_contents($filePath);
    $standardCodes = json_decode($content, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(500);
        echo json_encode(['error' => 'Invalid standard codes format']);
        return;
    }

    echo json_encode($standardCodes);
});

route('GET', '/standar-codes', function() {
    $filePath = __DIR__ . '/../Files/Standard_Codes.js';

    if (!file_exists($filePath)) {
        http_response_code(404);
        echo json_encode(['error' => 'Standard codes file not found']);
        return;
    }

    $content = file_get_contents($filePath);
    $standardCodes = json_decode($content, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(500);
        echo json_encode(['error' => 'Invalid standard codes format']);
        return;
    }

    echo json_encode($standardCodes);
});

route('GET', '/municipalities', function() {
    $filePath = __DIR__ . '/../Files/Municipalities.json';

    if (!file_exists($filePath)) {
        http_response_code(404);
        echo json_encode(['error' => 'Municipalities file not found']);
        return;
    }

    $content = file_get_contents($filePath);
    if ($content === false) {
        http_response_code(500);
        echo json_encode(['error' => 'Unable to read municipalities file']);
        return;
    }

    // Remove UTF-8 BOM if present and normalize to UTF-8 before decoding JSON.
    $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
    if (!mb_check_encoding($content, 'UTF-8')) {
        $content = mb_convert_encoding($content, 'UTF-8', 'UTF-8, ISO-8859-1, Windows-1252');
    }

    $parsed = json_decode($content, true, 512, JSON_INVALID_UTF8_SUBSTITUTE);

    if (json_last_error() !== JSON_ERROR_NONE) {
        // Fallback for files with accidental trailing characters after valid JSON.
        $startPos = strpos($content, '{');
        $endPos = strrpos($content, '}');
        if ($startPos !== false && $endPos !== false && $endPos > $startPos) {
            $jsonOnly = substr($content, $startPos, $endPos - $startPos + 1);
            $parsed = json_decode($jsonOnly, true, 512, JSON_INVALID_UTF8_SUBSTITUTE);
        }
    }

    if (json_last_error() !== JSON_ERROR_NONE || !isset($parsed['municipalities']) || !is_array($parsed['municipalities'])) {
        http_response_code(500);
        echo json_encode([
            'error' => 'Invalid municipalities format',
            'detail' => json_last_error_msg()
        ]);
        return;
    }

    $items = array_map(function($row) {
        $id = isset($row['code']) ? (string) $row['code'] : '';
        $name = isset($row['name']) ? (string) $row['name'] : '';
        $departmentName = isset($row['department']['name']) ? (string) $row['department']['name'] : '';

        return [
            'id' => $id,
            'name' => $name,
            'departmentName' => $departmentName,
            'label' => trim($name . ' - ' . $departmentName, ' -')
        ];
    }, $parsed['municipalities']);

    echo json_encode($items);
});
