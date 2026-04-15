<?php
/**
 * Contains the Condition class.
 *
 * @package WDK
 */


namespace WDK\Workflow\Conditions;

/**
 * Provides the base implementation for Condition.
 */
abstract class Condition {
    protected $parameters;

    public function __construct($parameters = []) {
        $this->parameters = $parameters;
    }

    /**
     * Evaluate the condition.
     *
     * @param array $context Contextual data passed through the workflow.
     * @return bool True if condition is met, false otherwise.
     */
    abstract public function evaluate(array $context): bool;
}
