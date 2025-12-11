<?php
/****************************************************
 * test_feed_access.php
 * Tests if your Bayut feed is publicly accessible
 ****************************************************/

error_reporting(E_ALL);
ini_set('display_errors', 1);

$secret = '023xyz1abc4AedfxYzdata';
if (!isset($_GET['key']) || $_GET['key'] !== $secret) {
    http_response_code(403);
    echo "Forbidden";
    exit;
}

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Feed Access Test</title>";
echo "<style>
    body { font-family: Arial; max-width: 900px; margin: 20px auto; padding: 20px; background: #f5f5f5; }
    .container { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
    h1 { color: #2c3e50; border-bottom: 3px solid #3498db; padding-bottom: 10px; }
    .success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 15px; border-radius: 5px; margin: 10px 0; }
    .error { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 15px; border-radius: 5px; margin: 10px 0; }
    .info { background: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460; padding: 15px; border-radius: 5px; margin: 10px 0; }
    .warning { background: #fff3cd; border: 1px solid #ffeaa7; color: #856404; padding: 15px; border-radius: 5px; margin: 10px 0; }
    code { background: #f4f4f4; padding: 2px 8px; border-radius: 3px; font-family: monospace; }
    .test-item { padding: 10px; margin: 10px 0; border-left: 4px solid #3498db; background: #f9f9f9; }
    .test-pass { border-left-color: #27ae60; }
    .test-fail { border-left-color: #e74c3c; }
</style></head><body><div class='container'>";

echo "<h1> - Bayut Feed Accessibility Test</h1>";

// Build feed URL
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$path = rtrim(dirname($_SERVER['REQUEST_URI']), '/');
$feedUrl = $protocol . '://' . $host . $path . '/generate_bayut_feed_for_keen_7.php?key=' . $secret;

echo "<div class='info'><strong>Testing Feed URL:</strong><br><code>$feedUrl</code></div>";

// Test 1: Local file exists
echo "<h2>Test Results</h2>";
$xmlFile = __DIR__ . '/bayut_feed.xml';
$localExists = file_exists($xmlFile);

echo "<div class='test-item " . ($localExists ? "test-pass" : "test-fail") . "'>";
echo "<strong>1. Local XML File</strong><br>";
if ($localExists) {
    echo "File exists at: " . $xmlFile . "<br>";
    echo "File size: " . number_format(filesize($xmlFile)) . " bytes<br>";
    echo "Last modified: " . date('Y-m-d H:i:s', filemtime($xmlFile));
} else {
    echo " File not found at: " . $xmlFile;
}
echo "</div>";

// Test 2: Try to fetch via HTTP (simulate Bayut accessing it)
echo "<div class='test-item'>";
echo "<strong>2. HTTP Accessibility Test</strong><br>";

$ch = curl_init($feedUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_TIMEOUT => 10,
    CURLOPT_HEADER => true,
    CURLOPT_NOBODY => false,
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($httpCode === 200) {
    echo " Feed is publicly accessible<br>";
    echo "HTTP Status: <code>$httpCode OK</code><br>";
    
    // Check if it's valid XML
    if (strpos($response, '<?xml') !== false && strpos($response, '<Properties>') !== false) {
        echo " Response contains valid XML structure";
    } else {
        echo " Response doesn't look like XML. Check your feed generator.";
    }
} else {
    echo " Feed is NOT accessible<br>";
    echo "HTTP Status: <code>$httpCode</code><br>";
    if ($error) {
        echo "Error: <code>$error</code><br>";
    }
    echo "<br><strong>Possible Issues:</strong><br>";
    echo "• Your site is on localhost (not accessible from internet)<br>";
    echo "• Firewall blocking external access<br>";
    echo "• .htaccess rules blocking the URL<br>";
    echo "• PHP script has errors";
}
echo "</div>";

// Test 3: Check if localhost
echo "<div class='test-item'>";
echo "<strong>3. Server Environment</strong><br>";

$isLocalhost = in_array($host, ['localhost', '127.0.0.1', '::1']) || strpos($host, 'localhost') !== false;

if ($isLocalhost) {
    echo " <strong>WARNING:</strong> Running on localhost<br>";
    echo "Your feed is currently only accessible from your local machine.<br>";
    echo "<strong>Action needed:</strong> Deploy to a public server before sharing with Bayut.<br>";
    echo "<br><strong>Options:</strong><br>";
    echo "• Upload to your live hosting server<br>";
    echo "• Use a service like ngrok for testing<br>";
    echo "• Deploy to cloud hosting (AWS, DigitalOcean, etc.)";
} else {
    echo " Running on: <code>$host</code><br>";
    echo "This appears to be a public server.";
}
echo "</div>";

// Test 4: XML Validation
if ($localExists) {
    echo "<div class='test-item'>";
    echo "<strong>4. XML Structure Validation</strong><br>";
    
    libxml_use_internal_errors(true);
    $xml = simplexml_load_file($xmlFile);
    
    if ($xml !== false) {
        echo " XML is well-formed<br>";
        
        $propertyCount = isset($xml->Property) ? count($xml->Property) : 0;
        echo "Properties found: <code>$propertyCount</code><br>";
        
        if ($propertyCount > 0) {
            echo " Feed contains property data<br>";
            
            // Quick check first property
            $firstProp = $xml->Property[0];
            $hasRef = !empty((string)$firstProp->Property_Ref_No);
            $hasPurpose = !empty((string)$firstProp->Property_purpose);
            $hasType = !empty((string)$firstProp->Property_Type);
            $hasPrice = !empty((string)$firstProp->Price);
            
            if ($hasRef && $hasPurpose && $hasType && $hasPrice) {
                echo " First property has all required fields";
            } else {
                echo " First property is missing some required fields<br>";
                echo "Run the full validator for details.";
            }
        } else {
            echo " No properties in feed";
        }
    } else {
        echo " XML parsing failed<br>";
        foreach (libxml_get_errors() as $error) {
            echo "Error: " . htmlspecialchars($error->message) . "<br>";
        }
    }
    libxml_clear_errors();
    echo "</div>";
}

// Final Summary
echo "<h2>Summary & Next Steps</h2>";

$allPassed = $localExists && $httpCode === 200 && !$isLocalhost;

if ($allPassed) {
    echo "<div class='success'>";
    echo "<strong> All Tests Passed!</strong><br><br>";
    echo "<strong>Your feed is ready to share with Bayut:</strong><br>";
    echo "1. Share this URL with Bayut support: <br>";
    echo "<code>$feedUrl</code><br><br>";
    echo "2. Run the full validator to check data quality:<br>";
    echo "<a href='validate_bayut_feed.php?key=$secret' target='_blank'>Open Full Validator</a><br><br>";
    echo "3. Bayut will configure their system to sync from your feed<br>";
    echo "4. Updates typically sync every 1-24 hours";
    echo "</div>";
} else {
    echo "<div class='error'>";
    echo "<strong> Action Required</strong><br><br>";
    
    if ($isLocalhost) {
        echo " <strong>Deploy to Public Server:</strong><br>";
        echo "Your feed must be accessible from the internet. Deploy to:<br>";
        echo "• Your live hosting provider<br>";
        echo "• Cloud hosting (AWS, DigitalOcean, Heroku)<br>";
        echo "• Shared hosting (cPanel, Plesk)<br><br>";
    }
    
    if (!$localExists) {
        echo " <strong>Generate XML Feed:</strong><br>";
        echo "Run your feed generator first:<br>";
        echo "<a href='generate_bayut_feed_for_keen_7.php.php?key=$secret' target='_blank'>Generate Feed Now</a><br><br>";
    }
    
    if ($httpCode !== 200 && !$isLocalhost) {
        echo " <strong>Fix HTTP Access:</strong><br>";
        echo "• Check .htaccess rules<br>";
        echo "• Verify file permissions<br>";
        echo "• Check PHP errors in logs<br><br>";
    }
    
    echo "After fixing issues, run this test again.";
    echo "</div>";
}

// Additional Tools
echo "<h2> Additional Tools</h2>";
echo "<div class='info'>";
echo "<strong>Available Tools:</strong><br>";
echo "• <a href='generate_bayut_feed_for_keen_7.php?key=$secret' target='_blank'>Generate Feed</a> - Create/update XML from Bitrix<br>";
echo "• <a href='validate_bayut_feed.php?key=$secret' target='_blank'>Full Validator</a> - Comprehensive data validation<br>";
echo "• <a href='bayut_feed.xml' target='_blank'>View Raw XML</a> - See generated XML file<br>";
echo "• <a href='test_feed_access.php?key=$secret' target='_blank'>Refresh This Test</a> - Re-run accessibility test";
echo "</div>";

echo "</div></body></html>";
?>