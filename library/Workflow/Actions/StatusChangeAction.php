<?php

namespace WDK\Workflow\Actions;

class StatusChangeAction extends WorkflowAction {
    public function execute(&$context) {
        $newStatus = $this->parameters['status'];
        // Update the status in the context
        $context['status'] = $newStatus;
    }
}