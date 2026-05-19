<?php
/**
 * MecaBuddy - À propos
 */

$skipDemoAuthGuard = true;
$pageTitle = 'À propos - MecaBuddy';
$currentPage = 'about';

require_once __DIR__ . '/../includes/header.php';
?>

<div class="about-page">
    <header class="hero-section about-hero">
        <h1 class="hero-title">À propos de <span class="highlight">MecaBuddy</span></h1>
        <p class="hero-subtitle about-hero-lead">
            MecaBuddy est un prototype né d'une conviction simple : l'IA peut aider dans l'automobile sans remplacer l'expertise humaine.
        </p>
        <p class="about-hero-text">
            L'objectif n'est pas de transformer tout le monde en mécanicien, mais d'aider chacun à mieux comprendre son véhicule, préparer un entretien, qualifier un problème ou suivre un tutoriel avec plus de clarté.
        </p>
    </header>

    <section class="about-section" aria-labelledby="about-why-title">
        <h2 id="about-why-title" class="section-title about-section-title">Pourquoi ce projet ?</h2>
        <div class="about-prose">
            <p>J'ai toujours été attiré par l'automobile : les véhicules, l'entretien, la donnée technique, l'expérience client, les plateformes de pièces, les garages et tous les outils qui peuvent rendre les choses plus simples.</p>
            <p>MecaBuddy est né de cette envie : créer un compagnon mécanique accessible, utile et un peu fun, capable d'accompagner un utilisateur lorsqu'il se retrouve face à une panne, un doute ou une opération d'entretien.</p>
        </div>
    </section>

    <section class="about-section features-section" aria-labelledby="about-features-title">
        <h2 id="about-features-title" class="section-title about-section-title">Ce que permet MecaBuddy</h2>
        <p class="about-section-intro">
            Selon la configuration de l'instance, un modèle de langage optionnel, un mode démo avec quotas ou une clé API personnelle (BYOK) peuvent compléter l'expérience.
        </p>
        <div class="features-grid about-features-grid">
            <article class="feature-card">
                <span class="feature-icon" aria-hidden="true">🚗</span>
                <h3>Sélectionner son véhicule</h3>
                <p>Enregistrer et choisir le véhicule concerné pour contextualiser les réponses.</p>
            </article>
            <article class="feature-card">
                <span class="feature-icon" aria-hidden="true">📖</span>
                <h3>Tutoriels personnalisés</h3>
                <p>Générer des guides d'entretien ou de réparation adaptés à ta situation.</p>
            </article>
            <article class="feature-card">
                <span class="feature-icon" aria-hidden="true">💬</span>
                <h3>Poser des questions à Buddy</h3>
                <p>Échanger avec l'assistant mécanique pour clarifier un symptôme ou une étape.</p>
            </article>
            <article class="feature-card">
                <span class="feature-icon" aria-hidden="true">🔍</span>
                <h3>Recherche web</h3>
                <p>Enrichir certaines réponses avec des informations trouvées en ligne, quand c'est activé.</p>
            </article>
            <article class="feature-card">
                <span class="feature-icon" aria-hidden="true">🔗</span>
                <h3>Sources affichées</h3>
                <p>Retrouver les références utilisées lorsqu'elles sont disponibles.</p>
            </article>
            <article class="feature-card">
                <span class="feature-icon" aria-hidden="true">🛡️</span>
                <h3>Couche de sécurité</h3>
                <p>Signaler les opérations sensibles et rappeler les précautions importantes.</p>
            </article>
        </div>
    </section>

    <section class="about-section" aria-labelledby="about-limits-title">
        <h2 id="about-limits-title" class="section-title about-section-title">Ce que MecaBuddy n'est pas</h2>
        <div class="about-disclaimer" role="note">
            <p><strong>MecaBuddy n'est pas un outil constructeur, ni un remplaçant d'un professionnel.</strong></p>
            <p>Les informations générées doivent être considérées comme une aide à la compréhension, pas comme une validation technique officielle. En cas de doute, de risque ou d'opération critique, l'avis d'un professionnel reste indispensable.</p>
        </div>
    </section>

    <section class="about-section" aria-labelledby="about-vision-title">
        <h2 id="about-vision-title" class="section-title about-section-title">Une vision produit</h2>
        <div class="about-prose">
            <p>MecaBuddy montre une direction possible : rendre l'information technique plus accessible, mieux guider les utilisateurs, aider à qualifier les problèmes et créer un pont entre le grand public, les professionnels de l'automobile et les outils numériques.</p>
            <p>Ce prototype s'inscrit aussi dans mon parcours : faire le lien entre produit, tech, IA et terrain, avec une conviction forte : l'IA est intéressante quand elle augmente l'humain, pas quand elle l'efface.</p>
        </div>
    </section>

    <section class="about-section about-cta" aria-labelledby="about-contact-title">
        <h2 id="about-contact-title" class="section-title about-section-title">Vous travaillez dans l'écosystème automobile ?</h2>
        <p class="about-cta-text">
            Je serais ravi d'échanger avec des acteurs de l'entretien, de la pièce auto, des garages, de l'assurance, des marketplaces ou de l'innovation automobile.
        </p>
        <div class="about-cta-actions">
            <a href="mailto:driouechmehdy.pro@gmail.com" class="btn btn-primary">Me contacter</a>
            <a href="<?= htmlspecialchars(PUBLIC_URL . '/index.php') ?>" class="btn btn-secondary">Retour à l'accueil</a>
        </div>
    </section>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
