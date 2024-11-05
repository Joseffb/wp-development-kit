<?php

namespace WDK\Workflow\Nodes;

use WDK\Workflow\Transitions\Transition;

class Node {
    private $id;
    private $actions = [];
    private $transitions = [];

    public function __construct($id) {
        $this->id = $id;
    }

    public function getId() {
        return $this->id;
    }

    public function addAction($actionData) {
        $this->actions[] = $actionData;
    }

    public function getActions() {
        return $this->actions;
    }

    public function addTransition(Transition $transition) {
        $this->transitions[] = $transition;
    }

    public function getTransitions() {
        return $this->transitions;
    }
}
