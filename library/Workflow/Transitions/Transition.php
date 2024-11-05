<?php

namespace WDK\Workflow\Transitions;

use WDK\Workflow\Conditions\Condition;

class Transition {
    private $condition;
    private $nextNode;

    public function __construct(Condition $condition, $nextNode) {
        $this->condition = $condition;
        $this->nextNode = $nextNode;
    }

    public function isTriggered($context) {
        return $this->condition->evaluate($context);
    }

    public function getNextNode() {
        return $this->nextNode;
    }
}