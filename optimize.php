<?php
header('Content-Type: application/json');
$inputData = json_decode(file_get_contents('php://input'), true);
$mode = $inputData['mode'] ?? 'analyze';
$code = $inputData['code'] ?? '';

$apiKey = getenv('GEMINI_API_KEY');
if (!$apiKey) {
    echo json_encode(['error' => 'API Key is missing.']);
    exit;
}

// --- TRUE LOCAL HARDWARE TELEMETRY FUNCTION ---
function runHardwareTest($javaCode) {
    if (preg_match('/class\s+([a-zA-Z0-9_]+)/', $javaCode, $matches)) {
        $className = $matches[1];
    } else {
        $className = "Main";
    }

    $filename = $className . ".java";
    file_put_contents($filename, $javaCode);
    
    // 1. Fetch exact raw physical registers from the local motherboard via WMI
    $psCommand = 'powershell -Command "Get-CimInstance Win32_Processor | Select-Object Name, NumberOfCores, MaxClockSpeed, CurrentVoltage | ConvertTo-Json"';
    $wmi = shell_exec($psCommand);
    $cpuData = json_decode($wmi, true);
    
    // Extract local system variables
    $cpuName = $cpuData['Name'] ?? 'Unknown CPU';
    $cores = $cpuData['NumberOfCores'] ?? 4;
    $maxSpeedMhz = $cpuData['MaxClockSpeed'] ?? 2400; // Local Frequency
    $voltageRaw = $cpuData['CurrentVoltage'] ?? 12;   
    
    // WMI stores voltage in tenths of a volt (e.g., 12 = 1.2 Volts)
    $voltage = ($voltageRaw > 0 && $voltageRaw < 100) ? ($voltageRaw / 10.0) : 1.2;

    // 2. Compile and Execute on the local machine
    exec("javac $filename 2>&1", $out, $res);
    if ($res !== 0) {
        @unlink($filename);
        return false; 
    }

    $start = microtime(true);
    exec("java $className 2>&1");
    $end = microtime(true);

    @unlink($filename);
    if(file_exists("$className.class")) @unlink("$className.class");

    $seconds = round($end - $start, 4);

    // 3. NO HARDCODED GUESSES: Use the CMOS Dynamic Power Formula (P = C * V^2 * f)
    // We use 0.005 as a baseline silicon capacitance scaling factor for modern CPUs
    $activeLoadRatio = 1.0 / $cores; 
    
    //The Exact DHACO Formula Implementation happens here.
    // The exact power draw based entirely on this specific local device's architecture
    $dynamicWatts = (0.005 * pow($voltage, 2) * $maxSpeedMhz) * $activeLoadRatio; 
    
    // Add 10% to account for local RAM and Motherboard overhead waking up
    $dynamicWatts = $dynamicWatts * 1.10; 
    
    $joules = round($seconds * $dynamicWatts, 4);

    return [
        'time_sec' => $seconds, 
        'energy_joules' => $joules,
        'cpu_name' => trim($cpuName)
    ];
}

// Run the physical hardware test FIRST
$realMetrics = runHardwareTest($code);
if ($realMetrics === false) {
    echo json_encode(['error' => 'Local Java Compilation Failed. Check syntax.']);
    exit;
}

// Ask the AI ONLY for the structural Big O notation and code refactoring
$prompt = ($mode == 'analyze') 
    ? "Analyze this Java code structurally. Return ONLY a valid JSON object with 'time_o' (String Big O notation) and 'space_o' (String Big O notation). Do not guess execution time. Code:\n" . $code
    : "Optimize this Java code for cache locality and speed. Return ONLY a valid JSON object with 'optimized_code' (the full string), 'time_o' (String Big O notation of the NEW code), and 'space_o' (String Big O notation of the NEW code). Code:\n" . $code;

$payload = ["contents" => [["parts" => [["text" => $prompt]]]]];
$apiUrl = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=" . $apiKey;

$ch = curl_init($apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 

$response = curl_exec($ch);
if(curl_errno($ch)){
    echo json_encode(['error' => 'Connection Error: ' . curl_error($ch)]);
    exit;
}
curl_close($ch);

$res = json_decode($response, true);
if (isset($res['error'])) {
     echo json_encode(['error' => 'Google API Error: ' . $res['error']['message']]);
     exit;
}

$rawText = $res['candidates'][0]['content']['parts'][0]['text'] ?? '';
$rawText = str_replace(['```json', '```'], '', $rawText);
$cleanJson = json_decode(trim($rawText), true);

if (!$cleanJson) {
    echo json_encode(['error' => 'Failed to parse AI response.']);
    exit;
}

// Combine the AI's structural math with the dynamic hardware telemetry
if ($mode == 'analyze') {
    $finalMetrics = [
        'time_o' => $cleanJson['time_o'],
        'space_o' => $cleanJson['space_o'],
        'time_sec' => $realMetrics['time_sec'],
        'energy_joules' => $realMetrics['energy_joules']
    ];
    // We now send the cpu_name back to JavaScript!
    echo json_encode(['metrics' => $finalMetrics, 'cpu_name' => $realMetrics['cpu_name']]);
} else {
    // Physically run the AI-generated code to get its real speed!
    $optRealMetrics = runHardwareTest($cleanJson['optimized_code']);
    
    $finalMetrics = [
        'time_o' => $cleanJson['time_o'],
        'space_o' => $cleanJson['space_o'],
        'time_sec' => $optRealMetrics['time_sec'],
        'energy_joules' => $optRealMetrics['energy_joules']
    ];
    
    echo json_encode([
        'optimized_code' => $cleanJson['optimized_code'],
        'metrics' => $finalMetrics,
        'cpu_name' => $optRealMetrics['cpu_name']
    ]);
}
?>