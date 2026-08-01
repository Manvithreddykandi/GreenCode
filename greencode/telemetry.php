<?php
// telemetry.php
header('Content-Type: application/json');

$inputData = json_decode(file_get_contents('php://input'), true);
$javaCode = $inputData['code'] ?? '';

// Save the submitted code to a temporary Windows file
$filename = "EnergyTest.java";
file_put_contents($filename, $javaCode);

// Compile Java on Windows
// --- OLD CODE ---
// $filename = "EnergyTest.java";
// file_put_contents($filename, $javaCode);

// --- NEW FIX ---
// Use Regex to dynamically find the class name the user typed
if (preg_match('/class\s+([a-zA-Z0-9_]+)/', $javaCode, $matches)) {
    $className = $matches[1];
} else {
    $className = "Main"; // Fallback name
}

$filename = $className . ".java";
file_put_contents($filename, $javaCode);

// Compile Java on Windows
exec("javac $filename 2>&1", $compileOutput, $compileResult);

// Run Java on Windows
exec("java $className 2>&1", $runOutput, $runResult);

// Clean up files dynamically
unlink($filename);
if(file_exists("$className.class")) unlink("$className.class");

// Assume the Java code prints its execution time as the last line
$time = end($runOutput); 

echo json_encode([
    'success' => true,
    'time' => $time
]);
?>