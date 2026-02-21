<?php
/**
 * Gestion CRUD des upsells (services additionnels)
 * Tables : riwa_upsells + riwa_booking_upsells
 */

if (!defined('ABSPATH')) {
    exit;
}

class Riwa_Upsells_Table {

    /** Services pré-définis fournis à l'installation */
    public static function get_default_upsells() {
        return [
            ['name' => 'Petit-déjeuner',         'description' => 'Petit-déjeuner continental servi chaque matin',  'price' => 25.00,  'pricing_type' => 'per_person_per_night', 'icon' => 'dashicons-coffee',        'sort_order' => 1],
            ['name' => 'Navette aéroport',        'description' => 'Transfert aller-retour depuis/vers l\'aéroport', 'price' => 60.00,  'pricing_type' => 'fixed',               'icon' => 'dashicons-airplane',      'sort_order' => 2],
            ['name' => 'Late check-out',          'description' => 'Départ tardif jusqu\'à 16h00',                   'price' => 50.00,  'pricing_type' => 'fixed',               'icon' => 'dashicons-clock',         'sort_order' => 3],
            ['name' => 'Early check-in',          'description' => 'Arrivée dès 10h00 selon disponibilité',          'price' => 50.00,  'pricing_type' => 'fixed',               'icon' => 'dashicons-migrate',       'sort_order' => 4],
            ['name' => 'Décoration romantique',   'description' => 'Décoration florale, bougies, pétales de roses',  'price' => 80.00,  'pricing_type' => 'fixed',               'icon' => 'dashicons-heart',         'sort_order' => 5],
            ['name' => 'Piscine chauffée',        'description' => 'Chauffage de la piscine pendant tout le séjour', 'price' => 30.00,  'pricing_type' => 'per_night',           'icon' => 'dashicons-palmtree',      'sort_order' => 6],
            ['name' => 'Pack romantique',         'description' => 'Champagne, décoration, dîner aux chandelles',    'price' => 180.00, 'pricing_type' => 'fixed',               'icon' => 'dashicons-star-filled',   'sort_order' => 7],
            ['name' => 'Pack famille',            'description' => 'Lit bébé, chaise haute, jeux enfants',           'price' => 40.00,  'pricing_type' => 'fixed',               'icon' => 'dashicons-groups',        'sort_order' => 8],
            ['name' => 'Transfert privé',         'description' => 'Véhicule avec chauffeur pour vos déplacements',  'price' => 120.00, 'pricing_type' => 'fixed',               'icon' => 'dashicons-car',           'sort_order' => 9],
            ['name' => 'Service ménage quotidien','description' => 'Ménage et renouvellement du linge chaque jour',  'price' => 45.00,  'pricing_type' => 'per_night',           'icon' => 'dashicons-superhero-alt', 'sort_order' => 10],
        ];
    }

    /**
     * Retourne le HTML d'une icône (Dashicons ou fallback générique)
     * Utilisable en PHP dans les partials admin
     */
    public static function render_icon($icon, $extra_class = '') {
        if (empty($icon)) {
            return '<span class="dashicons dashicons-admin-generic ' . esc_attr($extra_class) . '"></span>';
        }
        // Dashicon (commence par "dashicons-")
        if (strpos($icon, 'dashicons-') === 0) {
            return '<span class="dashicons ' . esc_attr($icon) . ' ' . esc_attr($extra_class) . '"></span>';
        }
        // Fallback : texte brut (anciens emojis)
        return '<span class="' . esc_attr($extra_class) . '">' . esc_html($icon) . '</span>';
    }

    /** Labels lisibles pour les types de tarification */
    public static function get_pricing_type_labels() {
        return [
            'fixed'               => 'Fixe (une seule fois)',
            'per_night'           => 'Par nuit',
            'per_person'          => 'Par personne',
            'per_person_per_night' => 'Par personne / par nuit',
        ];
    }

    /**
     * Créer les tables si elles n'existent pas (appelé depuis install/activate)
     */
    public static function create_tables() {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();

        $upsells_table = $wpdb->prefix . 'riwa_upsells';
        $sql1 = "CREATE TABLE $upsells_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            name varchar(100) NOT NULL,
            description text,
            price decimal(10,2) NOT NULL DEFAULT 0.00,
            pricing_type varchar(30) NOT NULL DEFAULT 'fixed',
            icon varchar(20) DEFAULT '',
            is_active tinyint(1) DEFAULT 1,
            sort_order int(5) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset;";

        $booking_upsells_table = $wpdb->prefix . 'riwa_booking_upsells';
        $sql2 = "CREATE TABLE $booking_upsells_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            booking_id mediumint(9) NOT NULL,
            upsell_id mediumint(9) NOT NULL,
            upsell_name varchar(100) NOT NULL,
            quantity int(3) DEFAULT 1,
            unit_price decimal(10,2) NOT NULL DEFAULT 0.00,
            total_price decimal(10,2) NOT NULL DEFAULT 0.00,
            PRIMARY KEY (id),
            KEY booking_id (booking_id)
        ) $charset;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql1);
        dbDelta($sql2);

        // Insérer les upsells par défaut si la table est vide
        if ((int) $wpdb->get_var("SELECT COUNT(*) FROM $upsells_table") === 0) {
            foreach (self::get_default_upsells() as $u) {
                $wpdb->insert($upsells_table, [
                    'name'         => $u['name'],
                    'description'  => $u['description'],
                    'price'        => $u['price'],
                    'pricing_type' => $u['pricing_type'],
                    'icon'         => $u['icon'],
                    'is_active'    => 1,
                    'sort_order'   => $u['sort_order'],
                ], ['%s','%s','%f','%s','%s','%d','%d']);
            }
        }
    }

    /** Récupérer tous les upsells, triés par sort_order */
    public static function get_all() {
        global $wpdb;
        return $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}riwa_upsells ORDER BY sort_order ASC, id ASC"
        );
    }

    /** Récupérer uniquement les upsells actifs */
    public static function get_active() {
        global $wpdb;
        return $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}riwa_upsells WHERE is_active = 1 ORDER BY sort_order ASC, id ASC"
        );
    }

    /** Récupérer les upsells sélectionnés pour une réservation */
    public static function get_for_booking($booking_id) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}riwa_booking_upsells WHERE booking_id = %d ORDER BY id ASC",
            (int) $booking_id
        ));
    }

    /** Calculer le prix d'un upsell selon son type, ses quantités et le séjour */
    public static function calculate_price($upsell, $nights, $guests, $quantity = 1) {
        $base = (float) $upsell->price;
        switch ($upsell->pricing_type) {
            case 'per_night':
                return $base * $nights * $quantity;
            case 'per_person':
                return $base * $guests * $quantity;
            case 'per_person_per_night':
                return $base * $guests * $nights * $quantity;
            case 'fixed':
            default:
                return $base * $quantity;
        }
    }

    /** Calculer le prix total de tous les upsells sélectionnés */
    public static function calculate_upsells_total($booking_id) {
        global $wpdb;
        $total = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(total_price) FROM {$wpdb->prefix}riwa_booking_upsells WHERE booking_id = %d",
            (int) $booking_id
        ));
        return (float) ($total ?? 0);
    }

    /**
     * Sauvegarder les upsells pour une réservation
     * $selected = [ ['id' => int, 'quantity' => int], ... ]
     */
    public static function save_for_booking($booking_id, $selected, $nights, $guests) {
        global $wpdb;
        $table = $wpdb->prefix . 'riwa_booking_upsells';

        // Supprimer les anciens upsells pour cette réservation
        $wpdb->delete($table, ['booking_id' => (int) $booking_id], ['%d']);

        if (empty($selected)) {
            return 0.0;
        }

        $total = 0.0;
        foreach ($selected as $item) {
            $upsell_id = (int) ($item['id'] ?? 0);
            $quantity  = max(1, (int) ($item['quantity'] ?? 1));
            if (!$upsell_id) continue;

            $upsell = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}riwa_upsells WHERE id = %d AND is_active = 1",
                $upsell_id
            ));
            if (!$upsell) continue;

            $unit_price  = (float) $upsell->price;
            $item_total  = self::calculate_price($upsell, $nights, $guests, $quantity);

            $wpdb->insert($table, [
                'booking_id'  => $booking_id,
                'upsell_id'   => $upsell_id,
                'upsell_name' => $upsell->name,
                'quantity'    => $quantity,
                'unit_price'  => $unit_price,
                'total_price' => $item_total,
            ], ['%d','%d','%s','%d','%f','%f']);

            $total += $item_total;
        }

        return $total;
    }

    /**
     * Traiter les actions POST admin (add, edit, delete, toggle)
     */
    public static function handle_post_actions() {
        if (!isset($_POST['riwa_upsell_action'])) {
            return;
        }
        if (!current_user_can('manage_options')) {
            return;
        }
        if (!wp_verify_nonce($_POST['riwa_upsell_nonce'] ?? '', 'riwa_upsell_action')) {
            echo '<div class="notice notice-error"><p>Action non autorisée.</p></div>';
            return;
        }

        $action = sanitize_text_field($_POST['riwa_upsell_action']);
        $table  = $GLOBALS['wpdb']->prefix . 'riwa_upsells';
        global $wpdb;

        if ($action === 'add' || $action === 'edit') {
            $data = [
                'name'         => sanitize_text_field($_POST['upsell_name'] ?? ''),
                'description'  => sanitize_textarea_field($_POST['upsell_description'] ?? ''),
                'price'        => (float) ($_POST['upsell_price'] ?? 0),
                'pricing_type' => sanitize_key($_POST['upsell_pricing_type'] ?? 'fixed'),
                'icon'         => sanitize_text_field($_POST['upsell_icon'] ?? ''),
                'is_active'    => isset($_POST['upsell_is_active']) ? 1 : 0,
            ];

            if (empty($data['name'])) {
                echo '<div class="notice notice-error"><p>Le nom est obligatoire.</p></div>';
                return;
            }

            $allowed_types = array_keys(self::get_pricing_type_labels());
            if (!in_array($data['pricing_type'], $allowed_types, true)) {
                $data['pricing_type'] = 'fixed';
            }

            if ($action === 'add') {
                $max_order = (int) $wpdb->get_var("SELECT MAX(sort_order) FROM $table");
                $data['sort_order'] = $max_order + 1;
                $wpdb->insert($table, $data, ['%s','%s','%f','%s','%s','%d','%d']);
                echo '<div class="notice notice-success"><p>Service ajouté avec succès !</p></div>';
            } else {
                $id = intval($_POST['upsell_id'] ?? 0);
                $wpdb->update($table, $data, ['id' => $id], ['%s','%s','%f','%s','%s','%d'], ['%d']);
                echo '<div class="notice notice-success"><p>Service modifié avec succès !</p></div>';
            }
        }

        if ($action === 'delete') {
            $id = intval($_POST['upsell_id'] ?? 0);
            $wpdb->delete($table, ['id' => $id], ['%d']);
            echo '<div class="notice notice-success"><p>Service supprimé.</p></div>';
        }

        if ($action === 'toggle') {
            $id         = intval($_POST['upsell_id'] ?? 0);
            $current    = (int) $wpdb->get_var($wpdb->prepare("SELECT is_active FROM $table WHERE id = %d", $id));
            $new_active = $current ? 0 : 1;
            $wpdb->update($table, ['is_active' => $new_active], ['id' => $id], ['%d'], ['%d']);
        }

        if ($action === 'reorder') {
            $order = array_map('intval', (array) ($_POST['upsell_order'] ?? []));
            foreach ($order as $pos => $id) {
                $wpdb->update($table, ['sort_order' => $pos + 1], ['id' => $id], ['%d'], ['%d']);
            }
        }
    }
}
