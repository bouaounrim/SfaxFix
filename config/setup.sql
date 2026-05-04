-- ===================================================================
-- SfaxFix — Database Setup Script
-- Run this in phpMyAdmin > SQL tab
-- ===================================================================
--
-- HOW TO USE:
--   1. Open phpMyAdmin: http://localhost/phpmyadmin
--   2. Click on your database: sfaxfix_db
--   3. Click the "SQL" tab at the top
--   4. Paste this entire script and click "Go"
--
-- This script uses "IF NOT EXISTS" and "IF EXISTS" so it is SAFE
-- to run multiple times — it won't break anything already there.
-- ===================================================================


-- ── 1. DATABASE ────────────────────────────────────────────────────
CREATE DATABASE IF NOT EXISTS sfaxfix_db
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE sfaxfix_db;


-- ── 2. UTILISATEURS (Users) ────────────────────────────────────────
--
--  Stores all users: both clients and providers.
--  'role' column lets us distinguish between them in the future.
--
CREATE TABLE IF NOT EXISTS utilisateurs (
    id          INT          NOT NULL AUTO_INCREMENT,
    nom         VARCHAR(100) NOT NULL,
    email       VARCHAR(150) NOT NULL UNIQUE,
    mot_de_passe VARCHAR(255) NOT NULL,            -- bcrypt hashed password
    role        ENUM('user','admin') NOT NULL DEFAULT 'user',
    photo_profil VARCHAR(255) DEFAULT NULL,
    created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ── 3. SERVICES ────────────────────────────────────────────────────
--
--  The categories of services offered (Plomberie, Électricité, etc.)
--  Pre-populated with common services for Sfax.
--
CREATE TABLE IF NOT EXISTS services (
    id  INT          NOT NULL AUTO_INCREMENT,
    nom VARCHAR(100) NOT NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default services (only if the table is empty)
INSERT INTO services (nom)
SELECT nom FROM (
    SELECT 'Plomberie'      AS nom UNION ALL
    SELECT 'Électricité'    UNION ALL
    SELECT 'Peinture'       UNION ALL
    SELECT 'Ménage'         UNION ALL
    SELECT 'Jardinage'      UNION ALL
    SELECT 'Climatisation'  UNION ALL
    SELECT 'Maçonnerie'     UNION ALL
    SELECT 'Menuiserie'     UNION ALL
    SELECT 'Informatique'   UNION ALL
    SELECT 'Déménagement'
) AS new_services
WHERE NOT EXISTS (SELECT 1 FROM services LIMIT 1);


-- ── 4. PRESTATAIRES (Providers) ────────────────────────────────────
--
--  A user can register as a service provider.
--  Links to: utilisateurs (who they are) + services (what they do)
--
CREATE TABLE IF NOT EXISTS prestataires (
    id             INT            NOT NULL AUTO_INCREMENT,
    utilisateur_id INT            NOT NULL,
    service_id     INT            NOT NULL,
    description    TEXT           NOT NULL,
    prix           DECIMAL(10,2)  NOT NULL DEFAULT 0.00,
    created_at     TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id) ON DELETE CASCADE,
    FOREIGN KEY (service_id)     REFERENCES services(id)     ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ── 5. RENDEZVOUS (Bookings) ───────────────────────────────────────
--
--  The core table. Each row = one booking request.
--
--  statut values:
--    'en_attente' → Pending  (default, waiting for provider to respond)
--    'accepte'    → Accepted (provider confirmed)
--    'refuse'     → Refused  (provider declined — slot is freed up)
--
--  CONFLICT PREVENTION:
--    The UNIQUE constraint on (prestataire_id, date_rdv, heure_rdv)
--    guarantees that the SAME PROVIDER cannot have two bookings
--    at the exact same date + time at the database level.
--    Our PHP code also checks this before inserting, but the
--    database constraint is the ultimate safety net.
--
CREATE TABLE IF NOT EXISTS rendezvous (
    id             INT      NOT NULL AUTO_INCREMENT,
    utilisateur_id INT      NOT NULL,
    prestataire_id INT      NOT NULL,
    date_rdv       DATE     NOT NULL,
    heure_rdv      TIME     NOT NULL,
    statut         ENUM('en_attente','accepte','refuse') NOT NULL DEFAULT 'en_attente',
    note           TEXT,                              -- optional note from client
    created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id)  ON DELETE CASCADE,
    FOREIGN KEY (prestataire_id) REFERENCES prestataires(id)  ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ── 5b. ADD UNIQUE CONSTRAINT (if it doesn't exist yet) ────────────
--
-- This prevents double-booking at the DB level.
-- We use a workaround because MySQL doesn't have "ADD CONSTRAINT IF NOT EXISTS".
-- The PROCEDURE checks first, then adds the constraint only if missing.
--
DROP PROCEDURE IF EXISTS add_booking_unique_constraint;

DELIMITER //
CREATE PROCEDURE add_booking_unique_constraint()
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM information_schema.TABLE_CONSTRAINTS
        WHERE CONSTRAINT_SCHEMA = DATABASE()
          AND TABLE_NAME         = 'rendezvous'
          AND CONSTRAINT_NAME    = 'unique_booking_slot'
    ) THEN
        ALTER TABLE rendezvous
            ADD CONSTRAINT unique_booking_slot
            UNIQUE (prestataire_id, date_rdv, heure_rdv);
    END IF;
END //
DELIMITER ;

CALL add_booking_unique_constraint();
DROP PROCEDURE IF EXISTS add_booking_unique_constraint;


-- ── 5c. ADD `note` COLUMN (if upgrading from old version) ──────────
--
-- If your rendezvous table already exists but doesn't have the `note`
-- or `created_at` columns, these ALTER statements add them safely.
--
DROP PROCEDURE IF EXISTS upgrade_rendezvous_table;

DELIMITER //
CREATE PROCEDURE upgrade_rendezvous_table()
BEGIN
    -- Add `note` column if missing
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = 'rendezvous'
          AND COLUMN_NAME  = 'note'
    ) THEN
        ALTER TABLE rendezvous ADD COLUMN note TEXT AFTER statut;
    END IF;

    -- Add `created_at` column if missing
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = 'rendezvous'
          AND COLUMN_NAME  = 'created_at'
    ) THEN
        ALTER TABLE rendezvous
            ADD COLUMN created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER note;
    END IF;
END //
DELIMITER ;

CALL upgrade_rendezvous_table();
DROP PROCEDURE IF EXISTS upgrade_rendezvous_table;


-- ── 6. DISPONIBILITES (Availability) ──────────────────────────────
--
--  (For future use) Providers can define their weekly working hours.
--  Example: Monday 09:00-18:00
--
CREATE TABLE IF NOT EXISTS disponibilites (
    id             INT  NOT NULL AUTO_INCREMENT,
    prestataire_id INT  NOT NULL,
    jour_semaine   TINYINT NOT NULL,               -- 0=Sunday, 1=Monday...6=Saturday
    heure_debut    TIME NOT NULL,
    heure_fin      TIME NOT NULL,
    PRIMARY KEY (id),
    FOREIGN KEY (prestataire_id) REFERENCES prestataires(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ── 7. AVIS (Reviews) ─────────────────────────────────────────────
--
--  (For future use) Users can leave a rating + comment
--  after a completed booking.
--
CREATE TABLE IF NOT EXISTS avis (
    id             INT  NOT NULL AUTO_INCREMENT,
    utilisateur_id INT  NOT NULL,
    prestataire_id INT  NOT NULL,
    note           TINYINT NOT NULL DEFAULT 5,    -- Rating 1 to 5
    commentaire    TEXT,
    created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id) ON DELETE CASCADE,
    FOREIGN KEY (prestataire_id) REFERENCES prestataires(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;