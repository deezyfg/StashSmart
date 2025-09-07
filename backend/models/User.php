<?php
require_once '../config/database.php';

class User
{
    private $conn;
    private $table_name = "users";

    public $id;
    public $full_name;
    public $email;
    public $mobile;
    public $username;
    public $password_hash;
    public $profile_picture;
    public $is_email_verified;
    public $status;
    public $created_at;
    public $updated_at;
    public $last_login;

    public function __construct($db)
    {
        $this->conn = $db;
    }

    // Create new user
    public function create()
    {
        $query = "INSERT INTO " . $this->table_name . " 
                  (full_name, email, mobile, username, password_hash) 
                  VALUES (:full_name, :email, :mobile, :username, :password_hash)";

        $stmt = $this->conn->prepare($query);

        // Sanitize inputs
        $this->full_name = htmlspecialchars(strip_tags($this->full_name));
        $this->email = htmlspecialchars(strip_tags($this->email));
        $this->mobile = htmlspecialchars(strip_tags($this->mobile));
        $this->username = htmlspecialchars(strip_tags($this->username));

        // Bind parameters
        $stmt->bindParam(":full_name", $this->full_name);
        $stmt->bindParam(":email", $this->email);
        $stmt->bindParam(":mobile", $this->mobile);
        $stmt->bindParam(":username", $this->username);
        $stmt->bindParam(":password_hash", $this->password_hash);

        if ($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            return true;
        }

        return false;
    }

    // Find user by email
    public function findByEmail($email)
    {
        $query = "SELECT * FROM " . $this->table_name . " WHERE email = :email LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":email", $email);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $this->mapFromArray($row);
            return true;
        }

        return false;
    }

    // Find user by username
    public function findByUsername($username)
    {
        $query = "SELECT * FROM " . $this->table_name . " WHERE username = :username LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":username", $username);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $this->mapFromArray($row);
            return true;
        }

        return false;
    }

    // Find user by ID
    public function findById($id)
    {
        $query = "SELECT * FROM " . $this->table_name . " WHERE id = :id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $id);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $this->mapFromArray($row);
            return true;
        }

        return false;
    }

    // Update user profile
    public function update()
    {
        $query = "UPDATE " . $this->table_name . " 
                  SET full_name = :full_name, email = :email, mobile = :mobile, 
                      username = :username, profile_picture = :profile_picture,
                      updated_at = CURRENT_TIMESTAMP
                  WHERE id = :id";

        $stmt = $this->conn->prepare($query);

        // Sanitize inputs
        $this->full_name = htmlspecialchars(strip_tags($this->full_name));
        $this->email = htmlspecialchars(strip_tags($this->email));
        $this->mobile = htmlspecialchars(strip_tags($this->mobile));
        $this->username = htmlspecialchars(strip_tags($this->username));

        // Bind parameters
        $stmt->bindParam(":full_name", $this->full_name);
        $stmt->bindParam(":email", $this->email);
        $stmt->bindParam(":mobile", $this->mobile);
        $stmt->bindParam(":username", $this->username);
        $stmt->bindParam(":profile_picture", $this->profile_picture);
        $stmt->bindParam(":id", $this->id);

        return $stmt->execute();
    }

    // Update password
    public function updatePassword($newPasswordHash)
    {
        $query = "UPDATE " . $this->table_name . " 
                  SET password_hash = :password_hash, updated_at = CURRENT_TIMESTAMP 
                  WHERE id = :id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":password_hash", $newPasswordHash);
        $stmt->bindParam(":id", $this->id);

        return $stmt->execute();
    }

    // Update last login
    public function updateLastLogin()
    {
        $query = "UPDATE " . $this->table_name . " 
                  SET last_login = CURRENT_TIMESTAMP 
                  WHERE id = :id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $this->id);

        return $stmt->execute();
    }

    // Check if email exists
    public function emailExists($email)
    {
        $query = "SELECT id FROM " . $this->table_name . " WHERE email = :email LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":email", $email);
        $stmt->execute();

        return $stmt->rowCount() > 0;
    }

    // Check if username exists
    public function usernameExists($username)
    {
        $query = "SELECT id FROM " . $this->table_name . " WHERE username = :username LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":username", $username);
        $stmt->execute();

        return $stmt->rowCount() > 0;
    }

    // Get user profile data (without sensitive info)
    public function getProfile()
    {
        return [
            'id' => $this->id,
            'full_name' => $this->full_name,
            'email' => $this->email,
            'mobile' => $this->mobile,
            'username' => $this->username,
            'profile_picture' => $this->profile_picture,
            'is_email_verified' => $this->is_email_verified,
            'status' => $this->status,
            'created_at' => $this->created_at,
            'last_login' => $this->last_login
        ];
    }

    // Verify password
    public function verifyPassword($password)
    {
        return password_verify($password, $this->password_hash);
    }

    // Hash password
    public static function hashPassword($password)
    {
        return password_hash($password, PASSWORD_DEFAULT);
    }

    // Map array data to object properties
    private function mapFromArray($data)
    {
        $this->id = $data['id'];
        $this->full_name = $data['full_name'];
        $this->email = $data['email'];
        $this->mobile = $data['mobile'];
        $this->username = $data['username'];
        $this->password_hash = $data['password_hash'];
        $this->profile_picture = $data['profile_picture'];
        $this->is_email_verified = $data['is_email_verified'];
        $this->status = $data['status'];
        $this->created_at = $data['created_at'];
        $this->updated_at = $data['updated_at'];
        $this->last_login = $data['last_login'];
    }

    // Create default categories for new user
    public function createDefaultCategories()
    {
        $defaultCategories = [
            // Income categories
            ['name' => 'Salary', 'type' => 'income', 'color' => '#28a745', 'icon' => 'fas fa-money-bill-wave'],
            ['name' => 'Freelance', 'type' => 'income', 'color' => '#17a2b8', 'icon' => 'fas fa-laptop'],
            ['name' => 'Investment', 'type' => 'income', 'color' => '#6f42c1', 'icon' => 'fas fa-chart-line'],
            ['name' => 'Other Income', 'type' => 'income', 'color' => '#6c757d', 'icon' => 'fas fa-plus-circle'],

            // Expense categories
            ['name' => 'Food & Dining', 'type' => 'expense', 'color' => '#dc3545', 'icon' => 'fas fa-utensils'],
            ['name' => 'Transportation', 'type' => 'expense', 'color' => '#fd7e14', 'icon' => 'fas fa-car'],
            ['name' => 'Shopping', 'type' => 'expense', 'color' => '#e83e8c', 'icon' => 'fas fa-shopping-bag'],
            ['name' => 'Entertainment', 'type' => 'expense', 'color' => '#6f42c1', 'icon' => 'fas fa-film'],
            ['name' => 'Bills & Utilities', 'type' => 'expense', 'color' => '#ffc107', 'icon' => 'fas fa-file-invoice-dollar'],
            ['name' => 'Healthcare', 'type' => 'expense', 'color' => '#20c997', 'icon' => 'fas fa-heartbeat'],
            ['name' => 'Education', 'type' => 'expense', 'color' => '#007bff', 'icon' => 'fas fa-graduation-cap'],
            ['name' => 'Other Expenses', 'type' => 'expense', 'color' => '#6c757d', 'icon' => 'fas fa-minus-circle']
        ];

        $query = "INSERT INTO categories (user_id, name, type, color, icon, is_default) 
                  VALUES (:user_id, :name, :type, :color, :icon, 1)";

        $stmt = $this->conn->prepare($query);

        foreach ($defaultCategories as $category) {
            $stmt->bindParam(":user_id", $this->id);
            $stmt->bindParam(":name", $category['name']);
            $stmt->bindParam(":type", $category['type']);
            $stmt->bindParam(":color", $category['color']);
            $stmt->bindParam(":icon", $category['icon']);
            $stmt->execute();
        }

        // Create default account
        $accountQuery = "INSERT INTO accounts (user_id, name, type, balance) 
                         VALUES (:user_id, 'Primary Account', 'checking', 0.00)";
        $accountStmt = $this->conn->prepare($accountQuery);
        $accountStmt->bindParam(":user_id", $this->id);
        $accountStmt->execute();

        return true;
    }
}
