<?php
/**
 * Gestion de l'installation de TCPDF
 */

if (!defined('ABSPATH')) {
    exit;
}

class Riwa_Installer {

    /**
     * Installation automatique de TCPDF lors de l'activation du plugin
     */
    public static function install_tcpdf() {
        if (class_exists('TCPDF')) {
            return;
        }

        $config_file = RIWA_BOOKING_PLUGIN_PATH . 'includes/tcpdf-config.php';
        if (file_exists($config_file)) {
            return;
        }

        if (!extension_loaded('zip') || !extension_loaded('curl')) {
            return;
        }

        try {
            $tcpdf_dir = RIWA_BOOKING_PLUGIN_PATH . 'includes/tcpdf/';
            if (!file_exists($tcpdf_dir)) {
                if (!mkdir($tcpdf_dir, 0755, true)) {
                    return;
                }
            }

            $tcpdf_url = 'https://github.com/tecnickcom/TCPDF/archive/refs/tags/6.6.5.zip';
            $zip_file  = $tcpdf_dir . 'tcpdf.zip';

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $tcpdf_url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 60);
            $zip_content = curl_exec($ch);
            $http_code   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($http_code !== 200 || empty($zip_content)) {
                return;
            }

            if (!file_put_contents($zip_file, $zip_content)) {
                return;
            }

            $zip = new ZipArchive();
            if ($zip->open($zip_file) !== true) {
                return;
            }
            $zip->extractTo($tcpdf_dir);
            $zip->close();

            $extracted_dir = null;
            foreach (scandir($tcpdf_dir) as $file) {
                if ($file !== '.' && $file !== '..' && is_dir($tcpdf_dir . $file) && strpos($file, 'TCPDF') !== false) {
                    $extracted_dir = $tcpdf_dir . $file;
                    break;
                }
            }

            if (!$extracted_dir) {
                return;
            }

            $final_dir = $tcpdf_dir . 'tcpdf/';
            if (file_exists($final_dir)) {
                self::delete_directory($final_dir);
            }

            if (!rename($extracted_dir, $final_dir)) {
                return;
            }

            unlink($zip_file);

            $config_content = self::get_tcpdf_config_content();
            if (file_put_contents($config_file, $config_content)) {
                require_once $config_file;
            }

        } catch (Exception $e) {
            // Erreur silencieuse lors de l'installation
        }
    }

    /**
     * Réinstaller TCPDF via AJAX (admin uniquement)
     */
    public static function reinstall_tcpdf() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permissions insuffisantes');
            return;
        }

        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'riwa_booking_nonce')) {
            wp_send_json_error('Erreur de sécurité');
            return;
        }

        $tcpdf_dir   = RIWA_BOOKING_PLUGIN_PATH . 'includes/tcpdf/';
        $config_file = RIWA_BOOKING_PLUGIN_PATH . 'includes/tcpdf-config.php';

        if (file_exists($tcpdf_dir)) {
            self::delete_directory($tcpdf_dir);
        }

        if (file_exists($config_file)) {
            unlink($config_file);
        }

        self::install_tcpdf();

        if (class_exists('TCPDF')) {
            wp_send_json_success('TCPDF réinstallé avec succès');
        } else {
            wp_send_json_error('Échec de la réinstallation de TCPDF');
        }
    }

    /**
     * Supprimer un dossier et son contenu récursivement
     */
    public static function delete_directory($dir) {
        if (!file_exists($dir)) {
            return true;
        }

        if (!is_dir($dir)) {
            return unlink($dir);
        }

        foreach (scandir($dir) as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            if (!self::delete_directory($dir . DIRECTORY_SEPARATOR . $item)) {
                return false;
            }
        }

        return rmdir($dir);
    }

    /**
     * Contenu du fichier de configuration TCPDF
     */
    private static function get_tcpdf_config_content() {
        return '<?php
/**
 * Configuration TCPDF pour Riwa Booking
 * Généré automatiquement lors de l\'activation du plugin
 */

if (!defined(\'ABSPATH\')) {
    exit;
}

define(\'K_TCPDF_EXTERNAL_CONFIG\', true);
define(\'K_PATH_MAIN\', __DIR__ . \'/tcpdf/\');
define(\'K_PATH_URL\', __DIR__ . \'/tcpdf/\');
define(\'K_PATH_FONTS\', K_PATH_MAIN . \'fonts/\');
define(\'K_PATH_CACHE\', K_PATH_MAIN . \'cache/\');
define(\'K_PATH_URL_CACHE\', K_PATH_URL . \'cache/\');
define(\'K_PATH_IMAGES\', K_PATH_MAIN . \'images/\');
define(\'K_BLANK_IMAGE\', K_PATH_IMAGES . \'_blank.png\');
define(\'PDF_PAGE_FORMAT\', \'A4\');
define(\'PDF_PAGE_ORIENTATION\', \'P\');
define(\'PDF_CREATOR\', \'Riwa Booking\');
define(\'PDF_AUTHOR\', \'Riwa Villa\');
define(\'PDF_UNIT\', \'mm\');
define(\'PDF_MARGIN_HEADER\', 5);
define(\'PDF_MARGIN_FOOTER\', 10);
define(\'PDF_MARGIN_TOP\', 27);
define(\'PDF_MARGIN_BOTTOM\', 25);
define(\'PDF_MARGIN_LEFT\', 15);
define(\'PDF_MARGIN_RIGHT\', 15);
define(\'PDF_FONT_NAME_MAIN\', \'helvetica\');
define(\'PDF_FONT_SIZE_MAIN\', 10);

require_once(K_PATH_MAIN . \'tcpdf.php\');
';
    }
}
