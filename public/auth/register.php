<?php
require_once __DIR__ . '/../../app/bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonResponse(405, ['ok'=>false,'message'=>'Method tidak diizinkan.']);
verifyCsrf();
$data = requestData();
$name = trim((string)($data['name'] ?? ''));
$email = strtolower(trim((string)($data['email'] ?? '')));
$phone = trim((string)($data['phone'] ?? ''));
$password = (string)($data['password'] ?? '');

if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) ||
    !preg_match('/^[0-9+ -]{9,20}$/', $phone) || strlen($password) < 8) {
    jsonResponse(422, ['ok'=>false,'message'=>'Periksa nama, email, nomor telepon, dan password (minimal 8 karakter).']);
}
$hash = password_hash($password, PASSWORD_DEFAULT);
try {
    $stmt = $pdo->prepare('INSERT INTO users(name,email,phone,password_hash,role) VALUES(?,?,?,?,?)');
    $stmt->execute([$name,$email,$phone,$hash,'customer']);
    jsonResponse(201, ['ok'=>true,'message'=>'Registrasi berhasil. Silakan login.']);
} catch (PDOException $e) {
    if ($e->getCode() === '23000') jsonResponse(409, ['ok'=>false,'message'=>'Email sudah digunakan.']);
    error_log($e->getMessage());
    jsonResponse(500, ['ok'=>false,'message'=>'Registrasi gagal.']);
}
