<?php
// Assuming $pdo is globally available from config.php or passed as argument
function dbQuery($sql, $params = []) {
    global $pdo; // Ensure $pdo is accessible
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

function dbFetch($sql, $params = []) {
    return dbQuery($sql, $params)->fetch();
}

function dbFetchAll($sql, $params = []) {
    return dbQuery($sql, $params)->fetchAll();
}

function dbInsert($table, $data) {
    global $pdo; // Ensure $pdo is accessible here
    $columns = implode(', ', array_keys($data));
    $placeholders = implode(', ', array_fill(0, count($data), '?'));
    $sql = "INSERT INTO $table ($columns) VALUES ($placeholders)";
    dbQuery($sql, array_values($data));
    return $pdo->lastInsertId(); // This assumes $pdo is the direct PDO object
}

function dbUpdate($table, $data, $where, $params = []) {
    $set = implode(' = ?, ', array_keys($data)) . ' = ?';
    $sql = "UPDATE $table SET $set WHERE $where";
    return dbQuery($sql, array_merge(array_values($data), $params))->rowCount();
}
?>