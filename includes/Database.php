<?php
class Database {
    private static $instance = null;
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {}
    
    public function createUserData($username, $email, $password, $role = 'individual') {
        $user_dir = DATA_PATH . $username;
        
        if (!is_dir($user_dir)) {
            mkdir($user_dir, 0755, true);
        }
        
        $user_data = [
            'profile' => [
                'username' => $username,
                'email' => $email,
                'password' => password_hash($password, PASSWORD_DEFAULT),
                'role' => $role,
                'created_at' => date('Y-m-d H:i:s'),
                'last_login' => null,
                'profile_photo' => null,
                'full_name' => '',
                'description' => '',
                'gender' => ''
            ],
            'finance' => [
                'income' => [],
                'expense' => [],
                'categories' => [
                    'income' => ['Salary', 'Business', 'Investment', 'Other'],
                    'expense' => ['Food', 'Transport', 'Housing', 'Entertainment', 'Health', 'Education', 'Other']
                ]
            ],
            'members' => [],
            'settings' => [
                'currency' => 'IDR',
                'language' => 'id',
                'notifications' => [
                    'email_login' => true,
                    'email_transaction' => true,
                    'email_report' => false
                ]
            ]
        ];
        
        $data_file = $user_dir . '/data.json';
        return file_put_contents($data_file, json_encode($user_data, JSON_PRETTY_PRINT), LOCK_EX) !== false;
    }
    
    public function userExists($username) {
        $data_file = DATA_PATH . $username . '/data.json';
        return file_exists($data_file);
    }
    
    public function emailExists($email) {
        $users = glob(DATA_PATH . '*/data.json');
        
        foreach ($users as $user_file) {
            $data = json_decode(file_get_contents($user_file), true);
            if (isset($data['profile']['email']) && $data['profile']['email'] === $email) {
                return true;
            }
        }
        
        return false;
    }
    
    public function authenticateUser($username, $password) {
        $data_file = DATA_PATH . $username . '/data.json';
        
        if (!file_exists($data_file)) {
            return false;
        }
        
        $data = json_decode(file_get_contents($data_file), true);
        
        if (isset($data['profile']['password']) && password_verify($password, $data['profile']['password'])) {
            return $data;
        }
        
        return false;
    }
    
    public function getUserData($username) {
        $data_file = DATA_PATH . $username . '/data.json';
        
        if (!file_exists($data_file)) {
            return null;
        }
        
        $data = json_decode(file_get_contents($data_file), true);
        return $data;
    }
    
    public function saveUserData($username, $data) {
        $user_dir = DATA_PATH . $username;
        
        if (!is_dir($user_dir)) {
            mkdir($user_dir, 0755, true);
        }
        
        $data_file = $user_dir . '/data.json';
        return file_put_contents($data_file, json_encode($data, JSON_PRETTY_PRINT), LOCK_EX) !== false;
    }
    
    public function addTransaction($username, $type, $data) {
        $user_data = $this->getUserData($username);
        
        if (!$user_data) {
            return false;
        }
        
        $transaction = [
            'id' => uniqid(),
            'date' => $data['date'],
            'amount' => floatval($data['amount']),
            'category' => $data['category'],
            'description' => $data['description'],
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        $user_data['finance'][$type][] = $transaction;
        
        return $this->saveUserData($username, $user_data);
    }

    public function getTransactionById($username, $type, $transaction_id) {
        $transactions = $this->getTransactions($username, $type);

        foreach ($transactions as $transaction) {
            if (($transaction['id'] ?? '') === $transaction_id) {
                return $transaction;
            }
        }

        return null;
    }
    
    public function deleteTransaction($username, $type, $transaction_id) {
        $user_data = $this->getUserData($username);
        
        if (!$user_data) {
            return false;
        }
        
        $user_data['finance'][$type] = array_filter(
            $user_data['finance'][$type],
            function($transaction) use ($transaction_id) {
                return $transaction['id'] !== $transaction_id;
            }
        );
        
        $user_data['finance'][$type] = array_values($user_data['finance'][$type]);
        
        return $this->saveUserData($username, $user_data);
    }

    public function updateTransaction($username, $type, $transaction_id, $data) {
        $user_data = $this->getUserData($username);

        if (!$user_data || !isset($user_data['finance'][$type])) {
            return false;
        }

        foreach ($user_data['finance'][$type] as $index => $transaction) {
            if (($transaction['id'] ?? '') === $transaction_id) {
                $user_data['finance'][$type][$index]['date'] = $data['date'];
                $user_data['finance'][$type][$index]['amount'] = floatval($data['amount']);
                $user_data['finance'][$type][$index]['category'] = $data['category'];
                $user_data['finance'][$type][$index]['description'] = $data['description'];
                return $this->saveUserData($username, $user_data);
            }
        }

        return false;
    }
    
    public function getTransactions($username, $type, $start_date = null, $end_date = null) {
        $user_data = $this->getUserData($username);
        
        if (!$user_data || !isset($user_data['finance'][$type])) {
            return [];
        }
        
        $transactions = $user_data['finance'][$type];
        
        if ($start_date && $end_date) {
            $transactions = array_filter($transactions, function($transaction) use ($start_date, $end_date) {
                return $transaction['date'] >= $start_date && $transaction['date'] <= $end_date;
            });
        }
        
        return array_values($transactions);
    }
    
    public function getFinancialSummary($username, $start_date = null, $end_date = null) {
        $income = $this->getTransactions($username, 'income', $start_date, $end_date);
        $expense = $this->getTransactions($username, 'expense', $start_date, $end_date);
        
        $total_income = array_sum(array_column($income, 'amount'));
        $total_expense = array_sum(array_column($expense, 'amount'));
        $balance = $total_income - $total_expense;
        
        return [
            'total_income' => $total_income,
            'total_expense' => $total_expense,
            'balance' => $balance,
            'income_count' => count($income),
            'expense_count' => count($expense)
        ];
    }

    public function addCategory($username, $type, $category_name) {
        $user_data = $this->getUserData($username);

        if (!$user_data || !in_array($type, ['income', 'expense'], true)) {
            return false;
        }

        $category_name = trim($category_name);
        if ($category_name === '') {
            return false;
        }

        if (!isset($user_data['finance']['categories'][$type]) || !is_array($user_data['finance']['categories'][$type])) {
            $user_data['finance']['categories'][$type] = [];
        }

        foreach ($user_data['finance']['categories'][$type] as $existing) {
            if (strcasecmp($existing, $category_name) === 0) {
                return true;
            }
        }

        $user_data['finance']['categories'][$type][] = $category_name;
        sort($user_data['finance']['categories'][$type], SORT_NATURAL | SORT_FLAG_CASE);

        return $this->saveUserData($username, $user_data);
    }

    public function deleteCategory($username, $type, $category_name) {
        $user_data = $this->getUserData($username);

        if (!$user_data || !in_array($type, ['income', 'expense'], true)) {
            return false;
        }

        if (empty($user_data['finance']['categories'][$type])) {
            return false;
        }

        $filtered = array_values(array_filter(
            $user_data['finance']['categories'][$type],
            function ($existing) use ($category_name) {
                return strcasecmp($existing, $category_name) !== 0;
            }
        ));

        $user_data['finance']['categories'][$type] = $filtered;
        return $this->saveUserData($username, $user_data);
    }
}
?>
