<?php
/**
 * pages/login.php
 * ===================================================
 * User Login Page — SfaxFix
 * ===================================================
 *
 * This page allows users to log into their account.
 *
 * FLOW:
 *  1. Start the session
 *  2. If form submitted:
 *      - Get email and password
 *      - Search user by email
 *      - Verify password
 *      - Save user data in session
 *      - Redirect to dashboard
 *  3. Show login form
 *
 * SECURITY:
 *  - Prepared statements protect SQL queries
 *  - password_verify() checks hashed passwords
 *  - Sessions keep users logged in
 */

session_start();
include("../config/db.php");

// ─────────────────────────────────────────────
// Redirect if already logged in
// ─────────────────────────────────────────────
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

// ─────────────────────────────────────────────
// Handle login form
// ─────────────────────────────────────────────
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Get form values
    $email    = trim($_POST['email']    ?? '');
    $password = trim($_POST['password'] ?? '');

    // Both fields required
    if (empty($email) || empty($password)) {

        $error = 'Veuillez remplir tous les champs.';

    } else {

        // Search user by email
        $stmt = $conn->prepare("
            SELECT *
            FROM utilisateurs
            WHERE email = ?
            LIMIT 1
        ");

        $stmt->bind_param("s", $email);

        $stmt->execute();

        $result = $stmt->get_result();

        $user = $result->fetch_assoc();

        $stmt->close();

        // Verify password
        if ($user) {

            if (password_verify($password, $user['mot_de_passe'])) {

                // Save user info in session
                $_SESSION['user_id']      = $user['id'];
                $_SESSION['nom']          = $user['nom'];
                $_SESSION['email']        = $user['email'];
                $_SESSION['role']         = $user['role'] ?? 'user';
                $_SESSION['photo_profil'] = $user['photo_profil'] ?? null;

                // Flash message
                $_SESSION['flash'] = [
                    'type'    => 'success',
                    'message' => 'Bienvenue, ' . $user['nom'] . ' ! 👋'
                ];

                // Redirect after login
                header("Location: dashboard.php");
                exit();

            } else {

                $error = 'Mot de passe incorrect. Réessayez.';
            }

        } else {

            $error = 'Aucun compte trouvé avec cet email.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion — SfaxFix</title>
    <meta name="description" content="Connectez-vous à votre compte SfaxFix.">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

<?php include("../includes/navbar.php"); ?>

<main>
    <div class="auth-container">
        <div class="auth-card">

            <!-- Logo / App Name -->
            <div class="auth-logo">⚡ SfaxFix</div>
            <h1 class="auth-title">Connexion</h1>
            <p class="auth-subtitle">Bon retour ! Entrez vos identifiants ci-dessous.</p>

            <!-- Logout success message -->
            <?php if (isset($_GET['logged_out'])): ?>
                <div class="alert alert-success">✅ Vous avez été déconnecté avec succès.</div>
            <?php endif; ?>

            <!-- Error Alert -->
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger">❌ <?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <!-- Login Form -->
            <form method="POST" novalidate>

                <div class="form-group">
                    <label class="form-label" for="email">Adresse email</label>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        class="form-control"
                        placeholder="vous@exemple.com"
                        value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                        required
                        autocomplete="email"
                    >
                </div>

                <div class="form-group">
                    <label class="form-label" for="password">Mot de passe</label>
                    <input
                        type="password"
                        id="password"
                        name="password"
                        class="form-control"
                        placeholder="Votre mot de passe"
                        required
                        autocomplete="current-password"
                    >
                </div>

                <button type="submit" class="btn btn-primary btn-full btn-lg" style="margin-top:0.5rem;">
                    Se connecter →
                </button>

            </form>

            <!-- Link to registration -->
            <div class="section-divider" style="margin: 1.5rem 0;">ou</div>
            <p style="text-align:center; font-size:0.875rem; color:var(--text-secondary);">
                Pas encore de compte ?
                <a href="register.php" style="font-weight:600;">Créer un compte</a>
            </p>

        </div>
    </div>
</main>

<?php include("../includes/footer.php"); ?>

</body>
</html>