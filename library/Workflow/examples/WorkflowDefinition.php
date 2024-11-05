<?php
namespace YourApp\Workflow\Definitions;

use WDK\Workflow\Nodes\Node;
use WDK\Workflow\Transitions\Transition;
use WDK\Workflow\Conditions\ConditionFactory;

class WorkflowDefinition {
    private $nodes = [];
    private $startNode;

    public static function fromJson($json) {
        $data = json_decode($json, true);
        $instance = new self();

        // Create nodes
        foreach ($data['nodes'] as $nodeData) {
            $node = new Node($nodeData['id']);

            // Add actions to node
            foreach ($nodeData['actions'] as $actionData) {
                $node->addAction($actionData);
            }

            // Add transitions to node
            foreach ($nodeData['transitions'] as $transitionData) {
                $condition = ConditionFactory::create(
                    $transitionData['condition']['type'],
                    $transitionData['condition']['parameters']
                );
                $transition = new Transition($condition, $transitionData['nextNode']);
                $node->addTransition($transition);
            }

            $instance->addNode($node);
        }

        $instance->setStartNode($data['startNode']);

        return $instance;
    }

    public function addNode($node) {
        $this->nodes[$node->getId()] = $node;
    }

    public function getNode($id) {
        return $this->nodes[$id] ?? null;
    }

    public function getStartNode() {
        return $this->getNode($this->startNode);
    }

    public function setStartNode($id) {
        $this->startNode = $id;
    }
}
