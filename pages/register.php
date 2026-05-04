<?php
/**
 * pages/register.php
 * User registration page
 */

session_start();
include("../config/db.php");

// Redirect logged-in users
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

// Store validation errors
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Get form values
    $nom      = trim($_POST['nom'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $confirm  = trim($_POST['confirm'] ?? '');

    // Validate required fields
    if (empty($nom) || empty($email) || empty($password)) {
        $errors[] = 'Tous les champs sont obligatoires.';
    }

    // Validate email format
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'L\'adresse email n\'est pas valide.';
    }

    // Validate password length
    if (!empty($password) && strlen($password) < 6) {
        $errors[] = 'Le mot de passe doit contenir au moins 6 caractères.';
    }

    // Check password confirmation
    if (!empty($password) && $password !== $confirm) {
        $errors[] = 'Les mots de passe ne correspondent pas.';
    }

    // Check if email already exists
    if (empty($errors)) {

        $check = $conn->prepare("
            SELECT id
            FROM utilisateurs
            WHERE email = ?
            LIMIT 1
        ");

        $check->bind_param("s", $email);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $errors[] = 'Cette adresse email est déjà utilisée.';
        }

        $check->close();
    }

    // Create account
    if (empty($errors)) {

        // Hash password before saving
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $conn->prepare("
            INSERT INTO utilisateurs (
                nom,
                email,
                mot_de_passe
            )
            VALUES (?, ?, ?)
        ");

        $stmt->bind_param(
            "sss",
            $nom,
            $email,
            $hashed_password
        );

        if ($stmt->execute()) {

            // Get new user ID
            $new_user_id = $stmt->insert_id;

            $stmt->close();

            // Auto login after registration
            $_SESSION['user_id']      = $new_user_id;
            $_SESSION['nom']          = $nom;
            $_SESSION['email']        = $email;
            $_SESSION['role']         = 'user';
            $_SESSION['photo_profil'] = null;

            $_SESSION['flash'] = [
                'type' => 'success',
                'message' => '🎉 Compte créé avec succès !'
            ];

            header("Location: dashboard.php");
            exit();

        } else {

            $errors[] = 'Erreur lors de la création du compte.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>S'inscrire — SfaxFix</title>
    <meta name="description" content="Créez votre compte SfaxFix gratuitement.">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

<?php include("../includes/navbar.php"); ?>

<main>
    <div class="auth-container">
        <div class="auth-card">

            <div class="auth-logo">⚡ SfaxFix</div>
            <h1 class="auth-title">Créer un compte</h1>
            <p class="auth-subtitle">Rejoignez la plateforme de services de Sfax. C'est gratuit !</p>

            <!-- Show all validation errors as a list -->
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <div>
                        <?php foreach ($errors as $err): ?>
                            <div>❌ <?= htmlspecialchars($err) ?></div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <form method="POST" novalidate>

                <!-- Full Name -->
                <div class="form-group">
                    <label class="form-label" for="nom">Nom complet</label>
                    <input
                        type="text"
                        id="nom"
                        name="nom"
                        class="form-control"
                        placeholder="Mohamed Ben Ali"
                        value="<?= htmlspecialchars($_POST['nom'] ?? '') ?>"
                        required
                        autocomplete="name"
                    >
                </div>

                <!-- Email -->
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

                <!-- Password -->
                <div class="form-group">
                    <label class="form-label" for="password">Mot de passe</label>
                    <input
                        type="password"
                        id="password"
                        name="password"
                        class="form-control"
                        placeholder="Minimum 6 caractères"
                        required
                        autocomplete="new-password"
                    >
                </div>

                <!-- Confirm Password -->
                <div class="form-group">
                    <label class="form-label" for="confirm">Confirmer le mot de passe</label>
                    <input
                        type="password"
                        id="confirm"
                        name="confirm"
                        class="form-control"
                        placeholder="Répétez votre mot de passe"
                        required
                        autocomplete="new-password"
                    >
                </div>

                <button type="submit" class="btn btn-primary btn-full btn-lg" style="margin-top:0.5rem;">
                    Créer mon compte →
                </button>

            </form>

            <div class="section-divider" style="margin: 1.5rem 0;">ou</div>
            <p style="text-align:center; font-size:0.875rem; color:var(--text-secondary);">
                Déjà inscrit ?
                <a href="login.php" style="font-weight:600;">Se connecter</a>
            </p>

        </div>
    </div>
</main>

<?php include("../includes/footer.php"); ?>

</body>
</html>