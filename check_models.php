<?php
// check_models.php
header('Content-Type: application/json');

$apiKey = getenv('GEMINI_API_KEY');
if (!$apiKey) {
    echo "API Key missing!";
    exit;
}

// Ask Google to list all available models for your key
$apiUrl = "https://generativelanguage.googleapis.com/v1/models?key=" . $apiKey;

$ch = curl_init($apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Bypass XAMPP SSL issues

$response = curl_exec($ch);
curl_close($ch);

$data = json_decode($response, true);

echo "<h3>Models your API Key has access to:</h3><ul>";
if (isset($data['models'])) {
    foreach ($data['models'] as $model) {
        // Only show models that support text generation
        if (in_array('generateContent', $model['supportedGenerationMethods'])) {
            echo "<li><b>" . str_replace('models/', '', $model['name']) . "</b></li>";
        }
    }
} else {
    echo "<li>Error fetching models: " . htmlspecialchars($response) . "</li>";
}
echo "</ul>";
?>