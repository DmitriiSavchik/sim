<?php

$dsn = 'mysql:host=localhost;dbname=sim_db;';
$username = 'root';
$password = '';

try {
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, 
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    die("Could not connect to the database: " . $e->getMessage());
}

$simid_from = $_POST['simid_from'];
$simid_to = $_POST['simid_to'];
$amount = (float) $_POST['amount'];
$comment = $_POST['comment'];

if ($amount <= 0) {
    die("Amount must be greater than zero.");
}

$pdo->beginTransaction();

try {
    $query = "SELECT iccid, balance FROM sim WHERE RIGHT(iccid, 6) = :simid";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute(['simid' => $simid_from]);
    $sim_from = $stmt->fetch();
    
    if (!$sim_from) {
        throw new Exception("SIM card with id $simid_from not found.");
    }
    
    $stmt->execute(['simid' => $simid_to]);
    $sim_to = $stmt->fetch();
    
    if (!$sim_to) {
        throw new Exception("SIM card with id $simid_to not found.");
    }

    if ($sim_from['balance'] < $amount) {
        throw new Exception("Insufficient balance on SIM card $simid_from.");
    }

    $query = "INSERT INTO sim_balance_away (iccid, amount, comment) VALUES (:iccid, :amount, :comment)";
    $stmt = $pdo->prepare($query);
    $stmt->execute([
        'iccid' => $sim_from['iccid'],
        'amount' => $amount,
        'comment' => $comment
    ]);

    $query = "INSERT INTO sim_balance_come (iccid, amount, comment) VALUES (:iccid, :amount, :comment)";
    $stmt = $pdo->prepare($query);
    $stmt->execute([
        'iccid' => $sim_to['iccid'],
        'amount' => $amount,
        'comment' => $comment
    ]);

    $pdo->commit();

    echo "Balance successfully transferred from SIM $simid_from to SIM $simid_to.";

} catch (Exception $e) {
    $pdo->rollBack();
    echo "Failed to transfer balance: " . $e->getMessage();
}
?>
