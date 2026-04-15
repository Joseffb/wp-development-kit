<?php
/**
 * Contains the StatusChangeAction class.
 *
 * @package WDK
 */


namespace WDK\Workflow\Actions;

/**
 * Provides the Status Change Action component.
 */
class StatusChangeAction extends WorkflowAction {
    public function execute(&$context) {
        $newStatus = $this->parameters['status'];
        // Update the status in the context
        $context['status'] = $newStatus;
    }
}