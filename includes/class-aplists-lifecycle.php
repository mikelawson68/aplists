<?php
/** apLists application lifecycle. @package apLists */
declare(strict_types=1);
if (!defined('ABSPATH')) { exit; }
final class APLists_Lifecycle
{
    private static ?self $instance=null;
    public static function instance(): self { return self::$instance ?: (self::$instance=new self()); }
    public function boot(): void
    {
        add_action('admin_menu',[$this,'menu'],5); add_action('admin_enqueue_scripts',[$this,'admin_assets']);
        add_action('admin_post_aplists_save_list',[$this,'save_list']); add_action('admin_post_aplists_save_segment',[$this,'save_segment']); add_action('admin_post_aplists_add_subscriber',[$this,'add_subscriber']); add_action('admin_post_aplists_import',[$this,'import']); add_action('admin_post_aplists_export',[$this,'export']); add_action('admin_post_aplists_suppress',[$this,'suppress']);
        add_action('admin_post_nopriv_aplists_subscribe',[$this,'subscribe']); add_action('admin_post_aplists_subscribe',[$this,'subscribe']); add_action('template_redirect',[$this,'token_routes']); add_shortcode('aplists_subscribe',[$this,'shortcode']);
    }
    public function menu(): void
    {
        add_menu_page('apLists','apLists','manage_options','aplists',[$this,'lists_page'],'dashicons-groups',30);
        add_submenu_page('aplists','Lists','Lists','manage_options','aplists',[$this,'lists_page']); add_submenu_page('aplists','Subscribers','Subscribers','manage_options','aplists-subscribers',[$this,'subscribers_page']); add_submenu_page('aplists','Segments','Segments','manage_options','aplists-segments',[$this,'segments_page']); add_submenu_page('aplists','Import','Import CSV','manage_options','aplists-import',[$this,'import_page']); add_submenu_page('aplists','Suppressions','Suppressions','manage_options','aplists-suppressions',[$this,'suppressions_page']); add_submenu_page('aplists','Getting Started','Getting Started','manage_options','aplists-start',[$this,'start_page']);
    }
    public function admin_assets(string $hook): void { if(strpos($hook,'aplists')===false)return; wp_enqueue_style('aplists-admin',plugin_dir_url(APLISTS_FILE).'assets/aplists-admin.css',[],APLISTS_VERSION); }

    /** @return array<string,mixed> */
    public static function create_list(array $data): array
    {
        global $wpdb; $t=APLists_Schema::tables(); $name=sanitize_text_field((string)($data['name']??'')); if(!$name)return ['success'=>false,'message'=>'List name is required.']; $now=current_time('mysql',true);
        $ok=$wpdb->insert($t['lists'],['name'=>$name,'description'=>sanitize_textarea_field((string)($data['description']??'')),'double_opt_in'=>!empty($data['double_opt_in'])?1:0,'from_name'=>sanitize_text_field((string)($data['from_name']??get_bloginfo('name'))),'reply_to'=>sanitize_email((string)($data['reply_to']??get_option('admin_email'))),'subscriber_count'=>0,'created_at'=>$now,'updated_at'=>$now]);
        return $ok?['success'=>true,'list_id'=>(int)$wpdb->insert_id]:['success'=>false,'message'=>'Could not create list.'];
    }
    /** @return array<int,array<string,mixed>> */
    public static function lists(): array { global $wpdb;$t=APLists_Schema::tables();$rows=$wpdb->get_results("SELECT l.*,SUM(CASE WHEN s.status='subscribed' THEN 1 ELSE 0 END) active_count,COUNT(s.id) total_count FROM {$t['lists']} l LEFT JOIN {$t['subscribers']} s ON s.list_id=l.id GROUP BY l.id ORDER BY l.updated_at DESC",ARRAY_A);return is_array($rows)?$rows:[]; }
    /** @return array<string,mixed>|null */
    public static function list_by_id(int $id): ?array { global $wpdb;$t=APLists_Schema::tables();$r=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$t['lists']} WHERE id=%d",$id),ARRAY_A);return is_array($r)?$r:null; }
    /** @return array<int,array<string,mixed>> */
    public static function subscribers(int $list_id=0,string $status=''): array
    {
        global $wpdb;
        $t = APLists_Schema::tables();
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT s.*,l.name list_name FROM %i s INNER JOIN %i l ON l.id=s.list_id WHERE (%d=0 OR s.list_id=%d) AND (%s='' OR s.status=%s) ORDER BY s.updated_at DESC LIMIT 2000",
            $t['subscribers'], $t['lists'], $list_id, $list_id, $status, $status
        ), ARRAY_A);
        return is_array($rows) ? $rows : [];
    }
    /** @return array<string,mixed> */
    public static function upsert_subscriber(int $list_id,array $data): array
    {
        global $wpdb;$t=APLists_Schema::tables();$list=self::list_by_id($list_id);$email=sanitize_email((string)($data['email']??''));if(!$list||!is_email($email))return ['success'=>false,'message'=>'A valid list and email are required.'];
        $suppressed=(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$t['suppressions']} WHERE email=%s",$email));if($suppressed)return ['success'=>false,'message'=>'This address is suppressed and cannot be subscribed.'];
        $now=current_time('mysql',true);$existing=(int)$wpdb->get_var($wpdb->prepare("SELECT id FROM {$t['subscribers']} WHERE email=%s AND list_id=%d",$email,$list_id));$double=!empty($list['double_opt_in'])&&!isset($data['force_subscribed']);$status=$double?'pending':'subscribed';$confirm=wp_generate_password(48,false,false);$unsub=wp_generate_password(48,false,false);
        $row=['email'=>$email,'first_name'=>sanitize_text_field((string)($data['first_name']??'')),'last_name'=>sanitize_text_field((string)($data['last_name']??'')),'status'=>$status,'source'=>sanitize_key((string)($data['source']??'manual')),'consent_at'=>$now,'consent_ip_hash'=>!empty($data['ip'])?hash('sha256',(string)$data['ip']):'','confirmed_at'=>$status==='subscribed'?$now:null,'unsubscribed_at'=>null,'confirmation_token'=>$confirm,'unsubscribe_token'=>$unsub,'updated_at'=>$now];
        if($existing){$wpdb->update($t['subscribers'],$row,['id'=>$existing]);$id=$existing;}else{$row['list_id']=$list_id;$row['created_at']=$now;$wpdb->insert($t['subscribers'],$row);$id=(int)$wpdb->insert_id;}
        self::recount($list_id);return $id?['success'=>true,'subscriber_id'=>$id,'status'=>$status,'confirmation_token'=>$confirm,'unsubscribe_token'=>$unsub]:['success'=>false,'message'=>'Could not save subscriber.'];
    }
    public static function suppress_email(string $email,string $reason='manual',string $source='aplists'): bool { global $wpdb;$t=APLists_Schema::tables();$email=sanitize_email($email);if(!is_email($email))return false;$now=current_time('mysql',true);$wpdb->replace($t['suppressions'],['email'=>$email,'reason'=>sanitize_key($reason),'source'=>sanitize_key($source),'created_at'=>$now]);$wpdb->update($t['subscribers'],['status'=>'unsubscribed','unsubscribed_at'=>$now,'updated_at'=>$now],['email'=>$email]);foreach(self::lists() as $list)self::recount((int)$list['id']);return true; }
    private static function recount(int $list_id): void { global $wpdb;$t=APLists_Schema::tables();$count=(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$t['subscribers']} WHERE list_id=%d AND status='subscribed'",$list_id));$wpdb->update($t['lists'],['subscriber_count'=>$count,'updated_at'=>current_time('mysql',true)],['id'=>$list_id]); }

    public function lists_page(): void { $this->guard();$lists=self::lists(); ?><div class="wrap aplists-admin"><div class="aplists-heading"><div><h1>apLists</h1><p>First-party subscriber lists, consent, and suppression inside WordPress.</p></div></div><?php $this->notice(); ?><div class="aplists-columns"><section class="aplists-card"><h2>Your lists</h2><?php if(!$lists):?><p>No lists yet. Create the first one beside this panel.</p><?php else:?><table class="widefat striped"><thead><tr><th>List</th><th>Active</th><th>Total</th><th>Subscribe form</th></tr></thead><tbody><?php foreach($lists as $list):?><tr><td><strong><?php echo esc_html($list['name']);?></strong><br><span class="description"><?php echo esc_html($list['description']);?></span></td><td><?php echo esc_html((string)$list['active_count']);?></td><td><?php echo esc_html((string)$list['total_count']);?></td><td><code>[aplists_subscribe list="<?php echo (int)$list['id'];?>"]</code><br><a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=aplists_export&list_id='.(int)$list['id']),'aplists_export'));?>">Export CSV</a></td></tr><?php endforeach;?></tbody></table><?php endif;?></section><section class="aplists-card"><h2>Create a list</h2><form method="post" action="<?php echo esc_url(admin_url('admin-post.php'));?>"><input type="hidden" name="action" value="aplists_save_list"><?php wp_nonce_field('aplists_save_list');?><p><label>Name<br><input class="widefat" required name="name"></label></p><p><label>Description<br><textarea class="widefat" rows="4" name="description"></textarea></label></p><p><label>Reply-to email<br><input class="widefat" type="email" required name="reply_to" value="<?php echo esc_attr(get_option('admin_email'));?>"></label></p><p><label><input type="checkbox" name="double_opt_in" value="1" checked> Require email confirmation</label></p><?php submit_button('Create list');?></form></section></div></div><?php }
    public function subscribers_page(): void { $this->guard();$list_id=absint($_GET['list_id']??0);$lists=self::lists();$rows=self::subscribers($list_id);?><div class="wrap aplists-admin"><h1>Subscribers</h1><?php $this->notice();?><div class="aplists-columns"><section class="aplists-card"><form method="get"><input type="hidden" name="page" value="aplists-subscribers"><select name="list_id" onchange="this.form.submit()"><option value="0">All lists</option><?php foreach($lists as $list):?><option value="<?php echo (int)$list['id'];?>" <?php selected($list_id,(int)$list['id']);?>><?php echo esc_html($list['name']);?></option><?php endforeach;?></select></form><table class="widefat striped"><thead><tr><th>Email</th><th>Name</th><th>List</th><th>Status</th><th>Consent</th></tr></thead><tbody><?php foreach($rows as $row):?><tr><td><?php echo esc_html($row['email']);?></td><td><?php echo esc_html(trim($row['first_name'].' '.$row['last_name']));?></td><td><?php echo esc_html($row['list_name']);?></td><td><span class="aplists-status aplists-<?php echo esc_attr($row['status']);?>"><?php echo esc_html(ucfirst($row['status']));?></span></td><td><?php echo esc_html($row['consent_at']?:'—');?></td></tr><?php endforeach;?></tbody></table></section><section class="aplists-card"><h2>Add subscriber</h2><form method="post" action="<?php echo esc_url(admin_url('admin-post.php'));?>"><input type="hidden" name="action" value="aplists_add_subscriber"><?php wp_nonce_field('aplists_add_subscriber');?><p><label>List<br><select class="widefat" required name="list_id"><option value="">Choose</option><?php foreach($lists as $list):?><option value="<?php echo (int)$list['id'];?>"><?php echo esc_html($list['name']);?></option><?php endforeach;?></select></label></p><p><label>Email<br><input class="widefat" type="email" required name="email"></label></p><p><label>First name<br><input class="widefat" name="first_name"></label></p><p><label>Last name<br><input class="widefat" name="last_name"></label></p><p><label><input type="checkbox" name="force_subscribed" value="1"> Confirm consent has already been collected</label></p><?php submit_button('Add subscriber');?></form></section></div></div><?php }
    public function import_page(): void { $this->guard();$lists=self::lists();?><div class="wrap aplists-admin"><h1>Import CSV</h1><?php $this->notice();?><div class="aplists-card"><p>Required column: <code>email</code>. Optional columns: <code>first_name</code> and <code>last_name</code>.</p><form method="post" enctype="multipart/form-data" action="<?php echo esc_url(admin_url('admin-post.php'));?>"><input type="hidden" name="action" value="aplists_import"><?php wp_nonce_field('aplists_import');?><p><label>Destination list<br><select required name="list_id"><option value="">Choose</option><?php foreach($lists as $list):?><option value="<?php echo (int)$list['id'];?>"><?php echo esc_html($list['name']);?></option><?php endforeach;?></select></label></p><p><input type="file" name="csv" accept=".csv,text/csv" required></p><p><label><input type="checkbox" name="consent_confirmed" value="1"> Imported contacts already gave permission to receive email</label></p><?php submit_button('Import subscribers');?></form></div></div><?php }
    public function segments_page(): void { $this->guard();global $wpdb;$t=APLists_Schema::tables();$segments=$wpdb->get_results("SELECT g.*,l.name list_name,c.field,c.operator,c.value FROM {$t['segments']} g INNER JOIN {$t['lists']} l ON l.id=g.list_id LEFT JOIN {$t['criteria']} c ON c.segment_id=g.id ORDER BY g.updated_at DESC",ARRAY_A);$lists=self::lists();?><div class="wrap aplists-admin"><h1>Segments</h1><?php $this->notice();?><div class="aplists-columns"><section class="aplists-card"><table class="widefat striped"><thead><tr><th>Segment</th><th>List</th><th>Rule</th></tr></thead><tbody><?php foreach($segments as $s):?><tr><td><strong><?php echo esc_html($s['name']);?></strong></td><td><?php echo esc_html($s['list_name']);?></td><td><code><?php echo esc_html($s['field'].' '.$s['operator'].' '.$s['value']);?></code></td></tr><?php endforeach;?></tbody></table></section><section class="aplists-card"><h2>Create a segment</h2><form method="post" action="<?php echo esc_url(admin_url('admin-post.php'));?>"><input type="hidden" name="action" value="aplists_save_segment"><?php wp_nonce_field('aplists_save_segment');?><p><label>Name<br><input class="widefat" required name="name"></label></p><p><label>List<br><select class="widefat" required name="list_id"><?php foreach($lists as $list):?><option value="<?php echo (int)$list['id'];?>"><?php echo esc_html($list['name']);?></option><?php endforeach;?></select></label></p><p><label>Field<br><select name="field"><option value="status">Subscription status</option><option value="source">Signup source</option><option value="email">Email address</option></select></label> <select name="operator"><option value="equals">equals</option><option value="contains">contains</option></select></p><p><label>Value<br><input class="widefat" required name="value"></label></p><?php submit_button('Create segment');?></form></section></div></div><?php }
    public function suppressions_page(): void { $this->guard();global $wpdb;$t=APLists_Schema::tables();$rows=$wpdb->get_results("SELECT * FROM {$t['suppressions']} ORDER BY created_at DESC LIMIT 1000",ARRAY_A);?><div class="wrap aplists-admin"><h1>Suppressions</h1><?php $this->notice();?><div class="aplists-columns"><section class="aplists-card"><table class="widefat striped"><thead><tr><th>Email</th><th>Reason</th><th>Date</th></tr></thead><tbody><?php foreach($rows as $row):?><tr><td><?php echo esc_html($row['email']);?></td><td><?php echo esc_html($row['reason']);?></td><td><?php echo esc_html($row['created_at']);?></td></tr><?php endforeach;?></tbody></table></section><section class="aplists-card"><h2>Suppress an address</h2><form method="post" action="<?php echo esc_url(admin_url('admin-post.php'));?>"><input type="hidden" name="action" value="aplists_suppress"><?php wp_nonce_field('aplists_suppress');?><p><input class="widefat" type="email" required name="email" placeholder="email@example.com"></p><p><select name="reason"><option value="manual">Manual</option><option value="complaint">Complaint</option><option value="hard_bounce">Hard bounce</option></select></p><?php submit_button('Suppress address','secondary');?></form></section></div></div><?php }
    public function start_page(): void { $this->guard();?><div class="wrap aplists-admin"><h1>Getting started with apLists</h1><div class="aplists-card"><ol class="aplists-steps"><li><strong>Create a list</strong><span>Choose double opt-in and the reply-to identity.</span></li><li><strong>Add subscribers</strong><span>Add them manually or import a consented CSV.</span></li><li><strong>Publish a form</strong><span>Paste the list shortcode into a page.</span></li><li><strong>Protect consent</strong><span>Confirmation, unsubscribe, and suppressions are handled automatically.</span></li><li><strong>Use your data</strong><span>Export a clean CSV for approved workflows.</span></li></ol><a class="button button-primary" href="<?php echo esc_url(admin_url('admin.php?page=aplists'));?>">Create your first list</a></div></div><?php }

    public function save_list(): void { $this->post_guard('aplists_save_list');$r=self::create_list(['name'=>sanitize_text_field(wp_unslash($_POST['name']??'')),'description'=>sanitize_textarea_field(wp_unslash($_POST['description']??'')),'reply_to'=>sanitize_email(wp_unslash($_POST['reply_to']??'')),'double_opt_in'=>isset($_POST['double_opt_in']) && sanitize_text_field(wp_unslash($_POST['double_opt_in']))==='1']);$this->redirect('aplists',!empty($r['success'])?'list-created':'error'); }
    public function save_segment(): void { $this->post_guard('aplists_save_segment');global $wpdb;$t=APLists_Schema::tables();$list_id=absint($_POST['list_id']??0);$name=sanitize_text_field(wp_unslash($_POST['name']??''));$field=sanitize_key(wp_unslash($_POST['field']??''));$operator=sanitize_key(wp_unslash($_POST['operator']??''));$value=sanitize_text_field(wp_unslash($_POST['value']??''));if(!$name||!self::list_by_id($list_id)||!in_array($field,['status','source','email'],true)||!in_array($operator,['equals','contains'],true))$this->redirect('aplists-segments','error');$now=current_time('mysql',true);$wpdb->insert($t['segments'],['list_id'=>$list_id,'name'=>$name,'description'=>'','subscriber_count'=>0,'created_at'=>$now,'updated_at'=>$now]);$segment_id=(int)$wpdb->insert_id;$wpdb->insert($t['criteria'],['segment_id'=>$segment_id,'field'=>$field,'operator'=>$operator,'value'=>$value,'created_at'=>$now]);$this->redirect('aplists-segments','segment-created'); }
    public function add_subscriber(): void { $this->post_guard('aplists_add_subscriber');$data=['email'=>sanitize_email(wp_unslash($_POST['email']??'')),'first_name'=>sanitize_text_field(wp_unslash($_POST['first_name']??'')),'last_name'=>sanitize_text_field(wp_unslash($_POST['last_name']??'')),'source'=>'admin'];if(isset($_POST['force_subscribed']) && sanitize_text_field(wp_unslash($_POST['force_subscribed']))==='1')$data['force_subscribed']=true;$r=self::upsert_subscriber(absint($_POST['list_id']??0),$data);$this->redirect('aplists-subscribers',!empty($r['success'])?'subscriber-added':'error'); }
    public function suppress(): void { $this->post_guard('aplists_suppress');$ok=self::suppress_email(sanitize_email(wp_unslash($_POST['email']??'')),sanitize_key(wp_unslash($_POST['reason']??'manual')));$this->redirect('aplists-suppressions',$ok?'suppressed':'error'); }
    public function import(): void
    {
        $this->post_guard('aplists_import');
        $list_id = absint($_POST['list_id'] ?? 0);
        $file = $_FILES['csv'] ?? [];
        if (!$list_id || empty($file['tmp_name']) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file((string) $file['tmp_name'])) {
            $this->redirect('aplists-import', 'error');
        }
        try {
            $csv = new SplFileObject((string) $file['tmp_name'], 'r');
            $csv->setCsvControl(',', '"', '');
            $headers = array_map('sanitize_key', $csv->fgetcsv() ?: []);
            if (!in_array('email', $headers, true) || count($headers) !== count(array_unique($headers))) {
                $this->redirect('aplists-import', 'missing-email');
            }
            $processed = 0;
            $errors = 0;
            while (!$csv->eof()) {
                $row = $csv->fgetcsv();
                if ($row === false || $row === [null]) { continue; }
                if (count($row) > count($headers)) { $errors++; continue; }
                $data = array_combine($headers, array_pad($row, count($headers), ''));
                $subscriber = ['email'=>$data['email'] ?? '', 'first_name'=>$data['first_name'] ?? '', 'last_name'=>$data['last_name'] ?? '', 'source'=>'csv_import'];
                if (isset($_POST['consent_confirmed']) && sanitize_text_field(wp_unslash($_POST['consent_confirmed'])) === '1') { $subscriber['force_subscribed'] = true; }
                $r = self::upsert_subscriber($list_id, $subscriber);
                !empty($r['success']) ? $processed++ : $errors++;
            }
        } catch (RuntimeException $error) {
            $this->redirect('aplists-import', 'error');
        }
        update_option('aplists_last_import', ['processed'=>$processed, 'errors'=>$errors, 'at'=>current_time('mysql')], false);
        $this->redirect('aplists-import', 'imported');
    }
    /** Stream a bounded snapshot without inheriting the admin-screen row limit. */
    public static function export_subscribers(int $list_id): Generator
    {
        global $wpdb;
        $t = APLists_Schema::tables();
        $last_id = (int) $wpdb->get_var($wpdb->prepare("SELECT MAX(id) FROM %i WHERE list_id=%d", $t['subscribers'], $list_id));
        $cursor = 0;
        while ($cursor < $last_id) {
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT id,email,first_name,last_name,status,source,consent_at,confirmed_at,unsubscribed_at FROM %i WHERE list_id=%d AND id>%d AND id<=%d ORDER BY id ASC LIMIT 500",
                $t['subscribers'], $list_id, $cursor, $last_id
            ), ARRAY_A);
            if (!$rows) { break; }
            foreach ($rows as $row) {
                $cursor = (int) $row['id'];
                yield $row;
            }
        }
    }

    public function export(): void
    {
        $this->post_guard('aplists_export', false);
        $list_id = absint($_GET['list_id'] ?? 0);
        $list = self::list_by_id($list_id);
        if (!$list) { wp_die('List not found.'); }
        nocache_headers();
        header('Content-Type:text/csv; charset=utf-8');
        header('Content-Disposition:attachment; filename="' . sanitize_file_name($list['name']) . '-subscribers.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['email','first_name','last_name','status','source','consent_at','confirmed_at','unsubscribed_at'], ',', '"', '');
        foreach (self::export_subscribers($list_id) as $row) {
            fputcsv($out, [$row['email'],$row['first_name'],$row['last_name'],$row['status'],$row['source'],$row['consent_at'],$row['confirmed_at'],$row['unsubscribed_at']], ',', '"', '');
        }
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Close the CSV response stream, not a filesystem path.
        fclose($out);
        exit;
    }

    public function shortcode($atts): string { $atts=shortcode_atts(['list'=>'0','title'=>'Join our list','button'=>'Subscribe'],$atts,'aplists_subscribe');$list=self::list_by_id(absint($atts['list']));if(!$list)return '';wp_enqueue_style('aplists-public',plugin_dir_url(APLISTS_FILE).'assets/aplists.css',[],APLISTS_VERSION);$status=sanitize_key(wp_unslash($_GET['aplists_status']??''));ob_start();?><section class="aplists-form-shell"><h2><?php echo esc_html($atts['title']);?></h2><?php if($status):?><p class="aplists-form-message"><?php echo esc_html($status==='check-email'?'Check your email to confirm your subscription.':($status==='subscribed'?'You are subscribed.':'Your request could not be completed.'));?></p><?php endif;?><form method="post" action="<?php echo esc_url(admin_url('admin-post.php'));?>"><input type="hidden" name="action" value="aplists_subscribe"><input type="hidden" name="list_id" value="<?php echo (int)$list['id'];?>"><?php wp_nonce_field('aplists_subscribe_'.$list['id']);?><p class="aplists-hp"><label>Website<input name="website" tabindex="-1" autocomplete="off"></label></p><div class="aplists-name-row"><label>First name<input name="first_name" autocomplete="given-name"></label><label>Last name<input name="last_name" autocomplete="family-name"></label></div><label>Email address<input required type="email" name="email" autocomplete="email"></label><label class="aplists-consent"><input required type="checkbox" name="consent" value="1"> I agree to receive email from <?php echo esc_html(get_bloginfo('name'));?>. I can unsubscribe at any time.</label><button type="submit"><?php echo esc_html($atts['button']);?></button></form></section><?php return (string)ob_get_clean(); }
    public function subscribe(): void
    {
        $list_id = absint($_POST['list_id'] ?? 0);
        $nonce = sanitize_text_field(wp_unslash($_POST['_wpnonce'] ?? ''));
        $website = sanitize_text_field(wp_unslash($_POST['website'] ?? ''));
        $consent = sanitize_text_field(wp_unslash($_POST['consent'] ?? ''));
        if (!wp_verify_nonce($nonce, 'aplists_subscribe_' . $list_id) || $website !== '' || $consent !== '1') {
            $this->public_redirect('error');
        }
        $email = sanitize_email(wp_unslash($_POST['email'] ?? ''));
        $ip = sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'] ?? ''));
        $ip = filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '';
        $r = self::upsert_subscriber($list_id, [
            'email' => $email,
            'first_name' => sanitize_text_field(wp_unslash($_POST['first_name'] ?? '')),
            'last_name' => sanitize_text_field(wp_unslash($_POST['last_name'] ?? '')),
            'source' => 'public_form',
            'ip' => $ip,
        ]);
        if (empty($r['success'])) { $this->public_redirect('error'); }
        if (($r['status'] ?? '') === 'pending') {
            $url = add_query_arg(['aplists_confirm' => $r['confirmation_token']], home_url('/'));
            wp_mail($email, 'Confirm your subscription', "Confirm your subscription:\n\n" . $url . "\n\nIf you did not request this, ignore this email.");
            $this->public_redirect('check-email');
        }
        $this->public_redirect('subscribed');
    }
    public function token_routes(): void { global $wpdb;$t=APLists_Schema::tables();if(!empty($_GET['aplists_confirm'])){$token=sanitize_text_field(wp_unslash($_GET['aplists_confirm']));$id=(int)$wpdb->get_var($wpdb->prepare("SELECT id FROM {$t['subscribers']} WHERE confirmation_token=%s AND status='pending'",$token));if($id){$now=current_time('mysql',true);$wpdb->update($t['subscribers'],['status'=>'subscribed','confirmed_at'=>$now,'confirmation_token'=>'','updated_at'=>$now],['id'=>$id]);$list_id=(int)$wpdb->get_var($wpdb->prepare("SELECT list_id FROM {$t['subscribers']} WHERE id=%d",$id));self::recount($list_id);}wp_die($id?'Subscription confirmed. You may close this page.':'This confirmation link is invalid or has already been used.','apLists',['response'=>$id?200:400]);}if(!empty($_GET['aplists_unsubscribe'])){$token=sanitize_text_field(wp_unslash($_GET['aplists_unsubscribe']));$row=$wpdb->get_row($wpdb->prepare("SELECT email,list_id FROM {$t['subscribers']} WHERE unsubscribe_token=%s",$token),ARRAY_A);if($row){self::suppress_email($row['email'],'unsubscribe','public_link');}wp_die($row?'You have been unsubscribed.':'This unsubscribe link is invalid.','apLists',['response'=>$row?200:400]);} }
    private function guard(): void { if(!current_user_can('manage_options'))wp_die('You do not have permission to manage lists.'); }
    private function post_guard(string $action,bool $post=true): void { $this->guard();check_admin_referer($action); }
    private function redirect(string $page,string $notice): void { wp_safe_redirect(add_query_arg(['page'=>$page,'aplists_notice'=>$notice],admin_url('admin.php')));exit; }
    private function public_redirect(string $status): void { wp_safe_redirect(add_query_arg('aplists_status',$status,wp_get_referer()?:home_url('/')));exit; }
    private function notice(): void { $n=sanitize_key(wp_unslash($_GET['aplists_notice']??''));if(!$n)return;$m=['list-created'=>'List created.','segment-created'=>'Segment created.','subscriber-added'=>'Subscriber saved.','suppressed'=>'Address suppressed.','imported'=>'CSV import completed.','missing-email'=>'The CSV needs an email column.','error'=>'The request could not be completed.'];echo '<div class="notice '.($n==='error'?'notice-error':'notice-success').' is-dismissible"><p>'.esc_html($m[$n]??'Done.').'</p></div>'; }
}
