<?php
declare(strict_types=1);

/* Phase 3: secure document and notification APIs. Files are stored outside the public web root. */
if ($path === 'api/documents' && $method === 'GET') {
    $u=requireAuth(); $db=Database::connection(); $where=[];$p=[];
    if(isset($_GET['beneficiary_id'])){$where[]='d.beneficiary_id=?';$p[]=(int)$_GET['beneficiary_id'];}
    if(isset($_GET['programme_id'])){$where[]='d.programme_id=?';$p[]=(int)$_GET['programme_id'];}
    $sql='SELECT d.id,d.beneficiary_id,d.programme_id,d.original_name,d.mime_type,d.size_bytes,d.uploaded_by,d.created_at,u.name uploaded_by_name FROM documents d JOIN users u ON u.id=d.uploaded_by'.($where?' WHERE '.implode(' AND ',$where):'').' ORDER BY d.created_at DESC';
    $s=$db->prepare($sql);$s->execute($p);jsonResponse(['success'=>true,'data'=>$s->fetchAll()]);
}
if($path==='api/documents'&&$method==='POST'){
    $u=requireRole(['admin','manager','field_officer']);requireCsrf();
    if(empty($_FILES['file'])||$_FILES['file']['error']!==UPLOAD_ERR_OK)jsonResponse(['success'=>false,'message'=>'A valid file is required'],422);
    $f=$_FILES['file'];if($f['size']>10*1024*1024)jsonResponse(['success'=>false,'message'=>'File exceeds 10 MB limit'],422);
    $allowed=['application/pdf','image/jpeg','image/png','text/plain','application/vnd.openxmlformats-officedocument.wordprocessingml.document','application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'];
    $mime=(new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);if(!in_array($mime,$allowed,true))jsonResponse(['success'=>false,'message'=>'File type is not allowed'],422);
    $beneficiary=isset($_POST['beneficiary_id'])?(int)$_POST['beneficiary_id']:null;$programme=isset($_POST['programme_id'])?(int)$_POST['programme_id']:null;
    if(!$beneficiary&&!$programme)jsonResponse(['success'=>false,'message'=>'Document must belong to a beneficiary or programme'],422);
    if($beneficiary){$s=Database::connection()->prepare('SELECT id FROM beneficiaries WHERE id=?');$s->execute([$beneficiary]);if(!$s->fetch())jsonResponse(['success'=>false,'message'=>'Beneficiary not found'],404);}
    if($programme){$s=Database::connection()->prepare('SELECT id FROM programmes WHERE id=?');$s->execute([$programme]);if(!$s->fetch())jsonResponse(['success'=>false,'message'=>'Programme not found'],404);}
    $dir=getenv('UPLOAD_DIR')?:dirname(__DIR__).'/storage/uploads';if(!is_dir($dir)&&!mkdir($dir,0750,true))jsonResponse(['success'=>false,'message'=>'Unable to initialize storage'],500);
    $stored=bin2hex(random_bytes(24));$target=$dir.'/'.$stored;if(!move_uploaded_file($f['tmp_name'],$target))jsonResponse(['success'=>false,'message'=>'Unable to store file'],500);
    $s=Database::connection()->prepare('INSERT INTO documents(beneficiary_id,programme_id,original_name,stored_name,mime_type,size_bytes,uploaded_by) VALUES(?,?,?,?,?,?,?)');$s->execute([$beneficiary,$programme,basename((string)$f['name']),$stored,$mime,(int)$f['size'],(int)$u['id']]);$id=(int)Database::connection()->lastInsertId();audit((int)$u['id'],'upload','document',$id);jsonResponse(['success'=>true,'id'=>$id],201);
}
if(preg_match('#^api/documents/(\\d+)$#',$path,$m)&&$method==='GET'){
    $u=requireAuth();$s=Database::connection()->prepare('SELECT d.*,u.name uploaded_by_name FROM documents d JOIN users u ON u.id=d.uploaded_by WHERE d.id=?');$s->execute([(int)$m[1]]);$d=$s->fetch();if(!$d)jsonResponse(['success'=>false,'message'=>'Document not found'],404);
    $dir=getenv('UPLOAD_DIR')?:dirname(__DIR__).'/storage/uploads';$file=$dir.'/'.$d['stored_name'];if(!is_file($file))jsonResponse(['success'=>false,'message'=>'Document file is missing'],404);header('Content-Type: '.$d['mime_type']);header('Content-Disposition: attachment; filename="'.str_replace('"','',basename($d['original_name'])).'"');header('Content-Length: '.filesize($file));readfile($file);exit;
}
if(preg_match('#^api/documents/(\\d+)$#',$path,$m)&&$method==='DELETE'){
    $u=requireRole(['admin','manager','field_officer']);requireCsrf();$s=Database::connection()->prepare('SELECT stored_name FROM documents WHERE id=?');$s->execute([(int)$m[1]]);$d=$s->fetch();if(!$d)jsonResponse(['success'=>false,'message'=>'Document not found'],404);$dir=getenv('UPLOAD_DIR')?:dirname(__DIR__).'/storage/uploads';$file=$dir.'/'.$d['stored_name'];if(is_file($file))unlink($file);Database::connection()->prepare('DELETE FROM documents WHERE id=?')->execute([(int)$m[1]]);audit((int)$u['id'],'delete','document',(int)$m[1]);jsonResponse(['success'=>true,'message'=>'Document deleted']);
}

if($path==='api/notifications'&&$method==='GET'){$u=requireAuth();$limit=min(max((int)($_GET['limit']??30),1),100);$s=Database::connection()->prepare('SELECT id,title,message,type,read_at,created_at FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT '.$limit);$s->execute([(int)$u['id']]);$items=$s->fetchAll();$s=Database::connection()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id=? AND read_at IS NULL');$s->execute([(int)$u['id']]);jsonResponse(['success'=>true,'data'=>$items,'unread_count'=>(int)$s->fetchColumn()]);}
if($path==='api/notifications'&&$method==='POST'){$u=requireRole(['admin','manager']);requireCsrf();$d=input();validateRequired($d,['user_id','title','message']);$s=Database::connection()->prepare('SELECT id FROM users WHERE id=? AND status="active"');$s->execute([(int)$d['user_id']]);if(!$s->fetch())jsonResponse(['success'=>false,'message'=>'Active user not found'],404);validateEnum($d,'type',['info','success','warning','danger']);$s=Database::connection()->prepare('INSERT INTO notifications(user_id,title,message,type) VALUES(?,?,?,?)');$s->execute([(int)$d['user_id'],clean($d['title']),clean($d['message']),$d['type']??'info']);$id=(int)Database::connection()->lastInsertId();audit((int)$u['id'],'create','notification',$id);jsonResponse(['success'=>true,'id'=>$id],201);}
if(preg_match('#^api/notifications/(\\d+)/read$#',$path,$m)&&$method==='PUT'){$u=requireAuth();requireCsrf();$s=Database::connection()->prepare('UPDATE notifications SET read_at=COALESCE(read_at,NOW()) WHERE id=? AND user_id=?');$s->execute([(int)$m[1],(int)$u['id']]);if(!$s->rowCount())jsonResponse(['success'=>false,'message'=>'Notification not found'],404);jsonResponse(['success'=>true,'message'=>'Notification marked as read']);}
if($path==='api/notifications/read-all'&&$method==='PUT'){$u=requireAuth();requireCsrf();Database::connection()->prepare('UPDATE notifications SET read_at=COALESCE(read_at,NOW()) WHERE user_id=? AND read_at IS NULL')->execute([(int)$u['id']]);jsonResponse(['success'=>true,'message'=>'Notifications marked as read']);}
