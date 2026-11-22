<?php
require_once __DIR__ . '/../../app/bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] !== 'GET') jsonResponse(405, ['ok'=>false,'message'=>'Method tidak diizinkan.']);
$services = $pdo->query('SELECT id,name,description,price,duration_minutes FROM services WHERE is_active=1 ORDER BY id')->fetchAll();
$products = $pdo->query('SELECT p.id,p.name,p.description,p.price,p.stock,c.name AS category FROM cafe_products p JOIN cafe_categories c ON c.id=p.category_id WHERE p.is_available=1 AND p.stock>0 ORDER BY c.name,p.name')->fetchAll();
jsonResponse(200, ['ok'=>true,'services'=>$services,'cafe_products'=>$products,'csrf_token'=>$_SESSION['csrf_token']]);
