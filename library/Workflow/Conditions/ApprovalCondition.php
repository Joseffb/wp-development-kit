<?php
/**
 * Contains the ApprovalCondition class.
 *
 * @package WDK
 */


namespace WDK\Workflow\Conditions;

/**
 * Provides the Approval Condition component.
 */
class ApprovalCondition extends Condition {
    public function evaluate(array $context): bool
    {
        return isset($context['approved']) && $context['approved'] === true;
    }
}