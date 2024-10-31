<?php
/**
 * Class Prosociate_WPWizardCloak
 *
 * Handles support for WP Wizard Cloak plugin for external / affiliate products
 */
class Prosociate_WPWizardCloak {
    /**
     * Generated cloak link
     * @var string
     */
    public $cloakedLink = '';

    /**
     * @param int $postId
     * @param string $buyUrl
     */
    function __construct($postId, $buyUrl) {
        // Insert link
        $this->addLink($postId);
        // Insert rule
        $this->addRule();
        // Insert destination
        $this->addDestination($buyUrl);
    }

    /**
     * Save a new entry of the link on the database
     * @param int $postId
     */
    private function addLink($postId) {
        global $wpdb;

        // Set data
        $data = array(
            'name' => 'Product ' . $postId,
            'slug' => 'product-' . $postId,
            'header_tracking_code' => '',
            'footer_tracking_code' => ''
        );

        $wpdb->insert($wpdb->prefix . 'pmlc_links', $data, array('%s', '%s'));

        // Set the cloaked link
        $this->cloakedLink = 'product-' . $postId;
    }

    /**
     * Save a new entry of the rule associated with the last added link
     */
    private function addRule() {
        global $wpdb;

        // Get the last inserted link
        $linkId = $wpdb->insert_id;

        // Set data
        $data = array(
            'link_id' => $linkId
        );

        $wpdb->insert($wpdb->prefix . 'pmlc_rules', $data, array('%s'));
    }

    /**
     * Save a new entry of destination
     * @param string $buyUrl
     */
    private function addDestination($buyUrl) {
        global $wpdb;

        // Get id of the last inserted rule
        $ruleId = $wpdb->insert_id;

        // Set data
        $data = array(
            'rule_id' => $ruleId,
            'url' => $buyUrl,
            'weight' => 100
        );

        // Insert
        $wpdb->insert($wpdb->prefix . 'pmlc_destinations', $data, array('%d', '%s', '%d'));
    }
}