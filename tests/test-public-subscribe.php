<?php
$r=APLists_Lifecycle::create_list(['name'=>'Public boundary '.time(),'double_opt_in'=>1]);$id=$r['list_id'];$email='public-'.wp_generate_password(8,false,false).'@example.invalid';
$_POST=['list_id'=>$id,'email'=>$email,'consent'=>'1','_wpnonce'=>wp_create_nonce('aplists_subscribe_'.$id),'force_subscribed'=>'1','source'=>'forged'];
add_filter('wp_redirect',static function(){throw new RuntimeException('redirect_captured');});
try{APLists_Lifecycle::instance()->subscribe();}catch(RuntimeException $e){}
$rows=APLists_Lifecycle::subscribers($id);$row=$rows[0]??[];$ok=($row['status']??'')==='pending'&&($row['source']??'')==='public_form';
echo json_encode(['case'=>'forged_public_subscription','status'=>$row['status']??null,'source'=>$row['source']??null,'pass'=>$ok])."\n";if(!$ok)exit(1);
