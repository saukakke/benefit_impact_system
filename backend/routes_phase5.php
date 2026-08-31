<?php
declare(strict_types=1);

/* Phase 5: data integrity, duplicate protection and consistent validation. */
function phase5DuplicateBeneficiary(array $d, ?int $ignoreId=null): void {
    $db=Database::connection();
    if(!empty($d['phone'])) { $q='SELECT id FROM beneficiaries WHERE phone=?'.($ignoreId?' AND id<>?':'').' LIMIT 1';$s=$db->prepare($q);$p=[$d['phone']];if($ignoreId)$p[]=$ignoreId;$s->execute($p);if($s->fetch())jsonResponse(['success'=>false,'message'=>'A beneficiary with this phone number already exists'],409); }
    if(!empty($d['email'])) { validateEmail($d,'email');$q='SELECT id FROM beneficiaries WHERE email=?'.($ignoreId?' AND id<>?':'').' LIMIT 1';$s=$db->prepare($q);$p=[strtolower(trim($d['email']))];if($ignoreId)$p[]=$ignoreId;$s->execute($p);if($s->fetch())jsonResponse(['success'=>false,'message'=>'A beneficiary with this email already exists'],409); }
}
if($path==='api/beneficiaries'&&$method==='POST'){
    $d=input();phase5DuplicateBeneficiary($d);if(isset($d['household_size'])&&((int)$d['household_size']<1||(int)$d['household_size']>100))jsonResponse(['success'=>false,'message'=>'Household size must be between 1 and 100'],422);if(isset($d['gender']))validateEnum($d,'gender',['male','female','other']);
}
if(preg_match('#^api/beneficiaries/(\\d+)$#',$path,$m)&&$method==='PUT'){
    $d=input();phase5DuplicateBeneficiary($d,(int)$m[1]);if(isset($d['household_size'])&&((int)$d['household_size']<1||(int)$d['household_size']>100))jsonResponse(['success'=>false,'message'=>'Household size must be between 1 and 100'],422);if(isset($d['gender']))validateEnum($d,'gender',['male','female','other']);
}
if($path==='api/programmes'&&$method==='POST'){
    $d=input();if(isset($d['code'])){$s=Database::connection()->prepare('SELECT id FROM programmes WHERE code=? LIMIT 1');$s->execute([strtoupper(trim($d['code']))]);if($s->fetch())jsonResponse(['success'=>false,'message'=>'Programme code already exists'],409);}
}
if($path==='api/assessments'&&$method==='POST'){
    $d=input();if(isset($d['beneficiary_id'])){ $s=Database::connection()->prepare('SELECT id FROM beneficiaries WHERE id=? LIMIT 1');$s->execute([(int)$d['beneficiary_id']]);if(!$s->fetch())jsonResponse(['success'=>false,'message'=>'Beneficiary not found'],404); }
    foreach(['food_security_score','education_score','health_score','livelihood_score','overall_score'] as $f)if(isset($d[$f])&&$d[$f]!==''&&(!is_numeric($d[$f])||(float)$d[$f]<0||(float)$d[$f]>100))jsonResponse(['success'=>false,'message'=>"$f must be between 0 and 100"],422);
}
