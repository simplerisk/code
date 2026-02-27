<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Include required configuration files
require_once(realpath(__DIR__ . '/../vendor/autoload.php'));
require_once(realpath(__DIR__ . '/functions.php'));
require_once(realpath(__DIR__ . '/display.php'));
require_once(realpath(__DIR__ . '/queues.php'));
require_once(language_file());

class ClaudeAPIClient {
    private $api_key;
    private $url = 'https://api.anthropic.com/v1/messages';
    private $model = 'claude-sonnet-4-20250514';
    private $last_request_time = 0;
    private $rate_limit_delay = 1; // Delay between requests in seconds

    public function __construct($api_key) {
        $this->api_key = $api_key;
    }

    /**
     * Call Claude API with support for documents
     *
     * @param array $messages Array of messages (can include document content blocks)
     * @param int $max_tokens Maximum tokens for response
     * @param string|null $system System prompt
     * @return array API response
     */
    public function callClaudeAPI($messages, $max_tokens = 300, $system = null, $tools = null)
    {
        $baseDelay = 10; // Initial delay in seconds
        $retries = 0;
        $maxRetries = 5;
        $success = false;

        // Process messages to handle both text and document content
        foreach ($messages as &$message) {
            // If content is a string, sanitize it
            if (is_string($message['content'])) {
                $message['content'] = $this->ensureValidUtf8($message['content']);
            }
            // If content is an array (for multi-part content with documents), process text blocks
            elseif (is_array($message['content'])) {
                foreach ($message['content'] as &$content_block) {
                    if ($content_block['type'] === 'text') {
                        $original = $content_block['text'];
                        $content_block['text'] = $this->ensureValidUtf8($content_block['text']);

                        // Debug logging if sanitization changed the content
                        if ($original !== $content_block['text']) {
                            $originalLength = strlen($original);
                            $cleanedLength = strlen($content_block['text']);
                            write_debug_log(
                                "UTF-8 sanitization modified text block: {$originalLength} bytes -> {$cleanedLength} bytes",
                                'debug'
                            );
                        }
                    }
                    // Document blocks don't need UTF-8 conversion as they're base64
                }
                unset($content_block);
            }
        }
        unset($message); // important to avoid variable reference issues

        $data = [
            'model' => $this->model,
            'max_tokens' => $max_tokens,
            // If a system is not specified, default to an expert on GRC
            'system' => is_null($system) ? "You are an expert on Governance, Risk Management and Compliance (GRC) and have been hired by an organization to help them improve their program using SimpleRisk as the GRC tool of choice. You are working with some people who are new to working with GRC." : $system,
            'messages' => $messages
        ];

        // Add tools to the request if provided
        if (!is_null($tools) && is_array($tools) && !empty($tools)) {
            $data['tools'] = $tools;
            write_debug_log('Tools added to API request: ' . json_encode($tools), 'debug');
        }

        // Validate UTF-8 before JSON encoding
        $this->validateUtf8InData($data);

        // Ensure that the json encoding works before sending the data
        $json_data = json_encode($data, JSON_UNESCAPED_UNICODE);

        if ($json_data === false) {
            $error = json_last_error_msg();
            write_debug_log('JSON encode error: ' . $error, 'error');

            // Additional debugging: try to find the problematic content
            $this->debugJsonEncodeFailure($data);

            // Throw exception instead of continuing with invalid data
            throw new Exception("Failed to JSON encode API request: {$error}");
        }

        // Verify the JSON is not empty
        if (trim($json_data) === '' || $json_data === 'null') {
            write_debug_log('JSON encoding resulted in empty/null data', 'error');
            write_debug_log('Original data structure: ' . print_r($this->truncateBase64ForLogging($data), true), 'debug');
            throw new Exception("JSON encoding produced empty result");
        }

        // Log the payload size
        write_debug_log('JSON payload size: ' . strlen($json_data) . ' bytes', 'debug');

        // Only log a truncated version if it contains large base64 data
        $log_data = $this->truncateBase64ForLogging($data);
        write_debug_log('JSON payload being sent: ' . json_encode($log_data), 'debug');

        $ch = curl_init($this->url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json_data);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'x-api-key: ' . $this->api_key,
            'anthropic-version: 2023-06-01'
        ]);

        while (!$success && $retries < $maxRetries) {
            try {
                $response = curl_exec($ch);
                $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);

                if (curl_errno($ch)) {
                    throw new Exception('Curl error: ' . curl_error($ch));
                }

                curl_close($ch);

                if ($http_status !== 200) {
                    write_debug_log("Curl returned an HTTP status of {$http_status}", "debug");

                    // If the HTTP code is 400 a bad request was made
                    if ($http_status === 400) {
                        // Attempt to get the actual error message
                        $data = json_decode($response, true);
                        $message = $data['error']['message'] ?? 'Unknown error';

                        // If we were able to get the error message
                        if (!empty($message)) {
                            // Log the error
                            write_debug_log("Full API Response on 400 error: " . print_r($response, true), "debug");
                            write_debug_log("Anthropic API Error: 400 - {$message}", "error");
                        }
                        // Otherwise, just display a generic message and the whole response
                        else {
                            write_debug_log("Anthropic API Error: 400 - Bad request for Anthropic API", "error");
                            write_debug_log($response);
                        }
                    }
                    // If the HTTP code is 402 a payment is required
                    if ($http_status === 402) {
                        // Display an alert
                        $message = "Payment required: Please add credits to your Anthropic API account.";
                        set_alert(true, "bad", $message);

                        // Log the error
                        write_debug_log("Anthropic API Error: 402 - Payment required for Anthropic API", "error");

                        // Throw an exception
                        throw new Exception('Anthropic API Error: 402 - Payment required for Anthropic API');
                    }
                    // If the HTTP code is 429 a rate limit has been hit
                    else if ($http_status === 429) {
                        // We will try again with a backoff delay so no need to display an alert
                        // Log the error
                        write_debug_log("Anthropic API Error: 429 - Rate limit hit for Anthropic API", "warning");

                        // Throw an exception
                        throw new Exception("Anthropic API Error: 429 - Rate limit hit for Anthropic API");
                    }
                    // If the HTTP code is 529 anthropic is experiencing capacity issues
                    else if ($http_status === 529) {
                        // We will try again with a backoff delay so no need to display an alert
                        // Log the error
                        write_debug_log("Anthropic API Error: 529 - The Anthropic API is overloaded");

                        // Throw an exception
                        throw new Exception("Anthropic API Error: 529 - The Anthropic API is overloaded");
                    }
                    // If it's not an expected HTTP status code
                    else {
                        // Throw an exception
                        throw new Exception("API error: {$http_status}");
                    }
                }
                // The request was successful
                else {
                    $result = json_decode($response, true);
                    $success = true;
                }
            } catch (Exception $e) {
                // If the error message is "429 Rate Limit Exceeded" or "529 Overloaded"
                if ($http_status === 429 || $http_status === 529) {
                    // Exponential backoff
                    $delay = $baseDelay * (2 ** $retries);
                    write_debug_log("Waiting {$delay} seconds before retry.", "info");
                    sleep($delay);
                    $retries++;
                } else {
                    throw $e;
                }
            }
        }

        return $result;
    }

    /**
     * Ensure text is valid UTF-8, using multiple fallback strategies
     *
     * @param string $text Text that may contain malformed UTF-8
     * @return string Valid UTF-8 text
     */
    private function ensureValidUtf8($text)
    {
        if (!is_string($text)) {
            return '';
        }

        if ($text === '') {
            return '';
        }

        // Check if already valid UTF-8
        if (mb_check_encoding($text, 'UTF-8')) {
            return $text;
        }

        write_debug_log('Detected invalid UTF-8, attempting to sanitize', 'warning');

        // Strategy 1: Try common encodings
        $encodings = ['UTF-8', 'ISO-8859-1', 'Windows-1252', 'CP1252'];
        foreach ($encodings as $encoding) {
            $converted = @mb_convert_encoding($text, 'UTF-8', $encoding);
            if ($converted !== false && mb_check_encoding($converted, 'UTF-8')) {
                write_debug_log("Successfully converted from {$encoding} to UTF-8", 'debug');
                return $converted;
            }
        }

        // Strategy 2: Use iconv with IGNORE to strip invalid sequences
        $cleaned = @iconv('UTF-8', 'UTF-8//IGNORE', $text);
        if ($cleaned !== false && mb_check_encoding($cleaned, 'UTF-8')) {
            write_debug_log('Used iconv //IGNORE to sanitize UTF-8', 'debug');
            return $cleaned;
        }

        // Strategy 3: Use iconv with TRANSLIT to transliterate problematic characters
        $cleaned = @iconv('UTF-8', 'UTF-8//TRANSLIT//IGNORE', $text);
        if ($cleaned !== false && mb_check_encoding($cleaned, 'UTF-8')) {
            write_debug_log('Used iconv //TRANSLIT//IGNORE to sanitize UTF-8', 'debug');
            return $cleaned;
        }

        // Strategy 4: Remove control characters and non-printable bytes
        $cleaned = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x80-\x9F]/u', '', $text);
        if ($cleaned !== null && $cleaned !== false && mb_check_encoding($cleaned, 'UTF-8')) {
            write_debug_log('Used regex to remove control characters', 'debug');
            return $cleaned;
        }

        // Strategy 5: Keep only ASCII-safe characters (most aggressive)
        write_debug_log('Falling back to ASCII-only sanitization', 'warning');
        $cleaned = preg_replace('/[^\x20-\x7E\x0A\x0D\t]/u', '', $text);

        if ($cleaned !== null && $cleaned !== false) {
            return $cleaned;
        }

        // Last resort: return empty string
        write_debug_log('All UTF-8 sanitization strategies failed, returning empty string', 'error');
        return '';
    }

    /**
     * Validate that all text content in the data structure is valid UTF-8
     *
     * @param array $data Data structure to validate
     * @return void
     */
    private function validateUtf8InData($data)
    {
        if (isset($data['messages']) && is_array($data['messages'])) {
            foreach ($data['messages'] as $index => $message) {
                if (is_string($message['content'])) {
                    if (!mb_check_encoding($message['content'], 'UTF-8')) {
                        write_debug_log("Message {$index} content has invalid UTF-8", 'error');
                    }
                } elseif (is_array($message['content'])) {
                    foreach ($message['content'] as $blockIndex => $block) {
                        if ($block['type'] === 'text' && isset($block['text'])) {
                            if (!mb_check_encoding($block['text'], 'UTF-8')) {
                                write_debug_log(
                                    "Message {$index}, block {$blockIndex} has invalid UTF-8",
                                    'error'
                                );
                                // Log a sample of the problematic text
                                $sample = substr($block['text'], 0, 100);
                                write_debug_log("Sample text: " . bin2hex($sample), 'debug');
                            }
                        }
                    }
                }
            }
        }
    }

    /**
     * Debug helper to identify what's causing JSON encoding to fail
     *
     * @param array $data Data that failed to encode
     * @return void
     */
    private function debugJsonEncodeFailure($data)
    {
        write_debug_log('Debugging JSON encode failure...', 'debug');

        // Try encoding just the structure without content
        $structure = [
            'model' => $data['model'] ?? 'unknown',
            'max_tokens' => $data['max_tokens'] ?? 0,
            'message_count' => count($data['messages'] ?? [])
        ];

        if (json_encode($structure) !== false) {
            write_debug_log('Basic structure encodes successfully', 'debug');

            // Test each message individually
            if (isset($data['messages']) && is_array($data['messages'])) {
                write_debug_log('Testing ' . count($data['messages']) . ' messages...', 'debug');

                foreach ($data['messages'] as $index => $message) {
                    // Test role
                    $test = json_encode(['role' => $message['role'] ?? 'unknown']);
                    if ($test === false) {
                        write_debug_log("Message {$index} role fails to encode", 'error');
                    } else {
                        write_debug_log("Message {$index} role: OK", 'debug');
                    }

                    // Test content
                    if (is_string($message['content'])) {
                        write_debug_log("Message {$index} has string content, length: " . strlen($message['content']), 'debug');
                        $test = json_encode(['content' => $message['content']]);
                        if ($test === false) {
                            write_debug_log("Message {$index} content (string) FAILS to encode", 'error');

                            // Check UTF-8 validity
                            if (!mb_check_encoding($message['content'], 'UTF-8')) {
                                write_debug_log("Message {$index} content is NOT valid UTF-8", 'error');
                                // Show hex dump of first 100 bytes
                                $hex = bin2hex(substr($message['content'], 0, 100));
                                write_debug_log("First 100 bytes (hex): {$hex}", 'debug');
                            } else {
                                write_debug_log("Message {$index} content IS valid UTF-8 but still fails JSON encode", 'error');
                            }
                        } else {
                            write_debug_log("Message {$index} string content: OK", 'debug');
                        }
                    } elseif (is_array($message['content'])) {
                        write_debug_log("Message {$index} has array content with " . count($message['content']) . " blocks", 'debug');

                        foreach ($message['content'] as $blockIndex => $block) {
                            $blockType = $block['type'] ?? 'unknown';
                            write_debug_log("Message {$index}, block {$blockIndex}: type={$blockType}", 'debug');

                            $test = json_encode($block);
                            if ($test === false) {
                                write_debug_log(
                                    "Message {$index}, block {$blockIndex} (type: {$blockType}) FAILS to encode",
                                    'error'
                                );

                                // If it's a text block, check the text
                                if ($blockType === 'text' && isset($block['text'])) {
                                    write_debug_log("Text block length: " . strlen($block['text']), 'debug');

                                    if (!mb_check_encoding($block['text'], 'UTF-8')) {
                                        write_debug_log("Text block is NOT valid UTF-8", 'error');
                                        // Show hex dump of first 100 bytes
                                        $hex = bin2hex(substr($block['text'], 0, 100));
                                        write_debug_log("First 100 bytes (hex): {$hex}", 'debug');
                                    } else {
                                        write_debug_log("Text block IS valid UTF-8 but still fails JSON encode", 'error');
                                    }
                                }
                            } else {
                                write_debug_log("Message {$index}, block {$blockIndex}: OK", 'debug');
                            }
                        }
                    } else {
                        write_debug_log("Message {$index} has unexpected content type: " . gettype($message['content']), 'error');
                    }
                }
            } else {
                write_debug_log('No messages array found in data', 'error');
            }
        } else {
            write_debug_log('Even basic structure fails to encode', 'error');
        }
    }

    /**
     * Truncate base64 data in messages for logging purposes
     */
    private function truncateBase64ForLogging($data) {
        $log_data = $data;

        if (isset($log_data['messages'])) {
            foreach ($log_data['messages'] as &$message) {
                if (is_array($message['content'])) {
                    foreach ($message['content'] as &$content_block) {
                        if ($content_block['type'] === 'document' && isset($content_block['source']['data'])) {
                            $original_length = strlen($content_block['source']['data']);
                            $content_block['source']['data'] = substr($content_block['source']['data'], 0, 50) . '... [truncated, original length: ' . $original_length . ' bytes]';
                        }
                    }
                    unset($content_block);
                }
            }
            unset($message);
        }

        return $log_data;
    }

    private function rateLimit() {
        $current_time = microtime(true);
        $time_since_last_request = $current_time - $this->last_request_time;

        if ($time_since_last_request < $this->rate_limit_delay) {
            usleep(($this->rate_limit_delay - $time_since_last_request) * 1000000);
        }

        $this->last_request_time = microtime(true);
    }

    public function test() {
        try {
            // Set a basic prompt
            $prompt = "Hello, Claude";

            $messages[] = [
                "role" => "user",
                "content" => "Hello, Claude"
            ];

            // Create the Claude client and call the API
            $result = $this->callClaudeAPI($messages);

            if (isset($result['content'][0]['text'])) {
                return true;
            } else {
                return false;
            }
        } catch (Exception $e) {
            return false;
        }
    }
}

/*********************************************
 * FUNCTION: DISPLAY ANTHROPIC API KEY INPUT *
 *********************************************/
function display_anthropic_api_key_input()
{
    global $lang, $escaper;

    // Create an empty display key
    $display_key = "";

    // Create the start of the row
    echo "
    <div class='row'>";

    // If the External tab was submitted
    if (isset($_POST['update_anthropic_api_key']))
    {
        // If an Anthropic API key was sent
        if (isset($_POST['anthropic_api_key']))
        {
            // Set this to the new display key
            $display_key = $_POST['anthropic_api_key'];

            // Test connectivity with the API key
            $client = new ClaudeAPIClient($_POST['anthropic_api_key']);
            $connected = $client->test();

            // If the connection was successful
            if ($connected)
            {
                // Update the Anthropic API key with that value
                update_setting("anthropic_api_key", $_POST['anthropic_api_key']);
            }
            // If the connection was not successful
            else
            {
                echo "
                <div class='col'>
                    <div class='alert alert-danger mb-0'>
                        " . $escaper->escapeHtml($lang['AnthropicConnectionWarning']) . "
                    </div>
                </div>";

                // End the row and start a new one
                echo "</div><div class='row'>&nbsp</div><div class='row'>";
            }
        }
    }

    // If the reset Anthropic API key was submitted
    if (isset($_POST['reset_anthropic_api_key']))
    {
        // Delete the Anthropic API key
        delete_setting("anthropic_api_key");
    }

    // Check to see if we have an anthropic API key already
    $anthropic_api_key = get_setting('anthropic_api_key', false, false);

    // If the anthropic API key doesn't exist
    if (!$anthropic_api_key)
    {
        // Display the input for the key
        echo "
            <div class='col'>
                <input name='anthropic_api_key' id='anthropic_api_key' class='form-control' value='" . $escaper->escapeHtml($display_key) . "'/>
            </div>
            <div class='col'>
                <button type='submit' name='update_anthropic_api_key' class='btn btn-submit'>" . $escaper->escapeHtml($lang['Submit']) . "</button>
            </div>";
    }
    // If the anthropic API key exists
    else
    {
        // Test connectivity with the API key
        $client = new ClaudeAPIClient($anthropic_api_key);
        $connected = $client->test();

        // If we are connected
        if ($connected)
        {
            echo "
                <div class='col'>
                    <div class='alert alert-success mb-0'>
                        " . $escaper->escapeHtml($lang['AnthropicConnectionSuccess']) . "
                    </div>
                </div>
                <div class='col'>
                    <button type='submit' name='reset_anthropic_api_key' class='btn btn-submit'>" . $escaper->escapeHtml($lang['ResetAPIKey']) . "</button>
                </div>";
        }
        // If we are not connected
        else
        {
            echo "
                <div class='col'>
                    <div class='alert alert-danger mb-0'>
                        " . $escaper->escapeHtml($lang['AnthropicConnectionWarning']) . "
                    </div>
                </div>
                <div class='col'>
                    <button type='submit' name='reset_anthropic_api_key' class='btn btn-submit'>" . $escaper->escapeHtml($lang['ResetAPIKey']) . "</button>
                </div> ";
        }
    }

    // Create the end of the row
    echo "
    </div>";
}

/*********************************************************
 * FUNCTION: DISPLAY ARTIFICIAL INTELLIGENCE ADD CONTEXT *
 *********************************************************/
function display_artificial_intelligence_add_context($parameter_array = [])
{
    global $lang, $escaper;

    echo "
            <form name='ai_add_context' method='post' action=''>
            <div class='card-body my-2 border'>
                <div class='row'>
                    <div class='d-flex justify-content-between align-items-center'>
                    <h3>" . $escaper->escapeHtml($lang['ArtificialIntelligenceAdditionalContext']) . "</h3>
                    <button class='btn btn-submit' type='submit' name='save_ai_context' style='float: right;'>" . $escaper->escapeHtml($lang['Save']) . "</button>
                    </div>
                </div>
                <div class='row'>&nbsp;</div>
                <div class='row'>
                    <div class='col'>
                        <p>" . $escaper->escapeHtml($lang['ArtificialIntelligenceAdditionalContextDescription']) . "</p>
                    </div>
                </div>
                <div class='accordion mt-2'>
                    <div class='well accordion-item'>
                        <h2 class='accordion-header'>
                            <button class='accordion-button collapsed' type='button' data-bs-toggle='collapse' data-bs-target='#organization_context'>" . $escaper->escapehtml($lang['OrganizationContext']) . "</button>
                        </h2>
                        <div id='organization_context' class='accordion-collapse collapse'>
                            <div class='accordion-body'>
                                <div class='form-group col-8'>
                                    <label for='org_name'>What is the name of your organization?</label>
                                    <input name='org_name' type='text' class='form-control' value='" . $escaper->escapeHtml($parameter_array['org_name']['value']). "' />
                                </div>
                                <div class='form-group col-8'>
                                    <label for='org_size_employees'>How many employees does your organization have?</label>
                                    <input name='org_size_employees' type='text' class='form-control' value='" . $escaper->escapeHtml($parameter_array['org_size_employees']['value']). "' />
                                </div>
                                <div class='form-group col-8'>
                                    <label for='org_size_revenue'>What is the annual revenue of your organization?</label>
                                    <input name='org_size_revenue' type='text' class='form-control' value='" . $escaper->escapeHtml($parameter_array['org_size_revenue']['value']). "' />
                                </div>
                                <div class='form-group col-8'>
                                    <label for='org_objective'>What are your organization's primary business objectives and strategic goals?</label>";

    // Get the list of org objectives
    $org_objectives = get_org_objectives_array();

    // Display the multi-select dropdown of org objectives
    $selected_array = (isset($parameter_array['org_objective']['value']) ? $parameter_array['org_objective']['value'] : []);
    display_generic_multiselect('org_objective', $org_objectives, $selected_array);

    echo "
                                </div>
                                <div class='form-group col-8'>
                                    <label for='org_industry'>What is the primary industry or sector of your organization?</label>";

    // Get the list of org industries
    $org_industries = get_org_industry_array();

    // Display the dropdown of org industries
    $selected_value = (isset($parameter_array['org_industry']['value']) ? $parameter_array['org_industry']['value'] : "");
    display_generic_dropdown('org_industry', $org_industries, $selected_value);

    echo "
                                </div>
                                <div class='form-group col-8'>
                                    <label for='org_location'>In which countries or regions does your organization operate?</label>";

    // Get an updated list of countries
    $countries = fetchCountries();

    // Display a multi-select of the countries
    $selected_array = (isset($parameter_array['org_location']['value']) ? $parameter_array['org_location']['value'] : []);
    display_generic_multiselect('org_location', $countries, $selected_array);

    echo "
                                </div>
                                <div class='form-group col-8'>
                                    <label for='org_type'>What type of organization are you?</label>";

    // Get the list of org types
    $org_types = get_org_type_array();

    // Display the dropdown of org industries
    $selected_value = (isset($parameter_array['org_type']['value']) ? $parameter_array['org_type']['value'] : "");
    display_generic_dropdown('org_type', $org_types, $selected_value);

    echo "
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class='well accordion-item'>
                        <h2 class='accordion-header'>
                            <button class='accordion-button collapsed' type='button' data-bs-toggle='collapse' data-bs-target='#data_context'>" . $escaper->escapehtml($lang['DataContext']) . "</button>
                        </h2>
                        <div id='data_context' class='accordion-collapse collapse'>
                            <div class='accordion-body'>
                                <div class='form-group col-8'>
                                    <label for='data_types'>What types of data does your organization collect, process, or store (e.g., personal data, financial data, health information)?</label>";

    // Get the list of data types
    $data_types = get_data_types_array();

    // Display the multiselect dropdown of data types
    $selected_array = (isset($parameter_array['data_types']['value']) ? $parameter_array['data_types']['value'] : []);
    display_generic_multiselect('data_types', $data_types, $selected_array);

    echo "
                                </div>
                                <div class='form-group col-8'>
                                    <label for='data_customers'>Who are your primary customers or stakeholders (e.g., consumers, businesses, government entities)?</label>";

    // Get the list of data customers
    $data_customers = get_data_customers_array();

    // Display the multiselect dropdown of data customers
    $selected_array = (isset($parameter_array['data_customers']['value']) ? $parameter_array['data_customers']['value'] : []);
    display_generic_multiselect('data_customers', $data_customers, $selected_array);

    echo "
                                </div>
                                <div class='form-group col-8'>
                                    <label for='data_regulatory'>Are you subject to any specific regulatory requirements in your industry?</label>";

    // Get the list of data regulations
    $data_regulations = get_data_regulations_array();

    // Display the multiselect dropdown of data regulations
    $selected_array = (isset($parameter_array['data_regulatory']['value']) ? $parameter_array['data_regulatory']['value'] : []);
    display_generic_multiselect('data_regulatory', $data_regulations, $selected_array);

    echo "
                                </div>
                                <div class='form-group col-8'>
                                    <label for='data_third_parties'>Do you have any third-party relationships or outsourced services that require compliance oversight?</label>";

    // Get the list of data third parties
    $data_third_parties = get_data_third_parties_array();

    // Display the multiselect dropdown of data third parties
    $selected_array = (isset($parameter_array['data_third_parties']['value']) ? $parameter_array['data_third_parties']['value'] : []);
    display_generic_multiselect('data_third_parties', $data_third_parties, $selected_array);

    echo "
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class='well accordion-item'>
                        <h2 class='accordion-header'>
                            <button class='accordion-button collapsed' type='button' data-bs-toggle='collapse' data-bs-target='#maturity_context'>" . $escaper->escapehtml($lang['MaturityContext']) . "</button>
                        </h2>
                        <div id='maturity_context' class='accordion-collapse collapse'>
                            <div class='accordion-body'>
                                <div class='form-group col-8'>
                                    <label for='maturity_issues'>Have you experienced any significant compliance issues, security breaches, or risk events in the past 3-5 years?</label>";

    // Get the array of maturity issues
    $maturity_issues = get_maturity_issues_array();

    // Display a multi-select of the maturity issues
    $selected_array = (isset($parameter_array['maturity_issues']['value']) ? $parameter_array['maturity_issues']['value'] : []);
    display_generic_multiselect('maturity_issues', $maturity_issues, $selected_array);

    echo "
                                </div>
                                <div class='form-group col-8'>
                                    <label for='maturity_concerns'>Are there any specific areas of concern or improvement that you've identified in your current GRC processes?</label>";

    // Get the array of maturity concerns
    $maturity_concerns = get_maturity_concerns_array();

    // Display the multi-select of maturity concerns
    $selected_array = (isset($parameter_array['maturity_concerns']['value']) ? $parameter_array['maturity_concerns']['value'] : []);
    display_generic_multiselect('maturity_concerns', $maturity_concerns, $selected_array);

    echo "
                                </div>
                                <div class='form-group col-8'>
                                    <label for='maturity_appetite'>What is your organization's risk appetite and tolerance?</label>";

    // Get the list of maturity appetites
    $maturity_appetites = get_maturity_appetite_array();

    // Display the radio select of maturity appetites
    $selected_value = (isset($parameter_array['maturity_appetite']['value']) ? $parameter_array['maturity_appetite']['value'] : "");
    display_generic_radio_select('maturity_appetite', $maturity_appetites, $selected_value);

    echo "
                                </div>
                                <div class='form-group col-8'>
                                    <label for='maturity_level'>What is your current maturity level in terms of governance, risk management, and compliance practices?</label>";

    // Get the array of maturity levels
    $maturity_levels = get_maturity_levels_array();

    // Display the radio select of maturity levels
    $selected_value = (isset($parameter_array['maturity_level']['value']) ? $parameter_array['maturity_level']['value'] : "");
    display_generic_radio_select('maturity_level', $maturity_levels, $selected_value);

    echo "
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class='well accordion-item'>
                        <h2 class='accordion-header'>
                            <button class='accordion-button collapsed' type='button' data-bs-toggle='collapse' data-bs-target='#implementation_context'>" . $escaper->escapehtml($lang['ImplementationContext']) . "</button>
                        </h2>
                        <div id='implementation_context' class='accordion-collapse collapse'>
                            <div class='accordion-body'>
                                <div class='form-group col-8'>
                                    <label for='implementation_changes'>Are there any upcoming changes in your business model, technology infrastructure, or market that might impact your compliance needs?</label>";

    // Get the array of implementation changes
    $implementation_changes = get_implementation_changes_array();

    // Display the multi-select of implementation changes
    $selected_array = (isset($parameter_array['implementation_changes']['value']) ? $parameter_array['implementation_changes']['value'] : []);
    display_generic_multiselect('implementation_changes', $implementation_changes, $selected_array);

    echo "
                                </div>
                                <div class='form-group col-8'>
                                    <label for='implementation_resources_budget'>What budget allocation resources do you have available for implementing and maintaining compliance frameworks?</label>";

    // Get the array of implementation resources budget
    $implementation_resources_budget = get_implementation_resources_budget_array();

    // Display the radio-select of implementation resources budget
    $selected_value = (isset($parameter_array['implementation_resources_budget']['value']) ? $parameter_array['implementation_resources_budget']['value'] : "");
    display_generic_radio_select('implementation_resources_budget', $implementation_resources_budget, $selected_value);

    echo "
                                </div>
                                <div class='form-group col-8'>
                                    <label for='implementation_resources_personnel'>What personnel resources do you have available for implementing and maintaining compliance frameworks?</label>";

    // Get the array of implementation resources personnel
    $implementation_resources_personnel = get_implementation_resources_personnel_array();

    // Display the radio-select of implementation resources personnel
    $selected_value = (isset($parameter_array['implementation_resources_personnel']['value']) ? $parameter_array['implementation_resources_personnel']['value'] : "");
    display_generic_radio_select('implementation_resources_personnel', $implementation_resources_personnel, $selected_value);

    echo "
                                </div>
                                <div class='form-group col-8'>
                                    <label for='implementation_resources_technology'>What technology infrastructure resources do you have available for implementing and maintaining compliance frameworks?</label>";

    // Get the array of implementation resources budget
    $implementation_resources_technology = get_implementation_resources_technology_array();

    // Display the radio-select of implementation resources technology
    $selected_value = (isset($parameter_array['implementation_resources_technology']['value']) ? $parameter_array['implementation_resources_technology']['value'] : "");
    display_generic_radio_select('implementation_resources_technology', $implementation_resources_technology, $selected_value);

    echo "
                                </div>
                                <div class='form-group col-8'>
                                    <label for='implementation_resources_training'>What training and development resources do you have available for implementing and maintaining compliance frameworks?</label>";

    // Get the array of implementation resources budget
    $implementation_resources_training = get_implementation_resources_training_array();

    // Display the radio-select of implementation resources training
    $selected_value = (isset($parameter_array['implementation_resources_training']['value']) ? $parameter_array['implementation_resources_training']['value'] : "");
    display_generic_radio_select('implementation_resources_training', $implementation_resources_training, $selected_value);

    echo "
                                </div>
                                <div class='form-group col-8'>
                                    <label for='implementation_resources_external'>What external support resources do you have available for implementing and maintaining compliance frameworks?</label>";

    // Get the array of implementation resources external
    $implementation_resources_external = get_implementation_resources_external_array();

    // Display the radio-select of implementation resources external
    $selected_value = (isset($parameter_array['implementation_resources_external']['value']) ? $parameter_array['implementation_resources_external']['value'] : "");
    display_generic_radio_select('implementation_resources_external', $implementation_resources_external, $selected_value);

    echo "
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            </form>
            <script type='text/javascript'>
                $(document).ready(function(){
                    $('#org_objective, #org_location, #data_types, #data_customers, #data_regulatory, #data_third_parties, #maturity_issues, #maturity_concerns, #implementation_changes').multiselect({
                        nonSelectedText: 'Unknown / Prefer Not to Say',
                        buttonTextAlignment: 'left',
                        includeSelectAllOption: false,
                        buttonWidth: '100%',
                        enableCollapsibleOptGroups: true,
                        enableFiltering: true,
                        enableCaseInsensitiveFiltering: true
                    });
                });
            </script>";
}

/**************************************
 * FUNCTION: GET ORG OBJECTIVES ARRAY *
 **************************************/
function get_org_objectives_array()
{
    // Create an array of org industries
    $org_objectives = [
        "Financial Goals" => [
            "Increase revenue growth",
            "Improve profit margins",
            "Reduce operational costs",
            "Increase market share",
        ],
        "Customer-Centric Goals" => [
            "Enhance customer satisfaction and loyalty",
            "Accelerate customer acquisition",
            "Improve customer retention rates",
        ],
        "Operational Excellence" => [
            "Increase operational efficiency",
            "Enhance product/service quality",
            "Optimize supply chain operations",
        ],
        "Innovation and Growth" => [
            "Develop and launch new products/services",
            "Expand into new markets or regions",
            "Increase investment in R&D",
        ],
        "Digital Transformation" => [
            "Accelerate digital technology adoption",
            "Become more data-driven in decision making",
            "Enhance e-commerce capabilities",
        ],
        "Sustainability and Corporate Responsibility" => [
            "Reduce environmental impact",
            "Enhance corporate social responsibility",
            "Strengthen ethical business practices",
        ],
        "Human Capital" => [
            "Improve talent acquisition and retention",
            "Enhance employee engagement and satisfaction",
            "Develop employee skills and capabilities",
        ],
    ];

    // Return the array of org objectives
    return $org_objectives;
}

/************************************
 * FUNCTION: GET ORG INDUSTRY ARRAY *
 ************************************/
function get_org_industry_array()
{
    // Create an array of org industries
    $org_industries = [
        "Unknown / Prefer Not to Say",
        "Aerospace and Defense",
        "Agriculture and Food Production",
        "Automotive",
        "Biotechnology",
        "Construction and Real Estate",
        "Education",
        "Energy and Utilities",
        "Financial Services (Banking, Insurance, Investment)",
        "Government and Public Sector",
        "Healthcare and Pharmaceuticals",
        "Hospitality and Tourism",
        "Manufacturing",
        "Media and Entertainment",
        "Mining and Natural Resources",
        "Non-profit and NGOs",
        "Professional Services (Consulting, Legal, Accounting)",
        "Retail and E-commerce",
        "Technology and Software",
        "Telecommunications",
        "Transportation and Logistics",
    ];

    // Return the array of org industries
    return $org_industries;
}

/********************************
 * FUNCTION: GET ORG TYPE ARRAY *
 ********************************/
function get_org_type_array()
{
    // Create an array of org types
    $org_types = [
        "Unknown / Prefer Not to Say",
        "Publicly Traded",
        "Privately Held",
        "Non-Profit",
    ];

    // Return the array of org types
    return $org_types;
}

/**********************************
 * FUNCTION: GET DATA TYPES ARRAY *
 **********************************/
function get_data_types_array()
{
    // Create an array of data types
    $data_types = [
        "Personal Information" => [
            "Personally Identifiable Information (PII) (e.g., names, addresses, phone numbers)",
            "Financial Data (e.g., credit card numbers, bank account details)",
            "Health Information (e.g., medical records, insurance information)",
            "Biometric Data (e.g., fingerprints, facial recognition data)",
        ],
        "Sensitive Information" => [
            "Data related to minors",
            "Genetic information",
        ],
        "Business Data" => [
            "Trade secrets or proprietary information",
            "Business plans and strategies",
            "Employee data (e.g., HR records, payroll information)",
        ],
        "Technical Data" => [
            "Intellectual property (e.g., patents, copyrights)",
            "Source code or software",
            "Network and infrastructure data",
        ],
        "Other Types" => [
            "Location data",
            "Communication records (e.g., emails, chat logs)",
            "Behavioral or preference data",
        ]
    ];

    // Return the array of data types
    return $data_types;
}

/**************************************
 * FUNCTION: GET DATA CUSTOMERS ARRAY *
 **************************************/
function get_data_customers_array()
{
    // Create an array of data customers
    $data_customers = [
        "Business-to-Consumer (B2C)" => [
            "General Consumers (retail customers)",
            "High Net Worth Individuals",
            "Students or Educational Institutions",
        ],
        "Business-to-Business (B2B)" => [
            "Small and Medium-sized Businesses (SMBs)",
            "Large Corporations or Enterprises",
            "Wholesalers or Distributors",
        ],
        "Government and Public Sector" => [
            "Government Agencies or Departments",
            "Military or Defense Organizations",
            "Public Services (e.g., healthcare, education)",
        ],
        "Non-Profit and NGO" => [
            "Charities or Foundations",
            "Non-Governmental Organizations (NGOs)",
        ],
        "Financial Sector" => [
            "Banks or Financial Institutions",
            "Investors or Shareholders",
        ],
        "Industry-Specific" => [
            "Healthcare Providers or Patients",
            "Manufacturers or Suppliers",
        ],
        "Internal Stakeholders" => [
            "Employees",
            "Board Members or Executives",
        ]
    ];

    // Return the array of data customers
    return $data_customers;
}

/****************************************
 * FUNCTION: GET DATA REGULATIONS ARRAY *
 ****************************************/
function get_data_regulations_array()
{
    // Create an array of data regulations
    $data_regulations = [
        "Data Protection and Privacy" => [
            "General Data Protection Regulation (GDPR)",
            "California Consumer Privacy Act (CCPA)",
            "Personal Information Protection and Electronic Documents Act (PIPEDA)",
        ],
        "Financial Services" => [
            "Sarbanes-Oxley Act (SOX)",
            "Gramm-Leach-Bliley Act (GLBA)",
            "Payment Services Directive 2 (PSD2)",
            "Anti-Money Laundering (AML) regulations",
        ],
        "Healthcare" => [
            "Health Insurance Portability and Accountability Act (HIPAA)",
            "Health Information Technology for Economic and Clinical Health Act (HITECH)",
            "Food and Drug Administration (FDA) regulations",
        ],
        "Retail and E-commerce" => [
            "Payment Card Industry Data Security Standard (PCI DSS)",
        ],
        "Government and Defense" => [
            "Federal Information Security Management Act (FISMA)",
            "Federal Risk and Authorization Management Program (FedRAMP)",
            "International Traffic in Arms Regulations (ITAR)",
        ],
        "Energy and Utilities" => [
            "North American Electric Reliability Corporation Critical Infrastructure Protection (NERC CIP)",
        ],
        "Environmental" => [
            "Environmental Protection Agency (EPA) regulations",
        ],
        "Telecommunications" => [
            "Federal Communications Commission (FCC) regulations",
        ],
        "Cross-Industry Standards" => [
            "ISO 27001 (Information Security Management)",
            "NIST Cybersecurity Framework",
        ]
    ];

    // Return the array of data regulations
    return $data_regulations;
}

/******************************************
 * FUNCTION: GET DATA THIRD PARTIES ARRAY *
 ******************************************/
function get_data_third_parties_array()
{
    // Create an array of data third parties
    $data_third_parties = [
        "IT and Data Services" => [
            "Cloud Service Providers (IaaS, PaaS, SaaS)",
            "Data Processing Services",
            "Managed IT Services",
            "Third-Party Software Vendors",
        ],
        "Business Operations" => [
            "Business Process Outsourcing (BPO)",
            "Call Centers or Customer Support",
            "Logistics and Supply Chain Services",
        ],
        "Financial Services" => [
            "Payment Processors",
            "Financial Services Providers",
        ],
        "Human Resources" => [
            "Recruitment and Staffing Agencies",
            "Payroll Processing Services",
        ],
        "Professional Services" => [
            "Legal Services",
            "Accounting and Auditing Services",
            "Management Consulting Services",
        ],
        "Marketing and Sales" => [
            "Marketing Agencies",
            "Sales Partners or Resellers",
        ],
        "Research and Development" => [
            "Research Partners or Laboratories",
            "Product Development Partners",
        ],
        "Facilities and Physical Security" => [
            "Facilities Management Services",
            "Physical Security Services",
        ]
    ];

    // Return the array of data regulations
    return $data_third_parties;
}

/*****************************************
 * FUNCTION: GET MATURITY APPETITE ARRAY *
 *****************************************/
function get_maturity_appetite_array()
{
    // Create an array of issues
    $maturity_appetites = [
        "Unknown / Prefer Not to Say" => [
            "text" => "Unknown / Prefer Not to Say",
            "html" => "<strong>Unknown / Prefer Not to Say</strong>"
        ],
        "Averse (Minimal): We avoid risk whenever possible and are willing to sacrifice potential returns to ensure stability and security. We have very low tolerance for uncertainty." => [
            "text" => "Averse (Minimal): We avoid risk whenever possible and are willing to sacrifice potential returns to ensure stability and security. We have very low tolerance for uncertainty.",
            "html" => "<strong>Averse (Minimal):</strong> We avoid risk whenever possible and are willing to sacrifice potential returns to ensure stability and security. We have very low tolerance for uncertainty."
        ],
        "Cautious (Low): We prefer low-risk options and are willing to accept lower returns for more certainty. We have a low tolerance for volatility and uncertainty." => [
            "text" => "Cautious (Low): We prefer low-risk options and are willing to accept lower returns for more certainty. We have a low tolerance for volatility and uncertainty.",
            "html" => "<strong>Cautious (Low):</strong> We prefer low-risk options and are willing to accept lower returns for more certainty. We have a low tolerance for volatility and uncertainty."
        ],
        "Moderate (Balanced): We seek a balance between risk and return. We're willing to accept some volatility and uncertainty in pursuit of our objectives." => [
            "text" => "Moderate (Balanced): We seek a balance between risk and return. We're willing to accept some volatility and uncertainty in pursuit of our objectives.",
            "html" => "<strong>Moderate (Balanced):</strong> We seek a balance between risk and return. We're willing to accept some volatility and uncertainty in pursuit of our objectives."
        ],
        "Open (High): We're comfortable with significant risk if it aligns with our strategic objectives. We have a high tolerance for volatility and are willing to accept potential losses in pursuit of higher returns." => [
            "text" => "Open (High): We're comfortable with significant risk if it aligns with our strategic objectives. We have a high tolerance for volatility and are willing to accept potential losses in pursuit of higher returns.",
            "html" => "<strong>Open (High):</strong> We're comfortable with significant risk if it aligns with our strategic objectives. We have a high tolerance for volatility and are willing to accept potential losses in pursuit of higher returns."
        ],
        "Hungry (Aggressive): We actively seek high-risk, high-reward opportunities. We have a very high tolerance for volatility and uncertainty, viewing them as necessary for achieving exceptional results." => [
            "text" => "Hungry (Aggressive): We actively seek high-risk, high-reward opportunities. We have a very high tolerance for volatility and uncertainty, viewing them as necessary for achieving exceptional results.",
            "html" => "<strong>Hungry (Aggressive):</strong> We actively seek high-risk, high-reward opportunities. We have a very high tolerance for volatility and uncertainty, viewing them as necessary for achieving exceptional results."
        ],
    ];

    // Return the array of maturity appetites
    return $maturity_appetites;
}

/***************************************
 * FUNCTION: GET MATURITY ISSUES ARRAY *
 ***************************************/
function get_maturity_issues_array()
{
    // Create an array of issues
    $maturity_issues = [
        "Compliance Issue" => [
            "Regulatory violations or fines",
            "Significant audit findings or qualified opinions",
            "License suspensions or revocations",
        ],
        "Security Breach" => [
            "Data breaches or unauthorized access to sensitive information",
            "Cyberattacks (e.g., ransomware, DDoS)",
            "Insider threats or employee misconduct",
        ],
        "Risk Event" => [
            "Significant financial losses or fraud",
            "Major operational disruptions or system failures",
            "Reputational damage or negative media coverage",
            "Legal actions or lawsuits against the organization",
        ]
    ];

    // Return the array of maturity issues
    return $maturity_issues;
}

/***************************************
 * FUNCTION: GET MATURITY LEVELS ARRAY *
 ***************************************/
function get_maturity_levels_array()
{
    $maturity_levels = [
        "Unknown / Prefer Not to Say" => [
            "text" => "Unknown / Prefer Not to Say",
            "html" => "<strong>Unknown / Prefer Not to Say</strong>"
        ],
        "Initial (Level 1): Ad hoc and reactive" => [
            "text" => "Initial (Level 1): Ad hoc and reactive",
            "html" => "<strong>Initial (Level 1):</strong> Ad hoc and reactive"
        ],
        "Developing (Level 2): Repeatable but intuitive" => [
            "text" => "Developing (Level 2): Repeatable but intuitive",
            "html" => "<strong>Developing (Level 2):</strong> Repeatable but intuitive"
        ],
        "Defined (Level 3): Defined process" => [
            "text" => "Defined (Level 3): Defined process",
            "html" => "<strong>Defined (Level 3):</strong> Defined process"
        ],
        "Managed (Level 4): Measured and controlled" => [
            "text" => "Managed (Level 4): Measured and controlled",
            "html" => "<strong>Managed (Level 4):</strong> Measured and controlled"
        ],
        "Optimizing (Level 5): Continuous improvement" => [
            "text" => "Optimizing (Level 5): Continuous improvement",
            "html" => "<strong>Optimizing (Level 5):</strong> Continuous improvement"
        ],
    ];

    // Return the array of maturity levels
    return $maturity_levels;
}

/*****************************************
 * FUNCTION: GET MATURITY CONCERNS ARRAY *
 *****************************************/
function get_maturity_concerns_array()
{
    $maturity_concerns = [
        "Governance" => [
            "Policy Management and Implementation",
            "Board Oversight and Reporting",
            "Organizational Structure and Responsibilities",
        ],
        "Risk Management" => [
            "Risk Assessment and Prioritization",
            "Risk Mitigation Strategies",
            "Identification of Emerging Risks",
        ],
        "Compliance" => [
            "Regulatory Change Tracking and Implementation",
            "Compliance Monitoring and Testing",
            "Incident Management and Reporting",
        ],
        "Technology and Data" => [
            "GRC Technology and Tools",
            "Data Quality and Management",
            "Process Automation and Efficiency",
        ],
        "Reporting and Analytics" => [
            "Metrics and KPI Development",
            "Reporting and Dashboard Creation",
            "Advanced Analytics and Predictive Modeling",
        ],
        "Culture and Training" => [
            "GRC Awareness and Training Programs",
            "Risk and Compliance Culture",
            "Internal Communication of GRC Initiatives",
        ],
        "Third-Party Management" => [
            "Vendor Risk Assessment and Due Diligence",
            "Contract Management and Compliance",
        ],
        "Audit and Assurance" => [
            "Internal Audit Processes",
            "External Audit Readiness",
        ]
    ];

    // Return the array of maturity concerns
    return $maturity_concerns;
}

/**********************************************
 * FUNCTION: GET IMPLEMENTATION CHANGES ARRAY *
 **********************************************/
function get_implementation_changes_array()
{
    $implementation_changes = [
        "Business Model Changes" => [
            "Launching new products or services",
            "Entering new markets or geographical regions",
            "Mergers, acquisitions, or divestitures",
            "Significant business restructuring",
        ],
        "Technology Infrastructure Changes" => [
            "Cloud migration or adoption",
            "Implementation of new core systems (e.g., ERP, CRM)",
            "Adoption of AI/ML technologies",
            "Internet of Things (IoT) implementation",
            "Blockchain or distributed ledger technology adoption",
        ],
        "Data Management Changes" => [
            "Changes in data collection practices",
            "New data sharing arrangements",
            "Advanced data analytics initiatives",
        ],
        "Market and Customer Changes" => [
            "Significant changes in customer base",
            "Digital transformation of customer interactions",
            "New e-commerce initiatives",
        ],
        "Operational Changes" => [
            "Shift to remote or hybrid work models",
            "New outsourcing arrangements",
            "Major changes in supply chain",
        ],
        "Regulatory Environment" => [
            "Anticipated new regulations in your industry",
            "Significant changes to existing regulations",
        ]
    ];

    // Return the array of implementation changes
    return $implementation_changes;
}

/*******************************************************
 * FUNCTION: GET IMPLEMENTATION RESOURCES BUDGET ARRAY *
 *******************************************************/
function get_implementation_resources_budget_array()
{
    $implementation_resources_budget = [
        "Unknown / Prefer Not to Say" => [
            "text" => "Unknown / Prefer Not to Say",
            "html" => "<strong>Unknown / Prefer Not to Say</strong>"
        ],
        "Minimal: Limited budget, compliance is not a primary focus" => [
            "text" => "Minimal: Limited budget, compliance is not a primary focus",
            "html" => "<strong>Minimal:</strong> Limited budget, compliance is not a primary focus"
        ],
        "Moderate: Sufficient budget for essential compliance activities" => [
            "text" => "Moderate: Sufficient budget for essential compliance activities",
            "html" => "<strong>Moderate:</strong> Sufficient budget for essential compliance activities"
        ],
        "Significant: Well-funded compliance program with room for initiatives" => [
            "text" => "Significant: Well-funded compliance program with room for initiatives",
            "html" => "<strong>Significant:</strong> Well-funded compliance program with room for initiatives"
        ],
        "Extensive: Large budget allocation, compliance is a top priority" => [
            "text" => "Extensive: Large budget allocation, compliance is a top priority",
            "html" => "<strong>Extensive:</strong> Large budget allocation, compliance is a top priority"
        ],
    ];

    // Return the array of implementation resources budget
    return $implementation_resources_budget;
}

/**********************************************************
 * FUNCTION: GET IMPLEMENTATION RESOURCES PERSONNEL ARRAY *
 **********************************************************/
function get_implementation_resources_personnel_array()
{
    $implementation_resources_personnel = [
        "Unknown / Prefer Not to Say" => [
            "text" => "Unknown / Prefer Not to Say",
            "html" => "<strong>Unknown / Prefer Not to Say</strong>"
        ],
        "Part-time: Compliance responsibilities are shared or part-time" => [
            "text" => "Part-time: Compliance responsibilities are shared or part-time",
            "html" => "<strong>Part-time:</strong> Compliance responsibilities are shared or part-time"
        ],
        "Small Team: Dedicated compliance team with limited capacity" => [
            "text" => "Small Team: Dedicated compliance team with limited capacity",
            "html" => "<strong>Small Team:</strong> Dedicated compliance team with limited capacity"
        ],
        "Full Team: Well-staffed compliance department" => [
            "text" => "Full Team: Well-staffed compliance department",
            "html" => "<strong>Full Team:</strong> Well-staffed compliance department"
        ],
        "Large Department: Extensive compliance staff across multiple areas" => [
            "text" => "Large Department: Extensive compliance staff across multiple areas",
            "html" => "<strong>Large Department:</strong> Extensive compliance staff across multiple areas"
        ],
    ];

    // Return the array of implementation resources personnel
    return $implementation_resources_personnel;
}

/***********************************************************
 * FUNCTION: GET IMPLEMENTATION RESOURCES TECHNOLOGY ARRAY *
 ***********************************************************/
function get_implementation_resources_technology_array()
{
    $implementation_resources_technology = [
        "Unknown / Prefer Not to Say" => [
            "text" => "Unknown / Prefer Not to Say",
            "html" => "<strong>Unknown / Prefer Not to Say</strong>"
        ],
        "Basic: Minimal technology, mostly manual processes" => [
            "text" => "Basic: Minimal technology, mostly manual processes",
            "html" => "<strong>Basic:</strong> Minimal technology, mostly manual processes"
        ],
        "Moderate: Some compliance-specific tools, partially automated" => [
            "text" => "Moderate: Some compliance-specific tools, partially automated",
            "html" => "<strong>Moderate:</strong> Some compliance-specific tools, partially automated"
        ],
        "Advanced: Dedicated GRC software and automation tools" => [
            "text" => "Advanced: Dedicated GRC software and automation tools",
            "html" => "<strong>Advanced:</strong> Dedicated GRC software and automation tools"
        ],
        "Cutting-edge: Fully integrated GRC platform with advanced analytics" => [
            "text" => "Cutting-edge: Fully integrated GRC platform with advanced analytics",
            "html" => "<strong>Cutting-edge:</strong> Fully integrated GRC platform with advanced analytics"
        ],
    ];

    // Return the array of implementation resources technology
    return $implementation_resources_technology;
}

/*********************************************************
 * FUNCTION: GET IMPLEMENTATION RESOURCES TRAINING ARRAY *
 *********************************************************/
function get_implementation_resources_training_array()
{
    $implementation_resources_training = [
        "Unknown / Prefer Not to Say" => [
            "text" => "Unknown / Prefer Not to Say",
            "html" => "<strong>Unknown / Prefer Not to Say</strong>"
        ],
        "Minimal: Basic compliance training only" => [
            "text" => "Minimal: Basic compliance training only",
            "html" => "<strong>Minimal:</strong> Basic compliance training only"
        ],
        "Moderate: Regular training programs for compliance staff" => [
            "text" => "Moderate: Regular training programs for compliance staff",
            "html" => "<strong>Moderate:</strong> Regular training programs for compliance staff"
        ],
        "Comprehensive: Ongoing training and development for all employees" => [
            "text" => "Comprehensive: Ongoing training and development for all employees",
            "html" => "<strong>Comprehensive:</strong> Ongoing training and development for all employees"
        ],
        "Advanced: Specialized certifications and external expertise readily available" => [
            "text" => "Advanced: Specialized certifications and external expertise readily available",
            "html" => "<strong>Advanced:</strong> Specialized certifications and external expertise readily available"
        ],
    ];

    // Return the array of implementation resources training
    return $implementation_resources_training;
}

/*********************************************************
 * FUNCTION: GET IMPLEMENTATION RESOURCES EXTERNAL ARRAY *
 *********************************************************/
function get_implementation_resources_external_array()
{
    $implementation_resources_external = [
        "Unknown / Prefer Not to Say" => [
            "text" => "Unknown / Prefer Not to Say",
            "html" => "<strong>Unknown / Prefer Not to Say</strong>"
        ],
        "Minimal: Rarely use external consultants or services" => [
            "text" => "Minimal: Rarely use external consultants or services",
            "html" => "<strong>Minimal:</strong> Rarely use external consultants or services"
        ],
        "Occasional: Use external support for specific projects or audits" => [
            "text" => "Occasional: Use external support for specific projects or audits",
            "html" => "<strong>Occasional:</strong> Use external support for specific projects or audits"
        ],
        "Regular: Ongoing relationships with compliance consultants" => [
            "text" => "Regular: Ongoing relationships with compliance consultants",
            "html" => "<strong>Regular:</strong> Ongoing relationships with compliance consultants"
        ],
        "Extensive: Heavy reliance on external expertise and managed services" => [
            "text" => "Extensive: Heavy reliance on external expertise and managed services",
            "html" => "<strong>Extensive:</strong> Heavy reliance on external expertise and managed services"
        ],
    ];

    // Return the array of implementation resources external
    return $implementation_resources_external;
}

/*****************************************************************
 * FUNCTION: GET ARTIFICIAL INTELLIGENCE CONTEXT PARAMETER ARRAY *
 *****************************************************************/
function get_artificial_intelligence_context_parameter_array()
{
    $context_parameters = [
        "org_name" => [
            "type" => "text",
            "question" => "What is the name of your organization?"
        ],
        "org_size_employees" => [
            "type" => "text",
            "question" => "How many employees does your organization have?"
        ],
        "org_size_revenue" => [
            "type" => "text",
            "question" => "What is the annual revenue of your organization?"
        ],
        "org_objective" => [
            "type" => "multiselect",
            "question" => "What are your organization's primary business objectives and strategic goals?"
        ],
        "org_industry" => [
            "type" => "singleselect",
            "question" => "What is the primary industry or sector of your organization?"
        ],
        "org_location" => [
            "type" => "multiselect",
            "question" => "In which countries or regions does your organization operate?"
        ],
        "org_type" => [
            "type" => "singleselect",
            "question" => "What type of organization are you?"
        ],
        "data_types" => [
            "type" => "multiselect",
            "question" => "What types of data does your organization collect, process, or store (e.g., personal data, financial data, health information)?"
        ],
        "data_customers" => [
            "type" => "multiselect",
            "question" => "Who are your primary customers or stakeholders (e.g., consumers, businesses, government entities)?"
        ],
        "data_regulatory" => [
            "type" => "multiselect",
            "question" => "Are you subject to any specific regulatory requirements in your industry?"
        ],
        "data_third_parties" => [
            "type" => "multiselect",
            "question" => "Do you have any third-party relationships or outsourced services that require compliance oversight?"
        ],
        "maturity_issues" => [
            "type" => "multiselect",
            "question" => "Have you experienced any significant compliance issues, security breaches, or risk events in the past 3-5 years?"
        ],
        "maturity_concerns" => [
            "type" => "multiselect",
            "question" => "Are there any specific areas of concern or improvement that you've identified in your current GRC processes?"
        ],
        "maturity_appetite" => [
            "type" => "singleselect",
            "question" => "What is your organization's risk appetite and tolerance?"
        ],
        "maturity_level" => [
            "type" => "singleselect",
            "question" => "What is your current maturity level in terms of governance, risk management, and compliance practices?"
        ],
        "implementation_changes" => [
            "type" => "multiselect",
            "question" => "Are there any upcoming changes in your business model, technology infrastructure, or market that might impact your compliance needs?"
        ],
        "implementation_resources_budget" => [
            "type" => "singleselect",
            "question" => "What budget allocation resources do you have available for implementing and maintaining compliance frameworks?"
        ],
        "implementation_resources_personnel" => [
            "type" => "singleselect",
            "question" => "What personnel resources do you have available for implementing and maintaining compliance frameworks?"
        ],
        "implementation_resources_technology" => [
            "type" => "singleselect",
            "question" => "What technology infrastructure resources do you have available for implementing and maintaining compliance frameworks?"
        ],
        "implementation_resources_training" => [
            "type" => "singleselect",
            "question" => "What training and development resources do you have available for implementing and maintaining compliance frameworks?"
        ],
        "implementation_resources_external" => [
            "type" => "singleselect",
            "question" => "What external support resources do you have available for implementing and maintaining compliance frameworks?"
        ]
    ];

    // Return the array of context parameters
    return $context_parameters;
}

/************************************************
 * FUNCTION: GENERATE ANTHROPIC MESSAGE CONTEXT *
 ************************************************/
function generate_anthropic_message_context()
{
    write_debug_log("CORE: FUNCTION[generate_anthropic_message_context]");

    // Get the list of context parameters
    $context_parameters = get_artificial_intelligence_context_parameter_array();

    // Connect to the database
    $db = db_open();

    // Get the specified context values
    $stmt = $db->prepare("SELECT name, value FROM `settings` WHERE name like 'ai_context_%';");
    $stmt->execute();
    $settings_table = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Rewrite the array as name value pairs
    $settings = [];
    foreach ($settings_table as $setting)
    {
        $settings[$setting['name']] = $setting['value'];
    }

    // Create an empty content string
    $content = "";

    // For each context parameter
    foreach ($context_parameters as $key => $value)
    {
        // Create the setting name
        $setting_name = "ai_context_" . $key;

        // If we have a matching value in our settings
        if (array_key_exists($setting_name, $settings))
        {
            // Get the question that goes with the setting
            $question = "Question: " . $context_parameters[$key]['question'];

            // Get the answer that goes with the setting
            $answer = "Answer: " . $settings[$setting_name];

            // Add the question and answer to the content
            $content .= "\n" . $question . "\n";
            $content .= $answer . "\n";
        }
    }

    // Write the content to the debug log
    write_debug_log($content);

    // Return the content
    return $content;
}

/***********************************************
 * FUNCTION: ASK ANTHROPIC FOR RECOMMENDATIONS *
 ***********************************************/
function ask_anthropic_for_recommendations($context_content)
{
    // Create the content asking for advice
    $content = "The organization you have been hired to assist has been asked a series of questions to determine which frameworks are relevant for their GRC program.  What follows is a list of questions they were asked and the answers that they provided.\n";

    // Add the context Q&A content
    $content .= $context_content;

    // Finish the prompt
    $content .= "
        I would like you to:
        * Read through the list of questions and answers and make note of any key insights or data
        * Consider additional data points from your knowledge base or critiques of the data
        * Provide advice and guidance on what activities they should be doing to improve their GRC program
        * Provide advice and guidance on specific frameworks they should consider along with justification as to why
        * Provide any concerns about their current GRC program along with recommendations for improvement
        * Tie your responses back to how they answered the questions and provide justification for any suggestions that you make
        * Phrase the output like you are directly advising the end user
        * Skip the preamble and provide just the output
        
        Think about this step-by-step. 
    ";

    // Add the message to the end of the messages array
    $messages[] = [
        "role" => "user",
        "content" => $content
    ];

    try
    {
        // Get the anthropic API key
        $anthropic_api_key = get_setting('anthropic_api_key', false, false);

        // Create the client
        $client = new ClaudeAPIClient($anthropic_api_key);

        // Call the Claude API with the messages
        $result = $client->callClaudeAPI($messages, 8192);

        // If we received a result
        if (isset($result['content'][0]['text']))
        {
            // Add the result to the messages array
            $messages[] = [
                "role" => "assistant",
                "content" => $result['content'][0]['text']
            ];

            // Ask Claude to critique its results and determine how to improve them
            $messages[] = [
                "role" => "user",
                "content" => "Critique the above output, how could this be improved?"
            ];

            // Call the Claude API with the messages
            $result = $client->callClaudeAPI($messages, 8192);

            // If we received a result
            if (isset($result['content'][0]['text']))
            {
                // Add the result to the messages array
                $messages[] = [
                    "role" => "assistant",
                    "content" => $result['content'][0]['text']
                ];

                // Ask Claude to action on the suggestions
                $content = "
                    I would like you to:
                    * Improve the output by taking action on these suggestions
                    * The results should include sections for Executive Summary, Key Insights and Data Points, Prioritized Activities for Improving GRC Program, Relevant Compliance Frameworks and Justifications, Concerns and Recommendations for Improvement and Conclusion
                    * The Key Insights and Data Points should contain a table with columns for 'Data Point' and 'Implication'
                    * The Relevant Compliance Frameworks and Justifications should contain a table with columns for 'Framework' and 'Justification'
                    * Populate all data tables in the output and apply the class 'table table-bordered table-striped table-condensed' to the table tags
                    * Apply the class 'card-title' to the h4 tags
                    * Skip the preamble and provide just the output
                    * Provide the output as HTML code, without the html, head or body tags, for display as content in an existing web page
                ";
                $messages[] = [
                    "role" => "user",
                    "content" => $content
                ];

                // Call the Claude API with the messages
                $result = $client->callClaudeAPI($messages, 8192);
            }

            // Return the result
            return $result['content'][0]['text'];
        }
        else
        {
            // Write an error message to the debug log
            write_debug_log("Unexpected response format: " . json_encode($result));

            // Return false
            return false;
        }
    }
    catch (Exception $e)
    {
        // Write an error message to the debug log
        write_debug_log("Error: " . $e->getMessage());

        // Return false
        return false;
    }
}

/**************************************************
 * FUNCTION: DISPLAY ARTIFICIAL INTELLIGENCE ICON *
 **************************************************/
function display_artificial_intelligence_icon($type, $id)
{
    // If the AI Extra is enabled
    if (artificial_intelligence_extra())
    {
        // If the extra directory exists
        if (is_dir(realpath(__DIR__ . '/../extras/artificial_intelligence')))
        {
            // Include the Artificial Intelligence Extra
            require_once(realpath(__DIR__ . '/../extras/artificial_intelligence/index.php'));

            // Display the AI Extra icon
            artificial_intelligence_display_icon($type, $id);
        }
    }
}

/**********************************************************
 * FUNCTION: CHECK ARTIFICIAL INTELLIGENCE CONTEXT UPDATE *
 **********************************************************/
function check_artificial_intelligence_context_update()
{
    write_debug_log("Artificial Intelligence: Checking for context updates.", "info");

    // Open the database connection
    $db = db_open();

    // Get timestamps
    $last_saved = get_setting("ai_context_last_saved", db: $db);
    $last_updated = get_setting("ai_context_last_updated", db: $db);

    write_debug_log("Artificial Intelligence: Context last saved at " . date("Y-m-d H:i:s", $last_saved), "debug");
    write_debug_log("Artificial Intelligence: Context last updated: " . date("Y-m-d H:i:s", $last_updated), "debug");

    // If it's time to update
    if ($last_updated < $last_saved || !$last_updated) {
        $message = "Artificial Intelligence: Context updated. Queueing for new recommendations.";
        write_debug_log($message, "notice");
        write_log(0, 0, $message, 'artificial_intelligence');

        // Queue the AI update job
        $queue_task_payload = [
            'triggered_at' => time(),
        ];
        queue_task($db, 'core_ai_context_update', $queue_task_payload, 50, 5, 3600);

        // Update the timestamp to prevent repeated queuing
        update_setting("ai_context_last_updated", time(), db: $db);
    } else {
        write_debug_log("Artificial Intelligence: Context has not been updated.", "info");
    }

    // Close the database connection
    db_close($db);
}

/************************************************************
 * FUNCTION: PROCESS ARTIFICIAL INTELLIGENCE CONTEXT UPDATE *
 ************************************************************/
function process_artificial_intelligence_context_update_task()
{
    try {
        write_debug_log("Artificial Intelligence: Starting context update.", "info");

        $context_content = generate_anthropic_message_context();
        $advice = ask_anthropic_for_recommendations($context_content);

        // If successful
        update_setting("ai_context_last_updated", time());
        write_debug_log("Artificial Intelligence: Context update completed successfully.", "info");
        write_log(0, 0, "AI context successfully updated via queue.", 'artificial_intelligence');

        return true;

    } catch (Exception $e) {
        write_debug_log("Artificial Intelligence: Context update failed: " . $e->getMessage(), "error");
        throw $e; // allows queue retry mechanism to kick in
    }
}

/**************************************************************************
 * FUNCTION: PROCESS ARTIFICIAL INTELLIGENCE DOCUMENT TO CONTROL MATCHING *
 **************************************************************************/
function process_artificial_intelligence_document_to_control_matching_task($db, $task)
{
    $payload = json_decode($task['payload'] ?? '', true);
    if (!is_array($payload) || !isset($payload['document_id'])) {
        write_debug_log("Invalid AI task payload: " . json_encode($task), 'error');
        $db->prepare("UPDATE queue_tasks SET status='failed', attempts=attempts+1, updated_at=NOW() WHERE id=?")
            ->execute([$task['id']]);
        return;
    }

    $document_id = $payload['document_id'];
    $promise_id = create_promise('ai_document_enhance', $document_id, $payload);
    update_promise_status($promise_id, 'running');

    try {
        $result = ai_document_enhance($document_id, false);

        if ($result['status_code'] == 200) {
            update_promise_status($promise_id, 'completed', $result);
            $db->prepare("UPDATE queue_tasks SET status='completed', updated_at=NOW() WHERE id=?")
                ->execute([$task['id']]);
            write_debug_log("Document ID {$document_id} enhanced successfully.", "info");
        } else {
            increment_promise_attempts($promise_id);
            update_promise_status($promise_id, 'failed', $result);
            $db->prepare("
                UPDATE queue_tasks 
                SET status='failed', attempts=attempts+1, updated_at=NOW() 
                WHERE id=?
            ")->execute([$task['id']]);
            write_debug_log("Error processing document ID {$document_id}: {$result['status_message']}", "warning");
        }
    } catch (Exception $e) {
        increment_promise_attempts($promise_id);
        update_promise_status($promise_id, 'failed', ['error' => $e->getMessage()]);
        $db->prepare("
            UPDATE queue_tasks 
            SET status='failed', attempts=attempts+1, updated_at=NOW() 
            WHERE id=?
        ")->execute([$task['id']]);
        write_debug_log("AI document enhance failed: " . $e->getMessage(), 'error');
    }
}

?>