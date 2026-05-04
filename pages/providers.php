<?php
header('Content-Type: text/html; charset=utf-8');

// -------------------------------------------------------
// Providers listing page
// -------------------------------------------------------

session_start();
include("../config/db.php");

// -------------------------------------------------------
// Read search parameters
// -------------------------------------------------------
$search     = trim($_GET['search'] ?? '');
$service_id = (int)($_GET['service_id'] ?? 0);

// -------------------------------------------------------
// Build dynamic SQL query
// -------------------------------------------------------
$conditions = [];
$params     = [];
$types      = '';

// Filter by service category
if ($service_id > 0) {

    $conditions[] = "p.service_id = ?";
    $params[]     = $service_id;
    $types       .= 'i';
}

// Search by provider name or description
if (!empty($search)) {

    $conditions[] = "(u.nom LIKE ? OR p.description LIKE ?)";

    $search_term = '%' . $search . '%';

    $params[] = $search_term;
    $params[] = $search_term;

    $types .= 'ss';
}

// Build WHERE clause
$where_sql = !empty($conditions)
    ? 'WHERE ' . implode(' AND ', $conditions)
    : '';

// Final SQL query
$sql = "
    SELECT
        p.id,
        p.description,
        p.prix,
        u.nom AS provider_name,
        u.photo_profil,
        s.nom AS service_name,
        s.id AS service_id,
        ROUND(AVG(a.note), 1) AS avg_rating,
        COUNT(a.id) AS review_count
    FROM prestataires p
    JOIN utilisateurs u ON p.utilisateur_id = u.id
    JOIN services s ON p.service_id = s.id
    LEFT JOIN avis a ON a.prestataire_id = p.id
    $where_sql
    GROUP BY
        p.id,
        p.description,
        p.prix,
        u.nom,
        u.photo_profil,
        s.nom,
        s.id
    ORDER BY p.id DESC
";

// Prepare query
$stmt = $conn->prepare($sql);

// Bind parameters if filters exist
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

// Execute query
$stmt->execute();

$result = $stmt->get_result();

$total = $result->num_rows;

$stmt->close();

// -------------------------------------------------------
// Load all services for dropdown filter
// -------------------------------------------------------
$services_list = $conn->query("
    SELECT id, nom
    FROM services
    ORDER BY nom ASC
");
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prestataires — SfaxFix</title>
    <meta name="description" content="Trouvez le meilleur prestataire de services à Sfax, Tunisie.">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        /* ── Search & Filter Bar ── */
        .search-bar {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 1.25rem 1.5rem;
            margin-bottom: 2rem;
            display: flex;
            gap: 1rem;
            align-items: flex-end;
            flex-wrap: wrap;
        }

        .search-bar .form-group {
            margin-bottom: 0;
            flex: 1;
            min-width: 180px;
        }

        .search-input-wrapper {
            position: relative;
        }

        .search-input-wrapper .search-icon {
            position: absolute;
            left: 0.9rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: 1rem;
            pointer-events: none;
        }

        .search-input-wrapper .form-control {
            padding-left: 2.5rem; /* Room for the icon */
        }

        /* Results count tag */
        .results-count {
            font-size: 0.85rem;
            color: var(--text-muted);
            margin-bottom: 1.5rem;
        }

        .results-count strong {
            color: var(--primary);
        }

        /* Active filter tag (shown when a filter is applied) */
        .filter-tags {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
            margin-bottom: 1.25rem;
        }

        .filter-tag {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            background: rgba(108,99,255,0.15);
            color: var(--primary);
            border: 1px solid rgba(108,99,255,0.3);
            padding: 0.3rem 0.75rem;
            border-radius: 99px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .filter-tag a {
            color: var(--primary);
            font-weight: 700;
            font-size: 1rem;
            line-height: 1;
            text-decoration: none;
        }

        .filter-tag a:hover {
            color: var(--accent);
        }
    </style>
</head>
<body>

<?php include("../includes/navbar.php"); ?>

<main>
    <div class="page-wrapper">

        <!-- Page Header -->
        <div class="page-header">
            <h1 class="page-title">Nos Prestataires</h1>
            <p class="page-subtitle">
                Trouvez le professionnel qu'il vous faut à Sfax — réservez en quelques clics.
            </p>
        </div>

        <!-- Flash Messages -->
        <?php include("../includes/flash.php"); ?>

        <!-- ── Search & Filter Form ──────────────────────────
             This form uses GET method so filters appear in the URL.
             The user can bookmark or share a filtered page.
        ──────────────────────────────────────────────────── -->
        <form method="GET" action="providers.php" id="searchForm">
            <div class="search-bar">

                <!-- Text Search -->
                <div class="form-group">
                    <label class="form-label" for="search">🔍 Rechercher</label>
                    <div class="search-input-wrapper">
                        <span class="search-icon">🔍</span>
                        <input
                            type="text"
                            id="search"
                            name="search"
                            class="form-control"
                            placeholder="Nom du prestataire ou mot-clé..."
                            value="<?= htmlspecialchars($search) ?>"
                            autocomplete="off"
                        >
                    </div>
                </div>

                <!-- Service Category Filter -->
                <div class="form-group">
                    <label class="form-label" for="service_id">🛠️ Catégorie</label>
                    <select name="service_id" id="service_id" class="form-control">
                        <option value="0">Tous les services</option>
                        <?php while ($svc = $services_list->fetch_assoc()): ?>
                            <option
                                value="<?= (int)$svc['id'] ?>"
                                <?= ($service_id === (int)$svc['id']) ? 'selected' : '' ?>
                            >
                                <?= htmlspecialchars($svc['nom']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <!-- Buttons -->
                <div style="display:flex; gap:0.5rem; align-items:flex-end;">
                    <button type="submit" class="btn btn-primary">Filtrer</button>
                    <?php if (!empty($search) || $service_id > 0): ?>
                        <a href="providers.php" class="btn btn-outline">✕ Reset</a>
                    <?php endif; ?>
                </div>
            </div>
        </form>

        <!-- Active Filter Tags (visual feedback) -->
        <?php if (!empty($search) || $service_id > 0): ?>
            <div class="filter-tags">
                <?php if (!empty($search)): ?>
                    <span class="filter-tag">
                        Recherche: "<?= htmlspecialchars($search) ?>"
                        <a href="providers.php?service_id=<?= $service_id ?>">×</a>
                    </span>
                <?php endif; ?>
                <?php if ($service_id > 0): ?>
                    <span class="filter-tag">
                        Service filtré
                        <a href="providers.php?search=<?= urlencode($search) ?>">×</a>
                    </span>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Results Count -->
        <p class="results-count">
            <strong><?= $total ?></strong>
            prestataire<?= $total !== 1 ? 's' : '' ?> trouvé<?= $total !== 1 ? 's' : '' ?>
            <?= (!empty($search) || $service_id > 0) ? 'pour votre recherche' : 'au total' ?>
        </p>

        <!-- Provider Cards Grid -->
        <?php if ($total > 0): ?>

            <div class="provider-grid">
                <?php while ($row = $result->fetch_assoc()): ?>

                    <div class="provider-card">

                        <!-- Avatar + Name + Service Badge -->
                        <div class="provider-header">
                            <?php if (!empty($row['photo_profil'])): ?>
                                <img src="../assets/uploads/<?= htmlspecialchars($row['photo_profil']) ?>" alt="Avatar" class="provider-avatar" style="object-fit:cover;">
                            <?php else: ?>
                                <div class="provider-avatar">
                                    <?= strtoupper(mb_substr($row['provider_name'], 0, 1)) ?>
                                </div>
                            <?php endif; ?>
                            <div class="provider-info">
                                <h3>
                                    <a href="provider_profile.php?id=<?= (int)$row['id'] ?>"
                                       style="color:var(--text-primary); text-decoration:none;"
                                       onmouseover="this.style.color='var(--primary)'"
                                       onmouseout="this.style.color='var(--text-primary)'">
                                        <?= htmlspecialchars($row['provider_name']) ?>
                                    </a>
                                </h3>
                                <span class="service-badge">
                                    <?= htmlspecialchars($row['service_name']) ?>
                                </span>
                            </div>
                        </div>

                        <!-- Description -->
                        <p class="provider-description">
                            <?php
                                $desc = htmlspecialchars($row['description']);
                                echo mb_strlen($desc) > 120
                                    ? mb_substr($desc, 0, 120) . '…'
                                    : $desc;
                            ?>
                        </p>

                        <!-- Star Rating Row -->
                        <div style="display:flex; align-items:center; gap:0.5rem;">
                            <?php if ($row['review_count'] > 0): ?>
                                <?php
                                    $avg     = (float)$row['avg_rating'];
                                    $full    = floor($avg);           // Full stars
                                    $half    = ($avg - $full) >= 0.5; // Half star?
                                    $empty   = 5 - $full - ($half ? 1 : 0);
                                ?>
                                <span style="color:#fbbf24; font-size:1rem; letter-spacing:1px;">
                                    <?= str_repeat('★', $full) ?>
                                    <?= $half ? '½' : '' ?>
                                    <?= str_repeat('☆', $empty) ?>
                                </span>
                                <span style="font-size:0.8rem; color:var(--text-muted);">
                                    <?= $avg ?> / 5
                                    (<?= (int)$row['review_count'] ?> avis)
                                </span>
                            <?php else: ?>
                                <span style="font-size:0.8rem; color:var(--text-muted); font-style:italic;">
                                    Pas encore d'avis
                                </span>
                            <?php endif; ?>
                        </div>

                        <!-- Price + Book Button -->
                        <div class="provider-footer">
                            <div class="provider-price">
                                <?= htmlspecialchars($row['prix']) ?>
                                <span>DT</span>
                            </div>

                            <?php if (isset($_SESSION['user_id'])): ?>
                                <a href="book.php?provider_id=<?= (int)$row['id'] ?>"
                                   class="btn btn-primary btn-sm">
                                    📅 Réserver
                                </a>
                            <?php else: ?>
                                <a href="login.php" class="btn btn-outline btn-sm">
                                    🔒 Connexion requise
                                </a>
                            <?php endif; ?>
                        </div>

                    </div><!-- /.provider-card -->

                <?php endwhile; ?>
            </div><!-- /.provider-grid -->

        <?php else: ?>
            <!-- Empty State -->
            <div class="empty-state">
                <div class="empty-state-icon">🔍</div>
                <h3>Aucun résultat trouvé</h3>
                <?php if (!empty($search) || $service_id > 0): ?>
                    <p>Essayez un autre mot-clé ou une autre catégorie.</p>
                    <br>
                    <a href="providers.php" class="btn btn-outline">Voir tous les prestataires</a>
                <?php else: ?>
                    <p>Soyez le premier à proposer vos services !</p>
                    <br>
                    <a href="add_provider.php" class="btn btn-primary">➕ Devenir prestataire</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>

    </div><!-- /.page-wrapper -->
</main>

<?php include("../includes/footer.php"); ?>

<script>
    // Auto-submit the form when the service dropdown changes
    // so the user doesn't have to click "Filtrer" every time
    document.getElementById('service_id').addEventListener('change', function () {
        document.getElementById('searchForm').submit();
    });
</script>

</body>
</html>