<?php
declare(strict_types=1);

/* Phase 1 core-completeness routes. Loaded after base routes. */
function entityExists(string $table, int $id): bool {
    $allowed=['programmes','interventions','beneficiaries','indicators','beneficiary_interventions'];
    if(!in_array($table,$allowed,true)) return false;
    $s=Database::connection()->prepare("SELECT 1 FROM $table WHERE id=? LIMIT 1"); $s->execute([$id]); return (bool)$s->fetchColumn();
}
function requireEntity(string $table,int $id,string $label): void { if(!entityExists($table,$id)) jsonResponse(['success'=>false,'message'=>"$label not found"],404); }
function phaseDate(string $v,string $field,bool $required=true): void { $d=['x'=>$v]; validateDateValue($d,'x',$required); if($required && $v==='') jsonResponse(['success'=>false,'message'=>"Invalid $field"],422); }
function phaseProgrammePayload(array $d,bool $create=false): array {
    if($create) validateRequired($d,['code','name','start_date']);
    if(isset($d['status'])) validateEnum($d,'status',['planned','active','completed','suspended']);
    if(isset($d['budget'])) validateNonNegative($d,'budget');
    if(isset($d['start_date'])) validateDateValue($d,'start_date',true);
    if(isset($d['end_date'])) validateDateValue($d,'end_date');
    if(isset($d['start_date'],$d['end_date']) && $d['end_date']!=='' && $d['end_date']<$d['start_date']) jsonResponse(['success'=>false,'message'=>'End date cannot be before start date'],422);
    return $d;
}
function phaseInterventionPayload(array $d,bool $create=false): array {
    if($create) validateRequired($d,['programme_id','name','intervention_type','start_date']);
    if(isset($d['programme_id'])) requireEntity('programmes',validateId($d,'programme_id'),'Programme');
    if(isset($d['status'])) validateEnum($d,'status',['planned','active','completed','cancelled']);
    foreach(['target_count','unit_cost'] as $f) if(isset($d[$f])) validateNonNegative($d,$f);
    if(isset($d['start_date'])) validateDateValue($d,'start_date',true);
    if(isset($d['end_date'])) validateDateValue($d,'end_date');
    if(isset($d['start_date'],$d['end_date']) && $d['end_date']!=='' && $d['end_date']<$d['start_date']) jsonResponse(['success'=>false,'message'=>'End date cannot be before start date'],422);
    return $d;
}

if(preg_match('#^GET api/programmes/(\\d+)$#',$path,$m)){
    requireAuth(); $s=Database::connection()->prepare('SELECT p.*,COUNT(DISTINCT i.id) intervention_count,COUNT(DISTINCT bi.beneficiary_id) beneficiary_count FROM programmes p LEFT JOIN interventions i ON i.programme_id=p.id LEFT JOIN beneficiary_interventions bi ON bi.intervention_id=i.id WHERE p.id=? GROUP BY p.id');$s->execute([(int)$m[1]]);$x=$s->fetch();if(!$x)jsonResponse(['success'=>false,'message'=>'Programme not found'],404);jsonResponse(['success'=>true,'data'=>$x]);
}
if(preg_match('#^PUT api/programmes/(\\d+)$#',$path,$m)){
    $u=requireRole(['admin','manager']);requireCsrf();$id=(int)$m[1];requireEntity('programmes',$id,'Programme');$d=phaseProgrammePayload(input());$fields=['code','name','description','start_date','end_date','budget','status'];$sets=[];$p=[];foreach($fields as $f)if(array_key_exists($f,$d)){$sets[]="$f=?";$p[]=$f==='code'?strtoupper(clean((string)$d[$f])):$d[$f];}if(!$sets)jsonResponse(['success'=>false,'message'=>'No fields supplied'],422);$p[]=$id;Database::connection()->prepare('UPDATE programmes SET '.implode(',',$sets).' WHERE id=?')->execute($p);audit((int)$u['id'],'update','programme',$id);jsonResponse(['success'=>true,'message'=>'Programme updated']);
}
if(preg_match('#^DELETE api/programmes/(\\d+)$#',$path,$m)){
    $u=requireRole(['admin','manager']);requireCsrf();$id=(int)$m[1];requireEntity('programmes',$id,'Programme');$count=(int)Database::connection()->prepare('SELECT COUNT(*) FROM interventions WHERE programme_id=?')->execute([$id]);$s=Database::connection()->prepare('UPDATE programmes SET status="suspended" WHERE id=?');$s->execute([$id]);audit((int)$u['id'],'archive','programme',$id);jsonResponse(['success'=>true,'message'=>'Programme archived']);
}

if(preg_match('#^GET api/interventions/(\\d+)$#',$path,$m)){
    requireAuth();$s=Database::connection()->prepare('SELECT i.*,p.name programme_name FROM interventions i JOIN programmes p ON p.id=i.programme_id WHERE i.id=?');$s->execute([(int)$m[1]]);$x=$s->fetch();if(!$x)jsonResponse(['success'=>false,'message'=>'Intervention not found'],404);$e=Database::connection()->prepare('SELECT bi.*,CONCAT(b.first_name," ",b.last_name) beneficiary_name,b.beneficiary_code FROM beneficiary_interventions bi JOIN beneficiaries b ON b.id=bi.beneficiary_id WHERE bi.intervention_id=? ORDER BY bi.id DESC');$e->execute([(int)$m[1]]);$x['enrollments']=$e->fetchAll();jsonResponse(['success'=>true,'data'=>$x]);
}
if(preg_match('#^PUT api/interventions/(\\d+)$#',$path,$m)){
    $u=requireRole(['admin','manager']);requireCsrf();$id=(int)$m[1];requireEntity('interventions',$id,'Intervention');$d=phaseInterventionPayload(input());$fields=['programme_id','name','intervention_type','description','target_count','unit_cost','start_date','end_date','status'];$sets=[];$p=[];foreach($fields as $f)if(array_key_exists($f,$d)){$sets[]="$f=?";$p[]=$d[$f];}if(!$sets)jsonResponse(['success'=>false,'message'=>'No fields supplied'],422);$p[]=$id;Database::connection()->prepare('UPDATE interventions SET '.implode(',',$sets).' WHERE id=?')->execute($p);audit((int)$u['id'],'update','intervention',$id);jsonResponse(['success'=>true,'message'=>'Intervention updated']);
}
if(preg_match('#^DELETE api/interventions/(\\d+)$#',$path,$m)){
    $u=requireRole(['admin','manager']);requireCsrf();$id=(int)$m[1];requireEntity('interventions',$id,'Intervention');Database::connection()->prepare('UPDATE interventions SET status="cancelled" WHERE id=?')->execute([$id]);audit((int)$u['id'],'cancel','intervention',$id);jsonResponse(['success'=>true,'message'=>'Intervention cancelled']);
}

if(preg_match('#^GET api/enrollments/(\\d+)$#',$path,$m)){
    requireAuth();$s=Database::connection()->prepare('SELECT bi.*,CONCAT(b.first_name," ",b.last_name) beneficiary_name,b.beneficiary_code,i.name intervention_name FROM beneficiary_interventions bi JOIN beneficiaries b ON b.id=bi.beneficiary_id JOIN interventions i ON i.id=bi.intervention_id WHERE bi.id=?');$s->execute([(int)$m[1]]);$x=$s->fetch();if(!$x)jsonResponse(['success'=>false,'message'=>'Enrollment not found'],404);jsonResponse(['success'=>true,'data'=>$x]);
}
if(preg_match('#^PUT api/enrollments/(\\d+)$#',$path,$m)){
    $u=requireRole(['admin','manager','field_officer']);requireCsrf();$id=(int)$m[1];requireEntity('beneficiary_interventions',$id,'Enrollment');$d=input();if(isset($d['status']))validateEnum($d,'status',['enrolled','completed','withdrawn','referred']);if(isset($d['benefit_value']))validateNonNegative($d,'benefit_value');if(isset($d['exit_date']))validateDateValue($d,'exit_date');$fields=['status','exit_date','benefit_value','notes'];$sets=[];$p=[];foreach($fields as $f)if(array_key_exists($f,$d)){$sets[]="$f=?";$p[]=$d[$f];}if(!$sets)jsonResponse(['success'=>false,'message'=>'No fields supplied'],422);if(isset($d['status'])&&in_array($d['status'],['completed','withdrawn','referred'],true)&&empty($d['exit_date'])){$sets[]='exit_date=COALESCE(exit_date,CURDATE())';}$p[]=$id;Database::connection()->prepare('UPDATE beneficiary_interventions SET '.implode(',',$sets).' WHERE id=?')->execute($p);audit((int)$u['id'],'update','beneficiary_intervention',$id,['status'=>$d['status']??null]);jsonResponse(['success'=>true,'message'=>'Enrollment updated']);
}

if($path==='api/indicators' && $method==='POST'){
    $u=requireRole(['admin','manager','analyst']);requireCsrf();$d=input();validateRequired($d,['programme_id','name','indicator_type','unit']);requireEntity('programmes',validateId($d,'programme_id'),'Programme');validateEnum($d,'indicator_type',['output','outcome','impact']);validateEnum($d,'frequency',['monthly','quarterly','biannual','annual','event']);foreach(['baseline','target'] as $f)if(isset($d[$f])&&$d[$f]!==''&&!is_numeric($d[$f]))jsonResponse(['success'=>false,'message'=>"Invalid $f"],422);$s=Database::connection()->prepare('INSERT INTO indicators(programme_id,name,description,indicator_type,unit,baseline,target,frequency,active) VALUES(?,?,?,?,?,?,?,?,?)');$s->execute([(int)$d['programme_id'],clean($d['name']),$d['description']??null,$d['indicator_type'],clean($d['unit']),$d['baseline']!==''?($d['baseline']??null):null,$d['target']!==''?($d['target']??null):null,$d['frequency']??'quarterly',(int)($d['active']??1)]);$id=(int)Database::connection()->lastInsertId();audit((int)$u['id'],'create','indicator',$id);jsonResponse(['success'=>true,'id'=>$id],201);
}
if(preg_match('#^PUT api/indicators/(\\d+)$#',$path,$m)){
    $u=requireRole(['admin','manager','analyst']);requireCsrf();$id=(int)$m[1];requireEntity('indicators',$id,'Indicator');$d=input();if(isset($d['programme_id']))requireEntity('programmes',validateId($d,'programme_id'),'Programme');if(isset($d['indicator_type']))validateEnum($d,'indicator_type',['output','outcome','impact']);if(isset($d['frequency']))validateEnum($d,'frequency',['monthly','quarterly','biannual','annual','event']);$fields=['programme_id','name','description','indicator_type','unit','baseline','target','frequency','active'];$sets=[];$p=[];foreach($fields as $f)if(array_key_exists($f,$d)){$sets[]="$f=?";$p[]=$d[$f];}if(!$sets)jsonResponse(['success'=>false,'message'=>'No fields supplied'],422);$p[]=$id;Database::connection()->prepare('UPDATE indicators SET '.implode(',',$sets).' WHERE id=?')->execute($p);audit((int)$u['id'],'update','indicator',$id);jsonResponse(['success'=>true,'message'=>'Indicator updated']);
}
if(preg_match('#^DELETE api/indicators/(\\d+)$#',$path,$m)){
    $u=requireRole(['admin','manager']);requireCsrf();$id=(int)$m[1];requireEntity('indicators',$id,'Indicator');Database::connection()->prepare('UPDATE indicators SET active=0 WHERE id=?')->execute([$id]);audit((int)$u['id'],'deactivate','indicator',$id);jsonResponse(['success'=>true,'message'=>'Indicator deactivated']);
}
