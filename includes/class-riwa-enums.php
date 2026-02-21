<?php
if (!defined('ABSPATH')) exit;

/**
 * Riwa_Enums — Constantes métier centralisées
 *
 * Source unique de vérité pour tous les statuts, labels et valeurs
 * répétées dans le plugin. Évite les magic strings dispersées.
 */
class Riwa_Enums {

    /* ----------------------------------------------------------------
     * Statuts de réservation
     * ---------------------------------------------------------------- */

    const BOOKING_PENDING   = 'pending';
    const BOOKING_CONFIRMED = 'confirmed';
    const BOOKING_CANCELLED = 'cancelled';

    const BOOKING_STATUSES = [
        self::BOOKING_PENDING,
        self::BOOKING_CONFIRMED,
        self::BOOKING_CANCELLED,
    ];

    const BOOKING_STATUS_LABELS = [
        self::BOOKING_PENDING   => 'En attente',
        self::BOOKING_CONFIRMED => 'Confirmée',
        self::BOOKING_CANCELLED => 'Annulée',
    ];

    /* ----------------------------------------------------------------
     * Statuts de ménage (housekeeping)
     * ---------------------------------------------------------------- */

    const HK_PENDING     = 'pending';
    const HK_IN_PROGRESS = 'in_progress';
    const HK_READY       = 'ready';

    const HK_STATUSES = [
        self::HK_PENDING,
        self::HK_IN_PROGRESS,
        self::HK_READY,
    ];

    const HK_STATUS_LABELS = [
        self::HK_PENDING     => 'En attente',
        self::HK_IN_PROGRESS => 'En cours',
        self::HK_READY       => 'Prêt',
    ];

    /* ----------------------------------------------------------------
     * Statuts de paiement (calculés, jamais stockés en base)
     * ---------------------------------------------------------------- */

    const PAY_PAID         = 'paid';
    const PAY_DEPOSIT_PAID = 'deposit_paid';
    const PAY_PARTIAL      = 'partial';
    const PAY_OVERDUE      = 'overdue';
    const PAY_UNPAID       = 'unpaid';

    const PAY_STATUS_LABELS = [
        self::PAY_PAID         => 'Payé',
        self::PAY_DEPOSIT_PAID => 'Acompte reçu',
        self::PAY_PARTIAL      => 'Partiel',
        self::PAY_OVERDUE      => 'En retard',
        self::PAY_UNPAID       => 'Non payé',
    ];

    const PAY_STATUS_COLORS = [
        self::PAY_PAID         => '#22c55e',
        self::PAY_DEPOSIT_PAID => '#f59e0b',
        self::PAY_PARTIAL      => '#3b82f6',
        self::PAY_OVERDUE      => '#ef4444',
        self::PAY_UNPAID       => '#94a3b8',
    ];

    /* ----------------------------------------------------------------
     * Modes de paiement
     * ---------------------------------------------------------------- */

    const METHOD_CASH     = 'cash';
    const METHOD_TRANSFER = 'transfer';
    const METHOD_CARD     = 'card';
    const METHOD_MOBILE   = 'mobile';
    const METHOD_PLATFORM = 'platform';
    const METHOD_OTHER    = 'other';

    const PAYMENT_METHOD_LABELS = [
        self::METHOD_CASH     => 'Espèces',
        self::METHOD_TRANSFER => 'Virement',
        self::METHOD_CARD     => 'Carte bancaire',
        self::METHOD_MOBILE   => 'Paiement mobile',
        self::METHOD_PLATFORM => 'Plateforme (Airbnb…)',
        self::METHOD_OTHER    => 'Autre',
    ];

    /* ----------------------------------------------------------------
     * Canaux de notification
     * ---------------------------------------------------------------- */

    const NOTIF_WHATSAPP = 'whatsapp';
    const NOTIF_EMAIL    = 'email';

    /* ----------------------------------------------------------------
     * Types de notification
     * ---------------------------------------------------------------- */

    const NOTIF_CONFIRMATION = 'confirmation';
    const NOTIF_REMINDER     = 'reminder';
    const NOTIF_CHECKIN      = 'checkin';
    const NOTIF_REVIEW       = 'review';
    const NOTIF_CUSTOM       = 'custom';

    const NOTIF_TYPE_LABELS = [
        self::NOTIF_CONFIRMATION => 'Confirmation',
        self::NOTIF_REMINDER     => 'Rappel',
        self::NOTIF_CHECKIN      => 'Infos arrivée',
        self::NOTIF_REVIEW       => 'Demande avis',
        self::NOTIF_CUSTOM       => 'Personnalisé',
    ];

    /* ----------------------------------------------------------------
     * Types de documents PDF
     * ---------------------------------------------------------------- */

    const DOC_CONFIRMATION = 'confirmation';
    const DOC_FACTURE      = 'facture';
    const DOC_DEVIS        = 'devis';
    const DOC_CONTRAT      = 'contrat';
    const DOC_RAPPORT      = 'rapport';

    const DOC_TYPES = [
        self::DOC_CONFIRMATION,
        self::DOC_FACTURE,
        self::DOC_DEVIS,
        self::DOC_CONTRAT,
        self::DOC_RAPPORT,
    ];

    /* ----------------------------------------------------------------
     * Helpers
     * ---------------------------------------------------------------- */

    /**
     * Retourne le label d'un statut de réservation.
     */
    public static function booking_status_label(string $status): string {
        return self::BOOKING_STATUS_LABELS[$status] ?? $status;
    }

    /**
     * Vérifie si un statut de réservation est valide.
     */
    public static function is_valid_booking_status(string $status): bool {
        return in_array($status, self::BOOKING_STATUSES, true);
    }

    /**
     * Vérifie si un statut de ménage est valide.
     */
    public static function is_valid_hk_status(string $status): bool {
        return in_array($status, self::HK_STATUSES, true);
    }

    /**
     * Vérifie si un mode de paiement est valide.
     */
    public static function is_valid_payment_method(string $method): bool {
        return isset(self::PAYMENT_METHOD_LABELS[$method]);
    }

    /**
     * Retourne le label d'un statut de paiement.
     */
    public static function pay_status_label(string $status): string {
        return self::PAY_STATUS_LABELS[$status] ?? $status;
    }

    /**
     * Retourne la couleur d'un statut de paiement.
     */
    public static function pay_status_color(string $status): string {
        return self::PAY_STATUS_COLORS[$status] ?? '#94a3b8';
    }
}
