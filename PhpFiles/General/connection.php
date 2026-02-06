<?php
$host = "srv1986.hstgr.io";
$user = "u682055666_thesiscaps";
$pass = "ThesisCaps123.";
$dbname = "u682055666_testingBrgySJ";

// Create connection
$conn = new mysqli($host, $user, $pass, $dbname);

// Check connection
if ($conn->connect_error) {
    // If connection fails, stop execution and show error
    die("Connection Failed: " . $conn->connect_error);
} else {
    // Optional: Uncomment this line if you want confirmation of success
    //echo "✅ Connected successfully to $dbname";
}
?>
