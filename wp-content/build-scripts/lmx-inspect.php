<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
require __DIR__ . '/lib.php';
echo json_encode(['url'=>home_url(),'plugins'=>get_option('active_plugins'),'counts'=>wp_count_posts('product'),'build_products'=>bstate('products'),'cats'=>bstate('cats')], JSON_PRETTY_PRINT), "\n";
foreach (get_posts(['post_type'=>['page','elementor_library'],'posts_per_page'=>-1]) as $post) {
    $data=get_post_meta($post->ID,'_elementor_data',true);
    preg_match_all('/"widgetType":"([^"]+)"/',$data,$matches);
    echo json_encode(['id'=>$post->ID,'title'=>$post->post_title,'type'=>$post->post_type,'widgets'=>array_count_values($matches[1])],JSON_UNESCAPED_UNICODE),"\n";
}
$dir = 'C:/xampp/boutique-migration';
if (!is_dir($dir)) mkdir($dir, 0700, true);
if (!file_exists($dir.'/before-import.sql')) {
    $cnf = $dir.'/backup.cnf';
    file_put_contents($cnf, "[client]\nuser=".DB_USER."\npassword=\"".addcslashes(DB_PASSWORD, "\\\"")."\"\nhost=".DB_HOST."\n");
    exec('C:\\xampp\\mysql\\bin\\mysqldump.exe --defaults-extra-file='.escapeshellarg($cnf).' --single-transaction --result-file='.escapeshellarg($dir.'/before-import.sql').' '.escapeshellarg(DB_NAME).' 2>&1', $out, $code);
    unlink($cnf);
    if ($code) throw new Exception(implode("\n", $out));
    echo "Database backup saved\n";
}
