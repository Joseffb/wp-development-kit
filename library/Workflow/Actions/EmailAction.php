<?php
/**
 * Contains the EmailAction class.
 *
 * @package WDK
 */


namespace WDK\Workflow\Actions;

/**
 * Provides the Email Action component.
 */
class EmailAction extends WorkflowAction {
    public function execute(&$context) {
        $to = $this->parameters['to'];
        $subject = $this->parameters['subject'];
        $message = $this->parameters['message'];

        // Use WordPress's wp_mail function
        wp_mail($to, $subject, $message);
    }
}