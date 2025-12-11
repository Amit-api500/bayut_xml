<?php
/****************************************************
 * generate_bayut_to_bm_feed_for_keen.php
 ****************************************************/

error_reporting(E_ALL);
ini_set('display_errors', 1);

/**
 * 1. CONFIG
 */
$bitrixWebhook = 'https://benchmark.bitrix24.com/rest/78/n5py7lleglvr2rn5/';

// Smart Process Automation entityTypeId for your property SPA
// NOTE: 1048 must exist on benchmark.bitrix24.com as a Smart Process.
// If you get "Smart Process Automation was not found", use debug_types=1
// in the URL to see actual types and adjust this.
$entityTypeId  = 1042;

$outputFile = __DIR__ . '/bayutToBenchmark.xml';

// Simple security key
$secret = '02av123aveabcdxyz1230987gana';
if (!isset($_GET['key']) || $_GET['key'] !== $secret) {
    http_response_code(403);
    echo "Forbidden (wrong or missing key)";
    exit;
}

// Now it's safe to output debug info
echo "__DIR__ = " . __DIR__ . "<br>\n";
echo "Output file path = " . $outputFile . "<br>\n";


/**
 * ENUM LISTS (from your JSON)
 */

// Property Purpose
$ENUM_ufCrm10_1764836634820 = [
    ["ID" => "282", "VALUE" => "Buy"],
    ["ID" => "284", "VALUE" => "Rent"],
];

// Property Status
$ENUM_ufCrm10_1764837276903 = [
    ["ID" => "300", "VALUE" => "live"],
    ["ID" => "302", "VALUE" => "deleted"],
];

// Property Type
$ENUM_ufCrm10_1764836584095 = [
    ["ID" => "246",  "VALUE" => "Villa"],
    ["ID" => "248",  "VALUE" => "Apartment"],
    ["ID" => "250",  "VALUE" => "Office"],
    ["ID" => "252", "VALUE" => "Shop"],
    ["ID" => "254", "VALUE" => "Warehouse"],
    ["ID" => "256", "VALUE" => "Factory"],
    ["ID" => "258", "VALUE" => "Labour Camp"],
    ["ID" => "260", "VALUE" => "Other Commercial"],
    ["ID" => "262", "VALUE" => "Commercial Building"],
    ["ID" => "264", "VALUE" => "Residential Floor"],
    ["ID" => "266", "VALUE" => "Commercial Floor"],
    ["ID" => "268", "VALUE" => "Residential Land"],
    ["ID" => "270", "VALUE" => "Commercial Land"],
    ["ID" => "272", "VALUE" => "Townhouse"],
    ["ID" => "274", "VALUE" => "Residential Building"],
    ["ID" => "276", "VALUE" => "Hotel Apartment"],
    ["ID" => "278", "VALUE" => "Loft"],
    ["ID" => "280", "VALUE" => "Pent House"],
];

// Rent Frequency
$ENUM_ufCrm10_1764836827219 = [
    ["ID" => "286", "VALUE" => "Monthly"],
    ["ID" => "288", "VALUE" => "Weekly"],
    ["ID" => "290", "VALUE" => "Quarterly"],
    ["ID" => "292", "VALUE" => "Annually"],
];

// Furnished
$ENUM_ufCrm10_1764836862764 = [
    ["ID" => "294", "VALUE" => "Yes"],
    ["ID" => "296", "VALUE" => "No"],
    ["ID" => "298", "VALUE" => "Partly"],
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
    ]);
    $result = curl_exec($ch);
    if ($result === false) {
        throw new Exception('Curl error: ' . curl_error($ch));
    }
    curl_close($ch);
    $data = json_decode($result, true);

    if (isset($data['error'])) {
        // e.g. "Smart Process Automation was not found" etc.
        $msg = isset($data['error_description']) ? $data['error_description'] : $data['error'];
        throw new Exception('Bitrix error: ' . $msg);
    }
    return $data;
}

/**
 * OPTIONAL: DEBUG TYPES
 * If you open this script with &debug_types=1, it will list all CRM types
 * and exit. Example:
 *   https://yourdomain.com/generate_bayut_to_bm_feed_for_keen.php?key=02av&debug_types=1
 */
if (isset($_GET['debug_types']) && $_GET['debug_types'] == '1') {
    try {
        $types = callBitrix('crm.type.list');
        echo "<h3>CRM Types (Smart Processes)</h3><pre>";
        print_r($types);
        echo "</pre>";
    } catch (Exception $e) {
        echo "Error while listing types: " . $e->getMessage();
    }
    // Stop here in debug mode
    exit;
}

// Fetch all items from SPA
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
                'ufCrm10_1764836401221', // Permit Number
                'ufCrm10_1764836634820', // Property Purpose
                'ufCrm10_1764836584095', // Property Type
                'ufCrm10_1764837276903', // Property(Listing) Status
                'ufCrm10_1764836704563', // Bedrooms
                'ufCrm10_1764836725371', // Bathrooms
                'ufCrm10_1764836739003', // Size Sqft
                'ufCrm10_1764836752835', // Price (if you want custom price)
                'ufCrm10_1764836862764', // Furnished
                'ufCrm10_1764836827219', // Rent Frequency
                'ufCrm10_1764837029304', // Features
                'ufCrm10_1764836879258', // City
                'ufCrm10_1764836893212', // Locality
                'ufCrm10_1764836906299', // Sub-Locality
                'ufCrm10_1764836920426', // Tower Name
                'ufCrm10_1764836935603', // Agent Name
                'ufCrm10_1764836978337', // Agent Phone
                'ufCrm10_1764837003081', // Agent Email
                'ufCrm10_1764837036680', // Video Link
                'ufCrm10_1764837048930', // Floor Plans Img
                'ufCrm10_1765457577109', // Images
                'ufCrm10_1764853602028', // Property Description
                'ufCrm10_1764853568788', // Property Title ARABIC
                'ufCrm10_1764853585100', // Property Description ARABIC
                'ufCrm10_1765459768741', // Title
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
        $ENUM_ufCrm10_1764836634820,
        $ENUM_ufCrm10_1764837276903,
        $ENUM_ufCrm10_1764836584095,
        $ENUM_ufCrm10_1764836827219,
        $ENUM_ufCrm10_1764836862764;

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
        $description = $item['ufCrm10_1764853602028'] ?? '';

        // Ref - you had fallback but now forced to id:
        // $ref = $item['ufCrm12_1764731249567'] ?? $id;
        $ref = $id;

        // Status (ENUM) → live / deleted
        $statusId = $item['ufCrm10_1764837276903'] ?? '';
        $status   = $statusId !== '' ? mapEnumSingle($statusId, $ENUM_ufCrm10_1764837276903) : 'live';

        // Purpose (ENUM) → Buy / Rent
        $purposeId = $item['ufCrm10_1764836634820'] ?? '';
        $purpose   = mapEnumSingle($purposeId, $ENUM_ufCrm10_1764836634820);

        // Type (ENUM) → Apartment, Villa, ...
        $typeId = $item['ufCrm10_1764836584095'] ?? '';
        $type   = mapEnumSingle($typeId, $ENUM_ufCrm10_1764836584095);

        $city       = $item['ufCrm10_1764836879258'] ?? '';
        $locality   = $item['ufCrm10_1764836893212'] ?? '';
        $subLoc     = $item['ufCrm10_1764836906299'] ?? '';
        $tower      = $item['ufCrm10_1764836920426'] ?? '';
        $beds       = $item['ufCrm10_1764836704563'] ?? '';
        $baths      = $item['ufCrm10_1764836725371'] ?? '';
        $size       = $item['ufCrm10_1764836739003'] ?? '';

        // You can choose which price to use: custom or opportunity
        // $price      = $item['ufCrm10_1764836752835'] ?? '';
        $price      = $item['opportunity'] ?? '';

        // Rent Frequency (ENUM) → Monthly / Weekly / ...
        $rentFreqId = $item['ufCrm10_1764836827219'] ?? '';
        $rentFreq   = mapEnumSingle($rentFreqId, $ENUM_ufCrm10_1764836827219);

        $features   = $item['ufCrm10_1764837029304'] ?? '';

        // Furnished (ENUM) → Yes / No / Partly
        $furnishedId = $item['ufCrm10_1764836862764'] ?? '';
        $furnished   = mapEnumSingle($furnishedId, $ENUM_ufCrm10_1764836862764);

        $agentName   = $item['ufCrm10_1764836935603'] ?? '';
        $agentPhone  = $item['ufCrm10_1764836978337'] ?? '';
        $agentEmail  = $item['ufCrm10_1764837003081'] ?? '';
        $permit      = $item['ufCrm10_1764836401221'] ?? '';
        $videolink   = $item['ufCrm10_1764837036680'] ?? '';
        $floorPlans  = $item['ufCrm10_1764837048930'] ?? '';
        $images      = $item['ufCrm10_1764853541621'] ?? '';
        $description_ae = $item['ufCrm10_1764853585100'] ?? '';
        $pro_title_ae   = $item['ufCrm10_1764853568788'] ?? '';
        $title_eng   = $item['ufCrm10_1765459768741'] ?? '';

        // Updated date
        $updatedRaw = $item['updatedTime'] ?? null;
        if ($updatedRaw) {
            $updated = (new DateTime($updatedRaw))->format('Y-m-d H:i:s');
        } else {
            $updated = date('Y-m-d H:i:s');
        }

        // BAYUT TAGS
        $addNode($p, 'Property_Ref_No', $ref);
        $addNode($p, 'Property_Status', $status);
        $addNode($p, 'Property_purpose', $purpose);
        $addNode($p, 'Property_Type', $type);

        if ($features !== '')  $addNode($p, 'Features', $features);

        // Videos
        if (!empty($videolink)) {
            $videosNode = $xml->createElement('Videos');

            if (is_array($videolink)) {
                foreach ($videolink as $v) {
                    $url = is_array($v) ? ($v['url'] ?? $v['value'] ?? null) : $v;
                    if ($url) {
                        $videoEl = $xml->createElement('Video');
                        $videoEl->appendChild($xml->createCDATASection((string)$url));
                        $videosNode->appendChild($videoEl);
                    }
                }
            } else {
                $url = trim((string)$videolink);
                if ($url !== '') {
                    $videoEl = $xml->createElement('Video');
                    $videoEl->appendChild($xml->createCDATASection($url));
                    $videosNode->appendChild($videoEl);
                }
            }

            $p->appendChild($videosNode);
        }

        // Floor Plans
        if (!empty($floorPlans)) {
            $fpNode = $xml->createElement('Floor_Plans');

            if (is_array($floorPlans)) {
                foreach ($floorPlans as $fp) {
                    $url = is_array($fp) ? ($fp['url'] ?? $fp['value'] ?? null) : $fp;
                    if ($url) {
                        $planEl = $xml->createElement('Floor_Plan');
                        $planEl->appendChild($xml->createCDATASection((string)$url));
                        $fpNode->appendChild($planEl);
                    }
                }
            } else {
                $url = trim((string)$floorPlans);
                if ($url !== '') {
                    $planEl = $xml->createElement('Floor_Plan');
                    $planEl->appendChild($xml->createCDATASection($url));
                    $fpNode->appendChild($planEl);
                }
            }

            $p->appendChild($fpNode);
        }

        // Titles & Descriptions
        if ($title !== '')  $addNode($p, 'Property_Title', $title);
        $addNode($p, 'Property_Description', $description);

        if ($description_ae !== '')  $addNode($p, 'Property_Description_AR', $description_ae);
        if ($pro_title_ae !== '')    $addNode($p, 'Property_Title_AR', $pro_title_ae);
        if ($title_eng !== '')    $addNode($p, 'Property_Title', $title_eng);

        // Location
        $addNode($p, 'City', $city);
        $addNode($p, 'Locality', $locality);
        $addNode($p, 'Sub_Locality', $subLoc);
        $addNode($p, 'Tower_Name', $tower);

        // Images
        $imagesNode = $xml->createElement('Images');

        if (is_array($images)) {
            foreach ($images as $img) {
                if (is_array($img)) {
                    $url = $img['url'] ?? $img['value'] ?? null;
                } else {
                    $url = $img;
                }

                if ($url !== null && $url !== '') {
                    $imgEl  = $xml->createElement('Image');
                    $cdata  = $xml->createCDATASection((string)$url);
                    $imgEl->appendChild($cdata);
                    $imagesNode->appendChild($imgEl);
                }
            }
        } else {
            $url = trim((string)$images);
            if ($url !== '') {
                $imgEl = $xml->createElement('Image');
                $imgEl->appendChild($xml->createCDATASection($url));
                $imagesNode->appendChild($imgEl);
            }
        }

        $p->appendChild($imagesNode);

        // Size
        if ($size !== '') {
            $addNode($p, 'Property_Size', $size);
            $addNode($p, 'Property_Size_Unit', 'SQFT');
        }

        // Beds & Baths
        if ($beds !== '')  $addNode($p, 'Bedrooms', $beds);
        if ($baths !== '') $addNode($p, 'Bathrooms', $baths);

        // Price
        if ($price !== '') $addNode($p, 'Price', $price);

        // Only for rent listings, and we send lowercase rent frequency
        if (strtolower($purpose) === 'rent' && $rentFreq !== '') {
            $addNode($p, 'Rent_Frequency', strtolower($rentFreq));
        }

        // Furnished
        if ($furnished !== '') {
            $addNode($p, 'Furnished', $furnished);
        }

        // Off_plan (you set No by default)
        $addNode($p, 'Off_Plan', 'No');

        // Permit
        if ($permit !== '') {
            $addNode($p, 'Permit_Number', $permit);
        }

        // Last Updated
        $addNode($p, 'Last_Updated', $updated);

        // Agent info
        if ($agentName !== '')  $addNode($p, 'Listing_Agent', $agentName);
        if ($agentPhone !== '') $addNode($p, 'Listing_Agent_Phone', $agentPhone);
        if ($agentEmail !== '') $addNode($p, 'Listing_Agent_Email', $agentEmail);

        // Portals (Bayut + dubizzle)
        $portals = $xml->createElement('Portals');

        $portalBayut = $xml->createElement('Portal');
        $portalBayut->appendChild($xml->createCDATASection('Bayut'));
        $portals->appendChild($portalBayut);

        $portalDubiz = $xml->createElement('Portal');
        $portalDubiz->appendChild($xml->createCDATASection('dubizzle'));
        $portals->appendChild($portalDubiz);

        $p->appendChild($portals);

        // Append property to root
        $root->appendChild($p);
    }

    $bytes = $xml->save($outputFile);
    clearstatcache();

    echo "Bytes written: " . $bytes . "<br>\n";
}

// MAIN
try {
    global $outputFile, $entityTypeId;

    $properties = getAllProperties($entityTypeId);

    echo "Items from Bitrix (entityTypeId={$entityTypeId}): " . count($properties) . "<br>\n";

    generateBayutXml($properties, $outputFile);

    echo "DONE.<br>\n";

} catch (Exception $e) {
    // Avoid http_response_code(500) after echo to prevent header warnings
    echo "Error: " . $e->getMessage();
}

?>
