<?php
if (!defined('ABSPATH')) exit;

/**
 * Riwa_Date_Utils — Utilitaires de calcul de dates
 *
 * Centralise tous les calculs de durée et de manipulation de dates
 * répétés dans le plugin (calcul de nuits, génération de plages, etc.)
 */
class Riwa_Date_Utils {

    /**
     * Calcule le nombre de nuits entre deux dates.
     *
     * @param  string $check_in   Date d'arrivée  (format Y-m-d)
     * @param  string $check_out  Date de départ  (format Y-m-d)
     * @return int                Nombre de nuits (0 si dates invalides ou identiques)
     */
    public static function nights(string $check_in, string $check_out): int {
        $start = DateTime::createFromFormat('Y-m-d', $check_in);
        $end   = DateTime::createFromFormat('Y-m-d', $check_out);

        if (!$start || !$end || $end <= $start) {
            return 0;
        }

        return (int) $start->diff($end)->days;
    }

    /**
     * Génère toutes les dates d'une plage (de check_in inclus à check_out exclus).
     * Utile pour désactiver les dates dans Flatpickr ou vérifier les chevauchements.
     *
     * @param  string $start   Date de début (Y-m-d), incluse
     * @param  string $end     Date de fin   (Y-m-d), exclue
     * @param  string $format  Format de sortie des dates (défaut Y-m-d)
     * @return array           Liste des dates
     */
    public static function date_range(string $start, string $end, string $format = 'Y-m-d'): array {
        $current = DateTime::createFromFormat('Y-m-d', $start);
        $end_dt  = DateTime::createFromFormat('Y-m-d', $end);

        if (!$current || !$end_dt || $end_dt <= $current) {
            return [];
        }

        $dates = [];
        while ($current < $end_dt) {
            $dates[] = $current->format($format);
            $current->modify('+1 day');
        }
        return $dates;
    }

    /**
     * Vérifie si une date se situe dans une plage (bornes incluses).
     *
     * @param  string $date         Date à tester   (Y-m-d)
     * @param  string $range_start  Début de plage  (Y-m-d)
     * @param  string $range_end    Fin de plage    (Y-m-d)
     * @return bool
     */
    public static function is_in_range(string $date, string $range_start, string $range_end): bool {
        return $date >= $range_start && $date <= $range_end;
    }

    /**
     * Vérifie si deux périodes se chevauchent.
     * Algorithme : NOT (B finit avant A OU B commence après A)
     *
     * @param  string $start1  Début période 1
     * @param  string $end1    Fin période 1
     * @param  string $start2  Début période 2
     * @param  string $end2    Fin période 2
     * @return bool
     */
    public static function periods_overlap(
        string $start1,
        string $end1,
        string $start2,
        string $end2
    ): bool {
        return !($end1 < $start2 || $start1 > $end2);
    }

    /**
     * Formate une date Y-m-d au format français d/m/Y.
     *
     * @param  string $date  Date au format Y-m-d
     * @return string        Date au format d/m/Y, ou chaîne vide si invalide
     */
    public static function format_fr(string $date): string {
        $dt = DateTime::createFromFormat('Y-m-d', $date);
        return $dt ? $dt->format('d/m/Y') : '';
    }

    /**
     * Valide qu'une chaîne est une date au format Y-m-d valide.
     *
     * @param  string $date
     * @return bool
     */
    public static function is_valid_date(string $date): bool {
        if (empty($date)) return false;
        $dt = DateTime::createFromFormat('Y-m-d', $date);
        return $dt && $dt->format('Y-m-d') === $date;
    }

    /**
     * Retourne la date d'aujourd'hui au format Y-m-d.
     */
    public static function today(): string {
        return date('Y-m-d');
    }

    /**
     * Retourne une date dans N jours au format Y-m-d.
     *
     * @param  int    $days  Nombre de jours (positif = futur, négatif = passé)
     * @return string
     */
    public static function in_days(int $days): string {
        return date('Y-m-d', strtotime("{$days} days"));
    }
}
