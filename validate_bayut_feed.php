<?php
/****************************************************
 * validate_bayut_feed.php
 * Comprehensive validator for Bayut XML feeds
 ****************************************************/

error_reporting(E_ALL);
ini_set('display_errors', 1);

$xmlFile = __DIR__ . '/bayut_feed.xml';

// Optional: Add security key
$secret = '023xyz1abc4AedfxYzdata';
if (!isset($_GET['key']) || $_GET['key'] !== $secret) {
    http_response_code(403);
    echo "Forbidden (wrong or missing key)";
    exit;
}

// CSS for better display
echo <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Bayut XML Feed Validator</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            max-width: 1200px; 
            margin: 20px auto; 
            padding: 0 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 { 
            color: #2c3e50; 
            border-bottom: 3px solid #3498db;
            padding-bottom: 10px;
        }
        h2 { 
            color: #34495e; 
            margin-top: 30px;
            border-left: 4px solid #3498db;
            padding-left: 10px;
        }
        h3 {
            color: #555;
            margin-top: 20px;
        }
        .success { 
            background: #d4edda; 
            border: 1px solid #c3e6cb; 
            color: #155724; 
            padding: 15px; 
            border-radius: 5px; 
            margin: 10px 0;
        }
        .error { 
            background: #f8d7da; 
            border: 1px solid #f5c6cb; 
            color: #721c24; 
            padding: 15px; 
            border-radius: 5px; 
            margin: 10px 0;
        }
        .warning { 
            background: #fff3cd; 
            border: 1px solid #ffeaa7; 
            color: #856404; 
            padding: 15px; 
            border-radius: 5px; 
            margin: 10px 0;
        }
        .info { 
            background: #d1ecf1; 
            border: 1px solid #bee5eb; 
            color: #0c5460; 
            padding: 15px; 
            border-radius: 5px; 
            margin: 10px 0;
        }
        .property-box {
            background: #f9f9f9;
            border: 1px solid #ddd;
            padding: 15px;
            margin: 15px 0;
            border-radius: 5px;
        }
        .property-title {
            font-weight: bold;
            color: #2c3e50;
            font-size: 16px;
            margin-bottom: 10px;
        }
        .field-error {
            color: #e74c3c;
            margin-left: 20px;
            font-size: 14px;
        }
        .field-warning {
            color: #f39c12;
            margin-left: 20px;
            font-size: 14px;
        }
        .field-ok {
            color: #27ae60;
            margin-left: 20px;
            font-size: 14px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background-color: #3498db;
            color: white;
            font-weight: bold;
        }
        tr:hover {
            background-color: #f5f5f5;
        }
        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: bold;
        }
        .badge-success { background: #27ae60; color: white; }
        .badge-error { background: #e74c3c; color: white; }
        .badge-warning { background: #f39c12; color: white; }
        .summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }
        .summary-card {
            background: white;
            border: 2px solid #3498db;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
        }
        .summary-card h3 {
            margin: 0 0 10px 0;
            color: #3498db;
        }
        .summary-card .number {
            font-size: 36px;
            font-weight: bold;
            color: #2c3e50;
        }
        code {
            background: #f4f4f4;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
        }
        .xml-sample {
            background: #2c3e50;
            color: #ecf0f1;
            padding: 15px;
            border-radius: 5px;
            overflow-x: auto;
            margin: 10px 0;
        }
    </style>
</head>
<body>
<div class="container">
HTML;

class BayutValidator {
    
    private $errors = [];
    private $warnings = [];
    private $propertyErrors = [];
    private $stats = [
        'total' => 0,
        'valid' => 0,
        'with_errors' => 0,
        'with_warnings' => 0
    ];
    
    // Bayut allowed values
    private $allowedPurpose = ['Buy', 'Rent'];
    private $allowedStatus = ['live', 'deleted'];
    private $allowedPropertyTypes = [
        'Villa', 'Apartment', 'Office', 'Shop', 'Warehouse', 'Factory',
        'Labour Camp', 'Other Commercial', 'Commercial Building',
        'Residential Floor', 'Commercial Floor', 'Residential Land',
        'Commercial Land', 'Townhouse', 'Residential Building',
        'Hotel Apartment', 'Loft', 'Pent House', 'Duplex', 'Loft Apartment'
    ];
    private $allowedRentFreq = ['daily', 'weekly', 'monthly', 'yearly'];
    private $allowedFurnished = ['Yes', 'No', 'Partly'];
    private $allowedBedrooms = ['-1', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10', '10+'];
    private $allowedBathrooms = ['1', '2', '3', '4', '5', '6', '7', '8', '9', '10'];
    private $allowedPortals = ['Bayut', 'bayut', 'dubizzle'];
    
    public function validateFile($xmlFile) {
        
        if (!file_exists($xmlFile)) {
            $this->errors[] = "XML file not found: $xmlFile";
            return false;
        }
        
        // Check if file is readable
        if (!is_readable($xmlFile)) {
            $this->errors[] = "XML file is not readable: $xmlFile";
            return false;
        }
        
        // Check file size
        $fileSize = filesize($xmlFile);
        if ($fileSize === 0) {
            $this->errors[] = "XML file is empty (0 bytes)";
            return false;
        }
        
        // Load XML
        libxml_use_internal_errors(true);
        $xml = simplexml_load_file($xmlFile);
        
        if ($xml === false) {
            $this->errors[] = "Failed to parse XML file. Errors:";
            foreach (libxml_get_errors() as $error) {
                $this->errors[] = "Line {$error->line}: {$error->message}";
            }
            libxml_clear_errors();
            return false;
        }
        
        // Validate structure
        $this->validateStructure($xml);
        
        // Validate each property
        if (isset($xml->Property)) {
            foreach ($xml->Property as $index => $property) {
                $this->validateProperty($property, (int)$index + 1);
            }
        }
        
        return true;
    }
    
    private function validateStructure($xml) {
        
        // Check root element
        if ($xml->getName() !== 'Properties') {
            $this->errors[] = "Root element must be &lt;Properties&gt;, found: &lt;{$xml->getName()}&gt;";
        }
        
        // Check if has properties
        if (!isset($xml->Property) || count($xml->Property) === 0) {
            $this->errors[] = "No &lt;Property&gt; elements found in the feed";
        } else {
            $this->stats['total'] = count($xml->Property);
        }
    }
    
    private function validateProperty($property, $index) {
        
        $propErrors = [];
        $propWarnings = [];
        $propRef = (string)$property->Property_Ref_No ?: "Property #$index";
        
        // === REQUIRED FIELDS ===
        
        // 1. Property_Ref_No (REQUIRED)
        if (empty((string)$property->Property_Ref_No)) {
            $propErrors[] = "Missing REQUIRED field: Property_Ref_No";
        }
        
        // 2. Property_purpose (REQUIRED)
        $purpose = (string)$property->Property_purpose;
        if (empty($purpose)) {
            $propErrors[] = "Missing REQUIRED field: Property_purpose (must be 'Buy' or 'Rent')";
        } elseif (!in_array($purpose, $this->allowedPurpose)) {
            $propErrors[] = "Invalid Property_purpose: '$purpose'. Allowed: " . implode(', ', $this->allowedPurpose);
        }
        
        // 3. Property_Type (REQUIRED)
        $type = (string)$property->Property_Type;
        if (empty($type)) {
            $propErrors[] = "Missing REQUIRED field: Property_Type";
        } elseif (!in_array($type, $this->allowedPropertyTypes)) {
            $propErrors[] = "Invalid Property_Type: '$type'. Check Bayut documentation for allowed types.";
        }
        
        // 4. Price (REQUIRED)
        $price = (string)$property->Price;
        if (empty($price)) {
            $propErrors[] = "Missing REQUIRED field: Price";
        } elseif (!is_numeric($price) || $price <= 0) {
            $propErrors[] = "Invalid Price: '$price' (must be numeric and greater than 0)";
        }
        
        // 5. Portals (REQUIRED)
        if (!isset($property->Portals) || !isset($property->Portals->Portal)) {
            $propErrors[] = "Missing REQUIRED field: Portals (must have at least one Portal)";
        } else {
            $portals = [];
            foreach ($property->Portals->Portal as $portal) {
                $portalValue = (string)$portal;
                $portals[] = $portalValue;
                if (!in_array($portalValue, $this->allowedPortals)) {
                    $propErrors[] = "Invalid Portal value: '$portalValue'. Allowed: Bayut, dubizzle";
                }
            }
            if (empty($portals)) {
                $propErrors[] = "Portals element exists but has no Portal children";
            }
        }
        
        // === VALIDATION FOR SPECIFIC VALUES ===
        
        // Property_Status
        $status = (string)$property->Property_Status;
        if (!empty($status) && !in_array($status, $this->allowedStatus)) {
            $propErrors[] = "Invalid Property_Status: '$status'. Allowed: live, deleted";
        }
        
        // Rent_Frequency (required for Rent purpose)
        if (strtolower($purpose) === 'rent') {
            $rentFreq = (string)$property->Rent_Frequency;
            if (empty($rentFreq)) {
                $propWarnings[] = "Rent_Frequency is recommended for Rent listings";
            } elseif (!in_array(strtolower($rentFreq), $this->allowedRentFreq)) {
                $propErrors[] = "Invalid Rent_Frequency: '$rentFreq'. Allowed: daily, weekly, monthly, yearly (lowercase)";
            }
        }
        
        // Furnished
        $furnished = (string)$property->Furnished;
        if (!empty($furnished) && !in_array($furnished, $this->allowedFurnished)) {
            $propErrors[] = "Invalid Furnished value: '$furnished'. Allowed: Yes, No, Partly";
        }
        
        // Bedrooms
        $bedrooms = (string)$property->Bedrooms;
        if (!empty($bedrooms) && !in_array($bedrooms, $this->allowedBedrooms)) {
            $propWarnings[] = "Unusual Bedrooms value: '$bedrooms'. Typical values: -1 (studio), 1-10, 10+";
        }
        
        // Bathrooms
        $bathrooms = (string)$property->Bathrooms;
        if (!empty($bathrooms) && !in_array($bathrooms, $this->allowedBathrooms)) {
            $propWarnings[] = "Unusual Bathrooms value: '$bathrooms'. Typical values: 1-10";
        }
        
        // Property_Size
        $size = (string)$property->Property_Size;
        if (!empty($size) && (!is_numeric($size) || $size <= 0)) {
            $propErrors[] = "Invalid Property_Size: '$size' (must be numeric and positive)";
        }
        
        // Property_Size_Unit
        if (!empty($size) && empty((string)$property->Property_Size_Unit)) {
            $propWarnings[] = "Property_Size_Unit missing (should be 'SQFT' when size is provided)";
        }
        
        // === CHECK FOR RECOMMENDED FIELDS ===
        
        if (empty((string)$property->Property_Title)) {
            $propWarnings[] = "Property_Title is empty (recommended for better visibility)";
        }
        
        if (empty((string)$property->Property_Description)) {
            $propWarnings[] = "Property_Description is empty (recommended for better engagement)";
        }
        
        if (empty((string)$property->City)) {
            $propWarnings[] = "City is empty (location data is important)";
        }
        
        if (empty((string)$property->Locality)) {
            $propWarnings[] = "Locality is empty (location data is important)";
        }
        
        // Check for images
        if (!isset($property->Images) || !isset($property->Images->Image)) {
            $propWarnings[] = "No images provided (listings without images perform poorly)";
        } else {
            $imageCount = count($property->Images->Image);
            if ($imageCount < 3) {
                $propWarnings[] = "Only $imageCount image(s) provided (recommended: 5+ images)";
            }
            
            // Validate image URLs
            foreach ($property->Images->Image as $img) {
                $url = (string)$img;
                if (!filter_var($url, FILTER_VALIDATE_URL)) {
                    $propErrors[] = "Invalid image URL: '$url'";
                }
            }
        }
        
        // Check contact info
        if (empty((string)$property->Listing_Agent)) {
            $propWarnings[] = "Listing_Agent name is empty";
        }
        if (empty((string)$property->Listing_Agent_Phone)) {
            $propWarnings[] = "Listing_Agent_Phone is empty";
        }
        if (empty((string)$property->Listing_Agent_Email)) {
            $propWarnings[] = "Listing_Agent_Email is empty";
        }
        
        // Last_Updated format check
        $lastUpdated = (string)$property->Last_Updated;
        if (!empty($lastUpdated)) {
            $pattern = '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/';
            if (!preg_match($pattern, $lastUpdated)) {
                $propErrors[] = "Invalid Last_Updated format: '$lastUpdated' (should be YYYY-MM-DD HH:MM:SS)";
            }
        }
        
        // === STORE RESULTS ===
        
        if (!empty($propErrors)) {
            $this->stats['with_errors']++;
            $this->propertyErrors[$propRef] = [
                'errors' => $propErrors,
                'warnings' => $propWarnings
            ];
        } elseif (!empty($propWarnings)) {
            $this->stats['with_warnings']++;
            $this->propertyErrors[$propRef] = [
                'errors' => [],
                'warnings' => $propWarnings
            ];
        } else {
            $this->stats['valid']++;
        }
    }
    
    public function getStats() {
        return $this->stats;
    }
    
    public function getErrors() {
        return $this->errors;
    }
    
    public function getWarnings() {
        return $this->warnings;
    }
    
    public function getPropertyErrors() {
        return $this->propertyErrors;
    }
    
    public function hasErrors() {
        return !empty($this->errors) || $this->stats['with_errors'] > 0;
    }
    
    public function displayReport() {
        
        echo "<h1> Bayut XML Feed Validation Report</h1>";
        
        // File info
        echo "<div class='info'>";
        echo "<strong>Feed File:</strong> " . basename($GLOBALS['xmlFile']) . "<br>";
        echo "<strong>File Size:</strong> " . number_format(filesize($GLOBALS['xmlFile'])) . " bytes<br>";
        echo "<strong>Validation Time:</strong> " . date('Y-m-d H:i:s') . "<br>";
        echo "</div>";
        
        // Overall status
        if (!$this->hasErrors()) {
            echo "<div class='success'>";
            echo "<strong> VALIDATION PASSED!</strong><br>";
            echo "Your XML feed meets all Bayut requirements and is ready to be submitted.";
            echo "</div>";
        } else {
            echo "<div class='error'>";
            echo "<strong> VALIDATION FAILED!</strong><br>";
            echo "Please fix the errors below before submitting to Bayut.";
            echo "</div>";
        }
        
        // Summary cards
        echo "<div class='summary'>";
        echo "<div class='summary-card'>";
        echo "<h3>Total Properties</h3>";
        echo "<div class='number'>{$this->stats['total']}</div>";
        echo "</div>";
        
        echo "<div class='summary-card'>";
        echo "<h3> Valid</h3>";
        echo "<div class='number' style='color: #27ae60;'>{$this->stats['valid']}</div>";
        echo "</div>";
        
        echo "<div class='summary-card'>";
        echo "<h3> With Warnings</h3>";
        echo "<div class='number' style='color: #f39c12;'>{$this->stats['with_warnings']}</div>";
        echo "</div>";
        
        echo "<div class='summary-card'>";
        echo "<h3> With Errors</h3>";
        echo "<div class='number' style='color: #e74c3c;'>{$this->stats['with_errors']}</div>";
        echo "</div>";
        echo "</div>";
        
        // Global errors
        if (!empty($this->errors)) {
            echo "<h2> Critical Errors</h2>";
            echo "<div class='error'>";
            foreach ($this->errors as $error) {
                echo " " . htmlspecialchars($error) . "<br>";
            }
            echo "</div>";
        }
        
        // Property-level errors
        if (!empty($this->propertyErrors)) {
            echo "<h2> Property-Level Issues</h2>";
            
            foreach ($this->propertyErrors as $propRef => $issues) {
                echo "<div class='property-box'>";
                echo "<div class='property-title'> " . htmlspecialchars($propRef) . "</div>";
                
                if (!empty($issues['errors'])) {
                    echo "<strong style='color: #e74c3c;'>Errors (MUST FIX):</strong><br>";
                    foreach ($issues['errors'] as $error) {
                        echo "<div class='field-error'> " . htmlspecialchars($error) . "</div>";
                    }
                }
                
                if (!empty($issues['warnings'])) {
                    echo "<strong style='color: #f39c12;'>Warnings (Recommended):</strong><br>";
                    foreach ($issues['warnings'] as $warning) {
                        echo "<div class='field-warning'> " . htmlspecialchars($warning) . "</div>";
                    }
                }
                
                echo "</div>";
            }
        }
        
        // Next steps
        echo "<h2> Next Steps</h2>";
        
        if ($this->hasErrors()) {
            echo "<div class='warning'>";
            echo "<strong>Action Required:</strong><br>";
            echo "1. Fix all errors marked with  above<br>";
            echo "2. Re-run this validator to confirm fixes<br>";
            echo "3. Consider addressing warnings marked with  for better performance<br>";
            echo "4. Once validation passes, share your feed URL with Bayut";
            echo "</div>";
        } else {
            echo "<div class='success'>";
            echo "<strong> Ready to Submit!</strong><br>";
            echo "1. Share your feed URL with Bayut support team<br>";
            echo "2. Your feed URL should be: <code>" . $this->getFeedUrl() . "</code><br>";
            echo "3. Bayut will configure their system to read from this URL<br>";
            echo "4. Properties will sync automatically (usually hourly)<br>";
            echo "5. Monitor the first sync in your Bayut dashboard";
            echo "</div>";
        }
        
        // Additional recommendations
        echo "<h2> Best Practices</h2>";
        echo "<div class='info'>";
        echo "• <strong>Images:</strong> Include 5-10 high-quality images per property<br>";
        echo "• <strong>Descriptions:</strong> Write detailed, engaging descriptions (200+ words)<br>";
        echo "• <strong>Pricing:</strong> Ensure prices are accurate and competitive<br>";
        echo "• <strong>Updates:</strong> Keep your feed updated (Bayut syncs every 1-24 hours)<br>";
        echo "• <strong>Duplicates:</strong> Ensure Property_Ref_No is unique for each property<br>";
        echo "• <strong>Contact Info:</strong> Provide valid agent details for lead generation";
        echo "</div>";
    }
    
    private function getFeedUrl() {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];
        $path = dirname($_SERVER['REQUEST_URI']);
        return $protocol . '://' . $host . $path . '/generate_bayut_feed_for_keen.php?key=' . $GLOBALS['secret'];
    }
}

// RUN VALIDATION
try {
    
    $validator = new BayutValidator();
    $validator->validateFile($xmlFile);
    $validator->displayReport();
    
} catch (Exception $e) {
    echo "<div class='error'>";
    echo "<strong> Validation Failed:</strong><br>";
    echo htmlspecialchars($e->getMessage());
    echo "</div>";
}

echo "</div></body></html>";
?>