<?php

namespace WDK\Workflow\Conditions;

class ApprovalCondition extends Condition {
    public function evaluate(array $context): bool
    {
        return isset($context['approved']) && $context['approved'] === true;
    }
}