<?php
/**
 * Contains the WorkflowActionFactory class.
 *
 * @package WDK
 */


namespace WDK\Workflow;

/**
 * Creates instances for the Workflow Action Factory component.
 */
class WorkflowActionFactory {
    public static function create($type, $parameters) {
        $className = "\\WDK\\Workflow\\Actions\\" . ucfirst($type) . "Action";
        if (class_exists($className)) {
            return new $className($parameters);
        }
        // Optionally, allow client app to provide custom actions
        elseif (class_exists($className = "\\YourApp\\Workflow\\Actions\\" . ucfirst($type) . "Action")) {
            return new $className($parameters);
        }
        throw new \Exception("Action class $className not found.");
    }
}