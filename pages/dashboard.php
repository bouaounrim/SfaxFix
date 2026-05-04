<?php
/**
 * pages/dashboard.php
 * ===================================================
 * User Dashboard — SfaxFix
 * ===================================================
 *
 * Main page shown after login.
 *
 * Displays:
 *   - Welcome message
 *   - Booking statistics
 *   - Quick access buttons
 *   - Recent bookings
 *
 * ACCESS:
 *   Logged-in users only.
 */

session_start();
include("../config/db.php");

// ─────────────────────────────────────────────
// Require login
// ─────────────────────────────────────────────
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = (int)$_SESSION['user_id'];

// ─────────────────────────────────────────────
// Load booking statistics
// ─────────────────────────────────────────────
$stats_stmt = $conn->prepare("
    SELECT 
        COUNT(*)                   AS total,
        SUM(statut = 'en_attente') AS pending,
        SUM(statut = 'accepte')    AS accepted,
        SUM(statut = 'refuse')     AS refused
    FROM rendezvous
    WHERE utilisateur_id = ?
");

$stats_stmt->bind_param("i", $user_id);
$stats_stmt->execute();

$stats = $stats_stmt->get_result()->fetch_assoc();

$stats_stmt->close();

// ─────────────────────────────────────────────
// Load 3 latest bookings
// ─────────────────────────────────────────────
$recent_stmt = $conn->prepare("
    SELECT 
        r.date_rdv,
        r.heure_rdv,
        r.statut,
        u.nom   AS provider_name,
        s.nom   AS service_name
    FROM rendezvous    r
    JOIN prestataires  p ON r.prestataire_id  = p.id
    JOIN utilisateurs  u ON p.utilisateur_id  = u.id
    JOIN services      s ON p.service_id      = s.id
    WHERE r.utilisateur_id = ?
    ORDER BY r.date_rdv DESC, r.heure_rdv DESC
    LIMIT 3
");

$recent_stmt->bind_param("i", $user_id);
$recent_stmt->execute();

$recent_bookings = $recent_stmt->get_result();

$recent_stmt->close();

// ─────────────────────────────────────────────
// Check if user is also a provider
// ─────────────────────────────────────────────
$is_provider_stmt = $conn->prepare("
    SELECT id
    FROM prestataires
    WHERE utilisateur_id = ?
    LIMIT 1
");

$is_provider_stmt->bind_param("i", $user_id);
$is_provider_stmt->execute();

$is_provider_stmt->store_result();

$is_provider = $is_provider_stmt->num_rows > 0;

$is_provider_stmt->close();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard — SfaxFix</title>
    <meta name="description" content="Votre tableau de bord SfaxFix.">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        /* Quick action cards */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2.5rem;
        }

        .action-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            text-align: center;
            text-decoration: none;
            color: var(--text-primary);
            transition: var(--transition);
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.75rem;
        }

        .action-card:hover {
            border-color: var(--primary);
            box-shadow: var(--shadow-glow);
            transform: translateY(-3px);
            color: var(--text-primary);
        }

        .action-card .action-icon {
            font-size: 2rem;
        }

        .action-card .action-label {
            font-size: 0.875rem;
            font-weight: 600;
        }

        /* Welcome banner */
        .welcome-banner {
            background: linear-gradient(135deg, var(--bg-card), var(--bg-elevated));
            border: 1px solid var(--border);
            border-radius: var(--radius-xl);
            padding: 2rem 2.5rem;
            margin-bottom: 2.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1.5rem;
            flex-wrap: wrap;
            position: relative;
            overflow: hidden;
        }

        .welcome-banner::before {
            content: '';
            position: absolute;
            top: -40px; right: -40px;
            width: 200px; height: 200px;
            background: radial-gradient(circle, rgba(108,99,255,0.15), transparent 70%);
            border-radius: 50%;
            pointer-events: none;
        }

        .welcome-name {
            font-size: 1.6rem;
            font-weight: 800;
        }

        .welcome-sub {
            color: var(--text-secondary);
            margin-top: 0.25rem;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>

<?php include("../includes/navbar.php"); ?>

<main>
    <div class="page-wrapper">

        <!-- Flash Messages (shown once after login) -->
        <?php include("../includes/flash.php"); ?>

        <!-- Welcome Banner -->
        <div class="welcome-banner">
            <div>
                <div class="welcome-name">
                    👋 Bonjour, <?= htmlspecialchars($_SESSION['nom']) ?> !
                </div>
                <div class="welcome-sub">
                    Voici un aperçu de votre activité sur SfaxFix.
                </div>
            </div>
            <a href="providers.php" class="btn btn-primary">
                📅 Faire une réservation
            </a>
        </div>

        <!-- Stats Widgets -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">📋</div>
                <div class="stat-value"><?= (int)$stats['total'] ?></div>
                <div class="stat-label">Total réservations</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">⏳</div>
                <div class="stat-value"><?= (int)$stats['pending'] ?></div>
                <div class="stat-label">En attente</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">✅</div>
                <div class="stat-value"><?= (int)$stats['accepted'] ?></div>
                <div class="stat-label">Acceptées</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">❌</div>
                <div class="stat-value"><?= (int)$stats['refused'] ?></div>
                <div class="stat-label">Refusées</div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="section-divider">Actions rapides</div>
        <div class="quick-actions">
            <a href="providers.php" class="action-card">
                <span class="action-icon">🔍</span>
                <span class="action-label">Voir les prestataires</span>
            </a>
            <a href="my_bookings.php" class="action-card">
                <span class="action-icon">📅</span>
                <span class="action-label">Mes réservations</span>
            </a>
            <?php if ($is_provider): ?>
                <a href="provider_dashboard.php" class="action-card">
                    <span class="action-icon">🛠️</span>
                    <span class="action-label">Dashboard prestataire</span>
                </a>
            <?php else: ?>
                <a href="add_provider.php" class="action-card">
                    <span class="action-icon">➕</span>
                    <span class="action-label">Devenir prestataire</span>
                </a>
            <?php endif; ?>
            <a href="logout.php" class="action-card" style="border-color:rgba(239,68,68,0.2);">
                <span class="action-icon">🚪</span>
                <span class="action-label" style="color:var(--danger);">Se déconnecter</span>
            </a>
        </div>

        <!-- Recent Bookings -->
        <div class="section-divider">Réservations récentes</div>

        <?php if ($recent_bookings->num_rows > 0): ?>
            <div class="table-wrapper" style="margin-bottom: 1rem;">
                <table>
                    <thead>
                        <tr>
                            <th>Prestataire</th>
                            <th>Service</th>
                            <th>Date</th>
                            <th>Heure</th>
                            <th>Statut</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($b = $recent_bookings->fetch_assoc()): ?>
                            <?php
                                $status_map = [
                                    'en_attente' => ['class' => 'badge-pending',  'label' => 'En attente'],
                                    'accepte'    => ['class' => 'badge-accepted', 'label' => 'Accepté'],
                                    'refuse'     => ['class' => 'badge-refused',  'label' => 'Refusé'],
                                ];
                                $s = $status_map[$b['statut']] ?? ['class' => 'badge-pending', 'label' => $b['statut']];
                            ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($b['provider_name']) ?></strong></td>
                                <td><?= htmlspecialchars($b['service_name']) ?></td>
                                <td><?= date('d/m/Y', strtotime($b['date_rdv'])) ?></td>
                                <td><?= htmlspecialchars($b['heure_rdv']) ?></td>
                                <td><span class="badge <?= $s['class'] ?>"><?= $s['label'] ?></span></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            <div style="text-align:right;">
                <a href="my_bookings.php" class="btn btn-outline btn-sm">Voir toutes mes réservations →</a>
            </div>
        <?php else: ?>
            <div class="empty-state" style="padding:2.5rem 1rem;">
                <div class="empty-state-icon">📭</div>
                <h3>Aucune réservation pour l'instant</h3>
                <p>Commencez par trouver un prestataire !</p>
                <br>
                <a href="providers.php" class="btn btn-primary">🔍 Explorer les prestataires</a>
            </div>
        <?php endif; ?>

    </div><!-- /.page-wrapper -->
</main>

<?php include("../includes/footer.php"); ?>

</body>
</html>