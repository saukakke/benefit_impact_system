<?php
declare(strict_types=1);

/* Phase 6: reporting, exports and monitoring endpoints. */
if ($path === 'api/reports/overview' && $method === 'GET') {
    requireAuth();
    $db=Database::connection();
    $programme=(int)($_GET['programme_id']??0);
    $where=$programme?' WHERE p.id=?':''; $params=$programme?[$programme]:[];
    $s=$db->prepare("SELECT p.id,p.code,p.name,p.status,p.budget,COUNT(DISTINCT i.id) interventions,COUNT(DISTINCT bi.beneficiary_id) beneficiaries,COUNT(DISTINCT a.id) assessments,ROUND(AVG(a.overall_score),2) average_impact_score FROM programmes p LEFT JOIN interventions i ON i.programme_id=p.id LEFT JOIN beneficiary_interventions bi ON bi.intervention_id=i.id LEFT JOIN beneficiaries b ON b.id=bi.beneficiary_id LEFT JOIN assessments a ON a.beneficiary_id=b.id $where GROUP BY p.id ORDER BY p.id DESC");$s->execute($params);
    jsonResponse(['success'=>true,'data'=>$s->fetchAll()]);
}
if ($path === 'api/reports/beneficiary-outcomes' && $method === 'GET') {
    requireRole(['admin','manager','analyst']);
    $db=Database::connection();
    $s=$db->query("SELECT b.id,b.beneficiary_code,CONCAT(b.first_name,' ',b.last_name) beneficiary_name,MIN(CASE WHEN a.assessment_type='baseline' THEN a.overall_score END) baseline_score,MAX(CASE WHEN a.assessment_type IN ('endline','exit') THEN a.overall_score END) endline_score,COUNT(a.id) assessment_count FROM beneficiaries b LEFT JOIN assessments a ON a.beneficiary_id=b.id GROUP BY b.id ORDER BY b.id DESC");
    $rows=$s->fetchAll(); foreach($rows as &$r){$base=$r['baseline_score'];$end=$r['endline_score'];$r['absolute_change']=($base!==null&&$end!==null)?round((float)$end-(float)$base,2):null;$r['percentage_change']=($base!==null&&$end!==null&&abs((float)$base)>0)?round((((float)$end-(float)$base)/(float)$base)*100,2):null;} unset($r);
    jsonResponse(['success'=>true,'data'=>$rows]);
}
if ($path === 'api/reports/indicator-performance' && $method === 'GET') {
    requireRole(['admin','manager','analyst']);
    $s=Database::connection()->query("SELECT i.id,i.name,i.indicator_type,i.unit,i.baseline,i.target,MAX(iv.reporting_period) latest_period,MAX(iv.value) latest_value FROM indicators i LEFT JOIN indicator_values iv ON iv.indicator_id=i.id WHERE i.active=1 GROUP BY i.id ORDER BY i.id DESC");$rows=$s->fetchAll();foreach($rows as &$r){$r['achievement_percentage']=($r['target']!==null&&(float)$r['target']!=0&&$r['latest_value']!==null)?round(((float)$r['latest_value']/(float)$r['target'])*100,2):null;}unset($r);jsonResponse(['success'=>true,'data'=>$rows]);
}
if ($path === 'api/reports/audit-summary' && $method === 'GET') {
    requireRole(['admin']);
    $s=Database::connection()->query("SELECT action,entity_type,COUNT(*) event_count,MAX(created_at) last_event_at FROM audit_logs GROUP BY action,entity_type ORDER BY event_count DESC");jsonResponse(['success'=>true,'data'=>$s->fetchAll()]);
}
