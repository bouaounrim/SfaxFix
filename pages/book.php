<?php
/**
 * pages/book.php
 * ===================================================
 * Booking Page — SfaxFix
 * ===================================================
 *
 * This page lets a logged-in user book an appointment
 * with a provider.
 *
 * FLOW:
 *  1. Get the provider from the URL
 *  2. Load provider information
 *  3. User selects a date and time
 *  4. Validate the booking
 *  5. Save the booking in the database
 *  6. Redirect to "my_bookings.php"
 *
 * SECURITY:
 *  - Only logged-in users can access this page
 *  - provider_id is converted to integer
 *  - Prepared statements protect database queries
 *  - htmlspecialchars() protects displayed output
 */

session_start();
include("../config/db.php");

// ─────────────────────────────────────────────
// 1. Require login
// ─────────────────────────────────────────────
if (!isset($_SESSION['user_id'])) {

    $_SESSION['flash'] = [
        'type'    => 'warning',
        'message' => 'Vous devez être connecté pour faire une réservation.'
    ];

    header("Location: login.php");
    exit();
}

// ─────────────────────────────────────────────
// 2. Get provider ID from URL
// ─────────────────────────────────────────────
$provider_id = (int)($_GET['provider_id'] ?? 0);

if ($provider_id <= 0) {

    $_SESSION['flash'] = [
        'type'    => 'danger',
        'message' => 'Prestataire invalide. Veuillez choisir un prestataire depuis la liste.'
    ];

    header("Location: providers.php");
    exit();
}

// ─────────────────────────────────────────────
// 3. Load provider information
// ─────────────────────────────────────────────
$stmt = $conn->prepare("
    SELECT 
        p.id,
        p.description,
        p.prix,
        u.nom   AS provider_name,
        s.nom   AS service_name
    FROM prestataires p
    JOIN utilisateurs u ON p.utilisateur_id = u.id
    JOIN services     s ON p.service_id     = s.id
    WHERE p.id = ?
    LIMIT 1
");

$stmt->bind_param("i", $provider_id);
$stmt->execute();

$result   = $stmt->get_result();
$provider = $result->fetch_assoc();

$stmt->close();

// Provider not found
if (!$provider) {

    $_SESSION['flash'] = [
        'type'    => 'danger',
        'message' => 'Ce prestataire n\'existe pas.'
    ];

    header("Location: providers.php");
    exit();
}

// ─────────────────────────────────────────────
// 4. Handle booking form submission
// ─────────────────────────────────────────────
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Form values
    $date_rdv  = trim($_POST['date_rdv']  ?? '');
    $heure_rdv = trim($_POST['heure_rdv'] ?? '');
    $note      = trim($_POST['note']      ?? '');

    // ── Validation ───────────────────────────

    // Date and time are required
    if (empty($date_rdv) || empty($heure_rdv)) {
        $errors[] = 'Veuillez remplir la date et l\'heure.';
    }

    // Prevent booking past dates
    if (!empty($date_rdv) && strtotime($date_rdv) < strtotime('today')) {
        $errors[] = 'Vous ne pouvez pas réserver une date passée.';
    }

    // Check if slot already exists
    if (empty($errors)) {

        $conflict_stmt = $conn->prepare("
            SELECT id
            FROM rendezvous
            WHERE prestataire_id = ?
              AND date_rdv        = ?
              AND heure_rdv       = ?
              AND statut         != 'refuse'
            LIMIT 1
        ");

        $conflict_stmt->bind_param(
            "iss",
            $provider_id,
            $date_rdv,
            $heure_rdv
        );

        $conflict_stmt->execute();

        $conflict_result = $conflict_stmt->get_result();

        $conflict_stmt->close();

        // Slot already booked
        if ($conflict_result->num_rows > 0) {
            $errors[] = '⏰ Ce créneau est déjà réservé. Veuillez choisir un autre horaire.';
        }
    }

    // ── Save booking ─────────────────────────
    if (empty($errors)) {

        $user_id = $_SESSION['user_id'];

        $insert = $conn->prepare("
            INSERT INTO rendezvous
                (utilisateur_id, prestataire_id, date_rdv, heure_rdv, note, statut)
            VALUES
                (?, ?, ?, ?, ?, 'en_attente')
        ");

        $insert->bind_param(
            "iisss",
            $user_id,
            $provider_id,
            $date_rdv,
            $heure_rdv,
            $note
        );

        if ($insert->execute()) {

            $insert->close();

            $_SESSION['flash'] = [
                'type'    => 'success',
                'message' => '🎉 Réservation envoyée ! En attente de confirmation du prestataire.'
            ];

            header("Location: my_bookings.php");
            exit();

        } else {

            $errors[] = 'Erreur base de données : ' . $insert->error;
        }
    }
}

// ─────────────────────────────────────────────
// 5. Load provider availability
// ─────────────────────────────────────────────
$disp_stmt = $conn->prepare("
    SELECT jour_semaine, heure_debut, heure_fin
    FROM disponibilites
    WHERE prestataire_id = ?
");

$disp_stmt->bind_param("i", $provider_id);
$disp_stmt->execute();

$disp_res = $disp_stmt->get_result();

$schedule = [];

while ($row = $disp_res->fetch_assoc()) {

    $schedule[$row['jour_semaine']] = [

        // Remove seconds from time
        'start' => substr($row['heure_debut'], 0, 5),
        'end'   => substr($row['heure_fin'], 0, 5)
    ];
}

$disp_stmt->close();

// Default schedule if provider has none
if (empty($schedule)) {

    // Monday → Saturday
    for ($i = 1; $i <= 6; $i++) {

        $schedule[$i] = [
            'start' => '08:00',
            'end'   => '18:00'
        ];
    }
}

// Convert PHP array to JSON for JavaScript
$provider_schedule_json = json_encode($schedule);

// ─────────────────────────────────────────────
// 6. Load booked slots
// ─────────────────────────────────────────────
$booked_stmt = $conn->prepare("
    SELECT date_rdv, heure_rdv
    FROM rendezvous
    WHERE prestataire_id = ?
      AND date_rdv       >= CURDATE()
      AND statut        != 'refuse'
");

$booked_stmt->bind_param("i", $provider_id);
$booked_stmt->execute();

$booked_result = $booked_stmt->get_result();

$booked_stmt->close();

// Build array of booked times
$booked_slots = [];

while ($b = $booked_result->fetch_assoc()) {

    $booked_slots[$b['date_rdv']][] = $b['heure_rdv'];
}

// Convert to JSON for JavaScript
$booked_slots_json = json_encode($booked_slots);

// Minimum selectable date
$today = date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Réserver — <?= htmlspecialchars($provider['provider_name']) ?> — SfaxFix</title>
    <meta name="description" content="Réservez un créneau avec <?= htmlspecialchars($provider['provider_name']) ?> sur SfaxFix.">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        /* Extra styles specific to the booking page */

        /* Disabled time slot style */
        .time-slot-btn[disabled] {
            opacity: 0.3;
            cursor: not-allowed;
            text-decoration: line-through;
        }

        /* Selected time slot style */
        .time-slot-btn.selected {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: #fff;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(108,99,255,0.3);
        }

        /* Grid of time slot buttons */
        .time-slots-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(90px, 1fr));
            gap: 0.6rem;
            margin-top: 0.5rem;
        }

        .time-slot-btn {
            padding: 0.55rem;
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            background: var(--bg-elevated);
            color: var(--text-secondary);
            font-family: 'Inter', sans-serif;
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            text-align: center;
        }

        .time-slot-btn:not([disabled]):hover {
            border-color: var(--primary);
            color: var(--primary);
            background: var(--primary-light);
        }

        /* Hidden actual time input synced with slot buttons */
        #heure_rdv_hidden {
            display: none;
        }
    </style>
</head>
<body>

<?php include("../includes/navbar.php"); ?>

<main>
    <div class="page-wrapper">

        <!-- Page Header -->
        <div class="page-header">
            <a href="providers.php" class="btn btn-outline btn-sm" style="margin-bottom:1rem;">
                ← Retour aux prestataires
            </a>
            <h1 class="page-title">Réserver un créneau</h1>
            <p class="page-subtitle">
                Choisissez votre date et heure — confirmation par le prestataire.
            </p>
        </div>

        <!-- Validation Errors -->
        <?php if (!empty($errors)): ?>
            <?php foreach ($errors as $error): ?>
                <div class="alert alert-danger">
                    ❌ <?= htmlspecialchars($error) ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <!-- Flash Messages (from previous redirect) -->
        <?php include("../includes/flash.php"); ?>

        <!-- Two-column layout: Form + Summary -->
        <div class="booking-layout">

            <!-- LEFT: Booking Form -->
            <div class="card">

                <form method="POST" id="bookingForm" novalidate>

                    <!-- Date Picker -->
                    <div class="form-group">
                        <label class="form-label" for="date_rdv">📅 Date du rendez-vous</label>
                        <input
                            type="date"
                            id="date_rdv"
                            name="date_rdv"
                            class="form-control"
                            min="<?= $today ?>"
                            value="<?= htmlspecialchars($_POST['date_rdv'] ?? '') ?>"
                            required
                        >
                    </div>

                    <!-- Time Slot Picker -->
                    <div class="form-group">
                        <label class="form-label">🕐 Heure du rendez-vous</label>
                        <p style="font-size:0.82rem; color:var(--text-muted); margin-bottom:0.75rem;">
                            Les créneaux <del>barrés</del> sont déjà réservés.
                        </p>

                        <!-- Visual slot grid (buttons) — populated by JS -->
                        <div class="time-slots-grid" id="timeSlotsGrid">
                            <div style="font-size:0.85rem; color:var(--text-muted); padding:1rem; text-align:center; grid-column:1/-1;">
                                Veuillez choisir une date pour voir les horaires disponibles.
                            </div>
                        </div>

                        <!-- Hidden real input — gets populated by JS -->
                        <input
                            type="hidden"
                            id="heure_rdv_hidden"
                            name="heure_rdv"
                            value="<?= htmlspecialchars($_POST['heure_rdv'] ?? '') ?>"
                            required
                        >
                        <!-- Visible label showing selected time -->
                        <p id="selectedTimeLabel" style="
                            margin-top: 0.75rem;
                            font-size: 0.85rem;
                            color: var(--text-secondary);
                        ">
                            Aucun créneau sélectionné
                        </p>
                    </div>

                    <div class="section-divider">Confirmer</div>

                    <!-- Note optionnelle (future feature) -->
                    <div class="form-group">
                        <label class="form-label" for="note">📝 Note pour le prestataire (optionnel)</label>
                        <textarea
                            id="note"
                            name="note"
                            class="form-control"
                            placeholder="Ex: Je préfère le matin. Adresse : 12 rue Ibn Khaldoun..."
                            rows="3"
                        ><?= htmlspecialchars($_POST['note'] ?? '') ?></textarea>
                    </div>

                    <!-- Submit Button -->
                    <button type="submit" class="btn btn-primary btn-lg btn-full" id="submitBtn" disabled>
                        📅 Confirmer la réservation
                    </button>

                    <p style="text-align:center; font-size:0.8rem; color:var(--text-muted); margin-top:0.75rem;">
                        Le prestataire devra accepter votre demande avant confirmation définitive.
                    </p>

                </form>
            </div><!-- /card -->

            <!-- RIGHT: Provider Summary Card -->
            <aside class="booking-summary-card">
                <h3>📋 Récapitulatif</h3>

                <div class="summary-row">
                    <span class="label">Prestataire</span>
                    <span class="value"><?= htmlspecialchars($provider['provider_name']) ?></span>
                </div>

                <div class="summary-row">
                    <span class="label">Service</span>
                    <span class="value"><?= htmlspecialchars($provider['service_name']) ?></span>
                </div>

                <div class="summary-row">
                    <span class="label">Date</span>
                    <span class="value" id="summaryDate">—</span>
                </div>

                <div class="summary-row">
                    <span class="label">Heure</span>
                    <span class="value" id="summaryTime">—</span>
                </div>

                <div class="summary-row summary-total">
                    <span class="label">Prix</span>
                    <span class="value">
                        <?= htmlspecialchars($provider['prix']) ?> DT
                    </span>
                </div>

                <br>
                <div class="alert alert-info" style="margin:0;">
                    ℹ️ La réservation sera en attente jusqu'à validation du prestataire.
                </div>
            </aside>

        </div><!-- /.booking-layout -->

    </div><!-- /.page-wrapper -->
</main>

<?php include("../includes/footer.php"); ?>

<!-- =====================================================
     JAVASCRIPT — Slot Logic
     =====================================================
     This script:
     1. Reads the booked_slots JSON from PHP (passed via data attribute)
     2. When user changes the date, disables already-booked time buttons
     3. When user clicks a slot, marks it selected and updates hidden input
     4. Enables the submit button only when both date + time are chosen
     5. Updates the summary card in real-time
===================================================== -->
<script>
    // JSON data from PHP
    const bookedSlots      = <?= $booked_slots_json ?>;
    const providerSchedule = <?= $provider_schedule_json ?>;

    const dateInput      = document.getElementById('date_rdv');
    const timeSlotsGrid  = document.getElementById('timeSlotsGrid');
    const hiddenInput    = document.getElementById('heure_rdv_hidden');
    const submitBtn      = document.getElementById('submitBtn');
    const selectedLabel  = document.getElementById('selectedTimeLabel');
    const summaryDate    = document.getElementById('summaryDate');
    const summaryTime    = document.getElementById('summaryTime');

    // ── Generate time slots (30-min intervals) between start and end ──
    function generateTimeSlots(startStr, endStr) {
        let slots = [];
        let startParts = startStr.split(':');
        let endParts   = endStr.split(':');
        
        let startHour = parseInt(startParts[0], 10);
        let startMin  = parseInt(startParts[1], 10);
        let endHour   = parseInt(endParts[0], 10);
        let endMin    = parseInt(endParts[1], 10);

        let currentHour = startHour;
        let currentMin  = startMin;

        while (currentHour < endHour || (currentHour === endHour && currentMin <= endMin)) {
            let hh = currentHour.toString().padStart(2, '0');
            let mm = currentMin.toString().padStart(2, '0');
            
            // Don't add the exact end time as a start slot if we don't want to work past it
            // Assuming "end" means "finished working by this time", we shouldn't start a 30m slot at end time
            if (currentHour === endHour && currentMin === endMin) {
                break;
            }

            slots.push(`${hh}:${mm}`);

            currentMin += 30;
            if (currentMin >= 60) {
                currentMin -= 60;
                currentHour++;
            }
        }
        return slots;
    }

    // ── On date change: generate buttons + disable booked slots ──
    dateInput.addEventListener('change', function () {
        const chosenDate = this.value;
        timeSlotsGrid.innerHTML = ''; // Clear current grid
        hiddenInput.value = '';
        selectedLabel.textContent = 'Aucun créneau sélectionné';
        summaryTime.textContent = '—';
        updateSubmitBtn();

        if (!chosenDate) {
            summaryDate.textContent = '—';
            timeSlotsGrid.innerHTML = '<div style="grid-column:1/-1; text-align:center; color:var(--text-muted); font-size:0.85rem;">Veuillez choisir une date pour voir les horaires disponibles.</div>';
            return;
        }

        // 1. Determine day of week (0 = Sunday, 1 = Monday)
        const d = new Date(chosenDate + 'T00:00:00');
        const dayOfWeek = d.getDay();

        // 2. Format date in French locale for summary
        summaryDate.textContent = d.toLocaleDateString('fr-FR', {
            weekday: 'long', year: 'numeric', month: 'long', day: 'numeric'
        });

        // 3. Check if provider works on this day
        const daySchedule = providerSchedule[dayOfWeek];
        if (!daySchedule) {
            timeSlotsGrid.innerHTML = '<div style="grid-column:1/-1; text-align:center; color:var(--danger); font-size:0.85rem; padding:1rem;">❌ Le prestataire ne travaille pas ce jour-là.</div>';
            return;
        }

        // 4. Generate the slots based on the schedule
        const availableSlots = generateTimeSlots(daySchedule.start, daySchedule.end);
        if (availableSlots.length === 0) {
            timeSlotsGrid.innerHTML = '<div style="grid-column:1/-1; text-align:center; color:var(--text-muted); font-size:0.85rem; padding:1rem;">Aucun créneau disponible.</div>';
            return;
        }

        // 5. Look up which slots are already booked on this exact date
        const takenSlots = bookedSlots[chosenDate] || [];

        // 6. Create buttons
        availableSlots.forEach(time => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'time-slot-btn';
            btn.textContent = time;
            btn.dataset.time = time;

            if (takenSlots.includes(time)) {
                btn.disabled = true;
                btn.title = 'Créneau déjà réservé';
            }

            btn.addEventListener('click', function() {
                if (btn.disabled) return;
                
                // Remove selection from all
                Array.from(timeSlotsGrid.querySelectorAll('.time-slot-btn')).forEach(b => b.classList.remove('selected'));
                
                // Select this one
                btn.classList.add('selected');
                hiddenInput.value = time;
                selectedLabel.textContent = '✅ Créneau choisi : ' + time;
                summaryTime.textContent = time;
                updateSubmitBtn();
            });

            timeSlotsGrid.appendChild(btn);
        });
    });

    // ── Enable submit only when both date and time are chosen ──
    function updateSubmitBtn() {
        const ready = dateInput.value !== '' && hiddenInput.value !== '';
        submitBtn.disabled = !ready;
    }

    // ── On page load: restore state if form was re-submitted ──
    (function restoreState() {
        const prevDate = dateInput.value;
        const prevTime = hiddenInput.value;

        if (prevDate) {
            // Trigger date change to generate buttons
            dateInput.dispatchEvent(new Event('change'));
            
            // If there was a previously selected time, select it again
            if (prevTime) {
                // Must wait slightly for the DOM update in the event above
                setTimeout(() => {
                    const btns = Array.from(timeSlotsGrid.querySelectorAll('.time-slot-btn'));
                    const matchBtn = btns.find(b => b.dataset.time === prevTime);
                    if (matchBtn && !matchBtn.disabled) {
                        matchBtn.click(); // Programmatically click to trigger state update
                    }
                }, 10);
            }
        }
    })();
</script>

</body>
</html>
