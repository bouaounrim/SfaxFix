<?php
/**
 * pages/admin.php
 *
 * Admin dashboard of SfaxFix.
 * Only admins can access this page.
 *
 * Features:
 * - Manage users
 * - Manage providers
 * - View bookings
 * - Delete bookings/providers
 * - Change user roles
 */

session_start();
include("../config/db.php");
include("../includes/auth.php");

// Verify admin access
require_admin();

$action    = $_GET['action'] ?? '';
$target_id = (int)($_GET['id'] ?? 0);

// Delete booking
if ($action === 'delete_booking' && $target_id > 0) {

    $del = $conn->prepare("
        DELETE FROM rendezvous
        WHERE id = ?
    ");

    $del->bind_param("i", $target_id);
    $del->execute();
    $del->close();

    $_SESSION['flash'] = [
        'type' => 'success',
        'message' => '✅ Réservation supprimée.'
    ];

    header("Location: admin.php#bookings");
    exit();
}

// Delete provider
if ($action === 'delete_provider' && $target_id > 0) {

    $del = $conn->prepare("
        DELETE FROM prestataires
        WHERE id = ?
    ");

    $del->bind_param("i", $target_id);
    $del->execute();
    $del->close();

    $_SESSION['flash'] = [
        'type' => 'success',
        'message' => '✅ Prestataire supprimé.'
    ];

    header("Location: admin.php#providers");
    exit();
}

// Change user role
if ($action === 'toggle_role' && $target_id > 0) {

    // Prevent admin from changing their own role
    if ($target_id === (int)$_SESSION['user_id']) {

        $_SESSION['flash'] = [
            'type' => 'warning',
            'message' => '⚠️ Vous ne pouvez pas modifier votre propre rôle.'
        ];

        header("Location: admin.php#users");
        exit();
    }

    // Get current role
    $role_stmt = $conn->prepare("
        SELECT role
        FROM utilisateurs
        WHERE id = ?
    ");

    $role_stmt->bind_param("i", $target_id);
    $role_stmt->execute();

    $current_role = $role_stmt
        ->get_result()
        ->fetch_assoc()['role'] ?? 'user';

    $role_stmt->close();

    // Switch role
    $new_role = ($current_role === 'admin')
        ? 'user'
        : 'admin';

    $upd = $conn->prepare("
        UPDATE utilisateurs
        SET role = ?
        WHERE id = ?
    ");

    $upd->bind_param("si", $new_role, $target_id);
    $upd->execute();
    $upd->close();

    $_SESSION['flash'] = [
        'type' => 'success',
        'message' => "✅ Rôle mis à jour → $new_role"
    ];

    header("Location: admin.php#users");
    exit();
}

/* =========================
   Platform Statistics
========================= */

$stats = [
    'users' => $conn->query("
        SELECT COUNT(*) AS n
        FROM utilisateurs
    ")->fetch_assoc()['n'],

    'providers' => $conn->query("
        SELECT COUNT(*) AS n
        FROM prestataires
    ")->fetch_assoc()['n'],

    'bookings' => $conn->query("
        SELECT COUNT(*) AS n
        FROM rendezvous
    ")->fetch_assoc()['n'],

    'pending' => $conn->query("
        SELECT COUNT(*) AS n
        FROM rendezvous
        WHERE statut='en_attente'
    ")->fetch_assoc()['n'],

    'accepted' => $conn->query("
        SELECT COUNT(*) AS n
        FROM rendezvous
        WHERE statut='accepte'
    ")->fetch_assoc()['n'],

    'reviews' => $conn->query("
        SELECT COUNT(*) AS n
        FROM avis
    ")->fetch_assoc()['n'],
];

/* =========================
   Load Users
========================= */

$users_result = $conn->query("
    SELECT
        id,
        nom,
        email,
        role,
        created_at
    FROM utilisateurs
    ORDER BY created_at DESC
");

/* =========================
   Load Providers
========================= */

$providers_result = $conn->query("
    SELECT
        p.id,
        p.prix,
        u.nom   AS provider_name,
        u.email AS provider_email,
        s.nom   AS service_name,
        p.created_at
    FROM prestataires p
    JOIN utilisateurs u
        ON p.utilisateur_id = u.id
    JOIN services s
        ON p.service_id = s.id
    ORDER BY p.created_at DESC
");

/* =========================
   Load Bookings
========================= */

$bookings_result = $conn->query("
    SELECT
        r.id,
        r.date_rdv,
        r.heure_rdv,
        r.statut,
        r.created_at,
        client.nom   AS client_name,
        provider.nom AS provider_name
    FROM rendezvous r
    JOIN utilisateurs client
        ON r.utilisateur_id = client.id
    JOIN prestataires p
        ON r.prestataire_id = p.id
    JOIN utilisateurs provider
        ON p.utilisateur_id = provider.id
    ORDER BY r.created_at DESC
    LIMIT 50
");
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administration — SfaxFix</title>
    <meta name="description" content="Panel d'administration SfaxFix.">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        /* ── Admin-specific badge for role ── */
        .role-badge-admin {
            background: rgba(239,68,68,0.15);
            color: #f87171;
            border: 1px solid rgba(239,68,68,0.3);
            padding: 0.2rem 0.6rem;
            border-radius: 99px;
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
        }

        .role-badge-user {
            background: rgba(108,99,255,0.12);
            color: var(--primary);
            border: 1px solid rgba(108,99,255,0.25);
            padding: 0.2rem 0.6rem;
            border-radius: 99px;
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
        }

        /* ── Tab navigation ── */
        .admin-tabs {
            display: flex;
            gap: 0.25rem;
            border-bottom: 1px solid var(--border);
            margin-bottom: 2rem;
            overflow-x: auto;
        }

        .admin-tab {
            padding: 0.7rem 1.25rem;
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--text-secondary);
            text-decoration: none;
            border-bottom: 2px solid transparent;
            white-space: nowrap;
            transition: var(--transition);
        }

        .admin-tab:hover {
            color: var(--primary);
        }

        .admin-tab.active-tab {
            color: var(--primary);
            border-bottom-color: var(--primary);
        }

        /* ── Admin warning banner ── */
        .admin-banner {
            background: linear-gradient(135deg, rgba(239,68,68,0.12), rgba(239,68,68,0.06));
            border: 1px solid rgba(239,68,68,0.25);
            border-radius: var(--radius-md);
            padding: 0.75rem 1.25rem;
            font-size: 0.85rem;
            color: #f87171;
            font-weight: 600;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
    </style>
</head>
<body>

<?php include("../includes/navbar.php"); ?>

<main>
    <div class="page-wrapper">

        <!-- Page Header -->
        <div class="page-header">
            <h1 class="page-title">🛡️ Panel Administration</h1>
            <p class="page-subtitle">
                Connecté en tant qu'admin : <strong><?= htmlspecialchars($_SESSION['nom']) ?></strong>
            </p>
        </div>

        <!-- Admin Warning -->
        <div class="admin-banner">
            ⚠️ Zone réservée aux administrateurs. Les actions ici affectent toute la plateforme.
        </div>

        <!-- Flash Messages -->
        <?php include("../includes/flash.php"); ?>

        <!-- ════════════════════════════════════════
             STATISTICS GRID
        ════════════════════════════════════════ -->
        <div class="stats-grid" style="grid-template-columns: repeat(auto-fill, minmax(160px,1fr));">
            <div class="stat-card">
                <div class="stat-icon">👥</div>
                <div class="stat-value"><?= $stats['users'] ?></div>
                <div class="stat-label">Utilisateurs</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">🛠️</div>
                <div class="stat-value"><?= $stats['providers'] ?></div>
                <div class="stat-label">Prestataires</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">📋</div>
                <div class="stat-value"><?= $stats['bookings'] ?></div>
                <div class="stat-label">Réservations</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">⏳</div>
                <div class="stat-value"><?= $stats['pending'] ?></div>
                <div class="stat-label">En attente</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">✅</div>
                <div class="stat-value"><?= $stats['accepted'] ?></div>
                <div class="stat-label">Acceptées</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">⭐</div>
                <div class="stat-value"><?= $stats['reviews'] ?></div>
                <div class="stat-label">Avis</div>
            </div>
        </div>

        <!-- ════════════════════════════════════════
             TAB NAVIGATION
             (Uses URL hash #users, #providers, #bookings
              JavaScript shows/hides the sections)
        ════════════════════════════════════════ -->
        <div class="admin-tabs">
            <a class="admin-tab" href="#users"     onclick="showTab('users')">
                👥 Utilisateurs (<?= $stats['users'] ?>)
            </a>
            <a class="admin-tab" href="#providers" onclick="showTab('providers')">
                🛠️ Prestataires (<?= $stats['providers'] ?>)
            </a>
            <a class="admin-tab" href="#bookings"  onclick="showTab('bookings')">
                📋 Réservations (<?= $stats['bookings'] ?>)
            </a>
        </div>

        <!-- ════════════════════════════════════════
             TAB 1: USERS
        ════════════════════════════════════════ -->
        <div id="tab-users" class="tab-content">
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Nom</th>
                            <th>Email</th>
                            <th>Rôle</th>
                            <th>Inscrit le</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($u = $users_result->fetch_assoc()): ?>
                            <tr>
                                <td><strong>#<?= (int)$u['id'] ?></strong></td>
                                <td>
                                    <strong><?= htmlspecialchars($u['nom']) ?></strong>
                                    <?php if ((int)$u['id'] === (int)$_SESSION['user_id']): ?>
                                        <span style="font-size:0.75rem; color:var(--text-muted);"> (vous)</span>
                                    <?php endif; ?>
                                </td>
                                <td style="color:var(--text-muted); font-size:0.85rem;">
                                    <?= htmlspecialchars($u['email']) ?>
                                </td>
                                <td>
                                    <span class="role-badge-<?= $u['role'] ?>">
                                        <?= $u['role'] ?>
                                    </span>
                                </td>
                                <td style="font-size:0.82rem; color:var(--text-muted);">
                                    <?= date('d/m/Y', strtotime($u['created_at'])) ?>
                                </td>
                                <td>
                                    <?php if ((int)$u['id'] !== (int)$_SESSION['user_id']): ?>
                                        <a href="admin.php?action=toggle_role&id=<?= (int)$u['id'] ?>#users"
                                           class="btn btn-outline btn-sm"
                                           onclick="return confirm('Changer le rôle de <?= htmlspecialchars($u['nom']) ?> ?')">
                                            <?= $u['role'] === 'admin' ? '⬇️ → User' : '⬆️ → Admin' ?>
                                        </a>
                                    <?php else: ?>
                                        <span style="color:var(--text-muted); font-size:0.82rem;">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ════════════════════════════════════════
             TAB 2: PROVIDERS
        ════════════════════════════════════════ -->
        <div id="tab-providers" class="tab-content" style="display:none;">
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Prestataire</th>
                            <th>Service</th>
                            <th>Prix</th>
                            <th>Créé le</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($p = $providers_result->fetch_assoc()): ?>
                            <tr>
                                <td><strong>#<?= (int)$p['id'] ?></strong></td>
                                <td>
                                    <strong><?= htmlspecialchars($p['provider_name']) ?></strong>
                                    <br>
                                    <span style="font-size:0.78rem; color:var(--text-muted);">
                                        <?= htmlspecialchars($p['provider_email']) ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($p['service_name']) ?></td>
                                <td><strong><?= htmlspecialchars($p['prix']) ?> DT</strong></td>
                                <td style="font-size:0.82rem; color:var(--text-muted);">
                                    <?= date('d/m/Y', strtotime($p['created_at'])) ?>
                                </td>
                                <td>
                                    <a href="provider_profile.php?id=<?= (int)$p['id'] ?>"
                                       class="btn btn-outline btn-sm">
                                        👁 Voir
                                    </a>
                                    <a href="admin.php?action=delete_provider&id=<?= (int)$p['id'] ?>#providers"
                                       class="btn btn-danger btn-sm"
                                       onclick="return confirm('Supprimer ce prestataire ? Cette action est irréversible.')">
                                        🗑️ Supprimer
                                    </a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ════════════════════════════════════════
             TAB 3: BOOKINGS
        ════════════════════════════════════════ -->
        <div id="tab-bookings" class="tab-content" style="display:none;">
            <p style="font-size:0.82rem; color:var(--text-muted); margin-bottom:1rem;">
                Affichage des 50 réservations les plus récentes.
            </p>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Client</th>
                            <th>Prestataire</th>
                            <th>Date</th>
                            <th>Statut</th>
                            <th>Créé le</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($b = $bookings_result->fetch_assoc()):
                            $status_map = [
                                'en_attente' => ['class' => 'badge-pending',  'label' => 'En attente'],
                                'accepte'    => ['class' => 'badge-accepted', 'label' => 'Accepté'],
                                'refuse'     => ['class' => 'badge-refused',  'label' => 'Refusé'],
                            ];
                            $s = $status_map[$b['statut']] ?? ['class' => 'badge-pending', 'label' => $b['statut']];
                        ?>
                            <tr>
                                <td><strong>#<?= (int)$b['id'] ?></strong></td>
                                <td><?= htmlspecialchars($b['client_name']) ?></td>
                                <td><?= htmlspecialchars($b['provider_name']) ?></td>
                                <td><?= date('d/m/Y', strtotime($b['date_rdv'])) ?> à <?= htmlspecialchars($b['heure_rdv']) ?></td>
                                <td><span class="badge <?= $s['class'] ?>"><?= $s['label'] ?></span></td>
                                <td style="font-size:0.78rem; color:var(--text-muted);">
                                    <?= date('d/m/Y H:i', strtotime($b['created_at'])) ?>
                                </td>
                                <td>
                                    <a href="admin.php?action=delete_booking&id=<?= (int)$b['id'] ?>#bookings"
                                       class="btn btn-danger btn-sm"
                                       onclick="return confirm('Supprimer cette réservation ?')">
                                        🗑️
                                    </a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div><!-- /.page-wrapper -->
</main>

<?php include("../includes/footer.php"); ?>

<script>
    /**
     * TAB SWITCHING LOGIC
     * ─────────────────────────────────────────────────
     * We have three sections on the page (users, providers, bookings).
     * Only one is visible at a time.
     * showTab() hides all, then shows the requested one.
     *
     * WHY NOT USE THREE SEPARATE PAGES?
     *   This approach is faster (one PHP page load) and
     *   keeps all admin functionality in one place.
     *   The URL hash (#users, #providers) also lets the user
     *   bookmark or share a specific tab.
     */
    function showTab(name) {
        // Hide all tab contents
        document.querySelectorAll('.tab-content').forEach(function(el) {
            el.style.display = 'none';
        });

        // Remove active class from all tabs
        document.querySelectorAll('.admin-tab').forEach(function(el) {
            el.classList.remove('active-tab');
        });

        // Show the requested tab
        var target = document.getElementById('tab-' + name);
        if (target) target.style.display = 'block';

        // Mark the clicked tab link as active
        var links = document.querySelectorAll('.admin-tab');
        links.forEach(function(link) {
            if (link.getAttribute('href') === '#' + name) {
                link.classList.add('active-tab');
            }
        });
    }

    // On page load: check URL hash to activate the right tab
    // This makes browser Back/Forward and page refresh work correctly
    (function initTab() {
        var hash = window.location.hash.replace('#', '');
        var validTabs = ['users', 'providers', 'bookings'];
        if (validTabs.includes(hash)) {
            showTab(hash);
        } else {
            showTab('users'); // Default tab
        }
    })();
</script>

</body>
</html>
