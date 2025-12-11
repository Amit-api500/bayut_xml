<?php
/****************************************************
 * generate_bayut_feed_for_keen.php - FIXED VERSION
 ****************************************************/

error_reporting(E_ALL);
ini_set('display_errors', 1);

// 1. CONFIG
$bitrixWebhook = 'https://deckeentest.bitrix24.com/rest/28/mt4e19bqnnwy9tb2/';
$entityTypeId  = 1048;

$outputFile = __DIR__ . '/bayut_feed.xml';

echo "__DIR__ = " . __DIR__ . "<br>\n";
echo "Output file path = " . $outputFile . "<br>\n";

$secret = '023xyz1abc4AedfxYzdata';
if (!isset($_GET['key']) || $_GET['key'] !== $secret) {
    http_response_code(403);
    echo "Forbidden (wrong or missing key)";
    exit;
}

/**
 * ENUM LISTS - FIXED TO MATCH BAYUT SPEC
 */

// Property Purpose
$ENUM_ufCrm12_1764827986849 = [
    ["ID" => "90", "VALUE" => "Buy"],
    ["ID" => "92", "VALUE" => "Rent"],
];

// Property Status
$ENUM_ufCrm12_1764826963001 = [
    ["ID" => "86", "VALUE" => "live"],
    ["ID" => "88", "VALUE" => "deleted"],
];

// Property Type
$ENUM_ufCrm12_1764828482981 = [
    ["ID" => "94",  "VALUE" => "Villa"],
    ["ID" => "96",  "VALUE" => "Apartment"],
    ["ID" => "98",  "VALUE" => "Office"],
    ["ID" => "100", "VALUE" => "Shop"],
    ["ID" => "102", "VALUE" => "Warehouse"],
    ["ID" => "104", "VALUE" => "Factory"],
    ["ID" => "106", "VALUE" => "Labour Camp"],
    ["ID" => "108", "VALUE" => "Other Commercial"],
    ["ID" => "110", "VALUE" => "Commercial Building"],
    ["ID" => "112", "VALUE" => "Residential Floor"],
    ["ID" => "114", "VALUE" => "Commercial Floor"],
    ["ID" => "116", "VALUE" => "Residential Land"],
    ["ID" => "118", "VALUE" => "Commercial Land"],
    ["ID" => "120", "VALUE" => "Townhouse"],
    ["ID" => "122", "VALUE" => "Residential Building"],
    ["ID" => "124", "VALUE" => "Hotel Apartment"],
    ["ID" => "126", "VALUE" => "Loft"],
    ["ID" => "128", "VALUE" => "Pent House"],
];

// Rent Frequency - FIXED: Must match PDF values exactly
$ENUM_ufCrm12_1764829836005 = [
    ["ID" => "136", "VALUE" => "Daily"],
    ["ID" => "138", "VALUE" => "Weekly"],
    ["ID" => "140", "VALUE" => "Monthly"],
    ["ID" => "142", "VALUE" => "Yearly"],  // Changed from "Annually"
];

// Furnished
$ENUM_ufCrm12_1764828761224 = [
    ["ID" => "130", "VALUE" => "Yes"],
    ["ID" => "132", "VALUE" => "No"],
    ["ID" => "134", "VALUE" => "Partly"],
];

// Helper function to map enum ID → VALUE
function mapEnumSingle($value, $enumList)
{
    if ($value === '' || $value === null) return $value;

    foreach ($enumList as $item) {
        if ($item['ID'] == $value) {
            return $item['VALUE'];
        }
    }
    return $value;
}

// Bitrix REST
function callBitrix($method, $params = [])
{
    global $bitrixWebhook;
    $url = $bitrixWebhook . $method;
    $ch  = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($params),
        CURLOPT_TIMEOUT        => 30,
    ]);
    $result = curl_exec($ch);
    if ($result === false) {
        throw new Exception('Curl error: ' . curl_error($ch));
    }
    curl_close($ch);
    $data = json_decode($result, true);

    if (isset($data['error'])) {
        throw new Exception('Bitrix error: ' . $data['error_description']);
    }
    return $data;
}

// Fetch all items
function getAllProperties($entityTypeId)
{
    $start  = 0;
    $result = [];

    do {
        $response = callBitrix('crm.item.list', [
            'entityTypeId' => $entityTypeId,
            'select'       => [
                'id','title','updatedTime','sourceId','sourceDescription','opportunity',
                'ufCrm12_1764731249567', // Property Ref No
                'ufCrm12_1764731269142', // Permit Number
                'ufCrm12_1764827986849', // Property Purpose
                'ufCrm12_1764828482981', // Property Type
                'ufCrm12_1764826963001', // Listing Status
                'ufCrm12_1764731450860', // Bedrooms
                'ufCrm12_1764731465420', // Bathrooms
                'ufCrm12_1764731487021', // Size Sqft
                'ufCrm12_1764731499860', // Price (if you want custom price)
                'ufCrm12_1764828761224', // Furnished
                'ufCrm12_1764829836005', // Rent Frequency
                'ufCrm12_1764829663833', // Features
                'ufCrm12_1764731604732', // City
                'ufCrm12_1764731622291', // Locality
                'ufCrm12_1764731636516', // Sub-Locality
                'ufCrm12_1764731651427', // Tower Name
                'ufCrm12_1764731672612', // Agent Name
                'ufCrm12_1764731691787', // Agent Phone
                'ufCrm12_1764731708675', // Agent Email
                'ufCrm12_1764829919892', // Video Link
                'ufCrm12_1764829999837', // Floor Plans Img
                'ufCrm12_1764842555615', // Images
                'ufCrm12_1764843649888', // Property Description
                'ufCrm12_1764842808080', // Property Title ARABIC
                'ufCrm12_1764842880446', // Property Description ARABIC
            ],
            'start' => $start,
        ]);

        if (!empty($response['result']['items'])) {
            $result = array_merge($result, $response['result']['items']);
        }

        $start = $response['next'] ?? false;

    } while ($start !== false);

    return $result;
}

// Generate XML
function generateBayutXml($properties, $outputFile)
{
    global
        $ENUM_ufCrm12_1764827986849,
        $ENUM_ufCrm12_1764826963001,
        $ENUM_ufCrm12_1764828482981,
        $ENUM_ufCrm12_1764829836005,
        $ENUM_ufCrm12_1764828761224;

    $xml = new DOMDocument('1.0', 'UTF-8');
    $xml->formatOutput = true;

    $root = $xml->createElement('Properties');
    $xml->appendChild($root);

    // Safe helper for CDATA nodes
    $addNode = function($parent, $tag, $value) use ($xml) {
        if ($value === null) {
            $value = '';
        }
        if (is_array($value)) {
            $value = implode(', ', $value);
        }
        $value = (string)$value;

        $node  = $xml->createElement($tag);
        $cdata = $xml->createCDATASection($value);
        $node->appendChild($cdata);
        $parent->appendChild($node);
    };

    foreach ($properties as $item) {

        $p = $xml->createElement('Property');

        // Basic fields
        $id          = $item['id'] ?? '';
        $title       = $item['title'] ?? '';
        $description = $item['ufCrm12_1764843649888'] ?? '';

        // Ref - fallback to id if empty
        $ref = $item['ufCrm12_1764731249567'] ?? $id;

        // Status (ENUM) → live / deleted
        $statusId = $item['ufCrm12_1764826963001'] ?? '';
        $status   = $statusId !== '' ? mapEnumSingle($statusId, $ENUM_ufCrm12_1764826963001) : 'live';

        // Purpose (ENUM) → Buy / Rent
        $purposeId = $item['ufCrm12_1764827986849'] ?? '';
        $purpose   = mapEnumSingle($purposeId, $ENUM_ufCrm12_1764827986849);

        // Type (ENUM) → Apartment, Villa, ...
        $typeId = $item['ufCrm12_1764828482981'] ?? '';
        $type   = mapEnumSingle($typeId, $ENUM_ufCrm12_1764828482981);

        $city       = $item['ufCrm12_1764731604732'] ?? '';
        $locality   = $item['ufCrm12_1764731622291'] ?? '';
        $subLoc     = $item['ufCrm12_1764731636516'] ?? '';
        $tower      = $item['ufCrm12_1764731651427'] ?? '';
        $beds       = $item['ufCrm12_1764731450860'] ?? '';
        $baths      = $item['ufCrm12_1764731465420'] ?? '';
        $size       = $item['ufCrm12_1764731487021'] ?? '';

        // Using opportunity as price
        $price      = $item['opportunity'] ?? '';

        // Rent Frequency (ENUM) → Daily, Weekly, Monthly, Yearly
        $rentFreqId = $item['ufCrm12_1764829836005'] ?? '';
        $rentFreq   = mapEnumSingle($rentFreqId, $ENUM_ufCrm12_1764829836005);

        $features   = $item['ufCrm12_1764829663833'] ?? '';

        // Furnished (ENUM) → Yes / No / Partly
        $furnishedId = $item['ufCrm12_1764828761224'] ?? '';
        $furnished   = mapEnumSingle($furnishedId, $ENUM_ufCrm12_1764828761224);

        $agentName   = $item['ufCrm12_1764731672612'] ?? '';
        $agentPhone  = $item['ufCrm12_1764731691787'] ?? '';
        $agentEmail  = $item['ufCrm12_1764731708675'] ?? '';
        $permit      = $item['ufCrm12_1764731269142'] ?? '';
        $videolink   = $item['ufCrm12_1764829919892'] ?? '';
        $floorPlans  = $item['ufCrm12_1764829999837'] ?? '';
        $images      = $item['ufCrm12_1764842555615'] ?? '';
        $description_ae = $item['ufCrm12_1764842880446'] ?? '';
        $pro_title_ae   = $item['ufCrm12_1764842808080'] ?? '';

        // Updated date
        $updatedRaw = $item['updatedTime'] ?? null;
        if ($updatedRaw) {
            try {
                $updated = (new DateTime($updatedRaw))->format('Y-m-d H:i:s');
            } catch (Exception $e) {
                $updated = date('Y-m-d H:i:s');
            }
        } else {
            $updated = date('Y-m-d H:i:s');
        }

        // === BAYUT XML STRUCTURE (Following PDF Guidelines) ===
        
        // 1. Property_Ref_No (REQUIRED - unique identifier)
        $addNode($p, 'Property_Ref_No', $ref);
        
        // 2. Permit_Number (if available)
        if ($permit !== '') {
            $addNode($p, 'Permit_Number', $permit);
        }
        
        // 3. Property_Status (live or deleted)
        $addNode($p, 'Property_Status', $status);
        
        // 4. Property_purpose (Buy or Rent - REQUIRED)
        if ($purpose !== '') {
            $addNode($p, 'Property_purpose', $purpose);
        }
        
        // 5. Property_Type (REQUIRED)
        if ($type !== '') {
            $addNode($p, 'Property_Type', $type);
        }
        
        // 6. Property_Size & Property_Size_Unit
        if ($size !== '' && $size > 0) {
            $addNode($p, 'Property_Size', $size);
            $addNode($p, 'Property_Size_Unit', 'SQFT');
        }
        
        // 7. Bedrooms (use -1 for studio as per PDF)
        if ($beds !== '') {
            $addNode($p, 'Bedrooms', $beds);
        }
        
        // 8. Bathrooms
        if ($baths !== '') {
            $addNode($p, 'Bathrooms', $baths);
        }
        
        // 9. Features (with child <Feature> tags if multiple)
        if (!empty($features)) {
            $featuresNode = $xml->createElement('Features');
            
            if (is_array($features)) {
                foreach ($features as $feat) {
                    $featValue = is_array($feat) ? ($feat['value'] ?? $feat['VALUE'] ?? '') : $feat;
                    if ($featValue !== '') {
                        $featureEl = $xml->createElement('Feature');
                        $featureEl->appendChild($xml->createCDATASection((string)$featValue));
                        $featuresNode->appendChild($featureEl);
                    }
                }
            } else {
                $featArray = preg_split('/[,\n]+/', $features);
                foreach ($featArray as $feat) {
                    $feat = trim($feat);
                    if ($feat !== '') {
                        $featureEl = $xml->createElement('Feature');
                        $featureEl->appendChild($xml->createCDATASection($feat));
                        $featuresNode->appendChild($featureEl);
                    }
                }
            }
            
            if ($featuresNode->hasChildNodes()) {
                $p->appendChild($featuresNode);
            }
        }
        
        // 10. Off_Plan (Yes or No)
        $addNode($p, 'Off_Plan', 'No');
        
        // 11. Portals (REQUIRED - Bayut and/or dubizzle)
        $portals = $xml->createElement('Portals');
        
        $portalBayut = $xml->createElement('Portal');
        $portalBayut->appendChild($xml->createCDATASection('Bayut'));
        $portals->appendChild($portalBayut);
        
        $portalDubiz = $xml->createElement('Portal');
        $portalDubiz->appendChild($xml->createCDATASection('dubizzle'));
        $portals->appendChild($portalDubiz);
        
        $p->appendChild($portals);
        
        // 12. Last_Updated (YYYY-MM-DD HH:MM:SS format)
        $addNode($p, 'Last_Updated', $updated);
        
        // 13. Property_Title
        if ($title !== '') {
            $addNode($p, 'Property_Title', $title);
        }
        
        // 14. Property_Description
        if ($description !== '') {
            $addNode($p, 'Property_Description', $description);
        }
        
        // 15. Property_Title_AR (Arabic title)
        if ($pro_title_ae !== '') {
            $addNode($p, 'Property_Title_AR', $pro_title_ae);
        }
        
        // 16. Property_Description_AR (Arabic description)
        if ($description_ae !== '') {
            $addNode($p, 'Property_Description_AR', $description_ae);
        }
        
        // 17. Price (REQUIRED)
        if ($price !== '' && $price > 0) {
            $addNode($p, 'Price', $price);
        }
        
        // 18. Rent_Frequency (ONLY for Rent listings)
        // PDF sample shows lowercase: "monthly"
        if (!empty($purpose) && strtolower($purpose) === 'rent' && $rentFreq !== '') {
            $addNode($p, 'Rent_Frequency', strtolower($rentFreq));
        }
        
        // 19. Furnished (Yes, No, or Partly)
        if ($furnished !== '') {
            $addNode($p, 'Furnished', $furnished);
        }
        
        // === IMAGES & VIDEOS SECTION ===
        
        // 20. Images (with child <Image> tags)
        if (!empty($images)) {
            $imagesNode = $xml->createElement('Images');
            $hasImages = false;
            
            if (is_array($images)) {
                foreach ($images as $img) {
                    if (is_array($img)) {
                        $url = $img['url'] ?? $img['downloadUrl'] ?? $img['value'] ?? null;
                    } else {
                        $url = $img;
                    }
                    
                    if ($url !== null && $url !== '') {
                        $imgEl = $xml->createElement('Image');
                        $imgEl->appendChild($xml->createCDATASection((string)$url));
                        $imagesNode->appendChild($imgEl);
                        $hasImages = true;
                    }
                }
            } else {
                $url = trim((string)$images);
                if ($url !== '') {
                    $imgEl = $xml->createElement('Image');
                    $imgEl->appendChild($xml->createCDATASection($url));
                    $imagesNode->appendChild($imgEl);
                    $hasImages = true;
                }
            }
            
            if ($hasImages) {
                $p->appendChild($imagesNode);
            }
        }
        
        // 21. Videos (with child <Video> tags)
        if (!empty($videolink)) {
            $videosNode = $xml->createElement('Videos');
            $hasVideos = false;
            
            if (is_array($videolink)) {
                foreach ($videolink as $v) {
                    $url = is_array($v) ? ($v['url'] ?? $v['value'] ?? null) : $v;
                    if ($url) {
                        $videoEl = $xml->createElement('Video');
                        $videoEl->appendChild($xml->createCDATASection((string)$url));
                        $videosNode->appendChild($videoEl);
                        $hasVideos = true;
                    }
                }
            } else {
                $url = trim((string)$videolink);
                if ($url !== '') {
                    $videoEl = $xml->createElement('Video');
                    $videoEl->appendChild($xml->createCDATASection($url));
                    $videosNode->appendChild($videoEl);
                    $hasVideos = true;
                }
            }
            
            if ($hasVideos) {
                $p->appendChild($videosNode);
            }
        }
        
        // 22. Floor_Plans (with child <Floor_Plan> tags)
        if (!empty($floorPlans)) {
            $fpNode = $xml->createElement('Floor_Plans');
            $hasPlans = false;
            
            if (is_array($floorPlans)) {
                foreach ($floorPlans as $fp) {
                    $url = is_array($fp) ? ($fp['url'] ?? $fp['downloadUrl'] ?? $fp['value'] ?? null) : $fp;
                    if ($url) {
                        $planEl = $xml->createElement('Floor_Plan');
                        $planEl->appendChild($xml->createCDATASection((string)$url));
                        $fpNode->appendChild($planEl);
                        $hasPlans = true;
                    }
                }
            } else {
                $url = trim((string)$floorPlans);
                if ($url !== '') {
                    $planEl = $xml->createElement('Floor_Plan');
                    $planEl->appendChild($xml->createCDATASection($url));
                    $fpNode->appendChild($planEl);
                    $hasPlans = true;
                }
            }
            
            if ($hasPlans) {
                $p->appendChild($fpNode);
            }
        }
        
        // === LOCATION SECTION ===
        
        // 23. City (Emirate)
        if ($city !== '') {
            $addNode($p, 'City', $city);
        }
        
        // 24. Locality (main locality)
        if ($locality !== '') {
            $addNode($p, 'Locality', $locality);
        }
        
        // 25. Sub_Locality
        if ($subLoc !== '') {
            $addNode($p, 'Sub_Locality', $subLoc);
        }
        
        // 26. Tower_Name (building name)
        if ($tower !== '') {
            $addNode($p, 'Tower_Name', $tower);
        }
        
        // === CONTACT INFO SECTION ===
        
        // 27. Listing_Agent
        if ($agentName !== '') {
            $addNode($p, 'Listing_Agent', $agentName);
        }
        
        // 28. Listing_Agent_Phone
        if ($agentPhone !== '') {
            $addNode($p, 'Listing_Agent_Phone', $agentPhone);
        }
        
        // 29. Listing_Agent_Email
        if ($agentEmail !== '') {
            $addNode($p, 'Listing_Agent_Email', $agentEmail);
        }

        // Append property to root
        $root->appendChild($p);
    }

    $bytes = $xml->save($outputFile);
    clearstatcache();

    echo "Bytes written: " . $bytes . "<br>\n";
    echo "Output file: <a href='" . basename($outputFile) . "'>" . basename($outputFile) . "</a><br>\n";
}

// MAIN
try {
    $properties = getAllProperties($entityTypeId);

    echo "Items from Bitrix: " . count($properties) . "<br>\n";

    if (empty($properties)) {
        echo "WARNING: No properties found in Bitrix!<br>\n";
    }

    generateBayutXml($properties, $outputFile);

    echo "DONE. <a href='" . basename($outputFile) . "' target='_blank'>Download XML Feed</a><br>\n";

} catch (Exception $e) {
    http_response_code(500);
    echo "Error: " . $e->getMessage() . "<br>\n";
    echo "Stack trace: <pre>" . $e->getTraceAsString() . "</pre>";
}