<?php
// Test the customers and suppliers APIs directly
echo "=== TESTING CUSTOMERS API ===\n";

// Test customer creation
$customerData = [
    'name' => 'Test Customer',
    'email' => 'test@example.com',
    'phone' => '123-456-7890',
    'address' => '123 Test St',
    'credit_limit' => 1000
];

$jsonData = json_encode($customerData);

// Use cURL to test the API endpoint
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "http://localhost/Inventory-Flow/api/customers.php");
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Content-Length: ' . strlen($jsonData)
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);

curl_close($ch);

echo "Customer API HTTP Code: $httpCode\n";
echo "Customer API Response: $response\n";

if ($error) {
    echo "Customer API cURL Error: $error\n";
}

// If successful, try to delete the test customer
if ($httpCode == 200) {
    $responseData = json_decode($response, true);
    if ($responseData && isset($responseData['id'])) {
        echo "Cleaning up test customer...\n";
        $deleteData = json_encode(['id' => $responseData['id']]);
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "http://localhost/Inventory-Flow/api/customers.php");
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $deleteData);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($deleteData)
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        $deleteResponse = curl_exec($ch);
        $deleteHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        echo "Delete Customer HTTP Code: $deleteHttpCode\n";
        echo "Delete Customer Response: $deleteResponse\n";
    }
}

echo "\n=== TESTING SUPPLIERS API ===\n";

// Test supplier creation
$supplierData = [
    'name' => 'Test Supplier',
    'contact_person' => 'John Doe',
    'email' => 'john@example.com',
    'phone' => '098-765-4321',
    'address' => '456 Test Ave',
    'notes' => 'Test notes'
];

$jsonData = json_encode($supplierData);

// Use cURL to test the API endpoint
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "http://localhost/Inventory-Flow/api/suppliers.php");
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Content-Length: ' . strlen($jsonData)
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);

curl_close($ch);

echo "Supplier API HTTP Code: $httpCode\n";
echo "Supplier API Response: $response\n";

if ($error) {
    echo "Supplier API cURL Error: $error\n";
}

// If successful, try to delete the test supplier
if ($httpCode == 200) {
    $responseData = json_decode($response, true);
    if ($responseData && isset($responseData['id'])) {
        echo "Cleaning up test supplier...\n";
        $deleteData = json_encode(['id' => $responseData['id']]);
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "http://localhost/Inventory-Flow/api/suppliers.php");
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $deleteData);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($deleteData)
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        $deleteResponse = curl_exec($ch);
        $deleteHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        echo "Delete Supplier HTTP Code: $deleteHttpCode\n";
        echo "Delete Supplier Response: $deleteResponse\n";
    }
}
?>