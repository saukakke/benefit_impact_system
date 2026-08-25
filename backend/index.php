<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

$path = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '', '/');
$base = trim(dirname($_SERVER['SCRIPT_NAME']), '/');
if ($base !== '' && str_starts_with($path, $base)) $path = trim(substr($path, strlen($base)), '/');
$method = $_SERVER['REQUEST_METHOD'];

if ($path === '' || $path === 'index.php') jsonResponse(['success'=>true,'application'=>APP_NAME,'version'=>'1.0.0','csrf_token'=>csrfToken()]);

$routes = [
 'GET api/csrf' => fn() => jsonResponse(['success'=>true,'csrf_token'=>csrfToken()]),
 'POST api/auth/login' => function(){
    requireMethod('POST'); $d=input(); validateRequired($d,['email','password']);
    $s=Database::connection()->prepare('SELECT * FROM users WHERE email=? LIMIT 1'); $s->execute([strtolower(clean($d['email']))]); $u=$s->fetch();
    if(!$u || !password_verify((string)$d['password'],$u['password_hash']) || $u['status']!=='active') jsonResponse(['success'=>false,'message'=>'Invalid credentials'],401);
    session_regenerate_id(true); $_SESSION['user_id']=(int)$u['id']; csrfToken();
    Database::connection()->prepare('UPDATE users SET last_login_at=NOW() WHERE id=?')->execute([$u['id']]); audit((int)$u['id'],'login','user',(int)$u['id']);
    unset($u['password_hash']); jsonResponse(['success'=>true,'user'=>$u,'csrf_token'=>csrfToken()]);
 },
 'POST api/auth/logout' => function(){ $u=requireAuth(); requireCsrf(); audit((int)$u['id'],'logout','user',(int)$u['id']); $_SESSION=[]; session_destroy(); jsonResponse(['success'=>true,'message'=>'Logged out']); },
 'GET api/auth/me' => function(){ $u=requireAuth(); jsonResponse(['success'=>true,'user'=>$u,'csrf_token'=>csrfToken()]); },
 'GET api/dashboard' => function(){
    requireAuth(); $db=Database::connection();
    $counts=[]; foreach(['beneficiaries','programmes','interventions','assessments'] as $t) $counts[$t]=(int)$db->query("SELECT COUNT(*) FROM $t")->fetchColumn();
    $counts['active_beneficiaries']=(int)$db->query("SELECT COUNT(*) FROM beneficiaries WHERE status='active'")->fetchColumn();
    $counts['completed_interventions']=(int)$db->query("SELECT COUNT(*) FROM beneficiary_interventions WHERE status='completed'")->fetchColumn();
    $impact=(float)$db->query('SELECT COALESCE(AVG(overall_score),0) FROM assessments')->fetchColumn();
    $counts['average_impact_score']=round($impact,2);
    jsonResponse(['success'=>true,'data'=>$counts]);
 },
];

$key="$method $path";
if(isset($routes[$key])) { $routes[$key](); exit; }

if(preg_match('#^GET api/beneficiaries$#',$path)) {
    requireAuth(); [$page,$limit,$offset]=pagination(); $q=clean((string)($_GET['q']??'')); $status=$_GET['status']??'';
    $where=[];$params=[]; if($q!==''){ $where[]='(b.beneficiary_code LIKE ? OR b.first_name LIKE ? OR b.last_name LIKE ? OR b.phone LIKE ? OR b.community LIKE ?)'; $x="%$q%"; $params=array_merge($params,[$x,$x,$x,$x,$x]); } if($status!==''){ $where[]='b.status=?';$params[]=$status; }
    $w=$where?'WHERE '.implode(' AND ',$where):''; $db=Database::connection(); $c=$db->prepare("SELECT COUNT(*) FROM beneficiaries b $w");$c->execute($params);$total=(int)$c->fetchColumn();
    $s=$db->prepare("SELECT b.*,u.name AS creator_name FROM beneficiaries b JOIN users u ON u.id=b.created_by $w ORDER BY b.id DESC LIMIT $limit OFFSET $offset");$s->execute($params);
    jsonResponse(['success'=>true,'data'=>$s->fetchAll(),'pagination'=>['page'=>$page,'per_page'=>$limit,'total'=>$total,'pages'=>(int)ceil($total/$limit)]]);
}

if(preg_match('#^GET api/beneficiaries/(\\d+)$#',$path,$m)) { requireAuth(); $s=Database::connection()->prepare('SELECT * FROM beneficiaries WHERE id=?');$s->execute([(int)$m[1]]);$b=$s->fetch();if(!$b)jsonResponse(['success'=>false,'message'=>'Beneficiary not found'],404);jsonResponse(['success'=>true,'data'=>$b]); }

if($path==='api/beneficiaries' && $method==='POST') { requireRole(['admin','manager','field_officer']); requireCsrf(); $d=input(); validateRequired($d,['first_name','last_name','gender','community','state','registration_date']); if(!in_array($d['gender'],['male','female','other'],true))jsonResponse(['success'=>false,'message'=>'Invalid gender'],422); $u=currentUser();$db=Database::connection();$code='UCSI-'.date('Ymd').'-'.strtoupper(bin2hex(random_bytes(3)));$s=$db->prepare('INSERT INTO beneficiaries (beneficiary_code,first_name,middle_name,last_name,gender,date_of_birth,phone,email,address,community,lga,state,household_size,vulnerability_status,disability_status,employment_status,status,consent_given,registration_date,created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');$s->execute([$code,clean($d['first_name']),clean($d['middle_name']??''),clean($d['last_name']),$d['gender'],$d['date_of_birth']??null,$d['phone']??null,$d['email']??null,$d['address']??null,clean($d['community']),$d['lga']??null,clean($d['state']),(int)($d['household_size']??1),$d['vulnerability_status']??'medium',(int)($d['disability_status']??0),$d['employment_status']??null,$d['status']??'active',(int)($d['consent_given']??0),$d['registration_date'],(int)$u['id']]);$id=(int)$db->lastInsertId();audit((int)$u['id'],'create','beneficiary',$id);jsonResponse(['success'=>true,'message'=>'Beneficiary created','id'=>$id,'beneficiary_code'=>$code],201); }

if(preg_match('#^PUT api/beneficiaries/(\\d+)$#',$path,$m)) { $u=requireRole(['admin','manager','field_officer']);requireCsrf();$d=input();$id=(int)$m[1];$fields=['first_name','middle_name','last_name','gender','date_of_birth','phone','email','address','community','lga','state','household_size','vulnerability_status','disability_status','employment_status','status','consent_given','registration_date'];$sets=[];$p=[];foreach($fields as $f)if(array_key_exists($f,$d)){ $sets[]="$f=?";$p[]=$d[$f]; }if(!$sets)jsonResponse(['success'=>false,'message'=>'No fields supplied'],422);$p[]=$id;$s=Database::connection()->prepare('UPDATE beneficiaries SET '.implode(',',$sets).' WHERE id=?');$s->execute($p);audit((int)$u['id'],'update','beneficiary',$id);jsonResponse(['success'=>true,'message'=>'Beneficiary updated']); }

if(preg_match('#^DELETE api/beneficiaries/(\\d+)$#',$path,$m)) { $u=requireRole(['admin','manager']);requireCsrf();$id=(int)$m[1];$s=Database::connection()->prepare("UPDATE beneficiaries SET status='inactive' WHERE id=?");$s->execute([$id]);audit((int)$u['id'],'deactivate','beneficiary',$id);jsonResponse(['success'=>true,'message'=>'Beneficiary deactivated']); }

if($path==='api/programmes' && $method==='GET') { requireAuth();$s=Database::connection()->query('SELECT p.*,u.name AS creator_name,(SELECT COUNT(*) FROM interventions i WHERE i.programme_id=p.id) intervention_count FROM programmes p JOIN users u ON u.id=p.created_by ORDER BY p.id DESC');jsonResponse(['success'=>true,'data'=>$s->fetchAll()]); }
if($path==='api/programmes' && $method==='POST') { $u=requireRole(['admin','manager']);requireCsrf();$d=input();validateRequired($d,['code','name','start_date']);$s=Database::connection()->prepare('INSERT INTO programmes(code,name,description,start_date,end_date,budget,status,created_by) VALUES(?,?,?,?,?,?,?,?)');$s->execute([strtoupper(clean($d['code'])),clean($d['name']),$d['description']??null,$d['start_date'],$d['end_date']??null,(float)($d['budget']??0),$d['status']??'planned',$u['id']]);$id=(int)Database::connection()->lastInsertId();audit((int)$u['id'],'create','programme',$id);jsonResponse(['success'=>true,'id'=>$id],201);}
if($path==='api/interventions' && $method==='GET') { requireAuth();$s=Database::connection()->query('SELECT i.*,p.name AS programme_name,(SELECT COUNT(*) FROM beneficiary_interventions bi WHERE bi.intervention_id=i.id) enrolled_count FROM interventions i JOIN programmes p ON p.id=i.programme_id ORDER BY i.id DESC');jsonResponse(['success'=>true,'data'=>$s->fetchAll()]); }
if($path==='api/interventions' && $method==='POST') { $u=requireRole(['admin','manager']);requireCsrf();$d=input();validateRequired($d,['programme_id','name','intervention_type','start_date']);$s=Database::connection()->prepare('INSERT INTO interventions(programme_id,name,intervention_type,description,target_count,unit_cost,start_date,end_date,status) VALUES(?,?,?,?,?,?,?,?,?)');$s->execute([(int)$d['programme_id'],clean($d['name']),clean($d['intervention_type']),$d['description']??null,(int)($d['target_count']??0),(float)($d['unit_cost']??0),$d['start_date'],$d['end_date']??null,$d['status']??'planned']);$id=(int)Database::connection()->lastInsertId();audit((int)$u['id'],'create','intervention',$id);jsonResponse(['success'=>true,'id'=>$id],201);}

if($path==='api/enrollments' && $method==='POST') { $u=requireRole(['admin','manager','field_officer']);requireCsrf();$d=input();validateRequired($d,['beneficiary_id','intervention_id','enrollment_date']);$s=Database::connection()->prepare('INSERT INTO beneficiary_interventions(beneficiary_id,intervention_id,enrollment_date,benefit_value,notes,assigned_by) VALUES(?,?,?,?,?,?)');try{$s->execute([(int)$d['beneficiary_id'],(int)$d['intervention_id'],$d['enrollment_date'],(float)($d['benefit_value']??0),$d['notes']??null,$u['id']]);}catch(PDOException $e){if((int)$e->errorInfo[1]===1062)jsonResponse(['success'=>false,'message'=>'Beneficiary is already enrolled in this intervention'],409);throw $e;} $id=(int)Database::connection()->lastInsertId();audit((int)$u['id'],'enroll','beneficiary_intervention',$id);jsonResponse(['success'=>true,'id'=>$id],201); }

if($path==='api/assessments' && $method==='POST') { $u=requireRole(['admin','manager','field_officer','analyst']);requireCsrf();$d=input();validateRequired($d,['beneficiary_id','assessment_date']);$scores=[];foreach(['food_security_score','education_score','health_score','livelihood_score'] as $f)if(isset($d[$f]))$scores[]=(float)$d[$f];$overall=$scores?array_sum($scores)/count($scores):null;$s=Database::connection()->prepare('INSERT INTO assessments(beneficiary_id,intervention_id,assessment_date,assessor_id,household_income,food_security_score,education_score,health_score,livelihood_score,overall_score,narrative) VALUES(?,?,?,?,?,?,?,?,?,?,?)');$s->execute([(int)$d['beneficiary_id'],$d['intervention_id']??null,$d['assessment_date'],$u['id'],$d['household_income']??null,$d['food_security_score']??null,$d['education_score']??null,$d['health_score']??null,$d['livelihood_score']??null,$overall,$d['narrative']??null]);$id=(int)Database::connection()->lastInsertId();audit((int)$u['id'],'create','assessment',$id);jsonResponse(['success'=>true,'id'=>$id,'overall_score'=>$overall],201);}

if($path==='api/assessments' && $method==='GET') { requireAuth();$s=Database::connection()->query('SELECT a.*,CONCAT(b.first_name," ",b.last_name) beneficiary_name,u.name assessor_name FROM assessments a JOIN beneficiaries b ON b.id=a.beneficiary_id JOIN users u ON u.id=a.assessor_id ORDER BY a.assessment_date DESC,a.id DESC');jsonResponse(['success'=>true,'data'=>$s->fetchAll()]); }

if($path==='api/indicators' && $method==='GET') { requireAuth();$s=Database::connection()->query('SELECT i.*,p.name programme_name,(SELECT iv.value FROM indicator_values iv WHERE iv.indicator_id=i.id ORDER BY iv.reporting_period DESC LIMIT 1) latest_value FROM indicators i JOIN programmes p ON p.id=i.programme_id ORDER BY i.id DESC');jsonResponse(['success'=>true,'data'=>$s->fetchAll()]); }
if($path==='api/indicator-values' && $method==='POST') { $u=requireRole(['admin','manager','analyst']);requireCsrf();$d=input();validateRequired($d,['indicator_id','reporting_period','value']);$s=Database::connection()->prepare('INSERT INTO indicator_values(indicator_id,reporting_period,value,evidence_note,recorded_by) VALUES(?,?,?,?,?) ON DUPLICATE KEY UPDATE value=VALUES(value),evidence_note=VALUES(evidence_note),recorded_by=VALUES(recorded_by)');$s->execute([(int)$d['indicator_id'],$d['reporting_period'],(float)$d['value'],$d['evidence_note']??null,$u['id']]);audit((int)$u['id'],'record','indicator_value',(int)$d['indicator_id']);jsonResponse(['success'=>true,'message'=>'Indicator value recorded']);}

if($path==='api/reports/impact' && $method==='GET') { requireAuth();$db=Database::connection();$s=$db->query("SELECT p.id,p.code,p.name,COUNT(DISTINCT bi.beneficiary_id) beneficiaries,COUNT(DISTINCT bi.id) interventions_completed,ROUND(AVG(a.overall_score),2) average_impact FROM programmes p LEFT JOIN interventions i ON i.programme_id=p.id LEFT JOIN beneficiary_interventions bi ON bi.intervention_id=i.id LEFT JOIN assessments a ON a.beneficiary_id=bi.beneficiary_id GROUP BY p.id,p.code,p.name ORDER BY p.name");jsonResponse(['success'=>true,'data'=>$s->fetchAll()]);}

jsonResponse(['success'=>false,'message'=>'Endpoint not found'],404);
