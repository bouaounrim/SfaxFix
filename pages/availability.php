<?php
/**
 * pages/availability.php
 *
 * Manage provider working hours.
 *
 * Providers can:
 * - Select working days
 * - Set start and end hours
 * - Save availability to the database
 */

session_start();
include("../config/db.php");
include("../includes/auth.php");

// User must be logged in
require_login();

$user_id = current_user_id();

// Verify provider account
$prov_stmt = $conn->prepare("
    SELECT id
    FROM prestataires
    WHERE utilisateur_id = ?
    LIMIT 1
");

$prov_stmt->bind_param("i", $user_id);
$prov_stmt->execute();

$prov_result = $prov_stmt
    ->get_result()
    ->fetch_assoc();

$prov_stmt->close();

if (!$prov_result) {

    $_SESSION['flash'] = [
        'type' => 'warning',
        'message' => 'Vous devez être prestataire pour gérer vos horaires.'
    ];

    header("Location: dashboard.php");
    exit();
}

$provider_id = (int)$prov_result['id'];

/* =========================
   Days of the week
========================= */

$days = [
    1 => 'Lundi',
    2 => 'Mardi',
    3 => 'Mercredi',
    4 => 'Jeudi',
    5 => 'Vendredi',
    6 => 'Samedi',
    0 => 'Dimanche'
];

$errors = [];

/* =========================
   Save Form
========================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $conn->begin_transaction();

    try {

        // Remove old schedule
        $del = $conn->prepare("
            DELETE FROM disponibilites
            WHERE prestataire_id = ?
        ");

        $del->bind_param("i", $provider_id);
        $del->execute();
        $del->close();

        // Insert new schedule
        $insert = $conn->prepare("
            INSERT INTO disponibilites
            (prestataire_id, jour_semaine, heure_debut, heure_fin)
            VALUES (?, ?, ?, ?)
        ");

        // Loop through selected days
        if (isset($_POST['working']) && is_array($_POST['working'])) {

            foreach ($_POST['working'] as $day_index => $is_working) {

                $start = $_POST['start'][$day_index] ?? '';
                $end   = $_POST['end'][$day_index] ?? '';

                if (!empty($start) && !empty($end)) {

                    // Verify end time > start time
                    if (strtotime($end) <= strtotime($start)) {

                        throw new Exception(
                            "L'heure de fin doit être après l'heure de début pour le "
                            . $days[$day_index]
                        );
                    }

                    $day_int = (int)$day_index;

                    $insert->bind_param(
                        "iiss",
                        $provider_id,
                        $day_int,
                        $start,
                        $end
                    );

                    $insert->execute();
                }
            }
        }

        $insert->close();

        $conn->commit();

        $_SESSION['flash'] = [
            'type' => 'success',
            'message' => '✅ Horaires mis à jour avec succès.'
        ];

        header("Location: provider_dashboard.php");
        exit();

    } catch (Exception $e) {

        $conn->rollback();
        $errors[] = $e->getMessage();
    }
}

/* =========================
   Load Current Schedule
========================= */

$curr_stmt = $conn->prepare("
    SELECT
        jour_semaine,
        heure_debut,
        heure_fin
    FROM disponibilites
    WHERE prestataire_id = ?
");

$curr_stmt->bind_param("i", $provider_id);
$curr_stmt->execute();

$curr_res = $curr_stmt->get_result();

$current_schedule = [];

while ($row = $curr_res->fetch_assoc()) {

    $current_schedule[$row['jour_semaine']] = [

        // Remove seconds from time
        'start' => substr($row['heure_debut'], 0, 5),
        'end'   => substr($row['heure_fin'], 0, 5)
    ];
}

$curr_stmt->close();

/* =========================
   Default Schedule
========================= */

$is_empty = empty($current_schedule);

if ($is_empty) {

    // Monday → Saturday
    for ($i = 1; $i <= 6; $i++) {

        $current_schedule[$i] = [
            'start' => '08:00',
            'end'   => '18:00'
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gérer mes horaires — SfaxFix</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .day-row {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            border: 1px solid var(--border);
            border-radius: var(--radius-md);
            margin-bottom: 0.75rem;
            background: var(--bg-card);
            transition: var(--transition);
            flex-wrap: wrap;
        }

        .day-row:hover {
            border-color: var(--primary);
        }

        .day-row.off-day {
            opacity: 0.6;
            background: rgba(0,0,0,0.2);
        }

        .day-name {
            width: 120px;
            font-weight: 700;
            font-size: 1rem;
        }

        .toggle-switch {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            width: 100px;
            cursor: pointer;
        }

        /* Checkbox hide */
        .toggle-switch input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }

        .time-inputs {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            flex: 1;
        }

        .time-inputs input[type="time"] {
            padding: 0.5rem;
            border-radius: var(--radius-sm);
            border: 1px solid var(--border);
            background: var(--bg-elevated);
            color: var(--text-primary);
            font-family: 'Inter', sans-serif;
            font-size: 0.9rem;
        }

        .time-inputs input[type="time"]:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .day-label-working { color: var(--success); font-weight: 600; font-size: 0.85rem;}
        .day-label-off     { color: var(--danger); font-weight: 600; font-size: 0.85rem;}
    </style>
</head>
<body>

<?php include("../includes/navbar.php"); ?>

<main>
    <div class="page-wrapper-sm">
        
        <div class="page-header">
            <a href="provider_dashboard.php" class="btn btn-outline btn-sm" style="margin-bottom:1rem;">
                ← Retour au dashboard
            </a>
            <h1 class="page-title">📅 Mes Horaires</h1>
            <p class="page-subtitle">Définissez vos jours et heures de travail.</p>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <?php foreach ($errors as $e): ?>
                    <div>❌ <?= htmlspecialchars($e) ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($is_empty): ?>
            <div class="alert alert-info">
                ℹ️ Vous n'avez pas encore configuré vos horaires. Un horaire par défaut vous est proposé.
            </div>
        <?php endif; ?>

        <form method="POST">
            
            <div class="card" style="padding: 1.5rem;">
                
                <!-- Loop through the 7 days -->
                <?php foreach ($days as $index => $name): 
                    $is_working = isset($current_schedule[$index]);
                    $start      = $is_working ? $current_schedule[$index]['start'] : '08:00';
                    $end        = $is_working ? $current_schedule[$index]['end']   : '18:00';
                ?>
                    
                    <div class="day-row <?= $is_working ? '' : 'off-day' ?>" id="row_<?= $index ?>">
                        
                        <!-- Day Name -->
                        <div class="day-name"><?= $name ?></div>
                        
                        <!-- Toggle Checkbox -->
                        <label class="toggle-switch">
                            <input 
                                type="checkbox" 
                                name="working[<?= $index ?>]" 
                                value="1"
                                class="day-checkbox"
                                data-target="times_<?= $index ?>"
                                data-row="row_<?= $index ?>"
                                <?= $is_working ? 'checked' : '' ?>
                            >
                            <span class="status-label <?= $is_working ? 'day-label-working' : 'day-label-off' ?>">
                                <?= $is_working ? 'Travaillé' : 'Repos' ?>
                            </span>
                        </label>

                        <!-- Time Inputs -->
                        <div class="time-inputs" id="times_<?= $index ?>">
                            <input 
                                type="time" 
                                name="start[<?= $index ?>]" 
                                value="<?= htmlspecialchars($start) ?>"
                                <?= $is_working ? '' : 'disabled' ?>
                                required
                            >
                            <span style="color:var(--text-muted);">à</span>
                            <input 
                                type="time" 
                                name="end[<?= $index ?>]" 
                                value="<?= htmlspecialchars($end) ?>"
                                <?= $is_working ? '' : 'disabled' ?>
                                required
                            >
                        </div>

                    </div>

                <?php endforeach; ?>

            </div>

            <div style="margin-top: 1.5rem;">
                <button type="submit" class="btn btn-primary btn-lg btn-full">
                    💾 Sauvegarder mes horaires
                </button>
            </div>
        </form>

    </div>
</main>

<?php include("../includes/footer.php"); ?>

<script>
    // Handle the checkbox toggle logic dynamically
    document.querySelectorAll('.day-checkbox').forEach(function(checkbox) {
        checkbox.addEventListener('change', function() {
            var targetId = this.dataset.target;
            var rowId    = this.dataset.row;
            var timeDiv  = document.getElementById(targetId);
            var rowDiv   = document.getElementById(rowId);
            var label    = this.nextElementSibling;
            
            var inputs   = timeDiv.querySelectorAll('input[type="time"]');

            if (this.checked) {
                // Working day
                inputs.forEach(i => i.disabled = false);
                rowDiv.classList.remove('off-day');
                label.textContent = 'Travaillé';
                label.className = 'status-label day-label-working';
            } else {
                // Off day
                inputs.forEach(i => i.disabled = true);
                rowDiv.classList.add('off-day');
                label.textContent = 'Repos';
                label.className = 'status-label day-label-off';
            }
        });
    });
</script>

</body>
</html>