<?php
// -------------------------------------------------------
// Public provider profile page
// -------------------------------------------------------

session_start();
include("../config/db.php");

// -------------------------------------------------------
// Get provider ID from URL
// -------------------------------------------------------
$provider_id = (int)($_GET['id'] ?? 0);

if ($provider_id <= 0) {
    header("Location: providers.php");
    exit();
}

// -------------------------------------------------------
// Fetch provider information
// -------------------------------------------------------
$prov_stmt = $conn->prepare("
    SELECT
        p.id,
        p.description,
        p.prix,
        p.created_at,
        u.nom AS provider_name,
        u.photo_profil,
        s.nom AS service_name
    FROM prestataires p
    JOIN utilisateurs u ON p.utilisateur_id = u.id
    JOIN services s ON p.service_id = s.id
    WHERE p.id = ?
    LIMIT 1
");

$prov_stmt->bind_param("i", $provider_id);
$prov_stmt->execute();

$provider = $prov_stmt->get_result()->fetch_assoc();

$prov_stmt->close();

// -------------------------------------------------------
// Redirect if provider does not exist
// -------------------------------------------------------
if (!$provider) {

    $_SESSION['flash'] = [
        'type' => 'danger',
        'message' => 'Prestataire introuvable.'
    ];

    header("Location: providers.php");
    exit();
}

// -------------------------------------------------------
// Fetch rating summary
// -------------------------------------------------------
$rating_stmt = $conn->prepare("
    SELECT
        COUNT(*) AS total_reviews,
        ROUND(AVG(note),1) AS avg_rating,
        SUM(note = 5) AS five_stars,
        SUM(note = 4) AS four_stars,
        SUM(note = 3) AS three_stars,
        SUM(note = 2) AS two_stars,
        SUM(note = 1) AS one_star
    FROM avis
    WHERE prestataire_id = ?
");

$rating_stmt->bind_param("i", $provider_id);
$rating_stmt->execute();

$rating_summary = $rating_stmt->get_result()->fetch_assoc();

$rating_stmt->close();

// -------------------------------------------------------
// Fetch provider reviews
// -------------------------------------------------------
$reviews_stmt = $conn->prepare("
    SELECT
        a.note,
        a.commentaire,
        a.created_at,
        u.nom AS reviewer_name
    FROM avis a
    JOIN utilisateurs u ON a.utilisateur_id = u.id
    WHERE a.prestataire_id = ?
    ORDER BY a.created_at DESC
");

$reviews_stmt->bind_param("i", $provider_id);
$reviews_stmt->execute();

$reviews = $reviews_stmt->get_result();

$reviews_stmt->close();

// -------------------------------------------------------
// Count accepted bookings
// -------------------------------------------------------
$done_stmt = $conn->prepare("
    SELECT COUNT(*) AS done
    FROM rendezvous
    WHERE prestataire_id = ?
      AND statut = 'accepte'
");

$done_stmt->bind_param("i", $provider_id);
$done_stmt->execute();

$done_count = $done_stmt->get_result()->fetch_assoc()['done'];

$done_stmt->close();

// -------------------------------------------------------
// Generate star icons
// -------------------------------------------------------
function render_stars($note)
{
    $note = (int)$note;

    $stars =
        str_repeat('★', $note) .
        str_repeat('☆', 5 - $note);

    return '<span style="color:#fbbf24; letter-spacing:2px;">' . $stars . '</span>';
}

// -------------------------------------------------------
// Calculate percentage for rating bars
// -------------------------------------------------------
function bar_percent($count, $total)
{
    if ($total == 0) {
        return 0;
    }

    return round(($count / $total) * 100);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($provider['provider_name']) ?> — SfaxFix</title>
    <meta name="description" content="Profil de <?= htmlspecialchars($provider['provider_name']) ?>, prestataire <?= htmlspecialchars($provider['service_name']) ?> sur SfaxFix Sfax.">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        /* ── Profile Layout: two columns ── */
        .profile-layout {
            display: grid;
            grid-template-columns: 1fr 340px;
            gap: 2rem;
            align-items: start;
        }

        /* ── Provider hero card ── */
        .provider-hero {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius-xl);
            padding: 2.5rem;
            position: relative;
            overflow: hidden;
        }

        .provider-hero::before {
            content: '';
            position: absolute;
            top: -60px; right: -60px;
            width: 220px; height: 220px;
            background: radial-gradient(circle, rgba(108,99,255,0.12), transparent 70%);
            border-radius: 50%;
            pointer-events: none;
        }

        .provider-hero-header {
            display: flex;
            align-items: center;
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .provider-avatar-xl {
            width: 72px;
            height: 72px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--accent));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            font-weight: 800;
            color: #fff;
            flex-shrink: 0;
            box-shadow: 0 8px 24px rgba(108,99,255,0.35);
        }

        /* ── Rating Breakdown (like Amazon) ── */
        .rating-bar-row {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 0.45rem;
            font-size: 0.82rem;
        }

        .rating-bar-label {
            color: var(--text-secondary);
            white-space: nowrap;
            width: 48px;
            text-align: right;
        }

        .rating-bar-track {
            flex: 1;
            height: 8px;
            background: var(--bg-elevated);
            border-radius: 99px;
            overflow: hidden;
        }

        .rating-bar-fill {
            height: 100%;
            background: linear-gradient(90deg, #fbbf24, #f59e0b);
            border-radius: 99px;
            transition: width 0.6s ease;
        }

        .rating-bar-count {
            color: var(--text-muted);
            width: 20px;
            text-align: left;
            font-size: 0.78rem;
        }

        /* ── Big average score ── */
        .big-score {
            font-size: 3.5rem;
            font-weight: 800;
            letter-spacing: -2px;
            color: var(--text-primary);
            line-height: 1;
        }

        /* ── Review card ── */
        .review-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius-md);
            padding: 1.25rem 1.5rem;
            margin-bottom: 1rem;
            transition: var(--transition);
        }

        .review-card:hover {
            border-color: var(--border-hover);
        }

        .review-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 0.6rem;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .reviewer-name {
            font-weight: 700;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .reviewer-avatar-sm {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--accent));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            font-weight: 700;
            color: #fff;
        }

        .review-date {
            font-size: 0.75rem;
            color: var(--text-muted);
        }

        .review-comment {
            font-size: 0.9rem;
            color: var(--text-secondary);
            line-height: 1.65;
            font-style: italic;
        }

        /* ── Sticky right sidebar ── */
        .booking-sidebar {
            position: sticky;
            top: 80px;
        }

        @media (max-width: 900px) {
            .profile-layout {
                grid-template-columns: 1fr;
            }
            .booking-sidebar {
                position: static;
                order: -1;
            }
        }
    </style>
</head>
<body>

<?php include("../includes/navbar.php"); ?>

<main>
    <div class="page-wrapper">

        <!-- Breadcrumb navigation -->
        <div style="margin-bottom:1.5rem;">
            <a href="providers.php" class="btn btn-outline btn-sm">← Tous les prestataires</a>
        </div>

        <!-- Flash Messages -->
        <?php include("../includes/flash.php"); ?>

        <div class="profile-layout">

            <!-- ══════════════════════════════════════
                 LEFT COLUMN: Bio + Reviews
            ══════════════════════════════════════ -->
            <div>

                <!-- Provider Hero Card -->
                <div class="provider-hero" style="margin-bottom:2rem;">
                    <div class="provider-hero-header">
                        <?php if (!empty($provider['photo_profil'])): ?>
                            <img src="../assets/uploads/<?= htmlspecialchars($provider['photo_profil']) ?>" alt="Avatar" class="provider-avatar-xl" style="object-fit:cover;">
                        <?php else: ?>
                            <div class="provider-avatar-xl">
                                <?= strtoupper(mb_substr($provider['provider_name'], 0, 1)) ?>
                            </div>
                        <?php endif; ?>
                        <div>
                            <h1 style="font-size:1.5rem; font-weight:800; margin-bottom:0.25rem;">
                                <?= htmlspecialchars($provider['provider_name']) ?>
                            </h1>
                            <span class="service-badge" style="font-size:0.85rem;">
                                <?= htmlspecialchars($provider['service_name']) ?>
                            </span>

                            <!-- Star summary under name -->
                            <?php if ($rating_summary['total_reviews'] > 0): ?>
                                <div style="margin-top:0.5rem; display:flex; align-items:center; gap:0.5rem;">
                                    <?= render_stars((int)round($rating_summary['avg_rating'])) ?>
                                    <span style="font-size:0.85rem; color:var(--text-muted);">
                                        <?= $rating_summary['avg_rating'] ?> / 5
                                        (<?= $rating_summary['total_reviews'] ?> avis)
                                    </span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Quick stat pills -->
                    <div style="display:flex; gap:1rem; flex-wrap:wrap; margin-bottom:1.5rem;">
                        <div style="
                            background:var(--bg-elevated); border:1px solid var(--border);
                            border-radius:var(--radius-sm); padding:0.5rem 1rem;
                            font-size:0.82rem; color:var(--text-secondary);
                        ">
                            ✅ <strong style="color:var(--text-primary);"><?= (int)$done_count ?></strong>
                            intervention<?= $done_count != 1 ? 's' : '' ?> réalisée<?= $done_count != 1 ? 's' : '' ?>
                        </div>
                        <div style="
                            background:var(--bg-elevated); border:1px solid var(--border);
                            border-radius:var(--radius-sm); padding:0.5rem 1rem;
                            font-size:0.82rem; color:var(--text-secondary);
                        ">
                            💰 <strong style="color:var(--text-primary);">
                                <?= htmlspecialchars($provider['prix']) ?> DT
                            </strong> / prestation
                        </div>
                        <div style="
                            background:var(--bg-elevated); border:1px solid var(--border);
                            border-radius:var(--radius-sm); padding:0.5rem 1rem;
                            font-size:0.82rem; color:var(--text-secondary);
                        ">
                            📅 Membre depuis <?= date('M Y', strtotime($provider['created_at'])) ?>
                        </div>
                    </div>

                    <!-- Description -->
                    <div style="
                        color:var(--text-secondary);
                        font-size:0.95rem;
                        line-height:1.75;
                        padding-top:1.25rem;
                        border-top:1px solid var(--border);
                    ">
                        <?= nl2br(htmlspecialchars($provider['description'])) ?>
                    </div>
                </div>

                <!-- ══════════════════════════════════
                     Rating Breakdown
                ══════════════════════════════════ -->
                <?php if ($rating_summary['total_reviews'] > 0): ?>

                    <div class="card" style="margin-bottom:2rem;">
                        <h2 style="font-size:1rem; font-weight:700; margin-bottom:1.5rem;">
                            ⭐ Évaluations (<?= $rating_summary['total_reviews'] ?> avis)
                        </h2>

                        <div style="display:flex; gap:2rem; align-items:center; flex-wrap:wrap;">

                            <!-- Big Average Score -->
                            <div style="text-align:center; flex-shrink:0;">
                                <div class="big-score"><?= $rating_summary['avg_rating'] ?></div>
                                <div style="color:#fbbf24; font-size:1.3rem; margin:0.35rem 0;">
                                    <?= render_stars((int)round($rating_summary['avg_rating'])) ?>
                                </div>
                                <div style="font-size:0.78rem; color:var(--text-muted);">
                                    sur 5 étoiles
                                </div>
                            </div>

                            <!-- Star Breakdown Bars -->
                            <div style="flex:1; min-width:200px;">
                                <?php
                                    $breakdown = [
                                        5 => $rating_summary['five_stars'],
                                        4 => $rating_summary['four_stars'],
                                        3 => $rating_summary['three_stars'],
                                        2 => $rating_summary['two_stars'],
                                        1 => $rating_summary['one_star'],
                                    ];
                                    foreach ($breakdown as $stars => $count):
                                        $pct = bar_percent($count, $rating_summary['total_reviews']);
                                ?>
                                    <div class="rating-bar-row">
                                        <span class="rating-bar-label"><?= $stars ?> ★</span>
                                        <div class="rating-bar-track">
                                            <div class="rating-bar-fill" style="width:<?= $pct ?>%;"></div>
                                        </div>
                                        <span class="rating-bar-count"><?= (int)$count ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                        </div>
                    </div>

                <?php endif; ?>

                <!-- ══════════════════════════════════
                     Individual Reviews
                ══════════════════════════════════ -->
                <h2 style="font-size:1.05rem; font-weight:700; margin-bottom:1.25rem;">
                    💬 Commentaires clients
                </h2>

                <?php if ($reviews->num_rows > 0): ?>

                    <?php while ($review = $reviews->fetch_assoc()): ?>
                        <div class="review-card">
                            <div class="review-header">
                                <div class="reviewer-name">
                                    <div class="reviewer-avatar-sm">
                                        <?= strtoupper(mb_substr($review['reviewer_name'], 0, 1)) ?>
                                    </div>
                                    <?= htmlspecialchars($review['reviewer_name']) ?>
                                </div>
                                <div style="display:flex; align-items:center; gap:0.75rem;">
                                    <?= render_stars($review['note']) ?>
                                    <span class="review-date">
                                        <?= date('d/m/Y', strtotime($review['created_at'])) ?>
                                    </span>
                                </div>
                            </div>

                            <?php if (!empty($review['commentaire'])): ?>
                                <p class="review-comment">
                                    "<?= nl2br(htmlspecialchars($review['commentaire'])) ?>"
                                </p>
                            <?php else: ?>
                                <p style="font-size:0.82rem; color:var(--text-muted); font-style:italic;">
                                    Aucun commentaire laissé.
                                </p>
                            <?php endif; ?>
                        </div>
                    <?php endwhile; ?>

                <?php else: ?>
                    <div class="empty-state" style="padding:2rem 1rem;">
                        <div class="empty-state-icon">💬</div>
                        <h3>Pas encore d'avis</h3>
                        <p>Soyez le premier à évaluer ce prestataire après votre réservation !</p>
                    </div>
                <?php endif; ?>

            </div><!-- /left column -->

            <!-- ══════════════════════════════════════
                 RIGHT COLUMN: Booking Sidebar
            ══════════════════════════════════════ -->
            <div class="booking-sidebar">
                <div class="booking-summary-card">
                    <h3>📋 Réserver ce prestataire</h3>

                    <div class="summary-row">
                        <span class="label">Prestataire</span>
                        <span class="value"><?= htmlspecialchars($provider['provider_name']) ?></span>
                    </div>
                    <div class="summary-row">
                        <span class="label">Service</span>
                        <span class="value"><?= htmlspecialchars($provider['service_name']) ?></span>
                    </div>
                    <div class="summary-row summary-total">
                        <span class="label">Prix</span>
                        <span class="value"><?= htmlspecialchars($provider['prix']) ?> DT</span>
                    </div>

                    <br>

                    <?php if (isset($_SESSION['user_id'])): ?>
                        <a href="book.php?provider_id=<?= (int)$provider_id ?>"
                           class="btn btn-primary btn-full btn-lg">
                            📅 Réserver maintenant
                        </a>
                    <?php else: ?>
                        <a href="login.php" class="btn btn-outline btn-full">
                            🔒 Connectez-vous pour réserver
                        </a>
                        <p style="text-align:center; font-size:0.78rem; color:var(--text-muted); margin-top:0.75rem;">
                            Pas encore de compte ?
                            <a href="register.php">Inscription gratuite</a>
                        </p>
                    <?php endif; ?>

                    <br>
                    <div class="alert alert-info" style="margin:0;">
                        ℹ️ La réservation sera en attente de confirmation du prestataire.
                    </div>
                </div>

                <!-- Share Card -->
                <div style="
                    background:var(--bg-card); border:1px solid var(--border);
                    border-radius:var(--radius-lg); padding:1.25rem 1.5rem;
                    margin-top:1rem; font-size:0.82rem;
                ">
                    <strong style="display:block; margin-bottom:0.5rem;">📤 Partager ce profil</strong>
                    <input
                        type="text"
                        class="form-control"
                        id="profileUrl"
                        readonly
                        value="http://localhost/SfaxFix/pages/provider_profile.php?id=<?= (int)$provider_id ?>"
                        style="font-size:0.75rem; cursor:pointer;"
                        onclick="copyUrl(this)"
                        title="Cliquer pour copier"
                    >
                    <p style="color:var(--text-muted); margin-top:0.4rem;">
                        Cliquez sur le lien pour copier
                    </p>
                </div>
            </div><!-- /right column -->

        </div><!-- /.profile-layout -->

    </div><!-- /.page-wrapper -->
</main>

<?php include("../includes/footer.php"); ?>

<script>
    function copyUrl(input) {
        input.select();
        input.setSelectionRange(0, 99999); // For mobile
        try {
            navigator.clipboard.writeText(input.value).then(function() {
                input.style.borderColor = 'var(--success)';
                setTimeout(function() {
                    input.style.borderColor = '';
                }, 2000);
            });
        } catch (e) {
            document.execCommand('copy'); // Fallback for older browsers
        }
    }
</script>

</body>
</html>
