<?php

namespace WDK\Workflow\Conditions;

class ConditionFactory {
    public static function create($type, $parameters = []) {
        // Attempt to create a condition from the WDK namespace
        $wdkClass = "\\WDK\\Workflow\\Conditions\\" . ucfirst($type) . "Condition";

        if (class_exists($wdkClass)) {
            return new $wdkClass($parameters);
        }

        // Attempt to create a condition from the client application's namespace
        $appClass = apply_filters('wdk_workflow_condition_class', null, $type, $parameters);

        if ($appClass && class_exists($appClass)) {
            return new $appClass($parameters);
        }

        throw new \RuntimeException("Condition class for type '$type' not found.");
    }
}
