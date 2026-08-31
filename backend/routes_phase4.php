<?php
declare(strict_types=1);

function phase4Score(array $d,string $field): ?float {
    if (!array_key_exists($field,$d) || $d[$field] === '' || $d[$field] === null) return null;
    if (!is_numeric($d[$field]) || (float)$d[$field] < 0 || (float)$d[$field] > 100) jsonResponse(['success'=>false,'message'=>"$field must be between 0 and 100"],422);
    return (float)$d[$field];
}

if ($path === 'api/assessments' && $method === 'POST') {
    $u=requireRole(['admin','manager','field_officer','analyst']); requireCsrf(); $d=input();
    validateRequired($d,['beneficiary_id','assessment_date','assessment_type']); validateEnum($d,'assessment_type',['baseline','follow_up','midline','endline','exit']);
    requireEntity('beneficiaries',validateId($d,'beneficiary_id'),'Beneficiary');
    if(isset($d['intervention_id'])&&$d['intervention_id']!=='')requireEntity('interventions',validateId($d,'intervention_id'),'Intervention'); validateDateValue($d,'assessment_date',true);
    foreach(['food_security_score','education_score','health_score','livelihood_score','overall_score'] as $f) phase4Score($d,$f);
    $scores=[];foreach(['food_security_score','education_score','health_score','livelihood_score'] as $f){$v=phase4Score($d,$f);if($v!==null)$scores[]=$v;}$overall=$scores?round(array_sum($scores)/count($scores),2):null;
    $db=Database::connection();$s=$db->prepare('INSERT INTO assessments(beneficiary_id,intervention_id,assessment_date,assessment_type,assessor_id,household_income,food_security_score,education_score,health_score,livelihood_score,overall_score,narrative) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)');
    $s->execute([(int)$d['beneficiary_id'],($d['intervention_id']??null)!==''?$d['intervention_id']:null,$d['assessment_date'],$d['assessment_type'],$u['id'],$d['household_income']??null,$d['food_security_score']??null,$d['education_score']??null,$d['health_score']??null,$d['livelihood_score']??null,$overall,$d['narrative']??null]);$id=(int)$db->lastInsertId();audit((int)$u['id'],'create','assessment',$id);jsonResponse(['success'=>true,'id'=>$id,'overall_score'=>$overall],201);
}
if($path==='api/assessments'&&$method==='GET'){
    requireAuth();$type=$_GET['assessment_type']??null;$bid=$_GET['beneficiary_id']??null;$where=[];$p=[];if($type){validateEnum(['assessment_type'=>$type],'assessment_type',['baseline','follow_up','midline','endline','exit']);$where[]='a.assessment_type=?';$p[]=$type;}if($bid){$where[]='a.beneficiary_id=?';$p[]=(int)$bid;}$w=$where?' WHERE '.implode(' AND ',$where):'';$s=Database::connection()->prepare("SELECT a.*,CONCAT(b.first_name,' ',b.last_name) beneficiary_name,u.name assessor_name FROM assessments a JOIN beneficiaries b ON b.id=a.beneficiary_id JOIN users u ON u.id=a.assessor_id$w ORDER BY a.assessment_date DESC,a.id DESC");$s->execute($p);jsonResponse(['success'=>true,'data'=>$s->fetchAll()]);
}
if(preg_match('#^GET api/beneficiaries/(\\d+)/impact$#',$path,$m)){
    requireAuth();$bid=(int)$m[1];requireEntity('beneficiaries',$bid,'Beneficiary');$s=Database::connection()->prepare('SELECT a.* FROM assessments a WHERE a.beneficiary_id=? ORDER BY a.assessment_date ASC,a.id ASC');$s->execute([$bid]);$rows=$s->fetchAll();$baseline=null;$endline=null;foreach($rows as $r){if($r['assessment_type']==='baseline'&&!$baseline)$baseline=$r;if($r['assessment_type']==='endline')$endline=$r;}$change=null;$percent=null;if($baseline&&$endline&&$baseline['overall_score']!==null&&$endline['overall_score']!==null){$change=round((float)$endline['overall_score']-(float)$baseline['overall_score'],2);$percent=(float)$baseline['overall_score']!=0?round($change/(float)$baseline['overall_score']*100,2):null;}jsonResponse(['success'=>true,'data'=>['beneficiary_id'=>$bid,'baseline'=>$baseline,'endline'=>$endline,'absolute_change'=>$change,'percentage_change'=>$percent,'assessments'=>$rows]]);
}
if($path==='api/reports/impact'&&$method==='GET'){
 requireAuth();$s=Database::connection()->query("SELECT p.id,p.code,p.name,COUNT(DISTINCT bi.beneficiary_id) beneficiaries,COUNT(DISTINCT CASE WHEN bi.status='completed' THEN bi.id END) interventions_completed,ROUND(AVG(CASE WHEN a.assessment_type='baseline' THEN a.overall_score END),2) baseline_average,ROUND(AVG(CASE WHEN a.assessment_type='endline' THEN a.overall_score END),2) endline_average FROM programmes p LEFT JOIN interventions i ON i.programme_id=p.id LEFT JOIN beneficiary_interventions bi ON bi.intervention_id=i.id LEFT JOIN assessments a ON a.beneficiary_id=bi.beneficiary_id GROUP BY p.id,p.code,p.name ORDER BY p.name");$data=$s->fetchAll();foreach($data as &$r){$r['absolute_change']=($r['baseline_average']!==null&&$r['endline_average']!==null)?round((float)$r['endline_average']-(float)$r['baseline_average'],2):null;$r['percentage_change']=($r['baseline_average']!==null&&(float)$r['baseline_average']!=0&&$r['endline_average']!==null)?round(((float)$r['endline_average']-(float)$r['baseline_average'])/(float)$r['baseline_average']*100,2):null;}jsonResponse(['success'=>true,'data'=>$data]);
}
