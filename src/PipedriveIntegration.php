<?php

require __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

class PipedriveIntegration {
    private $apiToken;
    private $domain;
    private $baseUrl;
    
    // Custom field IDs
    const HOUSING_TYPE_FIELD = '35c4e320a6dee7094535c0fe65fd9e748754a171';
    const PROPERTY_SIZE_FIELD = '533158ca6c8a97cc1207b273d5802bd4a074f887';
    const DEAL_TYPE_FIELD = '761dd27362225e433e1011b3bd4389a48ae4a412';
    const CONTACT_TYPE_FIELD = 'c0b071d74d13386af76f5681194fd8cd793e6020';
    
    // Option mappings
    private $housingTypeOptions = [
        'Enebolig' => 30,
        'Leilighet' => 31,
        'Tomannsbolig' => 32,
        'Rekkehus' => 33,
        'Hytte' => 34,
        'Annet' => 35
    ];
    
    private $dealTypeOptions = [
        'Alle stromavtaler er aktuelle' => 42,
        'Fastpris' => 43,
        'Spotpris' => 44,
        'Kraftforvaltning' => 45,
        'Annen avtale/vet ikke' => 46
    ];
    
    private $contactTypeOptions = [
        'Privat' => 27,
        'Borettslag' => 28,
        'Bedrift' => 29
    ];

    public function __construct($apiToken, $domain = 'nettbureaucase') {
        $this->apiToken = $apiToken;
        $this->domain = $domain;
        $this->baseUrl = "https://{$domain}.pipedrive.com/api/v1/";
    }

    /**
     * Create organization, person and deal in Pipedrive
     */
    public function createLead($data) {
        try {
            // Log start
            $this->log("Starting lead creation for: " . ($data['name'] ?? 'Unknown'));
            
            // Create organization
            $orgId = $this->createOrganization($data);
            $this->log("Organization created with ID: $orgId");
            
            // Create person
            $personId = $this->createPerson($data, $orgId);
            $this->log("Person created with ID: $personId");
            
            // Create deal
            $dealId = $this->createDeal($data, $orgId, $personId);
            $this->log("Deal created with ID: $dealId");
            
            return [
                'organization_id' => $orgId,
                'person_id' => $personId,
                'deal_id' => $dealId
            ];
            
        } catch (Exception $e) {
            $this->log("Error: " . $e->getMessage(), 'ERROR');
            throw $e;
        }
    }

    /**
     * Create organization in Pipedrive
     */
    private function createOrganization($data) {
        // Use name for organization or generate one if not available
        $orgName = $data['name'] ?? 'Unknown Organization';
        
        $orgData = [
            'name' => $orgName,
            'visible_to' => 3 // Visible to everyone
        ];
        
        $response = $this->makeApiCall('organizations', $orgData);
        
        if (isset($response['data']['id'])) {
            return $response['data']['id'];
        } else {
            throw new Exception("Failed to create organization: " . 
                               ($response['error'] ?? 'Unknown error'));
        }
    }

    /**
     * Create person in Pipedrive
     */
    private function createPerson($data, $orgId) {
        $personData = [
            'name' => $data['name'] ?? '',
            'email' => $data['email'] ?? '',
            'phone' => $data['phone'] ?? '',
            'org_id' => $orgId,
            'visible_to' => 3
        ];
        
        // Add custom fields if available
        if (!empty($data['contact_type'])) {
            $optionId = $this->contactTypeOptions[$data['contact_type']] ?? null;
            if ($optionId) {
                $personData[self::CONTACT_TYPE_FIELD] = $optionId;
            }
        }
        
        $response = $this->makeApiCall('persons', $personData);
        
        if (isset($response['data']['id'])) {
            return $response['data']['id'];
        } else {
            throw new Exception("Failed to create person: " . 
                               ($response['error'] ?? 'Unknown error'));
        }
    }

    /**
     * Create deal (lead) in Pipedrive
     */
    private function createDeal($data, $orgId, $personId) {
        $title = "Lead: " . ($data['name'] ?? 'New Lead');
        
        $dealData = [
            'title' => $title,
            'org_id' => $orgId,
            'person_id' => $personId,
            'visible_to' => 3
        ];
        
        // Add custom fields if available
        if (!empty($data['housing_type'])) {
            $optionId = $this->housingTypeOptions[$data['housing_type']] ?? null;
            if ($optionId) {
                $dealData[self::HOUSING_TYPE_FIELD] = $optionId;
            }
        }
        
        if (!empty($data['property_size'])) {
            $dealData[self::PROPERTY_SIZE_FIELD] = (int)$data['property_size'];
        }
        
        if (!empty($data['deal_type'])) {
            $optionId = $this->dealTypeOptions[$data['deal_type']] ?? null;
            if ($optionId) {
                $dealData[self::DEAL_TYPE_FIELD] = $optionId;
            }
        }
        
        $response = $this->makeApiCall('deals', $dealData);
        
        if (isset($response['data']['id'])) {
            return $response['data']['id'];
        } else {
            throw new Exception("Failed to create deal: " . 
                               ($response['error'] ?? 'Unknown error'));
        }
    }

    /**
     * Make API call to Pipedrive
     */
    private function makeApiCall($endpoint, $data) {
        $url = $this->baseUrl . $endpoint . '?api_token=' . $this->apiToken;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json'
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($response === false) {
            throw new Exception("CURL error: " . curl_error($ch));
        }
        
        $decodedResponse = json_decode($response, true);
        
        if ($httpCode >= 400) {
            throw new Exception("API error ($httpCode): " . 
                               ($decodedResponse['error'] ?? 'Unknown error'));
        }
        
        return $decodedResponse;
    }

    /**
     * Simple logging function
     */
    private function log($message, $level = 'INFO') {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp] [$level] $message" . PHP_EOL;
        
        // Log to file if possible, otherwise to stdout
        if (is_writable('logs')) {
            file_put_contents('logs/pipedrive.log', $logMessage, FILE_APPEND | LOCK_EX);
        } else {
            echo $logMessage;
        }
    }
}

// Example usage if script is run directly
if (php_sapi_name() === 'cli' && isset($argv[0]) && basename($argv[0]) === 'PipedriveIntegration.php') {
    $apiToken = $_ENV['PIPEDRIVE_API_TOKEN'] ?? getenv('PIPEDRIVE_API_TOKEN');
    $domain   = $_ENV['PIPEDRIVE_DOMAIN'] ?? getenv('PIPEDRIVE_DOMAIN');

    $integration = new PipedriveIntegration($apiToken, $domain);
    
    // Example data
    $data = [
        "name" => "Ola Nordmann",
        "phone" => "12345678",
        "email" => "ola.nordmannn@online.no",
        "housing_type" => "Enebolig",
        "property_size" => 160,
        "deal_type" => "Spotpris",
        "contact_type" => "Privat"
    ];
    
    try {
        $result = $integration->createLead($data);
        echo "Success! Created:\n";
        echo "Organization ID: " . $result['organization_id'] . "\n";
        echo "Person ID: " . $result['person_id'] . "\n";
        echo "Deal ID: " . $result['deal_id'] . "\n";
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
        exit(1);
    }
}
