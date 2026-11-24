<?php
/** Apply visually reviewed background-only PNGs while retaining attachment IDs. */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
define('WP_HTTP_BLOCK_EXTERNAL', true);
define('WP_ACCESSIBLE_HOSTS', 'localhost,127.0.0.1');
define('DISABLE_WP_CRON', true);
ini_set('memory_limit','1024M');
echo "Loading local WordPress...\n";flush();
require __DIR__.'/lib.php';
echo "WordPress loaded.\n";flush();
set_time_limit(0);
$root='D:/boutique-migration-work/watermark-audit/background-cleanup';
$manifest=json_decode(file_get_contents($root.'/manifest.json'),true,512,JSON_THROW_ON_ERROR);
$maps=get_option('lmx_image_map',[]);
for($i=0;$i<4;$i++) $maps=array_replace($maps,get_option('lmx_image_map_'.$i,[]));
function catalog_snapshot() {
    global $wpdb;
    $posts=$wpdb->get_results("SELECT ID,post_title,post_content,post_excerpt,post_status FROM {$wpdb->posts} WHERE post_type IN ('product','product_variation') ORDER BY ID",ARRAY_A);
    $meta=$wpdb->get_results("SELECT m.post_id,m.meta_key,m.meta_value FROM {$wpdb->postmeta} m JOIN {$wpdb->posts} p ON p.ID=m.post_id WHERE p.post_type IN ('product','product_variation') ORDER BY m.meta_id",ARRAY_A);
    return [$posts,$meta];
}
function catalog_fingerprint() { return hash('sha256',serialize(catalog_snapshot())); }
$stateFile=$root.'/wordpress-state.json';
$state=file_exists($stateFile)?json_decode(file_get_contents($stateFile),true):['catalog_before'=>catalog_fingerprint(),'attachments'=>[]];
$runBefore=catalog_snapshot();
if (($argv[1]??'apply')==='rollback') {
    if(is_file($root.'/retired-files.json')) {
        foreach(json_decode(file_get_contents($root.'/retired-files.json'),true) as $file) {
            if(!is_file($file['archive_file']) || hash_file('sha256',$file['archive_file'])!==$file['sha256']) throw new Exception('Archive checksum mismatch');
            if(!copy($file['archive_file'],$file['public_file'])) throw new Exception('Archive restore failed');
        }
        $rules=ABSPATH.'wp-content/uploads/lmx-catalog/.htaccess';
        if(is_file($rules)) file_put_contents($rules,preg_replace('/# BEGIN LMX cleaned image redirects.*?# END LMX cleaned image redirects\s*/s','',file_get_contents($rules)));
    }
    foreach($state['attachments'] as $id=>$old) {
        if(!is_file($old['file'])) throw new Exception('Missing original '.$id);
        update_attached_file($id,$old['file']);
        wp_update_post(['ID'=>$id,'post_mime_type'=>$old['mime']]);
        wp_update_attachment_metadata($id,$old['metadata']);
        delete_post_meta($id,'_lmx_background_cleanup');
        clean_post_cache($id);
    }
    wp_cache_flush();
    echo 'Restored '.count($state['attachments'])." original attachments.\n";
    exit;
}
// Complete preflight and durable rollback state before modifying any attachment.
foreach($manifest as $row) {
    if($row['status']!=='prepared') continue;
    $id=$maps[$row['url']]??0;
    if(!$id || !get_post($id)) throw new Exception('Missing attachment: '.$row['url']);
    if(hash_file('sha256',$row['clean_file'])!==$row['clean_sha256']) throw new Exception('Clean file hash mismatch');
    if(!isset($state['attachments'][$id])) {
        $file=get_attached_file($id);
        if(hash_file('sha256',$file)!==$row['source_sha256']) throw new Exception('Original mismatch '.$id);
        $state['attachments'][$id]=['file'=>$file,'attached_file'=>get_post_meta($id,'_wp_attached_file',true),'metadata'=>wp_get_attachment_metadata($id),'mime'=>get_post_mime_type($id),'url'=>wp_get_attachment_url($id),'source_url'=>$row['url']];
    }
}
file_put_contents($stateFile,json_encode($state,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
$report=[];
foreach($manifest as $row) {
    if($row['status']!=='prepared') continue;
    $id=$maps[$row['url']];
    $dest=dirname($state['attachments'][$id]['file']).'/'.basename($row['clean_file']);
    $existing=wp_get_attachment_metadata($id);
    $alreadyInstalled=get_attached_file($id)===$dest && is_file($dest) && hash_file('sha256',$dest)===$row['clean_sha256'] && !empty($existing['sizes']);
    if (!$alreadyInstalled) {
    if(!copy($row['clean_file'],$dest)) throw new Exception('Copy failed');
    if(hash_file('sha256',$dest)!==$row['clean_sha256']) throw new Exception('Installed hash mismatch');
    update_attached_file($id,$dest);
    $result=wp_update_post(['ID'=>$id,'post_mime_type'=>'image/png'],true);
    if(is_wp_error($result)) throw new Exception($result->get_error_message());
    $metadata=wp_generate_attachment_metadata($id,$dest);
    if(empty($metadata['width']) || empty($metadata['sizes'])) throw new Exception('Thumbnail generation failed');
    wp_update_attachment_metadata($id,$metadata);
    update_post_meta($id,'_lmx_background_cleanup',['original'=>$state['attachments'][$id]['attached_file'],'sha256'=>$row['clean_sha256'],'mask'=>$row['mask_file'],'outside_mask_changed_pixels'=>0]);
    clean_post_cache($id);
    } else { $metadata=$existing; }
    $report[]=['id'=>$id,'url'=>wp_get_attachment_url($id),'file'=>$dest,'width'=>$metadata['width'],'height'=>$metadata['height'],'sizes'=>$metadata['sizes'],'source_url'=>$row['url']];
    echo 'Updated attachment '.$id.PHP_EOL;flush();
}
wp_cache_flush();
$runAfter=catalog_snapshot();
function without_elementor_cache($snapshot) {
    $snapshot[1]=array_values(array_filter($snapshot[1],fn($row)=>!in_array($row['meta_key'],['_elementor_page_assets','_elementor_global_class_usage_indexed'],true)));
    return $snapshot;
}
if(without_elementor_cache($runBefore)!==without_elementor_cache($runAfter)) throw new Exception('Product data unexpectedly changed');
$result=['installed'=>count($report),'catalog_unchanged'=>true,'attachments'=>$report];
file_put_contents($root.'/installed.json',json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
echo json_encode(['installed'=>count($report),'catalog_unchanged'=>true]).PHP_EOL;
