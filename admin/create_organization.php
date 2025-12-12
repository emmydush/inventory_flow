<?php
// Script to create a new organization and assign a user to it
// Usage: php create_organization.php "Organization Name" "organization-slug" "admin_username"

require_once '../config/database.php';

function createOrganization($name, $slug, $description = '') {
    try {
        $database = new Database();
        $conn = $database->getConnection();
        
        if (!$conn) {
            throw new Exception("Database connection failed");
        }
        
        // Check if organization already exists
        $stmt = $conn->prepare("SELECT id FROM organizations WHERE slug = ? OR name = ?");
        $stmt->execute([$slug, $name]);
        $existingOrg = $stmt->fetch();
        
        if ($existingOrg) {
            echo "Organization already exists with ID: " . $existingOrg['id'] . "\n";
            return $existingOrg['id'];
        }
        
        // Create new organization
        $stmt = $conn->prepare("
            INSERT INTO organizations (name, slug, description, status) 
            VALUES (?, ?, ?, 'active')
        ");
        $stmt->execute([$name, $slug, $description]);
        
        $orgId = $conn->lastInsertId();
        echo "Created organization '$name' with ID: $orgId\n";
        
        // Create default departments for the organization
        $departments = [
            ['Sales', 'Sales department'],
            ['Inventory', 'Inventory management department'],
            ['Administration', 'Administrative department']
        ];
        
        foreach ($departments as $dept) {
            $stmt = $conn->prepare("
                INSERT INTO departments (name, description, organization_id) 
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$dept[0], $dept[1], $orgId]);
            echo "  Created department: " . $dept[0] . "\n";
        }
        
        // Create default settings for the organization
        $settings = [
            ['company_name', $name, 'text'],
            ['currency_symbol', '$', 'text'],
            ['tax_rate', '8.5', 'number'],
            ['low_stock_threshold', '10', 'number'],
            ['invoice_prefix', 'INV-', 'text']
        ];
        
        foreach ($settings as $setting) {
            $stmt = $conn->prepare("
                INSERT INTO settings (setting_key, setting_value, setting_type, organization_id) 
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$setting[0], $setting[1], $setting[2], $orgId]);
            echo "  Created setting: " . $setting[0] . "\n";
        }
        
        return $orgId;
        
    } catch (Exception $e) {
        echo "Error creating organization: " . $e->getMessage() . "\n";
        return false;
    }
}

function assignUserToOrganization($username, $orgId) {
    try {
        $database = new Database();
        $conn = $database->getConnection();
        
        if (!$conn) {
            throw new Exception("Database connection failed");
        }
        
        // Check if user exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        if (!$user) {
            echo "User '$username' not found\n";
            return false;
        }
        
        // Assign user to organization
        $stmt = $conn->prepare("
            UPDATE users 
            SET organization_id = ? 
            WHERE username = ?
        ");
        $stmt->execute([$orgId, $username]);
        
        echo "Assigned user '$username' to organization ID: $orgId\n";
        return true;
        
    } catch (Exception $e) {
        echo "Error assigning user to organization: " . $e->getMessage() . "\n";
        return false;
    }
}

function createAdminUser($username, $email, $password, $fullName, $orgId) {
    try {
        $database = new Database();
        $conn = $database->getConnection();
        
        if (!$conn) {
            throw new Exception("Database connection failed");
        }
        
        // Check if user already exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        $existingUser = $stmt->fetch();
        
        if ($existingUser) {
            echo "User already exists with ID: " . $existingUser['id'] . "\n";
            return $existingUser['id'];
        }
        
        // Hash password
        $passwordHash = password_hash($password, PASSWORD_BCRYPT);
        
        // Create admin user for the organization
        $stmt = $conn->prepare("
            INSERT INTO users (username, email, password_hash, full_name, role, status, organization_id) 
            VALUES (?, ?, ?, ?, 'admin', 'active', ?)
        ");
        $stmt->execute([$username, $email, $passwordHash, $fullName, $orgId]);
        
        $userId = $conn->lastInsertId();
        echo "Created admin user '$username' with ID: $userId for organization ID: $orgId\n";
        
        return $userId;
        
    } catch (Exception $e) {
        echo "Error creating admin user: " . $e->getMessage() . "\n";
        return false;
    }
}

// Command line interface
if (php_sapi_name() === 'cli') {
    global $argv;
    
    if (count($argv) < 2) {
        echo "Usage: php create_organization.php <command> [options]\n";
        echo "Commands:\n";
        echo "  create <name> <slug> [description] - Create a new organization\n";
        echo "  assign <username> <org_slug> - Assign a user to an organization\n";
        echo "  create-admin <username> <email> <password> <full_name> <org_slug> - Create admin user for organization\n";
        echo "\nExamples:\n";
        echo "  php create_organization.php create \"Acme Corporation\" \"acme-corp\" \"Acme Corporation Inventory System\"\n";
        echo "  php create_organization.php assign john_doe acme-corp\n";
        echo "  php create_organization.php create-admin admin admin@acme.com password123 \"Acme Admin\" acme-corp\n";
        exit(1);
    }
    
    $command = $argv[1];
    
    try {
        switch ($command) {
            case 'create':
                if (count($argv) < 4) {
                    echo "Usage: php create_organization.php create <name> <slug> [description]\n";
                    exit(1);
                }
                
                $name = $argv[2];
                $slug = $argv[3];
                $description = $argv[4] ?? '';
                
                $orgId = createOrganization($name, $slug, $description);
                if ($orgId) {
                    echo "Organization created successfully!\n";
                } else {
                    echo "Failed to create organization.\n";
                    exit(1);
                }
                break;
                
            case 'assign':
                if (count($argv) < 4) {
                    echo "Usage: php create_organization.php assign <username> <org_slug>\n";
                    exit(1);
                }
                
                $username = $argv[2];
                $orgSlug = $argv[3];
                
                // Get organization ID by slug
                $database = new Database();
                $conn = $database->getConnection();
                
                if (!$conn) {
                    throw new Exception("Database connection failed");
                }
                
                $stmt = $conn->prepare("SELECT id FROM organizations WHERE slug = ?");
                $stmt->execute([$orgSlug]);
                $org = $stmt->fetch();
                
                if (!$org) {
                    echo "Organization with slug '$orgSlug' not found\n";
                    exit(1);
                }
                
                $orgId = $org['id'];
                
                $result = assignUserToOrganization($username, $orgId);
                if ($result) {
                    echo "User assigned to organization successfully!\n";
                } else {
                    echo "Failed to assign user to organization.\n";
                    exit(1);
                }
                break;
                
            case 'create-admin':
                if (count($argv) < 7) {
                    echo "Usage: php create_organization.php create-admin <username> <email> <password> <full_name> <org_slug>\n";
                    exit(1);
                }
                
                $username = $argv[2];
                $email = $argv[3];
                $password = $argv[4];
                $fullName = $argv[5];
                $orgSlug = $argv[6];
                
                // Get organization ID by slug
                $database = new Database();
                $conn = $database->getConnection();
                
                if (!$conn) {
                    throw new Exception("Database connection failed");
                }
                
                $stmt = $conn->prepare("SELECT id FROM organizations WHERE slug = ?");
                $stmt->execute([$orgSlug]);
                $org = $stmt->fetch();
                
                if (!$org) {
                    echo "Organization with slug '$orgSlug' not found\n";
                    exit(1);
                }
                
                $orgId = $org['id'];
                
                $userId = createAdminUser($username, $email, $password, $fullName, $orgId);
                if ($userId) {
                    echo "Admin user created successfully!\n";
                } else {
                    echo "Failed to create admin user.\n";
                    exit(1);
                }
                break;
                
            default:
                echo "Unknown command: $command\n";
                echo "Usage: php create_organization.php <command> [options]\n";
                exit(1);
        }
        
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
        exit(1);
    }
}
?>