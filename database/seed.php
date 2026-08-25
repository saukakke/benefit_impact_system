<?php
declare(strict_types=1);
require_once __DIR__ . '/../backend/bootstrap.php';

$db=Database::connection();
$users=[
 ['System Administrator','admin@ucsi.org','admin'],
 ['Programme Manager','manager@ucsi.org','manager'],
 ['Field Officer','field@ucsi.org','field_officer'],
 ['Impact Analyst','analyst@ucsi.org','analyst'],
];
$plainPassword='ChangeMe@2026';
foreach($users as [$name,$email,$role]){
 $s=$db->prepare('INSERT INTO users(name,email,password_hash,role,status) VALUES(?,?,?,?,\'active\') ON DUPLICATE KEY UPDATE name=VALUES(name),role=VALUES(role),status=\'active\'');
 $s->execute([$name,$email,password_hash($plainPassword,PASSWORD_DEFAULT),$role]);
}
$admin=(int)$db->query("SELECT id FROM users WHERE email='admin@ucsi.org'")->fetchColumn();
if(!(int)$db->query('SELECT COUNT(*) FROM programmes')->fetchColumn()){
 $s=$db->prepare('INSERT INTO programmes(code,name,description,start_date,budget,status,created_by) VALUES(?,?,?,?,?,?,?)');
 $s->execute(['UCSI-DEMO','Community Livelihood Support','Initial programme record for system configuration and controlled demonstration.','2026-01-01',5000000,'active',$admin]);
}
echo "Seed completed. Default accounts use password: {$plainPassword}. Change it immediately after first login.\n";
