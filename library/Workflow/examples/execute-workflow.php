<?php
use WDK\Workflow\WorkflowEngine;
use YourApp\Workflow\Definitions\WorkflowDefinition;

// Register the custom action with the factory. Add conditions same way.
add_filter('wdk_workflow_action_class', static function($className, $type, $parameters) {
    if ($type === 'custom') {
        return '\\YourApp\\Workflow\\Actions\\CustomAction';
    }
    return $className;
}, 10, 3);

// Load the workflow definition (from database or file)
$workflowJson = get_option('my_workflow_definition'); // Or read from a file
$workflowDefinition = WorkflowDefinition::fromJson($workflowJson);

// Contextual data (e.g., form data)
$context = [
    'form_data' => $_POST,
    'status' => 'Submitted',
];

// Create and execute the workflow
$engine = new WorkflowEngine($workflowDefinition, $context);
$engine->execute();

// After execution, you can use $context['status'] or other updated context data
