<?php

namespace WDK\Workflow\Actions;

use WDK\Workflow\Actions\WorkflowAction;

class WorkflowActionFactory {
    /**
     * @throws \RuntimeException
     */
    public static function create($type, $parameters = []) {
        // Attempt to create an action from the WDK namespace
        $wdkClass = "\\WDK\\Workflow\\Actions\\" . ucfirst($type) . "Action";

        if (class_exists($wdkClass)) {
            return new $wdkClass($parameters);
        }

        // Attempt to create an action from the client application's namespace
        $appClass = apply_filters('wdk_workflow_action_class', null, $type, $parameters);

        if ($appClass && class_exists($appClass)) {
            return new $appClass($parameters);
        }

        throw new \RuntimeException("Action class for type '$type' not found.");
    }
}