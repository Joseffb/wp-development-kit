<?php
/**
 * Contains the WorkflowAction class.
 *
 * @package WDK
 */


namespace WDK\Workflow\Actions;

/**
 * Provides the base implementation for Workflow Action.
 */
abstract class WorkflowAction {
    protected $parameters;

    public function __construct($parameters = []) {
        $this->parameters = $parameters;
    }

    /**
     * Execute the action.
     *
     * @param array $context Contextual data passed through the workflow.
     */
    abstract public function execute(&$context);
}