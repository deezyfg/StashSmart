<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'config/database.php';

echo "<h1>StashSmart Database Setup</h1>";

try {
    // Connect to MySQL without specifying database
    $pdo = new PDO("mysql:host=localhost", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Read and execute the schema file
    $schemaPath = 'sql/schema.sql';

    if (!file_exists($schemaPath)) {
        throw new Exception("Schema file not found at: " . $schemaPath);
    }

    $sql = file_get_contents($schemaPath);

    // Split SQL commands by semicolon and execute each
    $commands = array_filter(array_map('trim', explode(';', $sql)));

    foreach ($commands as $command) {
        if (!empty($command) && !preg_match('/^--/', $command)) {
            $pdo->exec($command);
        }
    }

    echo "<p style='color: green;'>✅ Database and tables created successfully!</p>";

    // Test connection to the new database
    $database = new Database();
    $conn = $database->getConnection();

    if ($conn) {
        echo "<p style='color: green;'>✅ Database connection test successful!</p>";

        // Check if tables exist
        $tables = ['users', 'categories', 'accounts', 'transactions', 'financial_goals', 'budgets'];

        foreach ($tables as $table) {
            $stmt = $conn->prepare("SHOW TABLES LIKE :table");
            $stmt->bindParam(':table', $table);
            $stmt->execute();

            if ($stmt->rowCount() > 0) {
                echo "<p style='color: green;'>✅ Table '$table' created successfully</p>";
            } else {
                echo "<p style='color: red;'>❌ Table '$table' not found</p>";
            }
        }

        echo "<h2>Setup Complete!</h2>";
        echo "<p>Your StashSmart backend is now ready to use.</p>";
        echo "<h3>API Endpoints:</h3>";
        echo "<ul>";
        echo "<li><strong>Authentication:</strong> /StashSmart/backend/api/auth/</li>";
        echo "<li><strong>Transactions:</strong> /StashSmart/backend/api/transactions/</li>";
        echo "<li><strong>Categories:</strong> /StashSmart/backend/api/categories/</li>";
        echo "<li><strong>Accounts:</strong> /StashSmart/backend/api/accounts/</li>";
        echo "<li><strong>Goals:</strong> /StashSmart/backend/api/goals/</li>";
        echo "<li><strong>Budgets:</strong> /StashSmart/backend/api/budgets/</li>";
        echo "</ul>";

        echo "<h3>Test the API:</h3>";
        echo "<p>You can test the API using tools like Postman or by updating your frontend forms.</p>";
    } else {
        echo "<p style='color: red;'>❌ Database connection failed</p>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
}
?>

<!DOCTYPE html>
<html>

<head>
    <title>StashSmart Database Setup</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
        }

        h1 {
            color: #333;
        }

        p {
            margin: 10px 0;
        }

        ul {
            margin: 10px 0;
        }

        li {
            margin: 5px 0;
        }
    </style>
</head>

<body>
</body>

</html>