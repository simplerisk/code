<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

/***********************************************
 * FUNCTION: RESOLVE WORKFLOW VARIABLES        *
 * Replaces {{dot.path}} placeholders in a     *
 * string with values from $context and        *
 * $step_outputs.                              *
 *                                             *
 * Supported paths:                            *
 *   {{field}}              → context['field'] *
 *   {{step.NODE_ID.field}} → step output      *
 ***********************************************/
function resolve_workflow_variables(string $template, array $context, array $step_outputs = []): string
{
    return preg_replace_callback('/\{\{([^}]+)\}\}/', function(array $matches) use ($context, $step_outputs): string {
        $path  = trim($matches[1]);
        $parts = explode('.', $path);

        // Step output reference: {{step.node_id.field.subfield...}}
        if (count($parts) >= 3 && $parts[0] === 'step')
        {
            $node_id       = $parts[1];
            $remaining     = array_slice($parts, 2);
            $value         = $step_outputs[$node_id] ?? null;

            foreach ($remaining as $key)
            {
                if (is_array($value) && isset($value[$key])) {
                    $value = $value[$key];
                } else {
                    return $matches[0]; // keep placeholder unchanged if path not found
                }
            }

            return is_scalar($value) ? (string)$value : json_encode($value);
        }

        // Context reference: supports dot notation
        $value = $context;
        foreach ($parts as $key)
        {
            if (is_array($value) && array_key_exists($key, $value)) {
                $value = $value[$key];
            } else {
                return $matches[0]; // keep placeholder unchanged
            }
        }

        return is_scalar($value) ? (string)$value : json_encode($value);
    }, $template);
}

/***********************************************
 * FUNCTION: RESOLVE NODE INPUTS               *
 * Resolves all {{variable}} references in an  *
 * associative array of raw node inputs.       *
 ***********************************************/
function resolve_node_inputs(array $raw_inputs, array $context, array $step_outputs = []): array
{
    $resolved = [];
    foreach ($raw_inputs as $key => $value)
    {
        if (is_string($value)) {
            $resolved[$key] = resolve_workflow_variables($value, $context, $step_outputs);
        } elseif (is_array($value)) {
            $resolved[$key] = resolve_node_inputs($value, $context, $step_outputs);
        } else {
            $resolved[$key] = $value;
        }
    }
    return $resolved;
}

/***********************************************
 * FUNCTION: EVALUATE WORKFLOW CONDITION       *
 * Evaluates a single condition object against *
 * the context.                                *
 *                                             *
 * $condition = [                              *
 *   'field'    => 'risk.score',               *
 *   'operator' => '>=',                       *
 *   'value'    => 7.0,                        *
 * ]                                           *
 ***********************************************/
function evaluate_workflow_condition(array $condition, array $context, array $step_outputs = []): bool
{
    $field    = $condition['field']    ?? '';
    $operator = $condition['operator'] ?? '=';
    $expected = $condition['value']    ?? null;

    // Resolve the actual value from context (supports dot notation)
    $actual = resolve_workflow_variables("{{" . $field . "}}", $context, $step_outputs);

    // If the placeholder was not substituted, the field doesn't exist
    $field_exists = ($actual !== "{{" . $field . "}}");

    switch ($operator)
    {
        case '=':
            return $field_exists && (string)$actual === (string)$expected;
        case '!=':
            return !$field_exists || (string)$actual !== (string)$expected;
        case '>':
            return $field_exists && (float)$actual > (float)$expected;
        case '>=':
            return $field_exists && (float)$actual >= (float)$expected;
        case '<':
            return $field_exists && (float)$actual < (float)$expected;
        case '<=':
            return $field_exists && (float)$actual <= (float)$expected;
        case 'in':
            $list = is_array($expected) ? $expected : explode(',', (string)$expected);
            return $field_exists && in_array($actual, array_map('trim', $list));
        case 'not_in':
            $list = is_array($expected) ? $expected : explode(',', (string)$expected);
            return !$field_exists || !in_array($actual, array_map('trim', $list));
        case 'contains':
            return $field_exists && str_contains((string)$actual, (string)$expected);
        case 'is_empty':
            return !$field_exists || $actual === '' || $actual === null;
        case 'is_not_empty':
            return $field_exists && $actual !== '' && $actual !== null;
        default:
            write_debug_log("WORKFLOW: Unknown condition operator '{$operator}'", 'error');
            return false;
    }
}
