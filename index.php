<?php
header('Content-Type: text/html; charset=utf-8');

/**
 * ===================================================
 * SfaxFix — Local Services Platform
 * ===================================================
 *
 * SfaxFix is a web platform created to help users in
 * Sfax, Tunisia find and book local service providers
 * such as plumbers, electricians, painters, cleaners,
 * and other home service professionals.
 *
 * Users can:
 * - Create an account
 * - Browse providers
 * - Make reservations
 * - Manage their bookings
 *
 * Providers can:
 * - Create provider profiles
 * - Manage reservations
 * - Access their dashboard
 *
 * This project was developed by Rym Bouaoun as part
 * of a university project in 2026.
 *
 * Some AI tools were used during development for
 * learning, debugging, and improving the code structure.
 */
/**
 * Start session and connect to database
 */
session_start();
include("config/db.php");
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Page title -->
    <title>SfaxFix — Services à domicile à Sfax, Tunisie</title>

    <!-- SEO description -->
    <meta name="description" content="Trouvez et réservez les meilleurs prestataires de services à Sfax, Tunisie. Plombiers, électriciens, peintres — en quelques clics.">

    <!-- Main CSS file -->
    <link rel="stylesheet" href="assets/css/style.css">

    <style>

        /* =========================================
           HERO SECTION
        ========================================= */

        .hero {
            text-align: center;
            padding: 6rem 1.5rem 4rem;
            position: relative;
            overflow: hidden;
        }

        /* Background effect behind hero */
        .hero::before {
            content: '';
            position: absolute;
            top: -100px;
            left: 50%;
            transform: translateX(-50%);

            width: 600px;
            height: 600px;

            background: radial-gradient(
                circle,
                rgba(108,99,255,0.15) 0%,
                rgba(255,101,132,0.08) 50%,
                transparent 70%
            );

            border-radius: 50%;
            pointer-events: none;
            z-index: 0;
        }

        .hero-content {
            position: relative;
            z-index: 1;
            max-width: 700px;
            margin: 0 auto;
        }

        .hero-badge {
            display: inline-block;
            background: rgba(108,99,255,0.15);
            color: var(--primary);

            border: 1px solid rgba(108,99,255,0.3);

            padding: 0.35rem 1rem;
            border-radius: 99px;

            font-size: 0.8rem;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;

            margin-bottom: 1.5rem;
        }

        .hero h1 {
            font-size: clamp(2rem, 5vw, 3.5rem);
            font-weight: 800;
            line-height: 1.15;
            letter-spacing: -1px;
            margin-bottom: 1.25rem;
        }

        /* Gradient text effect */
        .hero h1 .gradient-text {
            background: linear-gradient(
                135deg,
                var(--primary),
                var(--accent)
            );

            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .hero-description {
            font-size: 1.1rem;
            color: var(--text-secondary);
            line-height: 1.7;

            margin-bottom: 2.5rem;

            max-width: 560px;
            margin-left: auto;
            margin-right: auto;
        }

        /* Hero buttons */
        .hero-cta-group {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
        }

        /* Statistics section */
        .hero-stats {
            display: flex;
            justify-content: center;
            gap: 3rem;

            margin-top: 4rem;
            flex-wrap: wrap;
        }

        .hero-stat {
            text-align: center;
        }

        .hero-stat-value {
            font-size: 1.8rem;
            font-weight: 800;

            background: linear-gradient(
                135deg,
                var(--primary),
                var(--accent)
            );

            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .hero-stat-label {
            font-size: 0.85rem;
            color: var(--text-muted);
            margin-top: 0.25rem;
        }

        /* =========================================
           GENERAL SECTIONS
        ========================================= */

        .section {
            padding: 5rem 1.5rem;
        }

        .section-header {
            text-align: center;
            max-width: 560px;
            margin: 0 auto 3.5rem;
        }

        .section-tag {
            display: inline-block;

            background: rgba(108,99,255,0.1);
            color: var(--primary);

            font-size: 0.75rem;
            font-weight: 700;

            letter-spacing: 0.1em;
            text-transform: uppercase;

            padding: 0.3rem 0.85rem;
            border-radius: 99px;

            margin-bottom: 1rem;
        }

        .section-title {
            font-size: 2rem;
            font-weight: 800;
            letter-spacing: -0.5px;
            line-height: 1.25;
        }

        .section-subtitle {
            color: var(--text-secondary);
            margin-top: 0.75rem;
        }

        /* =========================================
           HOW IT WORKS SECTION
        ========================================= */

        .steps-grid {
            display: grid;

            grid-template-columns:
                repeat(auto-fit, minmax(240px, 1fr));

            gap: 1.5rem;

            max-width: 1000px;
            margin: 0 auto;
        }

        .step-card {
            background: var(--bg-card);
            border: 1px solid var(--border);

            border-radius: var(--radius-lg);

            padding: 2rem 1.75rem;
            text-align: center;

            transition: var(--transition);
            position: relative;
        }

        .step-card:hover {
            border-color: var(--border-hover);
            box-shadow: var(--shadow-glow);
            transform: translateY(-4px);
        }

        .step-number {
            display: inline-flex;
            align-items: center;
            justify-content: center;

            width: 44px;
            height: 44px;

            border-radius: 50%;

            background: linear-gradient(
                135deg,
                var(--primary),
                var(--primary-dark)
            );

            color: #fff;
            font-size: 1rem;
            font-weight: 800;

            margin-bottom: 1.25rem;
        }

        .step-icon {
            font-size: 2rem;
            display: block;
            margin-bottom: 0.5rem;
        }

        .step-card h3 {
            font-size: 1rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .step-card p {
            font-size: 0.875rem;
            color: var(--text-secondary);
            line-height: 1.6;
        }

        /* =========================================
           SERVICES SECTION
        ========================================= */

        .services-grid {
            display: grid;

            grid-template-columns:
                repeat(auto-fill, minmax(160px, 1fr));

            gap: 1rem;

            max-width: 900px;
            margin: 0 auto;
        }

        .service-item {
            background: var(--bg-card);
            border: 1px solid var(--border);

            border-radius: var(--radius-md);

            padding: 1.5rem 1rem;
            text-align: center;

            cursor: pointer;
            transition: var(--transition);

            text-decoration: none;
            color: var(--text-primary);
        }

        .service-item:hover {
            border-color: var(--primary);
            background: var(--primary-light);

            transform: translateY(-3px);

            color: var(--primary);
            box-shadow: var(--shadow-glow);
        }

        .service-item .icon {
            font-size: 2rem;
            display: block;
            margin-bottom: 0.75rem;
        }

        .service-item span {
            font-size: 0.875rem;
            font-weight: 600;
        }

        /* =========================================
           CTA BANNER
        ========================================= */

        .cta-banner {
            background: linear-gradient(
                135deg,
                var(--primary) 0%,
                var(--primary-dark) 50%,
                #3d35b5 100%
            );

            border-radius: var(--radius-xl);

            padding: 4rem 2rem;
            text-align: center;

            max-width: 800px;
            margin: 0 auto;

            position: relative;
            overflow: hidden;
        }

        .cta-banner::before {
            content: '';
            position: absolute;

            top: -50%;
            right: -10%;

            width: 300px;
            height: 300px;

            background: rgba(255,255,255,0.06);
            border-radius: 50%;
        }

        .cta-banner h2 {
            font-size: 1.75rem;
            font-weight: 800;

            color: #fff;
            margin-bottom: 0.75rem;
        }

        .cta-banner p {
            color: rgba(255,255,255,0.8);

            margin-bottom: 2rem;
            font-size: 1rem;
        }

        .btn-white {
            background: #fff;
            color: var(--primary);
            font-weight: 700;
        }

        .btn-white:hover {
            background: rgba(255,255,255,0.9);
            color: var(--primary-dark);

            box-shadow: 0 8px 25px rgba(0,0,0,0.2);

            transform: translateY(-2px);
        }

    </style>
</head>
<body>

<!-- Navigation bar -->
<?php include("includes/navbar.php"); ?>

<main>
    <!-- ═══════════════════════════════════════
         HERO SECTION
    ════════════════════════════════════════ -->
    <section class="hero">
        <div class="hero-content">
            <div class="hero-badge">🇹🇳 Sfax, Tunisie</div>

            <h1>
                Trouvez le bon professionnel,<br>
                <span class="gradient-text">réservez en 1 clic</span>
            </h1>

            <p class="hero-description">
                SfaxFix connecte les habitants de Sfax avec des prestataires de services
                locaux de confiance. Plombiers, électriciens, peintres — disponibles pour vous.
            </p>

            <div class="hero-cta-group">
                <a href="pages/providers.php" class="btn btn-primary btn-lg">
                    🔍 Voir les prestataires
                </a>
                <?php if (!isset($_SESSION['user_id'])): ?>
                    <a href="pages/register.php" class="btn btn-outline btn-lg">
                        ✨ Créer un compte
                    </a>
                <?php else: ?>
                    <a href="pages/my_bookings.php" class="btn btn-outline btn-lg">
                        📅 Mes réservations
                    </a>
                <?php endif; ?>
            </div>

            <!-- Stats Row -->
            <div class="hero-stats">
                <div class="hero-stat">
                    <div class="hero-stat-value">100%</div>
                    <div class="hero-stat-label">Sécurisé</div>
                </div>
                <div class="hero-stat">
                    <div class="hero-stat-value">Local</div>
                    <div class="hero-stat-label">Prestataires de Sfax</div>
                </div>
                <div class="hero-stat">
                    <div class="hero-stat-value">Gratuit</div>
                    <div class="hero-stat-label">Inscription sans frais</div>
                </div>
            </div>
        </div>
    </section>

    <!-- ═══════════════════════════════════════
         HOW IT WORKS
    ════════════════════════════════════════ -->
    <section class="section">
        <div class="section-header">
            <div class="section-tag">Comment ça marche</div>
            <h2 class="section-title">Réservez en 3 étapes simples</h2>
            <p class="section-subtitle">
                Pas de complications. Trouvez, choisissez, réservez.
            </p>
        </div>

        <div class="steps-grid">
            <div class="step-card">
                <span class="step-icon">🔍</span>
                <div class="step-number">1</div>
                <h3>Trouvez un prestataire</h3>
                <p>Parcourez notre liste de professionnels locaux et choisissez celui qui correspond à vos besoins.</p>
            </div>
            <div class="step-card">
                <span class="step-icon">📅</span>
                <div class="step-number">2</div>
                <h3>Choisissez un créneau</h3>
                <p>Sélectionnez la date et l'heure qui vous conviennent. Les créneaux déjà pris sont automatiquement bloqués.</p>
            </div>
            <div class="step-card">
                <span class="step-icon">✅</span>
                <div class="step-number">3</div>
                <h3>Confirmez et attendez</h3>
                <p>Votre demande est envoyée au prestataire. Il vous confirme directement via la plateforme.</p>
            </div>
        </div>
    </section>

    <!-- ═══════════════════════════════════════
         SERVICES CATEGORIES
    ════════════════════════════════════════ -->
    <section class="section" style="padding-top: 0;">
        <div class="section-header">
            <div class="section-tag">Nos services</div>
            <h2 class="section-title">Tous types de services à domicile</h2>
        </div>

        <div class="services-grid">
            <a href="pages/providers.php" class="service-item">
                <span class="icon">🔧</span><span>Plomberie</span>
            </a>
            <a href="pages/providers.php" class="service-item">
                <span class="icon">⚡</span><span>Électricité</span>
            </a>
            <a href="pages/providers.php" class="service-item">
                <span class="icon">🎨</span><span>Peinture</span>
            </a>
            <a href="pages/providers.php" class="service-item">
                <span class="icon">🏠</span><span>Ménage</span>
            </a>
            <a href="pages/providers.php" class="service-item">
                <span class="icon">🌿</span><span>Jardinage</span>
            </a>
            <a href="pages/providers.php" class="service-item">
                <span class="icon">❄️</span><span>Climatisation</span>
            </a>
        </div>
    </section>

    <!-- ═══════════════════════════════════════
         PROVIDER CTA BANNER
    ════════════════════════════════════════ -->
    <section class="section" style="padding-top: 0;">
        <div class="cta-banner">
            <h2>Vous êtes un professionnel ? 🛠️</h2>
            <p>
                Rejoignez SfaxFix et recevez des clients directement.
                Inscription rapide et gratuite.
            </p>
            <a href="pages/add_provider.php" class="btn btn-white btn-lg">
                Devenir prestataire →
            </a>
        </div>
    </section>

</main>

<?php include("includes/footer.php"); ?>

</body>
</html>