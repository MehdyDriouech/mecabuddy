<?php
/**
 * MecaBuddy - Script de test des API
 * 
 * Ce fichier permet de tester tous les endpoints API de MecaBuddy
 * en affichant les réponses JSON de manière formatée.
 * 
 * Accès : /mecabuddy/public/test_api.php
 */

// Configuration
require_once __DIR__ . '/../config/config.php';

// Démarre la session pour les tests
if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_start();
}

// Headers HTML
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🧪 Test API - MecaBuddy</title>
    <style>
        :root {
            --bg-dark: #1A1A2E;
            --bg-card: #252542;
            --primary: #FF6B35;
            --success: #00D9A5;
            --error: #FF4757;
            --warning: #FFB800;
            --text: #FFFFFF;
            --text-muted: rgba(255,255,255,0.6);
        }
        
        * { box-sizing: border-box; margin: 0; padding: 0; }
        
        body {
            font-family: 'Segoe UI', system-ui, sans-serif;
            background: var(--bg-dark);
            color: var(--text);
            padding: 20px;
            line-height: 1.6;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        h1 {
            text-align: center;
            margin-bottom: 30px;
            color: var(--primary);
        }
        
        h2 {
            margin: 30px 0 15px;
            padding: 10px 15px;
            background: var(--bg-card);
            border-radius: 8px;
            border-left: 4px solid var(--primary);
        }
        
        .test-group {
            margin-bottom: 40px;
        }
        
        .test-card {
            background: var(--bg-card);
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 15px;
        }
        
        .test-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .test-title {
            font-weight: 600;
            font-size: 1.1em;
        }
        
        .method-badge {
            padding: 4px 12px;
            border-radius: 4px;
            font-size: 0.8em;
            font-weight: 700;
        }
        
        .method-get { background: var(--success); color: #000; }
        .method-post { background: var(--warning); color: #000; }
        
        .endpoint {
            font-family: monospace;
            background: rgba(255,255,255,0.1);
            padding: 8px 12px;
            border-radius: 4px;
            display: inline-block;
            margin-bottom: 15px;
            font-size: 0.9em;
        }
        
        .response-container {
            background: #0A0A0F;
            border-radius: 8px;
            overflow: hidden;
        }
        
        .response-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 15px;
            background: rgba(255,255,255,0.05);
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        .status {
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 0.8em;
            font-weight: 600;
        }
        
        .status-success { background: var(--success); color: #000; }
        .status-error { background: var(--error); color: #fff; }
        
        .response-body {
            padding: 15px;
            overflow-x: auto;
        }
        
        pre {
            margin: 0;
            font-family: 'JetBrains Mono', 'Fira Code', monospace;
            font-size: 0.85em;
            white-space: pre-wrap;
            word-break: break-word;
        }
        
        .json-key { color: #FF6B35; }
        .json-string { color: #00D9A5; }
        .json-number { color: #FFB800; }
        .json-boolean { color: #3B82F6; }
        .json-null { color: #9CA3AF; }
        
        .btn-run {
            background: var(--primary);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.2s;
        }
        
        .btn-run:hover {
            background: #E55A2B;
            transform: translateY(-1px);
        }
        
        .session-info {
            background: var(--bg-card);
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 30px;
        }
        
        .session-info h3 {
            margin-bottom: 10px;
            color: var(--primary);
        }
        
        .session-info code {
            background: rgba(255,255,255,0.1);
            padding: 2px 8px;
            border-radius: 4px;
            font-family: monospace;
        }
        
        .nav-links {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .nav-links a {
            color: var(--primary);
            text-decoration: none;
            margin: 0 15px;
        }
        
        .nav-links a:hover {
            text-decoration: underline;
        }
        
        .form-inline {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
            flex-wrap: wrap;
        }
        
        .form-inline input, .form-inline select {
            padding: 8px 12px;
            border-radius: 6px;
            border: 1px solid rgba(255,255,255,0.2);
            background: rgba(255,255,255,0.05);
            color: var(--text);
            font-size: 0.9em;
        }
        
        .form-inline input:focus, .form-inline select:focus {
            outline: none;
            border-color: var(--primary);
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🧪 Test des API MecaBuddy</h1>
        
        <div class="nav-links">
            <a href="<?= PUBLIC_URL ?>/index.php">🏠 Accueil</a>
            <a href="<?= PUBLIC_URL ?>/vehicle.php">🚗 Véhicule</a>
            <a href="<?= PUBLIC_URL ?>/tutorial.php">📖 Tutoriels</a>
            <a href="<?= PUBLIC_URL ?>/diagnostic.php">💬 Buddy</a>
        </div>
        
        <div class="session-info">
            <h3>📋 Informations de session</h3>
            <p><strong>Session ID:</strong> <code><?= session_id() ?></code></p>
            <p><strong>Véhicule en session:</strong> 
                <?php if (isset($_SESSION['vehicle_id'])): ?>
                    <code>ID #<?= $_SESSION['vehicle_id'] ?></code>
                <?php else: ?>
                    <em style="color: var(--text-muted);">Aucun</em>
                <?php endif; ?>
            </p>
        </div>
        
        <!-- ============================================ -->
        <!-- VEHICLE API TESTS -->
        <!-- ============================================ -->
        <div class="test-group">
            <h2>🚗 API Véhicule (vehicle_api.php)</h2>
            
            <!-- Test: Get Brands -->
            <div class="test-card">
                <div class="test-header">
                    <span class="test-title">Liste des marques</span>
                    <span class="method-badge method-get">GET</span>
                </div>
                <div class="endpoint"><?= API_URL ?>/vehicle_api.php?action=brands</div>
                <button class="btn-run" onclick="testAPI('brands-result', '<?= API_URL ?>/vehicle_api.php?action=brands')">
                    ▶️ Exécuter
                </button>
                <div id="brands-result" class="response-container" style="margin-top: 15px; display: none;"></div>
            </div>
            
            <!-- Test: Get Models -->
            <div class="test-card">
                <div class="test-header">
                    <span class="test-title">Liste des modèles (par marque)</span>
                    <span class="method-badge method-get">GET</span>
                </div>
                <div class="endpoint"><?= API_URL ?>/vehicle_api.php?action=models&brand_id=1</div>
                <div class="form-inline">
                    <label>Brand ID:</label>
                    <input type="number" id="models-brand-id" value="1" min="1" style="width: 80px;">
                    <button class="btn-run" onclick="testModels()">▶️ Exécuter</button>
                </div>
                <div id="models-result" class="response-container" style="margin-top: 15px; display: none;"></div>
            </div>
            
            <!-- Test: Get Current Vehicle -->
            <div class="test-card">
                <div class="test-header">
                    <span class="test-title">Véhicule courant (session)</span>
                    <span class="method-badge method-get">GET</span>
                </div>
                <div class="endpoint"><?= API_URL ?>/vehicle_api.php?action=current</div>
                <button class="btn-run" onclick="testAPI('current-result', '<?= API_URL ?>/vehicle_api.php?action=current')">
                    ▶️ Exécuter
                </button>
                <div id="current-result" class="response-container" style="margin-top: 15px; display: none;"></div>
            </div>
            
            <!-- Test: Lookup Plate -->
            <div class="test-card">
                <div class="test-header">
                    <span class="test-title">Recherche par plaque (simulée)</span>
                    <span class="method-badge method-post">POST</span>
                </div>
                <div class="endpoint"><?= API_URL ?>/vehicle_api.php?action=lookup</div>
                <div class="form-inline">
                    <label>Plaque:</label>
                    <input type="text" id="lookup-plate" value="AB-123-CD" style="text-transform: uppercase;">
                    <button class="btn-run" onclick="testLookup()">▶️ Exécuter</button>
                </div>
                <div id="lookup-result" class="response-container" style="margin-top: 15px; display: none;"></div>
            </div>
            
            <!-- Test: Save Vehicle -->
            <div class="test-card">
                <div class="test-header">
                    <span class="test-title">Enregistrer un véhicule</span>
                    <span class="method-badge method-post">POST</span>
                </div>
                <div class="endpoint"><?= API_URL ?>/vehicle_api.php?action=save</div>
                <div class="form-inline">
                    <input type="text" id="save-brand" placeholder="Marque" value="Renault">
                    <input type="text" id="save-model" placeholder="Modèle" value="Clio">
                    <input type="number" id="save-year" placeholder="Année" value="2020" style="width: 80px;">
                    <button class="btn-run" onclick="testSaveVehicle()">▶️ Exécuter</button>
                </div>
                <div id="save-result" class="response-container" style="margin-top: 15px; display: none;"></div>
            </div>
        </div>
        
        <!-- ============================================ -->
        <!-- TUTORIAL API TESTS -->
        <!-- ============================================ -->
        <div class="test-group">
            <h2>📖 API Tutoriel (tutorial_api.php)</h2>
            
            <!-- Test: Get Suggestions -->
            <div class="test-card">
                <div class="test-header">
                    <span class="test-title">Suggestions d'actions</span>
                    <span class="method-badge method-get">GET</span>
                </div>
                <div class="endpoint"><?= API_URL ?>/tutorial_api.php?action=suggestions</div>
                <button class="btn-run" onclick="testAPI('suggestions-result', '<?= API_URL ?>/tutorial_api.php?action=suggestions')">
                    ▶️ Exécuter
                </button>
                <div id="suggestions-result" class="response-container" style="margin-top: 15px; display: none;"></div>
            </div>
            
            <!-- Test: Generate Tutorial -->
            <div class="test-card">
                <div class="test-header">
                    <span class="test-title">Générer un tutoriel</span>
                    <span class="method-badge method-post">POST</span>
                </div>
                <div class="endpoint"><?= API_URL ?>/tutorial_api.php?action=generate</div>
                <div class="form-inline">
                    <label>Action:</label>
                    <select id="generate-action">
                        <option value="vidange">Vidange</option>
                        <option value="plaquettes">Plaquettes de frein</option>
                        <option value="batterie">Batterie</option>
                        <option value="filtre air">Filtre à air</option>
                        <option value="bougies">Bougies</option>
                        <option value="essuie-glaces">Essuie-glaces</option>
                    </select>
                    <button class="btn-run" onclick="testGenerate()">▶️ Exécuter</button>
                </div>
                <div id="generate-result" class="response-container" style="margin-top: 15px; display: none;"></div>
            </div>
            
            <!-- Test: List Tutorials -->
            <div class="test-card">
                <div class="test-header">
                    <span class="test-title">Lister les tutoriels récents</span>
                    <span class="method-badge method-get">GET</span>
                </div>
                <div class="endpoint"><?= API_URL ?>/tutorial_api.php?action=list&limit=5</div>
                <button class="btn-run" onclick="testAPI('list-tutorials-result', '<?= API_URL ?>/tutorial_api.php?action=list&limit=5')">
                    ▶️ Exécuter
                </button>
                <div id="list-tutorials-result" class="response-container" style="margin-top: 15px; display: none;"></div>
            </div>
        </div>
        
        <!-- ============================================ -->
        <!-- DIAGNOSTIC API TESTS -->
        <!-- ============================================ -->
        <div class="test-group">
            <h2>💬 API Diagnostic - Buddy Mode (diagnostic_api.php)</h2>
            
            <!-- Test: Ask Buddy -->
            <div class="test-card">
                <div class="test-header">
                    <span class="test-title">Poser une question au Buddy</span>
                    <span class="method-badge method-post">POST</span>
                </div>
                <div class="endpoint"><?= API_URL ?>/diagnostic_api.php?action=ask</div>
                <div class="form-inline">
                    <input type="text" id="ask-message" placeholder="Votre message..." value="Ma voiture fait un bruit bizarre quand je freine" style="flex: 1; min-width: 300px;">
                    <button class="btn-run" onclick="testAsk()">▶️ Exécuter</button>
                </div>
                <div id="ask-result" class="response-container" style="margin-top: 15px; display: none;"></div>
            </div>
            
            <!-- Test: Get History -->
            <div class="test-card">
                <div class="test-header">
                    <span class="test-title">Historique des conversations</span>
                    <span class="method-badge method-get">GET</span>
                </div>
                <div class="endpoint"><?= API_URL ?>/diagnostic_api.php?action=history&limit=10</div>
                <button class="btn-run" onclick="testAPI('history-result', '<?= API_URL ?>/diagnostic_api.php?action=history&limit=10')">
                    ▶️ Exécuter
                </button>
                <div id="history-result" class="response-container" style="margin-top: 15px; display: none;"></div>
            </div>
            
            <!-- Test: Clear History -->
            <div class="test-card">
                <div class="test-header">
                    <span class="test-title">Effacer l'historique</span>
                    <span class="method-badge method-post">POST</span>
                </div>
                <div class="endpoint"><?= API_URL ?>/diagnostic_api.php?action=clear</div>
                <button class="btn-run" onclick="testClear()" style="background: var(--error);">
                    🗑️ Effacer l'historique
                </button>
                <div id="clear-result" class="response-container" style="margin-top: 15px; display: none;"></div>
            </div>
        </div>
        
    </div>
    
    <script>
        // Fonction utilitaire pour formater le JSON avec coloration syntaxique
        function formatJSON(json) {
            if (typeof json === 'string') {
                json = JSON.parse(json);
            }
            const str = JSON.stringify(json, null, 2);
            return str.replace(/("(\\u[a-zA-Z0-9]{4}|\\[^u]|[^\\"])*"(\s*:)?|\b(true|false|null)\b|-?\d+(?:\.\d*)?(?:[eE][+\-]?\d+)?)/g, function (match) {
                let cls = 'json-number';
                if (/^"/.test(match)) {
                    if (/:$/.test(match)) {
                        cls = 'json-key';
                        match = match.slice(0, -1) + '</span>:';
                        return '<span class="' + cls + '">' + match;
                    } else {
                        cls = 'json-string';
                    }
                } else if (/true|false/.test(match)) {
                    cls = 'json-boolean';
                } else if (/null/.test(match)) {
                    cls = 'json-null';
                }
                return '<span class="' + cls + '">' + match + '</span>';
            });
        }
        
        // Affiche le résultat dans le container
        function displayResult(containerId, data, status) {
            const container = document.getElementById(containerId);
            container.style.display = 'block';
            
            const isSuccess = status >= 200 && status < 300;
            
            container.innerHTML = `
                <div class="response-header">
                    <span>Réponse</span>
                    <span class="status ${isSuccess ? 'status-success' : 'status-error'}">
                        ${status} ${isSuccess ? 'OK' : 'Error'}
                    </span>
                </div>
                <div class="response-body">
                    <pre>${formatJSON(data)}</pre>
                </div>
            `;
        }
        
        // Test générique GET
        async function testAPI(containerId, url) {
            try {
                const response = await fetch(url);
                const data = await response.json();
                displayResult(containerId, data, response.status);
            } catch (error) {
                displayResult(containerId, { error: error.message }, 500);
            }
        }
        
        // Test: Get Models
        function testModels() {
            const brandId = document.getElementById('models-brand-id').value;
            testAPI('models-result', `<?= API_URL ?>/vehicle_api.php?action=models&brand_id=${brandId}`);
        }
        
        // Test: Lookup Plate
        async function testLookup() {
            const plate = document.getElementById('lookup-plate').value;
            try {
                const response = await fetch(`<?= API_URL ?>/vehicle_api.php?action=lookup`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ license_plate: plate })
                });
                const data = await response.json();
                displayResult('lookup-result', data, response.status);
            } catch (error) {
                displayResult('lookup-result', { error: error.message }, 500);
            }
        }
        
        // Test: Save Vehicle
        async function testSaveVehicle() {
            const brand = document.getElementById('save-brand').value;
            const model = document.getElementById('save-model').value;
            const year = document.getElementById('save-year').value;
            
            try {
                const response = await fetch(`<?= API_URL ?>/vehicle_api.php?action=save`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ brand, model, year: parseInt(year) })
                });
                const data = await response.json();
                displayResult('save-result', data, response.status);
                
                // Recharge la page pour mettre à jour les infos de session
                if (data.success) {
                    setTimeout(() => location.reload(), 1500);
                }
            } catch (error) {
                displayResult('save-result', { error: error.message }, 500);
            }
        }
        
        // Test: Generate Tutorial
        async function testGenerate() {
            const action = document.getElementById('generate-action').value;
            
            try {
                const response = await fetch(`<?= API_URL ?>/tutorial_api.php?action=generate`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action_type: action })
                });
                const data = await response.json();
                displayResult('generate-result', data, response.status);
            } catch (error) {
                displayResult('generate-result', { error: error.message }, 500);
            }
        }
        
        // Test: Ask Buddy
        async function testAsk() {
            const message = document.getElementById('ask-message').value;
            
            try {
                const response = await fetch(`<?= API_URL ?>/diagnostic_api.php?action=ask`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ message })
                });
                const data = await response.json();
                displayResult('ask-result', data, response.status);
            } catch (error) {
                displayResult('ask-result', { error: error.message }, 500);
            }
        }
        
        // Test: Clear History
        async function testClear() {
            if (!confirm('Êtes-vous sûr de vouloir effacer l\'historique ?')) return;
            
            try {
                const response = await fetch(`<?= API_URL ?>/diagnostic_api.php?action=clear`, {
                    method: 'POST'
                });
                const data = await response.json();
                displayResult('clear-result', data, response.status);
            } catch (error) {
                displayResult('clear-result', { error: error.message }, 500);
            }
        }
    </script>
</body>
</html>

