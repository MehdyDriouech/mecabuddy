<?php
/**
 * MecaBuddy - Page d'accueil (Home / Buddy)
 *
 * Page principale avec message d'accueil, aperçu garage et actions.
 */

$pageTitle = 'MecaBuddy - Ton pote mécano';
$currentPage = 'home';

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/vehicle_context.php';

$activeVehicles = getActiveVehiclesForSession();
$garagePreviewSlots = array_pad(array_slice($activeVehicles, 0, 3), 3, null);
?>

<div class="hero-section">
    <div class="hero-content">
        <div class="buddy-avatar">
            <span class="avatar-emoji">🔧</span>
            <div class="avatar-pulse"></div>
        </div>

        <h1 class="hero-title">
            Salut, je suis <span class="highlight">MecaBuddy</span> 👋
        </h1>

        <p class="hero-subtitle">
            Ton compagnon mécanique intelligent.<br>
            Je t'aide à entretenir et réparer ta voiture, étape par étape !
        </p>
    </div>
</div>

<section class="vehicle-card-section garage-preview-section">
    <div class="current-vehicle-card garage-preview-card">
        <div class="vehicle-card-header">
            <span class="vehicle-icon">🏎️</span>
            <span class="vehicle-label">Mon garage</span>
        </div>
        <div class="garage-preview">
            <div class="garage-preview-grid" role="list">
                <?php foreach ($garagePreviewSlots as $slotIndex => $v): ?>
                    <?php if ($v === null): ?>
                        <article class="garage-preview-vehicle-card garage-preview-vehicle-card--empty" role="listitem">
                            <div class="garage-preview-vehicle-head">
                                <span class="garage-preview-vehicle-icon">➕</span>
                                <span class="garage-preview-vehicle-name">Emplacement libre</span>
                            </div>
                            <p class="garage-preview-slot-hint">Slot <?= (int) $slotIndex + 1 ?></p>
                            <a href="<?= PUBLIC_URL ?>/vehicle.php" class="btn btn-secondary btn-sm garage-preview-add-link">
                                Ajouter
                            </a>
                        </article>
                    <?php else: ?>
                        <?php
                        $icon = ($v['category'] ?? 'car') === 'moto' ? '🏍️' : '🚗';
                        $engineLabel = vehicle_format_engine_label(
                            isset($v['engine_type']) ? (string) $v['engine_type'] : null,
                            isset($v['engine_size']) ? (string) $v['engine_size'] : null
                        );
                        $transmissionLabel = vehicle_format_transmission_label(
                            isset($v['transmission']) ? (string) $v['transmission'] : null
                        );
                        $energyLabel = vehicle_extract_energy_label(
                            isset($v['engine_type']) ? (string) $v['engine_type'] : null
                        );
                        $yearLabel = !empty($v['year']) ? (string) $v['year'] : null;
                        $vehicleName = trim(($v['brand'] ?? '') . ' ' . ($v['model'] ?? ''));
                        ?>
                        <article class="garage-preview-vehicle-card" role="listitem">
                            <div class="garage-preview-vehicle-head">
                                <span class="garage-preview-vehicle-icon"><?= $icon ?></span>
                                <span class="garage-preview-vehicle-name">
                                    <?= htmlspecialchars($vehicleName) ?>
                                </span>
                            </div>
                            <div class="garage-preview-specs">
                                <div class="garage-preview-spec">
                                    <span class="garage-preview-spec-label">Moteur</span>
                                    <span class="garage-preview-spec-value"><?= $engineLabel !== null ? htmlspecialchars($engineLabel) : '—' ?></span>
                                </div>
                                <div class="garage-preview-spec">
                                    <span class="garage-preview-spec-label">Boîte</span>
                                    <span class="garage-preview-spec-value"><?= $transmissionLabel !== null ? htmlspecialchars($transmissionLabel) : '—' ?></span>
                                </div>
                                <div class="garage-preview-spec">
                                    <span class="garage-preview-spec-label">Année</span>
                                    <span class="garage-preview-spec-value"><?= $yearLabel !== null ? htmlspecialchars($yearLabel) : '—' ?></span>
                                </div>
                                <div class="garage-preview-spec">
                                    <span class="garage-preview-spec-label">Énergie</span>
                                    <span class="garage-preview-spec-value"><?= $energyLabel !== null ? htmlspecialchars($energyLabel) : '—' ?></span>
                                </div>
                            </div>
                        </article>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
            <a href="<?= PUBLIC_URL ?>/garage.php" class="btn btn-secondary btn-garage-manage">🏎️ Gérer mon garage</a>
        </div>
    </div>
</section>

<section class="main-actions">
    <div class="actions-grid">
        <a href="<?= PUBLIC_URL ?>/vehicle.php" class="action-card action-vehicle">
            <div class="action-icon">
                <span>🚗</span>
            </div>
            <div class="action-content">
                <h2 class="action-title">Choisir mon véhicule</h2>
                <p class="action-description">
                    Sélectionne ta voiture pour des tutoriels personnalisés
                </p>
            </div>
            <div class="action-arrow">→</div>
        </a>

        <a href="<?= PUBLIC_URL ?>/tutorial.php" class="action-card action-tutorial">
            <div class="action-icon">
                <span>📖</span>
            </div>
            <div class="action-content">
                <h2 class="action-title">Générer un tutoriel</h2>
                <p class="action-description">
                    Vidange, freins, batterie... Je te guide étape par étape
                </p>
            </div>
            <div class="action-arrow">→</div>
        </a>

        <a href="<?= PUBLIC_URL ?>/diagnostic.php" class="action-card action-diagnostic">
            <div class="action-icon">
                <span>💬</span>
            </div>
            <div class="action-content">
                <h2 class="action-title">Diagnostic (Buddy Mode)</h2>
                <p class="action-description">
                    Un problème ? Décris-le moi et je t'aide à trouver la solution
                </p>
            </div>
            <div class="action-arrow">→</div>
        </a>
    </div>
</section>

<section class="features-section">
    <h2 class="section-title">Pourquoi MecaBuddy ?</h2>
    <div class="features-grid">
        <div class="feature-card">
            <span class="feature-icon">⚡</span>
            <h3>Simple & Rapide</h3>
            <p>Des tutoriels clairs, étape par étape, même pour les débutants</p>
        </div>
        <div class="feature-card">
            <span class="feature-icon">🛡️</span>
            <h3>Sécurité d'abord</h3>
            <p>Alertes de sécurité et précautions pour chaque opération</p>
        </div>
        <div class="feature-card">
            <span class="feature-icon">🎯</span>
            <h3>Personnalisé</h3>
            <p>Tutoriels adaptés à ton véhicule spécifique</p>
        </div>
        <div class="feature-card">
            <span class="feature-icon">💰</span>
            <h3>Économise</h3>
            <p>Fais toi-même l'entretien de ta voiture en toute confiance</p>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
