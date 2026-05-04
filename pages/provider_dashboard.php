<?php
/**
 * pages/provider_dashboard.php
 * ===================================================
 * Dashboard Prestataire — SfaxFix
 * ===================================================
 *
 * Cette page permet à un prestataire connecté de :
 *   - Consulter les statistiques de ses réservations
 *   - Voir toutes les demandes reçues
 *   - Accepter ou refuser une réservation en attente
 *
 * Sécurité :
 *   - Accès réservé aux utilisateurs connectés
 *   - Vérification que le prestataire agit uniquement
 *     sur ses propres réservations
 *   - Utilisation de requêtes préparées pour éviter
 *     les injections SQL
 */

session_start();
include("../config/db.php");

// -------------------------------------------------------
// Vérification de connexion
// -------------------------------------------------------
if (!isset($_SESSION['user_id'])) {
    $_SESSION['flash'] = [
        'type'    => 'warning',
        'message' => 'Vous devez être connecté pour accéder au dashboard prestataire.'
    ];
    header("Location: login.php");
    exit();
}

$user_id = (int)$_SESSION['user_id'];

// -------------------------------------------------------
// Récupération du profil prestataire associé à l'utilisateur
// -------------------------------------------------------
$prov_stmt = $conn->prepare("
    SELECT p.id, p.description, p.prix, s.nom AS service_name
    FROM prestataires p
    JOIN services s ON p.service_id = s.id
    WHERE p.utilisateur_id = ?
    LIMIT 1
");
$prov_stmt->bind_param("i", $user_id);
$prov_stmt->execute();
$prov_result  = $prov_stmt->get_result();
$my_provider  = $prov_result->fetch_assoc();
$prov_stmt->close();

// Redirection si aucun profil prestataire n'existe
if (!$my_provider) {
    $_SESSION['flash'] = [
        'type'    => 'info',
        'message' => 'Vous n\'avez pas encore de profil prestataire. Créez-en un pour accéder au dashboard.'
    ];
    header("Location: add_provider.php");
    exit();
}

$provider_id = (int)$my_provider['id'];

// -------------------------------------------------------
// Gestion des actions : accepter ou refuser une réservation
// -------------------------------------------------------
$allowed_statuses = ['accepte', 'refuse'];

if (
    isset($_GET['action'], $_GET['booking_id']) &&
    in_array($_GET['action'], $allowed_statuses) &&
    is_numeric($_GET['booking_id'])
) {
    $action     = $_GET['action'];
    $booking_id = (int)$_GET['booking_id'];

    // Mise à jour uniquement si la réservation appartient au prestataire
    $update = $conn->prepare("
        UPDATE rendezvous
        SET    statut = ?
        WHERE  id = ?
          AND  prestataire_id = ?
    ");
    $update->bind_param("sii", $action, $booking_id, $provider_id);
    $update->execute();

    if ($update->affected_rows > 0) {
        $label = $action === 'accepte' ? 'acceptée' : 'refusée';
        $_SESSION['flash'] = [
            'type'    => 'success',
            'message' => "✅ Réservation $label avec succès."
        ];
    } else {
        $_SESSION['flash'] = [
            'type'    => 'danger',
            'message' => '❌ Action impossible ou réservation introuvable.'
        ];
    }
    $update->close();

    // Redirection pour éviter la répétition de l'action au rafraîchissement
    header("Location: provider_dashboard.php");
    exit();
}

// -------------------------------------------------------
// Statistiques des réservations du prestataire
// -------------------------------------------------------
$stats_stmt = $conn->prepare("
    SELECT
        COUNT(*)                   AS total,
        SUM(statut = 'en_attente') AS pending,
        SUM(statut = 'accepte')    AS accepted,
        SUM(statut = 'refuse')     AS refused
    FROM rendezvous
    WHERE prestataire_id = ?
");
$stats_stmt->bind_param("i", $provider_id);
$stats_stmt->execute();
$stats = $stats_stmt->get_result()->fetch_assoc();
$stats_stmt->close();

// -------------------------------------------------------
// Liste des réservations reçues
// -------------------------------------------------------
$bookings_stmt = $conn->prepare("
    SELECT
        r.id,
        r.date_rdv,
        r.heure_rdv,
        r.statut,
        r.note,
        r.created_at,
        u.nom   AS client_name,
        u.email AS client_email
    FROM rendezvous r
    JOIN utilisateurs u ON r.utilisateur_id = u.id
    WHERE r.prestataire_id = ?
    ORDER BY
        FIELD(r.statut, 'en_attente', 'accepte', 'refuse'),
        r.date_rdv ASC,
        r.heure_rdv ASC
");
$bookings_stmt->bind_param("i", $provider_id);
$bookings_stmt->execute();
$bookings = $bookings_stmt->get_result();
$bookings_stmt->close();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Prestataire — SfaxFix</title>
    <meta name="description" content="Gérez vos réservations en tant que prestataire sur SfaxFix.">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        /* Action buttons in table should be side by side */
        .action-btns {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        /* Provider profile info box */
        .provider-profile-banner {
            background: linear-gradient(135deg, var(--bg-card), var(--bg-elevated));
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 1.5rem 1.75rem;
            display: flex;
            align-items: center;
            gap: 1.25rem;
            margin-bottom: 2rem;
        }

        .provider-profile-banner .avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--accent));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            font-weight: 800;
            color: #fff;
            flex-shrink: 0;
        }

        .provider-profile-banner h2 {
            font-size: 1.15rem;
            font-weight: 700;
        }

        .provider-profile-banner p {
            font-size: 0.875rem;
            color: var(--text-secondary);
            margin-top: 0.15rem;
        }
    </style>
</head>
<body>

<?php include("../includes/navbar.php"); ?>

<main>
    <div class="page-wrapper">

        <!-- Page Header -->
        <div class="page-header">
            <h1 class="page-title">Dashboard Prestataire</h1>
            <p class="page-subtitle">
                Gérez vos demandes de réservation et votre agenda.
            </p>
        </div>

        <!-- Flash Messages -->
        <?php include("../includes/flash.php"); ?>

        <!-- Provider Profile Banner -->
        <div class="provider-profile-banner">
            <?php if (!empty($_SESSION['photo_profil'])): ?>
                <img src="../assets/uploads/<?= htmlspecialchars($_SESSION['photo_profil']) ?>" alt="Avatar" class="avatar" style="object-fit:cover;">
            <?php else: ?>
                <div class="avatar">
                    <?= strtoupper(mb_substr($_SESSION['nom'], 0, 1)) ?>
                </div>
            <?php endif; ?>
            <div>
                <h2><?= htmlspecialchars($_SESSION['nom']) ?></h2>
                <p>
                    <strong>Service :</strong> <?= htmlspecialchars($my_provider['service_name']) ?>
                    &nbsp;|&nbsp;
                    <strong>Prix :</strong> <?= htmlspecialchars($my_provider['prix']) ?> DT
                </p>
                <p style="margin-top:0.25rem; font-style:italic;">
                    "<?= htmlspecialchars($my_provider['description']) ?>"
                </p>
                <div style="margin-top:0.85rem;">
                    <a href="availability.php" class="btn btn-outline btn-sm">
                        📅 Gérer mes horaires
                    </a>
                </div>
            </div>
        </div>

        <!-- Statistics Widgets -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">📊</div>
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

        <!-- Bookings Management Table -->
        <div class="section-divider">Demandes de réservation</div>

        <?php if ($bookings->num_rows > 0): ?>

            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Client</th>
                            <th>Date</th>
                            <th>Heure</th>
                            <th>Note client</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($booking = $bookings->fetch_assoc()): ?>
                            <tr>
                                <!-- Booking ID -->
                                <td><strong>#<?= (int)$booking['id'] ?></strong></td>

                                <!-- Client Name + Email -->
                                <td>
                                    <strong><?= htmlspecialchars($booking['client_name']) ?></strong>
                                    <br>
                                    <span style="font-size:0.8rem; color:var(--text-muted);">
                                        <?= htmlspecialchars($booking['client_email']) ?>
                                    </span>
                                </td>

                                <!-- Date -->
                                <td><?= date('d/m/Y', strtotime($booking['date_rdv'])) ?></td>

                                <!-- Time -->
                                <td><?= htmlspecialchars($booking['heure_rdv']) ?></td>

                                <!-- Client Note -->
                                <td style="max-width:200px;">
                                    <?php if (!empty($booking['note'])): ?>
                                        <span style="
                                            font-size:0.82rem;
                                            color:var(--text-secondary);
                                            background:var(--bg-elevated);
                                            padding:0.35rem 0.65rem;
                                            border-radius:var(--radius-sm);
                                            border-left: 3px solid var(--primary);
                                            display:block;
                                            line-height:1.5;
                                        ">
                                            💬 <?= nl2br(htmlspecialchars($booking['note'])) ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="color:var(--text-muted); font-size:0.82rem;">—</span>
                                    <?php endif; ?>
                                </td>

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

                                <!-- Accept / Refuse Buttons (only for pending) -->
                                <td>
                                    <?php if ($booking['statut'] === 'en_attente'): ?>
                                        <div class="action-btns">
                                            <a href="provider_dashboard.php?action=accepte&booking_id=<?= (int)$booking['id'] ?>"
                                               class="btn btn-success btn-sm"
                                               onclick="return confirm('Accepter cette réservation ?')">
                                                ✅ Accepter
                                            </a>
                                            <a href="provider_dashboard.php?action=refuse&booking_id=<?= (int)$booking['id'] ?>"
                                               class="btn btn-danger btn-sm"
                                               onclick="return confirm('Refuser cette réservation ?')">
                                                ❌ Refuser
                                            </a>
                                        </div>
                                    <?php else: ?>
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
                <div class="empty-state-icon">📭</div>
                <h3>Aucune réservation reçue pour l'instant</h3>
                <p>Partagez votre profil avec vos clients pour recevoir des demandes.</p>
                <br>
                <a href="providers.php" class="btn btn-outline">
                    👁 Voir mon profil public
                </a>
            </div>
        <?php endif; ?>

    </div><!-- /.page-wrapper -->
</main>

<?php include("../includes/footer.php"); ?>

</body>
</html>
