<?php


class Analytics
{
    private $conn;

    public function __construct($conn)
    {
        $this->conn = $conn;
    }

    // Example method: get total users
    public function getTotalUsers()
    {
        $sql = "SELECT COUNT(*) as count FROM users WHERE status = 'active'";
        $result = $this->conn->query($sql);
        if ($result) {
            $row = $result->fetch_assoc();
            return $row['count'];
        }
        return 0;
    }

    // Add more analytics methods as needed
}