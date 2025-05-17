<?php 
    // <!-- FERDINAND JOHANNES NEL -->
    // <!-- u24594475 -->
    // <!-- ZOE JOUBERT -->
	// <!-- u05084360 -->
    // <!-- COS216 -->

    require_once 'COS216/PA3/php/config.php';
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);

    define('HQ_LATITUDE', -25.7472);
    define('HQ_LONGITUDE', 28.2511);

    class Database {
        private static $instance = null;
        private $connection;
        
        private function __construct() {
            $config = include('COS216/PA4/php/config.php');
            $this->connection = new mysqli($config['host'], $config['user'], $config['password'], $config['database']);
            
            if ($this->connection->connect_error) {
                throw new Exception("Database connection failed: " . $this->connection->connect_error);
            }
        }
        
        public static function getInstance() {
            if (!self::$instance) { //If there is no instance, create one
                self::$instance = new Database();
            }
            return self::$instance;
        }
        
        public function getConnection() {
            return $this->connection;
        }
        
        public function __destruct() {
            if ($this->connection) {
                $this->connection->close();
            }
        }
        
        private function __clone() {}
        private function __wakeup() {}
    }

    class ResponseAPI {
        public static function send($data, $code = 200) {
            http_response_code($code);
            header('Content-Type: application/json');
            echo json_encode($data);
            exit;
        }//send function
    }//class ResponseAPI

    class User {
        private $connection;

        public function __construct(){
            $db = Database::getInstance();
            $this->connection = $db->getConnection();
        }//user 

        public function registerUser($data){
            //Check empty
            if (empty($data['name'])) {
                return $this->error("name is required", 400);
            }
            if (empty($data['surname'])) {
                return $this->error("surname is required", 400);
            }
            if (empty($data['email'])) {
                return $this->error("email is required", 400);
            }
            if (empty($data['password'])) {
                return $this->error("password is required", 400);
            }
            if (empty($data['user_type'])) {
                return $this->error("user_type is required", 400);
            } 
            
            //Check valid
            if($this->validateName($data['name']) == false){
                return $this->error("Invalid name format", 400);
            }
            if($this->validateName($data['surname']) == false){
                return $this->error("Invalid surname format", 400);
            }
            if($this->validateEmail($data['email']) == false){
                return $this->error("Invalid email format", 400);
            }
            if ($this->validatePassword($data['password']) == false) {
                return $this->error("Password must be 9+ chars, have upper/lowercase letters, digits & symbols", 400);
            }
            if (!($data['user_type'] == 'Customer' || $data['user_type'] == 'Courier' || $data['user_type'] == 'Inventory Manager')) {
                return $this->error("user_type not Customer, Courier or Inventory Manager", 400);
            }

            //Check if user already exists
            if ($this->checkIfUserExists($data['email'])){
                return $this->error("User already exists. The email address entered is already in the database.", 409);
            }//if

            //Now, add user to DB
            $salt = $this->generateString(16);
            $hashedPassword = hash('sha512', $salt . $data['password']);
            $apikey = $this->generateString(32);
            while ($this->checkIfAPIKeyExists($apikey) == true) {
                $apikey = $this->generateString(32);
            }//while

            $stmt = $this->connection->prepare("INSERT INTO users (name, surname, email, password_hash, salt, type, api_key) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param(
                "sssssss",
                $data['name'],
                $data['surname'],
                $data['email'],
                $hashedPassword,
                $salt,
                $data['user_type'],
                $apikey
            );
            if ($stmt->execute()) {
                return [
                    'status' => 'success',
                    'timestamp' => round(microtime(true) * 1000),
                    'data' => [
                        'apikey' => $apikey
                    ]
                ];
            } else {
                return $this->error("Database error: " . $stmt->error, 500);
            }

        }//registerUser function

        public function loginUser($data){
            if (!isset($data['email']) || !isset($data['password'])) {
                return $this->error("Email and password required", 400);
            }
        
            $email = $data['email'];
            $password = $data['password'];
        
            $stmt = $this->connection->prepare("SELECT id, name, type, password_hash, salt, api_key FROM users WHERE email = ?");
            if (!$stmt) {
                return $this->error("Database error", 500);
            }
        
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows !== 1) {
                return $this->error("Email or password incorrect", 401);
            }
        
            $user = $result->fetch_assoc();
            $hashedPassword = hash('sha512', $user['salt'] . $password);
        
            if ($hashedPassword !== $user['password_hash']) {
                return $this->error("Email or password incorrect", 401);
            }
        
            return [
                'status' => 'success',
                'timestamp' => round(microtime(true) * 1000),
                'data' => [
                    [
                        'apikey' => $user['api_key'],
                        'name' => $user['name'],
                        'type' => $user['type'],
                    ]
                ],
                'code' => 200
            ];
        }//loginUser function

        private function error($message, $code) {
            return [
                'status' => 'error',
                'timestamp' => round(microtime(true) * 1000),
                'data' => $message,
                'code' => $code
            ];
        }//error function
        private function validateEmail($email) {
            $emailRegex = '/^(?=.{3,255}$)[-a-z0-9~!$%^&*_=+}{\'?]+(\.[-a-z0-9~!$%^&*_=+}{\'?]+)*@([a-z0-9_][-a-z0-9_]*(\.[-a-z0-9_]+)*\.(aero|arpa|biz|com|coop|edu|gov|info|int|mil|museum|name|net|org|pro|travel|mobi|[a-z][a-z])|([0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}))(:[0-9]{1,5})?$/';
            return preg_match($emailRegex, $email);
        }//validateEmail function
        private function validatePassword($password) {
            //Regex
            $passwordRegex = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[!@#$%^&*])[A-Za-z\d!@#$%^&*]{9,255}$/';
            return preg_match($passwordRegex, $password);
        }//validatePassword function
        private function validateName($name) {
            //Regex
            $nameRegex = '/^[a-zA-Z]{2,100}$/';
            return preg_match($nameRegex, $name);
        }//validateName function
        private function checkIfUserExists($email){
            $stmt = $this->connection->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $stmt->store_result();
            return $stmt->num_rows > 0;
        }//checkIfUserExists function
        private function checkIfAPIKeyExists($key){
            $stmt = $this->connection->prepare("SELECT id FROM users WHERE api_key = ?");
            $stmt->bind_param("s", $key);
            $stmt->execute();
            $stmt->store_result();
            return $stmt->num_rows > 0;
        }//checkIfAPIKeyExists function

        private function generateString($lengthOfString = 32){
            $charsForAPIKey = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
            $apiKey = '';
            for ($counter = 0; $counter < $lengthOfString; $counter++){
                $apiKey .= $charsForAPIKey[rand(0, strlen($charsForAPIKey) - 1)];
            }
            return $apiKey;
        }//for

    }//user class

    class Products {
        private $connection;
    
        public function __construct() {
            $db = Database::getInstance();
            $this->connection = $db->getConnection();
        }
    
        public function getAllProducts($data) {
            $allowedReturnSortColumns = [
                'id', 'title', 'brand', 'description', 'initial_price', 'final_price', 'currency', 'categories', 
                'image_url', 'product_dimensions', 'date_first_available', 'manufacturer', 'department', 
                'features', 'is_available', 'images', 'country_of_origin', 'created_at', 'updated_at', 'discount'
            ];
            
            $allowedSearchColumns = [
                'id', 'title', 'brand', 'categories', 'department', 'manufacturer', 'features', 
                'price_min', 'price_max', 'country_of_origin', 'discount_min', 'discount_max'
            ];
        
            // Validate required parameters
            if (!isset($data['apikey']) || !isset($data['type']) || !isset($data['return'])) {
                return $this->error("Not all required fields specified for this request type", 400);
            }
        
            //Validate limit parameter
            $limit = 500; // Default maximum limit
            if (isset($data['limit'])) {
                $limit = (int)$data['limit'];
                if ($limit < 1 || $limit > 500) {
                    return $this->error("limit must be between 1 and 500", 400);
                }
            }
        
            //Validate sort and order parameters
            $sortSql = '';
            if (isset($data['sort'])) {
                if (!in_array($data['sort'], $allowedReturnSortColumns)) {
                    return $this->error("Invalid sort column: {$data['sort']}", 400);
                }
                
                $order = 'ASC';
                if (isset($data['order'])) {
                    $order = strtoupper($data['order']);
                    if (!in_array($order, ['ASC', 'DESC'])) {
                        return $this->error("order must be ASC or DESC", 400);
                    }
                }
                
                $sortSql = " ORDER BY {$data['sort']} $order";
            } elseif (isset($data['order'])) {
                return $this->error("order can only be specified if sort is also specified", 400);
            }
        
            // Validate fuzzy parameter
            $useFuzzy = true; // Default to true
            if (isset($data['fuzzy'])) {
                if (is_bool($data['fuzzy'])) {
                    $useFuzzy = $data['fuzzy'];
                } elseif (is_string($data['fuzzy'])) {
                    $useFuzzy = strtolower($data['fuzzy']) === 'true';
                } else {
                    return $this->error("Invalid value for fuzzy. Must be true or false.", 400);
                }
            }
        
            // Process search parameters
            $whereClauses = [];
            $params = [];
            $priceConditions = [];
            $discountConditions = [];
            
            if (isset($data['search']) && is_array($data['search'])) {
                foreach ($data['search'] as $column => $value) {
                    if (!in_array($column, $allowedSearchColumns)) {
                        return $this->error("Invalid search column: {$column}", 400);
                    }
                    
                    if ($column === 'price_min') {
                        $priceConditions['min'] = (float)$value;
                    } elseif ($column === 'price_max') {
                        $priceConditions['max'] = (float)$value;
                    } elseif ($column === 'discount_min') {
                        $discountConditions['min'] = (float)$value;
                    } elseif ($column === 'discount_max') {
                        $discountConditions['max'] = (float)$value;
                    } else {
                        if ($useFuzzy) {
                            $whereClauses[] = "$column LIKE ?";
                            $params[] = "%$value%";
                        } else {
                            $whereClauses[] = "$column = ?";
                            $params[] = $value;
                        }
                    }
                }
            }
        
            // Process return fields
            $returnFields = $data['return'];
            $forceIncludeCurrency = false;
            
            if ($returnFields === '*') {
                $returnFieldsSql = '*';
            } else {
                if (!is_array($returnFields)) {
                    return $this->error("Return must be '*' or an array of valid column names", 400);
                }
                
                foreach ($returnFields as $field) {
                    if (!in_array($field, $allowedReturnSortColumns)) {
                        return $this->error("Invalid return field: '$field'", 400);
                    }
                }
                
                if ((in_array('final_price', $returnFields) || in_array('initial_price', $returnFields)) && 
                    !in_array('currency', $returnFields)) {
                    $forceIncludeCurrency = true;
                    $returnFields[] = 'currency';
                }
                
                $returnFieldsSql = implode(', ', $returnFields);
            }
        
            // Build and execute base query
            $sql = "SELECT $returnFieldsSql FROM products";
            if (!empty($whereClauses)) {
                $sql .= " WHERE " . implode(" AND ", $whereClauses);
            }
            $sql .= $sortSql;
        
            $stmt = $this->connection->prepare($sql);
            if (!$stmt) {
                return $this->error("Database error: failed to prepare statement", 500);
            }
        
            if (!empty($params)) {
                $types = str_repeat("s", count($params));
                $stmt->bind_param($types, ...$params);
            }
        
            if (!$stmt->execute()) {
                return $this->error("Database error: failed to execute query", 500);
            }
        
            $result = $stmt->get_result();
            $products = [];
            while ($row = $result->fetch_assoc()) {
                $products[] = $row;
            }
        
            // Convert all prices to ZAR
            $conversionResult = $this->convertPricesToRand($products);
            if (isset($conversionResult['status']) && $conversionResult['status'] === 'error') {
                return $conversionResult;
            }
        
            // Apply price range filtering if specified
            if (!empty($priceConditions)) {
                $products = array_filter($products, function($product) use ($priceConditions) {
                    if (!isset($product['final_price'])) {
                        return false;
                    }
                    
                    $price = (float)$product['final_price'];
                    $pass = true;
                    
                    if (isset($priceConditions['min']) && $price < $priceConditions['min']) {
                        $pass = false;
                    }
                    if (isset($priceConditions['max']) && $price > $priceConditions['max']) {
                        $pass = false;
                    }
                    
                    return $pass;
                });
                $products = array_values($products);
            }

            //Apply discount filtering
            if (!empty($discountConditions)) {
                $products = array_filter($products, function($product) use ($discountConditions) {
                    if (!isset($product['discount'])) {
                        return false;
                    }
            
                    $disc = (float)$product['discount'];
                    $pass = true;
            
                    if (isset($discountConditions['min']) && $disc < $discountConditions['min']) {
                        $pass = false;
                    }
                    if (isset($discountConditions['max']) && $disc > $discountConditions['max']) {
                        $pass = false;
                    }
            
                    return $pass;
                });
                $products = array_values($products);
            }
        
            // Apply limit
            if (count($products) > $limit) {
                $products = array_slice($products, 0, $limit);
            }
        
            // Remove currency if it was forced but not originally requested
            if ($forceIncludeCurrency && $returnFields !== '*') {
                foreach ($products as &$product) {
                    unset($product['currency']);
                }
            }
        
            return [
                'status' => 'success',
                'timestamp' => round(microtime(true) * 1000),
                'data' => $products,
                'code' => 200
            ];
        }
    
        public function convertPricesToRand(&$products) {
            $studentnum = 'u24594475';
            $apikey = '81346e1f1042acd37b735e8a1030fdb8';
    
            $postData = json_encode([
                'studentnum' => $studentnum,
                'apikey' => $apikey,
                'type' => 'GetCurrencyList'
            ]);
    
            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL => 'https://wheatley.cs.up.ac.za/api/',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $postData,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Content-Length: ' . strlen($postData)
                ]
            ]);
    
            $response = curl_exec($curl);
            curl_close($curl);
    
            if (!$response) {
                return $this->error("Failed to retrieve currency data", 500);
            }
    
            $currencyData = json_decode($response, true);
            if (!$currencyData || !isset($currencyData['data']) || !isset($currencyData['data']['ZAR'])) {
                return $this->error("Invalid currency data format", 500);
            }
    
            $exchangeRates = $currencyData['data'];
    
            foreach ($products as &$product) {
                $currency = $product['currency'] ?? 'ZAR';
                if ($currency !== 'ZAR' && isset($exchangeRates[$currency])) {
                    $rateToUSD = $exchangeRates[$currency];
                    $rateToZAR = $exchangeRates['ZAR'];
                    $conversionRate = $rateToZAR / $rateToUSD;
    
                    if (isset($product['final_price'])) {
                        $product['final_price'] = round($product['final_price'] * $conversionRate, 2);
                    }
                    if (isset($product['initial_price'])) {
                        $product['initial_price'] = round($product['initial_price'] * $conversionRate, 2);
                    }
                    $product['currency'] = 'ZAR';
                }
            }
    
            return ['status' => 'success'];
        }//convertPricesToRand function
    
        public function validateInputData($data){
            if (!isset($data['apikey']) || !isset($data['type']) || !isset($data['return'])) {
                return false;
            }
            return true;
        }//validateInputData function
    
        public function checkIfAPIKeyExists($key){
            $stmt = $this->connection->prepare("SELECT id FROM users WHERE api_key = ?");
            $stmt->bind_param("s", $key);
            $stmt->execute();
            $stmt->store_result();
            return $stmt->num_rows == 1;
        }//checkIfAPIKeyExists function
    
        private function error($message, $code) {
            return [
                'status' => 'error',
                'timestamp' => round(microtime(true) * 1000),
                'data' => $message,
                'code' => $code
            ];
        }//error function
    }//Products class

    
    class Preferences {
        private $connection;

        public function __construct($dbconnection) {
            $this->connection = $dbconnection;
        }

        public function handlePreferences($data) {
            if (!isset($data['apikey']) || !isset($data['action'])) {
                return $this->error("Not all required fields specified for this request type", 400);
            }

            $action = strtolower($data['action']);
            if ($action === 'set') {
                return $this->savePreferences($data);
            } elseif ($action === 'get') {
                return $this->returnPreferences($data);
            } else {
                return $this->error("Invalid action. Must be 'get' or 'set'", 400);
            }
        }

        private function savePreferences($data) {
            if (!isset($data['preferences'])) {
                return $this->error("Missing required parameters for saving preferences: preferences", 400);
            }

            $userId = $this->getUserIdByApiKey($data['apikey']);
            if (!$userId) {
                return $this->error("Invalid API Key (Unauthorised)", 401);
            }

            $prefs = $data['preferences'];
            if (!is_array($prefs)) {
                return $this->error("Preferences must be a JSON object", 400);
            }

            $theme = isset($prefs['theme']) ? $prefs['theme'] : 'Light';
            $currency = isset($prefs['currency']) ? $prefs['currency'] : 'ZAR';
            $department = isset($prefs['department_filter']) ? $prefs['department_filter'] : null;
            $brand = isset($prefs['brand_filter']) ? $prefs['brand_filter'] : null;
            $priceBracket = isset($prefs['price_bracket_filter']) ? $prefs['price_bracket_filter'] : null;
            $country = isset($prefs['country_filter']) ? $prefs['country_filter'] : null;
            $sortField = isset($prefs['sort_field']) ? $prefs['sort_field'] : null;
            $sortOrder = isset($prefs['sort_order']) ? strtoupper($prefs['sort_order']) : null;
            $lastSearch = isset($prefs['last_search']) ? $prefs['last_search'] : null;

            if ($theme !== 'Light' && $theme !== 'Dark') {
                return $this->error("Invalid theme. Must be 'Light' or 'Dark'", 400);
            }

            $validSortFields = ['price', 'final_price', 'name', 'title', 'discount'];
            if ($sortField !== null && !in_array($sortField, $validSortFields)) {
                return $this->error("Invalid sort_field. Must be 'price', 'final_price', 'name', 'title', or 'discount'", 400);
            }

            if ($sortField === 'price') {
                $sortField = 'final_price';
            } elseif ($sortField === 'name') {
                $sortField = 'title';
            }

            if ($sortOrder !== null && $sortOrder !== 'ASC' && $sortOrder !== 'DESC') {
                return $this->error("Invalid sort_order. Must be 'ASC' or 'DESC'", 400);
            }

            $stmt = $this->connection->prepare("
                INSERT INTO user_preferences (user_id, theme, currency, department_filter, brand_filter, price_bracket_filter, country_filter, sort_field, sort_order, last_search)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    theme = VALUES(theme),
                    currency = VALUES(currency),
                    department_filter = VALUES(department_filter),
                    brand_filter = VALUES(brand_filter),
                    price_bracket_filter = VALUES(price_bracket_filter),
                    country_filter = VALUES(country_filter),
                    sort_field = VALUES(sort_field),
                    sort_order = VALUES(sort_order),
                    last_search = VALUES(last_search)
            ");

            if (!$stmt) {
                return $this->error("Database error: failed to prepare statement", 500);
            }

            $stmt->bind_param(
                "isssssssss",
                $userId,
                $theme,
                $currency,
                $department,
                $brand,
                $priceBracket,
                $country,
                $sortField,
                $sortOrder,
                $lastSearch
            );

            if (!$stmt->execute()) {
                return $this->error("Database error: failed to save preferences", 500);
            }

            return [
                'status' => 'success',
                'timestamp' => round(microtime(true) * 1000),
                'data' => 'Preferences saved successfully',
                'code' => 200
            ];
        }

        private function returnPreferences($data) {
            $userId = $this->getUserIdByApiKey($data['apikey']);
            if (!$userId) {
                return $this->error("Invalid API Key (Unauthorised)", 401);
            }

            $stmt = $this->connection->prepare("SELECT theme, currency, department_filter, brand_filter, price_bracket_filter, country_filter, sort_field, sort_order, last_search FROM user_preferences WHERE user_id = ?");
            if (!$stmt) {
                return $this->error("Database error: failed to prepare statement", 500);
            }

            $stmt->bind_param("i", $userId);
            if (!$stmt->execute()) {
                return $this->error("Database error: failed to fetch preferences", 500);
            }

            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                return [
                    'status' => 'success',
                    'timestamp' => round(microtime(true) * 1000),
                    'data' => [$row],
                    'code' => 200
                ];
            } else {
                return [
                    'status' => 'success',
                    'timestamp' => round(microtime(true) * 1000),
                    'data' => [],
                    'code' => 200
                ];
            }
        }

        private function getUserIdByApiKey($apikey) {
            $stmt = $this->connection->prepare("SELECT id FROM users WHERE api_key = ?");
            if (!$stmt) {
                return false;
            }
            $stmt->bind_param("s", $apikey);
            if (!$stmt->execute()) {
                return false;
            }
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                return (int)$row['id'];
            }
            return false;
        }

        private function error($message, $code) {
            return [
                'status' => 'error',
                'timestamp' => round(microtime(true) * 1000),
                'data' => $message,
                'code' => $code
            ];
        }
    } // Preferences class

    class Wishlist {
        private $connection;
    
        public function __construct($dbconnection) {
            $this->connection = $dbconnection;
        }//constructor

        public function handleWishlist($data) {
            if (!isset($data['apikey']) || !isset($data['action'])) {
                return $this->error("Not all required fields specified for this request type", 400);
            }
        
            $userId = $this->getUserIdByApiKey($data['apikey']);
            if (!$userId) {
                return $this->error("Invalid API key (Unauthorised)", 401);
            }
        
            $action = strtolower($data['action']);
        
            if ($action === 'add' || $action === 'remove') {
                if (!isset($data['product_id'])) {
                    return $this->error("product_id is required for this action", 400);
                }
                
                $productId = (int)$data['product_id'];
                if (!$this->validateProductID($productId)) {
                    return $this->error("Invalid product ID", 404);
                }
        
                if ($action === 'add') {
                    return $this->addToWishlist($userId, $productId);
                } else {
                    return $this->removeFromWishlist($userId, $productId);
                }
            } elseif ($action === 'get') {
                return $this->getWishlist($userId);
            } else {
                return $this->error("Invalid action. Must be 'add', 'remove', or 'get'", 400);
            }
        }//handleWishlist function

        private function validateProductID($productId) {
            $stmt = $this->connection->prepare("SELECT id FROM products WHERE id = ?");
            $stmt->bind_param("i", $productId);
            $stmt->execute();
            $stmt->store_result();
            return $stmt->num_rows > 0;
        }//validateProductID function

        private function addToWishlist($userId, $productId) {
            // Check if product is already in wishlist
            $check = $this->connection->prepare("SELECT id FROM wishlists WHERE user_id = ? AND product_id = ?");
            if (!$check) {
                return $this->error("Database error: failed to prepare check statement", 500);
            }
            $check->bind_param("ii", $userId, $productId);
            $check->execute();
            $result = $check->get_result();
            if ($result->fetch_assoc()) {
                return [
                    'status' => 'success',
                    'timestamp' => round(microtime(true) * 1000),
                    'data' => 'Product already in wishlist',
                    'code' => 200
                ];
            }
    
            $stmt = $this->connection->prepare("INSERT INTO wishlists (user_id, product_id) VALUES (?, ?)");
            if (!$stmt) {
                return $this->error("Database error: failed to prepare insert statement", 500);
            }
            $stmt->bind_param("ii", $userId, $productId);
    
            if (!$stmt->execute()) {
                return $this->error("Database error: failed to add to wishlist", 500);
            }
    
            return [
                'status' => 'success',
                'timestamp' => round(microtime(true) * 1000),
                'data' => 'Product added to wishlist',
                'code' => 200
            ];
        } //addToWishlist function

        private function removeFromWishlist($userId, $productId) {
            $stmt = $this->connection->prepare("DELETE FROM wishlists WHERE user_id = ? AND product_id = ?");
            if (!$stmt) {
                return $this->error("Database error: failed to prepare delete statement", 500);
            }
            $stmt->bind_param("ii", $userId, $productId);
    
            if (!$stmt->execute()) {
                return $this->error("Database error: failed to remove from wishlist", 500);
            }
    
            if ($stmt->affected_rows === 0) {
                return [
                    'status' => 'success',
                    'timestamp' => round(microtime(true) * 1000),
                    'data' => 'Product not in wishlist',
                    'code' => 200
                ];
            }
    
            return [
                'status' => 'success',
                'timestamp' => round(microtime(true) * 1000),
                'data' => 'Product removed from wishlist',
                'code' => 200
            ];
        }//removeFromWishlist function

        private function getWishlist($userId) {
            $stmt = $this->connection->prepare("SELECT product_id FROM wishlists WHERE user_id = ?");
            if (!$stmt) {
                return $this->error("Database error: failed to prepare select statement", 500);
            }
            $stmt->bind_param("i", $userId);
    
            if (!$stmt->execute()) {
                return $this->error("Database error: failed to fetch wishlist", 500);
            }
    
            $result = $stmt->get_result();
            $wishlist = [];
            while ($row = $result->fetch_assoc()) {
                $wishlist[] = (int)$row['product_id'];
            }
    
            //Return the wishlist as an array of product IDs
            return [
                'status' => 'success',
                'timestamp' => round(microtime(true) * 1000),
                'data' => $wishlist,
                'code' => 200
            ];
        }//getWishlist function
    
        private function getUserIdByApiKey($apikey) {
            $stmt = $this->connection->prepare("SELECT id FROM users WHERE api_key = ?");
            if (!$stmt) {
                return false;
            }
            $stmt->bind_param("s", $apikey);
            if (!$stmt->execute()) {
                return false;
            }
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                return (int)$row['id'];
            }
            return false;
        }// getUserIdByApiKey function
        private function error($message, $code) {
            return [
                'status' => 'error',
                'timestamp' => round(microtime(true) * 1000),
                'data' => $message,
                'code' => $code
            ];
        }//error function

    }// Wishlist class

    class Cart {
        private $connection;
    
        public function __construct() {
            $db = Database::getInstance();
            $this->connection = $db->getConnection();
        }
    
        public function handleCart($data) {
            if (!isset($data['apikey']) || !isset($data['action'])) {
                return $this->error("Not all required fields specified for this request type", 400);
            }
    
            $customerId = $this->getCustomerIdByApiKey($data['apikey']);
            if (!$customerId) {
                return $this->error("Invalid API key (Unauthorised)", 401);
            }
    
            $action = strtolower($data['action']);
    
            switch ($action) {
                case 'add':
                    return $this->handleAdd($customerId, $data);
                case 'remove':
                    return $this->handleRemove($customerId, $data);
                case 'update':
                    return $this->handleUpdate($customerId, $data);
                case 'get':
                    return $this->handleGet($customerId);
                    case 'empty':
                        return $this->handleEmpty($customerId);
                default:
                    return $this->error("Invalid action. Must be 'add', 'remove', 'update', or 'get'", 400);
            }
        }
    
        private function handleAdd($customerId, $data) {
            if (!isset($data['product_id'])) {
                return $this->error("product_id is required", 400);
            }

            $productId = (int)$data['product_id'];
            if (!$this->validateProductId($productId)) {
                return $this->error("Invalid product ID", 404);
            }

            $stmt = $this->connection->prepare("SELECT SUM(quantity) as total FROM carts WHERE customer_id = ?");
            $stmt->bind_param("i", $customerId);
            $stmt->execute();
            $result = $stmt->get_result();
            $currentTotal = ($row = $result->fetch_assoc()) ? (int)$row['total'] : 0;

            if ($currentTotal >= 7) {
                return $this->error("Cannot have more than 7 items in cart", 400);
            }

            $checkStmt = $this->connection->prepare("SELECT quantity FROM carts WHERE customer_id = ? AND product_id = ?");
            $checkStmt->bind_param("ii", $customerId, $productId);
            $checkStmt->execute();
            $exists = $checkStmt->get_result()->fetch_assoc();
            $currentProductQty = $exists ? (int)$exists['quantity'] : 0;

            if ($currentProductQty >= 7) {
                return $this->error("Cannot have more than 7 of the same product in cart", 400);
            }

            if ($currentTotal + 1 > 7) {
                return $this->error("Cannot have more than 7 items in cart", 400);
            }

            $stmt = $this->connection->prepare("
                INSERT INTO carts (customer_id, product_id, quantity) 
                VALUES (?, ?, 1)
                ON DUPLICATE KEY UPDATE quantity = quantity + 1
            ");
            $stmt->bind_param("ii", $customerId, $productId);
            $stmt->execute();

            return [
                'status' => 'success',
                'timestamp' => round(microtime(true) * 1000),
                'data' => $currentProductQty ? 'Product quantity in cart increased' : 'Product added to cart',
                'code' => 200
            ];
        }
    
        private function handleRemove($customerId, $data) {
            if (!isset($data['product_id'])) {
                return $this->error("product_id is required", 400);
            }
    
            $productId = (int)$data['product_id'];
    
            $stmt = $this->connection->prepare("
                DELETE FROM carts 
                WHERE customer_id = ? AND product_id = ?
            ");
            $stmt->bind_param("ii", $customerId, $productId);
            $stmt->execute();
    
            return [
                'status' => 'success',
                'timestamp' => round(microtime(true) * 1000),
                'data' => $stmt->affected_rows ? 'Product removed from cart' : 'Product not in cart',
                'code' => 200
            ];
        }
    
        private function handleUpdate($customerId, $data) {
            if (!isset($data['product_id']) || !isset($data['quantity'])) {
                return $this->error("product_id and quantity are required", 400);
            }

            $productId = (int)$data['product_id'];
            $quantity = (int)$data['quantity'];

            if ($quantity <= 0) {
                return $this->handleRemove($customerId, $data);
            }

            if ($quantity > 7) {
                return $this->error("Cannot have more than 7 of the same product in cart", 400);
            }

            if (!$this->validateProductId($productId)) {
                return $this->error("Invalid product ID", 404);
            }

            $stmt = $this->connection->prepare("SELECT SUM(quantity) as total FROM carts WHERE customer_id = ?");
            $stmt->bind_param("i", $customerId);
            $stmt->execute();
            $result = $stmt->get_result();
            $currentTotal = ($row = $result->fetch_assoc()) ? (int)$row['total'] : 0;

            $stmt2 = $this->connection->prepare("SELECT quantity FROM carts WHERE customer_id = ? AND product_id = ?");
            $stmt2->bind_param("ii", $customerId, $productId);
            $stmt2->execute();
            $result2 = $stmt2->get_result();
            $currentProductQty = ($row2 = $result2->fetch_assoc()) ? (int)$row2['quantity'] : 0;

            $newTotal = $currentTotal - $currentProductQty + $quantity;
            if ($newTotal > 7) {
                return $this->error("Cannot have more than 7 items in cart", 400);
            }

            $stmt = $this->connection->prepare("
                INSERT INTO carts (customer_id, product_id, quantity) 
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE quantity = ?
            ");
            $stmt->bind_param("iiii", $customerId, $productId, $quantity, $quantity);
            $stmt->execute();

            return [
                'status' => 'success',
                'timestamp' => round(microtime(true) * 1000),
                'data' => 'Cart quantity updated',
                'code' => 200
            ];
        }
    
        private function handleGet($customerId) {
            $stmt = $this->connection->prepare("
                SELECT product_id, quantity 
                FROM carts 
                WHERE customer_id = ?
            ");
            if (!$stmt) {
                return $this->error("Database error: failed to prepare statement", 500);
            }

            $stmt->bind_param("i", $customerId);
            if (!$stmt->execute()) {
                return $this->error("Database error: failed to execute query", 500);
            }

            $result = $stmt->get_result();
            $cart = [];
            while ($row = $result->fetch_assoc()) {
                $cart[$row['product_id']] = $row['quantity'];
            }

            return [
                'status' => 'success',
                'timestamp' => round(microtime(true) * 1000),
                'data' => $cart,
                'code' => 200
            ];
        }

        private function handleEmpty($customerId) {
            try {
                $stmt = $this->connection->prepare("
                    DELETE FROM carts 
                    WHERE customer_id = ?
                ");
                $stmt->bind_param("i", $customerId);
                $stmt->execute();
    
                return [
                    'status' => 'success',
                    'timestamp' => round(microtime(true) * 1000),
                    'data' => 'Cart emptied successfully',
                    'code' => 200
                ];
            } catch (Exception $e) {
                return $this->error("Database error: " . $e->getMessage(), 500);
            }
        }
    
        private function getCustomerIdByApiKey($apiKey) {
            $stmt = $this->connection->prepare("SELECT id FROM users WHERE api_key = ?");
            $stmt->bind_param("s", $apiKey);
            $stmt->execute();
            $result = $stmt->get_result();
            return $result->num_rows ? $result->fetch_assoc()['id'] : null;
        }
    
        private function validateProductId($productId) {
            $stmt = $this->connection->prepare("SELECT id FROM products WHERE id = ?");
            $stmt->bind_param("i", $productId);
            $stmt->execute();
            $stmt->store_result();
            return $stmt->num_rows > 0;
        }
    
        private function error($message, $code) {
            return [
                'status' => 'error',
                'timestamp' => round(microtime(true) * 1000),
                'data' => $message,
                'code' => $code
            ];
        }
    }

    class Order {
        private $connection;
    
        public function __construct() {
            $db = Database::getInstance();
            $this->connection = $db->getConnection();
        }
    
        public function handleOrder($data) {
            if (!isset($data['apikey']) || !isset($data['action'])) {
                return $this->error("Not all required fields specified for this request type", 400);
            }

            $userId = $this->getUserIdByApiKey($data['apikey']);
            if (!$userId) {
                return $this->error("Invalid API key (Unauthorised)", 401);
            }

            $userType = $this->getUserType($userId);
            if (!$userType) {
                return $this->error("User not found", 404);
            }

            $action = strtolower($data['action']);

            switch ($action) {
                case 'create':
                case 'place':
                    if ($userType !== 'Customer') {
                        return $this->error("Unauthorized: Only Customers can place orders", 403);
                    }
                    return $this->handlePlaceOrder($userId, $data);
                case 'update':
                    return $this->handleUpdateOrder($userId, $userType, $data);
                case 'get':
                    return $this->handleGetStorageOrders($userId, $userType, $data);
                default:
                    return $this->error("Invalid action. Must be 'create', 'update', or 'get'", 400);
            }
        }
    
        private function handlePlaceOrder($customerId, $data = []) {
            $this->connection->begin_transaction();

            try {
                $cartItems = $this->getCartItems($customerId);
                $totalQty = 0;
                foreach ($cartItems as $item) {
                    $totalQty += (int)$item['quantity'];
                }
                if ($totalQty > 7) {
                    throw new Exception("Cannot place order with more than 7 items");
                }
                if (empty($cartItems)) {
                    throw new Exception("Cannot place order with empty cart");
                }

                $deliveryDate = $this->generateRandomDeliveryDate();
                $droneId = isset($data['drone_id']) ? (int)$data['drone_id'] : null;
                $latitude = isset($data['latitude']) ? (float)$data['latitude'] : null;
                $longitude = isset($data['longitude']) ? (float)$data['longitude'] : null;
                $state = isset($data['state']) ? $data['state'] : 'Storage';

                if (isset($data['state']) && !in_array($data['state'], ['Storage', 'Dispatched', 'Delivered'])) {
                    throw new Exception("Invalid state. Must be 'Storage', 'Dispatched', or 'Delivered'");
                }

                $orderId = $this->createOrderWithFields($customerId, $deliveryDate, $droneId, $latitude, $longitude, $state);
                $this->addProductsToOrder($orderId, $cartItems);
                $this->clearCart($customerId);
                $this->connection->commit();

                return [
                    'status' => 'success',
                    'timestamp' => round(microtime(true) * 1000),
                    'data' => [
                        'order_id' => $orderId
                    ],
                    'code' => 200
                ];
            } catch (Exception $e) {
                $this->connection->rollback();
                return $this->error($e->getMessage(), 400);
            }
        }

        private function createOrderWithFields($customerId, $deliveryDate, $droneId, $latitude, $longitude, $state) {
            $stmt = $this->connection->prepare("
                INSERT INTO orders (customer_id, delivery_date, drone_id, latitude, longitude, state) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param(
                "isddds",
                $customerId,
                $deliveryDate,
                $droneId,
                $latitude,
                $longitude,
                $state
            );
            $stmt->execute();
            return $this->connection->insert_id;
        }
    
        private function getCartItems($customerId) {
            $stmt = $this->connection->prepare("
                SELECT product_id, quantity 
                FROM carts 
                WHERE customer_id = ?
            ");
            $stmt->bind_param("i", $customerId);
            $stmt->execute();
            $result = $stmt->get_result();
    
            $items = [];
            while ($row = $result->fetch_assoc()) {
                $items[] = $row;
            }
            return $items;
        }
    
        private function createOrder($customerId) {
            $deliveryDate = $this->generateRandomDeliveryDate();
            
            $stmt = $this->connection->prepare("
                INSERT INTO orders (customer_id, delivery_date) 
                VALUES (?, ?)
            ");
            $stmt->bind_param("is", $customerId, $deliveryDate);
            $stmt->execute();
            return $this->connection->insert_id;
        }

        private function generateRandomDeliveryDate() {
            // Generate random day between 19-25
            $day = rand(19, 25);
            
            // Format as YYYY-MM-DD HH:MM:SS
            return sprintf("2025-05-%02d %02d:%02d:%02d",
                $day,
                rand(8, 20),  // Hours (8am to 8pm)
                rand(0, 59),  // Minutes
                rand(0, 59)   // Seconds
            );
        }
    
        private function addProductsToOrder($orderId, $items) {
            $stmt = $this->connection->prepare("
                INSERT INTO order_products (order_id, product_id, quantity) 
                VALUES (?, ?, ?)
            ");
    
            foreach ($items as $item) {
                $stmt->bind_param("iii", $orderId, $item['product_id'], $item['quantity']);
                $stmt->execute();
            }
        }
    
        private function clearCart($customerId) {
            $stmt = $this->connection->prepare("
                DELETE FROM carts 
                WHERE customer_id = ?
            ");
            $stmt->bind_param("i", $customerId);
            $stmt->execute();
        }
    
        private function getUserIdByApiKey($apiKey) {
            $stmt = $this->connection->prepare("SELECT id FROM users WHERE api_key = ?");
            $stmt->bind_param("s", $apiKey);
            $stmt->execute();
            $result = $stmt->get_result();
            return $result->num_rows ? $result->fetch_assoc()['id'] : null;
        }

        private function error($message, $code) {
            return [
                'status' => 'error',
                'timestamp' => round(microtime(true) * 1000),
                'data' => $message,
                'code' => $code
            ];
        }

        private function handleUpdateOrder($userId, $userType, $data) {
            if (!isset($data['order_id'])) {
                return $this->error("order_id is required for update", 400);
            }

            $orderId = (int)$data['order_id'];
            $updates = [];
            $params = [];

            if ($userType === 'Customer') {
                $stmt = $this->connection->prepare("SELECT id FROM orders WHERE id = ? AND customer_id = ?");
                $stmt->bind_param("ii", $orderId, $userId);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($result->num_rows === 0) {
                    return $this->error("Order not found or Unauthorized request (the order does not belong to this customer)", 404);
                }
            }

            if (isset($data['latitude'])) {
                $updates[] = "latitude = ?";
                $params[] = (float)$data['latitude'];
            }

            if (isset($data['longitude'])) {
                $updates[] = "longitude = ?";
                $params[] = (float)$data['longitude'];
            }

            if (isset($data['state'])) {
                if (!in_array($data['state'], ['Storage', 'Dispatched', 'Delivered'])) {
                    return $this->error("Invalid state. Must be 'Storage', 'Dispatched', or 'Delivered'", 400);
                }
                $updates[] = "state = ?";
                $params[] = $data['state'];
            }

            if (isset($data['drone_id'])) {
                if ($userType !== 'Courier') {
                    return $this->error("Unauthorized: Only Couriers can update drone_id", 403);
                }


                $lat = null;
                $lon = null;
                if (isset($data['latitude']) && isset($data['longitude'])) {
                    $lat = (float)$data['latitude'];
                    $lon = (float)$data['longitude'];
                } else {
                    $stmt = $this->connection->prepare("SELECT latitude, longitude FROM orders WHERE id = ?");
                    $stmt->bind_param("i", $orderId);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    if ($row = $result->fetch_assoc()) {
                        $lat = isset($data['latitude']) ? (float)$data['latitude'] : (float)$row['latitude'];
                        $lon = isset($data['longitude']) ? (float)$data['longitude'] : (float)$row['longitude'];
                    }
                }

                if ($lat !== null && $lon !== null) {
                    if (!$this->isWithin5kmOfHQ($lat, $lon)) {
                        return $this->error("Order is too far away to be delivered by a drone (must be within 5km of HQ)", 400);
                    }
                }

                $updates[] = "drone_id = ?";
                $params[] = $data['drone_id'] === null ? null : (int)$data['drone_id'];
            }

            if (empty($updates)) {
                return $this->error("No fields to update", 400);
            }

            $sql = "UPDATE orders SET " . implode(", ", $updates) . " WHERE id = ?";
            $params[] = $orderId;

            $stmt = $this->connection->prepare($sql);
            if (!$stmt) {
                return $this->error("Database error: failed to prepare statement", 500);
            }

            $types = '';
            foreach ($params as $i => $param) {
                if (is_int($param) || is_null($param)) {
                    $types .= 'i';
                } elseif (is_float($param)) {
                    $types .= 'd';
                } else {
                    $types .= 's';
                }
            }
            $stmt->bind_param($types, ...$params);

            if (!$stmt->execute()) {
                return $this->error("Database error: failed to update order", 500);
            }

            return [
                'status' => 'success',
                'timestamp' => round(microtime(true) * 1000),
                'data' => 'Order updated successfully',
                'code' => 200
            ];
        }

        private function isWithin5kmOfHQ($lat, $long) {
            $hqLat = HQ_LATITUDE;
            $hqLong = HQ_LONGITUDE;
            $earthRadius = 6371;

            $dLat = deg2rad($lat - $hqLat);
            $dLon = deg2rad($long - $hqLong);

            $a = sin($dLat/2) * sin($dLat/2) +
                cos(deg2rad($hqLat)) * cos(deg2rad($lat)) *
                sin($dLon/2) * sin($dLon/2);
            $c = 2 * atan2(sqrt($a), sqrt(1-$a));
            $distance = $earthRadius * $c;

            return $distance <= 5;
        }

        private function handleGetStorageOrders($userId, $userType, $data) {
            try {
                $orders = [];
                if ($userType === 'Courier') {
                    $state = null;
                    if (isset($_POST['state'])) {
                        $state = $_POST['state'];
                    } elseif (isset($_GET['state'])) {
                        $state = $_GET['state'];
                    }

                    if (isset($data['state'])) {
                        $state = $data['state'];
                    }
                    $allowedStates = ['Storage', 'Dispatched', 'Delivered', 'All'];
                    if ($state && !in_array($state, $allowedStates)) {
                        return $this->error("Invalid state filter. Must be 'Storage', 'Dispatched', 'Delivered', or 'All'", 400);
                    }
                    if (!$state) {
                        $state = 'Storage';
                    }
                    if ($state === 'All') {
                        $orderStmt = $this->connection->prepare("
                            SELECT id, customer_id, drone_id, state, delivery_date, created_at, latitude, longitude 
                            FROM orders 
                            ORDER BY created_at DESC
                        ");
                    } else {
                        $orderStmt = $this->connection->prepare("
                            SELECT id, customer_id, drone_id, state, delivery_date, created_at, latitude, longitude 
                            FROM orders 
                            WHERE state = ?
                            ORDER BY created_at DESC
                        ");
                        $orderStmt->bind_param("s", $state);
                    }
                    $orderStmt->execute();
                } else {
                    $orderStmt = $this->connection->prepare("
                        SELECT id, customer_id, drone_id, state, delivery_date, created_at, latitude, longitude 
                        FROM orders 
                        WHERE customer_id = ?
                        ORDER BY created_at DESC
                    ");
                    $orderStmt->bind_param("i", $userId);
                    $orderStmt->execute();
                }

                $orderResult = $orderStmt->get_result();

                while ($order = $orderResult->fetch_assoc()) {
                    $productStmt = $this->connection->prepare("
                        SELECT product_id, quantity 
                        FROM order_products 
                        WHERE order_id = ?
                    ");
                    $productStmt->bind_param("i", $order['id']);
                    $productStmt->execute();
                    $productResult = $productStmt->get_result();

                    $products = [];
                    while ($product = $productResult->fetch_assoc()) {
                        $products[] = [
                            'product_id' => $product['product_id'],
                            'quantity' => $product['quantity']
                        ];
                    }

                    $orders[] = [
                        'order_id' => $order['id'],
                        'customer_id' => $order['customer_id'],
                        'drone_id' => $order['drone_id'],
                        'state' => $order['state'],
                        'delivery_date' => $order['delivery_date'],
                        'created_at' => $order['created_at'],
                        'latitude' => $order['latitude'],
                        'longitude' => $order['longitude'],
                        'products' => $products
                    ];
                }

                return [
                    'status' => 'success',
                    'timestamp' => round(microtime(true) * 1000),
                    'data' => $orders,
                    'code' => 200
                ];
            } catch (Exception $e) {
                return $this->error("Database error: " . $e->getMessage(), 500);
            }
        }

        private function getUserType($userId) {
            $stmt = $this->connection->prepare("SELECT type FROM users WHERE id = ?");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            return $result->num_rows ? $result->fetch_assoc()['type'] : null;
        }
    }// Order class

    class Drone {
        private $connection;

        public function __construct() {
            $db = Database::getInstance();
            $this->connection = $db->getConnection();
        }

        public function handleDrone($data) {
            if (!isset($data['apikey']) || !isset($data['action'])) {
                return $this->error("Not all required fields specified for this request type", 400);
            }

            $userId = $this->getUserIdByApiKey($data['apikey']);
            if (!$userId) {
                return $this->error("Invalid API key (Unauthorised)", 401);
            }

            $userType = $this->getUserType($userId);
            if (!$userType) {
                return $this->error("User not found", 404);
            }

            $action = strtolower($data['action']);

            switch ($action) {
                case 'create':
                    return $this->handleCreateDrone($data);
                case 'update':
                    if ($userType !== 'Courier') {
                        return $this->error("Unauthorized: Only Couriers can update drones", 403);
                    }
                    return $this->handleUpdateDrone($data, $userId);
                case 'get':
                    return $this->handleGetDrones($data);
                case 'move':
                    if ($userType !== 'Courier') {
                        return $this->error("Unauthorized: Only Couriers can move drones", 403);
                    }
                    return $this->handleMoveDrone($data);
                case 'returntohq':
                    if ($userType !== 'Courier') {
                        return $this->error("Unauthorized: Only Couriers can move drones", 403);
                    }
                    if (!isset($data['drone_id'])) {
                        return $this->error("drone_id is required for returnToHQ", 400);
                    }
                    return $this->handleReturnToHQ($data['drone_id']);
                case 'dispatch':
                    if ($userType !== 'Courier') {
                        return $this->error("Unauthorized: Only Couriers can dispatch drones", 403);
                    }
                    if (!isset($data['drone_id']) || !isset($data['order_id'])) {
                        return $this->error("drone_id and order_id are required for dispatch", 400);
                    }
                    return $this->handleDispatch($userId, $data['drone_id'], $data['order_id']);
                case 'deliver':
                    if ($userType !== 'Courier') {
                        return $this->error("Unauthorized: Only Couriers can deliver orders", 403);
                    }
                    if (!isset($data['drone_id'])) {
                        return $this->error("drone_id is required for deliver", 400);
                    }
                    return $this->handleDeliver($userId, $data['drone_id']);
                case 'cancel':
                    if ($userType !== 'Courier') {
                        return $this->error("Unauthorized: Only Couriers can cancel drone deliveries", 403);
                    }
                    if (!isset($data['drone_id'])) {
                        return $this->error("drone_id is required for cancel", 400);
                    }
                    return $this->handleCancel($userId, $data['drone_id']);
                case 'updatealtitude':
                    if ($userType !== 'Courier') {
                        return $this->error("Unauthorized: Only Couriers can update drone altitude", 403);
                    }
                    if (!isset($data['drone_id'])) {
                        return $this->error("drone_id is required for updateAltitude", 400);
                    }
                    return $this->handleUpdateAltitude($userId, $data);
                default:
                    return $this->error("Invalid action. Must be 'create', 'update', 'get', 'move', 'returnToHQ', 'dispatch', 'deliver', 'cancel', or 'updateAltitude'", 400);
            }
        }

        private function handleCreateDrone($data) {
            $userId = $this->getUserIdByApiKey($data['apikey']);
            $userType = $this->getUserType($userId);

            if ($userType !== 'Courier') {
                return $this->error("Unauthorized: Only Couriers can create drones", 403);
            }

            $current_operator_id = null;
            $is_available = true;
            $latest_latitude = HQ_LATITUDE;
            $latest_longitude = HQ_LONGITUDE;
            $altitude = 0;
            $battery_level = 100;
            $state = 'Grounded at HQ';

            if (isset($data['current_operator_id'])) {
                $current_operator_id = $data['current_operator_id'] === null ? null : (int)$data['current_operator_id'];
            }
            if (isset($data['is_available'])) {
                $is_available = (bool)$data['is_available'];
            }
            if (isset($data['latest_latitude'])) {
                $latest_latitude = (float)$data['latest_latitude'];
            }
            if (isset($data['latest_longitude'])) {
                $latest_longitude = (float)$data['latest_longitude'];
            }
            if (isset($data['battery_level'])) {
                $battery = (int)$data['battery_level'];
                if ($battery < 0 || $battery > 100) {
                    return $this->error("Battery level must be between 0 and 100", 400);
                }
                $battery_level = $battery;
            }
            if (isset($data['state'])) {
                if (!in_array($data['state'], ['Grounded at HQ', 'Flying', 'Crashed'])) {
                    return $this->error("Invalid state. Must be 'Grounded at HQ', 'Flying', or 'Crashed'", 400);
                }
                $state = $data['state'];
            }

            $is_available = ($state === 'Grounded at HQ') ? 1 : 0;

            $stmt = $this->connection->prepare("
                INSERT INTO drones (
                    current_operator_id, 
                    is_available, 
                    latest_latitude, 
                    latest_longitude, 
                    altitude, 
                    battery_level,
                    state
                ) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            if (!$stmt) {
                return $this->error("Database error: failed to prepare statement", 500);
            }

            $stmt->bind_param(
                "iddddis",
                $current_operator_id,
                $is_available,
                $latest_latitude,
                $latest_longitude,
                $altitude, 
                $battery_level,
                $state
            );

            if (!$stmt->execute()) {
                return $this->error("Database error: failed to create drone", 500);
            }

            $droneId = $this->connection->insert_id;

            return [
                'status' => 'success',
                'timestamp' => round(microtime(true) * 1000),
                'data' => [
                    'drone_id' => $droneId
                ],
                'code' => 201
            ];
        }

        private function handleReturnToHQ($droneId) {
            $hqLat = HQ_LATITUDE;
            $hqLong = HQ_LONGITUDE;
            $state = 'Grounded at HQ';
            $is_available = 1;
            $battery_level = 100;
            $altitude = 0;

            $stmt = $this->connection->prepare("
                UPDATE drones 
                SET latest_latitude = ?, latest_longitude = ?, state = ?, is_available = ?, current_operator_id = NULL, battery_level = ?, altitude = ?
                WHERE id = ?
            ");
            if (!$stmt) {
                return $this->error("Database error: failed to prepare statement", 500);
            }
            $stmt->bind_param("ddsiiii", $hqLat, $hqLong, $state, $is_available, $battery_level, $altitude, $droneId);
            if (!$stmt->execute()) {
                return $this->error("Database error: failed to update drone", 500);
            }
            return [
                'status' => 'success',
                'timestamp' => round(microtime(true) * 1000),
                'data' => 'Drone returned to HQ',
                'code' => 200
            ];
        }

        private function handleUpdateDrone($data, $courierId) {
            if (!isset($data['drone_id'])) {
                return $this->error("drone_id is required for update", 400);
            }

            $droneId = (int)$data['drone_id'];
            $updates = [];
            $params = [];
            $types = '';

            $stmt = $this->connection->prepare("SELECT state, altitude FROM drones WHERE id = ?");
            $stmt->bind_param("i", $droneId);
            $stmt->execute();
            $result = $stmt->get_result();
            $currentState = null;
            $currentAltitude = null;
            if ($row = $result->fetch_assoc()) {
                $currentState = $row['state'];
                $currentAltitude = $row['altitude'];
            } else {
                return $this->error("Drone not found", 404);
            }

            $latitudeUpdate = array_key_exists('latest_latitude', $data);
            $longitudeUpdate = array_key_exists('latest_longitude', $data);

            if ($latitudeUpdate || $longitudeUpdate) {
                $stmt = $this->connection->prepare("SELECT latest_latitude, latest_longitude FROM drones WHERE id = ?");
                if (!$stmt) {
                    return $this->error("Database error: failed to prepare statement", 500);
                }
                $stmt->bind_param("i", $droneId);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($row = $result->fetch_assoc()) {
                    $latitude = $latitudeUpdate ? (float)$data['latest_latitude'] : (float)$row['latest_latitude'];
                    $longitude = $longitudeUpdate ? (float)$data['latest_longitude'] : (float)$row['latest_longitude'];
                } else {
                    return $this->error("Drone not found", 404);
                }

                if (!$this->isWithin5kmOfHQ($latitude, $longitude)) {
                    return $this->error("Drone cannot move outside a 5km radius from HQ", 400);
                }

                if ($latitudeUpdate) {
                    $updates[] = "latest_latitude = ?";
                    $params[] = $latitude;
                    $types .= 'd';
                }
                if ($longitudeUpdate) {
                    $updates[] = "latest_longitude = ?";
                    $params[] = $longitude;
                    $types .= 'd';
                }
            }

            if (isset($data['current_operator_id'])) {
                return $this->error("You cannot update current_operator_id manually. It is managed by the API.", 400);
            }

            if (isset($data['is_available'])) {
                $updates[] = "is_available = ?";
                $params[] = (bool)$data['is_available'];
                $types .= 'i';
            }

            if (isset($data['altitude'])) {
                $stmt = $this->connection->prepare("SELECT state FROM drones WHERE id = ?");
                $stmt->bind_param("i", $droneId);
                $stmt->execute();
                $resultState = $stmt->get_result();
                $droneStateRow = $resultState->fetch_assoc();
                if (!$droneStateRow || $droneStateRow['state'] !== 'Flying') {
                    return $this->error("Drone must be 'Flying' to update altitude", 400);
                }

                $newAltitude = (float)$data['altitude'];
                if ($newAltitude > 30) {
                    $this->crashDrone($droneId);
                    return [
                        'status' => 'error',
                        'timestamp' => round(microtime(true) * 1000),
                        'data' => 'Drone crashed: altitude exceeded 30 meters',
                        'code' => 400
                    ];
                }
                if ($newAltitude < 0) {
                    return $this->error("Altitude cannot be negative", 400);
                }
                $updates[] = "altitude = ?";
                $params[] = $newAltitude;
                $types .= 'd';
            }

            if (isset($data['battery_level'])) {
                $battery = (int)$data['battery_level'];
                if ($battery < 0 || $battery > 100) {
                    return $this->error("Battery level must be between 0 and 100", 400);
                }
                $updates[] = "battery_level = ?";
                $params[] = $battery;
                $types .= 'i';
            }

            if (isset($data['state'])) {
                $newState = $data['state'];
                if (!in_array($newState, ['Grounded at HQ', 'Flying', 'Crashed'])) {
                    return $this->error("Invalid state. Must be 'Grounded at HQ', 'Flying', or 'Crashed'", 400);
                }
                if ($newState === 'Crashed') {
                    $this->crashDrone($droneId);
                    return [
                        'status' => 'error',
                        'timestamp' => round(microtime(true) * 1000),
                        'data' => 'Drone crashed',
                        'code' => 400
                    ];
                }
                if ($newState === 'Grounded at HQ') {
                    return $this->handleReturnToHQ($droneId);
                }
                $updates[] = "state = ?";
                $params[] = $newState;
                $types .= 's';

                if ($newState === 'Flying') {
                    $updates[] = "is_available = ?";
                    $params[] = 0;
                    $types .= 'i';
                    $updates[] = "current_operator_id = ?";
                    $params[] = $courierId;
                    $types .= 'i';
                }
            }

            if (empty($updates)) {
                return $this->error("No fields to update", 400);
            }

            $sql = "UPDATE drones SET " . implode(", ", $updates) . " WHERE id = ?";
            $params[] = $droneId;
            $types .= 'i';

            $stmt = $this->connection->prepare($sql);
            if (!$stmt) {
                return $this->error("Database error: failed to prepare statement", 500);
            }

            $stmt->bind_param($types, ...$params);

            if (!$stmt->execute()) {
                return $this->error("Database error: failed to update drone", 500);
            }

            return [
                'status' => 'success',
                'timestamp' => round(microtime(true) * 1000),
                'data' => 'Drone updated successfully',
                'code' => 200
            ];
        }

        private function handleGetDrones($data = []) {
            try {
                $userId = $this->getUserIdByApiKey($data['apikey']);
                $userType = $this->getUserType($userId);

                $drones = [];
                $droneIdFilter = isset($data['drone_id']) ? (int)$data['drone_id'] : null;

                if ($userType === 'Courier') {
                    if ($droneIdFilter !== null) {
                        $stmt = $this->connection->prepare("
                            SELECT id, current_operator_id, is_available, latest_latitude, latest_longitude, altitude, battery_level, created_at, updated_at 
                            FROM drones
                            WHERE id = ?
                        ");
                        if (!$stmt) {
                            return $this->error("Database error: failed to prepare statement: " . $this->connection->error, 500);
                        }
                        $stmt->bind_param("i", $droneIdFilter);
                    } else {
                        $stmt = $this->connection->prepare("
                            SELECT id, current_operator_id, is_available, latest_latitude, latest_longitude, altitude, battery_level, created_at, updated_at 
                            FROM drones
                            ORDER BY id ASC
                        ");
                        if (!$stmt) {
                            return $this->error("Database error: failed to prepare statement: " . $this->connection->error, 500);
                        }
                    }
                } else {
                    if ($droneIdFilter !== null) {
                        $stmt = $this->connection->prepare("
                            SELECT d.id, d.current_operator_id, d.is_available, d.latest_latitude, d.latest_longitude, d.altitude, d.battery_level, d.created_at, d.updated_at
                            FROM drones d
                            INNER JOIN orders o ON o.drone_id = d.id
                            WHERE o.customer_id = ? AND d.id = ?
                        ");
                        if (!$stmt) {
                            return $this->error("Database error: failed to prepare statement: " . $this->connection->error, 500);
                        }
                        $stmt->bind_param("ii", $userId, $droneIdFilter);
                    } else {
                        $stmt = $this->connection->prepare("
                            SELECT d.id, d.current_operator_id, d.is_available, d.latest_latitude, d.latest_longitude, d.altitude, d.battery_level, d.created_at, d.updated_at
                            FROM drones d
                            INNER JOIN orders o ON o.drone_id = d.id
                            WHERE o.customer_id = ?
                            GROUP BY d.id
                            ORDER BY d.id ASC
                        ");
                        if (!$stmt) {
                            return $this->error("Database error: failed to prepare statement: " . $this->connection->error, 500);
                        }
                        $stmt->bind_param("i", $userId);
                    }
                }
                $stmt->execute();
                $result = $stmt->get_result();

                while ($row = $result->fetch_assoc()) {
                    $drones[] = [
                        'drone_id' => $row['id'],
                        'current_operator_id' => $row['current_operator_id'],
                        'is_available' => (bool)$row['is_available'],
                        'latest_latitude' => $row['latest_latitude'],
                        'latest_longitude' => $row['latest_longitude'],
                        'altitude' => $row['altitude'],
                        'battery_level' => $row['battery_level'],
                        'created_at' => $row['created_at'],
                        'updated_at' => $row['updated_at']
                    ];
                }

                return [
                    'status' => 'success',
                    'timestamp' => round(microtime(true) * 1000),
                    'data' => $drones,
                    'code' => 200
                ];
            } catch (Exception $e) {
                return $this->error("Database error: " . $e->getMessage(), 500);
            }
        }

        private function handleMoveDrone($data) {
            if (!isset($data['drone_id'])) {
                return $this->error("drone_id is required for move", 400);
            }
            if (!isset($data['direction'])) {
                return $this->error("direction is required for move", 400);
            }

            $droneId = (int)$data['drone_id'];
            $direction = strtolower($data['direction']);
            $distance = isset($data['distance']) ? (float)$data['distance'] : 0.0001;

            if (!in_array($direction, ['up', 'down', 'left', 'right'])) {
                return $this->error("Invalid direction. Must be 'up', 'down', 'left', or 'right'", 400);
            }

            $stmt = $this->connection->prepare("SELECT latest_latitude, latest_longitude, state FROM drones WHERE id = ?");
            if (!$stmt) {
                return $this->error("Database error: failed to prepare statement", 500);
            }
            $stmt->bind_param("i", $droneId);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows === 0) {
                return $this->error("Drone not found", 404);
            }
            $drone = $result->fetch_assoc();

            if ($drone['state'] !== 'Flying') {
                return $this->error("Drone must be 'Flying' to move", 400);
            }

            $latitude = (float)$drone['latest_latitude'];
            $longitude = (float)$drone['latest_longitude'];

            switch ($direction) {
                case 'up':
                    $latitude += $distance;
                    break;
                case 'down':
                    $latitude -= $distance;
                    break;
                case 'right':
                    $longitude += $distance;
                    break;
                case 'left':
                    $longitude -= $distance;
                    break;
            }

            if (!$this->isWithin5kmOfHQ($latitude, $longitude)) {
                return $this->error("Drone cannot move outside a 5km radius from HQ", 400);
            }

            $updateStmt = $this->connection->prepare("UPDATE drones SET latest_latitude = ?, latest_longitude = ? WHERE id = ?");
            if (!$updateStmt) {
                return $this->error("Database error: failed to prepare update statement", 500);
            }
            $updateStmt->bind_param("ddi", $latitude, $longitude, $droneId);
            if (!$updateStmt->execute()) {
                return $this->error("Database error: failed to update drone position", 500);
            }

            $selectStmt = $this->connection->prepare("SELECT id, current_operator_id, is_available, latest_latitude, latest_longitude, altitude, battery_level, created_at, updated_at FROM drones WHERE id = ?");
            if (!$selectStmt) {
                return $this->error("Database error: failed to prepare select statement", 500);
            }
            $selectStmt->bind_param("i", $droneId);
            $selectStmt->execute();
            $result = $selectStmt->get_result();
            if ($result->num_rows === 0) {
                return $this->error("Drone not found after move", 404);
            }
            $row = $result->fetch_assoc();

            return [
                'status' => 'success',
                'timestamp' => round(microtime(true) * 1000),
                'data' => [
                    'drone_id' => $row['id'],
                    'current_operator_id' => $row['current_operator_id'],
                    'is_available' => (bool)$row['is_available'],
                    'latest_latitude' => $row['latest_latitude'],
                    'latest_longitude' => $row['latest_longitude'],
                    'altitude' => $row['altitude'],
                    'battery_level' => $row['battery_level'],
                    'created_at' => $row['created_at'],
                    'updated_at' => $row['updated_at']
                ],
                'code' => 200
            ];
        }

        private function handleDispatch($courierId, $droneId, $orderId) {
            $stmt = $this->connection->prepare("SELECT state, is_available FROM drones WHERE id = ?");
            $stmt->bind_param("i", $droneId);
            $stmt->execute();
            $drone = $stmt->get_result()->fetch_assoc();
            if (!$drone) {
                return $this->error("Drone not found", 404);
            }
            if ($drone['state'] !== 'Grounded at HQ' || !$drone['is_available']) {
                return $this->error("Drone must be 'Grounded at HQ' and available to dispatch", 400);
            }

            $stmt = $this->connection->prepare("SELECT state, drone_id, latitude, longitude FROM orders WHERE id = ?");
            $stmt->bind_param("i", $orderId);
            $stmt->execute();
            $order = $stmt->get_result()->fetch_assoc();
            if (!$order) {
                return $this->error("Order not found", 404);
            }
            if ($order['state'] !== 'Storage') {
                return $this->error("Order must be in 'Storage' state to dispatch", 400);
            }
            if (!is_null($order['drone_id'])) {
                return $this->error("Order already has a drone assigned", 400);
            }
            if ($order['latitude'] === null || $order['longitude'] === null) {
                return $this->error("Order does not have a delivery location set", 400);
            }
            if (!$this->isWithin5kmOfHQ((float)$order['latitude'], (float)$order['longitude'])) {
                return $this->error("Order's delivery location is too far from HQ (must be within 5km) for drone dispatch", 400);
            }

            $this->connection->begin_transaction();
            try {
                $stmt = $this->connection->prepare("UPDATE orders SET state = 'Dispatched', drone_id = ? WHERE id = ?");
                $stmt->bind_param("ii", $droneId, $orderId);
                $stmt->execute();

                $stmt = $this->connection->prepare("UPDATE drones SET state = 'Flying', is_available = 0, current_operator_id = ?, altitude = 10 WHERE id = ?");
                $stmt->bind_param("ii", $courierId, $droneId);
                $stmt->execute();

                $this->connection->commit();
                return [
                    'status' => 'success',
                    'timestamp' => round(microtime(true) * 1000),
                    'data' => 'Drone dispatched and assigned to order',
                    'code' => 200
                ];
            } catch (Exception $e) {
                $this->connection->rollback();
                return $this->error("Failed to dispatch drone: " . $e->getMessage(), 500);
            }
        }

        private function handleDeliver($courierId, $droneId) {
            $stmt = $this->connection->prepare("SELECT state, latest_latitude, latest_longitude FROM drones WHERE id = ?");
            $stmt->bind_param("i", $droneId);
            $stmt->execute();
            $drone = $stmt->get_result()->fetch_assoc();
            if (!$drone) {
                return $this->error("Drone not found", 404);
            }
            if ($drone['state'] !== 'Flying') {
                return $this->error("Drone must be 'Flying' to deliver", 400);
            }

            $stmt = $this->connection->prepare("SELECT id, state, latitude, longitude FROM orders WHERE drone_id = ? AND state = 'Dispatched'");
            $stmt->bind_param("i", $droneId);
            $stmt->execute();
            $order = $stmt->get_result()->fetch_assoc();
            if (!$order) {
                return $this->error("No dispatched order assigned to this drone", 400);
            }

            $droneLat = (float)$drone['latest_latitude'];
            $droneLong = (float)$drone['latest_longitude'];
            $orderLat = (float)$order['latitude'];
            $orderLong = (float)$order['longitude'];

            $distance = $this->distanceBetween($droneLat, $droneLong, $orderLat, $orderLong);
            if ($distance >= 10) {
                return $this->error("Drone must be within 10 meters of the delivery location to deliver the order (current distance to delivery location: {$distance}m). Drone lat: {$droneLat} Order lat: {$orderLat} Drone long: {$droneLong} Order long: {$orderLong}", 400);
            }

            $this->connection->begin_transaction();
            try {
                $now = date('Y-m-d H:i:s');
                $stmt = $this->connection->prepare("UPDATE orders SET state = 'Delivered', drone_id = NULL, delivery_date = ? WHERE id = ?");
                $stmt->bind_param("si", $now, $order['id']);
                $stmt->execute();
    
                // $stmt = $this->connection->prepare("UPDATE drones SET state = 'Grounded at HQ', is_available = 0 WHERE id = ?");
                // $stmt->bind_param("i", $droneId);
                // $stmt->execute();

                $this->connection->commit();
                return [
                    'status' => 'success',
                    'timestamp' => round(microtime(true) * 1000),
                    'data' => 'Order delivered successfully',
                    'code' => 200
                ];
            } catch (Exception $e) {
                $this->connection->rollback();
                return $this->error("Failed to deliver order: " . $e->getMessage(), 500);
            }
        }

        private function handleCancel($courierId, $droneId) {
            $stmt = $this->connection->prepare("SELECT state FROM drones WHERE id = ?");
            $stmt->bind_param("i", $droneId);
            $stmt->execute();
            $drone = $stmt->get_result()->fetch_assoc();
            if (!$drone) {
                return $this->error("Drone not found", 404);
            }

            $stmt = $this->connection->prepare("SELECT id, state FROM orders WHERE drone_id = ?");
            $stmt->bind_param("i", $droneId);
            $stmt->execute();
            $order = $stmt->get_result()->fetch_assoc();

            $this->connection->begin_transaction();
            try {
                if ($order && $order['state'] !== 'Delivered') {
                    $stmt = $this->connection->prepare("UPDATE orders SET state = 'Storage', drone_id = NULL WHERE id = ?");
                    $stmt->bind_param("i", $order['id']);
                    $stmt->execute();
                }
                $hqLat = HQ_LATITUDE;
                $hqLong = HQ_LONGITUDE;
                $stmt = $this->connection->prepare("UPDATE drones SET latest_latitude = ?, latest_longitude = ?, state = 'Grounded at HQ', is_available = 1, current_operator_id = NULL, battery_level = 100, altitude = 0 WHERE id = ?");
                $stmt->bind_param("ddi", $hqLat, $hqLong, $droneId);
                $stmt->execute();

                $this->connection->commit();
                return [
                    'status' => 'success',
                    'timestamp' => round(microtime(true) * 1000),
                    'data' => 'Drone cancelled and returned to HQ',
                    'code' => 200
                ];
            } catch (Exception $e) {
                $this->connection->rollback();
                return $this->error("Failed to cancel drone delivery: " . $e->getMessage(), 500);
            }
        }

        private function handleUpdateAltitude($courierId, $data) {
            $droneId = (int)$data['drone_id'];
            $stmt = $this->connection->prepare("SELECT altitude, state FROM drones WHERE id = ?");
            $stmt->bind_param("i", $droneId);
            $stmt->execute();
            $result = $stmt->get_result();
            if (!$result->num_rows) {
                return $this->error("Drone not found", 404);
            }
            $drone = $result->fetch_assoc();
            $currentAltitude = (float)$drone['altitude'];
            $currentState = $drone['state'];
            if ($currentState !== 'Flying') {
                return $this->error("Drone must be 'Flying' to update altitude", 400);
            }

            $newAltitude = null;
            if (isset($data['increase'])) {
                if ($currentAltitude + (float)$data['increase'] < 0) {
                    return $this->error("Altitude cannot be negative", 400);
                }
                $increase = (float)$data['increase'];
                $newAltitude = $currentAltitude + $increase;
            } elseif (isset($data['altitude'])) {
                if ($data['altitude'] < 0) {
                    return $this->error("Altitude cannot be negative", 400);
                }
                $newAltitude = (float)$data['altitude'];
            } else {
                return $this->error("Must provide 'increase' or 'altitude' value", 400);
            }

            if ($newAltitude > 30) {
                $this->crashDrone($droneId);
                return [
                    'status' => 'error',
                    'timestamp' => round(microtime(true) * 1000),
                    'data' => 'Drone crashed: altitude exceeded 30 meters',
                    'code' => 400
                ];
            }

            $stmt = $this->connection->prepare("UPDATE drones SET altitude = ? WHERE id = ?");
            $stmt->bind_param("di", $newAltitude, $droneId);
            if (!$stmt->execute()) {
                return $this->error("Database error: failed to update altitude", 500);
            }

            return [
                'status' => 'success',
                'timestamp' => round(microtime(true) * 1000),
                'data' => "Drone altitude updated to $newAltitude (was $currentAltitude)",
                'code' => 200
            ];
        }

        private function crashDrone($droneId) {
            $stmt = $this->connection->prepare("UPDATE drones SET state = 'Crashed', is_available = 0, altitude = 0 WHERE id = ?");
            $stmt->bind_param("i", $droneId);
            $stmt->execute();

            $stmt = $this->connection->prepare("SELECT id, state FROM orders WHERE drone_id = ?");
            $stmt->bind_param("i", $droneId);
            $stmt->execute();
            $order = $stmt->get_result()->fetch_assoc();
            if ($order && $order['state'] !== 'Delivered') {
                $stmt = $this->connection->prepare("UPDATE orders SET state = 'Storage', drone_id = NULL WHERE id = ?");
                $stmt->bind_param("i", $order['id']);
                $stmt->execute();
            }
        }

        private function getUserIdByApiKey($apiKey) {
            $stmt = $this->connection->prepare("SELECT id FROM users WHERE api_key = ?");
            $stmt->bind_param("s", $apiKey);
            $stmt->execute();
            $result = $stmt->get_result();
            return $result->num_rows ? $result->fetch_assoc()['id'] : null;
        }

        private function getUserType($userId) {
            $stmt = $this->connection->prepare("SELECT type FROM users WHERE id = ?");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            return $result->num_rows ? $result->fetch_assoc()['type'] : null;
        }

        private function isWithin5kmOfHQ($lat, $long) {
            $hqLat = HQ_LATITUDE;
            $hqLong = HQ_LONGITUDE;
            $earthRadius = 6371;

            $dLat = deg2rad($lat - $hqLat);
            $dLon = deg2rad($long - $hqLong);

            $a = sin($dLat/2) * sin($dLat/2) +
                cos(deg2rad($hqLat)) * cos(deg2rad($lat)) *
                sin($dLon/2) * sin($dLon/2);
            $c = 2 * atan2(sqrt($a), sqrt(1-$a));
            $distance = $earthRadius * $c;

            return $distance <= 5;
        }

        private function distanceBetween($lat1, $lon1, $lat2, $lon2) {
            $earthRadius = 6371000;
            $dLat = deg2rad($lat2 - $lat1);
            $dLon = deg2rad($lon2 - $lon1);
            $a = sin($dLat/2) * sin($dLat/2) +
                cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
                sin($dLon/2) * sin($dLon/2);
            $c = 2 * atan2(sqrt($a), sqrt(1-$a));
            return $earthRadius * $c;
        }

        private function error($message, $code) {
            return [
                'status' => 'error',
                'timestamp' => round(microtime(true) * 1000),
                'data' => $message,
                'code' => $code
            ];
        }
    }// Drone class

/////////////////////
// MAIN API LOGIC //
///////////////////
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    ResponseAPI::send(['status' => 'error', 'timestamp' => round(microtime(true) * 1000),'data' => 'Invalid JSON format','code' => 400], 400);
}

switch($data['type']) {
    case 'Register':
        $user = new User($conn);
        try {
            $result = $user->registerUser($data);
            $code = isset($result['code']) ? $result['code'] : 200;
            ResponseAPI::send($result, $code);
        } catch (Exception $error) {
            ResponseAPI::send([
                'status' => 'error',
                'timestamp' => round(microtime(true) * 1000),
                'data' => 'Unhandled server error: ' . $error->getMessage(),
                'code' => 500
            ], 500);
        }
        break;

    case 'GetAllProducts':
        $products = new Products($conn);
        if ($products->validateInputData($data) == false){
            ResponseAPI::send([
                'status' => 'error',
                'timestamp' => round(microtime(true) * 1000),
                'data' => 'Not all required fields specified for this request type',
                'code' => 400
            ], 400);
        }
        if ($products->checkIfAPIKeyExists($data['apikey']) == false){
            ResponseAPI::send([
                'status' => 'error',
                'timestamp' => round(microtime(true) * 1000),
                'data' => 'Invalid API Key specified (Unauthorised)',
                'code' => 401
            ], 401);
        }
        try {
            $result = $products->getAllProducts($data);
            $code = isset($result['code']) ? $result['code'] : 200;
            ResponseAPI::send($result, $code);
        } catch (Exception $error) {
            ResponseAPI::send([
                'status' => 'error',
                'timestamp' => round(microtime(true) * 1000),
                'data' => 'Unhandled server error: ' . $error->getMessage(),
                'code' => 500
            ], 500);
        }
        break;

    case 'Login':
        $user = new User($conn);
        try {
            $result = $user->loginUser($data);
            $code = isset($result['code']) ? $result['code'] : 200;
            ResponseAPI::send($result, $code);
        } catch (Exception $error) {
            ResponseAPI::send([
                'status' => 'error',
                'timestamp' => round(microtime(true) * 1000),
                'data' => 'Unhandled server error: ' . $error->getMessage(),
                'code' => 500
            ], 500);
        }
        break;

    case 'Preferences':
        $preferences = new Preferences($conn);
        try {
            $result = $preferences->handlePreferences($data);
            $code = isset($result['code']) ? $result['code'] : 200;
            ResponseAPI::send($result, $code);
        } catch (Exception $error) {
            ResponseAPI::send([
                'status' => 'error',
                'timestamp' => round(microtime(true) * 1000),
                'data' => 'Unhandled server error: ' . $error->getMessage(),
                'code' => 500
            ], 500);
        }
        break;

    case 'Wishlist':
        $wishlist = new Wishlist($conn);
        try {
            $result = $wishlist->handleWishlist($data);
            $code = isset($result['code']) ? $result['code'] : 200;
            ResponseAPI::send($result, $code);
        } catch (Exception $error) {
            ResponseAPI::send([
                'status' => 'error',
                'timestamp' => round(microtime(true) * 1000),
                'data' => 'Unhandled server error: ' . $error->getMessage(),
                'code' => 500
            ], 500);
        }
        break;

    case 'Cart':
        $cart = new Cart($conn);
        try {
            $result = $cart->handleCart($data);
            $code = isset($result['code']) ? $result['code'] : 200;
            ResponseAPI::send($result, $code);
        } catch (Exception $error) {
            ResponseAPI::send([
                'status' => 'error',
                'timestamp' => round(microtime(true) * 1000),
                'data' => 'Unhandled server error: ' . $error->getMessage(),
                'code' => 500
            ], 500);
        }
        break;

    case 'Order':
        $order = new Order($conn);
        try {
            $result = $order->handleOrder($data);
            $code = isset($result['code']) ? $result['code'] : 200;
            ResponseAPI::send($result, $code);
        } catch (Exception $error) {
            ResponseAPI::send([
                'status' => 'error',
                'timestamp' => round(microtime(true) * 1000),
                'data' => 'Unhandled server error: ' . $error->getMessage(),
                'code' => 500
            ], 500);
        }
        break;

    case 'Drone':
        $drone = new Drone();
        try {
            $result = $drone->handleDrone($data);
            $code = isset($result['code']) ? $result['code'] : 200;
            ResponseAPI::send($result, $code);
        } catch (Exception $error) {
            ResponseAPI::send([
                'status' => 'error',
                'timestamp' => round(microtime(true) * 1000),
                'data' => 'Unhandled server error: ' . $error->getMessage(),
                'code' => 500
            ], 500);
        }
        break;

    default:
        ResponseAPI::send(['status' => 'error', 'timestamp' => round(microtime(true) * 1000), 'data' => 'Unkown request type'], 400);
}//switch statement


?>