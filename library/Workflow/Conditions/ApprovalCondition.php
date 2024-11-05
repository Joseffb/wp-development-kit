<?php

namespace WDK\Workflow\Conditions;

class ApprovalCondition extends Condition {
    public function evaluate($context) {
        return isset($context['approved']) && $context['approved'] === true;
    }
}