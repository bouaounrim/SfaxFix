<?php
    header('Content-Type: text/html; charset=utf-8');

/**
 * pages/profile.php
 * ===================================================
 * User Profile Page — SfaxFix
 * ===================================================
 *
 * PURPOSE:
 *   This page allows an authenticated user to manage
 *   their personal account settings safely.
 *
 * FEATURES:
 *   1. Upload / update profile photo
 *   2. Change display name
 *   3. Change password securely
 *   4. View current account information
 *
 * MULTI-FORM SYSTEM:
 *   This page contains multiple independent forms.
 *   Each form includes a hidden field called:
 *
 *       form_type
 *
 *   Example:
 *       <input type="hidden" name="form_type" value="photo">
 *
 *   This allows PHP to determine which form was submitted
 *   without splitting the page into multiple files.
 *
 * SECURITY MEASURES:
 *   - Authentication guard using sessions
 *   - Prepared statements for all database queries
 *   - password_verify() for current password validation
 *   - password_hash() for secure password storage
 *   - htmlspecialchars() for safe HTML output
 *   - File extension + size validation for avatar uploads
 *
 * UX FEATURES:
 *   - Flash messages after successful updates
 *   - Password strength indicator
 *   - Real-time password confirmation check
 *   - Immediate navbar/profile sync after name/photo update
 */

session_start();
include("../config/db.php");

// ─────────────────────────────────────────────────────
// AUTHENTICATION GUARD
// Only logged-in users can access this page.
// ─────────────────────────────────────────────────────
if (!isset($_SESSION['user_id'])) {
    $_SESSION['flash'] = [
        'type'    => 'warning',
        'message' => 'Vous devez être connecté pour accéder à votre profil.'
    ];

    header("Location: login.php");
    exit();
}

$user_id = (int)$_SESSION['user_id'];

$errors  = [];
$success = '';

// ─────────────────────────────────────────────────────
// FETCH CURRENT USER DATA
// We load the existing user information to:
//   - display current name/email
//   - display profile photo
//   - pre-fill the forms
// ─────────────────────────────────────────────────────
$fetch = $conn->prepare("
    SELECT nom, email, photo_profil
    FROM utilisateurs
    WHERE id = ?
    LIMIT 1
");

$fetch->bind_param("i", $user_id);
$fetch->execute();

$user = $fetch->get_result()->fetch_assoc();

$fetch->close();

// ─────────────────────────────────────────────────────
// HANDLE FORM SUBMISSIONS
// We check which form was submitted using `form_type`.
// ─────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $form_type = $_POST['form_type'] ?? '';

    // ════════════════════════════════════════════════
    // FORM 0 — PROFILE PHOTO UPLOAD
    // ════════════════════════════════════════════════
    if ($form_type === 'photo') {

        // Ensure a file was uploaded successfully
        if (
            isset($_FILES['avatar']) &&
            $_FILES['avatar']['error'] === UPLOAD_ERR_OK
        ) {

            $tmp_name = $_FILES['avatar']['tmp_name'];
            $name     = $_FILES['avatar']['name'];
            $size     = $_FILES['avatar']['size'];

            // Allowed image extensions
            $allowed_exts = ['jpg', 'jpeg', 'png', 'webp'];

            // Extract extension safely
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));

            // ── Validation: extension ─────────────────
            if (!in_array($ext, $allowed_exts)) {

                $errors[] = "Format non autorisé. Utilisez JPG, PNG ou WEBP.";

            // ── Validation: max size = 2 MB ──────────
            } elseif ($size > 2 * 1024 * 1024) {

                $errors[] = "L'image est trop grande (Maximum 2 Mo).";

            } else {

                // Generate a unique filename to avoid collisions
                $new_filename = uniqid('user_' . $user_id . '_') . '.' . $ext;

                // Destination folder
                $upload_dir = '../assets/uploads/';

                // Final destination path
                $dest_path = $upload_dir . $new_filename;

                // Move uploaded file from temporary folder
                if (move_uploaded_file($tmp_name, $dest_path)) {

                    // Save filename in database
                    $upd_photo = $conn->prepare("
                        UPDATE utilisateurs
                        SET photo_profil = ?
                        WHERE id = ?
                    ");

                    $upd_photo->bind_param("si", $new_filename, $user_id);
                    $upd_photo->execute();
                    $upd_photo->close();

                    // Update session immediately
                    $_SESSION['photo_profil'] = $new_filename;

                    // Flash success message
                    $_SESSION['flash'] = [
                        'type'    => 'success',
                        'message' => '✅ Photo de profil mise à jour.'
                    ];

                    // Redirect to prevent form resubmission
                    header("Location: profile.php");
                    exit();

                } else {

                    $errors[] = "Erreur lors de la sauvegarde du fichier.";
                }
            }

        } else {

            $errors[] = "Veuillez sélectionner une image valide.";
        }
    }

    // ════════════════════════════════════════════════
    // FORM 1 — UPDATE DISPLAY NAME
    // ════════════════════════════════════════════════
    elseif ($form_type === 'name') {

        // Remove extra spaces
        $new_nom = trim($_POST['nom'] ?? '');

        // ── Validation ───────────────────────────────
        if (empty($new_nom)) {

            $errors[] = 'Le nom ne peut pas être vide.';

        } elseif (mb_strlen($new_nom) < 2) {

            $errors[] = 'Le nom doit contenir au moins 2 caractères.';

        } else {

            // Update database
            $upd = $conn->prepare("
                UPDATE utilisateurs
                SET nom = ?
                WHERE id = ?
            ");

            $upd->bind_param("si", $new_nom, $user_id);

            if ($upd->execute()) {

                // Update session immediately
                $_SESSION['nom'] = $new_nom;

                // Update local variable for instant UI refresh
                $user['nom'] = $new_nom;

                $_SESSION['flash'] = [
                    'type'    => 'success',
                    'message' => '✅ Nom mis à jour avec succès !'
                ];

                header("Location: profile.php");
                exit();

            } else {

                $errors[] = 'Erreur lors de la mise à jour.';
            }

            $upd->close();
        }
    }

    // ════════════════════════════════════════════════
    // FORM 2 — CHANGE PASSWORD
    // ════════════════════════════════════════════════
    elseif ($form_type === 'password') {

        // Retrieve form values
        $current_pw = $_POST['current_password'] ?? '';
        $new_pw     = $_POST['new_password'] ?? '';
        $confirm_pw = $_POST['confirm_password'] ?? '';

        // ── Validation ───────────────────────────────

        // All fields required
        if (
            empty($current_pw) ||
            empty($new_pw) ||
            empty($confirm_pw)
        ) {

            $errors[] = 'Tous les champs du mot de passe sont obligatoires.';

        // Minimum password length
        } elseif (mb_strlen($new_pw) < 6) {

            $errors[] = 'Le nouveau mot de passe doit contenir au moins 6 caractères.';

        // Confirmation check
        } elseif ($new_pw !== $confirm_pw) {

            $errors[] = 'Les nouveaux mots de passe ne correspondent pas.';

        } else {

            // Fetch current hashed password from DB
            $pw_stmt = $conn->prepare("
                SELECT mot_de_passe
                FROM utilisateurs
                WHERE id = ?
                LIMIT 1
            ");

            $pw_stmt->bind_param("i", $user_id);
            $pw_stmt->execute();

            $pw_row = $pw_stmt->get_result()->fetch_assoc();

            $pw_stmt->close();

            // Verify current password
            if (!password_verify($current_pw, $pw_row['mot_de_passe'])) {

                $errors[] = '❌ Le mot de passe actuel est incorrect.';

            } else {

                // Hash new password securely
                $new_hashed = password_hash($new_pw, PASSWORD_DEFAULT);

                // Save new password
                $upd2 = $conn->prepare("
                    UPDATE utilisateurs
                    SET mot_de_passe = ?
                    WHERE id = ?
                ");

                $upd2->bind_param("si", $new_hashed, $user_id);

                if ($upd2->execute()) {

                    $_SESSION['flash'] = [
                        'type'    => 'success',
                        'message' => '🔐 Mot de passe changé avec succès !'
                    ];

                    header("Location: profile.php");
                    exit();

                } else {

                    $errors[] = 'Erreur lors du changement de mot de passe.';
                }

                $upd2->close();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon Profil — SfaxFix</title>
    <meta name="description" content="Gérez votre profil SfaxFix.">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        /* ── Password strength indicator ── */
        .strength-bar-wrapper {
            height: 4px;
            background: var(--bg-elevated);
            border-radius: 99px;
            margin-top: 0.5rem;
            overflow: hidden;
        }

        .strength-bar {
            height: 100%;
            width: 0%;
            border-radius: 99px;
            transition: width 0.3s ease, background 0.3s ease;
        }

        .strength-label {
            font-size: 0.75rem;
            margin-top: 0.3rem;
            font-weight: 600;
        }

        /* ── Profile avatar (large, centered) ── */
        .profile-avatar-lg {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--accent));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            font-weight: 800;
            color: #fff;
            margin: 0 auto 1.25rem;
            box-shadow: 0 8px 30px rgba(108,99,255,0.35);
        }

        /* ── Profile header ── */
        .profile-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .profile-header h2 {
            font-size: 1.3rem;
            font-weight: 700;
        }

        .profile-header p {
            color: var(--text-muted);
            font-size: 0.875rem;
        }
    </style>
</head>
<body>

<?php include("../includes/navbar.php"); ?>

<main>
    <div class="page-wrapper-sm">

        <!-- Page Header -->
        <div class="page-header">
            <a href="dashboard.php" class="btn btn-outline btn-sm" style="margin-bottom:1rem;">
                ← Retour au dashboard
            </a>
            <h1 class="page-title">Mon Profil</h1>
            <p class="page-subtitle">Mettez à jour vos informations personnelles.</p>
        </div>

        <!-- Flash Messages -->
        <?php include("../includes/flash.php"); ?>

        <!-- Error Alerts -->
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <div>
                    <?php foreach ($errors as $err): ?>
                        <div>❌ <?= htmlspecialchars($err) ?></div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Profile Avatar & Name -->
        <div class="profile-header">
            <?php if (!empty($user['photo_profil'])): ?>
                <img src="../assets/uploads/<?= htmlspecialchars($user['photo_profil']) ?>" alt="Photo" class="profile-avatar-lg" style="object-fit:cover;">
            <?php else: ?>
                <div class="profile-avatar-lg">
                    <?= strtoupper(mb_substr($user['nom'], 0, 1)) ?>
                </div>
            <?php endif; ?>
            <h2><?= htmlspecialchars($user['nom']) ?></h2>
            <p><?= htmlspecialchars($user['email']) ?></p>
        </div>

        <!-- ════════════════════════════════
             FORM 0 — Upload Photo
        ════════════════════════════════ -->
        <div class="card" style="margin-bottom: 1.5rem;">
            <h3 style="font-size:1rem; font-weight:700; margin-bottom:1.25rem;">
                📷 Photo de profil
            </h3>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="form_type" value="photo">
                <div class="form-group">
                    <label class="form-label" for="avatar">Choisissez une image (JPG, PNG, WEBP - Max 2Mo)</label>
                    <input type="file" id="avatar" name="avatar" class="form-control" accept=".jpg,.jpeg,.png,.webp" required style="padding:0.4rem;">
                </div>
                <button type="submit" class="btn btn-outline">
                    ⬆️ Mettre en ligne la photo
                </button>
            </form>
        </div>

        <!-- ════════════════════════════════
             FORM 1 — Update Name
        ════════════════════════════════ -->
        <div class="card" style="margin-bottom: 1.5rem;">
            <h3 style="font-size:1rem; font-weight:700; margin-bottom:1.25rem;">
                👤 Modifier votre nom
            </h3>

            <form method="POST" novalidate>
                <!-- This hidden field tells PHP which form was submitted -->
                <input type="hidden" name="form_type" value="name">

                <div class="form-group">
                    <label class="form-label" for="nom">Nom complet</label>
                    <input
                        type="text"
                        id="nom"
                        name="nom"
                        class="form-control"
                        value="<?= htmlspecialchars($user['nom']) ?>"
                        required
                        autocomplete="name"
                    >
                </div>

                <div class="form-group">
                    <label class="form-label">Email</label>
                    <!-- Email is shown but NOT editable (for simplicity and security) -->
                    <input
                        type="email"
                        class="form-control"
                        value="<?= htmlspecialchars($user['email']) ?>"
                        disabled
                    >
                    <p style="font-size:0.78rem; color:var(--text-muted); margin-top:0.35rem;">
                        L'adresse email ne peut pas être modifiée.
                    </p>
                </div>

                <button type="submit" class="btn btn-primary">
                    💾 Sauvegarder le nom
                </button>
            </form>
        </div>

        <!-- ════════════════════════════════
             FORM 2 — Change Password
        ════════════════════════════════ -->
        <div class="card">
            <h3 style="font-size:1rem; font-weight:700; margin-bottom:1.25rem;">
                🔐 Changer votre mot de passe
            </h3>

            <form method="POST" novalidate id="passwordForm">
                <input type="hidden" name="form_type" value="password">

                <!-- Current Password (required for security) -->
                <div class="form-group">
                    <label class="form-label" for="current_password">
                        Mot de passe actuel
                    </label>
                    <input
                        type="password"
                        id="current_password"
                        name="current_password"
                        class="form-control"
                        placeholder="Entrez votre mot de passe actuel"
                        required
                        autocomplete="current-password"
                    >
                </div>

                <!-- New Password with strength meter -->
                <div class="form-group">
                    <label class="form-label" for="new_password">
                        Nouveau mot de passe
                    </label>
                    <input
                        type="password"
                        id="new_password"
                        name="new_password"
                        class="form-control"
                        placeholder="Minimum 6 caractères"
                        required
                        autocomplete="new-password"
                        oninput="checkStrength(this.value)"
                    >
                    <!-- Password Strength Bar -->
                    <div class="strength-bar-wrapper">
                        <div class="strength-bar" id="strengthBar"></div>
                    </div>
                    <p class="strength-label" id="strengthLabel" style="color:var(--text-muted);">
                        Tapez un mot de passe pour voir sa solidité
                    </p>
                </div>

                <!-- Confirm New Password -->
                <div class="form-group">
                    <label class="form-label" for="confirm_password">
                        Confirmer le nouveau mot de passe
                    </label>
                    <input
                        type="password"
                        id="confirm_password"
                        name="confirm_password"
                        class="form-control"
                        placeholder="Répétez le nouveau mot de passe"
                        required
                        autocomplete="new-password"
                        oninput="checkMatch()"
                    >
                    <p class="strength-label" id="matchLabel" style="color:var(--text-muted);"></p>
                </div>

                <button type="submit" class="btn btn-primary">
                    🔐 Changer le mot de passe
                </button>
            </form>
        </div>

        <!-- Danger Zone: Logout -->
        <div style="text-align:center; margin-top:2rem; padding-top:2rem; border-top: 1px solid var(--border);">
            <a href="logout.php" class="btn btn-danger btn-sm">
                🚪 Se déconnecter de tous les appareils
            </a>
        </div>

    </div><!-- /.page-wrapper-sm -->
</main>

<?php include("../includes/footer.php"); ?>

<script>
    /**
     * PASSWORD STRENGTH METER
     * ─────────────────────────────────────────────────
     * We check the password for 4 criteria:
     *   1. Length >= 8 characters
     *   2. Contains a number
     *   3. Contains an uppercase letter
     *   4. Contains a special character
     *
     * Score 0-1 → Weak (red)
     * Score 2   → Medium (orange)
     * Score 3   → Good (yellow-green)
     * Score 4   → Strong (green)
     */
    function checkStrength(password) {
        var bar   = document.getElementById('strengthBar');
        var label = document.getElementById('strengthLabel');
        var score = 0;

        if (password.length >= 8)          score++;  // Long enough
        if (/[0-9]/.test(password))        score++;  // Has a number
        if (/[A-Z]/.test(password))        score++;  // Has uppercase
        if (/[^A-Za-z0-9]/.test(password)) score++;  // Has special char

        // Map score to width and color
        var levels = [
            { label: '⚠️ Trop court',  color: '#ef4444', width: '10%'  },
            { label: '🔴 Faible',       color: '#ef4444', width: '25%'  },
            { label: '🟠 Moyen',        color: '#f59e0b', width: '55%'  },
            { label: '🟡 Bien',         color: '#84cc16', width: '75%'  },
            { label: '🟢 Très solide',  color: '#22c55e', width: '100%' },
        ];

        if (password.length === 0) {
            bar.style.width = '0%';
            label.textContent = 'Tapez un mot de passe pour voir sa solidité';
            label.style.color = 'var(--text-muted)';
            return;
        }

        var level = levels[score];
        bar.style.width      = level.width;
        bar.style.background = level.color;
        label.textContent    = level.label;
        label.style.color    = level.color;
    }

    /**
     * PASSWORD MATCH CHECK
     * ─────────────────────────────────────────────────
     * Runs every time the user types in the confirm field.
     * Immediately tells them if passwords match.
     */
    function checkMatch() {
        var pw1   = document.getElementById('new_password').value;
        var pw2   = document.getElementById('confirm_password').value;
        var label = document.getElementById('matchLabel');

        if (pw2.length === 0) {
            label.textContent = '';
            return;
        }

        if (pw1 === pw2) {
            label.textContent = '✅ Les mots de passe correspondent';
            label.style.color = '#22c55e';
        } else {
            label.textContent = '❌ Les mots de passe ne correspondent pas';
            label.style.color = '#ef4444';
        }
    }
</script>

</body>
</html>
