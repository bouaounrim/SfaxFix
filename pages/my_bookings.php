<?php
/**
 * pages/my_bookings.php
 * ===================================================
 * My Bookings Page — SfaxFix
 * ===================================================
 *
 * Shows all bookings made by the logged-in user.
 *
 * FEATURES:
 *   - View booking history
 *   - See booking status
 *   - Cancel pending bookings
 *   - Leave reviews for accepted bookings
 *
 * SECURITY:
 *   - Logged-in users only
 *   - Users can only access their own bookings
 */

session_start();
include("../config/db.php");

// ─────────────────────────────────────────────
// Require login
// ─────────────────────────────────────────────
if (!isset($_SESSION['user_id'])) {

    $_SESSION['flash'] = [
        'type'    => 'warning',
        'message' => 'Vous devez être connecté pour voir vos réservations.'
    ];

    header("Location: login.php");
    exit();
}

$user_id = (int)$_SESSION['user_id'];

// ─────────────────────────────────────────────
// Handle booking cancellation
// Only pending bookings can be cancelled
// ─────────────────────────────────────────────
if (isset($_GET['cancel']) && is_numeric($_GET['cancel'])) {

    $booking_id = (int)$_GET['cancel'];

    $cancel_stmt = $conn->prepare("
        DELETE FROM rendezvous
        WHERE id             = ?
          AND utilisateur_id = ?
          AND statut         = 'en_attente'
    ");

    $cancel_stmt->bind_param("ii", $booking_id, $user_id);

    $cancel_stmt->execute();

    if ($cancel_stmt->affected_rows > 0) {

        $_SESSION['flash'] = [
            'type'    => 'success',
            'message' => '✅ Réservation annulée avec succès.'
        ];

    } else {

        $_SESSION['flash'] = [
            'type'    => 'danger',
            'message' => '❌ Impossible d\'annuler cette réservation.'
        ];
    }

    $cancel_stmt->close();

    header("Location: my_bookings.php");
    exit();
}

// ─────────────────────────────────────────────
// Load user bookings
// ─────────────────────────────────────────────
$stmt = $conn->prepare("
    SELECT
        r.id,
        r.prestataire_id,
        r.date_rdv,
        r.heure_rdv,
        r.statut,
        r.created_at,
        u.nom AS provider_name,
        s.nom AS service_name,
        p.prix
    FROM rendezvous r
    JOIN prestataires p ON r.prestataire_id = p.id
    JOIN utilisateurs u ON p.utilisateur_id = u.id
    JOIN services s ON p.service_id = s.id
    WHERE r.utilisateur_id = ?
    ORDER BY r.date_rdv DESC, r.heure_rdv DESC
");

$stmt->bind_param("i", $user_id);

$stmt->execute();

$bookings = $stmt->get_result();

$stmt->close();

// ─────────────────────────────────────────────
// Load providers already reviewed by user
// ─────────────────────────────────────────────
$reviewed_stmt = $conn->prepare("
    SELECT prestataire_id
    FROM avis
    WHERE utilisateur_id = ?
");

$reviewed_stmt->bind_param("i", $user_id);

$reviewed_stmt->execute();

$reviewed_result = $reviewed_stmt->get_result();

$reviewed_stmt->close();

// Build array of reviewed provider IDs
$already_reviewed_ids = [];

while ($rev = $reviewed_result->fetch_assoc()) {
    $already_reviewed_ids[] = (int)$rev['prestataire_id'];
}

// ─────────────────────────────────────────────
// Load booking statistics
// ─────────────────────────────────────────────
$count_stmt = $conn->prepare("
    SELECT 
        COUNT(*) AS total,
        SUM(statut = 'en_attente') AS pending,
        SUM(statut = 'accepte')    AS accepted,
        SUM(statut = 'refuse')     AS refused
    FROM rendezvous
    WHERE utilisateur_id = ?
");

$count_stmt->bind_param("i", $user_id);

$count_stmt->execute();

$counts = $count_stmt->get_result()->fetch_assoc();

$count_stmt->close();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mes Réservations — SfaxFix</title>
    <meta name="description" content="Consultez et gérez vos réservations sur SfaxFix.">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

<?php include("../includes/navbar.php"); ?>

<main>
    <div class="page-wrapper">

        <!-- Page Header -->
        <div class="page-header">
            <h1 class="page-title">Mes Réservations</h1>
            <p class="page-subtitle">
                Bonjour <?= htmlspecialchars($_SESSION['nom']) ?> — voici l'historique de vos demandes.
            </p>
        </div>

        <!-- Flash Messages -->
        <?php include("../includes/flash.php"); ?>

        <!-- Stats Widgets -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">📋</div>
                <div class="stat-value"><?= (int)$counts['total'] ?></div>
                <div class="stat-label">Total</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">⏳</div>
                <div class="stat-value"><?= (int)$counts['pending'] ?></div>
                <div class="stat-label">En attente</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">✅</div>
                <div class="stat-value"><?= (int)$counts['accepted'] ?></div>
                <div class="stat-label">Acceptées</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">❌</div>
                <div class="stat-value"><?= (int)$counts['refused'] ?></div>
                <div class="stat-label">Refusées</div>
            </div>
        </div>

        <!-- Bookings Table -->
        <?php if ($bookings->num_rows > 0): ?>

            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Prestataire</th>
                            <th>Service</th>
                            <th>Date</th>
                            <th>Heure</th>
                            <th>Prix</th>
                            <th>Statut</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($booking = $bookings->fetch_assoc()): ?>
                            <tr>
                                <!-- Booking ID -->
                                <td><strong>#<?= (int)$booking['id'] ?></strong></td>

                                <!-- Provider Name -->
                                <td><strong><?= htmlspecialchars($booking['provider_name']) ?></strong></td>

                                <!-- Service Name -->
                                <td><?= htmlspecialchars($booking['service_name']) ?></td>

                                <!-- Date — formatted nicely -->
                                <td>
                                    <?= date('d/m/Y', strtotime($booking['date_rdv'])) ?>
                                </td>

                                <!-- Time -->
                                <td><?= htmlspecialchars($booking['heure_rdv']) ?></td>

                                <!-- Price -->
                                <td><strong><?= htmlspecialchars($booking['prix']) ?> DT</strong></td>

                                <!-- Status Badge -->
                                <td>
                                    <?php
                                        $status_map = [
                                            'en_attente' => ['class' => 'badge-pending',  'label' => 'En attente'],
                                            'accepte'    => ['class' => 'badge-accepted', 'label' => 'Accepté'],
                                            'refuse'     => ['class' => 'badge-refused',  'label' => 'Refusé'],
                                        ];
                                        $s = $status_map[$booking['statut']] ?? ['class' => 'badge-pending', 'label' => $booking['statut']];
                                    ?>
                                    <span class="badge <?= $s['class'] ?>">
                                        <?= $s['label'] ?>
                                    </span>
                                </td>

                                <!-- Action column -->
                                <td>
                                    <?php if ($booking['statut'] === 'en_attente'): ?>
                                        <!-- Pending: user can cancel -->
                                        <a href="my_bookings.php?cancel=<?= (int)$booking['id'] ?>"
                                           class="btn btn-danger btn-sm"
                                           onclick="return confirm('Annuler cette réservation ?')">
                                            Annuler
                                        </a>

                                    <?php elseif ($booking['statut'] === 'accepte'): ?>
                                        <!-- Accepted: show rate button (or "already rated") -->
                                        <?php if (in_array((int)$booking['prestataire_id'], $already_reviewed_ids)): ?>
                                            <!-- Already left a review for this provider -->
                                            <span style="color:#fbbf24; font-size:0.82rem; font-weight:600;">
                                                ⭐ Avis donné
                                            </span>
                                        <?php else: ?>
                                            <!-- Not yet rated — show the button -->
                                            <a href="rate.php?booking_id=<?= (int)$booking['id'] ?>"
                                               class="btn btn-sm"
                                               style="background:linear-gradient(135deg,#f59e0b,#d97706); color:#fff;">
                                                ⭐ Laisser un avis
                                            </a>
                                        <?php endif; ?>

                                    <?php else: ?>
                                        <!-- Refused: nothing to do -->
                                        <span style="color:var(--text-muted); font-size:0.85rem;">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>

        <?php else: ?>
            <!-- Empty State -->
            <div class="empty-state">
                <div class="empty-state-icon">📅</div>
                <h3>Aucune réservation pour l'instant</h3>
                <p>Parcourez nos prestataires et faites votre première réservation !</p>
                <br>
                <a href="providers.php" class="btn btn-primary">
                    🔍 Voir les prestataires
                </a>
            </div>
        <?php endif; ?>

    </div><!-- /.page-wrapper -->
</main>

<?php include("../includes/footer.php"); ?>

</body>
</html>
