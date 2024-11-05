<?php
namespace WDK\Workflow;

class WorkflowEngine {
    private $workflowDefinition;
    private $currentNode;
    private $context;

    public function __construct($workflowDefinition, $context = []) {
        $this->workflowDefinition = $workflowDefinition;
        $this->currentNode = $this->workflowDefinition->getStartNode();
        $this->context = $context;
    }

    public function execute() {
        while ($this->currentNode !== null) {
            // Execute actions associated with the current node
            foreach ($this->currentNode->getActions() as $actionData) {
                $action = WorkflowActionFactory::create($actionData['type'], $actionData['parameters']);
                $action->execute($this->context);
            }

            // Determine the next node based on transitions
            $this->currentNode = $this->getNextNode();
        }
    }

    private function getNextNode() {
        foreach ($this->currentNode->getTransitions() as $transition) {
            if ($transition->isTriggered($this->context)) {
                return $transition->getNextNode();
            }
        }
        return null; // End of workflow
    }
}
