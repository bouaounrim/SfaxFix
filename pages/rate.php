<?php
/**
 * pages/rate.php
 * Leave a review for a provider
 */

session_start();
include("../config/db.php");

// Require login
if (!isset($_SESSION['user_id'])) {
    $_SESSION['flash'] = [
        'type' => 'warning',
        'message' => 'Connexion requise pour laisser un avis.'
    ];

    header("Location: login.php");
    exit();
}

$user_id    = (int)$_SESSION['user_id'];
$booking_id = (int)($_GET['booking_id'] ?? 0);

// Validate booking ID
if ($booking_id <= 0) {
    header("Location: my_bookings.php");
    exit();
}

// Load booking and provider information
// Ensure booking belongs to current user
$booking_stmt = $conn->prepare("
    SELECT
        r.id,
        r.prestataire_id,
        r.statut,
        u.nom AS provider_name,
        s.nom AS service_name
    FROM rendezvous r
    JOIN prestataires p ON r.prestataire_id = p.id
    JOIN utilisateurs u ON p.utilisateur_id = u.id
    JOIN services s ON p.service_id = s.id
    WHERE r.id = ?
      AND r.utilisateur_id = ?
    LIMIT 1
");

$booking_stmt->bind_param("ii", $booking_id, $user_id);
$booking_stmt->execute();

$booking = $booking_stmt->get_result()->fetch_assoc();

$booking_stmt->close();

// Booking not found
if (!$booking) {
    $_SESSION['flash'] = [
    'type' => 'info',
    'message' => 'Vous avez déjà laissé un avis pour cette réservation.'
];

    header("Location: my_bookings.php");
    exit();
}

// Only accepted bookings can be reviewed
if ($booking['statut'] !== 'accepte') {

    $_SESSION['flash'] = [
        'type' => 'warning',
        'message' => 'Vous ne pouvez laisser un avis que pour une réservation acceptée.'
    ];

    header("Location: my_bookings.php");
    exit();
}

$provider_id = (int)$booking['prestataire_id'];

// Check if user already reviewed this provider
$already_stmt = $conn->prepare("
    SELECT id
    FROM avis
    WHERE utilisateur_id = ?
      AND prestataire_id = ?
    LIMIT 1
");

$already_stmt->bind_param("ii", $user_id, $provider_id);
$already_stmt->execute();
$already_stmt->store_result();

$already_reviewed = $already_stmt->num_rows > 0;

$already_stmt->close();

$errors = [];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$already_reviewed) {

    $note = (int)($_POST['note'] ?? 0);

    $commentaire = trim($_POST['commentaire'] ?? '');

    // Validate rating
    if ($note < 1 || $note > 5) {
        $errors[] = 'Veuillez sélectionner une note entre 1 et 5 étoiles.';
    }

    // Validate comment length
    if (mb_strlen($commentaire) > 1000) {
        $errors[] = 'Le commentaire ne peut pas dépasser 1000 caractères.';
    }

    // Save review
    if (empty($errors)) {

        $insert = $conn->prepare("
            INSERT INTO avis (
                utilisateur_id,
                prestataire_id,
                note,
                commentaire
            )
            VALUES (?, ?, ?, ?)
        ");

        $insert->bind_param(
            "iiis",
            $user_id,
            $provider_id,
            $note,
            $commentaire
        );

        if ($insert->execute()) {

            $insert->close();

            $_SESSION['flash'] = [
                'type' => 'success',
                'message' => '⭐ Merci pour votre avis !'
            ];

            header("Location: my_bookings.php");
            exit();

        } else {

            $errors[] = 'Erreur lors de l\'enregistrement.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laisser un avis — SfaxFix</title>
    <meta name="description" content="Donnez votre avis sur votre prestataire SfaxFix.">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        /* ══════════════════════════════
           INTERACTIVE STAR RATING
           ══════════════════════════════
           Stars are label elements wrapping radio inputs.
           Trick: display the labels in REVERSE order in HTML,
           then use flex-direction: row-reverse so they appear
           left-to-right visually.

           The CSS sibling selector (~) lets us highlight
           all stars UP TO the hovered/checked one:
             input:checked ~ label { color: gold }
        ══════════════════════════════ */
        .stars-wrapper {
            display: flex;
            flex-direction: row-reverse;   /* Right to left in HTML, left to right visually */
            justify-content: flex-end;
            gap: 0.25rem;
            margin: 0.75rem 0;
        }

        /* Hide the actual radio buttons */
        .stars-wrapper input[type="radio"] {
            display: none;
        }

        .stars-wrapper label {
            font-size: 2.5rem;
            cursor: pointer;
            color: var(--bg-elevated);     /* Default: empty/dark star */
            filter: grayscale(100%);
            transition: color 0.15s, filter 0.15s, transform 0.15s;
            user-select: none;
        }

        /* When a star is checked: fill this star AND all siblings before it */
        /* (siblings BEFORE in DOM = stars with higher values because of reversed order) */
        .stars-wrapper input:checked ~ label,
        .stars-wrapper label:hover,
        .stars-wrapper label:hover ~ label {
            color: #fbbf24;               /* Gold */
            filter: grayscale(0%);
        }

        .stars-wrapper label:hover {
            transform: scale(1.15);
        }

        /* Rating legend below stars */
        .rating-legend {
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--text-secondary);
            min-height: 1.2em;
            margin-top: 0.25rem;
            transition: color 0.2s;
        }
    </style>
</head>
<body>

<?php include("../includes/navbar.php"); ?>

<main>
    <div class="page-wrapper-sm">

        <!-- Page Header -->
        <div class="page-header">
            <a href="my_bookings.php" class="btn btn-outline btn-sm" style="margin-bottom:1rem;">
                ← Retour à mes réservations
            </a>
            <h1 class="page-title">Laisser un avis</h1>
            <p class="page-subtitle">
                Partagez votre expérience avec <?= htmlspecialchars($booking['provider_name']) ?>.
            </p>
        </div>

        <!-- Flash Messages -->
        <?php include("../includes/flash.php"); ?>

        <!-- Already reviewed -->
        <?php if ($already_reviewed): ?>
            <div class="alert alert-info">
                ℹ️ Vous avez déjà laissé un avis pour ce prestataire.
            </div>
            <div style="text-align:center; margin-top:1.5rem;">
                <a href="my_bookings.php" class="btn btn-outline">← Retour</a>
            </div>

        <?php else: ?>

            <!-- Error Messages -->
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <?php foreach ($errors as $e): ?>
                        <div>❌ <?= htmlspecialchars($e) ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- Booking Context Card -->
            <div class="card" style="margin-bottom:1.5rem; padding:1.25rem 1.5rem;">
                <div style="display:flex; align-items:center; gap:1rem;">
                    <div style="
                        width:46px; height:46px; border-radius:50%;
                        background: linear-gradient(135deg, var(--primary), var(--accent));
                        display:flex; align-items:center; justify-content:center;
                        font-size:1.2rem; font-weight:800; color:#fff; flex-shrink:0;
                    ">
                        <?= strtoupper(mb_substr($booking['provider_name'], 0, 1)) ?>
                    </div>
                    <div>
                        <div style="font-weight:700;">
                            <?= htmlspecialchars($booking['provider_name']) ?>
                        </div>
                        <div style="font-size:0.85rem; color:var(--text-secondary);">
                            <?= htmlspecialchars($booking['service_name']) ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Review Form -->
            <div class="card">
                <form method="POST" novalidate id="ratingForm">

                    <!-- Star Rating -->
                    <div class="form-group">
                        <label class="form-label">⭐ Votre note</label>
                        <p style="font-size:0.82rem; color:var(--text-muted); margin-bottom:0.5rem;">
                            Cliquez sur une étoile pour noter de 1 à 5.
                        </p>

                        <!--
                            STARS: Listed in REVERSE order in HTML (5, 4, 3, 2, 1)
                            Combined with flex-direction: row-reverse,
                            they appear visually as (1, 2, 3, 4, 5) from left to right.
                            The CSS sibling selector ~ does the highlighting magic.
                        -->
                        <div class="stars-wrapper" id="starsWrapper">
                            <?php for ($i = 5; $i >= 1; $i--): ?>
                                <input
                                    type="radio"
                                    name="note"
                                    id="star<?= $i ?>"
                                    value="<?= $i ?>"
                                    <?= ((int)($_POST['note'] ?? 0) === $i) ? 'checked' : '' ?>
                                >
                                <label for="star<?= $i ?>" title="<?= $i ?> étoile<?= $i > 1 ? 's' : '' ?>">
                                    ★
                                </label>
                            <?php endfor; ?>
                        </div>

                        <!-- Description of the selected rating -->
                        <div class="rating-legend" id="ratingLegend">
                            Aucune note sélectionnée
                        </div>
                    </div>

                    <!-- Comment (optional) -->
                    <div class="form-group">
                        <label class="form-label" for="commentaire">
                            💬 Commentaire (optionnel)
                        </label>
                        <textarea
                            id="commentaire"
                            name="commentaire"
                            class="form-control"
                            rows="4"
                            placeholder="Décrivez votre expérience : ponctualité, qualité du travail, communication..."
                            maxlength="1000"
                        ><?= htmlspecialchars($_POST['commentaire'] ?? '') ?></textarea>
                        <div style="font-size:0.78rem; color:var(--text-muted); text-align:right; margin-top:0.35rem;">
                            <span id="commentCount">0</span>/1000
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary btn-lg btn-full" id="submitReview" disabled>
                        ⭐ Publier mon avis
                    </button>

                </form>
            </div>

        <?php endif; ?>

    </div><!-- /.page-wrapper-sm -->
</main>

<?php include("../includes/footer.php"); ?>

<script>
    // ── Star Rating Labels ──────────────────────────────
    // Maps the numeric score to a friendly description
    var ratingLabels = {
        1: '😞 Très décevant',
        2: '😐 Peut mieux faire',
        3: '🙂 Correct',
        4: '😊 Très bien',
        5: '🤩 Excellent — je recommande !'
    };

    var radios      = document.querySelectorAll('.stars-wrapper input[type="radio"]');
    var legend      = document.getElementById('ratingLegend');
    var submitBtn   = document.getElementById('submitReview');

    // Update legend text and enable submit when a star is clicked
    radios.forEach(function (radio) {
        radio.addEventListener('change', function () {
            legend.textContent = ratingLabels[this.value] || '';
            legend.style.color = '#fbbf24';
            if (submitBtn) submitBtn.disabled = false;
        });
    });

    // If form was re-submitted with errors, restore selected state
    (function restoreRating() {
        var checked = document.querySelector('.stars-wrapper input:checked');
        if (checked) {
            legend.textContent = ratingLabels[checked.value] || '';
            legend.style.color = '#fbbf24';
            if (submitBtn) submitBtn.disabled = false;
        }
    })();

    // ── Comment Character Counter ───────────────────────
    var commentaire  = document.getElementById('commentaire');
    var commentCount = document.getElementById('commentCount');

    if (commentaire) {
        commentaire.addEventListener('input', function () {
            commentCount.textContent = this.value.length;
        });
        commentCount.textContent = commentaire.value.length; // Init on load
    }
</script>

</body>
</html>
