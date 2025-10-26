<?php
// Configuración de la base de datos
$db_config = [
    'host' => getenv('DB_HOST') ?: '10.0.0.4',
    'port' => getenv('DB_PORT') ?: 3306,
    'dbname' => getenv('DB_NAME') ?: 'prueba2',
    'user' => getenv('DB_USER') ?: 'usuario',
    'password' => getenv('DB_PASSWORD') ?: 'contraseña1234567@'
];

// Función para conectar a PostgreSQL
function connectDB($config) {
    $conn_string = "host={$config['host']} port={$config['port']} dbname={$config['dbname']} user={$config['user']} password={$config['password']} sslmode=require";
    return pg_connect($conn_string);
}

// Función para ejecutar consultas seguras
function executeQuery($query, $params = []) {
    global $db_config;
    $conn = connectDB($db_config);
    if (!$conn) return ['success' => false, 'error' => pg_last_error()];
    
    $result = pg_query_params($conn, $query, $params);
    if (!$result) {
        $error = pg_last_error($conn);
        pg_close($conn);
        return ['success' => false, 'error' => $error];
    }
    
    $data = [];
    while ($row = pg_fetch_assoc($result)) {
        $data[] = $row;
    }
    
    pg_close($conn);
    return ['success' => true, 'data' => $data, 'rowCount' => count($data)];
}

// Procesar solicitudes AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'test-connection':
                testConnection();
                break;
            case 'create-table':
                createTable();
                break;
            case 'insert-data':
                insertData();
                break;
            case 'custom-query':
                customQuery();
                break;
        }
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action'])) {
    header('Content-Type: application/json');
    
    switch ($_GET['action']) {
        case 'tables':
            listTables();
            break;
        case 'test-connection':
            testConnection();
            break;
    }
    exit;
}

// Funciones de la API
function testConnection() {
    global $db_config;
    $conn = connectDB($db_config);
    
    if (!$conn) {
        echo json_encode(['success' => false, 'error' => pg_last_error()]);
        return;
    }
    
    $result = pg_query($conn, "SELECT version(), current_database(), current_timestamp");
    if (!$result) {
        echo json_encode(['success' => false, 'error' => pg_last_error($conn)]);
        return;
    }
    
    $row = pg_fetch_assoc($result);
    echo json_encode([
        'success' => true,
        'host' => $db_config['host'],
        'version' => $row['version'],
        'database' => $row['current_database'],
        'timestamp' => $row['current_timestamp']
    ]);
    
    pg_close($conn);
}

function listTables() {
    $result = executeQuery(
        "SELECT table_name 
         FROM information_schema.tables 
         WHERE table_schema = 'public' 
         ORDER BY table_name"
    );
    
    echo json_encode($result);
}

function createTable() {
    $result = executeQuery(
        "CREATE TABLE IF NOT EXISTS test_data (
            id SERIAL PRIMARY KEY,
            nombre VARCHAR(255) NOT NULL,
            descripcion TEXT,
            fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )"
    );
    
    echo json_encode($result);
}

function insertData() {
    $nombre = $_POST['nombre'] ?? '';
    $descripcion = $_POST['descripcion'] ?? '';
    
    if (empty($nombre) || empty($descripcion)) {
        echo json_encode(['success' => false, 'error' => 'Nombre y descripción son requeridos']);
        return;
    }
    
    $result = executeQuery(
        "INSERT INTO test_data (nombre, descripcion) VALUES ($1, $2) RETURNING id",
        [$nombre, $descripcion]
    );
    
    echo json_encode($result);
}

function customQuery() {
    $query = $_POST['query'] ?? '';
    
    if (empty($query)) {
        echo json_encode(['success' => false, 'error' => 'Consulta vacía']);
        return;
    }
    
    // Seguridad: solo permitir SELECT
    if (!preg_match('/^\s*SELECT/i', $query)) {
        echo json_encode(['success' => false, 'error' => 'Solo se permiten consultas SELECT']);
        return;
    }
    
    $result = executeQuery($query);
    echo json_encode($result);
}

// Si no es una solicitud AJAX, mostrar la interfaz HTML
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Web App PRD - Azure PHP</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        .container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            padding: 40px;
            max-width: 700px;
            width: 100%;
        }
        h1 {
            color: #333;
            margin-bottom: 10px;
            font-size: 28px;
        }
        .subtitle {
            color: #666;
            margin-bottom: 30px;
            font-size: 14px;
        }
        .status-box {
            background: #f8f9fa;
            border-left: 4px solid #667eea;
            padding: 15px;
            margin-bottom: 25px;
            border-radius: 4px;
        }
        .status-box h3 {
            color: #667eea;
            font-size: 16px;
            margin-bottom: 8px;
        }
        .button-group {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 30px;
        }
        button {
            background: #667eea;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        button:hover {
            background: #5568d3;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102,126,234,0.4);
        }
        button.secondary {
            background: #764ba2;
        }
        button.secondary:hover {
            background: #663d8f;
        }
        .form-section {
            margin-top: 30px;
            padding-top: 30px;
            border-top: 2px solid #e9ecef;
        }
        .form-section h3 {
            color: #333;
            margin-bottom: 20px;
            font-size: 18px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 8px;
            color: #555;
            font-weight: 600;
            font-size: 14px;
        }
        input, textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s ease;
        }
        input:focus, textarea:focus {
            outline: none;
            border-color: #667eea;
        }
        textarea {
            resize: vertical;
            min-height: 100px;
        }
        #result {
            margin-top: 20px;
            padding: 20px;
            border-radius: 8px;
            display: none;
            white-space: pre-wrap;
            font-family: monospace;
            font-size: 14px;
        }
        #result.success {
            background: #d4edda;
            border-left: 4px solid #28a745;
            color: #155724;
        }
        #result.error {
            background: #f8d7da;
            border-left: 4px solid #dc3545;
            color: #721c24;
        }
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(102,126,234,.3);
            border-radius: 50%;
            border-top-color: #667eea;
            animation: spin 1s ease-in-out infinite;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        .arch-info {
            background: #e9ecef;
            padding: 10px;
            border-radius: 6px;
            font-size: 12px;
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🚀 Web App PRD - Azure PHP</h1>
        <p class="subtitle">Ejercicio 3: Conectividad con PostgreSQL desde DMZ</p>
        
        <div class="status-box">
            <h3>Estado de Conexión</h3>
            <p id="connection-status">Verificando conexión a base de datos...</p>
            <div class="arch-info">
                <strong>Arquitectura:</strong> VNet DMZ → VNet Producción (Peering) | 
                <strong>DB Host:</strong> <?php echo $db_config['host']; ?>
            </div>
        </div>

        <div class="button-group">
            <button onclick="testConnection()">🔍 Probar Conexión DB</button>
            <button onclick="showTables()" class="secondary">📋 Listar Tablas</button>
            <button onclick="createTable()">➕ Crear Tabla Test</button>
            <button onclick="executeCustomQuery()" class="secondary">⚡ Consulta Personalizada</button>
        </div>

        <div class="form-section">
            <h3>Insertar Registro de Prueba</h3>
            <form onsubmit="insertData(event)">
                <div class="form-group">
                    <label for="nombre">Nombre:</label>
                    <input type="text" id="nombre" name="nombre" required>
                </div>
                <div class="form-group">
                    <label for="descripcion">Descripción:</label>
                    <textarea id="descripcion" name="descripcion" required></textarea>
                </div>
                <button type="submit">💾 Guardar en PostgreSQL</button>
            </form>
        </div>

        <div id="result"></div>
    </div>

    <script>
        // Verificar conexión al cargar la página
        window.onload = () => {
            testConnection();
        };

        async function apiCall(endpoint, data = null) {
            const formData = new FormData();
            if (data) {
                for (const key in data) {
                    formData.append(key, data[key]);
                }
            }

            const response = await fetch(endpoint, {
                method: data ? 'POST' : 'GET',
                body: data ? formData : null
            });
            return await response.json();
        }

        async function testConnection() {
            showLoading('Probando conexión...');
            try {
                const data = await apiCall('?action=test-connection');
                if (data.success) {
                    showResult('✅ Conexión exitosa a PostgreSQL<br>' + 
                               '<small>Host: ' + data.host + '<br>' +
                               'Versión: ' + data.version + '<br>' +
                               'Base de datos: ' + data.database + '</small>', 'success');
                    document.getElementById('connection-status').innerHTML = 
                        '✅ <strong>Conectado</strong> - PostgreSQL ' + data.version.split(' ')[1] + 
                        '<div class="arch-info"><strong>Arquitectura:</strong> VNet DMZ → VNet Producción (Peering)</div>';
                } else {
                    showResult('❌ Error de conexión: ' + data.error, 'error');
                    document.getElementById('connection-status').innerHTML = 
                        '❌ <strong>Desconectado</strong> - ' + data.error;
                }
            } catch (error) {
                showResult('❌ Error de red: ' + error.message, 'error');
            }
        }

        async function showTables() {
            showLoading('Consultando tablas...');
            try {
                const data = await apiCall('?action=tables');
                if (data.success) {
                    const tableList = data.data.map(t => '• ' + t.table_name).join('<br>');
                    showResult('📋 <strong>Tablas en la base de datos:</strong><br><br>' + 
                               (tableList || 'No hay tablas en la base de datos'), 'success');
                } else {
                    showResult('❌ Error: ' + data.error, 'error');
                }
            } catch (error) {
                showResult('❌ Error: ' + error.message, 'error');
            }
        }

        async function createTable() {
            showLoading('Creando tabla...');
            try {
                const data = await apiCall('', {action: 'create-table'});
                if (data.success) {
                    showResult('✅ Tabla "test_data" creada exitosamente', 'success');
                } else {
                    showResult('❌ Error: ' + data.error, 'error');
                }
            } catch (error) {
                showResult('❌ Error: ' + error.message, 'error');
            }
        }

        async function insertData(event) {
            event.preventDefault();
            showLoading('Guardando datos...');
            
            const nombre = document.getElementById('nombre').value;
            const descripcion = document.getElementById('descripcion').value;

            try {
                const data = await apiCall('', {
                    action: 'insert-data',
                    nombre: nombre,
                    descripcion: descripcion
                });
                
                if (data.success) {
                    showResult('✅ Registro insertado correctamente<br>ID: ' + data.data[0].id, 'success');
                    document.getElementById('nombre').value = '';
                    document.getElementById('descripcion').value = '';
                } else {
                    showResult('❌ Error: ' + data.error, 'error');
                }
            } catch (error) {
                showResult('❌ Error: ' + error.message, 'error');
            }
        }

        async function executeCustomQuery() {
            const query = prompt('Ingresa tu consulta SQL (solo SELECT):', 'SELECT * FROM test_data LIMIT 10');
            if (!query) return;
            
            showLoading('Ejecutando consulta...');
            try {
                const data = await apiCall('', {
                    action: 'custom-query',
                    query: query
                });
                
                if (data.success) {
                    showResult('✅ Consulta exitosa<br>Registros: ' + data.rowCount + '<br><pre>' + 
                               JSON.stringify(data.data, null, 2) + '</pre>', 'success');
                } else {
                    showResult('❌ Error: ' + data.error, 'error');
                }
            } catch (error) {
                showResult('❌ Error: ' + error.message, 'error');
            }
        }

        function showResult(message, type) {
            const resultDiv = document.getElementById('result');
            resultDiv.innerHTML = message;
            resultDiv.className = type;
            resultDiv.style.display = 'block';
        }

        function showLoading(message) {
            const resultDiv = document.getElementById('result');
            resultDiv.innerHTML = '<div class="loading"></div> ' + message;
            resultDiv.className = '';
            resultDiv.style.display = 'block';
        }
    </script>
</body>
</html>