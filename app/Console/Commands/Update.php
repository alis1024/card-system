<?php
namespace App\Console\Commands; use App\Library\CurlRequest; use function DeepCopy\deep_copy; use Illuminate\Console\Command; use Illuminate\Support\Str; class Update extends Command { protected $signature = 'update {--proxy=} {--proxy-auth=}'; protected $description = 'check update'; public function __construct() { parent::__construct(); } private function download_progress($spf47337, $sp74b29c) { $sp813fa0 = fopen($sp74b29c, 'w+'); if (!$sp813fa0) { return false; } $sp8bd541 = curl_init(); curl_setopt($sp8bd541, CURLOPT_URL, $spf47337); curl_setopt($sp8bd541, CURLOPT_FOLLOWLOCATION, true); curl_setopt($sp8bd541, CURLOPT_RETURNTRANSFER, true); curl_setopt($sp8bd541, CURLOPT_FILE, $sp813fa0); curl_setopt($sp8bd541, CURLOPT_PROGRESSFUNCTION, function ($sp07de66, $sp94bbea, $spa089a4, $sp7f5c41, $sp25da13) { if ($sp94bbea > 0) { echo '    download: ' . sprintf('%.2f', $spa089a4 / $sp94bbea * 100) . '%'; } }); curl_setopt($sp8bd541, CURLOPT_NOPROGRESS, false); curl_setopt($sp8bd541, CURLOPT_HEADER, 0); curl_setopt($sp8bd541, CURLOPT_USERAGENT, 'card update'); if (defined('MY_PROXY')) { $spa6f720 = MY_PROXY; $sp423e37 = CURLPROXY_HTTP; if (strpos($spa6f720, 'http://') || strpos($spa6f720, 'https://')) { $spa6f720 = str_replace('http://', $spa6f720, $spa6f720); $spa6f720 = str_replace('https://', $spa6f720, $spa6f720); $sp423e37 = CURLPROXY_HTTP; } elseif (strpos($spa6f720, 'socks4://')) { $spa6f720 = str_replace('socks4://', $spa6f720, $spa6f720); $sp423e37 = CURLPROXY_SOCKS4; } elseif (strpos($spa6f720, 'socks4a://')) { $spa6f720 = str_replace('socks4a://', $spa6f720, $spa6f720); $sp423e37 = CURLPROXY_SOCKS4A; } elseif (strpos($spa6f720, 'socks5://')) { $spa6f720 = str_replace('socks5://', $spa6f720, $spa6f720); $sp423e37 = CURLPROXY_SOCKS5_HOSTNAME; } curl_setopt($sp8bd541, CURLOPT_PROXY, $spa6f720); curl_setopt($sp8bd541, CURLOPT_PROXYTYPE, $sp423e37); if (defined('MY_PROXY_PASS')) { curl_setopt($sp8bd541, CURLOPT_PROXYUSERPWD, MY_PROXY_PASS); } } curl_exec($sp8bd541); curl_close($sp8bd541); echo '
'; return true; } public function handle() { set_time_limit(0); $spa6f720 = $this->option('proxy'); if (!empty($spa6f720)) { define('MY_PROXY', $spa6f720); } $sp06dd06 = $this->option('proxy-auth'); if (!empty($sp06dd06)) { define('MY_PROXY_PASS', $sp06dd06); } if (getenv('_')) { $spd07be9 = '"' . getenv('_') . '" "' . $_SERVER['PHP_SELF'] . '" '; } else { if (PHP_OS === 'WINNT') { $sp4f3c83 = dirname(php_ini_loaded_file()) . DIRECTORY_SEPARATOR . 'php.exe'; } else { $sp4f3c83 = dirname(php_ini_loaded_file()) . DIRECTORY_SEPARATOR . 'php'; } $spd07be9 = '"' . $sp4f3c83 . '" "' . $_SERVER['PHP_SELF'] . '" '; } exec($spd07be9 . ' cache:clear'); exec($spd07be9 . ' config:clear'); echo '
'; $this->comment('检查更新中...'); $this->info('当前版本: ' . config('app.version')); $spdf4035 = @json_decode(CurlRequest::get('https://raw.githubusercontent.com/Tai7sy/card-system/master/.version'), true); if (!@$spdf4035['version']) { $this->warn('检查更新失败!'); $this->warn('Error: ' . ($spdf4035 ? json_encode($spdf4035) : 'Network error')); goto LABEL_EXIT; } $this->info('最新版本: ' . $spdf4035['version']); $this->info('版本说明: ' . (@$spdf4035['description'] ?? '无')); if (config('app.version') >= $spdf4035['version']) { $this->comment('您的版本已是最新!'); $sp58ae84 = strtolower($this->ask('是否再次更新 (yes/no)', 'no')); if ($sp58ae84 !== 'yes') { goto LABEL_EXIT; } } else { $sp58ae84 = strtolower($this->ask('是否现在更新 (yes/no)', 'no')); if ($sp58ae84 !== 'yes') { goto LABEL_EXIT; } } $sp410706 = realpath(sys_get_temp_dir()); if (strlen($sp410706) < 3) { $this->warn('获取临时目录失败!'); goto LABEL_EXIT; } $sp410706 .= DIRECTORY_SEPARATOR . Str::random(16); if (!mkdir($sp410706) || !is_writable($sp410706) || !is_readable($sp410706)) { $this->warn('临时目录不可读写!'); goto LABEL_EXIT; } if (!function_exists('exec')) { $this->warn('函数 exec 已被禁用, 无法继续更新!'); goto LABEL_EXIT; } if (PHP_OS === 'WINNT') { $sp8d5109 = 'C:\\Program Files\\7-Zip\\7z.exe'; if (!is_file($sp8d5109)) { $sp8d5109 = strtolower($this->ask('未找到7-Zip, 请手动输入7zG.exe路径', $sp8d5109)); } if (!is_file($sp8d5109)) { $this->warn('7-Zip不可用, 请安装7-Zip后重试'); goto LABEL_EXIT; } $sp8d5109 = '"' . $sp8d5109 . '"'; } else { exec('tar --version', $sp414311, $spe2e64e); if ($spe2e64e) { $this->warn('Error: tar --version 
' . join('
', $sp414311)); goto LABEL_EXIT; } } $this->comment('正在下载新版本...'); $sp74b29c = $sp410706 . DIRECTORY_SEPARATOR . 'ka_update_' . Str::random(16) . '.tmp'; if (!$this->download_progress($spdf4035['url'], $sp74b29c)) { $this->warn('写入临时文件失败!'); goto LABEL_EXIT; } $spe98e07 = md5_file($sp74b29c); if ($spe98e07 !== $spdf4035['md5']) { $this->warn('更新文件md5校验失败!, file:' . $spe98e07 . ', require:' . $spdf4035['md5']); goto LABEL_EXIT; } $this->comment('正在解压...'); unset($sp414311); if (PHP_OS === 'WINNT') { exec("{$sp8d5109} x -so {$sp74b29c} | {$sp8d5109} x -aoa -si -ttar -o{$sp410706}", $sp414311, $spe2e64e); } else { exec("tar -zxf {$sp74b29c} -C {$sp410706}", $sp414311, $spe2e64e); } if ($spe2e64e) { $this->warn('Error: 解压失败 
' . join('
', $sp414311)); goto LABEL_EXIT; } $this->comment('正在关闭主站...'); exec($spd07be9 . ' down'); sleep(5); $this->comment(' --> 正在清理旧文件...'); $sp0e795b = base_path(); foreach (array('app', 'bootstrap', 'config', 'public/dist', 'database', 'routes', 'vendor') as $sp5bb4ef) { \File::deleteDirectory($sp0e795b . DIRECTORY_SEPARATOR . $sp5bb4ef); } $this->comment(' --> 正在复制新文件...'); \File::delete($sp410706 . DIRECTORY_SEPARATOR . 'card_dist' . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . 'logo.png'); \File::delete($sp410706 . DIRECTORY_SEPARATOR . 'card_dist' . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . '.htaccess'); \File::delete($sp410706 . DIRECTORY_SEPARATOR . 'card_dist' . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'web.config'); \File::delete($sp410706 . DIRECTORY_SEPARATOR . 'card_dist' . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'nginx.conf'); \File::delete($sp410706 . DIRECTORY_SEPARATOR . 'card_dist' . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'robots.txt'); \File::copyDirectory($sp410706 . DIRECTORY_SEPARATOR . 'card_system_free_dist', $sp0e795b); $this->comment(' --> 正在创建缓存...'); exec($spd07be9 . ' cache:clear'); exec($spd07be9 . ' route:cache'); exec($spd07be9 . ' config:cache'); $this->comment(' --> 正在更新数据库...'); exec($spd07be9 . ' migrate'); if (PHP_OS === 'WINNT') { echo '
'; $this->alert('请注意手动设置目录权限'); $this->comment('    storage 可读可写             '); $this->comment('    bootstrap/cache/ 可读可写    '); echo '

'; } else { $this->comment(' --> 正在设置目录权限...'); exec('rm -rf storage/framework/cache/data/*'); exec('chmod -R 777 storage/'); exec('chmod -R 777 bootstrap/cache/'); } $this->comment('正在启用主站...'); exec($spd07be9 . ' up'); exec($spd07be9 . ' queue:restart'); $sp394b32 = true; LABEL_EXIT: if (isset($sp410706) && strlen($sp410706) > 19) { $this->comment('清理临时目录...'); \File::deleteDirectory($sp410706); } if (isset($sp394b32) && $sp394b32) { $this->info('更新成功!'); } if (PHP_OS === 'WINNT') { } else { exec('rm -rf storage/framework/cache/data/*'); exec('chmod -R 777 storage/'); exec('chmod -R 777 bootstrap/cache/'); } echo '
'; die; } }