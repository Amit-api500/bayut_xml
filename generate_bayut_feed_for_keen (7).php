<?php
/****************************************************
 * generate_bayut_feed_for_keen.php
 ****************************************************/

error_reporting(E_ALL);
ini_set('display_errors', 1);

// 1. CONFIG
$bitrixWebhook = 'https://deckeentest.bitrix24.com/rest/12/of03i832fttl9ta3/';
$entityTypeId  = 1048;

$outputFile = __DIR__ . '/bayut.xml';

echo "__DIR__ = " . __DIR__ . "<br>\n";
echo "Output file path = " . $outputFile . "<br>\n";

$secret = '023xyz1abc4AedfxYzdata';
if (!isset($_GET['key']) || $_GET['key'] !== $secret) {
    http_response_code(403);
    echo "Forbidden (wrong or missing key)";
    exit;
}

/**
 * ENUM LISTS (from your JSON)
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

// Rent Frequency
$ENUM_ufCrm12_1764829836005 = [
    ["ID" => "136", "VALUE" => "Monthly"],
    ["ID" => "138", "VALUE" => "Weekly"],
    ["ID" => "140", "VALUE" => "Quarterly"],
    ["ID" => "142", "VALUE" => "Annually"],
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
            // If array accidentally passed, implode to avoid fatal
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

        // You can choose which price to use: custom or opportunity
        // $price      = $item['ufCrm12_1764731499860'] ?? '';
        $price      = $item['opportunity'] ?? '';

        // Rent Frequency (ENUM) → Monthly / Weekly / ...
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

        // Location
        $addNode($p, 'City', $city);
        $addNode($p, 'Locality', $locality);
        $addNode($p, 'Sub_Locality', $subLoc);
        $addNode($p, 'Tower_Name', $tower);

        // Images (proper Bayut structure)
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

    echo "Items from Bitrix: " . count($properties) . "<br>\n";

    generateBayutXml($properties, $outputFile);

    echo "DONE.<br>\n";

} catch (Exception $e) {
    http_response_code(500);
    echo "Error: " . $e->getMessage();
}
