<?php
/**
 * Learning Objective:
 * This tutorial demonstrates how to build a minimalist PHP API Gateway.
 * The gateway will proxy incoming requests to a target backend API
 * and implement basic HTTP Basic Authentication for security.
 * This will teach you about request handling, routing, and authentication
 * in a server-side context.
 */

// --- Configuration ---

// The base URL of the backend API we want to proxy requests to.
// All incoming requests to our gateway will be forwarded to this URL.
// Example: 'https://api.example.com/v1'
define('BACKEND_API_URL', 'http://localhost:8000/api'); // Replace with your actual backend URL

// --- Authentication ---

// This function checks if the provided username and password are valid.
// In a real-world application, you would typically check against a database
// or a secure authentication service.
function authenticateUser(string $username, string $password): bool
{
    // For this simple example, we'll hardcode a single valid user.
    // NEVER hardcode credentials in production! Use environment variables or a secure config.
    $validUsers = [
        'admin' => 'supersecretpassword', // Replace with a strong, unique password
    ];

    // Check if the username exists and if the provided password matches the stored one.
    return isset($validUsers[$username]) && $validUsers[$username] === $password;
}

// --- Request Handling ---

// This function retrieves authentication credentials from the HTTP Authorization header.
function getAuthCredentials(): ?array
{
    // Check if the 'Authorization' header is present in the incoming request.
    if (!isset($_SERVER['HTTP_AUTHORIZATION'])) {
        return null; // No authorization header found.
    }

    // The header typically looks like "Basic <base64-encoded credentials>".
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'];

    // Check if the header starts with "Basic ".
    if (strpos($authHeader, 'Basic ') !== 0) {
        return null; // Not a Basic authentication scheme.
    }

    // Extract the Base64 encoded credentials part.
    $encodedCredentials = substr($authHeader, 6); // 'Basic ' is 6 characters long.

    // Decode the Base64 string. This will give us "username:password".
    $decodedCredentials = base64_decode($encodedCredentials);

    // Check if decoding was successful and if it contains a colon (separator).
    if ($decodedCredentials === false || strpos($decodedCredentials, ':') === false) {
        return null; // Invalid encoding or format.
    }

    // Split the decoded string into username and password.
    [$username, $password] = explode(':', $decodedCredentials, 2); // Limit to 2 parts for safety.

    // Return the credentials as an associative array.
    return ['username' => $username, 'password' => $password];
}

// This function handles the incoming request.
function handleRequest()
{
    // First, check for authentication.
    $credentials = getAuthCredentials();

    // If credentials are not provided or are invalid, send a 401 Unauthorized response.
    if ($credentials === null || !authenticateUser($credentials['username'], $credentials['password'])) {
        header('WWW-Authenticate: Basic realm="API Gateway"'); // Inform the client about the authentication type and realm.
        http_response_code(401); // Set HTTP status to Unauthorized.
        echo json_encode(['error' => 'Unauthorized']); // Send a JSON error message.
        exit; // Stop script execution.
    }

    // If authenticated, prepare to proxy the request.

    // Get the method of the incoming request (GET, POST, PUT, DELETE, etc.).
    $requestMethod = $_SERVER['REQUEST_METHOD'];

    // Get the requested path from the URL, excluding the script name if it's in the path.
    // This is a simple way to get the path; more robust routing might be needed for complex setups.
    $requestPath = $_SERVER['REQUEST_URI'];
    // Basic cleanup to remove query parameters if needed, though cURL handles them.
    if (($queryPos = strpos($requestPath, '?')) !== false) {
        $requestPath = substr($requestPath, 0, $queryPos);
    }
    // Adjust path to remove leading slash if your backend expects it.
    // Example: if gateway is at /api/gateway.php and backend is at /v1,
    // you might need to strip '/api/gateway.php' or just '/api'.
    // For this simple example, we'll assume the backend is at the root of BACKEND_API_URL.
    // You might need to adjust this logic based on your BACKEND_API_URL structure.

    // Construct the full URL for the backend API.
    // We append the requested path (minus any potential script name) to the backend URL.
    $backendUrl = BACKEND_API_URL . $requestPath;

    // Initialize a cURL session to make the request to the backend.
    $ch = curl_init();

    // Set the URL for the cURL request.
    curl_setopt($ch, CURLOPT_URL, $backendUrl);

    // Specify that we want to return the transfer as a string instead of outputting it directly.
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

    // Forward the request method from the client to the backend.
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $requestMethod);

    // Forward request headers, except for Host and Authorization which cURL manages or we handle.
    // In a real-world scenario, you might want to carefully select or transform headers.
    $requestHeaders = getallheaders();
    $forwardedHeaders = [];
    foreach ($requestHeaders as $name => $value) {
        // Avoid forwarding the Authorization header as cURL might handle it internally or it's already processed.
        if (strtolower($name) !== 'authorization') {
            $forwardedHeaders[] = "$name: $value";
        }
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $forwardedHeaders);

    // If the request method is POST, PUT, or PATCH, we need to forward the request body.
    if (in_array($requestMethod, ['POST', 'PUT', 'PATCH'])) {
        // Get the raw request body from the client.
        $requestBody = file_get_contents('php://input');
        // Set the request body for the cURL request.
        curl_setopt($ch, CURLOPT_POSTFIELDS, $requestBody);
    }

    // Execute the cURL request and get the response.
    $response = curl_exec($ch);

    // Check for cURL errors.
    if (curl_errno($ch)) {
        http_response_code(502); // Bad Gateway: The server, while acting as a gateway or proxy,
                                 // did not receive a valid response from the upstream server.
        echo json_encode(['error' => 'Failed to connect to backend API', 'details' => curl_error($ch)]);
        curl_close($ch);
        exit;
    }

    // Get the HTTP status code from the backend response.
    $httpStatusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    // Set the HTTP status code for the client's response.
    http_response_code($httpStatusCode);

    // Get the response headers from the backend.
    $responseHeaders = [];
    curl_setopt($ch, CURLOPT_HEADERFUNCTION, function ($curl, $header) use (&$responseHeaders) {
        $len = strlen($header);
        // We're interested in response headers, not the request ones.
        if (strpos($header, ':') === false) { // Not a valid header line
            return $len;
        }
        // Split the header into name and value.
        [$name, $value] = explode(':', $header, 2);
        // Trim whitespace from name and value.
        $name = trim($name);
        $value = trim($value);
        // Store the header.
        $responseHeaders[$name] = $value;
        return $len; // Return the length of the header.
    });

    // Execute the request to get headers first.
    // We need to run it again or be smarter about getting headers.
    // A common approach is to set CURLOPT_HEADER to 1 and then parse the output.
    // For simplicity, let's re-initialize and get headers along with the body.
    // A more efficient way would be to get headers separately or parse the output carefully.

    // Re-initialize cURL to get both headers and body in a single exec.
    curl_setopt($ch, CURLOPT_HEADER, 1); // Include headers in the output.
    $responseWithHeaders = curl_exec($ch);

    if (curl_errno($ch)) {
        http_response_code(502);
        echo json_encode(['error' => 'Failed to connect to backend API', 'details' => curl_error($ch)]);
        curl_close($ch);
        exit;
    }

    // Separate headers from the body.
    $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $response_headers_raw = substr($responseWithHeaders, 0, $header_size);
    $response_body = substr($responseWithHeaders, $header_size);

    // Parse the raw headers and set them for the client.
    $header_lines = explode(PHP_EOL, $response_headers_raw);
    foreach ($header_lines as $line) {
        if (strpos($line, ':') !== false) {
            [$name, $value] = explode(':', $line, 2);
            $name = trim($name);
            $value = trim($value);
            // Don't send specific headers that might interfere with client/server communication,
            // like Transfer-Encoding or Content-Encoding if we're not handling them.
            // For simplicity, we'll send most back.
            if (!in_array(strtolower($name), ['content-encoding', 'transfer-encoding'])) {
                header("$name: $value");
            }
        }
    }

    // Output the body received from the backend.
    echo $response_body;

    // Close the cURL session.
    curl_close($ch);
}

// --- Main Execution ---

// This is the entry point of our API Gateway script.
// It checks the request method and calls the appropriate handler function.
// In this minimalist example, we handle all methods through `handleRequest`.
if (php_sapi_name() !== 'cli') { // Prevent execution if run from command line unless intended.
    handleRequest();
}

?>
// --- Example Usage ---

// To use this API Gateway:
// 1. Save the code above as `api_gateway.php` (or any other name).
// 2. Configure `BACKEND_API_URL` to point to your actual backend API.
// 3. Configure a valid username and password in the `authenticateUser` function
//    (or replace it with your actual authentication logic).
// 4. You'll need a backend API running at the `BACKEND_API_URL`. For testing,
//    you can use PHP's built-in web server:
//    - Create a simple `backend.php` file (example below).
//    - Run it: `php -S localhost:8000`
// 5. Access your gateway via a web browser or tools like `curl`.

// --- Example Backend API (backend.php) ---
/*
<?php
// This is a simple example backend API for testing the gateway.
// Save this as `backend.php` and run with `php -S localhost:8000`

header('Content-Type: application/json');

$path = $_SERVER['REQUEST_URI'];
$method = $_SERVER['REQUEST_METHOD'];

echo json_encode([
    'message' => 'Hello from the backend API!',
    'method' => $method,
    'path' => $path,
    'timestamp' => date('Y-m-d H:i:s')
]);
?>
*/

// --- Example `curl` Commands to Test the Gateway ---

// Assuming your gateway is at http://localhost/api_gateway.php

// 1. Without authentication (will result in 401 Unauthorized)
// curl http://localhost/api_gateway.php/some/endpoint

// 2. With correct authentication
// curl -u admin:supersecretpassword http://localhost/api_gateway.php/some/endpoint

// 3. With incorrect password
// curl -u admin:wrongpassword http://localhost/api_gateway.php/some/endpoint

// 4. Testing a POST request
// curl -X POST -u admin:supersecretpassword -d '{"key": "value"}' http://localhost/api_gateway.php/post/resource

// Remember to replace `http://localhost/api_gateway.php` with the actual URL to your gateway script.