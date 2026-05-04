<?php
/**
 * ===================================================
 * SfaxFix — Become a Provider Page
 * ===================================================
 *
 * This page allows a logged-in user to create a
 * provider profile on the platform.
 *
 * Users can:
 * - Choose a service category
 * - Add a description
 * - Set a price
 *
 * After registration, the provider becomes visible
 * in the public providers list.
 */

session_start();
include("../config/db.php");

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    $_SESSION['flash'] = [
        'type'    => 'warning',
        'message' => 'Vous devez être connecté pour devenir prestataire.'
    ];

    header("Location: login.php");
    exit();
}

$user_id = (int)$_SESSION['user_id'];
$errors  = [];

// Check if the user already has a provider profile
$check_stmt = $conn->prepare("
    SELECT id FROM prestataires WHERE utilisateur_id = ? LIMIT 1
");

$check_stmt->bind_param("i", $user_id);
$check_stmt->execute();
$check_stmt->store_result();

$already_provider = $check_stmt->num_rows > 0;

$check_stmt->close();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$already_provider) {

    $service_id  = (int)trim($_POST['service_id'] ?? 0);
    $description = trim($_POST['description'] ?? '');
    $prix        = trim($_POST['prix'] ?? '');

    // Validation
    if ($service_id <= 0) {
        $errors[] = 'Veuillez sélectionner un service.';
    }

    if (empty($description)) {
        $errors[] = 'La description est obligatoire.';
    }

    if (strlen($description) < 20) {
        $errors[] = 'La description doit contenir au moins 20 caractères.';
    }

    if (empty($prix) || !is_numeric($prix) || (float)$prix <= 0) {
        $errors[] = 'Veuillez entrer un prix valide.';
    }

    // Insert provider into database
    if (empty($errors)) {

        $prix_float = (float)$prix;

        $stmt = $conn->prepare("
            INSERT INTO prestataires (utilisateur_id, service_id, description, prix)
            VALUES (?, ?, ?, ?)
        ");

        $stmt->bind_param("iisd", $user_id, $service_id, $description, $prix_float);

        if ($stmt->execute()) {

            $stmt->close();

            $_SESSION['flash'] = [
                'type'    => 'success',
                'message' => '🎉 Votre profil prestataire a été créé !'
            ];

            header("Location: provider_dashboard.php");
            exit();

        } else {
            $errors[] = 'Erreur lors de la création du profil : ' . $stmt->error;
        }
    }
}

// Load services for the dropdown menu
$services_result = $conn->query("
    SELECT id, nom FROM services ORDER BY nom ASC
");
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Devenir prestataire — SfaxFix</title>

    <meta
        name="description"
        content="Inscrivez-vous comme prestataire de services sur SfaxFix."
    >

    <link rel="stylesheet" href="../assets/css/style.css">

    <style>

        /* Character counter */
        .char-counter {
            font-size: 0.78rem;
            color: var(--text-muted);
            text-align: right;
            margin-top: 0.35rem;
        }

        /* Information box */
        .info-box {
            background: rgba(59,130,246,0.08);
            border: 1px solid rgba(59,130,246,0.25);
            border-radius: var(--radius-md);
            padding: 1.25rem;
            margin-bottom: 1.5rem;
            font-size: 0.875rem;
            color: var(--text-secondary);
            line-height: 1.7;
        }

        .info-box strong {
            color: var(--info);
        }

    </style>
</head>
<body>

<?php include("../includes/navbar.php"); ?>

<main>
    <div class="page-wrapper-sm">

        <!-- Header -->
        <div class="page-header">

            <a
                href="dashboard.php"
                class="btn btn-outline btn-sm"
                style="margin-bottom:1rem;"
            >
                ← Retour au dashboard
            </a>

            <h1 class="page-title">Devenir prestataire</h1>

            <p class="page-subtitle">
                Proposez vos services aux habitants de Sfax.
            </p>

        </div>

        <!-- Flash Messages -->
        <?php include("../includes/flash.php"); ?>

        <!-- Already provider -->
        <?php if ($already_provider): ?>

            <div class="alert alert-info">
                ℹ️ Vous avez déjà un profil prestataire actif.
            </div>

            <div style="text-align:center; margin-top:1.5rem;">

                <a
                    href="provider_dashboard.php"
                    class="btn btn-primary btn-lg"
                >
                    🛠️ Accéder à mon dashboard
                </a>

            </div>

        <?php else: ?>

            <!-- Error messages -->
            <?php if (!empty($errors)): ?>

                <div class="alert alert-danger">

                    <div>

                        <?php foreach ($errors as $err): ?>

                            <div>
                                ❌ <?= htmlspecialchars($err) ?>
                            </div>

                        <?php endforeach; ?>

                    </div>

                </div>

            <?php endif; ?>

            <!-- Information -->
            <div class="info-box">

                <strong>ℹ️ Comment ça fonctionne ?</strong><br>

                Après votre inscription, votre profil sera visible
                dans la liste des prestataires.

            </div>

            <!-- Form -->
            <div class="card">

                <form method="POST" novalidate id="providerForm">

                    <!-- Service -->
                    <div class="form-group">

                        <label class="form-label" for="service_id">
                            🛠️ Type de service
                        </label>

                        <select
                            name="service_id"
                            id="service_id"
                            class="form-control"
                            required
                        >

                            <option value="">
                                -- Choisir un service --
                            </option>

                            <?php while ($service = $services_result->fetch_assoc()): ?>

                                <option
                                    value="<?= (int)$service['id'] ?>"
                                    <?= ((int)($_POST['service_id'] ?? 0) === (int)$service['id']) ? 'selected' : '' ?>
                                >
                                    <?= htmlspecialchars($service['nom']) ?>
                                </option>

                            <?php endwhile; ?>

                        </select>

                    </div>

                    <!-- Description -->
                    <div class="form-group">

                        <label class="form-label" for="description">
                            📝 Description
                        </label>

                        <textarea
                            id="description"
                            name="description"
                            class="form-control"
                            rows="5"
                            required
                            maxlength="1000"
                            oninput="updateCounter(this)"
                        ><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>

                        <div class="char-counter">
                            <span id="charCount">0</span> / 1000 caractères
                        </div>

                    </div>

                    <!-- Price -->
                    <div class="form-group">

                        <label class="form-label" for="prix">
                            💰 Prix (DT)
                        </label>

                        <input
                            type="number"
                            id="prix"
                            name="prix"
                            class="form-control"
                            min="1"
                            step="0.5"
                            value="<?= htmlspecialchars($_POST['prix'] ?? '') ?>"
                            required
                        >

                    </div>

                    <button
                        type="submit"
                        class="btn btn-primary btn-lg btn-full"
                    >
                        🚀 Créer mon profil
                    </button>

                </form>

            </div>

        <?php endif; ?>

    </div>
</main>

<?php include("../includes/footer.php"); ?>

<script>

    // Update character counter
    function updateCounter(textarea) {

        var count = textarea.value.length;

        document.getElementById('charCount').textContent = count;
    }

    // Run counter on page load
    var textarea = document.getElementById('description');

    if (textarea) {
        updateCounter(textarea);
    }

</script>

</body>
</html>